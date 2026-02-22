<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connect to the database
require_once 'includes/config.php';

// Test document ID
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 49;

echo "<h2>Diagnostic Results for Document ID: {$document_id}</h2>";

// Check if document exists
echo "<h3>1. Checking if document exists:</h3>";
$sql = "SELECT * FROM documents WHERE document_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $document = $result->fetch_assoc();
    echo "<p style='color:green'>✓ Document found: {$document['title']}</p>";
    echo "<pre>" . print_r($document, true) . "</pre>";
} else {
    echo "<p style='color:red'>✗ Document not found!</p>";
}

// Check document_workflow table
echo "<h3>2. Checking document_workflow table:</h3>";
$sql = "SELECT dw.*, o.office_name 
        FROM document_workflow dw
        JOIN offices o ON dw.office_id = o.office_id
        WHERE dw.document_id = ?
        ORDER BY dw.step_order ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<p style='color:green'>✓ Workflow steps found: {$result->num_rows} steps</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Step Order</th><th>Office ID</th><th>Office Name</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['step_order']}</td>";
        echo "<td>{$row['office_id']}</td>";
        echo "<td>{$row['office_name']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color:red'>✗ No workflow steps found for this document!</p>";
}

// Check workflow_steps table
echo "<h3>3. Testing the original SQL query:</h3>";
$sql = "SELECT dw.step_order, dw.office_id, o.office_name, COALESCE(ws.step_id, 0) as step_id
        FROM document_workflow dw
        JOIN offices o ON dw.office_id = o.office_id
        LEFT JOIN workflow_steps ws ON (ws.office_id = dw.office_id AND ws.type_id = (
            SELECT type_id FROM documents WHERE document_id = ?
        ))
        WHERE dw.document_id = ?
        ORDER BY dw.step_order ASC";

echo "<p>SQL: $sql</p>";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $document_id, $document_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<p style='color:green'>✓ Query successful: {$result->num_rows} results</p>";
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Step Order</th><th>Office ID</th><th>Office Name</th><th>Step ID</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['step_order']}</td>";
        echo "<td>{$row['office_id']}</td>";
        echo "<td>{$row['office_name']}</td>";
        echo "<td>{$row['step_id']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Create the same JSON response as the API
    $steps = array();
    $result->data_seek(0); // Reset result pointer
    
    while ($row = $result->fetch_assoc()) {
        $steps[] = $row;
    }
    
    echo "<h4>JSON that would be returned by API:</h4>";
    echo "<pre>" . json_encode(['success' => true, 'steps' => $steps], JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "<p style='color:red'>✗ Query returned no results!</p>";
}

echo "<h3>4. Checking actions table:</h3>";
$sql = "SELECT a.*, u.username 
        FROM actions a 
        JOIN users u ON a.user_id = u.user_id 
        WHERE a.document_id = ?
        ORDER BY a.action_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<p style='color:green'>✓ Actions found: {$result->num_rows} actions</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Action ID</th><th>Office ID</th><th>User</th><th>Action Type</th><th>Date</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['action_id']}</td>";
        echo "<td>{$row['office_id']}</td>";
        echo "<td>{$row['username']}</td>";
        echo "<td>{$row['action_type']}</td>";
        echo "<td>{$row['action_date']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color:red'>✗ No actions found for this document!</p>";
}

echo "<h3>5. Check 'document_actions' table (if different from 'actions'):</h3>";
try {
    $sql = "SELECT da.*, u.username 
            FROM document_actions da 
            JOIN users u ON da.user_id = u.user_id 
            WHERE da.document_id = ?
            ORDER BY da.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<p style='color:green'>✓ Document actions found: {$result->num_rows} actions</p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Action ID</th><th>Step ID</th><th>User</th><th>Action Type</th><th>Date</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['action_id']}</td>";
            echo "<td>{$row['step_id']}</td>";
            echo "<td>{$row['username']}</td>";
            echo "<td>{$row['action_type']}</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color:orange'>⚠ No document_actions found for this document.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:orange'>⚠ Table 'document_actions' might not exist: {$e->getMessage()}</p>";
}

echo "<h3>Database Tables:</h3>";
$result = $conn->query("SHOW TABLES");
echo "<ul>";
while ($row = $result->fetch_row()) {
    echo "<li>{$row[0]}</li>";
}
echo "</ul>";

echo "<p><a href='diagnostic.php?id=48'>Test with document ID 48</a> | <a href='diagnostic.php?id=49'>Test with document ID 49</a> | <a href='diagnostic.php?id=50'>Test with document ID 50</a></p>";
?>
