<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/notification_helper.php';

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

$user_id = $_SESSION['user_id'];

// Check if notifications table exists
$table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($table_check->num_rows == 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Notifications table does not exist'
    ]);
    exit;
}

// Check if a specific notification ID was provided
if (isset($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    
    // Mark specific notification as read
    $query = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $notification_id, $user_id);
    $success = $stmt->execute();
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Notification marked as read' : 'Failed to mark notification as read'
    ]);
} else {
    // Mark all notifications as read
    $success = mark_all_notifications_read($user_id);
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'All notifications marked as read' : 'Failed to mark notifications as read'
    ]);
} 