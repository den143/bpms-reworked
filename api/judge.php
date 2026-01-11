<?php
// Purpose: Backend controller for managing Event Judges.
// Handles adding/inviting judges, assigning Chairman roles, and automatically updating existing accounts.

require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

// HELPER: CHAIRMAN RESET
function resetChairman($conn, $event_id) {
    $stmt = $conn->prepare("UPDATE event_judges SET is_chairman = 0 WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
}

// --- 1. ADD or RESTORE JUDGE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    
    $event_id = (int)$_POST['event_id'];
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $pass     = trim($_POST['password']);
    $is_chairman = isset($_POST['is_chairman']) ? 1 : 0;

    if (empty($name) || empty($email) || empty($pass)) {
        header("Location: ../public/judges.php?error=All fields are required");
        exit();
    }

    $conn->begin_transaction();
    try {
        if ($is_chairman) {
            resetChairman($conn, $event_id);
        }

        // A. Check/Create User
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $res = $check->get_result();
        
        $msg_prefix = ""; // Variable to hold the specific action details

        if ($res->num_rows > 0) {
            // CASE: EXISTING JUDGE (Update details)
            $judge_id = $res->fetch_assoc()['id'];
            
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            $updateUser = $conn->prepare("UPDATE users SET name = ?, password = ? WHERE id = ?");
            $updateUser->bind_param("ssi", $name, $hashed_pass, $judge_id);
            $updateUser->execute();
            
            $msg_prefix = "Existing account updated & "; 
        } else {
            // CASE: NEW JUDGE (Create account)
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (created_by, name, email, password, role, status) VALUES (?, ?, ?, ?, 'Judge', 'Active')");
            $creator = $_SESSION['user_id'];
            $stmt->bind_param("isss", $creator, $name, $email, $hashed_pass);
            $stmt->execute();
            $judge_id = $conn->insert_id;
            
            $msg_prefix = "New judge created & ";
        }

        // B. Check Existing Link (Soft Deleted?)
        $linkCheck = $conn->prepare("SELECT id FROM event_judges WHERE event_id = ? AND judge_id = ?");
        $linkCheck->bind_param("ii", $event_id, $judge_id);
        $linkCheck->execute();
        $linkRes = $linkCheck->get_result();
        
        if ($linkRes->num_rows > 0) {
            // RESTORE existing link
            $link_id = $linkRes->fetch_assoc()['id'];
            $update = $conn->prepare("UPDATE event_judges SET status='Active', is_deleted=0, is_chairman=? WHERE id=?");
            $update->bind_param("ii", $is_chairman, $link_id);
            $update->execute();
            
            // [FIXED]: Use the prefix variable
            $msg = $msg_prefix . "restored to event list.";
        } else {
            // CREATE new link 
            $insert = $conn->prepare("INSERT INTO event_judges (event_id, judge_id, is_chairman, status, is_deleted) VALUES (?, ?, ?, 'Active', 0)");
            $insert->bind_param("iii", $event_id, $judge_id, $is_chairman);
            $insert->execute();
            
            // [FIXED]: Use the prefix variable
            $msg = $msg_prefix . "added to event list.";
        }

        // SEND EMAIL INVITE
        require_once __DIR__ . '/../app/core/CustomMailer.php';
        $site_link = "https://juvenal-esteban-octavalent.ngrok-free.dev/bpms/public/index.php"; 

        $evt_name = "the Pageant";
        $e_query = $conn->query("SELECT name FROM events WHERE id = $event_id");
        if ($row = $e_query->fetch_assoc()) {
            $evt_name = $row['name'];
        }

        $subject = "Official Invitation: Judge for $evt_name";
        $body = "
            <h2>Hello, $name!</h2>
            <p>You have been assigned as a Judge for <b>$evt_name</b>.</p>
            <div style='background:#f3f4f6; padding:15px; border-radius:8px; margin:20px 0;'>
                <strong>Your Login Credentials:</strong><br>
                Email: <b>$email</b><br>
                Password: <b>$pass</b>
            </div>
            <p><a href='$site_link' style='background:#F59E0B; color:white; padding:10px 20px; text-decoration:none;'>Login Now</a></p>
        ";

        sendCustomEmail($email, $subject, $body);

        $conn->commit();
        header("Location: ../public/judges.php?success=" . urlencode($msg));

    } catch (Exception $e) {
        $conn->rollback();
        header("Location: ../public/judges.php?error=Database Error");
    }
    exit();
}

// --- 2. UPDATE JUDGE (Edit Details) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    
    $link_id  = (int)$_POST['link_id'];
    $judge_id = (int)$_POST['judge_id']; 
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $pass     = trim($_POST['password']);
    $is_chairman = isset($_POST['is_chairman']) ? 1 : 0;
    
    $evtCheck = $conn->query("SELECT event_id FROM event_judges WHERE id = $link_id");
    $event_id = $evtCheck->fetch_assoc()['event_id'];

    $conn->begin_transaction();
    try {
        if ($is_chairman) {
            resetChairman($conn, $event_id);
        }

        $stmt1 = $conn->prepare("UPDATE event_judges SET is_chairman = ? WHERE id = ?");
        $stmt1->bind_param("ii", $is_chairman, $link_id);
        $stmt1->execute();

        if (!empty($pass)) {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
            $stmt2->bind_param("sssi", $name, $email, $hashed, $judge_id);
        } else {
            $stmt2 = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt2->bind_param("ssi", $name, $email, $judge_id);
        }
        $stmt2->execute();

        $conn->commit();
        header("Location: ../public/judges.php?success=Judge updated successfully");

    } catch (Exception $e) {
        $conn->rollback();
        header("Location: ../public/judges.php?error=Update failed");
    }
    exit();
}

// --- 3. REMOVE / RESTORE / DELETE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    
    $link_id = (int)$_POST['id'];
    $action  = $_POST['action'];

    if ($action === 'delete') {
        $stmt = $conn->prepare("UPDATE event_judges SET is_deleted = 1 WHERE id = ?");
        $stmt->bind_param("i", $link_id);
        $view = 'archived';
        $msg = "Judge permanently removed from list.";

    } elseif ($action === 'restore') {
        $stmt = $conn->prepare("UPDATE event_judges SET status = 'Active', is_deleted = 0 WHERE id = ?");
        $stmt->bind_param("i", $link_id);
        $view = 'archived';
        $msg = "Judge restored successfully.";

    } else {
        $stmt = $conn->prepare("UPDATE event_judges SET status = 'Inactive' WHERE id = ?");
        $stmt->bind_param("i", $link_id);
        $view = 'active';
        $msg = "Judge archived.";
    }
    
    if ($stmt->execute()) {
        header("Location: ../public/judges.php?view=$view&success=" . urlencode($msg));
    } else {
        header("Location: ../public/judges.php?error=Action failed");
    }
    exit();
}
?>