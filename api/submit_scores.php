<?php
// bpms/api/submit_scores.php
// Purpose: Receive ALL scores in bulk from Local Storage, save them, and lock the round.

session_start();
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/core/guard.php';

// 1. Security: Only Judges
requireLogin();
if ($_SESSION['role'] !== 'Judge') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// 2. Read JSON Input (Since we are sending data via fetch)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['round_id'])) {
    echo json_encode(['error' => 'Invalid Data Payload']);
    exit();
}

$judge_id = $_SESSION['user_id'];
$round_id = (int)$data['round_id'];
$all_scores = $data['all_scores'] ?? []; // This is the merged object from LocalStorage

// 3. Logic Check: Is round valid?
$stmt = $conn->prepare("SELECT status FROM rounds WHERE id = ?");
$stmt->bind_param("i", $round_id);
$stmt->execute();
$round = $stmt->get_result()->fetch_assoc();

if (!$round || $round['status'] !== 'Active') {
    echo json_encode(['error' => 'LOCKED: This round is closed.']);
    exit();
}

// 4. TRANSACTION: Bulk Save + Lock
$conn->begin_transaction();
try {
    // A. SAVE SCORES (Only if data exists)
    if (!empty($all_scores)) {
        // Prepared statement for saving scores (Upsert: Insert or Update)
        $stmt_score = $conn->prepare("INSERT INTO scores (criteria_id, judge_id, contestant_id, score_value) 
                                      VALUES (?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE score_value = VALUES(score_value)");
        
        foreach ($all_scores as $contestant_id => $criteria_list) {
            $contestant_id = (int)$contestant_id;
            
            // Loop through each criteria for this contestant
            foreach ($criteria_list as $crit_id => $val) {
                $crit_id = (int)$crit_id;
                $score = (float)$val;
                
                // Validate score range? (Optional, but good practice)
                if ($score < 0) $score = 0; 
                
                $stmt_score->bind_param("iiid", $crit_id, $judge_id, $contestant_id, $score);
                $stmt_score->execute();
            }
        }
    }

    // B. LOCK THE ROUND (Mark as Submitted)
    $stmt_lock = $conn->prepare("INSERT INTO judge_round_status (round_id, judge_id, status, submitted_at) 
                                 VALUES (?, ?, 'Submitted', NOW()) 
                                 ON DUPLICATE KEY UPDATE status = 'Submitted', submitted_at = NOW()");
    $stmt_lock->bind_param("ii", $judge_id, $round_id);
    $stmt_lock->execute();

    // C. COMMIT
    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    // Log error internally
    error_log("Submit Score Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
}
?>