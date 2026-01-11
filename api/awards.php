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
    $type     = $_POST['type']; // Major, Minor, Special
    $source   = $_POST['source_type']; // Options: Manual, Segment, Round
    
    // LOGIC: DYNAMIC SOURCE MAPPING
    // If an award is linked to a Segment (e.g., Swimsuit), we save that Segment's ID.
    // This allows the system to automatically calculate the winner later.
    $source_id = null;
    if ($source === 'Segment') $source_id = (int)$_POST['segment_id'];
    if ($source === 'Round')   $source_id = (int)$_POST['round_id'];

    // LOGIC: DUPLICATE CHECK
    // Prevent creating two awards with the exact same name to avoid confusion.
    $dup = $conn->prepare("SELECT id FROM awards WHERE event_id = ? AND title = ? AND is_deleted = 0");
    $dup->bind_param("is", $event_id, $title);
    $dup->execute();
    if ($dup->get_result()->num_rows > 0) {
        header("Location: ../public/awards.php?error=Award '$title' already exists.");
        exit();
    }

    // Insert the new award definition
    $stmt = $conn->prepare("INSERT INTO awards (event_id, title, description, type, source_type, source_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssi", $event_id, $title, $desc, $type, $source, $source_id);
    
    if ($stmt->execute()) {
        header("Location: ../public/awards.php?success=Award created successfully");
    } else {
        header("Location: ../public/awards.php?error=Database error");
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
    
    // Re-evaluate the source ID in case the user changed the linkage
    $source_id = null;
    if ($source === 'Segment') $source_id = (int)$_POST['segment_id'];
    if ($source === 'Round')   $source_id = (int)$_POST['round_id'];

    $stmt = $conn->prepare("UPDATE awards SET title=?, description=?, type=?, source_type=?, source_id=? WHERE id=?");
    $stmt->bind_param("ssssii", $title, $desc, $type, $source, $source_id, $id);
    
    if ($stmt->execute()) {
        header("Location: ../public/awards.php?success=Award updated");
    } else {
        header("Location: ../public/awards.php?error=Update failed");
    }
    exit();
}

// ACTION 3: ARCHIVE (Soft Delete)
// Logic: Hides the award from the list without permanently deleting the data.
if (isset($_GET['action']) && $_GET['action'] === 'archive') {
    $id = (int)$_GET['id'];
    $conn->query("UPDATE awards SET is_deleted = 1 WHERE id = $id");
    header("Location: ../public/awards.php?success=Award archived");
    exit();
}

// ACTION 4: RESTORE
// Logic: Un-hides a previously archived award.

if (isset($_GET['action']) && $_GET['action'] === 'restore') {
    $id = (int)$_GET['id'];
    $conn->query("UPDATE awards SET is_deleted = 0 WHERE id = $id");
    header("Location: ../public/awards.php?success=Award restored");
    exit();
}
?>