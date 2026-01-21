<?php
// Purpose: Backend controller for managing Scoring Criteria and Segments.
// Handles structural validation (weights/scores) and prevents changes during active rounds.

// Enable Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

// --- HELPER FUNCTIONS ---

// Logic: Prevent changes if the round is currently live or finished.
function checkRoundLock($conn, $round_id) {
    $stmt = $conn->prepare("SELECT status, title FROM rounds WHERE id = ?");
    $stmt->bind_param("i", $round_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    
    if ($res && ($res['status'] === 'Active' || $res['status'] === 'Completed')) {
        header("Location: ../public/criteria.php?round_id=$round_id&error=Action Denied: Round is {$res['status']}.");
        exit();
    }
}

// Logic: Ensure Total Segment Weights do not exceed 100%.
function validateSegmentWeight($conn, $round_id, $new_weight, $exclude_segment_id = null) {
    $sql = "SELECT SUM(weight_percent) as total FROM segments WHERE round_id = ? AND is_deleted = 0";
    if ($exclude_segment_id) $sql .= " AND id != $exclude_segment_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $round_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $current_total = (float)($res['total'] ?? 0);
    
    if (($current_total + $new_weight) > 100) {
        return "Validation Error: Total weight cannot exceed 100%. Current: $current_total%, You tried adding: $new_weight%.";
    }
    return true;
}

// Logic: Ensure Total Criteria Scores do not exceed 100 points.
function validateCriteriaScore($conn, $segment_id, $new_score, $exclude_crit_id = null) {
    $sql = "SELECT SUM(max_score) as total FROM criteria WHERE segment_id = ? AND is_deleted = 0";
    if ($exclude_crit_id) $sql .= " AND id != $exclude_crit_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $segment_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $current_total = (float)($res['total'] ?? 0);
    
    if (($current_total + $new_score) > 100) {
        return "Validation Error: Total points cannot exceed 100. Current: $current_total, You tried adding: $new_score.";
    }
    return true;
}

// Logic: Ensure ordering numbers (1, 2, 3...) are unique for display purposes.
function validateUniqueOrder($conn, $type, $parent_id, $order, $exclude_id = null) {
    if ($type === 'segment') {
        $sql = "SELECT title FROM segments WHERE round_id = ? AND ordering = ? AND is_deleted = 0";
        if ($exclude_id) $sql .= " AND id != $exclude_id";
    } else {
        $sql = "SELECT title FROM criteria WHERE segment_id = ? AND ordering = ? AND is_deleted = 0";
        if ($exclude_id) $sql .= " AND id != $exclude_id";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $parent_id, $order);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $existing = $res->fetch_assoc();
        return "Order #$order is already used by '{$existing['title']}'. Please choose a different order number.";
    }
    return true;
}


// PART 1: SEGMENT MANAGEMENT (Add / Update / Delete)

// ADD SEGMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_segment') {
    $round_id = (int)$_POST['round_id'];
    checkRoundLock($conn, $round_id); 

    $title    = trim($_POST['title']);
    $desc     = trim($_POST['description']);
    $weight   = (float)$_POST['weight_percentage']; 
    $order    = (int)$_POST['ordering'];

    if ($weight <= 0) {
        header("Location: ../public/criteria.php?round_id=$round_id&error=Weight must be a positive number.");
        exit();
    }

    $checkW = validateSegmentWeight($conn, $round_id, $weight);
    if ($checkW !== true) { header("Location: ../public/criteria.php?round_id=$round_id&error=" . urlencode($checkW)); exit(); }

    $checkO = validateUniqueOrder($conn, 'segment', $round_id, $order);
    if ($checkO !== true) { header("Location: ../public/criteria.php?round_id=$round_id&error=" . urlencode($checkO)); exit(); }

    $stmt = $conn->prepare("INSERT INTO segments (round_id, title, description, weight_percent, ordering) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) die("Database Error: " . $conn->error);
    $stmt->bind_param("issdi", $round_id, $title, $desc, $weight, $order);

    if ($stmt->execute()) {
        header("Location: ../public/criteria.php?round_id=$round_id&success=Segment added");
    } else {
        header("Location: ../public/criteria.php?round_id=$round_id&error=Failed to add segment");
    }
    exit();
}

// UPDATE SEGMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_segment') {
    $seg_id   = (int)$_POST['segment_id'];
    $round_id = (int)$_POST['round_id'];
    checkRoundLock($conn, $round_id);

    $title    = trim($_POST['title']);
    $desc     = trim($_POST['description']);
    $weight   = (float)$_POST['weight_percentage'];
    $order    = (int)$_POST['ordering'];

    if ($weight <= 0) {
        header("Location: ../public/criteria.php?round_id=$round_id&error=Weight must be a positive number.");
        exit();
    }

    $checkW = validateSegmentWeight($conn, $round_id, $weight, $seg_id);
    if ($checkW !== true) { header("Location: ../public/criteria.php?round_id=$round_id&error=" . urlencode($checkW)); exit(); }

    $checkO = validateUniqueOrder($conn, 'segment', $round_id, $order, $seg_id);
    if ($checkO !== true) { header("Location: ../public/criteria.php?round_id=$round_id&error=" . urlencode($checkO)); exit(); }

    $stmt = $conn->prepare("UPDATE segments SET title=?, description=?, weight_percent=?, ordering=? WHERE id=?");
    $stmt->bind_param("ssdii", $title, $desc, $weight, $order, $seg_id);

    if ($stmt->execute()) {
        header("Location: ../public/criteria.php?round_id=$round_id&success=Segment updated");
    } else {
        header("Location: ../public/criteria.php?round_id=$round_id&error=Update failed");
    }
    exit();
}

// DELETE SEGMENT (Soft Delete - Fixed & Cascading)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_segment') {
    $id = (int)$_POST['segment_id'];
    $r_id = (int)$_POST['round_id'];
    
    checkRoundLock($conn, $r_id);

    // Safety: Check if scores exist before deleting
    $check = $conn->prepare("SELECT s.id FROM scores s JOIN criteria c ON s.criteria_id = c.id WHERE c.segment_id = ? LIMIT 1");
    $check->bind_param("i", $id);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        header("Location: ../public/criteria.php?round_id=$r_id&error=Cannot delete: Scores exist.");
        exit();
    }

    // Use Prepared Statement
    $stmt = $conn->prepare("UPDATE segments SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
         if ($stmt->affected_rows > 0) {
             // UPDATE: Automatically soft-delete child criteria to keep data clean
             $stmt_criteria = $conn->prepare("UPDATE criteria SET is_deleted = 1 WHERE segment_id = ?");
             $stmt_criteria->bind_param("i", $id);
             $stmt_criteria->execute();

             header("Location: ../public/criteria.php?round_id=$r_id&success=Segment deleted");
         } else {
             // If affected_rows is 0, it means the ID wasn't found (or was already deleted)
             header("Location: ../public/criteria.php?round_id=$r_id&error=Segment not found or already deleted");
         }
    } else {
         header("Location: ../public/criteria.php?round_id=$r_id&error=Delete Error: " . $stmt->error);
    }
    exit();
}


// PART 2: CRITERIA MANAGEMENT (Add / Update / Delete)

// ADD CRITERIA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_criteria') {
    $segment_id = (int)$_POST['segment_id'];
    $r_id       = (int)$_POST['round_id'];
    checkRoundLock($conn, $r_id);

    $title      = trim($_POST['title']);
    $desc       = trim($_POST['description']); 
    $max_score  = (float)$_POST['max_score'];
    $order      = (int)$_POST['ordering'];

    if ($max_score <= 0) {
        header("Location: ../public/criteria.php?round_id=$r_id&error=Max score must be a positive number.");
        exit();
    }

    $checkS = validateCriteriaScore($conn, $segment_id, $max_score);
    if ($checkS !== true) { header("Location: ../public/criteria.php?round_id=$r_id&error=" . urlencode($checkS)); exit(); }

    $checkO = validateUniqueOrder($conn, 'criteria', $segment_id, $order);
    if ($checkO !== true) { header("Location: ../public/criteria.php?round_id=$r_id&error=" . urlencode($checkO)); exit(); }

    $stmt = $conn->prepare("INSERT INTO criteria (segment_id, title, description, max_score, ordering) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) die("Database Error: " . $conn->error);
    $stmt->bind_param("issdi", $segment_id, $title, $desc, $max_score, $order);

    if ($stmt->execute()) {
        header("Location: ../public/criteria.php?round_id=$r_id&success=Criteria added");
    } else {
        header("Location: ../public/criteria.php?round_id=$r_id&error=Failed to add criteria");
    }
    exit();
}

// UPDATE CRITERIA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_criteria') {
    $crit_id    = (int)$_POST['criteria_id'];
    $r_id       = (int)$_POST['round_id'];
    checkRoundLock($conn, $r_id);
    
    $segCheck = $conn->query("SELECT segment_id FROM criteria WHERE id = $crit_id")->fetch_assoc();
    $segment_id = $segCheck['segment_id'];

    $title      = trim($_POST['title']);
    $desc       = trim($_POST['description']); 
    $max_score  = (float)$_POST['max_score'];
    $order      = (int)$_POST['ordering'];

    if ($max_score <= 0) {
        header("Location: ../public/criteria.php?round_id=$r_id&error=Max score must be a positive number.");
        exit();
    }

    $checkS = validateCriteriaScore($conn, $segment_id, $max_score, $crit_id);
    if ($checkS !== true) { header("Location: ../public/criteria.php?round_id=$r_id&error=" . urlencode($checkS)); exit(); }

    $checkO = validateUniqueOrder($conn, 'criteria', $segment_id, $order, $crit_id);
    if ($checkO !== true) { header("Location: ../public/criteria.php?round_id=$r_id&error=" . urlencode($checkO)); exit(); }

    $stmt = $conn->prepare("UPDATE criteria SET title=?, description=?, max_score=?, ordering=? WHERE id=?");
    $stmt->bind_param("ssdii", $title, $desc, $max_score, $order, $crit_id);

    if ($stmt->execute()) {
        header("Location: ../public/criteria.php?round_id=$r_id&success=Criteria updated");
    } else {
        header("Location: ../public/criteria.php?round_id=$r_id&error=Update failed");
    }
    exit();
}

// DELETE CRITERIA (Soft Delete - Fixed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_criteria') {
    $id = (int)$_POST['criteria_id'];
    $r_id = (int)$_POST['round_id'];
    checkRoundLock($conn, $r_id);

    $check = $conn->prepare("SELECT id FROM scores WHERE criteria_id = ? LIMIT 1");
    $check->bind_param("i", $id);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        header("Location: ../public/criteria.php?round_id=$r_id&error=Cannot delete: Scores exist.");
        exit();
    }
    
    // Use Prepared Statement
    $stmt = $conn->prepare("UPDATE criteria SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
         if ($stmt->affected_rows > 0) {
             header("Location: ../public/criteria.php?round_id=$r_id&success=Criteria deleted");
         } else {
             // If 0 rows affected, it means the ID was not found or was already 1.
             header("Location: ../public/criteria.php?round_id=$r_id&error=Criteria not found or already deleted");
         }
    } else {
         header("Location: ../public/criteria.php?round_id=$r_id&error=Delete Error: " . $stmt->error);
    }
    exit();
}
?>