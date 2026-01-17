<?php
// api/register_contestant.php
session_start();
require_once __DIR__ . '/../app/config/database.php';

// Enable error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../public/register.php?error=Invalid Request");
    exit;
}

$transaction_started = false;

try {
    // 1. Sanitize Inputs
    $event_id = intval($_POST['event_id']);
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $age      = intval($_POST['age']);
    $height   = floatval($_POST['height']);
    $bust     = floatval($_POST['bust']);
    $waist    = floatval($_POST['waist']);
    $hips     = floatval($_POST['hips']);
    $hometown = trim($_POST['hometown']);
    $motto    = trim($_POST['motto']);

    // 2. Validate
    if (empty($event_id) || empty($name) || empty($email) || empty($password)) {
        throw new Exception("All fields are required");
    }

    $conn->begin_transaction();
    $transaction_started = true;

    // 4. Handle File Upload
    $photo_filename = 'default_contestant.png';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['photo']['tmp_name'];
        $originalName = $_FILES['photo']['name'];
        $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileExtension, $allowed)) {
            throw new Exception("Invalid file type. Only JPG, PNG, GIF allowed.");
        }

        $uploadDir = __DIR__ . '/../public/assets/uploads/contestants/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $newFileName = 'contestant_' . time() . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;

        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            throw new Exception("Failed to upload photo");
        }
        $photo_filename = $newFileName;
    }

    // 5. CHECK USER EXISTENCE LOGIC
    $user_id = 0;
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // SCENARIO A: Returning Contestant
        if ($row['role'] !== 'Contestant') {
            throw new Exception("This email is already registered as a " . $row['role'] . ". Please use a different email.");
        }
        
        // Verify Password for linking
        if (password_verify($password, $row['password'])) {
            $user_id = $row['id'];

            // Check if they are already in THIS specific event
            $dupCheck = $conn->prepare("SELECT id FROM event_contestants WHERE event_id = ? AND user_id = ?");
            $dupCheck->bind_param("ii", $event_id, $user_id);
            $dupCheck->execute();
            if ($dupCheck->get_result()->num_rows > 0) {
                throw new Exception("You are already registered for this specific event.");
            }
            $dupCheck->close();

        } else {
            throw new Exception("This email is registered. Incorrect password.");
        }
    } else {
        // SCENARIO B: New User
        $stmt->close(); 

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'Contestant';
        $status = 'Active';

        $insertUser = $conn->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $insertUser->bind_param("sssss", $name, $email, $hashed_password, $role, $status);
        $insertUser->execute();
        $user_id = $conn->insert_id;
        $insertUser->close();
    }
    
    // 6. Calculate next Contestant Number
    $stmt = $conn->prepare("SELECT MAX(contestant_number) as max_num FROM event_contestants WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $next_number = ($res['max_num'] ?? 0) + 1;
    $stmt->close();

    // 7. Insert into 'event_contestants'
    $contestant_status = 'Pending';
    
    $stmt = $conn->prepare("INSERT INTO event_contestants 
        (event_id, user_id, contestant_number, age, hometown, motto, height, bust, waist, hips, photo, status, registered_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->bind_param("iiiissddddss", 
        $event_id, 
        $user_id, 
        $next_number, 
        $age, 
        $hometown, 
        $motto, 
        $height, 
        $bust, 
        $waist, 
        $hips, 
        $photo_filename,
        $contestant_status
    );
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    header("Location: ../public/register.php?success=Registration successful! You may now login.");

} catch (Exception $e) {
    if ($transaction_started) {
        try { $conn->rollback(); } catch (Exception $ex) {}
    }
    header("Location: ../public/register.php?error=" . urlencode($e->getMessage()));
}
?>