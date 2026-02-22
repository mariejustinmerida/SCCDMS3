<?php
/**
 * Add QR Signature Column to Documents Table
 * 
 * This script adds the has_qr_signature column to the documents table
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
    
    // Check if has_qr_signature column exists in documents table
    $check_qr_signature = "SHOW COLUMNS FROM documents LIKE 'has_qr_signature'";
    if ($conn->query($check_qr_signature)->num_rows === 0) {
        // Add has_qr_signature column
        $add_qr_signature = "ALTER TABLE documents ADD COLUMN has_qr_signature TINYINT(1) DEFAULT 0";
        if ($conn->query($add_qr_signature)) {
            $changes[] = "Added has_qr_signature column to documents table";
        } else {
            throw new Exception('Failed to add has_qr_signature column: ' . $conn->error);
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
