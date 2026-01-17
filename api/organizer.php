<?php
// Purpose: Backend controller for managing Event Organizers (Staff).
// Handles creating accounts for Tabulators, Coordinators, etc., and linking them to events.

require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

// ACTION 1: ADD or RESTORE ORGANIZER
// Purpose: Assign a staff member to the event. 
// Logic: If user exists, CHECK ROLE MATCH, then UPDATE details; otherwise CREATE new.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    
    $event_id = (int)$_POST['event_id'];
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $role     = trim($_POST['role']);
    $pass     = trim($_POST['password']);
    
    // SECURITY: Role Whitelist
    $allowed_roles = ['Judge Coordinator', 'Contestant Manager', 'Tabulator'];
    if (!in_array($role, $allowed_roles)) {
        header("Location: ../public/organizers.php?error=Invalid Role selected");
        exit();
    }

    $conn->begin_transaction();
    try {
        // Step 1: Check if this email already exists in 'users'
        $checkAdmin = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
        $checkAdmin->bind_param("s", $email);
        $checkAdmin->execute();
        $adminRes = $checkAdmin->get_result();

        $msg_prefix = ""; 

        if ($adminRes->num_rows > 0) {
            // CASE: EXISTING USER -> CHECK & UPDATE
            $existingUser = $adminRes->fetch_assoc();
            
            // 1. SECURITY: Hierarchy Protection
            if ($existingUser['role'] === 'Event Manager') {
                throw new Exception("Security Alert: You cannot assign the Event Manager account as an Organizer.");
            }

            // 2. LOGIC: PREVENT ROLE CHANGE (The Fix)
            if ($existingUser['role'] !== $role) {
                throw new Exception("This email is already registered as a '" . $existingUser['role'] . "'. You cannot change their role to '$role'. Please use a different email or select the correct role.");
            }
            
            $user_id = $existingUser['id'];
            
            // Logic: Update Name, Phone, Password. 
            // REMOVED 'role' from update to ensure identity is preserved.
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE users SET name=?, phone=?, password=?, status='Active' WHERE id=?");
            $updateStmt->bind_param("sssi", $name, $phone, $hashed_pass, $user_id);
            $updateStmt->execute();

            $msg_prefix = "Existing account updated & ";
            
        } else {
            // CASE: NEW USER -> CREATE ACCOUNT
            if (empty($pass)) { throw new Exception("Password required for new accounts"); }
            
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (created_by, name, email, phone, role, password, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
            $creator = $_SESSION['user_id'];
            $stmt->bind_param("isssss", $creator, $name, $email, $phone, $role, $hashed_pass);
            $stmt->execute();
            $user_id = $conn->insert_id;
            
            $msg_prefix = "New organizer created & ";
        }

        // Step 2: Link User to Event (Using 'event_teams')
        $linkCheck = $conn->prepare("SELECT id FROM event_teams WHERE event_id = ? AND user_id = ?");
        $linkCheck->bind_param("ii", $event_id, $user_id);
        $linkCheck->execute();
        $linkRes = $linkCheck->get_result();
        
        if ($linkRes->num_rows > 0) {
            // RESTORE
            $link_id = $linkRes->fetch_assoc()['id'];
            $restore = $conn->prepare("UPDATE event_teams SET status='Active', is_deleted=0, role=? WHERE id=?");
            $restore->bind_param("si", $role, $link_id);
            $restore->execute();
            $msg = $msg_prefix . "restored to this event.";
        } else {
            // CREATE LINK
            $insert = $conn->prepare("INSERT INTO event_teams (event_id, user_id, role, status, is_deleted) VALUES (?, ?, ?, 'Active', 0)");
            $insert->bind_param("iis", $event_id, $user_id, $role);
            $insert->execute();
            $msg = $msg_prefix . "added to this event.";
        }

        // Step 3: Send Email Notification
        require_once __DIR__ . '/../app/core/CustomMailer.php';
        $site_link = "https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php"; 

        $evt_name = "the Event";
        $e_query = $conn->query("SELECT title FROM events WHERE id = $event_id");
        if ($row = $e_query->fetch_assoc()) $evt_name = $row['title'];

        $subject = "Team Assignment: $role for $evt_name";
        
        $body = "
            <h2>Welcome, $name!</h2>
            <p>You have been assigned as a <b>$role</b> for <b>$evt_name</b>.</p>
            
            <div style='background:#f3f4f6; padding:15px; border-radius:8px; border:1px solid #ddd; margin:20px 0;'>
                <strong>Your Login Credentials:</strong><br>
                Email: <b>$email</b><br>
                Password: <b>$pass</b>
            </div>

            <p>Please login to your dashboard to start managing your tasks:</p>
            <p><a href='$site_link' style='background:#F59E0B; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Login to Dashboard</a></p>
        ";

        queueEmail($email, $subject, $body);

        $conn->commit();
        header("Location: ../public/organizers.php?success=" . urlencode($msg));

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        if ($e->getCode() == 1062) {
            header("Location: ../public/organizers.php?error=Email address is already in use.");
        } else {
            header("Location: ../public/organizers.php?error=Database Error: " . urlencode($e->getMessage()));
        }
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: ../public/organizers.php?error=" . urlencode($e->getMessage()));
    }
    exit();
}

// ACTION 2: UPDATE ORGANIZER (Edit via Edit Button)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    
    $user_id = (int)$_POST['org_id']; 
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role  = trim($_POST['role']);
    $pass  = trim($_POST['password']);

    $conn->begin_transaction();
    try {
        // 1. GET CURRENT ROLE
        $checkRole = $conn->query("SELECT role FROM users WHERE id = $user_id");
        $existing = $checkRole->fetch_assoc();
        
        if ($existing['role'] === 'Event Manager') {
             throw new Exception("Cannot edit Event Manager accounts here.");
        }

        // 2. CHECK ROLE CONSISTENCY (If editing role)
        if ($existing['role'] !== $role) {
             throw new Exception("Cannot change role. This user is registered as '" . $existing['role'] . "'.");
        }

        // Duplicate Email Check
        $dupCheck = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $dupCheck->bind_param("si", $email, $user_id);
        $dupCheck->execute();
        if ($dupCheck->get_result()->num_rows > 0) {
            throw new Exception("This email is already used by another account.");
        }

        // Update User Profile (EXCLUDING ROLE)
        if (!empty($pass)) {
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, password=? WHERE id=?");
            $stmt->bind_param("ssssi", $name, $email, $phone, $hashed_pass, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=?");
            $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
        }
        $stmt->execute();

        // SYNC: Update Role in Event Teams
        $stmt2 = $conn->prepare("UPDATE event_teams SET role=? WHERE user_id=?");
        $stmt2->bind_param("si", $role, $user_id);
        $stmt2->execute();

        $conn->commit();
        header("Location: ../public/organizers.php?success=Organizer updated");

    } catch (Exception $e) {
        $conn->rollback();
        header("Location: ../public/organizers.php?error=" . urlencode($e->getMessage()));
    }
    exit();
}

// ACTION 3: REMOVE / RESTORE / ARCHIVE (Fixed Logic)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $link_id = (int)$_GET['id']; // This is event_teams.id
    $type = $_GET['action'];

    $conn->begin_transaction();
    try {
        $q = $conn->query("SELECT user_id FROM event_teams WHERE id = $link_id");
        if ($q->num_rows === 0) {
             throw new Exception("Organizer not found in event list");
        }
        $target_user_id = $q->fetch_assoc()['user_id'];

        if ($type === 'delete') {
            $conn->query("UPDATE event_teams SET is_deleted = 1 WHERE id = $link_id");
            $conn->query("UPDATE users SET status = 'Inactive' WHERE id = $target_user_id");
            $redirect_view = 'archived'; 
            $msg = "Organizer removed permanently.";

        } elseif ($type === 'restore') {
            $conn->query("UPDATE event_teams SET status = 'Active', is_deleted = 0 WHERE id = $link_id");
            $conn->query("UPDATE users SET status = 'Active' WHERE id = $target_user_id");
            $redirect_view = 'archived'; 
            $msg = "Organizer restored successfully.";

        } else {
            $conn->query("UPDATE event_teams SET status = 'Inactive' WHERE id = $link_id");
            $conn->query("UPDATE users SET status = 'Inactive' WHERE id = $target_user_id");
            $redirect_view = 'active'; 
            $msg = "Organizer moved to archive.";
        }

        $conn->commit();
        header("Location: ../public/organizers.php?view=$redirect_view&success=" . urlencode($msg));

    } catch (Exception $e) {
        $conn->rollback();
        header("Location: ../public/organizers.php?error=" . urlencode($e->getMessage()));
    }
    exit();
}
?>