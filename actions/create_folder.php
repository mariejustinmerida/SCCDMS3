<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['folderName']) || empty($data['folderName'])) {
    echo json_encode(['success' => false, 'error' => 'No folder name provided']);
    exit;
}

$folderName = trim($data['folderName']);

// Sanitize folder name to prevent directory traversal and other issues
$folderName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $folderName);

// Ensure the folder name is not empty after sanitization
if (empty($folderName)) {
    echo json_encode(['success' => false, 'error' => 'Invalid folder name']);
    exit;
}

// Define the base upload directory
$baseUploadDir = realpath(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

// Create the folder path
$folderPath = $baseUploadDir . $folderName;

// Check if folder already exists
if (is_dir($folderPath)) {
    echo json_encode(['success' => false, 'error' => 'Folder already exists']);
    exit;
}

// Create the folder
if (mkdir($folderPath, 0755, true)) {
    // Log the folder creation
    $user_id = $_SESSION['user_id'];
    $log_sql = "INSERT INTO activity_logs (user_id, action, details, timestamp) VALUES (?, 'create_folder', ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    $log_details = "Created folder: " . $folderName;
    $log_stmt->bind_param("is", $user_id, $log_details);
    $log_stmt->execute();
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to create folder']);
}
?> 