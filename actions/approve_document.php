<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/enhanced_notification_system.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if document ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Document ID is required";
    header("Location: ../pages/documents.php");
    exit;
}

$document_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Get document details
$doc_query = "SELECT d.*, u.username as creator_name, dt.name as document_type
              FROM documents d
              JOIN users u ON d.creator_id = u.user_id
              JOIN document_types dt ON d.type_id = dt.type_id
              WHERE d.document_id = ?";
$doc_stmt = $conn->prepare($doc_query);
$doc_stmt->bind_param("i", $document_id);
$doc_stmt->execute();
$doc_result = $doc_stmt->get_result();

if ($doc_result->num_rows === 0) {
    $_SESSION['error'] = "Document not found";
    header("Location: ../pages/documents.php");
    exit;
}

$document = $doc_result->fetch_assoc();

// Check if user has permission to approve this document
// This would typically check if the user is in the approval workflow
// For simplicity, we'll allow it if they're not the creator
if ($document['creator_id'] == $user_id) {
    $_SESSION['error'] = "You cannot approve your own document";
    header("Location: ../pages/document_details.php?id=$document_id");
    exit;
}

// Update document status to approved
$update_sql = "UPDATE documents SET status = 'approved', updated_at = NOW() WHERE document_id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("i", $document_id);

if ($update_stmt->execute()) {
    // Log the approval action
    $log_sql = "INSERT INTO document_logs (document_id, user_id, action, details) VALUES (?, ?, 'approved', 'Document approved')";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("ii", $document_id, $user_id);
    $log_stmt->execute();
    
    // Create enhanced notification for document creator
    notify_document_creator($document_id, 'approved', [
        'reason' => 'Document has been approved and is ready for the next step',
        'approved_by' => $_SESSION['user_id']
    ]);
    
    // Notify all users involved in the workflow
    notify_workflow_users($document_id, 'approved', [
        'reason' => 'Document has been approved and is ready for the next step',
        'approved_by' => $_SESSION['user_id']
    ]);
    
    // Move document to next workflow step if applicable
    move_to_next_workflow_step($document_id);
    
    $_SESSION['success'] = "Document has been approved successfully";
} else {
    $_SESSION['error'] = "Failed to approve document: " . $conn->error;
}

// Redirect back to document details
header("Location: ../pages/document_details.php?id=$document_id");
exit;

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
} 