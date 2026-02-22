<?php
// Turn off all error reporting and display
error_reporting(0);
ini_set('display_errors', 0);

// Always set JSON content type header first thing
header('Content-Type: application/json');

// Function to output JSON and exit
function output_json($data) {
    echo json_encode($data);
    exit;
}

try {
    // Include required files
    require_once '../includes/config.php';
    require_once '../includes/auth_check.php';
    
    // Get user ID from session or from request for testing
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // For testing purposes, allow a test_user_id parameter
    if (!$user_id && isset($_REQUEST['test_user_id'])) {
        $user_id = (int)$_REQUEST['test_user_id'];
    }
    
    if (!$user_id) {
        output_json([
            'success' => false,
            'error' => 'User not authenticated'
        ]);
    }
    
    // Get today's date
    $today = date('Y-m-d');
    
    // Check if reminders table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'reminders'");
    if (!$table_check || $table_check->num_rows === 0) {
        output_json([
            'success' => true,
            'reminders' => [],
            'message' => 'Reminders table not found'
        ]);
    }
    
    // Get reminders for today - use DATE() function to handle datetime fields
    // Also extract TIME() to get the time component if it's stored as DATETIME
    // Show reminders that haven't passed yet (reminder_date >= NOW()) or all reminders for today
    $sql = "SELECT reminder_id, title, description, reminder_date, 
                   TIME(reminder_date) as reminder_time, is_completed,
                   CASE 
                     WHEN reminder_date >= NOW() THEN 'upcoming'
                     WHEN DATE(reminder_date) = DATE(NOW()) AND TIME(reminder_date) < TIME(NOW()) THEN 'past'
                     ELSE 'upcoming'
                   END as reminder_status
           FROM reminders 
           WHERE user_id = ? 
           AND DATE(reminder_date) = ? 
           AND is_completed = 0
           ORDER BY reminder_date ASC";
           
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        output_json([
            'success' => false,
            'error' => 'Database prepare error: ' . $conn->error
        ]);
    }
    
    $stmt->bind_param("is", $user_id, $today);
    
    // Execute the query
    if (!$stmt->execute()) {
        output_json([
            'success' => false,
            'error' => 'Database execute error: ' . $stmt->error
        ]);
    }
    
    // Get the results
    $result = $stmt->get_result();
    $reminders = [];
    
    while ($row = $result->fetch_assoc()) {
        $reminders[] = $row;
    }
    
    // Output the results
    output_json([
        'success' => true,
        'reminders' => $reminders
    ]);
    
} catch (Exception $e) {
    // Handle any unexpected exceptions
    output_json([
        'success' => false,
        'error' => 'Unexpected error: ' . $e->getMessage()
    ]);
}

// This should never be reached, but just in case
output_json([
    'success' => false,
    'error' => 'Unknown error occurred'
]);
