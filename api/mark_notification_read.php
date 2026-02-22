<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['notification_id'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['user_id'];
$notification_id = $_POST['notification_id'];

$query = "UPDATE notifications SET is_read = 1 
          WHERE notification_id = ? AND user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $notification_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to update notification']);
}
?> 