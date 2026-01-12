<?php
// bpms/api/save_draft.php
// Purpose: Autosave scores and comments as the judge types.
// Handling: Uses "Upsert" (Insert or Update) logic.

session_start();
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/core/guard.php';

requireLogin();
if ($_SESSION['role'] !== 'Judge') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Read JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['error' => 'Invalid JSON']);
    exit();
}

$judge_id = $_SESSION['user_id'];
$contestant_id = (int)$data['contestant_id'];
$segment_id_input = (int)($data['segment_id'] ?? 0);

$conn->begin_transaction();
try {
    // OPTIMIZATION: Fetch all Criteria Limits in ONE Query
    // This ensures we validate scores against the REAL max_score in the database
    $criteria_limits = [];
    if (isset($data['scores']) && !empty($data['scores'])) {
        $crit_ids = array_keys($data['scores']);
        
        // Safety check: ensure IDs are integers
        $crit_ids = array_map('intval', $crit_ids);
        
        if (!empty($crit_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($crit_ids), '?'));
            
            // Matches 'criteria' table
            $sql = "SELECT id, segment_id, max_score FROM criteria WHERE id IN ($ids_placeholder)";
            $stmt_limits = $conn->prepare($sql);
            
            // Dynamically bind params
            $types = str_repeat('i', count($crit_ids));
            $stmt_limits->bind_param($types, ...$crit_ids);
            $stmt_limits->execute();
            $res = $stmt_limits->get_result();
            
            while ($row = $res->fetch_assoc()) {
                $criteria_limits[$row['id']] = $row;
            }
        }
    }

    // 2. Save Scores
    if (isset($data['scores'])) {
        $stmt = $conn->prepare("INSERT INTO scores (criteria_id, judge_id, contestant_id, score_value) 
                                VALUES (?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE score_value = VALUES(score_value)");
        
        foreach ($data['scores'] as $crit_id => $val) {
            $crit_id = (int)$crit_id;
            
            // Security: Skip if this criteria ID doesn't exist in our DB check
            if (!isset($criteria_limits[$crit_id])) continue;

            $limit_info = $criteria_limits[$crit_id];
            $max_limit = (float)$limit_info['max_score'];
            $score_val = (float)$val;

            // Server-Side Validation: Cap the score
            if ($score_val > $max_limit) $score_val = $max_limit;
            if ($score_val < 0) $score_val = 0;

            $stmt->bind_param("iiid", $crit_id, $judge_id, $contestant_id, $score_val);
            
            // Note: This might fail if the Judge is already 'Submitted' due to DB Triggers.
            // The catch block will handle that.
            $stmt->execute();
        }
    }

    // 3. Save Comment
    if (isset($data['comment'])) {
        $stmt_c = $conn->prepare("INSERT INTO judge_comments (segment_id, judge_id, contestant_id, comment) 
                                  VALUES (?, ?, ?, ?) 
                                  ON DUPLICATE KEY UPDATE comment = VALUES(comment)");
        
        $comment_text = trim($data['comment']);
        $stmt_c->bind_param("iiis", $segment_id_input, $judge_id, $contestant_id, $comment_text);
        $stmt_c->execute();
    }

    $conn->commit();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    $conn->rollback();
    
    // Check if the error came from our DB Triggers (Preventing changes after submit)
    if ($conn->errno == 1644 || strpos($e->getMessage(), 'DENIED') !== false) {
        echo json_encode(['error' => 'Locked: You have already submitted these scores.']);
    } else {
        // Log generic errors internally
        error_log("Save Draft Error: " . $e->getMessage()); 
        echo json_encode(['error' => 'Save failed']); 
    }
}
?>