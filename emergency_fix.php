<?php
require_once 'includes/config.php';

// Set content type to HTML for better display
header('Content-Type: text/html');

// Start session to get user information
session_start();
$user_id = $_SESSION['user_id'] ?? null;
$office_id = $_SESSION['office_id'] ?? null;

echo "<html><head><title>Emergency Fix</title>
<style>
  body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
  h1, h2, h3 { color: #2c3e50; }
  pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow: auto; }
  .success { color: green; }
  .error { color: red; }
  .warning { color: orange; }
  .code { font-family: monospace; background: #f0f0f0; padding: 2px 4px; }
  table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
  th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
  th { background-color: #f2f2f2; }
  .button { display: inline-block; background: #3498db; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; margin-top: 10px; }
</style>
</head><body>
<h1>Emergency Fix for Incoming Documents</h1>

";

if (!$user_id || !$office_id) {
    echo "<div class='error'>Error: User not logged in or office ID not set. Please <a href='auth/login.php'>log in</a> first.</div>";
    echo "</body></html>";
    exit;
}

echo "<p>User ID: $user_id</p>";
echo "<p>Office ID: $office_id</p>";

// STEP 1: Check for database issues
echo "<h2>Step 1: Database Diagnosis</h2>";

// Check if there are any documents assigned to this office
$check_query = "SELECT COUNT(*) as count FROM document_workflow WHERE office_id = $office_id";
$check_result = $conn->query($check_query);
$assigned_count = 0;
if ($check_result && $row = $check_result->fetch_assoc()) {
    $assigned_count = $row['count'];
}

echo "<p>Documents assigned to your office: $assigned_count</p>";

// Check if there are any documents with 'current' status
$current_query = "SELECT COUNT(*) as count FROM document_workflow WHERE office_id = $office_id AND status = 'current'";
$current_result = $conn->query($current_query);
$current_count = 0;
if ($current_result && $row = $current_result->fetch_assoc()) {
    $current_count = $row['count'];
}

echo "<p>Documents with 'current' status for your office: $current_count</p>";

// Check if there are any documents with 'Current' status (case sensitive)
$case_query = "SELECT COUNT(*) as count FROM document_workflow WHERE office_id = $office_id AND status = 'Current'";
$case_result = $conn->query($case_query);
$case_count = 0;
if ($case_result && $row = $case_result->fetch_assoc()) {
    $case_count = $row['count'];
}

echo "<p>Documents with 'Current' status (case sensitive) for your office: $case_count</p>";

// STEP 2: Fix case sensitivity issues
echo "<h2>Step 2: Fixing Case Sensitivity Issues</h2>";

if ($case_count > 0) {
    $fix_case_query = "UPDATE document_workflow SET status = 'current' WHERE office_id = $office_id AND status = 'Current'";
    $fix_case_result = $conn->query($fix_case_query);
    
    if ($fix_case_result) {
        echo "<p class='success'>Fixed $case_count documents with 'Current' status to 'current'</p>";
    } else {
        echo "<p class='error'>Error fixing case sensitivity: " . $conn->error . "</p>";
    }
} else {
    echo "<p>No case sensitivity issues found.</p>";
}

// STEP 3: Create a test document
echo "<h2>Step 3: Creating a Test Document</h2>";

// Start a transaction
$conn->begin_transaction();

try {
    // Insert a new document
    $title = "EMERGENCY TEST DOCUMENT - " . date('Y-m-d H:i:s');
    $insert_doc_query = "INSERT INTO documents (title, type_id, creator_id, status, created_at) 
        VALUES (?, 1, ?, 'pending', NOW())";
    $insert_doc_stmt = $conn->prepare($insert_doc_query);
    $insert_doc_stmt->bind_param("si", $title, $user_id);
    $insert_doc_stmt->execute();
    
    $document_id = $conn->insert_id;
    
    // Insert workflow step for the current office
    $insert_workflow_query = "INSERT INTO document_workflow (document_id, office_id, step_order, status, assigned_at) 
        VALUES (?, ?, 1, 'current', NOW())";
    $insert_workflow_stmt = $conn->prepare($insert_workflow_query);
    $insert_workflow_stmt->bind_param("ii", $document_id, $office_id);
    $insert_workflow_stmt->execute();
    
    // Commit the transaction
    $conn->commit();
    
    echo "<p class='success'>Created test document with ID: $document_id</p>";
    echo "<p>Title: $title</p>";
    echo "<p>Status: pending</p>";
    echo "<p>Workflow Status: current</p>";
} catch (Exception $e) {
    // Roll back the transaction if something failed
    $conn->rollback();
    echo "<p class='error'>Error creating test document: " . $e->getMessage() . "</p>";
}

// STEP 4: Show all documents that should be in the inbox
echo "<h2>Step 4: Documents That Should Be in Your Inbox</h2>";

$documents_query = "SELECT d.document_id, d.title, d.status, dw.status as workflow_status 
    FROM documents d 
    JOIN document_workflow dw ON d.document_id = dw.document_id 
    WHERE dw.office_id = $office_id AND dw.status = 'current'";

$documents_result = $conn->query($documents_query);

if ($documents_result && $documents_result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Document ID</th><th>Title</th><th>Document Status</th><th>Workflow Status</th></tr>";
    
    while ($row = $documents_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['document_id']}</td>";
        echo "<td>{$row['title']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['workflow_status']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p class='warning'>No documents found that should be in your inbox.</p>";
}

// STEP 5: Create a direct view of the inbox
echo "<h2>Step 5: Direct Inbox View</h2>";

// Create a simple direct inbox view
$direct_inbox_query = "SELECT d.document_id, d.title, d.status, dw.status as workflow_status, 
                            dt.type_name, u.full_name as creator_name, o.office_name as creator_office 
                      FROM document_workflow dw 
                      JOIN documents d ON dw.document_id = d.document_id 
                      LEFT JOIN document_types dt ON d.type_id = dt.type_id 
                      LEFT JOIN users u ON d.creator_id = u.user_id 
                      LEFT JOIN offices o ON u.office_id = o.office_id 
                      WHERE dw.office_id = $office_id AND dw.status = 'current'";

$direct_inbox_result = $conn->query($direct_inbox_query);

if ($direct_inbox_result && $direct_inbox_result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Document ID</th><th>Title</th><th>Type</th><th>Creator</th><th>From Office</th><th>Status</th></tr>";
    
    while ($row = $direct_inbox_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['document_id']}</td>";
        echo "<td>{$row['title']}</td>";
        echo "<td>{$row['type_name']}</td>";
        echo "<td>{$row['creator_name']}</td>";
        echo "<td>{$row['creator_office']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p class='warning'>No documents found in direct inbox view.</p>";
}

// STEP 6: Create a direct link to the incoming page
echo "<h2>Step 6: Next Steps</h2>";

echo "<p>Please try the following steps:</p>";
echo "<ol>";
echo "<li>Click <a href='pages/incoming.php' target='_blank' class='button'>Open Inbox</a> to see if the documents appear in your inbox</li>";
echo "<li>If documents still don't appear, try clearing your browser cache or opening in a private/incognito window</li>";
echo "<li>If documents appear in this page but not in your inbox, there may be an issue with the incoming.php page</li>";
echo "</ol>";

echo "<h3>Troubleshooting</h3>";
echo "<p>If you're still experiencing issues, try the following:</p>";
echo "<ol>";
echo "<li>Check if your browser's JavaScript is enabled</li>";
echo "<li>Try a different browser</li>";
echo "<li>Check if there are any JavaScript errors in your browser's console (F12 > Console)</li>";
echo "<li>Contact your system administrator for further assistance</li>";
echo "</ol>";

echo "</body></html>";
?>
