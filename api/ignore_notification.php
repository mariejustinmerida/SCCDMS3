<?php
/**
 * Ignore Notification API
 * 
 * This API handles ignoring/removing notifications that users don't want to see
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
    // Get the JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['notification_id']) || empty($input['notification_id'])) {
        throw new Exception('Notification ID is required');
    }
    
    $notification_id = intval($input['notification_id']);
    
    // Verify the notification belongs to the current user
    $verify_query = "SELECT notification_id FROM notifications WHERE notification_id = ? AND user_id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("ii", $notification_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        throw new Exception('Notification not found or you do not have permission to ignore it');
    }
    
    // Delete the notification (ignore it)
    $delete_query = "DELETE FROM notifications WHERE notification_id = ? AND user_id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("ii", $notification_id, $user_id);
    
    if ($delete_stmt->execute()) {
        $affected_rows = $delete_stmt->affected_rows;
        
        if ($affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification ignored successfully',
                'notification_id' => $notification_id
            ]);
        } else {
            throw new Exception('No notification was deleted');
        }
    } else {
        throw new Exception('Failed to delete notification: ' . $delete_stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
