<?php
// bpms/api/submit_scores.php
// Purpose: Finalizes a judge's scorecard and locks them out.

session_start();
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/core/guard.php';

// 1. Security: Only Judges
requireLogin();
if ($_SESSION['role'] !== 'Judge') {
    header("Location: ../public/index.php");
    exit();
}

// 2. Security: Ensure this is a POST request (CSRF Protection)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Method not allowed");
}

$round_id = (int)($_POST['round_id'] ?? 0);
$judge_id = $_SESSION['user_id'];

if ($round_id <= 0) {
    header("Location: ../public/judge_dashboard.php?error=Invalid Round");
    exit();
}

try {
    // 3. Logic Check: Is round active?
    $stmt = $conn->prepare("SELECT status, event_id FROM rounds WHERE id = ?");
    $stmt->bind_param("i", $round_id);
    $stmt->execute();
    $round = $stmt->get_result()->fetch_assoc();

    // Critical Check: If Manager locked it, stop here.
    if (!$round || $round['status'] !== 'Active') {
        throw new Exception("LOCKED: This round is closed. Scores can no longer be submitted.");
    }
    
    $event_id = $round['event_id'];

    // 4. THE COMPLETION CHECK (New Feature)
    
    // A. Count Active Contestants
    $stmt_c = $conn->prepare("SELECT COUNT(*) as cnt FROM event_contestants WHERE event_id = ? AND status IN ('Active', 'Qualified') AND is_deleted = 0");
    $stmt_c->bind_param("i", $event_id);
    $stmt_c->execute();
    $contestant_count = $stmt_c->get_result()->fetch_assoc()['cnt'];

    // B. Count Active Criteria for this Round
    // Joins 'criteria' -> 'segments' -> 'rounds'
    $stmt_crit = $conn->prepare("
        SELECT COUNT(c.id) as cnt 
        FROM criteria c 
        JOIN segments s ON c.segment_id = s.id 
        WHERE s.round_id = ? AND s.is_deleted = 0 AND c.is_deleted = 0
    ");
    $stmt_crit->bind_param("i", $round_id);
    $stmt_crit->execute();
    $criteria_count = $stmt_crit->get_result()->fetch_assoc()['cnt'];

    // C. Calculate Expected Scores
    $expected_total = $contestant_count * $criteria_count;

    // D. Count Actual Scores submitted by this Judge
    // Note: Scores table does not have round_id, so we join up to check.
    $stmt_actual = $conn->prepare("
        SELECT COUNT(sc.id) as cnt 
        FROM scores sc
        JOIN criteria c ON sc.criteria_id = c.id
        JOIN segments s ON c.segment_id = s.id
        WHERE s.round_id = ? AND sc.judge_id = ?
    ");
    $stmt_actual->bind_param("ii", $round_id, $judge_id);
    $stmt_actual->execute();
    $actual_total = $stmt_actual->get_result()->fetch_assoc()['cnt'];

    // E. Validation
    if ($actual_total < $expected_total) {
        $missing = $expected_total - $actual_total;
        throw new Exception("INCOMPLETE: You are missing $missing score(s). Please score ALL contestants in ALL criteria before submitting.");
    }

    // 5. THE LOCK (Mark Judge as Submitted)
    
    $stmt_status = $conn->prepare("
        INSERT INTO judge_round_status (round_id, judge_id, status, submitted_at) 
        VALUES (?, ?, 'Submitted', NOW()) 
        ON DUPLICATE KEY UPDATE status = 'Submitted', submitted_at = NOW()
    ");
    
    $stmt_status->bind_param("ii", $round_id, $judge_id);
    
    if ($stmt_status->execute()) {
        header("Location: ../public/judge_dashboard.php?success=locked");
        exit();
    } else {
        throw new Exception("Database error: Could not save submission status."); 
    }

} catch (Exception $e) {
    // Log the actual error internally, show friendly message to user
    error_log("Submit Error: " . $e->getMessage()); 
    header("Location: ../public/judge_dashboard.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>