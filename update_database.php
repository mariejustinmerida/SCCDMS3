<?php
// Database update script - adds verification_code column to document_workflow table
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';

echo "<h1>Database Update Script</h1>";

if (!$conn) {
    echo "<p style='color:red'>Database connection failed!</p>";
    exit;
}

// Check if the verification_code column already exists in document_workflow
$check_column = "SHOW COLUMNS FROM document_workflow LIKE 'verification_code'";
$result = $conn->query($check_column);

if (!$result || $result->num_rows == 0) {
    // Add the column
    $alter_sql = "ALTER TABLE document_workflow ADD COLUMN verification_code VARCHAR(10) DEFAULT NULL AFTER step_order";
    if ($conn->query($alter_sql)) {
        echo "<p style='color:green'>Success: Added 'verification_code' column to document_workflow table.</p>";
    } else {
        echo "<p style='color:red'>Error adding column: " . $conn->error . "</p>";
    }
} else {
    echo "<p>The 'verification_code' column already exists in document_workflow table.</p>";
}

// Update existing document verification codes
echo "<h2>Updating document workflow verification codes</h2>";
$update_query = "UPDATE document_workflow dw 
                JOIN documents d ON dw.document_id = d.document_id
                SET dw.verification_code = d.verification_code
                WHERE dw.status = 'COMPLETED' 
                AND d.verification_code IS NOT NULL 
                AND dw.verification_code IS NULL";

if ($conn->query($update_query)) {
    $rows_affected = $conn->affected_rows;
    echo "<p>Updated verification codes for $rows_affected workflow steps.</p>";
} else {
    echo "<p style='color:red'>Error updating workflow verification codes: " . $conn->error . "</p>";
}

// Check if simple_verifications table exists
$check_table = "SHOW TABLES LIKE 'simple_verifications'";
$table_result = $conn->query($check_table);

if (!$table_result || $table_result->num_rows == 0) {
    // Create the simple_verifications table
    $create_table = "CREATE TABLE IF NOT EXISTS simple_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        user_id INT NOT NULL,
        office_id INT NOT NULL,
        verification_code VARCHAR(10) NOT NULL,
        created_at DATETIME NOT NULL,
        FOREIGN KEY (document_id) REFERENCES documents(document_id)
    )";
    
    if ($conn->query($create_table)) {
        echo "<p style='color:green'>Success: Created 'simple_verifications' table.</p>";
    } else {
        echo "<p style='color:red'>Error creating simple_verifications table: " . $conn->error . "</p>";
    }
} else {
    echo "<p>The 'simple_verifications' table already exists.</p>";
}

echo "<p>Database update complete.</p>";
echo "<p><a href='dashboard.php?page=user_logs'>Return to User Logs</a></p>";
?> 