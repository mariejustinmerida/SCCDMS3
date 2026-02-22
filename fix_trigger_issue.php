<?php
// Connect to database
$conn = new mysqli("localhost", "root", "", "scc_dms");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Trigger Issue</title>
    <link href='https://cdn.tailwindcss.com' rel='stylesheet'>
</head>
<body class='bg-gray-100 p-6'>
    <div class='max-w-4xl mx-auto'>
        <div class='bg-white rounded-lg shadow-md p-6 mb-6'>
            <h1 class='text-xl font-bold mb-4'>Fix Trigger Issue</h1>";

// Document IDs to fix
$document_ids = [85, 86, 87, 88, 89];
$doc_ids_str = implode(',', $document_ids);

// Step 1: Drop the problematic trigger
echo "<h2 class='text-lg font-semibold mb-2'>Step 1: Disable Trigger</h2>";

try {
    $drop_trigger = "DROP TRIGGER IF EXISTS after_document_update";
    if ($conn->query($drop_trigger)) {
        echo "<p class='text-green-600 mb-2'>✅ Successfully disabled the after_document_update trigger</p>";
    } else {
        echo "<p class='text-red-600 mb-2'>❌ Failed to disable trigger: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p class='text-red-600 mb-2'>❌ Error: " . $e->getMessage() . "</p>";
}

// Step 2: Update the documents
echo "<h2 class='text-lg font-semibold mt-4 mb-2'>Step 2: Update Documents</h2>";

foreach ($document_ids as $doc_id) {
    $update_sql = "UPDATE documents SET status = 'revision_requested', updated_at = NOW() WHERE document_id = $doc_id";
    if ($conn->query($update_sql)) {
        echo "<p class='text-green-600 mb-2'>✅ Updated document #$doc_id status</p>";
    } else {
        echo "<p class='text-red-600 mb-2'>❌ Error updating document #$doc_id: " . $conn->error . "</p>";
    }
}

// Step 3: Create workflow entries if they don't exist
echo "<h2 class='text-lg font-semibold mt-4 mb-2'>Step 3: Create Workflow Entries</h2>";

foreach ($document_ids as $doc_id) {
    // Check if a workflow entry already exists
    $check_sql = "SELECT workflow_id FROM document_workflow WHERE document_id = $doc_id AND status = 'revision_requested'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result && $check_result->num_rows > 0) {
        echo "<p class='text-green-600 mb-2'>✅ Workflow entry already exists for document #$doc_id</p>";
    } else {
        // Get the document creator's office
        $creator_sql = "SELECT u.office_id FROM documents d JOIN users u ON d.creator_id = u.user_id WHERE d.document_id = $doc_id";
        $creator_result = $conn->query($creator_sql);
        
        if ($creator_result && $creator_result->num_rows > 0) {
            $creator_data = $creator_result->fetch_assoc();
            $office_id = $creator_data['office_id'];
            
            // Create workflow entry
            $insert_sql = "INSERT INTO document_workflow (document_id, office_id, step_order, status, comments, created_at) 
                          VALUES ($doc_id, $office_id, 999, 'revision_requested', 'Document needs revision', NOW())";
            
            if ($conn->query($insert_sql)) {
                echo "<p class='text-green-600 mb-2'>✅ Created workflow entry for document #$doc_id</p>";
            } else {
                echo "<p class='text-red-600 mb-2'>❌ Error creating workflow entry: " . $conn->error . "</p>";
            }
        } else {
            echo "<p class='text-red-600 mb-2'>❌ Could not find creator office for document #$doc_id</p>";
        }
    }
}

// Step 4: Verify the changes
echo "<h2 class='text-lg font-semibold mt-4 mb-2'>Step 4: Verify Changes</h2>";

// Check document status
$check_docs_sql = "SELECT document_id, title, status FROM documents WHERE document_id IN ($doc_ids_str)";
$check_docs_result = $conn->query($check_docs_sql);

echo "<h3 class='font-medium mb-2'>Document Status:</h3>";
echo "<div class='overflow-x-auto mb-4'>
        <table class='min-w-full bg-white border border-gray-200'>
            <thead>
                <tr>
                    <th class='px-4 py-2 border'>ID</th>
                    <th class='px-4 py-2 border'>Title</th>
                    <th class='px-4 py-2 border'>Status</th>
                </tr>
            </thead>
            <tbody>";

while ($row = $check_docs_result->fetch_assoc()) {
    $status_class = ($row['status'] === 'revision_requested') ? 'text-green-600' : 'text-red-600';
    echo "<tr>
            <td class='px-4 py-2 border'>{$row['document_id']}</td>
            <td class='px-4 py-2 border'>{$row['title']}</td>
            <td class='px-4 py-2 border $status_class'>{$row['status']}</td>
          </tr>";
}
echo "</tbody></table></div>";

// Check workflow entries
$check_workflow_sql = "SELECT dw.workflow_id, dw.document_id, d.title, dw.office_id, dw.status, dw.comments
                      FROM document_workflow dw
                      JOIN documents d ON dw.document_id = d.document_id
                      WHERE dw.document_id IN ($doc_ids_str) AND dw.status = 'revision_requested'";
$check_workflow_result = $conn->query($check_workflow_sql);

echo "<h3 class='font-medium mb-2'>Workflow Entries:</h3>";
echo "<div class='overflow-x-auto'>
        <table class='min-w-full bg-white border border-gray-200'>
            <thead>
                <tr>
                    <th class='px-4 py-2 border'>Workflow ID</th>
                    <th class='px-4 py-2 border'>Document ID</th>
                    <th class='px-4 py-2 border'>Document Title</th>
                    <th class='px-4 py-2 border'>Office ID</th>
                    <th class='px-4 py-2 border'>Status</th>
                    <th class='px-4 py-2 border'>Comments</th>
                </tr>
            </thead>
            <tbody>";

if ($check_workflow_result && $check_workflow_result->num_rows > 0) {
    while ($row = $check_workflow_result->fetch_assoc()) {
        echo "<tr>
                <td class='px-4 py-2 border'>{$row['workflow_id']}</td>
                <td class='px-4 py-2 border'>{$row['document_id']}</td>
                <td class='px-4 py-2 border'>{$row['title']}</td>
                <td class='px-4 py-2 border'>{$row['office_id']}</td>
                <td class='px-4 py-2 border'>{$row['status']}</td>
                <td class='px-4 py-2 border'>{$row['comments']}</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='6' class='px-4 py-2 border text-center text-red-600'>No workflow entries found</td></tr>";
}
echo "</tbody></table></div>";

echo "<div class='mt-6 flex space-x-4'>
        <a href='pages/dashboard.php?page=documents_needing_revision' class='inline-block px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700'>
            Go to Documents Needing Revision
        </a>
        <a href='pages/dashboard.php' class='inline-block px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700'>
            Return to Dashboard
        </a>
      </div>";

echo "</div></div></body></html>";

// Close connection
$conn->close();
?>
