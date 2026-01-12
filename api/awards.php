<?php
// Purpose: Backend controller for creating and configuring Award definitions.
// Allows the Event Manager to define awards and link them to specific scoring segments.

// Enable Error Reporting (Helpful for debugging during development)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// SECURITY: Only Event Managers can create/edit awards.
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

// ACTION 1: ADD NEW AWARD
// Purpose: Create a new award trophy (e.g., "Best in Swimsuit").
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    
    $event_id = (int)$_POST['event_id'];
    $title    = trim($_POST['title']);
    $desc     = trim($_POST['description']);
    $type     = $_POST['type']; // Frontend sends: Major, Minor
    $source   = $_POST['source_type']; // Frontend sends: Manual, Segment, Round, Audience
    
    // LOGIC: DYNAMIC SOURCE MAPPING (New DB Schema)
    // The new DB separates Segment IDs and Round IDs into different columns
    // and uses specific ENUM values for the method.
    
    $selection_method = 'Manual';
    $linked_segment   = null;
    $linked_round     = null;

    if ($source === 'Segment') {
        $selection_method = 'Highest_Segment';
        $linked_segment   = (int)$_POST['segment_id'];
    } elseif ($source === 'Round') {
        $selection_method = 'Highest_Round';
        $linked_round     = (int)$_POST['round_id'];
    } elseif ($source === 'Audience') {
        $selection_method = 'Audience_Vote';
    }

    // LOGIC: DUPLICATE CHECK
    $dup = $conn->prepare("SELECT id FROM awards WHERE event_id = ? AND title = ? AND is_deleted = 0");
    $dup->bind_param("is", $event_id, $title);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        header("Location: ../public/awards.php?error=Award '$title' already exists.");
        exit();
    }

    // Insert the new award definition
    // UPDATED: Columns mapped to new schema (category_type, selection_method, linked_segment_id, linked_round_id)
    $stmt = $conn->prepare("INSERT INTO awards (event_id, title, description, category_type, selection_method, linked_segment_id, linked_round_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssii", $event_id, $title, $desc, $type, $selection_method, $linked_segment, $linked_round);
    
    if ($stmt->execute()) {
        header("Location: ../public/awards.php?success=Award created successfully");
    } else {
        header("Location: ../public/awards.php?error=Database error: " . $conn->error);
    }
    exit();
}

// ACTION 2: UPDATE EXISTING AWARD
// Purpose: Edit details of an award (e.g., change "Minor" to "Major").
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    
    $id       = (int)$_POST['award_id'];
    $title    = trim($_POST['title']);
    $desc     = trim($_POST['description']);
    $type     = $_POST['type'];
    $source   = $_POST['source_type'];
    
    // Re-evaluate mapping for updates
    $selection_method = 'Manual';
    $linked_segment   = null;
    $linked_round     = null;

    if ($source === 'Segment') {
        $selection_method = 'Highest_Segment';
        $linked_segment   = (int)$_POST['segment_id'];
    } elseif ($source === 'Round') {
        $selection_method = 'Highest_Round';
        $linked_round     = (int)$_POST['round_id'];
    } elseif ($source === 'Audience') {
        $selection_method = 'Audience_Vote';
    }

    // UPDATED: Query matches new schema
    $stmt = $conn->prepare("UPDATE awards SET title=?, description=?, category_type=?, selection_method=?, linked_segment_id=?, linked_round_id=? WHERE id=?");
    $stmt->bind_param("ssssiii", $title, $desc, $type, $selection_method, $linked_segment, $linked_round, $id);
    
    if ($stmt->execute()) {
        header("Location: ../public/awards.php?success=Award updated");
    } else {
        header("Location: ../public/awards.php?error=Update failed: " . $conn->error);
    }
    exit();
}

// ACTION 3: ARCHIVE (Soft Delete)
if (isset($_GET['action']) && $_GET['action'] === 'archive') {
    $id = (int)$_GET['id'];
    $conn->query("UPDATE awards SET is_deleted = 1 WHERE id = $id");
    header("Location: ../public/awards.php?success=Award archived");
    exit();
}

// ACTION 4: RESTORE
if (isset($_GET['action']) && $_GET['action'] === 'restore') {
    $id = (int)$_GET['id'];
    $conn->query("UPDATE awards SET is_deleted = 0 WHERE id = $id");
    header("Location: ../public/awards.php?success=Award restored");
    exit();
}
?>