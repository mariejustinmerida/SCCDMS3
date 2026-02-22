<?php
/**
 * Test Submit API - Minimal test to check JSON output
 */

// Completely isolate output
ob_start();

// Suppress all error output
error_reporting(0);
ini_set('display_errors', 0);

// Set error handler to prevent any output
set_error_handler(function($severity, $message, $file, $line) {
    return true; // Don't execute PHP internal error handler
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple test response
$response = [
    'success' => true,
    'message' => 'Test API working correctly',
    'timestamp' => date('Y-m-d H:i:s'),
    'session_user' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not_logged_in'
];

// Clean all output and send JSON response
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>
