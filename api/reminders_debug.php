<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log errors to a file
ini_set('log_errors', 1);
ini_set('error_log', '../reminders_error.log');

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
    
    // Skip authentication check for testing
    // require_once '../includes/auth_check.php';
    
    // Set a test user ID
    $user_id = 1; // Use a valid user ID from your database
    
    // Log the request method and data
    error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
    error_log('Raw input: ' . file_get_contents('php://input'));
    
    // Check if reminders table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'reminders'");
    if (!$table_check || $table_check->num_rows === 0) {
        error_log('Reminders table not found');
        output_json([
            'success' => false,
            'error' => 'Reminders table not found. Please run the setup script.'
        ]);
    }
    
    // Handle different request methods
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // Get reminders for the user
            if (isset($_GET['date'])) {
                // Get reminders for a specific date
                $date = $_GET['date'];
                $stmt = $conn->prepare("SELECT * FROM reminders WHERE user_id = ? AND reminder_date = ? ORDER BY created_at DESC");
                if (!$stmt) {
                    error_log('Database prepare error: ' . $conn->error);
                    output_json([
                        'success' => false,
                        'error' => 'Database prepare error: ' . $conn->error
                    ]);
                }
                $stmt->bind_param("is", $user_id, $date);
            } else {
                // Get all reminders for the user
                $stmt = $conn->prepare("SELECT * FROM reminders WHERE user_id = ? ORDER BY reminder_date ASC");
                if (!$stmt) {
                    error_log('Database prepare error: ' . $conn->error);
                    output_json([
                        'success' => false,
                        'error' => 'Database prepare error: ' . $conn->error
                    ]);
                }
                $stmt->bind_param("i", $user_id);
            }
            
            if (!$stmt->execute()) {
                error_log('Database execute error: ' . $stmt->error);
                output_json([
                    'success' => false,
                    'error' => 'Database execute error: ' . $stmt->error
                ]);
            }
            
            $result = $stmt->get_result();
            $reminders = [];
            
            while ($row = $result->fetch_assoc()) {
                $reminders[] = $row;
            }
            
            output_json(['success' => true, 'reminders' => $reminders]);
            break;
            
        case 'POST':
            // Add a new reminder
            $raw_input = file_get_contents('php://input');
            error_log('POST raw input: ' . $raw_input);
            
            $data = json_decode($raw_input, true);
            
            // Debug information if JSON parsing fails
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON parse error: ' . json_last_error_msg());
                output_json([
                    'success' => false,
                    'error' => 'Invalid JSON input: ' . json_last_error_msg(),
                    'raw_input' => $raw_input
                ]);
            }
            
            error_log('Decoded data: ' . print_r($data, true));
            
            if (!isset($data['title']) || !isset($data['reminder_date'])) {
                error_log('Missing required fields');
                output_json([
                    'success' => false,
                    'error' => 'Missing required fields'
                ]);
            }
            
            $title = $data['title'];
            $description = isset($data['description']) ? $data['description'] : '';
            $reminder_date = $data['reminder_date'];
            
            error_log("Adding reminder: Title=$title, Date=$reminder_date, User=$user_id");
            
            $stmt = $conn->prepare("INSERT INTO reminders (user_id, title, description, reminder_date) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                error_log('Database prepare error: ' . $conn->error);
                output_json([
                    'success' => false,
                    'error' => 'Database prepare error: ' . $conn->error
                ]);
            }
            
            $stmt->bind_param("isss", $user_id, $title, $description, $reminder_date);
            
            if (!$stmt->execute()) {
                error_log('Database execute error: ' . $stmt->error);
                output_json([
                    'success' => false,
                    'error' => 'Database execute error: ' . $stmt->error
                ]);
            }
            
            $reminder_id = $conn->insert_id;
            error_log("Reminder added successfully with ID: $reminder_id");
            
            output_json(['success' => true, 'reminder_id' => $reminder_id]);
            break;
            
        default:
            error_log('Invalid request method: ' . $method);
            output_json([
                'success' => false,
                'error' => 'Invalid request method'
            ]);
    }
} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    output_json([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
