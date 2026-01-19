<?php
// api/process_emails.php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/core/CustomMailer.php';

header('Content-Type: text/plain');

// 1. START TRANSACTION
// This is critical. It creates a "wrapper" around our actions.
$conn->begin_transaction();

try {
    // 2. FETCH & LOCK (The Magic Step)
    // "FOR UPDATE" locks these specific rows. 
    // No other script can read/modify them until we commit.
    // We select emails that are PENDING or FAILED (with < 3 attempts)
    $sql = "SELECT id, recipient_email, subject, body, attempts 
            FROM email_queue 
            WHERE status = 'pending' 
               OR (status = 'failed' AND attempts < 3) 
            LIMIT 5 
            FOR UPDATE";

    $result = $conn->query($sql);
    $emails_to_process = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $emails_to_process[] = $row;
        }
    }

    // If no emails found, close up and leave
    if (empty($emails_to_process)) {
        $conn->commit(); // Release lock
        echo "No emails to process.";
        exit;
    }

    // 3. PROCESS THE EMAILS
    foreach ($emails_to_process as $email) {
        $id = $email['id'];
        $to = $email['recipient_email'];
        $sub = $email['subject'];
        $body = $email['body'];
        $current_attempts = $email['attempts'] + 1; // Increment attempt count

        // Try to send
        if (sendCustomEmail($to, $sub, $body)) {
            // SUCCESS: Mark as Sent
            $update = $conn->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW(), attempts = ? WHERE id = ?");
            $update->bind_param("ii", $current_attempts, $id);
            $update->execute();
            echo "Sent ID: $id <br>";
        } else {
            // FAILURE: Mark as Failed (but keep attempts updated so we know when to stop)
            $update = $conn->prepare("UPDATE email_queue SET status = 'failed', attempts = ? WHERE id = ?");
            $update->bind_param("ii", $current_attempts, $id);
            $update->execute();
            echo "Failed ID: $id (Attempt $current_attempts) <br>";
        }
    }

    // 4. COMMIT
    // This saves the changes and UNLOCKS the rows for the next person.
    $conn->commit();

} catch (Exception $e) {
    // If anything explodes, undo everything to be safe
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}
?>