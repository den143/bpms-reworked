<?php
// app/core/guard.php
// Purpose: The Security Gatekeeper. Handles Sessions and Role-Based Access Control (RBAC).

// 1. Session Management
// Ensure "Memory" is turned on so we can read $_SESSION['user_id']
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- HELPER FUNCTIONS ---

// Detects if the current request is an API call (AJAX/Fetch) or a standard Page Load.
function isApiRequest() {
    // Logic: Check if the URL path contains '/api/' OR if the browser explicitly asked for JSON
    $in_api_folder = (strpos($_SERVER['SCRIPT_NAME'], '/api/') !== false);
    $wants_json = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    
    return ($in_api_folder || $wants_json);
}

// Calculates the correct path to the Login Page based on where this script is running.
function getLoginUrl() {
    // If we are deep inside the 'api' folder, we need to go up one level to reach 'public'
    if (strpos($_SERVER['SCRIPT_NAME'], '/api/') !== false) {
        return '../public/index.php';
    }
    // If we are already in 'public', just go to index.php
    return 'index.php';
}

// --- CORE SECURITY FUNCTIONS ---

function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        // CASE A: API Request (e.g., Save Draft) -> Return JSON
        if (isApiRequest()) {
            http_response_code(401); // Unauthorized
            echo json_encode(['status' => 'error', 'message' => 'Session Expired: Please login again.']);
            exit();
        } 
        // CASE B: Page Request (e.g., Dashboard) -> Redirect to Login
        else {
            header("Location: " . getLoginUrl());
            exit();
        }
    }
}

/**
 * @param string|array $allowed_roles
 * Example: requireRole(['Event Manager', 'Tabulator']);
 */
function requireRole($allowed_roles) {
    // 1. First, ensure they are logged in.
    requireLogin();

    // 2. Normalize input to array (allows passing a single string 'Judge')
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }

    // 3. Check Role Permission
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles, true)) {
        
        // Security Log (Optional - good for defense)
        // error_log("Security Warning: User ID " . $_SESSION['user_id'] . " attempted to access restricted area.");

        if (isApiRequest()) {
            http_response_code(403); // Forbidden
            echo json_encode(['status' => 'error', 'message' => 'Access Denied: You do not have permission.']);
            exit();
        } else {
            // Redirect with a visible error message
            $login_url = getLoginUrl();
            header("Location: $login_url?error=" . urlencode("Access Denied: Insufficient Privileges."));
            exit();
        }
    }
}
?>