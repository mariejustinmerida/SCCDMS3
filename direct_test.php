<?php
// Direct test script to diagnose reminders API issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';

echo "<h1>Direct Reminders API Test</h1>";

// Step 1: Check if the reminders table exists
echo "<h2>Step 1: Check Reminders Table</h2>";
$table_check = $conn->query("SHOW TABLES LIKE 'reminders'");
if ($table_check && $table_check->num_rows > 0) {
    echo "<p style='color:green'>✓ Reminders table exists</p>";
    
    // Show table structure
    echo "<h3>Table Structure:</h3>";
    $result = $conn->query("DESCRIBE reminders");
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
} else {
    echo "<p style='color:red'>✗ Reminders table does not exist</p>";
    echo "<p><a href='setup_reminders.php'>Run Setup Script</a></p>";
    exit;
}

// Step 2: Try a direct insert
echo "<h2>Step 2: Test Direct Insert</h2>";

$user_id = 1; // Test user ID
$title = "Test Reminder " . date('Y-m-d H:i:s');
$description = "This is a test reminder created directly";
$reminder_date = date('Y-m-d'); // Today

echo "<p>Attempting to insert: User ID: $user_id, Title: $title, Date: $reminder_date</p>";

try {
    $stmt = $conn->prepare("INSERT INTO reminders (user_id, title, description, reminder_date) VALUES (?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("isss", $user_id, $title, $description, $reminder_date);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $reminder_id = $conn->insert_id;
    echo "<p style='color:green'>✓ Reminder inserted successfully with ID: $reminder_id</p>";
    
    // Show the inserted record
    $result = $conn->query("SELECT * FROM reminders WHERE reminder_id = $reminder_id");
    echo "<h3>Inserted Record:</h3>";
    echo "<pre>";
    print_r($result->fetch_assoc());
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}

// Step 3: Test JSON handling
echo "<h2>Step 3: Test JSON Handling</h2>";

$test_data = [
    'title' => 'Test JSON Reminder',
    'description' => 'This is a test of JSON handling',
    'reminder_date' => date('Y-m-d')
];

echo "<p>Test JSON data:</p>";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

$decoded = json_decode(json_encode($test_data), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "<p style='color:red'>✗ JSON decode error: " . json_last_error_msg() . "</p>";
} else {
    echo "<p style='color:green'>✓ JSON encoding/decoding works correctly</p>";
}

// Step 4: Check auth_check.php file
echo "<h2>Step 4: Check auth_check.php</h2>";

if (file_exists('includes/auth_check.php')) {
    echo "<p style='color:green'>✓ auth_check.php file exists</p>";
    
    // Show file contents
    echo "<h3>File Contents:</h3>";
    echo "<pre>" . htmlspecialchars(file_get_contents('includes/auth_check.php')) . "</pre>";
} else {
    echo "<p style='color:red'>✗ auth_check.php file does not exist</p>";
}

// Close the connection
$conn->close();
