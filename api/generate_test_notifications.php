<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/notification_helper.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get some documents for the current user
$doc_query = "SELECT document_id, title FROM documents 
              WHERE creator_id = ? OR document_id IN (
                  SELECT document_id FROM document_workflow WHERE office_id = (
                      SELECT office_id FROM users WHERE user_id = ?
                  )
              )
              LIMIT 5";
$doc_stmt = $conn->prepare($doc_query);
$doc_stmt->bind_param("ii", $user_id, $user_id);
$doc_stmt->execute();
$doc_result = $doc_stmt->get_result();

$documents = [];
while ($row = $doc_result->fetch_assoc()) {
    $documents[] = $row;
}

// If no documents found, create a sample notification without document ID
if (empty($documents)) {
    $insert_query = "INSERT INTO notifications (user_id, title, message, status, is_read, created_at) 
                    VALUES (?, 'Sample Notification', 'This is a sample notification for testing purposes.', 'info', 0, NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("i", $user_id);
    $insert_stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Sample notification created'
    ]);
    exit;
}

// Generate different types of notifications
$statuses = ['approved', 'rejected', 'revision_requested', 'on_hold', 'pending'];
$created_count = 0;

foreach ($documents as $document) {
    $status = $statuses[array_rand($statuses)];
    $document_id = $document['document_id'];
    $title = $document['title'];
    
    switch ($status) {
        case 'approved':
            $notification_title = 'Document Approved';
            $message = "Document \"$title\" has been approved";
            break;
        case 'rejected':
            $notification_title = 'Document Rejected';
            $message = "Document \"$title\" has been rejected";
            break;
        case 'revision_requested':
            $notification_title = 'Revision Requested';
            $message = "Document \"$title\" requires revision";
            break;
        case 'on_hold':
            $notification_title = 'Document On Hold';
            $message = "Document \"$title\" has been put on hold";
            break;
        default:
            $notification_title = 'New Document';
            $message = "New document \"$title\" requires your attention";
    }
    
    // Create notification
    if (create_document_notification($document_id, $user_id, $status, $notification_title, $message)) {
        $created_count++;
    }
}

// Create a few more general notifications
$general_notifications = [
    [
        'title' => 'System Update',
        'message' => 'The document management system has been updated with new features.',
        'status' => 'info'
    ],
    [
        'title' => 'Reminder',
        'message' => 'You have pending documents that require your attention.',
        'status' => 'pending'
    ],
    [
        'title' => 'Welcome',
        'message' => 'Welcome to the updated notification system.',
        'status' => 'info'
    ]
];

foreach ($general_notifications as $notification) {
    $insert_query = "INSERT INTO notifications (user_id, title, message, status, is_read, created_at) 
                    VALUES (?, ?, ?, ?, 0, NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("isss", $user_id, $notification['title'], $notification['message'], $notification['status']);
    if ($insert_stmt->execute()) {
        $created_count++;
    }
}

echo json_encode([
    'success' => true,
    'message' => "$created_count test notifications created successfully"
]); 