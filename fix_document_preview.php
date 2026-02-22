<?php
// Database structure update script
require_once 'includes/config.php';

if ($conn) {
    // Check if verification_code column exists in document_workflow table
    $check_column = "SHOW COLUMNS FROM document_workflow LIKE 'verification_code'";
    $result = $conn->query($check_column);
    
    if (!$result || $result->num_rows == 0) {
        // Add the column if it doesn't exist
        $alter_table = "ALTER TABLE document_workflow ADD COLUMN verification_code VARCHAR(10) DEFAULT NULL AFTER step_order";
        if ($conn->query($alter_table)) {
            echo "Column verification_code added to document_workflow table.<br>";
        } else {
            echo "Error adding column: " . $conn->error . "<br>";
        }
    } else {
        echo "Column verification_code already exists in document_workflow table.<br>";
    }
    
    // Update the file path handling in admin_verify.php
    echo "Database structure updated.<br>";
    echo "Please refresh your page to see the changes.";
} else {
    echo "Database connection failed.";
}
?> 