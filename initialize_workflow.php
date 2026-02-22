<?php
// Initialize workflow steps for documents
require_once 'includes/config.php';

// Set error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Document Workflow Initialization</h1>";

// Check if we're running the script
if (isset($_GET['run'])) {
    // Disable foreign key checks to avoid issues
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    
    // Get all pending documents with NULL current_step
    $query = "SELECT document_id, type_id FROM documents 
              WHERE status = 'pending' AND (current_step IS NULL OR current_step = 0)";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        echo "<p>Found " . $result->num_rows . " documents to update.</p>";
        echo "<ul>";
        
        while ($doc = $result->fetch_assoc()) {
            $document_id = $doc['document_id'];
            $type_id = $doc['type_id'];
            
            // Find the first step for this document type
            $step_query = "SELECT id FROM workflow_steps 
                          WHERE document_id = 0 
                          AND type_id = ? 
                          ORDER BY step_order ASC LIMIT 1";
            $step_stmt = $conn->prepare($step_query);
            $step_stmt->bind_param("i", $type_id);
            $step_stmt->execute();
            $step_result = $step_stmt->get_result();
            
            if ($step_result && $step_result->num_rows > 0) {
                $first_step = $step_result->fetch_assoc()['id'];
                
                // Update the document with the first step
                $update_query = "UPDATE documents SET current_step = ? WHERE document_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ii", $first_step, $document_id);
                
                if ($update_stmt->execute()) {
                    echo "<li>Updated document ID $document_id (Type: $type_id) with first step ID: $first_step</li>";
                } else {
                    echo "<li class='error'>Failed to update document ID $document_id: " . $conn->error . "</li>";
                }
            } else {
                echo "<li class='warning'>No workflow steps found for document ID $document_id (Type: $type_id)</li>";
            }
        }
        
        echo "</ul>";
    } else {
        echo "<p>No documents need updating.</p>";
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    
    echo "<p><a href='initialize_workflow.php'>Back</a></p>";
} else {
    // Show the run button
    echo "<p>This script will initialize all pending documents that have NULL current_step values with the first step in their respective workflow.</p>";
    echo "<p><a href='initialize_workflow.php?run=1' style='padding: 10px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Initialize Documents</a></p>";
}

// Add some basic styling
echo "
<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1 { color: #333; }
    ul { list-style-type: none; padding: 0; }
    li { margin: 5px 0; padding: 5px; background-color: #f0f0f0; border-radius: 3px; }
    li.error { background-color: #ffebee; color: #c62828; }
    li.warning { background-color: #fff8e1; color: #ff8f00; }
    a { color: #2196F3; text-decoration: none; }
    a:hover { text-decoration: underline; }
</style>
";
?>
