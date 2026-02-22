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
    <title>ENUM Fix</title>
    <link href='https://cdn.tailwindcss.com' rel='stylesheet'>
</head>
<body class='bg-gray-100 p-6'>
    <div class='max-w-4xl mx-auto'>
        <div class='bg-white rounded-lg shadow-md p-6 mb-6'>
            <h1 class='text-xl font-bold mb-4'>ENUM Fix for Document Revisions</h1>";

// Document IDs to fix
$document_ids = [85, 86, 87, 88, 89];
$doc_ids_str = implode(',', $document_ids);

// Step 1: Check ENUM values
echo "<h2 class='text-lg font-semibold mb-2'>Step 1: Check ENUM Values</h2>";

// Check documents table status ENUM values
$check_docs_enum = "SHOW COLUMNS FROM documents LIKE 'status'";
$docs_enum_result = $conn->query($check_docs_enum);
$docs_enum_row = $docs_enum_result->fetch_assoc();
$docs_enum_values = $docs_enum_row['Type'];

echo "<p class='mb-2'>Documents status ENUM values: <strong>$docs_enum_values</strong></p>";

// Check document_workflow table status ENUM values
$check_workflow_enum = "SHOW COLUMNS FROM document_workflow LIKE 'status'";
$workflow_enum_result = $conn->query($check_workflow_enum);
$workflow_enum_row = $workflow_enum_result->fetch_assoc();
$workflow_enum_values = $workflow_enum_row['Type'];

echo "<p class='mb-4'>Document_workflow status ENUM values: <strong>$workflow_enum_values</strong></p>";

// Step 2: Update document status with correct ENUM value
echo "<h2 class='text-lg font-semibold mt-4 mb-2'>Step 2: Update Document Status</h2>";

// Use 'revision' for documents table as it's in the ENUM
foreach ($document_ids as $doc_id) {
    $update_sql = "UPDATE documents SET status = 'revision' WHERE document_id = $doc_id";
    if ($conn->query($update_sql)) {
        echo "<p class='text-green-600 mb-2'>✅ Updated document #$doc_id status to 'revision'</p>";
    } else {
        echo "<p class='text-red-600 mb-2'>❌ Error updating document #$doc_id: " . $conn->error . "</p>";
    }
}

// Step 3: Create workflow entries with correct ENUM value
echo "<h2 class='text-lg font-semibold mt-4 mb-2'>Step 3: Create Workflow Entries</h2>";

// Delete any existing workflow entries for these documents
$delete_workflow = "DELETE FROM document_workflow WHERE document_id IN ($doc_ids_str)";
if ($conn->query($delete_workflow)) {
    echo "<p class='text-green-600 mb-2'>✅ Cleared existing workflow entries</p>";
} else {
    echo "<p class='text-red-600 mb-2'>❌ Error clearing workflow entries: " . $conn->error . "</p>";
}

// Use 'CURRENT' for workflow table as it's in the ENUM
foreach ($document_ids as $doc_id) {
    // Get document creator's office
    $creator_sql = "SELECT u.office_id FROM documents d JOIN users u ON d.creator_id = u.user_id WHERE d.document_id = $doc_id";
    $creator_result = $conn->query($creator_sql);
    
    if ($creator_result && $creator_result->num_rows > 0) {
        $creator_data = $creator_result->fetch_assoc();
        $office_id = $creator_data['office_id'];
        
        // Create workflow entry with correct ENUM value
        $insert_sql = "INSERT INTO document_workflow 
                      (document_id, office_id, step_order, status, comments, created_at) 
                      VALUES ($doc_id, $office_id, 1, 'CURRENT', 'Document needs revision', NOW())";
        
        if ($conn->query($insert_sql)) {
            echo "<p class='text-green-600 mb-2'>✅ Created workflow entry for document #$doc_id with status 'CURRENT'</p>";
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
    $status_class = ($row['status'] === 'revision') ? 'text-green-600' : 'text-red-600';
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

// Modify the query to check for 'revision' status and workflow entries
$updated_content = preg_replace(
    "/\\\$sql = \"SELECT d\.document_id.*?LIMIT \\\$offset, \\\$items_per_page\";/s",
    "\$sql = \"SELECT d.document_id, d.title, dt.type_name, d.created_at, d.status, d.google_doc_id,
        (SELECT o.office_name FROM document_workflow dw JOIN offices o ON dw.office_id = o.office_id 
         WHERE dw.document_id = d.document_id ORDER BY dw.created_at DESC LIMIT 1) as requesting_office,
        (SELECT dw.comments FROM document_workflow dw 
         WHERE dw.document_id = d.document_id ORDER BY dw.created_at DESC LIMIT 1) as revision_comments,
        (SELECT COUNT(*) FROM document_workflow dw 
         WHERE dw.document_id = d.document_id AND dw.comments IS NOT NULL AND dw.comments != '') as has_comments
        FROM documents d
        JOIN document_types dt ON d.type_id = dt.type_id 
        WHERE d.status = 'revision' AND d.creator_id = ?\$search_condition
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
