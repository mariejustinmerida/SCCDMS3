<?php
/**
 * Setup Signatures Table
 * 
 * This script creates the signatures table for the QR code e-signature system
 */

// Include database configuration
require_once __DIR__ . '/../includes/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Track changes made
$changes = [];

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Check if signatures table exists
    $check_signatures_table = "SHOW TABLES LIKE 'signatures'";
    $signatures_table_exists = $conn->query($check_signatures_table)->num_rows > 0;
    
    if (!$signatures_table_exists) {
        // Create signatures table
        $create_signatures_table = "CREATE TABLE signatures (
            id VARCHAR(50) PRIMARY KEY,
            document_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            office_id INT(11) NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            is_revoked BOOLEAN DEFAULT FALSE,
            verification_hash VARCHAR(255) NOT NULL,
            KEY document_id (document_id),
            KEY user_id (user_id),
            KEY office_id (office_id)
        )";
        
        if ($conn->query($create_signatures_table)) {
            $changes[] = "Created signatures table";
        } else {
            throw new Exception('Failed to create signatures table: ' . $conn->error);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'changes' => $changes
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
