<?php
// bpms/api/submit_scores.php
session_start();
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/core/guard.php';

header('Content-Type: application/json');

// 1. Security: Only Judges
// Note: guard.php usually handles redirects, but for API we return JSON
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Judge') {
    echo json_encode(['error' => 'Unauthorized Access']);
    exit();
}

// 2. Read JSON Input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['round_id'])) {
    echo json_encode(['error' => 'Invalid Data Payload']);
    exit;
}

$judge_id = $_SESSION['user_id'];
$round_id = (int)$data['round_id'];
$all_scores = $data['all_scores'] ?? [];

// 3. Logic Check: Is round valid & Active?
$stmt = $conn->prepare("SELECT status FROM rounds WHERE id = ?");
$stmt->bind_param("i", $round_id);
$stmt->execute();
$res = $stmt->get_result();
$round = $res->fetch_assoc();

if (!$round || $round['status'] !== 'Active') {
    echo json_encode(['error' => 'Round is not Active. Submission denied.']);
    exit;
}

// 4. TRANSACTION
$conn->begin_transaction();
try {
    // A. SAVE SCORES
    if (!empty($all_scores)) {
        $stmt_score = $conn->prepare("INSERT INTO scores (criteria_id, judge_id, contestant_id, score_value) 
                                      VALUES (?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE score_value = VALUES(score_value)");
        
        foreach ($all_scores as $cid => $criteria_list) {
            foreach ($criteria_list as $crit_id => $val) {
                // Ensure types
                $c_id = (int)$cid;
                $cr_id = (int)$crit_id;
                $score = (float)$val;
                
                // Bind: criteria_id (i), judge_id (i), contestant_id (i), score_value (d)
                $stmt_score->bind_param("iiid", $cr_id, $judge_id, $c_id, $score);
                if (!$stmt_score->execute()) {
                    throw new Exception("Score Save Failed: " . $stmt_score->error);
                }
            }
        }
    }

    // B. LOCK THE ROUND (Fix Applied Here)
    // We use the correct column 'submitted_at' which matches your DB schema
    $stmt_lock = $conn->prepare("INSERT INTO judge_round_status (round_id, judge_id, status, submitted_at) 
                                 VALUES (?, ?, 'Submitted', NOW()) 
                                 ON DUPLICATE KEY UPDATE status = 'Submitted', submitted_at = NOW()");
    
    // FIX: Order must be Round ID then Judge ID to match the SQL VALUES (?, ?)
    $stmt_lock->bind_param("ii", $round_id, $judge_id);
    
    if (!$stmt_lock->execute()) {
        throw new Exception("Locking Failed: " . $stmt_lock->error);
    }

    // C. COMMIT
    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
}
?>