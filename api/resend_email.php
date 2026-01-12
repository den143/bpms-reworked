<?php
// Purpose: Backend controller for resending login credentials.
// Supports two modes: 'reminder' (link only) and 'reset' (generate new password).

require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
// Security: Only Admins can trigger this to prevent abuse
requireRole(['Event Manager', 'Contestant Manager']); 

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/core/CustomMailer.php';

if (isset($_POST['user_id'])) {
    
    $user_id = (int)$_POST['user_id'];
    $action_type = $_POST['action_type'] ?? 'reset'; // Default to 'reset'
    
    // 1. Fetch User Data
    // Matches 'users' table in beauty_pageant_db.sql
    $query = $conn->query("SELECT name, email, role FROM users WHERE id = $user_id");
    $user = $query->fetch_assoc();
    
    if (!$user) {
        $redirect_url = $_SERVER['HTTP_REFERER'] ?? '../public/index.php';
        header("Location: $redirect_url" . (strpos($redirect_url, '?') ? '&' : '?') . "error=" . urlencode("User not found"));
        exit();
    }

    // --- CONFIGURATION: UPDATE FOR DEPLOYMENT ---
    // If running on XAMPP/Localhost:
    $site_link = "https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php";
        
    $msg = "";
    $status = "error";

    // OPTION A: REMINDER (Safe Mode)
    // Logic: Send a login link but DO NOT change the password.
    if ($action_type === 'reminder') {
        $subject = "Reminder: Your Access Credentials";
        $body = "
            <h2>Hello, {$user['name']}!</h2>
            <p>This is a reminder for your access to the <b>BPMS System</b>.</p>
            
            <div style='background:#f0f9ff; padding:15px; border-radius:8px; border:1px solid #bae6fd; margin:20px 0; color:#0369a1;'>
                <strong>Your Login Details:</strong><br>
                Email: <b>{$user['email']}</b><br>
                Password: <i>(Hidden for security. If you forgot it, ask Admin to reset.)</i>
            </div>

            <p>Click below to login:</p>
            <p><a href='$site_link' style='background:#0284c7; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Login to Dashboard</a></p>
        ";

        if (sendCustomEmail($user['email'], $subject, $body)) {
            $msg = "Reminder email sent successfully!";
            $status = "success";
        } else {
            $msg = "Failed to send reminder email.";
        }
    }

    // OPTION B: RESET PASSWORD (Force Change)
    // Logic: Generate a random string, hash it, update DB, and email the plain text version.
    elseif ($action_type === 'reset') {
        
        // 1. Generate Random Password (8 characters)
        $new_pass = substr(str_shuffle("abcdefDEFGH23456789"), 0, 8);
        
        // 2. Hash the password for security
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);

        // 3. Update Database with Hash
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_pass, $user_id);
        
        if ($stmt->execute()) {
            
            // 4. Send Email with Plain Text Password
            $subject = "Security Alert: New Login Credentials";
            $body = "
                <h2>Hello, {$user['name']}!</h2>
                <p>Your login credentials for the BPMS System have been <b>reset</b> by the Event Manager.</p>
                
                <div style='background:#fffbeb; padding:15px; border-radius:8px; border:1px solid #fcd34d; margin:20px 0; color:#92400e;'>
                    <strong>New Credentials:</strong><br>
                    Email: <b>{$user['email']}</b><br>
                    Password: <b>$new_pass</b>
                </div>

                <p>Please login immediately using the link below:</p>
                <p><a href='$site_link' style='background:#F59E0B; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Login Now</a></p>
            ";

            if (sendCustomEmail($user['email'], $subject, $body)) {
                $msg = "Password reset and email sent successfully!";
                $status = "success";
            } else {
                $msg = "Password reset, but Email Failed to send.";
            }

        } else {
            $msg = "Database Error during reset.";
        }
    }

    // REDIRECTION LOGIC
    // Purpose: Send user back to the exact page they came from (Judges or Organizers).
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? '../public/index.php';
    
    // Clean URL to avoid stacking old parameters
    $redirect_url = strtok($redirect_url, '?');
    
    // If coming from organizers page, ensure we stay on the active tab
    if (strpos($_SERVER['HTTP_REFERER'], 'organizers.php') !== false) {
        $redirect_url .= "?view=active"; 
    }
    
    header("Location: $redirect_url" . (strpos($redirect_url, '?') ? '&' : '?') . "$status=" . urlencode($msg));
    exit();
}
?>