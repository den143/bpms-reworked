<?php
// Purpose: Backend controller for Public Contestant Registration.
// Handles self-registration, file uploads, and sets account status to 'Pending' for approval.

session_start();
require_once __DIR__ . '/../app/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. INPUT CAPTURE
    $event_id = (int)$_POST['event_id'];
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Profile Details
    $age      = (int)$_POST['age'];
    $height   = trim($_POST['height']);
    $hometown = trim($_POST['hometown']);
    $motto    = trim($_POST['motto']);
    
    // LOGIC: Parse "34-24-36" string into separate values for the new DB
    $vitals_str = trim($_POST['vital_stats']);
    $parts = preg_split('/[^0-9.]/', $vitals_str);
    $bust  = !empty($_POST['bust']) ? (float)$_POST['bust'] : 0;
    $waist = !empty($_POST['waist']) ? (float)$_POST['waist'] : 0;
    $hips  = !empty($_POST['hips']) ? (float)$_POST['hips'] : 0;

    // 2. VALIDATION
    if (empty($name) || empty($email) || empty($password) || empty($event_id)) {
        header("Location: ../public/register.php?error=All required fields must be filled.");
        exit();
    }

    // Logic: Prevent duplicate emails
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        header("Location: ../public/register.php?error=Email is already registered. Please login.");
        exit();
    }

    // 3. PHOTO UPLOADER
    $photo_name = "default_contestant.png";
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        
        // Security: Ensure only image files are uploaded
        if (in_array($ext, $allowed)) {
            $new_name = "contestant_" . time() . "." . $ext;
            $upload_dir = __DIR__ . '/../public/assets/uploads/contestants/';
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_name)) {
                $photo_name = $new_name;
            } else {
                header("Location: ../public/register.php?error=Failed to upload photo.");
                exit();
            }
        } else {
            header("Location: ../public/register.php?error=Invalid file type. Only JPG/PNG allowed.");
            exit();
        }
    }

    // 4. DATABASE TRANSACTION
    // Purpose: Create User Account, Calculate Number, and Create Profile simultaneously.
    $conn->begin_transaction();

    try {
        // Step A: Create User Account (Status = Pending)
        // The user cannot login until an Admin approves this 'Pending' status.
        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
        $role = 'Contestant';
        $status = 'Pending';
        
        $stmt1 = $conn->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt1->bind_param("sssss", $name, $email, $hashed_pass, $role, $status);
        
        if (!$stmt1->execute()) {
            throw new Exception("Error creating user account.");
        }
        $user_id = $conn->insert_id; 

        // Step B: Calculate Next Contestant Number
        // The new DB requires a number. We find the max number for this event and add 1.
        $num_q = $conn->query("SELECT MAX(contestant_number) as max_num FROM event_contestants WHERE event_id = $event_id");
        $next_num = ($num_q->fetch_assoc()['max_num'] ?? 0) + 1;

        // Step C: Create Contestant Profile
        $stmt2 = $conn->prepare("INSERT INTO event_contestants (user_id, event_id, contestant_number, age, height, bust, waist, hips, hometown, motto, photo, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt2->bind_param("iiidddddsss", $user_id, $event_id, $next_num, $age, $height, $bust, $waist, $hips, $hometown, $motto, $photo_name);
        
        if (!$stmt2->execute()) {
            throw new Exception("Error saving contestant details.");
        }

        // Commit changes
        $conn->commit();
        header("Location: ../public/register.php?success=Registration submitted! Please wait for approval.");

    } catch (Exception $e) {
        $conn->rollback(); // Revert changes on error
        header("Location: ../public/register.php?error=" . urlencode($e->getMessage()));
    }
    exit();

} else {
    // Block direct access
    header("Location: ../public/register.php");
    exit();
}
?>