<?php
// Purpose: Backend controller for managing Event Judges.
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

// HELPER: FAST INTERNET CHECK
function hasInternetConnection() {
    $connected = @fsockopen("www.google.com", 80, $errno, $errstr, 3);
    if ($connected) {
        fclose($connected);
        return true;
    }
    return false;
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
    $msg = ""; 

    try {
        // --- STEP A: DATABASE OPERATIONS ---
        
        if ($is_chairman) {
            resetChairman($conn, $event_id);
        }

        // 1. Check/Create User
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $res = $check->get_result();
        
        $msg_prefix = ""; 

        if ($res->num_rows > 0) {
            // Update Existing
            $existingUser = $res->fetch_assoc();
            $judge_id = $existingUser['id'];
            
            // SECURITY: Ensure we are not hijacking an admin account
            // (Optional strict check, good practice)
            // if ($existingUser['role'] === 'Event Manager') throw new Exception("Cannot add Event Manager as Judge");

            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            // Force status to Active (in case they were previously archived)
            $updateUser = $conn->prepare("UPDATE users SET name = ?, password = ?, status = 'Active' WHERE id = ?");
            $updateUser->bind_param("ssi", $name, $hashed_pass, $judge_id);
            $updateUser->execute();
            $msg_prefix = "Existing account updated & "; 
        } else {
            // Create New
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (created_by, name, email, password, role, status) VALUES (?, ?, ?, ?, 'Judge', 'Active')");
            $creator = $_SESSION['user_id'];
            $stmt->bind_param("isss", $creator, $name, $email, $hashed_pass);
            $stmt->execute();
            $judge_id = $conn->insert_id;
            $msg_prefix = "New judge created & ";
        }

        // 2. Link Logic
        $linkCheck = $conn->prepare("SELECT id FROM event_judges WHERE event_id = ? AND judge_id = ?");
        $linkCheck->bind_param("ii", $event_id, $judge_id);
        $linkCheck->execute();
        $linkRes = $linkCheck->get_result();
        
        if ($linkRes->num_rows > 0) {
            $link_id = $linkRes->fetch_assoc()['id'];
            $update = $conn->prepare("UPDATE event_judges SET status='Active', is_deleted=0, is_chairman=? WHERE id=?");
            $update->bind_param("ii", $is_chairman, $link_id);
            $update->execute();
            $msg = $msg_prefix . "restored to event list.";
        } else {
            $insert = $conn->prepare("INSERT INTO event_judges (event_id, judge_id, is_chairman, status, is_deleted) VALUES (?, ?, ?, 'Active', 0)");
            $insert->bind_param("iii", $event_id, $judge_id, $is_chairman);
            $insert->execute();
            $msg = $msg_prefix . "added to event list.";
        }

        // *** CRITICAL STEP: SAVE TO DATABASE NOW ***
        // This ensures the Judge is saved even if the email fails later.
        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        header("Location: ../public/judges.php?error=Database Error: " . $e->getMessage());
        exit();
    }

    // --- STEP B: EMAIL NOTIFICATION ---
    // The Judge is already saved. We try to email, but we won't wait/check for internet first.
    
    try {
        // REMOVED: if (!hasInternetConnection()) ... (This removes the 3-second delay)

        require_once __DIR__ . '/../app/core/CustomMailer.php';
        
        $site_link = "https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php"; 
        $evt_name = "the Pageant";
        
        $e_query = $conn->query("SELECT title FROM events WHERE id = $event_id");
        if ($row = $e_query->fetch_assoc()) {
            $evt_name = $row['title'];
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

        // This will try to send. If no SMTP is set up, it might fail instantly or log an error.
        queueEmail($email, $subject, $body);

        header("Location: ../public/judges.php?success=" . urlencode($msg));

    } catch (Exception $e) {
        // If email fails, we just warn the user.
        $warningMsg = "Judge saved successfully. (Email notification could not be sent: " . $e->getMessage() . ")";
        header("Location: ../public/judges.php?warning=" . urlencode($warningMsg));
    }
    exit();
}

// --- 2. UPDATE JUDGE (Edit details) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    // (Existing Update Logic - No changes needed here, just kept for completeness)
    $link_id  = (int)$_POST['link_id'];
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $pass     = trim($_POST['password']);
    $is_chairman = isset($_POST['is_chairman']) ? 1 : 0;
    
    $check_stmt = $conn->prepare("SELECT judge_id, event_id FROM event_judges WHERE id = ?");
    $check_stmt->bind_param("i", $link_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: ../public/judges.php?error=Invalid Judge Reference");
        exit();
    }

    $row = $result->fetch_assoc();
    $judge_id = $row['judge_id'];
    $event_id = $row['event_id'];

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

// --- 3. REMOVE / RESTORE / DELETE (FIXED) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    $link_id = (int)$_POST['id'];
    $action  = $_POST['action'];

    $conn->begin_transaction();
    try {
        // 1. FETCH USER ID (Needed to update the 'users' table)
        $q = $conn->query("SELECT judge_id FROM event_judges WHERE id = $link_id");
        if ($q->num_rows === 0) {
             throw new Exception("Judge not found in event list");
        }
        $target_user_id = $q->fetch_assoc()['judge_id'];

        if ($action === 'delete') {
            // Update Link
            $conn->query("UPDATE event_judges SET is_deleted = 1 WHERE id = $link_id");
            
            // SYNC: Disable User Login
            $conn->query("UPDATE users SET status = 'Inactive' WHERE id = $target_user_id");

            $view = 'archived';
            $msg = "Judge permanently removed from list.";

        } elseif ($action === 'restore') {
            // Update Link
            $conn->query("UPDATE event_judges SET status = 'Active', is_deleted = 0 WHERE id = $link_id");
            
            // SYNC: Re-enable User Login
            $conn->query("UPDATE users SET status = 'Active' WHERE id = $target_user_id");

            $view = 'archived';
            $msg = "Judge restored successfully.";

        } else {
            // Archive
            $conn->query("UPDATE event_judges SET status = 'Inactive' WHERE id = $link_id");

            // SYNC: Disable User Login
            $conn->query("UPDATE users SET status = 'Inactive' WHERE id = $target_user_id");

            $view = 'active';
            $msg = "Judge archived.";
        }
        
        $conn->commit();
        header("Location: ../public/judges.php?view=$view&success=" . urlencode($msg));

    } catch (Exception $e) {
        $conn->rollback();
        header("Location: ../public/judges.php?error=" . urlencode($e->getMessage()));
    }
    exit();
}
?>