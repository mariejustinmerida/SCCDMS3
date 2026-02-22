<?php
/**
 * Get Users API
 * 
 * This file handles API requests to get a list of users for a specific office.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';

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

// Check if office_id is provided
if (!isset($_GET['office_id']) || empty($_GET['office_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Office ID is required'
    ]);
    exit();
}

$officeId = (int)$_GET['office_id'];

try {
    // Get users for the specified office
    $sql = "SELECT user_id, CONCAT(first_name, ' ', last_name) AS full_name 
            FROM users 
            WHERE office_id = ? AND status = 'active' 
            ORDER BY first_name, last_name ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $officeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception('Error fetching users: ' . $conn->error);
    }
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'user_id' => $row['user_id'],
            'full_name' => $row['full_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
