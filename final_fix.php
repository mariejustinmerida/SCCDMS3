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
    <title>Final Fix</title>
    <link href='https://cdn.tailwindcss.com' rel='stylesheet'>
</head>
<body class='bg-gray-100 p-6'>
    <div class='max-w-4xl mx-auto'>
        <div class='bg-white rounded-lg shadow-md p-6 mb-6'>
            <h1 class='text-xl font-bold mb-4'>Final Fix for Document Revisions</h1>";

// Document IDs to fix
$document_ids = [85, 86, 87, 88, 89];
$doc_ids_str = implode(',', $document_ids);

// Step 1: Check database structure
echo "<h2 class='text-lg font-semibold mb-2'>Step 1: Check Database Structure</h2>";

// Check documents table structure
$check_docs = "DESCRIBE documents";
$docs_result = $conn->query($check_docs);

echo "<p class='mb-2'>Documents table columns:</p><ul class='list-disc ml-6 mb-4'>";
$has_status_column = false;
while ($row = $docs_result->fetch_assoc()) {
    echo "<li>{$row['Field']} ({$row['Type']})</li>";
    if ($row['Field'] === 'status') {
        $has_status_column = true;
    }
}
echo "</ul>";

if (!$has_status_column) {
    echo "<p class='text-red-600 mb-2'>❌ No 'status' column found in documents table!</p>";
    echo "<p class='mb-4'>Adding status column...</p>";
    $add_status = "ALTER TABLE documents ADD COLUMN status VARCHAR(50) DEFAULT 'pending'";
    if ($conn->query($add_status)) {
        echo "<p class='text-green-600 mb-2'>✅ Added status column to documents table</p>";
    } else {
        echo "<p class='text-red-600 mb-2'>❌ Failed to add status column: " . $conn->error . "</p>";
    }
}

// Check document_workflow table structure
$check_workflow = "DESCRIBE document_workflow";
$workflow_result = $conn->query($check_workflow);

echo "<p class='mb-2'>Document_workflow table columns:</p><ul class='list-disc ml-6 mb-4'>";
$has_workflow_status = false;
while ($row = $workflow_result->fetch_assoc()) {
    echo "<li>{$row['Field']} ({$row['Type']})</li>";
    if ($row['Field'] === 'status') {
        $has_workflow_status = true;
    }
}
echo "</ul>";

if (!$has_workflow_status) {
    echo "<p class='text-red-600 mb-2'>❌ No 'status' column found in document_workflow table!</p>";
    echo "<p class='mb-4'>Adding status column...</p>";
    $add_status = "ALTER TABLE document_workflow ADD COLUMN status VARCHAR(50) DEFAULT 'pending'";
    if ($conn->query($add_status)) {
        echo "<p class='text-green-600 mb-2'>✅ Added status column to document_workflow table</p>";
    } else {
        echo "<p class='text-red-600 mb-2'>❌ Failed to add status column: " . $conn->error . "</p>";
    }
}

// Step 2: Update document status
echo "<h2 class='text-lg font-semibold mt-4 mb-2'>Step 2: Update Document Status</h2>";

// Direct SQL update with no triggers
foreach ($document_ids as $doc_id) {
    $update_sql = "UPDATE documents SET status = 'revision_requested' WHERE document_id = $doc_id";
    if ($conn->query($update_sql)) {
        echo "<p class='text-green-600 mb-2'>✅ Updated document #$doc_id status</p>";
    } else {
        echo "<p class='text-red-600 mb-2'>❌ Error updating document #$doc_id: " . $conn->error . "</p>";
    }
}

// Step 3: Create workflow entries
echo "<h2 class='text-lg font-semibold mt-4 mb-2'>Step 3: Create Workflow Entries</h2>";

// Delete any existing workflow entries for these documents
$delete_workflow = "DELETE FROM document_workflow WHERE document_id IN ($doc_ids_str)";
if ($conn->query($delete_workflow)) {
    echo "<p class='text-green-600 mb-2'>✅ Cleared existing workflow entries</p>";
} else {
    echo "<p class='text-red-600 mb-2'>❌ Error clearing workflow entries: " . $conn->error . "</p>";
}

// Create new workflow entries
foreach ($document_ids as $doc_id) {
    // Get document creator's office
    $creator_sql = "SELECT u.office_id FROM documents d JOIN users u ON d.creator_id = u.user_id WHERE d.document_id = $doc_id";
    $creator_result = $conn->query($creator_sql);
    
    if ($creator_result && $creator_result->num_rows > 0) {
        $creator_data = $creator_result->fetch_assoc();
        $office_id = $creator_data['office_id'];
        
        // Create workflow entry with all required fields
        $insert_sql = "INSERT INTO document_workflow 
                      (document_id, office_id, step_order, status, comments, created_at) 
                      VALUES ($doc_id, $office_id, 1, 'revision_requested', 'Document needs revision', NOW())";
        
        if ($conn->query($insert_sql)) {
            echo "<p class='text-green-600 mb-2'>✅ Created workflow entry for document #$doc_id</p>";
        } else {
            echo "<p class='text-red-600 mb-2'>❌ Error creating workflow entry: " . $conn->error . "</p>";
        }
    } else {
        echo "<p class='text-red-600 mb-2'>❌ Could not find creator office for document #$doc_id</p>";
    }
}

// Step 4: Verify changes
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
$check_workflow_sql = "SELECT * FROM document_workflow WHERE document_id IN ($doc_ids_str)";
$check_workflow_result = $conn->query($check_workflow_sql);

echo "<h3 class='font-medium mb-2'>Workflow Entries:</h3>";
echo "<div class='overflow-x-auto'>
        <table class='min-w-full bg-white border border-gray-200'>
            <thead>
                <tr>
                    <th class='px-4 py-2 border'>Workflow ID</th>
                    <th class='px-4 py-2 border'>Document ID</th>
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
                <td class='px-4 py-2 border'>{$row['office_id']}</td>
                <td class='px-4 py-2 border'>{$row['status']}</td>
                <td class='px-4 py-2 border'>{$row['comments']}</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='5' class='px-4 py-2 border text-center text-red-600'>No workflow entries found</td></tr>";
}
echo "</tbody></table></div>";

// Step 5: Update documents_needing_revision.php
echo "<h2 class='text-lg font-semibold mt-4 mb-2'>Step 5: Update Documents Needing Revision Page</h2>";

// Update the query in documents_needing_revision.php
$revision_page_path = 'pages/documents_needing_revision.php';
$revision_page_content = file_get_contents($revision_page_path);

// Create a backup
copy($revision_page_path, $revision_page_path . '.bak');

// Modify the query to check both document status and workflow entries
$updated_content = preg_replace(
    "/\\\$sql = \"SELECT d\.document_id.*?LIMIT \\\$offset, \\\$items_per_page\";/s",
    "\$sql = \"SELECT d.document_id, d.title, dt.type_name, d.created_at, d.status, d.google_doc_id,
        (SELECT o.office_name FROM document_workflow dw JOIN offices o ON dw.office_id = o.office_id 
         WHERE dw.document_id = d.document_id AND dw.status = 'revision_requested' LIMIT 1) as requesting_office,
        (SELECT dw.comments FROM document_workflow dw 
         WHERE dw.document_id = d.document_id AND dw.status = 'revision_requested' LIMIT 1) as revision_comments,
        (SELECT COUNT(*) FROM document_workflow dw 
         WHERE dw.document_id = d.document_id AND dw.comments IS NOT NULL AND dw.comments != '') as has_comments
        FROM documents d
        JOIN document_types dt ON d.type_id = dt.type_id 
        WHERE (d.status = 'revision_requested' OR d.document_id IN (
            SELECT document_id FROM document_workflow WHERE status = 'revision_requested'
        ))
        AND d.creator_id = ?\$search_condition
        ORDER BY d.created_at DESC
        LIMIT \$offset, \$items_per_page\";",
    $revision_page_content
);

if ($updated_content !== $revision_page_content) {
    file_put_contents($revision_page_path, $updated_content);
    echo "<p class='text-green-600 mb-2'>✅ Updated documents_needing_revision.php query</p>";
} else {
    echo "<p class='text-amber-600 mb-2'>⚠️ No changes needed to documents_needing_revision.php</p>";
}

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
