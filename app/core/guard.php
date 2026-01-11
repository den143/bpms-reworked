<?php

// Check if the website's "memory" (session) is currently turned off.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// This function checks if the user is logged in at all.
function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }
}

/**
 * @param string|array $roles
 */

// This function checks if the user has the *correct* Job Title.
function requireRole($roles) {

    // If the user have no role at all, kick them out.
    if (!isset($_SESSION['role'])) {
        header("Location: index.php");
        exit();
    }

    // If the user is not in the list of allowed roles, kick them out.
    if (is_array($roles)) {
        if (!in_array($_SESSION['role'], $roles, true)) {
            header("Location: index.php");
            exit();
        }
    } else {
        // Check if the user's role does NOT match that one required role.
        if ($_SESSION['role'] !== $roles) {
            header("Location: index.php");
            exit();
        }
    }
}
