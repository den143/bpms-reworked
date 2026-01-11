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
    // We fetch the ticket's status to see if it's still valid for entry.
    $stmt = $conn->prepare("SELECT id, status, voted_contestant_id FROM tickets WHERE code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $ticket = $result->fetch_assoc();

        // CHECK: Is the ticket expired?
        // If the status is 'Used', it means they have presumably finished their session or vote
        // and are no longer allowed to log in.
        if ($ticket['status'] === 'Used') {
            header("Location: ../public/index.php?error=This ticket has expired.");
            exit();
        }

        // LOGIN SUCCESS
        // We store the ticket ID as the 'user_id' for the session since Audience members don't have user accounts.
        $_SESSION['user_id'] = $ticket['id'];
        $_SESSION['role'] = 'Audience';
        $_SESSION['ticket_code'] = $code;
        
        header("Location: ../public/audience_dashboard.php");
        exit();

    } else {
        header("Location: ../public/index.php?error=Invalid Ticket Code");
        exit();
    }
}
?>