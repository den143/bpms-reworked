<?php
// Purpose: Verifies user credentials, checks account status, and 
// enforces "Gatekeeper" rules before redirecting to the correct dashboard.

session_start();
require_once __DIR__ . '/../app/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    $role  = trim($_POST['role'] ?? '');

    // Basic Validation: Ensure no fields are empty
    if (empty($email) || empty($pass) || empty($role)) {
        header("Location: ../public/index.php?error=All fields are required");
        exit();
    }

    // 1. FETCH USER
    // We search for a user with the matching email AND the selected role.
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // 2. VERIFY PASSWORD (HASHED)
        if (password_verify($pass, $row['password'])) {
            
            // SECURITY FIX: Prevent Session Fixation attacks
            session_regenerate_id(true); 

            // 3. CHECK ACCOUNT STATUS
            // Prevent login if the admin hasn't approved the account yet.
            if ($row['status'] === 'Inactive') {
                header("Location: ../public/index.php?error=Your account is deactivated.");
                exit();
            }

            // 4. GATEKEEPER LOGIC (Crucial Security Step)
            // Even if the password is correct, we must ensure the user belongs to an 'Active' event.
            
            $u_id = $row['id'];
            $has_active_event = false;

            if ($role === 'Event Manager') {
                // RULE: Managers can login if they have an Active event OR if they haven't created one yet.
                $check = $conn->prepare("SELECT id FROM events WHERE manager_id = ? AND status = 'Active'");
                $check->bind_param("i", $u_id);
                $check->execute();
                if ($check->get_result()->num_rows > 0) $has_active_event = true;
                
                // Allow login if they have NO events yet (so they can create one)
                $all_events = $conn->prepare("SELECT id FROM events WHERE manager_id = ?");
                $all_events->bind_param("i", $u_id);
                $all_events->execute();
                if ($all_events->get_result()->num_rows === 0) $has_active_event = true;

            } elseif ($role === 'Contestant') {
                // RULE: Contestant must be in 'event_contestants' linked to an Active event.
                $c_check = $conn->prepare("
                    SELECT e.status 
                    FROM event_contestants ec 
                    JOIN events e ON ec.event_id = e.id 
                    WHERE ec.user_id = ? 
                    AND ec.status IN ('Active', 'Qualified') 
                    AND e.status = 'Active' 
                    LIMIT 1
                ");
                $c_check->bind_param("i", $u_id);
                $c_check->execute();
                if ($c_check->get_result()->num_rows > 0) $has_active_event = true;

            } elseif ($role === 'Judge') {
                // RULE: Judge must be in 'event_judges' linked to an Active event.
                $j_check = $conn->prepare("
                    SELECT ej.id FROM event_judges ej 
                    JOIN events e ON ej.event_id = e.id 
                    WHERE ej.judge_id = ? 
                    AND e.status = 'Active' 
                    AND ej.status = 'Active' 
                    LIMIT 1
                ");
                $j_check->bind_param("i", $u_id);
                $j_check->execute();
                if ($j_check->get_result()->num_rows > 0) $has_active_event = true;

            } else {
                // STAFF: Judge Coordinator, Contestant Manager, Tabulator
                // RULE: Must be in 'event_teams' linked to an Active event.
                $o_check = $conn->prepare("
                    SELECT et.id FROM event_teams et 
                    JOIN events e ON et.event_id = e.id 
                    WHERE et.user_id = ? 
                    AND et.role = ?
                    AND e.status = 'Active' 
                    AND et.status = 'Active' 
                    LIMIT 1
                ");
                $o_check->bind_param("is", $u_id, $role);
                $o_check->execute();
                if ($o_check->get_result()->num_rows > 0) $has_active_event = true;
            }

            // BLOCK ACCESS if no active event is found.
            if (!$has_active_event) {
                header("Location: ../public/index.php?error=Access Denied: The event you are assigned to is not currently active.");
                exit();
            }

            // 5. LOGIN SUCCESS: Set Session Variables
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['email']   = $row['email'];
            $_SESSION['role']    = $row['role'];
            $_SESSION['name']    = $row['name'];

            // Logic: Check if Event Manager needs to see the "Create Event" modal.
            if ($role === 'Event Manager') {
                $check_event = $conn->prepare("SELECT id FROM events WHERE manager_id = ?");
                $check_event->bind_param("i", $u_id);
                $check_event->execute();
                $_SESSION['show_modal'] = ($check_event->get_result()->num_rows == 0);
            }

            // 6. REDIRECT: Send user to their specific dashboard
            switch ($role) {
                case 'Event Manager':
                    header("Location: ../public/dashboard.php");
                    break;
                case 'Judge Coordinator':
                    header("Location: ../public/judge_coordinator.php");
                    break;
                case 'Contestant Manager':
                    header("Location: ../public/contestant_manager.php"); 
                    break;
                case 'Tabulator':
                    header("Location: ../public/tabulator.php"); 
                    break;
                case 'Contestant':
                    header("Location: ../public/contestant_dashboard.php");
                    break;
                case 'Judge':
                    header("Location: ../public/judge_dashboard.php"); 
                    break;
                default:
                    header("Location: ../public/index.php?error=Role configuration error");
                    break;
            }
            exit();

        } else {
            header("Location: ../public/index.php?error=Incorrect Password");
            exit();
        }
    } else {
        header("Location: ../public/index.php?error=User not found or Role incorrect");
        exit();
    }
} else {
    // Direct access to this file is not allowed
    header("Location: ../public/index.php");
    exit();
}
?>