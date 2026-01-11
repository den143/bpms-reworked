<?php
// bpms/api/submit_scores.php
session_start();
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/core/guard.php';

// 1. Security: Only Judges
requireLogin();
if ($_SESSION['role'] !== 'Judge') {
    header("Location: ../public/index.php");
    exit();
}

// 2. Security: Ensure this is a POST request (Fixes CSRF)
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
    $stmt = $conn->prepare("SELECT status FROM rounds WHERE id = ?");
    $stmt->bind_param("i", $round_id);
    $stmt->execute();
    $round = $stmt->get_result()->fetch_assoc();

    // [CRITICAL SECURITY FIX]
    // If the Manager has already clicked "Lock", reject this submission.
    if (!$round || $round['status'] !== 'Active') {
        throw new Exception("LOCKED: This round is closed. Scores can no longer be submitted.");
    }

    // 4. The Lock (Mark Judge as Submitted)
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
    // Log the actual error internally, show message to user
    error_log($e->getMessage()); 
    header("Location: ../public/judge_dashboard.php?error=" . urlencode($e->getMessage()));
    exit();
}