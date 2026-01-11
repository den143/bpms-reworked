<?php
// Purpose: Backend controller for managing Contestants.
// Handles CRUD operations: Create, Update, Delete (Archive), Approve, and Reject.

require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole(['Event Manager', 'Contestant Manager']); // Only authorized roles can access
require_once __DIR__ . '/../app/config/database.php';

// PART 1: HANDLE POST REQUESTS (CREATE / UPDATE)
// Purpose: Process the "Add Contestant" and "Edit Profile" forms.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $action = $_POST['action'] ?? 'create';
    $event_id = (int)$_POST['event_id'];
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    
    //Profile Details
    $age         = (int)$_POST['age'];
    $height      = trim($_POST['height']);
    $vital_stats = trim($_POST['vital_stats']);
    $hometown    = trim($_POST['hometown']);
    $motto       = trim($_POST['motto']);

    // --- CREATE NEW (Manual Add) ---
    if ($action === 'create') {
        $pass = trim($_POST['password']);
        
        // Validation: Photo is mandatory for new contestants
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== 0) {
            header("Location: ../public/contestants.php?error=Photo is required");
            exit();
        }

        $photo_name = uploadPhoto($_FILES['photo']);
        if (!$photo_name) {
            header("Location: ../public/contestants.php?error=Photo upload failed");
            exit();
        }

        // TRANSACTION START: Ensure both User and Detail records are created together.
        $conn->begin_transaction();
        try {
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            
            // Step 1: Create Login Credentials (users table)
            $stmt1 = $conn->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'Contestant', 'Active')");
            $stmt1->bind_param("sss", $name, $email, $hashed_pass);
            $stmt1->execute();
            $user_id = $conn->insert_id;

            // Step 2: Create Pageant Profile (contestant_details table)
            $stmt2 = $conn->prepare("INSERT INTO contestant_details (user_id, event_id, age, height, vital_stats, hometown, motto, photo, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
            $stmt2->bind_param("iiisssss", $user_id, $event_id, $age, $height, $vital_stats, $hometown, $motto, $photo_name);
            $stmt2->execute();
            
            // Logic: Send Welcome Email with Credentials
            require_once __DIR__ . '/../app/core/CustomMailer.php';
            $site_link = "https://juvenal-esteban-octavalent.ngrok-free.dev/bpms/public/index.php"; 
            $evt_name = "the Pageant";
            $e_query = $conn->query("SELECT name FROM events WHERE id = $event_id");
            if ($row = $e_query->fetch_assoc()) $evt_name = $row['name'];

            $subject = "Official Contestant Registration";
            $body = "<h2>Welcome, $name!</h2><p>You have been registered for <b>$evt_name</b>.</p><div style='background:#f3f4f6; padding:15px; border-radius:8px; margin:20px 0;'><strong>Credentials:</strong><br>Email: <b>$email</b><br>Password: <b>$pass</b></div><p><a href='$site_link' style='background:#F59E0B; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Login Now</a></p>";
            
            sendCustomEmail($email, $subject, $body);

            // Commit the transaction (Save changes)
            $conn->commit();
            header("Location: ../public/contestants.php?success=Contestant added successfully");

        } catch (Exception $e) {
            // Rollback if anything failed (undoes the INSERTs)
            $conn->rollback();
            header("Location: ../public/contestants.php?error=Database Error: " . $e->getMessage());
        }
    } 

    // --- LOGIC: UPDATE EXISTING CONTESTANT ---
    elseif ($action === 'update') {
        $id = (int)$_POST['contestant_id'];
        $pass = trim($_POST['password']);

        $conn->begin_transaction();
        try {
            // Step 1: Update Login Info (Update Password only if provided)
            if (!empty($pass)) {
                $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
                $stmt1 = $conn->prepare("UPDATE users SET name=?, email=?, password=? WHERE id=?");
                $stmt1->bind_param("sssi", $name, $email, $hashed_pass, $id);
            } else {
                $stmt1 = $conn->prepare("UPDATE users SET name=?, email=? WHERE id=?");
                $stmt1->bind_param("ssi", $name, $email, $id);
            }
            $stmt1->execute();

            // Step 2: Update Profile Info (Handle optional photo update)
            $photo_name = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
                $photo_name = uploadPhoto($_FILES['photo']);
            }
            
            if ($photo_name) {
                $stmt2 = $conn->prepare("UPDATE contestant_details SET event_id=?, age=?, height=?, vital_stats=?, hometown=?, motto=?, photo=? WHERE user_id=?");
                $stmt2->bind_param("iisssssi", $event_id, $age, $height, $vital_stats, $hometown, $motto, $photo_name, $id);
            } else {
                $stmt2 = $conn->prepare("UPDATE contestant_details SET event_id=?, age=?, height=?, vital_stats=?, hometown=?, motto=? WHERE user_id=?");
                $stmt2->bind_param("iissssi", $event_id, $age, $height, $vital_stats, $hometown, $motto, $id);
            }
            
            $stmt2->execute();
            $conn->commit();
            header("Location: ../public/contestants.php?success=Contestant updated");

        } catch (Exception $e) {
            $conn->rollback();
            header("Location: ../public/contestants.php?error=Update Failed");
        }
    }
    exit();
}

// HELPER: PHOTO UPLOADER
// Purpose: Validates file type and moves it to the uploads folder.
function uploadPhoto($file) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png'];
    if (in_array($ext, $allowed)) {
        $new_name = "contestant_" . time() . "." . $ext;
        $target = __DIR__ . '/../public/assets/uploads/contestants/' . $new_name;
        if (move_uploaded_file($file['tmp_name'], $target)) {
            return $new_name;
        }
    }
    return false;
}

// PART 2: HANDLE GET ACTIONS (Approve / Reject / Archive / Restore)
// Purpose: Manage the status of contestants.
if (isset($_GET['action']) && isset($_GET['id'])) {
    
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    $my_id = $_SESSION['user_id'];

    // SECURITY CHECK: Authorization
    // Ensure the current user is actually the manager of the event this contestant belongs to.
    $check_auth = $conn->prepare("
        SELECT u.id FROM users u
        JOIN contestant_details cd ON u.id = cd.user_id
        JOIN events e ON cd.event_id = e.id
        LEFT JOIN event_organizers eo ON (e.id = eo.event_id AND eo.user_id = ? AND eo.status = 'Active')
        WHERE u.id = ? AND (e.user_id = ? OR eo.id IS NOT NULL)
    ");
    $check_auth->bind_param("iii", $my_id, $id, $my_id);
    $check_auth->execute();
    
    if ($check_auth->get_result()->num_rows === 0) {
        header("Location: ../public/contestants.php?error=Unauthorized Action");
        exit();
    }

    // --- ACTION LOGIC ---
    if ($action === 'delete') {
        // SOFT DELETE: Hide from list completely
        $stmt = $conn->prepare("UPDATE contestant_details SET is_deleted = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $msg = "Contestant permanently removed from list.";
        $tab = 'archived'; // Stay in archive view

    } elseif ($action === 'restore') {
        // Restore to Active status
        $conn->begin_transaction();
        $conn->query("UPDATE users SET status = 'Active' WHERE id = $id");
        $conn->query("UPDATE contestant_details SET is_deleted = 0 WHERE user_id = $id");
        $conn->commit();
        $msg = "Contestant restored successfully.";
        $tab = 'archived';

    } elseif ($action === 'remove') {
        // Archive (Move to 'Inactive')
        $stmt = $conn->prepare("UPDATE users SET status = 'Inactive' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $msg = "Contestant moved to archive.";
        $tab = 'active';

    } elseif ($action === 'approve') {
        // Approve Application
        $stmt = $conn->prepare("UPDATE users SET status = 'Active' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $msg = "Application Approved";
        $tab = 'pending';

        // Send Email (Approve)
        require_once __DIR__ . '/../app/core/CustomMailer.php';
        $site_link = "https://juvenal-esteban-octavalent.ngrok-free.dev/bpms/public/index.php"; // link for ngrok
        $u_data = $conn->query("SELECT name, email FROM users WHERE id = $id")->fetch_assoc();
        if ($u_data) sendCustomEmail($u_data['email'], "Application ACCEPTED", "<h2>Congrats {$u_data['name']}!</h2><p>Your application is accepted.</p><p><a href='$site_link'>Login</a></p>");

    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE users SET status = 'Rejected' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $msg = "Application Rejected";
        $tab = 'pending';
        
        // Send Email (Reject)
        require_once __DIR__ . '/../app/core/CustomMailer.php';
        $u_data = $conn->query("SELECT name, email FROM users WHERE id = $id")->fetch_assoc();
        if ($u_data) sendCustomEmail($u_data['email'], "Application Update", "<p>Sorry, your application was not accepted.</p>");
    }

    if (isset($stmt)) $stmt->execute();
    
    header("Location: ../public/contestants.php?view=$tab&success=" . urlencode($msg));
    exit();
}
?>