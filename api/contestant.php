<?php
// bpms/api/contestant.php
// Purpose: Backend controller for managing Contestants.

require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole(['Event Manager', 'Contestant Manager']); 
require_once __DIR__ . '/../app/config/database.php';

// HELPER: Parse "34-24-36" string into separate values
function parseVitalStats($str) {
    $parts = preg_split('/[^0-9.]/', $str); 
    return [
        'bust'  => isset($parts[0]) ? (float)$parts[0] : 0,
        'waist' => isset($parts[1]) ? (float)$parts[1] : 0,
        'hips'  => isset($parts[2]) ? (float)$parts[2] : 0
    ];
}

// PART 1: HANDLE POST REQUESTS (CREATE / UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $action = $_POST['action'] ?? 'create';
    $event_id = (int)$_POST['event_id'];
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $age      = (int)$_POST['age'];
    $height   = trim($_POST['height']);
    $hometown = trim($_POST['hometown']);
    $motto    = trim($_POST['motto']);
    
    // Inputs from Modal
    $bust  = !empty($_POST['bust']) ? (float)$_POST['bust'] : 0;
    $waist = !empty($_POST['waist']) ? (float)$_POST['waist'] : 0;
    $hips  = !empty($_POST['hips']) ? (float)$_POST['hips'] : 0;

    // --- CREATE NEW ---
    if ($action === 'create') {
        $pass = trim($_POST['password']);
        
        // Check photo
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== 0) {
            header("Location: ../public/contestants.php?error=Photo is required");
            exit();
        }

        $photo_name = uploadPhoto($_FILES['photo']);
        if (!$photo_name) {
            header("Location: ../public/contestants.php?error=Photo upload failed");
            exit();
        }

        $conn->begin_transaction();
        try {
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            
            // [UPDATED] Get the ID of the manager creating this account
            $creator_id = $_SESSION['user_id'];

            // 1. Create User Login (Added created_by column)
            $stmt1 = $conn->prepare("INSERT INTO users (name, email, password, role, status, created_by) VALUES (?, ?, ?, 'Contestant', 'Active', ?)");
            $stmt1->bind_param("sssi", $name, $email, $hashed_pass, $creator_id);
            $stmt1->execute();
            $user_id = $conn->insert_id;

            // 2. Auto-Assign Contestant Number
            $num_q = $conn->query("SELECT MAX(contestant_number) as max_num FROM event_contestants WHERE event_id = $event_id");
            $next_num = ($num_q->fetch_assoc()['max_num'] ?? 0) + 1;

            // 3. Create Profile
            $stmt2 = $conn->prepare("INSERT INTO event_contestants (user_id, event_id, contestant_number, age, height, bust, waist, hips, hometown, motto, photo, status, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', 0)");
            $stmt2->bind_param("iiidddddsss", $user_id, $event_id, $next_num, $age, $height, $bust, $waist, $hips, $hometown, $motto, $photo_name);
            $stmt2->execute();
            
            // Send Email
            require_once __DIR__ . '/../app/core/CustomMailer.php';
            // Make sure this link is correct for your pc
            $site_link = "https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php"; 
            
            $e_query = $conn->query("SELECT title FROM events WHERE id = $event_id");
            $evt_name = ($row = $e_query->fetch_assoc()) ? $row['title'] : "the Pageant";

            $subject = "Official Contestant Registration";
            $body = "<h2>Welcome, $name!</h2><p>You have been registered for <b>$evt_name</b> as Contestant #$next_num.</p><div style='background:#f3f4f6; padding:15px;'><strong>Credentials:</strong><br>Email: $email<br>Password: $pass</div><p><a href='$site_link'>Login Now</a></p>";
            
            queueEmail($email, $subject, $body); 
            
            $conn->commit();
            header("Location: ../public/contestants.php?success=Contestant added successfully");

        } catch (Exception $e) {
            $conn->rollback();
            header("Location: ../public/contestants.php?error=Database Error: " . $e->getMessage());
        }
    } 

    // --- UPDATE EXISTING ---
    elseif ($action === 'update') {
        $u_id = (int)$_POST['contestant_id']; 
        
        $uid_q = $conn->query("SELECT user_id FROM event_contestants WHERE id = $u_id");
        $real_user_id = $uid_q->fetch_assoc()['user_id'];

        $pass = trim($_POST['password']);

        $conn->begin_transaction();
        try {
            // 1. Update Login
            if (!empty($pass)) {
                $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
                $stmt1 = $conn->prepare("UPDATE users SET name=?, email=?, password=? WHERE id=?");
                $stmt1->bind_param("sssi", $name, $email, $hashed_pass, $real_user_id);
            } else {
                $stmt1 = $conn->prepare("UPDATE users SET name=?, email=? WHERE id=?");
                $stmt1->bind_param("ssi", $name, $email, $real_user_id);
            }
            $stmt1->execute();

            // 2. Update Profile
            $photo_sql_part = "";
            $params = [$event_id, $age, $height, $bust, $waist, $hips, $hometown, $motto];
            $types = "iiddddss";

            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
                $photo_name = uploadPhoto($_FILES['photo']);
                if ($photo_name) {
                    $photo_sql_part = ", photo=?";
                    $params[] = $photo_name;
                    $types .= "s";
                }
            }
            
            $params[] = $real_user_id; 
            $types .= "i";

            $sql = "UPDATE event_contestants SET event_id=?, age=?, height=?, bust=?, waist=?, hips=?, hometown=?, motto=? $photo_sql_part WHERE user_id=?";
            $stmt2 = $conn->prepare($sql);
            $stmt2->bind_param($types, ...$params);
            $stmt2->execute();
            
            $conn->commit();
            header("Location: ../public/contestants.php?success=Contestant updated");

        } catch (Exception $e) {
            $conn->rollback();
            header("Location: ../public/contestants.php?error=Update Failed: " . $e->getMessage());
        }
    }
    exit();
}

// HELPER: PHOTO UPLOADER
function uploadPhoto($file) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png'];
    if (in_array($ext, $allowed)) {
        $new_name = "contestant_" . time() . "." . $ext;
        $target = __DIR__ . '/../public/assets/uploads/contestants/' . $new_name;
        
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true);
        
        if (move_uploaded_file($file['tmp_name'], $target)) {
            return $new_name;
        }
    }
    return false;
}

// PART 2: HANDLE GET ACTIONS
if (isset($_GET['action']) && isset($_GET['id'])) {
    
    $target_id = (int)$_GET['id'];
    $q = $conn->query("SELECT user_id, event_id FROM event_contestants WHERE id = $target_id");
    $res = $q->fetch_assoc();
    $target_user_id = $res['user_id'];
    $event_id = $res['event_id'];

    $action = $_GET['action'];
    $my_id = $_SESSION['user_id'];

    // Security Check
    $check_auth = $conn->prepare("
        SELECT id FROM events WHERE id = ? AND manager_id = ?
        UNION
        SELECT et.id FROM event_teams et WHERE et.event_id = ? AND et.user_id = ? AND et.status='Active'
    ");
    $check_auth->bind_param("iiii", $event_id, $my_id, $event_id, $my_id);
    $check_auth->execute();
    
    if ($check_auth->get_result()->num_rows === 0) {
        header("Location: ../public/contestants.php?error=Unauthorized Action");
        exit();
    }

    // Action Logic
    if ($action === 'delete') {
        $stmt = $conn->prepare("UPDATE event_contestants SET is_deleted = 1 WHERE id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $msg = "Contestant permanently removed from list.";
        $tab = 'archived';

    } elseif ($action === 'restore') {
        $conn->begin_transaction();
        $conn->query("UPDATE users SET status = 'Active' WHERE id = $target_user_id");
        $conn->query("UPDATE event_contestants SET is_deleted = 0, status = 'Active' WHERE id = $target_id");
        $conn->commit();
        $msg = "Contestant restored successfully.";
        $tab = 'archived';

    } elseif ($action === 'remove') {
        $stmt = $conn->prepare("UPDATE users SET status = 'Inactive' WHERE id = ?");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        
        $conn->query("UPDATE event_contestants SET status = 'Inactive' WHERE id = $target_id");
        
        $msg = "Contestant moved to archive.";
        $tab = 'active';

    } elseif ($action === 'approve') {
        $conn->begin_transaction();
        $conn->query("UPDATE users SET status = 'Active' WHERE id = $target_user_id");
        $conn->query("UPDATE event_contestants SET status = 'Active' WHERE id = $target_id");
        $conn->commit();

        $msg = "Application Approved";
        $tab = 'pending';

        // Notification
        require_once __DIR__ . '/../app/core/CustomMailer.php';
        $site_link = "https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php";
        $u_data = $conn->query("SELECT name, email FROM users WHERE id = $target_user_id")->fetch_assoc();
        if ($u_data) sendCustomEmail($u_data['email'], "Application ACCEPTED", "<h2>Congrats {$u_data['name']}!</h2><p>Your application is accepted.</p><p><a href='$site_link'>Login</a></p>");

    } elseif ($action === 'reject') {
        $conn->begin_transaction();
        $conn->query("UPDATE users SET status = 'Rejected' WHERE id = $target_user_id");
        $conn->query("UPDATE event_contestants SET status = 'Rejected' WHERE id = $target_id");
        $conn->commit();

        $msg = "Application Rejected";
        $tab = 'pending';
    }
    
    header("Location: ../public/contestants.php?view=$tab&success=" . urlencode($msg));
    exit();
}
?>