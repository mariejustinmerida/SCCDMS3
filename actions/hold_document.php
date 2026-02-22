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
$reason = $_GET['reason'] ?? 'No reason provided';

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

// Check if user has permission to put this document on hold
// This would typically check if the user is in the approval workflow
// For simplicity, we'll allow it if they're not the creator
if ($document['creator_id'] == $user_id) {
    $_SESSION['error'] = "You cannot put your own document on hold";
    header("Location: ../pages/document_details.php?id=$document_id");
    exit;
}

// Update document status to on_hold
$update_sql = "UPDATE documents SET status = 'on_hold', updated_at = NOW() WHERE document_id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("i", $document_id);

if ($update_stmt->execute()) {
    // Log the hold action
    $log_sql = "INSERT INTO document_logs (document_id, user_id, action, details) VALUES (?, ?, 'on_hold', ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_details = "Document put on hold. Reason: $reason";
    $log_stmt->bind_param("iis", $document_id, $user_id, $log_details);
    $log_stmt->execute();
    
    // Create enhanced notification for document creator
    notify_document_creator($document_id, 'on_hold', [
        'reason' => $reason,
        'held_by' => $_SESSION['user_id']
    ]);
    
    // Notify current office
    $current_office = get_current_workflow_office($document_id);
    if ($current_office) {
        notify_office_users($current_office, $document_id, 'on_hold', [
            'reason' => $reason,
            'held_by' => $_SESSION['user_id']
        ]);
    }
    
    $_SESSION['success'] = "Document has been put on hold successfully";
} else {
    $_SESSION['error'] = "Failed to put document on hold: " . $conn->error;
}

// Redirect back to document details
header("Location: ../pages/document_details.php?id=$document_id");
exit;

/**
 * Get current office in workflow
 */
function get_current_workflow_office($document_id) {
    global $conn;
    
    $current_sql = "SELECT office_id FROM document_workflow 
                    WHERE document_id = ? AND status = 'CURRENT'";
    $current_stmt = $conn->prepare($current_sql);
    $current_stmt->bind_param("i", $document_id);
    $current_stmt->execute();
    $current_result = $current_stmt->get_result();
    
    if ($current_result->num_rows > 0) {
        return $current_result->fetch_assoc()['office_id'];
    }
    
    return null;
}
?>
