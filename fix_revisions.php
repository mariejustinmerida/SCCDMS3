<?php
require_once 'includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Fixing Document Revisions</h1>";

// Begin transaction
$conn->begin_transaction();

try {
    // Step 1: Find all documents with revision_requested status
    $docs_query = "SELECT d.document_id, d.title, d.creator_id, d.status 
                  FROM documents d 
                  WHERE d.status = 'revision_requested'";
    $docs_result = $conn->query($docs_query);
    
    if (!$docs_result) {
        throw new Exception("Error querying documents: " . $conn->error);
    }
    
    $fixed_count = 0;
    $documents = [];
    
    if ($docs_result->num_rows > 0) {
        echo "<p>Found " . $docs_result->num_rows . " documents with revision_requested status.</p>";
        
        while ($doc = $docs_result->fetch_assoc()) {
            $documents[] = $doc;
        }
        
        // Step 2: For each document, ensure there's at least one workflow entry with revision_requested status
        foreach ($documents as $doc) {
            $document_id = $doc['document_id'];
            $title = $doc['title'];
            
            // Check if there's a workflow entry with revision_requested status
            $workflow_check = "SELECT COUNT(*) as count FROM document_workflow 
                              WHERE document_id = ? AND status = 'revision_requested'";
            $check_stmt = $conn->prepare($workflow_check);
            $check_stmt->bind_param("i", $document_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $workflow_count = $check_result->fetch_assoc()['count'];
            
            if ($workflow_count == 0) {
                // No workflow entry with revision_requested status found, need to fix
                echo "<p>Fixing document ID $document_id: $title</p>";
                
                // Find the latest workflow entry for this document
                $latest_workflow = "SELECT * FROM document_workflow 
                                  WHERE document_id = ? 
                                  ORDER BY step_order DESC LIMIT 1";
                $latest_stmt = $conn->prepare($latest_workflow);
                $latest_stmt->bind_param("i", $document_id);
                $latest_stmt->execute();
                $latest_result = $latest_stmt->get_result();
                
                if ($latest_result && $latest_result->num_rows > 0) {
                    $workflow = $latest_result->fetch_assoc();
                    
                    // Update this workflow entry to revision_requested
                    $update_workflow = "UPDATE document_workflow 
                                      SET status = 'revision_requested', 
                                          comments = 'Revision requested (fixed by system)' 
                                      WHERE workflow_id = ?";
                    $update_stmt = $conn->prepare($update_workflow);
                    $update_stmt->bind_param("i", $workflow['workflow_id']);
                    $update_stmt->execute();
                    
                    $fixed_count++;
                } else {
                    echo "<p>Warning: No workflow entries found for document ID $document_id</p>";
                }
            } else {
                echo "<p>Document ID $document_id already has workflow entries with revision_requested status.</p>";
            }
        }
    } else {
        echo "<p>No documents with revision_requested status found.</p>";
    }
    
    // Commit the transaction
    $conn->commit();
    
    echo "<h2>Fix Complete</h2>";
    echo "<p>Fixed $fixed_count documents.</p>";
    
} catch (Exception $e) {
    // Rollback the transaction on error
    $conn->rollback();
    echo "<h2>Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

echo "<p><a href='pages/dashboard.php'>Return to Dashboard</a></p>";
echo "<p><a href='debug_revisions.php'>View Revision Debug Info</a></p>";
?>
