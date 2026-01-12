<?php
// Purpose: Handles the login process for Audience members using a Ticket Code.

session_start();
require_once '../app/config/database.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = trim($_POST['ticket_code']);

    if (empty($code)) {
        header("Location: ../public/index.php?error=Ticket code required");
        exit();
    }

    // LOGIC: Validate Ticket
    $stmt = $conn->prepare("SELECT id, status, event_id FROM tickets WHERE ticket_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $ticket = $result->fetch_assoc();

        // CHECK: Is the ticket expired?
        // Logic: If status is 'Used', it means they have voted.
        if ($ticket['status'] === 'Used') {
            header("Location: ../public/index.php?error=This ticket has already been used.");
            exit();
        }

        // LOGIN SUCCESS
        // We store the ticket ID as the 'user_id' for the session.
        $_SESSION['user_id']     = $ticket['id'];
        $_SESSION['role']        = 'Audience';
        $_SESSION['ticket_code'] = $code;
        $_SESSION['event_id']    = $ticket['event_id']; // Added for convenience in dashboard
        
        header("Location: ../public/audience_dashboard.php");
        exit();

    } else {
        header("Location: ../public/index.php?error=Invalid Ticket Code");
        exit();
    }
}
?>