<?php
// bpms/api/tickets.php
// Purpose: Generates unique codes for audience members to vote.

// Enable Error Reporting (Turn off for production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
requireRole('Event Manager');
require_once __DIR__ . '/../app/config/database.php';

// HELPER: Generate Random Code (6 chars)
function generateCode($length = 6) {
    // UX Decision: Exclude I, 1, O, 0 to avoid confusion when reading/typing
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $code;
}

// --- 1. GENERATE TICKETS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    
    $event_id = (int)$_POST['event_id'];
    $quantity = (int)$_POST['quantity'];
    
    // Performance Guard: Cap generation to avoid PHP timeout
    if ($quantity < 1 || $quantity > 500) {
        header("Location: ../public/settings.php?error=Please generate between 1 and 500 tickets at a time.");
        exit();
    }

    $conn->begin_transaction();
    try {
        // Updated column name: 'ticket_code'
        $stmt = $conn->prepare("INSERT INTO tickets (event_id, ticket_code) VALUES (?, ?)");
        
        for ($i = 0; $i < $quantity; $i++) {
            $unique = false;
            $code = '';
            
            // Ensure Uniqueness (Retry if collision)
            while (!$unique) {
                $code = generateCode();
                
                // Collision Check
                $check = $conn->query("SELECT id FROM tickets WHERE ticket_code = '$code'");
                if ($check->num_rows === 0) {
                    $unique = true;
                }
            }
            
            $stmt->bind_param("is", $event_id, $code);
            $stmt->execute();
        }
        
        $conn->commit();
        header("Location: ../public/settings.php?success=Successfully generated $quantity ticket codes.");
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: ../public/settings.php?error=Failed to generate tickets: " . $e->getMessage());
    }
    exit();
}

// --- 2. DELETE ALL UNUSED TICKETS (Cleanup) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_unused') {
    $event_id = (int)$_POST['event_id'];
    
    // Safety: Only delete 'Unused'. Never delete 'Used' tickets to preserve voting history.
    $conn->query("DELETE FROM tickets WHERE event_id = $event_id AND status = 'Unused'");
    header("Location: ../public/settings.php?success=All unused tickets deleted.");
    exit();
}
?>