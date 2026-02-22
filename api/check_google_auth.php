<?php
/**
 * Check Google Authentication API
 * 
 * This file checks if the user is authenticated with Google.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
require_once '../includes/google_auth_handler.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'User not logged in'
    ]);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    // Check if user is authenticated with Google
    $authHandler = new GoogleAuthHandler();
    $isAuthenticated = $authHandler->hasValidToken($userId);
    
    echo json_encode([
        'success' => true,
        'authenticated' => $isAuthenticated
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
