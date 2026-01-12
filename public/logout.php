<?php
// bpms/public/logout.php
session_start();
require_once __DIR__ . '/../app/config/database.php';

// AUDIENCE LOGIC: Invalidate Ticket on Logout
// This ensures that once a user leaves, they cannot re-enter with the same code.
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Audience' && isset($_SESSION['user_id'])) {
    $ticket_id = $_SESSION['user_id'];
    
    if (isset($conn)) {
        // Matches 'tickets' table
        $stmt = $conn->prepare("UPDATE tickets SET status = 'Used' WHERE id = ?");
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
    }
}

// STANDARD LOGOUT: Clear all session data
$_SESSION = []; // Clear array
session_unset(); // Unset variables
session_destroy(); // Destroy session ID

// Redirect to Login Page
header("Location: index.php");
exit();
?>