<?php
require_once 'includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: pages/login.php");
    exit();
}

// Get document ID from URL
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($document_id <= 0) {
    die("<p>Error: Invalid document ID.</p>");
}

// Get the document information
$document_query = "SELECT * FROM documents WHERE document_id = $document_id";
$document_result = $conn->query($document_query);

if (!$document_result || $document_result->num_rows === 0) {
    die("<p>Error: Document not found.</p>");
}

$document = $document_result->fetch_assoc();
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'] ?? 0;

// Get the workflow history to determine the correct offices
$workflow_query = "SELECT * FROM document_workflow WHERE document_id = $document_id ORDER BY workflow_id DESC";
$workflow_result = $conn->query($workflow_query);

if (!$workflow_result || $workflow_result->num_rows === 0) {
    die("<p>Error: No workflow entries found for this document.</p>");
}

// Find the requesting office (the one that asked for revision)
$requesting_office_id = 0;
$creator_office_id = 0;

// First, get the creator's office ID
$creator_query = "SELECT o.office_id FROM users u 
                 JOIN offices o ON u.office_id = o.office_id 
                 WHERE u.user_id = {$document['creator_id']}";
$creator_result = $conn->query($creator_query);
if ($creator_result && $creator_result->num_rows > 0) {
    $creator_office_id = $creator_result->fetch_assoc()['office_id'];
}

// Find the office that requested the revision
// This should be the office that created the most recent workflow entry
// that is not the creator's office
while ($workflow = $workflow_result->fetch_assoc()) {
    if ($workflow['office_id'] != $creator_office_id) {
        $requesting_office_id = $workflow['office_id'];
        break;
    }
}

// Begin transaction
$conn->begin_transaction();

try {
    // Only proceed if we found a requesting office
    if ($requesting_office_id <= 0) {
        throw new Exception("Could not determine the office that requested the revision.");
    }
    
    // 1. Update document status to 'revised' - NOT 'incoming' to prevent auto-approval
    $update_doc = "UPDATE documents SET status = 'revised', updated_at = NOW() WHERE document_id = $document_id";
    $conn->query($update_doc);
    
    // 2. Mark all current workflow entries as COMPLETED
    $update_workflow = "UPDATE document_workflow SET status = 'COMPLETED' WHERE document_id = $document_id AND status = 'CURRENT'";
    $conn->query($update_workflow);
    
    // 3. Create a new PENDING workflow entry for the requesting office
    // Using PENDING instead of CURRENT to ensure it doesn't get auto-approved
    $new_workflow = "INSERT INTO document_workflow (document_id, office_id, status, comments, created_at) 
                    VALUES ($document_id, $requesting_office_id, 'PENDING', 'Document has been revised as requested. Please review the changes.', NOW())";
    $conn->query($new_workflow);
    
    // 4. Log the action
    $log_sql = "INSERT INTO document_logs (document_id, user_id, action, details, created_at) 
               VALUES ($document_id, $user_id, 'revised', 'Document revised and sent back to requesting office.', NOW())";
    $conn->query($log_sql);
    
    // Commit transaction
    $conn->commit();
    
    // Set success message
    $_SESSION['success_message'] = 'Document has been successfully revised and sent back to the requesting office for review. It will NOT be auto-approved.';
    
    // Redirect back to the documents needing revision page
    header("Location: pages/dashboard.php?page=documents_needing_revision");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
