<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
require_once 'includes/notification_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$created_count = 0;

// Create some general notifications
$general_notifications = [
    [
        'title' => 'Welcome to SCCDMS',
        'message' => 'Welcome to the SCC Document Management System. This notification system will keep you updated on document changes.',
        'status' => 'info'
    ],
    [
        'title' => 'Document Update',
        'message' => 'A document has been updated and requires your attention.',
        'status' => 'pending'
    ],
    [
        'title' => 'System Notification',
        'message' => 'The document management system has been updated with new features.',
        'status' => 'info'
    ],
    [
        'title' => 'Document Approved',
        'message' => 'Your document "Budget Proposal" has been approved.',
        'status' => 'approved'
    ],
    [
        'title' => 'Revision Requested',
        'message' => 'Your document "Meeting Minutes" requires revision.',
        'status' => 'revision_requested'
    ],
    [
        'title' => 'Document Rejected',
        'message' => 'Your document "Expense Report" has been rejected.',
        'status' => 'rejected'
    ],
    [
        'title' => 'Document On Hold',
        'message' => 'Your document "Project Proposal" has been put on hold.',
        'status' => 'on_hold'
    ]
];

// Insert notifications
foreach ($general_notifications as $notification) {
    $insert_query = "INSERT INTO notifications (user_id, title, message, status, is_read, created_at) 
                    VALUES (?, ?, ?, ?, 0, NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("isss", $user_id, $notification['title'], $notification['message'], $notification['status']);
    if ($insert_stmt->execute()) {
        $created_count++;
    }
}

// Get some documents for the current user
$doc_query = "SELECT document_id, title FROM documents 
              WHERE creator_id = ? OR document_id IN (
                  SELECT document_id FROM document_workflow WHERE office_id = (
                      SELECT office_id FROM users WHERE user_id = ?
                  )
              )
              LIMIT 3";
$doc_stmt = $conn->prepare($doc_query);
$doc_stmt->bind_param("ii", $user_id, $user_id);
$doc_stmt->execute();
$doc_result = $doc_stmt->get_result();

// Create document-related notifications if documents exist
while ($document = $doc_result->fetch_assoc()) {
    $document_id = $document['document_id'];
    $title = $document['title'];
    
    // Create notifications with different statuses
    $statuses = ['approved', 'rejected', 'revision_requested', 'on_hold', 'pending'];
    
    foreach ($statuses as $status) {
        if (create_document_notification($document_id, $user_id, $status, null, null)) {
            $created_count++;
        }
    }
}

// Redirect back to dashboard
$_SESSION['success'] = "$created_count test notifications created successfully. Click the notification bell to view them.";
header("Location: pages/dashboard.php");
exit; 