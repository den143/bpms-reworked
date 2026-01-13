<?php
// Purpose: Handles all backend logic for the Manage Activities module.

// Enable Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// SECURITY: Gatekeeper Check
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');

require_once __DIR__ . '/../app/config/database.php';

// LOGIC: TIME OVERLAP CHECKER
function checkOverlap($conn, $event_id, $date, $start, $end, $exclude_id = null) {
    $sql = "SELECT title, start_time, end_time FROM event_activities 
            WHERE event_id = ? 
            AND activity_date = ? 
            AND is_deleted = 0 
            AND ((? < end_time) AND (? > start_time))";
    
    if ($exclude_id) {
        $sql .= " AND id != " . (int)$exclude_id;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $event_id, $date, $start, $end);
    $stmt->execute();
    return $stmt->get_result();
}

// ACTION 1: ADD NEW ACTIVITY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    
    $event_id = (int)$_POST['event_id'];
    $title    = trim($_POST['title']);
    $venue    = trim($_POST['venue']);
    $date     = $_POST['activity_date'];
    $start    = $_POST['start_time'];
    $end      = $_POST['end_time'];
    $desc     = trim($_POST['description']);
    $force    = isset($_POST['force_save']) ? (int)$_POST['force_save'] : 0; 

    if (strtotime($start) >= strtotime($end)) {
        header("Location: ../public/activities.php?error=End time must be after Start time.");
        exit();
    }

    if ($force === 0) {
        $conflicts = checkOverlap($conn, $event_id, $date, $start, $end);
        
        if ($conflicts->num_rows > 0) {
            $c = $conflicts->fetch_assoc();
            $msg = "Warning: This overlaps with '{$c['title']}' ({$c['start_time']} - {$c['end_time']}).";
            $repopulate = http_build_query($_POST);
            header("Location: ../public/activities.php?warning=" . urlencode($msg) . "&" . $repopulate);
            exit();
        }
    }

    $stmt = $conn->prepare("INSERT INTO event_activities (event_id, title, venue, activity_date, start_time, end_time, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $event_id, $title, $venue, $date, $start, $end, $desc);
    
    if ($stmt->execute()) {
        header("Location: ../public/activities.php?success=Activity scheduled successfully");
    } else {
        header("Location: ../public/activities.php?error=Database error: " . $conn->error);
    }
    exit();
}

// ACTION 2: UPDATE EXISTING ACTIVITY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id       = (int)$_POST['activity_id'];
    $event_id = (int)$_POST['event_id'];
    $title    = trim($_POST['title']);
    $venue    = trim($_POST['venue']);
    $date     = $_POST['activity_date'];
    $start    = $_POST['start_time'];
    $end      = $_POST['end_time'];
    $desc     = trim($_POST['description']);
    $force    = isset($_POST['force_save']) ? (int)$_POST['force_save'] : 0;

    if (strtotime($start) >= strtotime($end)) {
        header("Location: ../public/activities.php?error=End time must be after Start time.");
        exit();
    }

    if ($force === 0) {
        $conflicts = checkOverlap($conn, $event_id, $date, $start, $end, $id);
        if ($conflicts->num_rows > 0) {
            $c = $conflicts->fetch_assoc();
            $msg = "Warning: This overlaps with '{$c['title']}' ({$c['start_time']} - {$c['end_time']}).";
            $repopulate = http_build_query($_POST);
            header("Location: ../public/activities.php?warning=" . urlencode($msg) . "&" . $repopulate);
            exit();
        }
    }

    $stmt = $conn->prepare("UPDATE event_activities SET title=?, venue=?, activity_date=?, start_time=?, end_time=?, description=? WHERE id=?");
    $stmt->bind_param("ssssssi", $title, $venue, $date, $start, $end, $desc, $id);
    
    if ($stmt->execute()) {
        header("Location: ../public/activities.php?success=Activity updated");
    } else {
        header("Location: ../public/activities.php?error=Update failed: " . $conn->error);
    }
    exit();
}

// ACTION 3: SOFT DELETE (ARCHIVE)
if (isset($_GET['action']) && $_GET['action'] === 'archive') {
    $id = (int)$_GET['id'];
    $conn->query("UPDATE event_activities SET is_deleted = 1 WHERE id = $id");
    header("Location: ../public/activities.php?success=Activity archived");
    exit();
}

// ACTION 4: RESTORE (UN-ARCHIVE) --> THIS WAS MISSING
if (isset($_GET['action']) && $_GET['action'] === 'restore') {
    $id = (int)$_GET['id'];
    // Update is_deleted back to 0
    $conn->query("UPDATE event_activities SET is_deleted = 0 WHERE id = $id");
    header("Location: ../public/activities.php?view=archive&success=Activity restored successfully");
    exit();
}

// ACTION 5: PERMANENT DELETE
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)$_GET['id'];
    $conn->query("DELETE FROM event_activities WHERE id = $id");
    header("Location: ../public/activities.php?view=archive&success=Activity deleted permanently");
    exit();
}
?>