<?php
// bpms/public/test_email.php

// 1. Include the Mailer
require_once __DIR__ . '/../app/core/CustomMailer.php';

$message = "";
$status = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = $_POST['email'];
    $subject = "BPMS SMTP Test Run";
    $body = "
        <h2>SMTP Connection Success!</h2>
        <p>If you are reading this, your PHP Native Mailer is working correctly.</p>
        <p><b>Time Sent:</b> " . date('Y-m-d H:i:s') . "</p>
        <hr>
        <p><i>- BPMS System</i></p>
    ";

    // 2. Attempt to Send
    // Since our mailer is silent, it returns TRUE on success, FALSE on failure.
    if (sendCustomEmail($to, $subject, $body)) {
        $status = "success";
        $message = "✅ Email successfully sent to <b>$to</b>. Check your inbox (and spam folder)!";
    } else {
        $status = "error";
        $message = "❌ Email Sending Failed. <br><small>If this persists, your host might be blocking port 587.</small>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMTP Test Run</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        h2 { color: #1f2937; margin-bottom: 20px; }
        input[type="email"] { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; margin-bottom: 15px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #F59E0B; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        button:hover { background: #d97706; }
        .alert { margin-top: 20px; padding: 15px; border-radius: 6px; font-size: 14px; }
        .alert.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .back-link { display: block; margin-top: 15px; color: #6b7280; text-decoration: none; font-size: 13px; }
    </style>
</head>
<body>

    <div class="card">
        <h2>🚀 SMTP Test Run</h2>
        <p style="color:#6b7280; margin-bottom: 25px;">Enter an email address to test the <code>CustomMailer.php</code> configuration.</p>

        <form method="POST">
            <input type="email" name="email" placeholder="Enter recipient email..." required>
            <button type="submit">Send Test Email</button>
        </form>

        <?php if ($message): ?>
            <div class="alert <?= $status ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <a href="index.php" class="back-link">← Return to Home</a>
    </div>

</body>
</html>