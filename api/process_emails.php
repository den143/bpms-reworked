<?php
// api/process_emails.php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/core/CustomMailer.php';

// 1. Fetch up to 5 pending emails
$result = $conn->query("SELECT * FROM email_queue WHERE status = 'pending' LIMIT 5");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        
        // 2. Mark as processing to prevent double sending
        $conn->query("UPDATE email_queue SET status = 'failed', attempts = attempts + 1 WHERE id = $id");

        // 3. Try to send
        if (sendCustomEmail($row['recipient_email'], $row['subject'], $row['body'])) {
            // Success
            $conn->query("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = $id");
            echo "Sent ID: $id <br>";
        } else {
            // Failure (will stay marked as 'failed' or you can reset to 'pending' to retry)
            echo "Failed ID: $id <br>";
        }
    }
} else {
    echo "No pending emails.";
}
?>