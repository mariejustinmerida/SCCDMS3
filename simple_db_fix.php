<?php
// Simple database fix script
// Connect to database
$conn = new mysqli("localhost", "root", "", "scc_dms");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Simple Database Fix</h1>";

// Disable foreign key checks temporarily
$conn->query("SET FOREIGN_KEY_CHECKS=0");

try {
    // Update one document at a time
    $document_ids = [85, 86, 87, 88, 89];
    
    foreach ($document_ids as $doc_id) {
        echo "<p>Processing document #$doc_id...</p>";
        
        // Update document status
        $update_sql = "UPDATE documents SET status = 'revision_requested' WHERE document_id = $doc_id";
        if ($conn->query($update_sql)) {
            echo "<p>✅ Updated document #$doc_id status</p>";
        } else {
            echo "<p>❌ Error updating document #$doc_id: " . $conn->error . "</p>";
        }
        
        // Insert workflow entry
        $insert_sql = "INSERT INTO document_workflow (document_id, office_id, status, comments, created_at) 
                      VALUES ($doc_id, 1, 'revision_requested', 'Document needs revision', NOW())";
        if ($conn->query($insert_sql)) {
            echo "<p>✅ Created workflow entry for document #$doc_id</p>";
        } else {
            echo "<p>❌ Error creating workflow entry for document #$doc_id: " . $conn->error . "</p>";
        }
        
        echo "<hr>";
    }
    
    echo "<p><strong>Done processing all documents.</strong></p>";
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
} finally {
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
}

// Show link to revision page
echo "<p><a href='pages/dashboard.php?page=documents_needing_revision'>Go to Documents Needing Revision</a></p>";
?>
