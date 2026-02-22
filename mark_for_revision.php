<?php
require_once 'includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p>Please log in first.</p>";
    echo "<p><a href='pages/dashboard.php'>Go to Dashboard</a></p>";
    exit();
}

$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Get document IDs from the URL or use defaults
$document_ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [85, 86, 87, 88, 89];

echo "<!DOCTYPE html>
<html>
<head>
    <title>Mark Documents for Revision</title>
    <link href='https://cdn.tailwindcss.com' rel='stylesheet'>
</head>
<body class='bg-gray-100 p-6'>
    <div class='max-w-4xl mx-auto'>
        <div class='bg-white rounded-lg shadow-md p-6 mb-6'>
            <h1 class='text-xl font-bold mb-4'>Mark Documents for Revision</h1>";

// Process specific document if requested
if ($action === 'mark' && $document_id > 0) {
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update document status
        $update_doc = "UPDATE documents SET status = 'revision_requested', updated_at = NOW() WHERE document_id = ?";
        $doc_stmt = $conn->prepare($update_doc);
        $doc_stmt->bind_param("i", $document_id);
        $doc_stmt->execute();
        
        // Check if there's already a workflow entry for this document
        $check_workflow = "SELECT workflow_id FROM document_workflow WHERE document_id = ? AND status = 'revision_requested'";
        $check_stmt = $conn->prepare($check_workflow);
        $check_stmt->bind_param("i", $document_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            // Get the document creator's office
            $creator_sql = "SELECT u.office_id FROM documents d JOIN users u ON d.creator_id = u.user_id WHERE d.document_id = ?";
            $creator_stmt = $conn->prepare($creator_sql);
            $creator_stmt->bind_param("i", $document_id);
            $creator_stmt->execute();
            $creator_result = $creator_stmt->get_result();
            $creator_data = $creator_result->fetch_assoc();
            $creator_office_id = $creator_data['office_id'];
            
            // Create a workflow entry
            $comments = "Document needs revision. Please make necessary changes.";
            $insert_workflow = "INSERT INTO document_workflow (document_id, office_id, step_order, status, comments, created_at) 
                              VALUES (?, ?, 999, 'revision_requested', ?, NOW())";
            $workflow_stmt = $conn->prepare($insert_workflow);
            $workflow_stmt->bind_param("iis", $document_id, $creator_office_id, $comments);
            $workflow_stmt->execute();
        }
        
        $conn->commit();
        echo "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4'>
                Document #{$document_id} has been marked for revision.
              </div>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4'>
                Error: " . $e->getMessage() . "
              </div>";
    }
}

// Process all documents if requested
if ($action === 'mark_all') {
    $success_count = 0;
    $error_count = 0;
    
    foreach ($document_ids as $doc_id) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update document status
            $update_doc = "UPDATE documents SET status = 'revision_requested', updated_at = NOW() WHERE document_id = ?";
            $doc_stmt = $conn->prepare($update_doc);
            $doc_stmt->bind_param("i", $doc_id);
            $doc_stmt->execute();
            
            // Check if there's already a workflow entry for this document
            $check_workflow = "SELECT workflow_id FROM document_workflow WHERE document_id = ? AND status = 'revision_requested'";
            $check_stmt = $conn->prepare($check_workflow);
            $check_stmt->bind_param("i", $doc_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                // Get the document creator's office
                $creator_sql = "SELECT u.office_id FROM documents d JOIN users u ON d.creator_id = u.user_id WHERE d.document_id = ?";
                $creator_stmt = $conn->prepare($creator_sql);
                $creator_stmt->bind_param("i", $doc_id);
                $creator_stmt->execute();
                $creator_result = $creator_stmt->get_result();
                $creator_data = $creator_result->fetch_assoc();
                $creator_office_id = $creator_data['office_id'];
                
                // Create a workflow entry
                $comments = "Document needs revision. Please make necessary changes.";
                $insert_workflow = "INSERT INTO document_workflow (document_id, office_id, step_order, status, comments, created_at) 
                                  VALUES (?, ?, 999, 'revision_requested', ?, NOW())";
                $workflow_stmt = $conn->prepare($insert_workflow);
                $workflow_stmt->bind_param("iis", $doc_id, $creator_office_id, $comments);
                $workflow_stmt->execute();
            }
            
            $conn->commit();
            $success_count++;
        } catch (Exception $e) {
            $conn->rollback();
            $error_count++;
        }
    }
    
    if ($success_count > 0) {
        echo "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4'>
                Successfully marked {$success_count} documents for revision.
              </div>";
    }
    
    if ($error_count > 0) {
        echo "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4'>
                Failed to mark {$error_count} documents for revision.
              </div>";
    }
}

// Display documents
$docs_sql = "SELECT d.document_id, d.title, d.status, u.full_name as creator_name
            FROM documents d
            JOIN users u ON d.creator_id = u.user_id
            WHERE d.document_id IN (" . implode(',', array_map('intval', $document_ids)) . ")";
$docs_result = $conn->query($docs_sql);

if ($docs_result && $docs_result->num_rows > 0) {
    echo "<div class='overflow-x-auto'>
            <table class='min-w-full bg-white border border-gray-200'>
                <thead>
                    <tr>
                        <th class='px-4 py-2 border'>ID</th>
                        <th class='px-4 py-2 border'>Title</th>
                        <th class='px-4 py-2 border'>Status</th>
                        <th class='px-4 py-2 border'>Creator</th>
                        <th class='px-4 py-2 border'>Action</th>
                    </tr>
                </thead>
                <tbody>";
    
    while ($row = $docs_result->fetch_assoc()) {
        $status_class = ($row['status'] === 'revision_requested') ? 'bg-purple-100' : '';
        echo "<tr class='{$status_class}'>
                <td class='px-4 py-2 border'>{$row['document_id']}</td>
                <td class='px-4 py-2 border'>{$row['title']}</td>
                <td class='px-4 py-2 border'>{$row['status']}</td>
                <td class='px-4 py-2 border'>{$row['creator_name']}</td>
                <td class='px-4 py-2 border'>
                    <a href='?action=mark&id={$row['document_id']}' class='inline-block px-3 py-1 bg-purple-600 text-white rounded hover:bg-purple-700'>
                        Mark for Revision
                    </a>
                </td>
            </tr>";
    }
    
    echo "</tbody></table></div>";
    
    echo "<div class='mt-6 flex space-x-4'>
            <a href='?action=mark_all' class='inline-block px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700'>
                Mark All for Revision
            </a>
            <a href='check_revision_status.php' class='inline-block px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700'>
                Check Status
            </a>
            <a href='pages/dashboard.php' class='inline-block px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700'>
                Return to Dashboard
            </a>
          </div>";
} else {
    echo "<p>No documents found.</p>";
}

echo "</div></div></body></html>";
?>
