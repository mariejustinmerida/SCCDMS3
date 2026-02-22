<?php
// Connect to database
$conn = new mysqli("localhost", "root", "", "scc_dms");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Update Document Status</h1>";

// Get the document IDs from the URL or use defaults
$document_ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [85, 86, 87, 88, 89];
$doc_ids_str = implode(',', $document_ids);

// First, let's check for any triggers on the documents table
echo "<h2>Checking for triggers</h2>";
$trigger_sql = "SHOW TRIGGERS LIKE 'documents'";
$trigger_result = $conn->query($trigger_sql);

if ($trigger_result && $trigger_result->num_rows > 0) {
    echo "<p>Found triggers on the documents table:</p><ul>";
    while ($row = $trigger_result->fetch_assoc()) {
        echo "<li>" . print_r($row, true) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No triggers found on the documents table.</p>";
}

// Try a different approach - use prepared statements
echo "<h2>Updating documents using prepared statements</h2>";

try {
    // Prepare the statement
    $stmt = $conn->prepare("UPDATE documents SET status = ? WHERE document_id = ?");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Bind parameters
    $status = "revision_requested";
    $doc_id = 0;
    $stmt->bind_param("si", $status, $doc_id);
    
    // Execute for each document
    $success_count = 0;
    foreach ($document_ids as $id) {
        $doc_id = $id;
        if ($stmt->execute()) {
            echo "<p>✅ Updated document #$id status</p>";
            $success_count++;
        } else {
            echo "<p>❌ Error updating document #$id: " . $stmt->error . "</p>";
        }
    }
    
    echo "<p>Successfully updated $success_count out of " . count($document_ids) . " documents.</p>";
    
    // Close the statement
    $stmt->close();
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Now check if the documents were updated
echo "<h2>Checking document statuses</h2>";
$check_sql = "SELECT document_id, title, status FROM documents WHERE document_id IN ($doc_ids_str)";
$check_result = $conn->query($check_sql);

echo "<table border='1' cellpadding='5'>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Status</th>
        </tr>";

while ($row = $check_result->fetch_assoc()) {
    $status_color = ($row['status'] === 'revision_requested') ? 'green' : 'red';
    echo "<tr>
            <td>{$row['document_id']}</td>
            <td>{$row['title']}</td>
            <td style='color:$status_color'>{$row['status']}</td>
          </tr>";
}

echo "</table>";

// Check workflow entries
echo "<h2>Checking workflow entries</h2>";
$workflow_sql = "SELECT * FROM document_workflow WHERE document_id IN ($doc_ids_str) AND status = 'revision_requested'";
$workflow_result = $conn->query($workflow_sql);

if ($workflow_result && $workflow_result->num_rows > 0) {
    echo "<p>Found " . $workflow_result->num_rows . " workflow entries with 'revision_requested' status.</p>";
    
    echo "<table border='1' cellpadding='5'>
            <tr>
                <th>Workflow ID</th>
                <th>Document ID</th>
                <th>Office ID</th>
                <th>Status</th>
                <th>Comments</th>
            </tr>";
    
    while ($row = $workflow_result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['workflow_id']}</td>
                <td>{$row['document_id']}</td>
                <td>{$row['office_id']}</td>
                <td>{$row['status']}</td>
                <td>{$row['comments']}</td>
              </tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No workflow entries found with 'revision_requested' status.</p>";
}

// Show links
echo "<p><a href='pages/dashboard.php?page=documents_needing_revision'>Go to Documents Needing Revision</a></p>";
echo "<p><a href='pages/dashboard.php'>Return to Dashboard</a></p>";

// Close connection
$conn->close();
?>
