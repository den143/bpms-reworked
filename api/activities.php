<?php
// Purpose: Handles all backend logic for the Manage Activities module.

// Enable Error Reporting (Helpful for debugging during development)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// SECURITY: Gatekeeper Check
// 1. Ensure the user is logged in.
// 2. Ensure ONLY the 'Event Manager' can access this file.
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');

require_once __DIR__ . '/../app/config/database.php';

// LOGIC: TIME OVERLAP CHECKER
// Purpose: Prevents double-booking by checking if a new activity clashes with an existing one.
function checkOverlap($conn, $event_id, $date, $start, $end, $exclude_id = null) {
    // ALGORITHM:
    // An overlap occurs if: (NewStart < ExistingEnd) AND (NewEnd > ExistingStart).
    // We only check active items (is_deleted = 0).
    $sql = "SELECT title, start_time, end_time FROM activities 
            WHERE event_id = ? 
            AND activity_date = ? 
            AND is_deleted = 0 
            AND ((? < end_time) AND (? > start_time))";
    
    // If Updating: We must exclude the activity's OWN id, otherwise it conflicts with itself.
    if ($exclude_id) {
        $sql .= " AND id != " . (int)$exclude_id;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $event_id, $date, $start, $end);
    $stmt->execute();
    return $stmt->get_result();
}

// ACTION 1: ADD NEW ACTIVITY
// Triggered when the user submits the "Add Activity" form.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    
    // Collect data from the form
    $event_id = (int)$_POST['event_id'];
    $title    = trim($_POST['title']);
    $venue    = trim($_POST['venue']);
    $date     = $_POST['activity_date'];
    $start    = $_POST['start_time'];
    $end      = $_POST['end_time'];
    $desc     = trim($_POST['description']);
    
    // "Force Save": If 1, the user clicked "Proceed Anyway" on a warning, so we skip the overlap check.
    $force    = isset($_POST['force_save']) ? (int)$_POST['force_save'] : 0; 

    // VALIDATION: Ensure time flows forward (Start cannot be after End)
    if (strtotime($start) >= strtotime($end)) {
        header("Location: ../public/activities.php?error=End time must be after Start time.");
        exit();
    }

    // LOGIC: Check for Conflicts (Only if not forced)
    if ($force === 0) {
        $conflicts = checkOverlap($conn, $event_id, $date, $start, $end);
        
        if ($conflicts->num_rows > 0) {
            // If there are conflicts, we show a warning to the user.
            // This tells the UI to show the "Conflict Detected" modal to the user.
            $c = $conflicts->fetch_assoc();
            $msg = "Warning: This overlaps with '{$c['title']}' ({$c['start_time']} - {$c['end_time']}).";
            
            // We send the form data back so the user doesn't have to re-type it.
            $repopulate = http_build_query($_POST);
            header("Location: ../public/activities.php?warning=" . urlencode($msg) . "&" . $repopulate);
            exit();
        }
    }

    // SAVE TO DATABASE
    $stmt = $conn->prepare("INSERT INTO activities (event_id, title, venue, activity_date, start_time, end_time, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $event_id, $title, $venue, $date, $start, $end, $desc);
    
    if ($stmt->execute()) {
        header("Location: ../public/activities.php?success=Activity scheduled successfully");
    } else {
        header("Location: ../public/activities.php?error=Database error");
    }
    exit();
}

// ACTION 2: UPDATE EXISTING ACTIVITY
// Triggered when editing an activity. Logic is similar to Add, but updates a specific ID.
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

    // Validation
    if (strtotime($start) >= strtotime($end)) {
        header("Location: ../public/activities.php?error=End time must be after Start time.");
        exit();
    }

    // Overlap Check (Passing $id to exclude itself)
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

    // UPDATE DATABASE
    $stmt = $conn->prepare("UPDATE activities SET title=?, venue=?, activity_date=?, start_time=?, end_time=?, description=? WHERE id=?");
    $stmt->bind_param("ssssssi", $title, $venue, $date, $start, $end, $desc, $id);
    
    if ($stmt->execute()) {
        header("Location: ../public/activities.php?success=Activity updated");
    } else {
        header("Location: ../public/activities.php?error=Update failed");
    }
    exit();
}

// ACTION 3: SOFT DELETE (ARCHIVE)
// Logic: We don't actually delete the row. We set 'is_deleted' to 1.
// This allows us to restore it later if it was a mistake.
if (isset($_GET['action']) && $_GET['action'] === 'archive') {
    $id = (int)$_GET['id'];
    $conn->query("UPDATE activities SET is_deleted = 1 WHERE id = $id");
    header("Location: ../public/activities.php?success=Activity archived");
    exit();
}

// ACTION 4: RESTORE (UN-ARCHIVE)
// Logic: Set 'is_deleted' back to 0 to make it visible again.
if (isset($_GET['action']) && $_GET['action'] === 'restore') {
    $id = (int)$_GET['id'];
    $conn->query("UPDATE activities SET is_deleted = 0 WHERE id = $id");
    header("Location: ../public/activities.php?success=Activity restored");
    exit();
}
?>