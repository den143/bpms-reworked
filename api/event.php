<?php
// Purpose: Backend controller for creating the main Event.
// Handles form validation and delegates creation to the Event model.

require_once __DIR__ . '/../app/core/guard.php';
requireLogin();

// Include the Event Model (Logic is separated for cleaner code)
require_once __DIR__ . '/../app/models/Event.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. INPUT SANITIZATION
    // FIX: Updated to look for 'title' (which matches the dashboard.php form), not 'event_name'
    $title = trim($_POST['title'] ?? '');
    $date  = trim($_POST['event_date'] ?? '');
    $venue = trim($_POST['venue'] ?? '');

    // Validation: Ensure required fields are present
    if (empty($title) || empty($date) || empty($venue)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: ../public/dashboard.php");
        exit();
    }

    // Validation: Check Date Format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $_SESSION['error'] = "Invalid date format.";
        header("Location: ../public/dashboard.php");
        exit();
    }

    // 2. CREATE EVENT (Delegated to Model)
    // We pass the current user's ID. The Model will save this into the 'manager_id' column.
    // The Model will also handle setting other events to 'Inactive' automatically.
    $success = Event::create($_SESSION['user_id'], $title, $date, $venue);

    if ($success) {
        // Logic: Turn off the "Force Create" modal now that an event exists.
        $_SESSION['show_modal'] = false;
        $_SESSION['success'] = "Event created successfully.";
    } else {
        $_SESSION['error'] = "Failed to create event. Please try again.";
    }

    header("Location: ../public/dashboard.php");
    exit();
}
?>