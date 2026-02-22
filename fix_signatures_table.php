<?php
/**
 * Fix Signatures Table
 * 
 * This script adds missing columns to the signatures table
 */

// Include database connection
require_once 'includes/config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Check if signatures table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'signatures'");
    if ($table_check && $table_check->num_rows === 0) {
        // Create the signatures table if it doesn't exist
        $create_table_sql = "CREATE TABLE signatures (
            id VARCHAR(50) PRIMARY KEY,
            document_id INT NOT NULL,
            user_id INT NOT NULL,
            office_id VARCHAR(50) NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            verification_hash VARCHAR(255) NOT NULL,
            FOREIGN KEY (document_id) REFERENCES documents(document_id)
        )";
        
        if ($conn->query($create_table_sql)) {
            echo json_encode(["success" => true, "message" => "Signatures table created successfully"]);
            exit;
        } else {
            throw new Exception("Failed to create signatures table: " . $conn->error);
        }
    }
    
    // Check existing columns
    $columns = $conn->query("SHOW COLUMNS FROM signatures");
    $column_names = [];
    while ($column = $columns->fetch_assoc()) {
        $column_names[] = $column['Field'];
    }
    
    $changes = [];
    
    // Add missing columns
    if (!in_array('user_id', $column_names)) {
        $conn->query("ALTER TABLE signatures ADD COLUMN user_id INT NOT NULL AFTER document_id");
        $changes[] = "Added user_id column";
    }
    
    if (!in_array('office_id', $column_names)) {
        $conn->query("ALTER TABLE signatures ADD COLUMN office_id VARCHAR(50) NOT NULL AFTER user_id");
        $changes[] = "Added office_id column";
    }
    
    if (!in_array('expires_at', $column_names)) {
        $conn->query("ALTER TABLE signatures ADD COLUMN expires_at DATETIME NOT NULL AFTER created_at");
        $changes[] = "Added expires_at column";
    }
    
    if (!in_array('verification_hash', $column_names)) {
        $conn->query("ALTER TABLE signatures ADD COLUMN verification_hash VARCHAR(255) NOT NULL AFTER expires_at");
        $changes[] = "Added verification_hash column";
    }
    
    echo json_encode(["success" => true, "changes" => $changes]);
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
