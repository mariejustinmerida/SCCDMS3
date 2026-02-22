<?php
/**
 * Migration script to alter reminders.reminder_date from DATE to DATETIME
 * This allows storing time information along with the date
 * 
 * Run this script once to update your database schema.
 */

require_once 'includes/config.php';

// Check if column is already DATETIME
$check_sql = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'reminders' 
              AND COLUMN_NAME = 'reminder_date'";

$result = $conn->query($check_sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $column_type = strtoupper($row['COLUMN_TYPE']);
    
    if (strpos($column_type, 'DATETIME') !== false || strpos($column_type, 'TIMESTAMP') !== false) {
        echo json_encode([
            'success' => true,
            'message' => 'Column is already DATETIME. No migration needed.',
            'current_type' => $row['COLUMN_TYPE']
        ]);
        exit;
    }
}

// Alter the column from DATE to DATETIME
$alter_sql = "ALTER TABLE reminders MODIFY COLUMN reminder_date DATETIME NOT NULL";

if ($conn->query($alter_sql)) {
    echo json_encode([
        'success' => true,
        'message' => 'Successfully migrated reminder_date from DATE to DATETIME.',
        'action' => 'Column altered successfully. Existing reminders will have time set to 00:00:00.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to alter column: ' . $conn->error,
        'sql_error' => $conn->error
    ]);
}

$conn->close();
?>

