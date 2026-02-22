<?php
/**
 * Mark All Notifications as Read API
 */

session_start();
require_once '../includes/config.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Mark all notifications as read for the user
    $update_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $user_id);
    
    if ($update_stmt->execute()) {
        $affected_rows = $update_stmt->affected_rows;
        
        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read',
            'notifications_marked' => $affected_rows
        ]);
    } else {
        throw new Exception('Failed to update notifications');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
