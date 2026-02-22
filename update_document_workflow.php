<?php
// Script to update the document_workflow table structure by adding completed_at and comments columns

// Include database connection
require_once 'includes/config.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Output as HTML
header('Content-Type: text/html');
echo '<!DOCTYPE html>
<html>
<head>
    <title>Database Update</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; line-height: 1.6; }
        h1 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto; }
    </style>
</head>
<body>
    <h1>Document Workflow Table Update</h1>';

try {
    // Check if completed_at column exists
    $completed_at_exists = false;
    $columns_result = $conn->query("SHOW COLUMNS FROM document_workflow LIKE 'completed_at'");
    if ($columns_result && $columns_result->num_rows > 0) {
        $completed_at_exists = true;
        echo "<p class='info'>The 'completed_at' column already exists in the document_workflow table.</p>";
    } else {
        echo "<p class='info'>The 'completed_at' column does not exist and will be added.</p>";
    }
    
    // Check if comments column exists
    $comments_exists = false;
    $columns_result = $conn->query("SHOW COLUMNS FROM document_workflow LIKE 'comments'");
    if ($columns_result && $columns_result->num_rows > 0) {
        $comments_exists = true;
        echo "<p class='info'>The 'comments' column already exists in the document_workflow table.</p>";
    } else {
        echo "<p class='info'>The 'comments' column does not exist and will be added.</p>";
    }
    
    // Check if status column is ENUM or VARCHAR
    $status_is_enum = false;
    $status_result = $conn->query("SHOW COLUMNS FROM document_workflow LIKE 'status'");
    if ($status_result && $status_result->num_rows > 0) {
        $status_row = $status_result->fetch_assoc();
        if (strpos($status_row['Type'], 'enum') === 0) {
            $status_is_enum = true;
            echo "<p class='info'>The 'status' column is an ENUM type.</p>";
        } else {
            echo "<p class='info'>The 'status' column is a " . $status_row['Type'] . " type.</p>";
        }
    } else {
        echo "<p class='error'>Could not determine the status column type.</p>";
    }
    
    // Add the missing columns if they don't exist
    if (!$completed_at_exists) {
        $alter_sql = "ALTER TABLE document_workflow ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL";
        if ($conn->query($alter_sql)) {
            echo "<p class='success'>Successfully added 'completed_at' column to document_workflow table.</p>";
        } else {
            throw new Exception("Failed to add 'completed_at' column: " . $conn->error);
        }
    }
    
    if (!$comments_exists) {
        $alter_sql = "ALTER TABLE document_workflow ADD COLUMN comments TEXT";
        if ($conn->query($alter_sql)) {
            echo "<p class='success'>Successfully added 'comments' column to document_workflow table.</p>";
        } else {
            throw new Exception("Failed to add 'comments' column: " . $conn->error);
        }
    }
    
    // If status is not ENUM type, check if we need to update the values to be consistent
    if (!$status_is_enum) {
        // Get count of rows with lowercase status values
        $count_sql = "SELECT COUNT(*) as count FROM document_workflow WHERE status = 'current' OR status = 'pending' OR status = 'completed'";
        $count_result = $conn->query($count_sql);
        $count_row = $count_result->fetch_assoc();
        $lowercase_count = $count_row['count'];
        
        if ($lowercase_count > 0) {
            echo "<p class='info'>Found $lowercase_count rows with lowercase status values. Converting to uppercase for consistency.</p>";
            
            // Update lowercase status values to uppercase
            $update_sql = "UPDATE document_workflow SET status = UPPER(status) WHERE status = 'current' OR status = 'pending' OR status = 'completed'";
            if ($conn->query($update_sql)) {
                echo "<p class='success'>Successfully updated status values to uppercase.</p>";
            } else {
                throw new Exception("Failed to update status values: " . $conn->error);
            }
        } else {
            echo "<p class='info'>No lowercase status values found. No update needed.</p>";
        }
    }
    
    // Show the current table structure
    echo "<h2>Current Table Structure</h2>";
    $structure_result = $conn->query("DESCRIBE document_workflow");
    if ($structure_result) {
        echo "<pre>";
        while ($row = $structure_result->fetch_assoc()) {
            echo "Field: " . $row['Field'] . "\n";
            echo "Type: " . $row['Type'] . "\n";
            echo "Null: " . $row['Null'] . "\n";
            echo "Key: " . $row['Key'] . "\n";
            echo "Default: " . $row['Default'] . "\n";
            echo "Extra: " . $row['Extra'] . "\n\n";
        }
        echo "</pre>";
    } else {
        echo "<p class='error'>Error retrieving table structure: " . $conn->error . "</p>";
    }
    
    echo "<p class='success'>Database update completed successfully.</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

echo '
</body>
</html>';
?> 