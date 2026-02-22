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

    // Get user ID from session or from POST/GET for testing
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
                    output_json([
                        'success' => false,
                        'error' => 'Database prepare error: ' . $conn->error
                    ]);
                }
                $stmt->bind_param("i", $user_id);
            }

            if (!$stmt->execute()) {
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
            $data = json_decode($raw_input, true);
            
            // Debug information if JSON parsing fails
            if (json_last_error() !== JSON_ERROR_NONE) {
                output_json([
                    'success' => false,
                    'error' => 'Invalid JSON input: ' . json_last_error_msg(),
                    'raw_input' => $raw_input
                ]);
            }

            if (!isset($data['title']) || !isset($data['reminder_date'])) {
                output_json([
                    'success' => false,
                    'error' => 'Missing required fields'
                ]);
            }

            $title = $data['title'];
            $description = isset($data['description']) ? $data['description'] : '';
            $reminder_date = $data['reminder_date'];

            $stmt = $conn->prepare("INSERT INTO reminders (user_id, title, description, reminder_date) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                output_json([
                    'success' => false,
                    'error' => 'Database prepare error: ' . $conn->error
                ]);
            }
            $stmt->bind_param("isss", $user_id, $title, $description, $reminder_date);

            if (!$stmt->execute()) {
                output_json([
                    'success' => false,
                    'error' => 'Database execute error: ' . $stmt->error
                ]);
            }

            $reminder_id = $conn->insert_id;
            output_json(['success' => true, 'reminder_id' => $reminder_id]);
            break;

        case 'PUT':
            // Update an existing reminder
            $raw_input = file_get_contents('php://input');
            $data = json_decode($raw_input, true);
            
            // Debug information if JSON parsing fails
            if (json_last_error() !== JSON_ERROR_NONE) {
                output_json([
                    'success' => false,
                    'error' => 'Invalid JSON input: ' . json_last_error_msg(),
                    'raw_input' => $raw_input
                ]);
            }
            
            if (!isset($data['reminder_id'])) {
                output_json([
                    'success' => false,
                    'error' => 'Missing reminder ID'
                ]);
            }
            
            $reminder_id = $data['reminder_id'];
            
            // Check if the reminder belongs to the user
            $check_stmt = $conn->prepare("SELECT user_id FROM reminders WHERE reminder_id = ?");
            if (!$check_stmt) {
                output_json([
                    'success' => false,
                    'error' => 'Database prepare error: ' . $conn->error
                ]);
            }
            
            $check_stmt->bind_param("i", $reminder_id);
            
            if (!$check_stmt->execute()) {
                output_json([
                    'success' => false,
                    'error' => 'Database execute error: ' . $check_stmt->error
                ]);
            }
            
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                output_json([
                    'success' => false,
                    'error' => 'Reminder not found'
                ]);
            }
            
            $reminder = $check_result->fetch_assoc();
            if ($reminder['user_id'] != $user_id) {
                output_json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ]);
            }
            
            // Build update query based on provided fields
            $updates = [];
            $params = [];
            $types = "";
            
            if (isset($data['title'])) {
                $updates[] = "title = ?";
                $params[] = $data['title'];
                $types .= "s";
            }
            
            if (isset($data['description'])) {
                $updates[] = "description = ?";
                $params[] = $data['description'];
                $types .= "s";
            }
            
            if (isset($data['reminder_date'])) {
                $updates[] = "reminder_date = ?";
                $params[] = $data['reminder_date'];
                $types .= "s";
            }
            
            if (isset($data['is_completed'])) {
                $updates[] = "is_completed = ?";
                $params[] = $data['is_completed'] ? 1 : 0;
                $types .= "i";
            }
            
            if (empty($updates)) {
                output_json([
                    'success' => false,
                    'error' => 'No fields to update'
                ]);
            }
            
            $update_query = "UPDATE reminders SET " . implode(", ", $updates) . " WHERE reminder_id = ?";
            $params[] = $reminder_id;
            $types .= "i";
            
            $update_stmt = $conn->prepare($update_query);
            if (!$update_stmt) {
                output_json([
                    'success' => false,
                    'error' => 'Database prepare error: ' . $conn->error
                ]);
            }
            
            $update_stmt->bind_param($types, ...$params);
            
            if (!$update_stmt->execute()) {
                output_json([
                    'success' => false,
                    'error' => 'Database execute error: ' . $update_stmt->error
                ]);
            }
            
            output_json(['success' => true]);
            break;
            
        case 'DELETE':
            // Delete a reminder
            $raw_input = file_get_contents('php://input');
            $data = json_decode($raw_input, true);
            
            // Debug information if JSON parsing fails
            if (json_last_error() !== JSON_ERROR_NONE) {
                output_json([
                    'success' => false,
                    'error' => 'Invalid JSON input: ' . json_last_error_msg(),
                    'raw_input' => $raw_input
                ]);
            }
            
            if (!isset($data['reminder_id'])) {
                output_json([
                    'success' => false,
                    'error' => 'Missing reminder ID'
                ]);
            }
            
            $reminder_id = $data['reminder_id'];
            
            // Check if the reminder belongs to the user
            $check_stmt = $conn->prepare("SELECT user_id FROM reminders WHERE reminder_id = ?");
            if (!$check_stmt) {
                output_json([
                    'success' => false,
                    'error' => 'Database prepare error: ' . $conn->error
                ]);
            }
            
            $check_stmt->bind_param("i", $reminder_id);
            
            if (!$check_stmt->execute()) {
                output_json([
                    'success' => false,
                    'error' => 'Database execute error: ' . $check_stmt->error
                ]);
            }
            
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                output_json([
                    'success' => false,
                    'error' => 'Reminder not found'
                ]);
            }
            
            $reminder = $check_result->fetch_assoc();
            if ($reminder['user_id'] != $user_id) {
                output_json([
                    'success' => false,
                    'error' => 'Unauthorized'
                ]);
            }
            
            $delete_stmt = $conn->prepare("DELETE FROM reminders WHERE reminder_id = ?");
            if (!$delete_stmt) {
                output_json([
                    'success' => false,
                    'error' => 'Database prepare error: ' . $conn->error
                ]);
            }
            
            $delete_stmt->bind_param("i", $reminder_id);
            
            if (!$delete_stmt->execute()) {
                output_json([
                    'success' => false,
                    'error' => 'Database execute error: ' . $delete_stmt->error
                ]);
            }
            
            output_json(['success' => true]);
            break;
            
        default:
            output_json([
                'success' => false,
                'error' => 'Invalid request method'
            ]);
    }
} catch (Exception $e) {
    output_json([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}