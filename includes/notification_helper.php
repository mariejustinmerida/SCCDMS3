<?php
/**
 * Notification Helper Functions
 * 
 * This file contains functions for creating and managing notifications
 */

/**
 * Create a document-related notification
 * 
 * @param int $document_id The ID of the document
 * @param int $user_id The ID of the user to notify
 * @param string $status The document status (approved, rejected, revision_requested, on_hold, etc.)
 * @param string $title Optional custom title for the notification
 * @param string $message Optional custom message for the notification
 * @return bool True if notification was created, false otherwise
 */
function create_document_notification($document_id, $user_id, $status, $title = null, $message = null) {
    global $conn;
    
    // Get document details
    $doc_query = "SELECT title FROM documents WHERE document_id = ?";
    $doc_stmt = $conn->prepare($doc_query);
    $doc_stmt->bind_param("i", $document_id);
    $doc_stmt->execute();
    $doc_result = $doc_stmt->get_result();
    
    if ($doc_result->num_rows === 0) {
        return false; // Document not found
    }
    
    $document = $doc_result->fetch_assoc();
    $document_title = $document['title'];
    
    // Generate title and message based on status if not provided
    if (!$title) {
        switch ($status) {
            case 'approved':
                $title = "Document Approved";
                break;
            case 'rejected':
                $title = "Document Rejected";
                break;
            case 'revision_requested':
                $title = "Revision Requested";
                break;
            case 'on_hold':
                $title = "Document On Hold";
                break;
            default:
                $title = "Document Update";
        }
    }
    
    if (!$message) {
        $message = "Document \"$document_title\" requires your attention";
    }
    
    // Check if notifications table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($table_check->num_rows == 0) {
        // Create notifications table if it doesn't exist
        $create_table_sql = "CREATE TABLE IF NOT EXISTS notifications (
            notification_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            document_id INT,
            title VARCHAR(255) NOT NULL,
            message TEXT,
            status VARCHAR(50),
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE
        )";
        
        if (!$conn->query($create_table_sql)) {
            return false; // Failed to create table
        }
    }
    
    // Check if we need to update the table structure
    $column_check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'document_id'");
    if ($column_check->num_rows == 0) {
        // Add document_id column if it doesn't exist
        $alter_table_sql = "ALTER TABLE notifications 
                            ADD COLUMN document_id INT,
                            ADD COLUMN status VARCHAR(50),
                            ADD FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE";
        $conn->query($alter_table_sql);
    }
    
    // Insert notification
    $insert_query = "INSERT INTO notifications (user_id, document_id, title, message, status) VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iisss", $user_id, $document_id, $title, $message, $status);
    
    return $insert_stmt->execute();
}

/**
 * Create notifications for all users involved in a document workflow
 * 
 * @param int $document_id The ID of the document
 * @param string $status The document status
 * @param string $title Optional custom title for the notification
 * @param string $message Optional custom message for the notification
 * @return int Number of notifications created
 */
function notify_document_workflow_users($document_id, $status, $title = null, $message = null) {
    global $conn;
    
    // Get document creator
    $creator_query = "SELECT creator_id FROM documents WHERE document_id = ?";
    $creator_stmt = $conn->prepare($creator_query);
    $creator_stmt->bind_param("i", $document_id);
    $creator_stmt->execute();
    $creator_result = $creator_stmt->get_result();
    
    if ($creator_result->num_rows === 0) {
        return 0; // Document not found
    }
    
    $document = $creator_result->fetch_assoc();
    $creator_id = $document['creator_id'];
    
    // Get all users involved in the workflow
    $workflow_query = "SELECT DISTINCT user_id FROM document_workflow 
                      WHERE document_id = ? AND user_id IS NOT NULL
                      UNION
                      SELECT DISTINCT u.user_id FROM document_workflow dw
                      JOIN offices o ON dw.office_id = o.office_id
                      JOIN users u ON u.office_id = o.office_id
                      WHERE dw.document_id = ?";
    
    $workflow_stmt = $conn->prepare($workflow_query);
    $workflow_stmt->bind_param("ii", $document_id, $document_id);
    $workflow_stmt->execute();
    $workflow_result = $workflow_stmt->get_result();
    
    $users = [];
    while ($row = $workflow_result->fetch_assoc()) {
        $users[] = $row['user_id'];
    }
    
    // Add document creator if not already included
    if (!in_array($creator_id, $users)) {
        $users[] = $creator_id;
    }
    
    // Create notification for each user
    $count = 0;
    foreach ($users as $user_id) {
        if (create_document_notification($document_id, $user_id, $status, $title, $message)) {
            $count++;
        }
    }
    
    return $count;
}

/**
 * Mark all notifications as read for a user
 * 
 * @param int $user_id The ID of the user
 * @return bool True if successful, false otherwise
 */
function mark_all_notifications_read($user_id) {
    global $conn;
    
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    
    return $stmt->execute();
}

/**
 * Get unread notification count for a user
 * 
 * @param int $user_id The ID of the user
 * @return int Number of unread notifications
 */
function get_unread_notification_count($user_id) {
    global $conn;
    
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['count'];
    }
    
    return 0;
} 