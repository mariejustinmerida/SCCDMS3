<?php
/**
 * Get Offices API
 */

// Ensure clean output
ob_start();

// Suppress all error output
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Include database configuration
    require_once __DIR__ . '/../includes/config.php';
    
    // Check user authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }
    
    // Get all offices
    $sql = "SELECT office_id, office_name FROM offices ORDER BY office_name";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Failed to fetch offices: ' . $conn->error);
    }
    
    $offices = [];
    while ($row = $result->fetch_assoc()) {
        $offices[] = [
            'office_id' => (int)$row['office_id'],
            'office_name' => $row['office_name']
        ];
    }
    
    // Clean output and send JSON response
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    echo json_encode([
        'success' => true,
        'offices' => $offices,
        'count' => count($offices)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Clean output and send error response
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>