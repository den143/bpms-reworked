<?php
// Purpose: Backend controller for creating and configuring Award definitions.
// Allows the Event Manager to define awards and link them to specific scoring segments.

// Enable Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// SECURITY
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

// ACTION 1: ADD NEW AWARD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    
    $event_id = (int)$_POST['event_id'];
    $title    = trim($_POST['title']);
    $desc     = trim($_POST['description']);
    $type     = $_POST['category_type'];
    $source   = $_POST['selection_method'];
    
    // LOGIC: MAPPING
    $selection_method = 'Manual';
    $linked_segment   = null;
    $linked_round     = null;

    if ($source === 'Highest_Segment') {
        $selection_method = 'Highest_Segment';
        $linked_segment   = (int)$_POST['linked_segment_id'];
    } elseif ($source === 'Highest_Round') {
        $selection_method = 'Highest_Round';
        $linked_round     = (int)$_POST['linked_round_id']; 
    } elseif ($source === 'Audience_Vote') {
        $selection_method = 'Audience_Vote';
    } else {
        $selection_method = 'Manual';
    }

    // DUPLICATE CHECK (Only check active non-deleted ones)
    $dup = $conn->prepare("SELECT id FROM awards WHERE event_id = ? AND title = ? AND is_deleted = 0");
    $dup->bind_param("is", $event_id, $title);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        header("Location: ../public/awards.php?error=Award '$title' already exists.");
        exit();
    }

    // INSERT (Default Status = Active)
    $stmt = $conn->prepare("INSERT INTO awards (event_id, title, description, category_type, selection_method, linked_segment_id, linked_round_id, status, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', 0)");
    $stmt->bind_param("issssii", $event_id, $title, $desc, $type, $selection_method, $linked_segment, $linked_round);
    
    if ($stmt->execute()) {
        header("Location: ../public/awards.php?success=Award created successfully");
    } else {
        header("Location: ../public/awards.php?error=Database error: " . $conn->error);
    }
    exit();
}

// ACTION 2: UPDATE EXISTING AWARD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    
    $id       = (int)$_POST['award_id'];
    $title    = trim($_POST['title']);
    $desc     = trim($_POST['description']);
    $type     = $_POST['category_type'];
    $source   = $_POST['selection_method'];
    
    $selection_method = 'Manual';
    $linked_segment   = null;
    $linked_round     = null;

    if ($source === 'Highest_Segment') {
        $selection_method = 'Highest_Segment';
        $linked_segment   = (int)$_POST['linked_segment_id'];
    } elseif ($source === 'Highest_Round') {
        $selection_method = 'Highest_Round';
        $linked_round     = (int)$_POST['linked_round_id'];
    } elseif ($source === 'Audience_Vote') {
        $selection_method = 'Audience_Vote';
    }

    $stmt = $conn->prepare("UPDATE awards SET title=?, description=?, category_type=?, selection_method=?, linked_segment_id=?, linked_round_id=? WHERE id=?");
    $stmt->bind_param("ssssiii", $title, $desc, $type, $selection_method, $linked_segment, $linked_round, $id);
    
    if ($stmt->execute()) {
        header("Location: ../public/awards.php?success=Award updated");
    } else {
        header("Location: ../public/awards.php?error=Update failed: " . $conn->error);
    }
    exit();
}

// ACTION 3: ARCHIVE (Set Status = Inactive, Keep is_deleted = 0)
if (isset($_GET['action']) && $_GET['action'] === 'archive') {
    $id = (int)$_GET['id'];
    // FIX: Only change status to Inactive.
    $conn->query("UPDATE awards SET status = 'Inactive' WHERE id = $id");
    header("Location: ../public/awards.php?success=Award archived");
    exit();
}

// ACTION 4: RESTORE (Set Status = Active)
if (isset($_GET['action']) && $_GET['action'] === 'restore') {
    $id = (int)$_GET['id'];
    // FIX: Change status back to Active.
    $conn->query("UPDATE awards SET status = 'Active' WHERE id = $id");
    header("Location: ../public/awards.php?view=archive&success=Award restored");
    exit();
}

// ACTION 5: DELETE PERMANENTLY (Set is_deleted = 1)
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)$_GET['id'];
    // FIX: Soft Delete. Set is_deleted = 1.
    $conn->query("UPDATE awards SET is_deleted = 1 WHERE id = $id");
    header("Location: ../public/awards.php?view=archive&success=Award deleted permanently");
    exit();
}
?>