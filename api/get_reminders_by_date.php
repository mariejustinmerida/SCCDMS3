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
    
    // Check if reminders table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'reminders'");
    if (!$table_check || $table_check->num_rows === 0) {
        output_json([
            'success' => true,
            'reminders' => [],
            'message' => 'Reminders table not found'
        ]);
    }
    
    // Check if date parameter is provided
    if (!isset($_GET['date'])) {
        // If no specific date, get reminders for the current month
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');
        
        $sql = "SELECT reminder_id, title, description, reminder_date, is_completed 
               FROM reminders 
               WHERE user_id = ? 
               AND reminder_date BETWEEN ? AND ? 
               ORDER BY reminder_date ASC";
               
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            output_json([
                'success' => false,
                'error' => 'Database prepare error: ' . $conn->error
            ]);
        }
        
        $stmt->bind_param("iss", $user_id, $month_start, $month_end);
    } else {
        // Get reminders for a specific date (handles both date and datetime formats)
        $date = $_GET['date'];
        
        // If date contains time, extract just the date part
        $dateOnly = explode(' ', $date)[0];
        
        // Use DATE() function to extract date part for comparison (handles datetime fields)
        // Also extract TIME() to get the time component if it's stored as DATETIME
        $sql = "SELECT reminder_id, title, description, reminder_date, 
                       TIME(reminder_date) as reminder_time, is_completed 
               FROM reminders 
               WHERE user_id = ? 
               AND DATE(reminder_date) = ? 
               ORDER BY reminder_date ASC";
               
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            output_json([
                'success' => false,
                'error' => 'Database prepare error: ' . $conn->error
            ]);
        }
        
        $stmt->bind_param("is", $user_id, $dateOnly);
    }
    
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
