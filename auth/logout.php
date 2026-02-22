<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/logging.php'; // Include logging functions

// Log user logout action if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Unknown user';
    $office_id = $_SESSION['office_id'] ?? null;
    
    // Log user logout action with enhanced details
    $details = "User $username logged out";
    log_user_action(
        $user_id,
        'logout',
        $details,
        null,
        null,
        $office_id
    );
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>
