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

// HELPER: EMAIL QUEUE
function queueEmail($to, $subject, $body) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO email_queue (recipient_email, subject, body, status) VALUES (?, ?, ?, 'pending')");
    $stmt->bind_param("sss", $to, $subject, $body);
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
    $msg = ""; 

    try {
        if ($is_chairman) {
            resetChairman($conn, $event_id);
        }

        // Check User
        $check = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $res = $check->get_result();
        
        $msg_prefix = ""; 

        if ($res->num_rows > 0) {
            $existingUser = $res->fetch_assoc();
            if ($existingUser['role'] !== 'Judge') {
                throw new Exception("Email registered as '" . $existingUser['role'] . "'. Users cannot hold multiple roles.");
            }
            $judge_id = $existingUser['id'];
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            $updateUser = $conn->prepare("UPDATE users SET name = ?, password = ?, status = 'Active' WHERE id = ?");
            $updateUser->bind_param("ssi", $name, $hashed_pass, $judge_id);
            $updateUser->execute();
            $msg_prefix = "Existing Judge account updated & "; 
        } else {
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (created_by, name, email, password, role, status) VALUES (?, ?, ?, ?, 'Judge', 'Active')");
            $creator = $_SESSION['user_id'];
            $stmt->bind_param("isss", $creator, $name, $email, $hashed_pass);
            $stmt->execute();
            $judge_id = $conn->insert_id;
            $msg_prefix = "New judge created & ";
        }

        // Link to Event
        $linkCheck = $conn->prepare("SELECT id FROM event_judges WHERE event_id = ? AND judge_id = ?");
        $linkCheck->bind_param("ii", $event_id, $judge_id);
        $linkCheck->execute();
        $linkRes = $linkCheck->get_result();
        
        if ($linkRes->num_rows > 0) {
            $link_id = $linkRes->fetch_assoc()['id'];
            $update = $conn->prepare("UPDATE event_judges SET status='Active', is_deleted=0, is_chairman=? WHERE id=?");
            $update->bind_param("ii", $is_chairman, $link_id);
            $update->execute();
            $msg = $msg_prefix . "restored to event.";
        } else {
            $insert = $conn->prepare("INSERT INTO event_judges (event_id, judge_id, is_chairman, status, is_deleted) VALUES (?, ?, ?, 'Active', 0)");
            $insert->bind_param("iii", $event_id, $judge_id, $is_chairman);
            $insert->execute();
            $msg = $msg_prefix . "added to event.";
        }

        $conn->commit();

        // Email Notification
        // Note: Update this link to your actual domain when deploying
        $site_link = "http://" . $_SERVER['HTTP_HOST'] . "/bpms_v2/public/index.php"; 
        $evt_name = "the Pageant";
        $e_query = $conn->query("SELECT title FROM events WHERE id = $event_id");
        if ($row = $e_query->fetch_assoc()) { $evt_name = $row['title']; }

        $subject = "Official Invitation: Judge for $evt_name";
        $body = "<h2>Hello, $name!</h2><p>You have been assigned as a Judge.</p>
                 <div style='background:#f3f4f6; padding:15px;'><strong>Credentials:</strong><br>Email: <b>$email</b><br>Password: <b>$pass</b></div>
                 <p><a href='$site_link'>Login Now</a></p>";

        queueEmail($email, $subject, $body);
        header("Location: ../public/judges.php?success=" . urlencode($msg));

    } catch (Exception $e) {
        $conn->rollback();
        header("Location: ../public/judges.php?error=Error: " . $e->getMessage());
    }
    exit();
}

// --- 2. UPDATE JUDGE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
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
        if ($is_chairman) resetChairman($conn, $event_id);
        
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id']) && $_POST['action'] !== 'unlock_scorecard') {
    $link_id = (int)$_POST['id'];
    $action  = $_POST['action'];

    $conn->begin_transaction();
    try {
        $q = $conn->query("SELECT judge_id FROM event_judges WHERE id = $link_id");
        if ($q->num_rows === 0) throw new Exception("Judge not found");
        $target_user_id = $q->fetch_assoc()['judge_id'];

        if ($action === 'delete') {
            // FIX: Soft Delete BOTH tables and set is_deleted = 1
            $conn->query("UPDATE event_judges SET is_deleted = 1 WHERE id = $link_id");
            $conn->query("UPDATE users SET status = 'Inactive', is_deleted = 1 WHERE id = $target_user_id");
            
            $view = 'archived'; $msg = "Judge removed permanently.";

        } elseif ($action === 'restore') {
            // FIX: Restore BOTH tables and set is_deleted = 0
            $conn->query("UPDATE event_judges SET status = 'Active', is_deleted = 0 WHERE id = $link_id");
            $conn->query("UPDATE users SET status = 'Active', is_deleted = 0 WHERE id = $target_user_id");
            
            $view = 'archived'; $msg = "Judge restored.";

        } else {
            // Archive (Temporary) - Just status inactive, do not delete user account
            $conn->query("UPDATE event_judges SET status = 'Inactive' WHERE id = $link_id");
            $conn->query("UPDATE users SET status = 'Inactive' WHERE id = $target_user_id");
            
            $view = 'active'; $msg = "Judge archived.";
        }
        
        $conn->commit();
        header("Location: ../public/judges.php?view=$view&success=" . urlencode($msg));

    } catch (Exception $e) {
        $conn->rollback();
        header("Location: ../public/judges.php?error=" . urlencode($e->getMessage()));
    }
    exit();
}

// --- 4. UNLOCK SCORECARD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlock_scorecard') {
    $judge_id = (int)$_POST['judge_id'];
    $round_id = (int)$_POST['round_id'];

    if (!$judge_id || !$round_id) {
        header("Location: ../public/judges.php?error=Invalid Parameters");
        exit();
    }

    try {
        // Unlock by resetting status to Pending AND setting unlocked_at to NOW()
        $stmt = $conn->prepare("UPDATE judge_round_status 
                                SET status = 'Pending', unlocked_at = NOW() 
                                WHERE round_id = ? AND judge_id = ?");
        $stmt->bind_param("ii", $round_id, $judge_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            header("Location: ../public/judges.php?success=Scorecard Unlocked");
        } else {
            header("Location: ../public/judges.php?error=Could not unlock (Maybe it wasn't submitted?)");
        }
    } catch (Exception $e) {
        header("Location: ../public/judges.php?error=" . urlencode($e->getMessage()));
    }
    exit();
}
?>