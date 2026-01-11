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
            
            // 3. CHECK ACCOUNT STATUS
            // Prevent login if the admin hasn't approved the account yet.
            if ($row['status'] === 'Pending') {
                header("Location: ../public/index.php?error=Your application is still pending approval.");
                exit();
            } elseif ($row['status'] === 'Rejected') {
                header("Location: ../public/index.php?error=Your application was rejected.");
                exit();
            } elseif ($row['status'] === 'Inactive') {
                header("Location: ../public/index.php?error=Your account is deactivated.");
                exit();
            }

            // 4. GATEKEEPER LOGIC (Crucial Security Step)
            // Even if the password is correct, we must ensure the user belongs to an 'Active' event.
            // Note: 'Event Manager' is exempt because they create the events.
            if ($role !== 'Event Manager') {
                $u_id = $row['id'];
                $has_active_event = false;

                // A. For Contestants: Check if their assigned event is Active.
                if ($role === 'Contestant') {
                    $c_check = $conn->prepare("
                        SELECT e.status 
                        FROM contestant_details cd 
                        JOIN events e ON cd.event_id = e.id 
                        WHERE cd.user_id = ? LIMIT 1
                    ");
                    $c_check->bind_param("i", $u_id);
                    $c_check->execute();
                    $res = $c_check->get_result();
                    
                    if ($res->num_rows > 0) {
                        $evt = $res->fetch_assoc();
                        if ($evt['status'] === 'Active') $has_active_event = true;
                    }
                } 
                // B. For Judges: Check if they are assigned to an Active event AND are Active themselves.
                elseif ($role === 'Judge') {
                    $j_check = $conn->prepare("
                        SELECT ej.id FROM event_judges ej 
                        JOIN events e ON ej.event_id = e.id 
                        WHERE ej.judge_id = ? AND e.status = 'Active' AND ej.status = 'Active' LIMIT 1
                    ");
                    $j_check->bind_param("i", $u_id);
                    $j_check->execute();
                    if ($j_check->get_result()->num_rows > 0) $has_active_event = true;
                }
                // C. For Staff (Coordinators, Tabulators): Similar check.
                elseif (in_array($role, ['Judge Coordinator', 'Contestant Manager', 'Tabulator'])) {
                    $o_check = $conn->prepare("
                        SELECT eo.id FROM event_organizers eo 
                        JOIN events e ON eo.event_id = e.id 
                        WHERE eo.user_id = ? AND e.status = 'Active' AND eo.status = 'Active' LIMIT 1
                    ");
                    $o_check->bind_param("i", $u_id);
                    $o_check->execute();
                    if ($o_check->get_result()->num_rows > 0) $has_active_event = true;
                }

                // BLOCK ACCESS if no active event is found.
                if (!$has_active_event) {
                    header("Location: ../public/index.php?error=Access Denied: The event you are assigned to is not currently active.");
                    exit();
                }
            }

            // 5. LOGIN SUCCESS: Set Session Variables
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['email']   = $row['email'];
            $_SESSION['role']    = $row['role'];
            $_SESSION['name']    = $row['name'];

            // Logic: Check if Event Manager needs to see the "Create Event" modal.
            if ($role === 'Event Manager') {
                $u_id = $row['id'];
                $check_event = $conn->query("SELECT id FROM events WHERE user_id = '$u_id'");
                $_SESSION['show_modal'] = ($check_event->num_rows == 0);
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