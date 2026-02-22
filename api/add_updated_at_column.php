<?php
/**
 * Add updated_at Column
 * 
 * This script adds the updated_at column to the documents table if it doesn't exist.
 */

// Include database configuration
require_once __DIR__ . '/../includes/config.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Check if the updated_at column exists
    $check_column_sql = "SHOW COLUMNS FROM documents LIKE 'updated_at'";
    $check_column_result = $conn->query($check_column_sql);

    if ($check_column_result->num_rows === 0) {
        // Add the updated_at column to the documents table
        $add_column_sql = "ALTER TABLE documents ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        if ($conn->query($add_column_sql)) {
            echo json_encode([
                'success' => true,
                'message' => 'Added updated_at column to documents table successfully'
            ]);
        } else {
            throw new Exception('Failed to add updated_at column: ' . $conn->error);
        }
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'updated_at column already exists in documents table'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
