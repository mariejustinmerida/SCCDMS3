<?php
require_once 'includes/config.php';

// Start session to access session variables
session_start();

// Display session information
echo "<h2>Session Information</h2>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p>Office ID: " . ($_SESSION['office_id'] ?? 'Not set') . "</p>";

// Check document_workflow table
echo "<h2>Document Workflow Table Check</h2>";

// Count total documents in workflow
$count_sql = "SELECT COUNT(*) as total FROM document_workflow";
$count_result = $conn->query($count_sql);

if ($count_result) {
    $total = $count_result->fetch_assoc()['total'];
    echo "<p>Total documents in workflow: $total</p>";
} else {
    echo "<p>Error querying document_workflow table: " . $conn->error . "</p>";
}

// Check if there are any pending documents for any office
$pending_sql = "SELECT COUNT(*) as total FROM documents d 
               JOIN document_workflow dw ON d.document_id = dw.document_id 
               WHERE d.status = 'pending'";
$pending_result = $conn->query($pending_sql);

if ($pending_result) {
    $pending_total = $pending_result->fetch_assoc()['total'];
    echo "<p>Total pending documents in workflow: $pending_total</p>";
} else {
    echo "<p>Error querying pending documents: " . $conn->error . "</p>";
}

// Check document_workflow structure
echo "<h2>Document Workflow Table Structure</h2>";
$structure_sql = "DESCRIBE document_workflow";
$structure_result = $conn->query($structure_sql);

if ($structure_result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $structure_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>Error querying table structure: " . $conn->error . "</p>";
}

// Check if there are any documents for the current office
if (isset($_SESSION['office_id']) && !empty($_SESSION['office_id'])) {
    $office_id = $_SESSION['office_id'];
    echo "<h2>Documents for Office ID: $office_id</h2>";
    
    $office_sql = "SELECT d.document_id, d.title, d.status, dw.is_current 
                  FROM documents d 
                  JOIN document_workflow dw ON d.document_id = dw.document_id 
                  WHERE dw.office_id = $office_id 
                  ORDER BY d.created_at DESC 
                  LIMIT 10";
    $office_result = $conn->query($office_sql);
    
    if ($office_result) {
        if ($office_result->num_rows > 0) {
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Is Current</th></tr>";
            
            while ($row = $office_result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['document_id']}</td>";
                echo "<td>{$row['title']}</td>";
                echo "<td>{$row['status']}</td>";
                echo "<td>{$row['is_current']}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p>No documents found for this office.</p>";
        }
    } else {
        echo "<p>Error querying office documents: " . $conn->error . "</p>";
    }
}

// Check if the is_current column exists in document_workflow
$check_column_sql = "SHOW COLUMNS FROM document_workflow LIKE 'is_current'";
$check_column_result = $conn->query($check_column_sql);

echo "<h2>Check for is_current Column</h2>";
if ($check_column_result && $check_column_result->num_rows > 0) {
    echo "<p>The is_current column exists in the document_workflow table.</p>";
} else {
    echo "<p>The is_current column does NOT exist in the document_workflow table.</p>";
    
    // If the column doesn't exist, this could be the issue
    echo "<p>This is likely why the query is failing - we're trying to filter on a column that doesn't exist.</p>";
}
