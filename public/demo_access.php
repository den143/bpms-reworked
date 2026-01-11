<?php
session_start();
require_once __DIR__ . '/../app/config/database.php';

// Map the requested Role to the specific Demo Email created in SQL
$demo_emails = [
    'Event Manager'      => 'manager@demo.com',
    'Contestant Manager' => 'cm@demo.com',
    'Judge Coordinator'  => 'jc@demo.com',
    'Tabulator'          => 'tab@demo.com',
    'Judge'              => 'judge@demo.com',
    'Contestant'         => 'contestant@demo.com'
];

// HANDLE CLICK ACTION
if (isset($_GET['role'])) {
    $role = $_GET['role'];

    // --- CASE A: AUDIENCE (Ticket Based) ---
    if ($role === 'Audience') {
        // We look for the ticket created in Step 6
        $stmt = $conn->prepare("SELECT id, code FROM tickets WHERE code = 'DEMO-TICKET' LIMIT 1");
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();

        if ($ticket) {
            $_SESSION['user_id'] = $ticket['id'];
            $_SESSION['role'] = 'Audience';
            $_SESSION['ticket_code'] = $ticket['code'];
            header("Location: audience_dashboard.php");
            exit();
        } else {
            die("Error: Demo Ticket not found. Did you run the SQL?");
        }
    }

    // --- CASE B: STAFF & CONTESTANTS (User Based) ---
    if (isset($demo_emails[$role])) {
        $email = $demo_emails[$role];
        
        // Find the user ID dynamically
        $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            // Log them in (Bypassing password check)
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $email;
            $_SESSION['role']    = $user['role'];
            
            // Redirect based on role
            switch ($role) {
                case 'Event Manager':      header("Location: dashboard.php"); break;
                case 'Judge Coordinator':  header("Location: judge_coordinator.php"); break;
                case 'Contestant Manager': header("Location: contestant_manager.php"); break;
                case 'Tabulator':          header("Location: tabulator.php"); break;
                case 'Judge':              header("Location: judge_dashboard.php"); break;
                case 'Contestant':         header("Location: contestant_dashboard.php"); break;
                default:                   header("Location: index.php"); break;
            }
            exit();
        } else {
            die("Error: User for $role ($email) not found. Did you run the SQL?");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BPMS Research Demo Portal</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 480px; text-align: center; }
        h1 { color: #111827; margin-bottom: 5px; font-size: 24px; }
        p { color: #6b7280; margin-bottom: 30px; font-size: 14px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
        .btn { display: block; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; transition: transform 0.1s; color: white; }
        .btn:hover { transform: translateY(-2px); opacity: 0.9; }
        /* Role Colors */
        .mgr { background: #111827; grid-column: span 2; } 
        .sub-mgr { background: #4b5563; } 
        .judge { background: #F59E0B; grid-column: span 2; } 
        .cont { background: #ec4899; } 
        .aud { background: #059669; } 
        .footer { margin-top: 20px; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 15px; }
        .back-link { display: block; margin-top: 10px; color: #6b7280; text-decoration: none; font-size: 13px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Research Demo Access</h1>
    <p>Select a role to enter the Sandbox Environment.</p>
    <div class="grid">
        <a href="?role=Event Manager" class="btn mgr">Event Manager (Admin)</a>
        <a href="?role=Contestant Manager" class="btn sub-mgr">Contestant Mgr</a>
        <a href="?role=Judge Coordinator" class="btn sub-mgr">Judge Coord</a>
        <a href="?role=Tabulator" class="btn sub-mgr">Tabulator</a>
        <a href="?role=Judge" class="btn judge">Judge</a>
        <a href="?role=Contestant" class="btn cont">Contestant</a>
        <a href="?role=Audience" class="btn aud">Audience</a>
    </div>
    <div class="footer">
        <strong>Note for Respondents:</strong> All data entered here is for testing purposes only.<br>
        <a href="logout.php" class="back-link">← Return to Regular Login</a>
    </div>
</div>
</body>
</html>