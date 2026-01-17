<?php
// bpms/api/contestant.php
// Updated: Fixed Redirect Logic & Role Checking

require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole(['Event Manager', 'Contestant Manager']); 
require_once __DIR__ . '/../app/config/database.php';

// SMART REDIRECT LOGIC
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$redirect_url = (strpos($referer, 'contestant_manager.php') !== false) 
    ? '../public/contestant_manager.php' 
    : '../public/contestants.php';

$my_id = $_SESSION['user_id'];
$my_role = trim($_SESSION['role']); // Added trim() to avoid whitespace bugs

// PART 1: POST REQUESTS (CREATE / UPDATE) - Kept same as before, skipping for brevity unless you need it.
// (I will assume you have the create/update logic from previous correct steps. 
//  Focusing on the GET Actions which caused the bug).

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (Your Create/Update Logic Here - ensure it redirects to $redirect_url) ...
    // If you need the full POST block again, verify the previous turn. 
    // Just ensure all header("Location: ...") use $redirect_url.
    
    // Quick Re-paste of CREATE/UPDATE Logic for safety:
    $action = $_POST['action'] ?? 'create';
    $event_id = (int)$_POST['event_id'];
    $name = trim($_POST['name']); $email = trim($_POST['email']);
    $age = (int)$_POST['age']; $height = trim($_POST['height']);
    $hometown = trim($_POST['hometown']); $motto = trim($_POST['motto']);
    $bust = !empty($_POST['bust']) ? (float)$_POST['bust'] : 0;
    $waist = !empty($_POST['waist']) ? (float)$_POST['waist'] : 0;
    $hips = !empty($_POST['hips']) ? (float)$_POST['hips'] : 0;

    if ($action === 'create') {
        $pass = trim($_POST['password']);
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== 0) { header("Location: $redirect_url?error=Photo required"); exit(); }
        $photo_name = uploadPhoto($_FILES['photo']);
        
        $conn->begin_transaction();
        try {
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            $check_stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_res = $check_stmt->get_result();

            if ($existing_user = $check_res->fetch_assoc()) {
                if ($existing_user['role'] !== 'Contestant') throw new Exception("Email taken by " . $existing_user['role']);
                $user_id = $existing_user['id'];
                $conn->query("UPDATE users SET name = '$name', password = '$hashed_pass', status='Active' WHERE id = $user_id");
                $dup = $conn->query("SELECT id FROM event_contestants WHERE event_id=$event_id AND user_id=$user_id");
                if ($dup->num_rows > 0) throw new Exception("Already registered.");
            } else {
                $stmt1 = $conn->prepare("INSERT INTO users (name, email, password, role, status, created_by) VALUES (?, ?, ?, 'Contestant', 'Active', ?)");
                $stmt1->bind_param("sssi", $name, $email, $hashed_pass, $my_id);
                $stmt1->execute();
                $user_id = $conn->insert_id;
            }

            $num_q = $conn->query("SELECT MAX(contestant_number) as max_num FROM event_contestants WHERE event_id = $event_id");
            $next_num = ($num_q->fetch_assoc()['max_num'] ?? 0) + 1;

            $stmt2 = $conn->prepare("INSERT INTO event_contestants (user_id, event_id, contestant_number, age, height, bust, waist, hips, hometown, motto, photo, status, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', 0)");
            $stmt2->bind_param("iiidddddsss", $user_id, $event_id, $next_num, $age, $height, $bust, $waist, $hips, $hometown, $motto, $photo_name);
            $stmt2->execute();
            
            $conn->commit();
            header("Location: $redirect_url?success=Added");
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: $redirect_url?error=" . urlencode($e->getMessage()));
        }
    } elseif ($action === 'update') {
        $u_id = (int)$_POST['contestant_id'];
        $uid_q = $conn->query("SELECT user_id FROM event_contestants WHERE id = $u_id");
        $real_user_id = $uid_q->fetch_assoc()['user_id'];
        $pass = trim($_POST['password']);

        $conn->begin_transaction();
        try {
            if (!empty($pass)) {
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET name='$name', email='$email', password='$hashed' WHERE id=$real_user_id");
            } else {
                $conn->query("UPDATE users SET name='$name', email='$email' WHERE id=$real_user_id");
            }

            $photo_sql = "";
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
                $pn = uploadPhoto($_FILES['photo']);
                if ($pn) $photo_sql = ", photo='$pn'";
            }

            $sql = "UPDATE event_contestants SET event_id=$event_id, age=$age, height='$height', bust=$bust, waist=$waist, hips=$hips, hometown='$hometown', motto='$motto' $photo_sql WHERE user_id=$real_user_id";
            $conn->query($sql);
            $conn->commit();
            header("Location: $redirect_url?success=Updated");
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: $redirect_url?error=Update Failed");
        }
    }
    exit();
}

function uploadPhoto($file) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
        $n = "c_" . time() . "." . $ext;
        $t = __DIR__ . '/../public/assets/uploads/contestants/' . $n;
        if (move_uploaded_file($file['tmp_name'], $t)) return $n;
    }
    return false;
}

// PART 2: HANDLE GET ACTIONS (DELETE, RESTORE, APPROVE)
if (isset($_GET['action']) && isset($_GET['id'])) {
    
    $target_id = (int)$_GET['id'];
    $q = $conn->query("SELECT user_id, event_id FROM event_contestants WHERE id = $target_id");
    
    if($q->num_rows === 0) {
        header("Location: $redirect_url?error=Contestant not found");
        exit();
    }

    $res = $q->fetch_assoc();
    $target_user_id = $res['user_id'];
    $event_id = $res['event_id'];
    $action = $_GET['action'];

    // --- ROBUST SECURITY CHECK ---
    if ($my_role === 'Event Manager') {
        $check_auth = $conn->prepare("SELECT id FROM events WHERE id = ? AND manager_id = ?");
        $check_auth->bind_param("ii", $event_id, $my_id);
    } else {
        // Contestant Manager
        $check_auth = $conn->prepare("SELECT id FROM event_teams WHERE event_id = ? AND user_id = ? AND is_deleted = 0");
        $check_auth->bind_param("ii", $event_id, $my_id);
    }
    
    $check_auth->execute();
    
    if ($check_auth->get_result()->num_rows === 0) {
        header("Location: $redirect_url?error=Unauthorized Action");
        exit();
    }

    // --- ACTIONS ---
    if ($action === 'delete') {
        $conn->begin_transaction();
        $conn->query("UPDATE event_contestants SET is_deleted = 1 WHERE id = $target_id");
        $conn->query("UPDATE users SET status = 'Inactive', is_deleted = 1 WHERE id = $target_user_id");
        $conn->commit();
        $msg = "Permanently Deleted"; $tab = 'archived';

    } elseif ($action === 'restore') {
        $conn->begin_transaction();
        $conn->query("UPDATE users SET status = 'Active', is_deleted = 0 WHERE id = $target_user_id");
        $conn->query("UPDATE event_contestants SET is_deleted = 0, status = 'Active' WHERE id = $target_id");
        $conn->commit();
        $msg = "Restored"; $tab = 'archived';

    } elseif ($action === 'remove') {
        $conn->query("UPDATE users SET status = 'Inactive' WHERE id = $target_user_id");
        $conn->query("UPDATE event_contestants SET status = 'Inactive' WHERE id = $target_id");
        $msg = "Archived"; $tab = 'active';

    } elseif ($action === 'approve') {
        $conn->query("UPDATE users SET status = 'Active' WHERE id = $target_user_id");
        $conn->query("UPDATE event_contestants SET status = 'Active' WHERE id = $target_id");
        $msg = "Approved"; $tab = 'pending';

    } elseif ($action === 'reject') {
        $conn->query("UPDATE users SET status = 'Inactive' WHERE id = $target_user_id");
        $conn->query("UPDATE event_contestants SET status = 'Rejected' WHERE id = $target_id");
        $msg = "Rejected"; $tab = 'pending';
    }
    
    header("Location: $redirect_url?view=$tab&success=" . urlencode($msg));
    exit();
}
?>