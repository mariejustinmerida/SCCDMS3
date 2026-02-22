<?php
/**
 * Enhanced Notification System
 * 
 * This file contains comprehensive notification functions for all document workflow events
 */

/**
 * Create a comprehensive document notification
 * 
 * @param int $document_id The ID of the document
 * @param int $user_id The ID of the user to notify
 * @param string $event_type The type of event (incoming, approved, rejected, on_hold, urgent, stuck, etc.)
 * @param array $additional_data Additional data for the notification
 * @return bool True if notification was created, false otherwise
 */
function create_enhanced_notification($document_id, $user_id, $event_type, $additional_data = []) {
    global $conn;
    
    // Get document details
    $doc_query = "SELECT d.*, u.full_name as creator_name, dt.type_name, o.office_name as creator_office
                  FROM documents d
                  LEFT JOIN users u ON d.creator_id = u.user_id
                  LEFT JOIN document_types dt ON d.type_id = dt.type_id
                  LEFT JOIN offices o ON u.office_id = o.office_id
                  WHERE d.document_id = ?";
    $doc_stmt = $conn->prepare($doc_query);
    $doc_stmt->bind_param("i", $document_id);
    $doc_stmt->execute();
    $doc_result = $doc_stmt->get_result();
    
    if ($doc_result->num_rows === 0) {
        return false; // Document not found
    }
    
    $document = $doc_result->fetch_assoc();
    $document_title = $document['title'];
    $creator_name = $document['creator_name'] ?? 'Unknown';
    $document_type = $document['type_name'] ?? 'Document';
    $creator_office = $document['creator_office'] ?? 'Unknown Office';
    
    // Generate notification based on event type
    $notification_data = generate_notification_content($event_type, $document, $additional_data);
    
    // Ensure notifications table exists with all required columns
    ensure_notifications_table_structure();
    
    // Insert notification
    $insert_query = "INSERT INTO notifications (user_id, document_id, title, message, status, event_type, priority, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iisssss", $user_id, $document_id, $notification_data['title'], 
                           $notification_data['message'], $notification_data['status'], 
                           $event_type, $notification_data['priority']);
    
    return $insert_stmt->execute();
}

/**
 * Generate notification content based on event type
 */
function generate_notification_content($event_type, $document, $additional_data = []) {
    $document_title = $document['title'];
    $creator_name = $document['creator_name'] ?? 'Unknown';
    $document_type = $document['type_name'] ?? 'Document';
    $creator_office = $document['creator_office'] ?? 'Unknown Office';
    $is_urgent = $document['is_urgent'] ?? false;
    
    switch ($event_type) {
        case 'incoming':
            $title = "📥 New Document Received";
            $message = "Document \"$document_title\" from $creator_name ($creator_office) requires your attention.";
            $status = 'pending';
            $priority = $is_urgent ? 'high' : 'normal';
            break;
            
        case 'approved':
            $title = "✅ Document Approved";
            $message = "Your document \"$document_title\" has been approved and is ready for the next step.";
            $status = 'approved';
            $priority = 'normal';
            break;
            
        case 'rejected':
            $reason = $additional_data['reason'] ?? 'No reason provided';
            $title = "❌ Document Rejected";
            $message = "Your document \"$document_title\" has been rejected. Reason: $reason";
            $status = 'rejected';
            $priority = 'high';
            break;
            
        case 'on_hold':
            $reason = $additional_data['reason'] ?? 'No reason provided';
            $title = "⏸️ Document On Hold";
            $message = "Your document \"$document_title\" has been put on hold. Reason: $reason";
            $status = 'on_hold';
            $priority = 'normal';
            break;
            
        case 'revision_requested':
            $title = "📝 Revision Requested";
            $message = "Your document \"$document_title\" requires revisions before it can proceed.";
            $status = 'revision_requested';
            $priority = 'high';
            break;
            
        case 'urgent_reminder':
            $days_stuck = $additional_data['days_stuck'] ?? 0;
            $title = "🚨 URGENT: Document Needs Attention";
            $message = "Document \"$document_title\" has been pending for $days_stuck days and requires immediate attention.";
            $status = 'urgent';
            $priority = 'critical';
            break;
            
        case 'stuck_document':
            $days_stuck = $additional_data['days_stuck'] ?? 0;
            $current_office = $additional_data['current_office'] ?? 'Unknown Office';
            $title = "⏰ Document Stuck in Workflow";
            $message = "Document \"$document_title\" has been stuck in $current_office for $days_stuck days.";
            $status = 'stuck';
            $priority = 'high';
            break;
            
        case 'workflow_advance':
            $from_office = $additional_data['from_office'] ?? 'Previous Office';
            $to_office = $additional_data['to_office'] ?? 'Next Office';
            $title = "➡️ Document Advanced in Workflow";
            $message = "Document \"$document_title\" has moved from $from_office to $to_office.";
            $status = 'workflow_advance';
            $priority = 'normal';
            break;
            
        case 'memorandum_received':
            $title = "📋 Memorandum Received";
            $message = "New memorandum \"$document_title\" has been distributed to your office.";
            $status = 'memorandum';
            $priority = 'normal';
            break;
            
        case 'deadline_approaching':
            $days_left = $additional_data['days_left'] ?? 0;
            $title = "⏰ Deadline Approaching";
            $message = "Document \"$document_title\" deadline is approaching in $days_left days.";
            $status = 'deadline';
            $priority = 'high';
            break;
            
        default:
            $title = "📄 Document Update";
            $message = "Document \"$document_title\" has been updated.";
            $status = 'info';
            $priority = 'normal';
    }
    
    return [
        'title' => $title,
        'message' => $message,
        'status' => $status,
        'priority' => $priority
    ];
}

/**
 * Notify all users in an office about a document event
 */
function notify_office_users($office_id, $document_id, $event_type, $additional_data = []) {
    global $conn;
    
    // Get all users in the office
    $users_query = "SELECT user_id FROM users WHERE office_id = ?";
    $users_stmt = $conn->prepare($users_query);
    $users_stmt->bind_param("i", $office_id);
    $users_stmt->execute();
    $users_result = $users_stmt->get_result();
    
    $notifications_created = 0;
    while ($user = $users_result->fetch_assoc()) {
        if (create_enhanced_notification($document_id, $user['user_id'], $event_type, $additional_data)) {
            $notifications_created++;
        }
    }
    
    return $notifications_created;
}

/**
 * Notify document creator about status changes
 */
function notify_document_creator($document_id, $event_type, $additional_data = []) {
    global $conn;
    
    // Get document creator
    $creator_query = "SELECT creator_id FROM documents WHERE document_id = ?";
    $creator_stmt = $conn->prepare($creator_query);
    $creator_stmt->bind_param("i", $document_id);
    $creator_stmt->execute();
    $creator_result = $creator_stmt->get_result();
    
    if ($creator_result->num_rows === 0) {
        return false;
    }
    
    $creator = $creator_result->fetch_assoc();
    return create_enhanced_notification($document_id, $creator['creator_id'], $event_type, $additional_data);
}

/**
 * Notify all users involved in document workflow
 */
function notify_workflow_users($document_id, $event_type, $additional_data = []) {
    global $conn;
    
    // Get all users involved in the workflow
    $workflow_query = "SELECT DISTINCT u.user_id 
                       FROM document_workflow dw
                       JOIN users u ON u.office_id = dw.office_id
                       WHERE dw.document_id = ?
                       UNION
                       SELECT creator_id FROM documents WHERE document_id = ?";
    
    $workflow_stmt = $conn->prepare($workflow_query);
    $workflow_stmt->bind_param("ii", $document_id, $document_id);
    $workflow_stmt->execute();
    $workflow_result = $workflow_stmt->get_result();
    
    $notifications_created = 0;
    while ($user = $workflow_result->fetch_assoc()) {
        if (create_enhanced_notification($document_id, $user['user_id'], $event_type, $additional_data)) {
            $notifications_created++;
        }
    }
    
    return $notifications_created;
}

/**
 * Ensure notifications table has all required columns
 */
function ensure_notifications_table_structure() {
    global $conn;
    
    // Check if notifications table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($table_check->num_rows == 0) {
        // Create notifications table
        $create_table_sql = "CREATE TABLE notifications (
            notification_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            document_id INT,
            title VARCHAR(255) NOT NULL,
            message TEXT,
            status VARCHAR(50),
            event_type VARCHAR(50),
            priority ENUM('low', 'normal', 'high', 'critical') DEFAULT 'normal',
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE
        )";
        $conn->query($create_table_sql);
    } else {
        // Check and add missing columns
        $columns_to_add = [
            'event_type' => "ADD COLUMN event_type VARCHAR(50)",
            'priority' => "ADD COLUMN priority ENUM('low', 'normal', 'high', 'critical') DEFAULT 'normal'"
        ];
        
        foreach ($columns_to_add as $column => $sql) {
            $column_check = $conn->query("SHOW COLUMNS FROM notifications LIKE '$column'");
            if ($column_check->num_rows == 0) {
                $conn->query("ALTER TABLE notifications $sql");
            }
        }
    }
}

/**
 * Create AI-powered urgent document reminder
 */
function create_urgent_reminder($document_id, $days_stuck = 0) {
    global $conn;
    
    // Get document details
    $doc_query = "SELECT d.*, u.full_name as creator_name, o.office_name as current_office
                  FROM documents d
                  LEFT JOIN users u ON d.creator_id = u.user_id
                  LEFT JOIN document_workflow dw ON d.document_id = dw.document_id AND dw.status = 'CURRENT'
                  LEFT JOIN offices o ON dw.office_id = o.office_id
                  WHERE d.document_id = ?";
    $doc_stmt = $conn->prepare($doc_query);
    $doc_stmt->bind_param("i", $document_id);
    $doc_stmt->execute();
    $doc_result = $doc_stmt->get_result();
    
    if ($doc_result->num_rows === 0) {
        return false;
    }
    
    $document = $doc_result->fetch_assoc();
    
    // Determine urgency level
    $urgency_level = 'normal';
    if ($days_stuck >= 7) {
        $urgency_level = 'critical';
    } elseif ($days_stuck >= 3) {
        $urgency_level = 'high';
    }
    
    // Create urgent reminder for document creator
    $additional_data = [
        'days_stuck' => $days_stuck,
        'current_office' => $document['current_office'] ?? 'Unknown Office'
    ];
    
    $creator_notified = notify_document_creator($document_id, 'urgent_reminder', $additional_data);
    
    // Also notify current office if different from creator
    if ($document['current_office'] && $document['creator_id']) {
        $current_office_query = "SELECT office_id FROM document_workflow 
                                WHERE document_id = ? AND status = 'CURRENT'";
        $office_stmt = $conn->prepare($current_office_query);
        $office_stmt->bind_param("i", $document_id);
        $office_stmt->execute();
        $office_result = $office_stmt->get_result();
        
        if ($office_result->num_rows > 0) {
            $office = $office_result->fetch_assoc();
            $office_notified = notify_office_users($office['office_id'], $document_id, 'stuck_document', $additional_data);
        }
    }
    
    return $creator_notified;
}

/**
 * Check for stuck documents and create reminders
 */
function check_stuck_documents() {
    global $conn;
    
    // Find documents that have been pending for more than 2 days
    $stuck_query = "SELECT d.document_id, d.title, d.created_at, d.updated_at,
                           DATEDIFF(NOW(), COALESCE(d.updated_at, d.created_at)) as days_stuck
                    FROM documents d
                    WHERE d.status = 'pending'
                    AND DATEDIFF(NOW(), COALESCE(d.updated_at, d.created_at)) >= 2
                    AND d.document_id NOT IN (
                        SELECT DISTINCT document_id FROM notifications 
                        WHERE event_type = 'urgent_reminder' 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                    )";
    
    $stuck_result = $conn->query($stuck_query);
    $reminders_created = 0;
    
    while ($document = $stuck_result->fetch_assoc()) {
        if (create_urgent_reminder($document['document_id'], $document['days_stuck'])) {
            $reminders_created++;
        }
    }
    
    return $reminders_created;
}

/**
 * Check for urgent documents that need immediate attention
 */
function check_urgent_documents() {
    global $conn;
    
    $urgent_query = "SELECT d.document_id, d.title, d.created_at, d.creator_id,
                            DATEDIFF(NOW(), d.created_at) as days_old,
                            u.full_name as creator_name,
                            o.office_name as current_office
                     FROM documents d
                     LEFT JOIN users u ON d.creator_id = u.user_id
                     LEFT JOIN document_workflow dw ON d.document_id = dw.document_id AND dw.status = 'CURRENT'
                     LEFT JOIN offices o ON dw.office_id = o.office_id
                     WHERE d.status = 'pending'
                     AND DATEDIFF(NOW(), d.created_at) >= 5
                     AND d.document_id NOT IN (
                         SELECT DISTINCT document_id FROM notifications 
                         WHERE event_type = 'urgent_reminder' 
                         AND created_at >= DATE_SUB(NOW(), INTERVAL 6 HOURS)
                     )";
    
    $urgent_result = $conn->query($urgent_query);
    if (!$urgent_result) {
        error_log("Error in check_urgent_documents query: " . $conn->error);
        return 0;
    }
    
    $urgent_reminders = 0;
    
    while ($document = $urgent_result->fetch_assoc()) {
        $days_stuck = $document['days_old'];
        $additional_data = [
            'days_stuck' => $days_stuck,
            'current_office' => $document['current_office'] ?? 'Unknown Office',
            'is_urgent' => true
        ];
        
        // Create urgent reminder
        if (create_enhanced_notification($document['document_id'], $document['creator_id'] ?? 0, 'urgent_reminder', $additional_data)) {
            $urgent_reminders++;
        }
        
        // Also notify current office
        if ($document['current_office']) {
            $office_query = "SELECT office_id FROM document_workflow 
                            WHERE document_id = ? AND status = 'CURRENT'";
            $office_stmt = $conn->prepare($office_query);
            $office_stmt->bind_param("i", $document['document_id']);
            $office_stmt->execute();
            $office_result = $office_stmt->get_result();
            
            if ($office_result->num_rows > 0) {
                $office = $office_result->fetch_assoc();
                notify_office_users($office['office_id'], $document['document_id'], 'stuck_document', $additional_data);
            }
        }
    }
    
    return $urgent_reminders;
}

/**
 * Check for documents approaching deadlines
 */
function check_deadline_documents() {
    global $conn;
    
    // Check for documents with deadlines in the next 3 days
    $deadline_query = "SELECT d.document_id, d.title, d.deadline_date,
                              DATEDIFF(d.deadline_date, NOW()) as days_left,
                              u.full_name as creator_name
                       FROM documents d
                       LEFT JOIN users u ON d.creator_id = u.user_id
                       WHERE d.deadline_date IS NOT NULL
                       AND d.deadline_date > NOW()
                       AND DATEDIFF(d.deadline_date, NOW()) <= 3
                       AND d.status != 'completed'
                       AND d.document_id NOT IN (
                           SELECT DISTINCT document_id FROM notifications 
                           WHERE event_type = 'deadline_approaching' 
                           AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                       )";
    
    $deadline_result = $conn->query($deadline_query);
    if (!$deadline_result) {
        error_log("Error in check_deadline_documents query: " . $conn->error);
        return 0;
    }
    
    $deadline_reminders = 0;
    
    while ($document = $deadline_result->fetch_assoc()) {
        $additional_data = [
            'days_left' => $document['days_left'],
            'deadline_date' => $document['deadline_date']
        ];
        
        // Notify document creator
        if (create_enhanced_notification($document['document_id'], $document['creator_id'] ?? 0, 'deadline_approaching', $additional_data)) {
            $deadline_reminders++;
        }
        
        // Also notify current office
        $office_query = "SELECT office_id FROM document_workflow 
                        WHERE document_id = ? AND status = 'CURRENT'";
        $office_stmt = $conn->prepare($office_query);
        $office_stmt->bind_param("i", $document['document_id']);
        $office_stmt->execute();
        $office_result = $office_stmt->get_result();
        
        if ($office_result->num_rows > 0) {
            $office = $office_result->fetch_assoc();
            notify_office_users($office['office_id'], $document['document_id'], 'deadline_approaching', $additional_data);
        }
    }
    
    return $deadline_reminders;
}

/**
 * Move document to next workflow step
 */
function move_to_next_workflow_step($document_id) {
    global $conn;
    
    // Get current step
    $current_sql = "SELECT step_order FROM document_workflow WHERE document_id = ? AND status = 'CURRENT'";
    $current_stmt = $conn->prepare($current_sql);
    $current_stmt->bind_param("i", $document_id);
    $current_stmt->execute();
    $current_result = $current_stmt->get_result();
    
    if ($current_result->num_rows === 0) {
        return false;
    }
    
    $current_step = $current_result->fetch_assoc()['step_order'];
    
    // Get next step
    $next_sql = "SELECT step_order FROM document_workflow 
                 WHERE document_id = ? AND step_order > ? 
                 ORDER BY step_order ASC LIMIT 1";
    $next_stmt = $conn->prepare($next_sql);
    $next_stmt->bind_param("ii", $document_id, $current_step);
    $next_stmt->execute();
    $next_result = $next_stmt->get_result();
    
    if ($next_result->num_rows > 0) {
        $next_step = $next_result->fetch_assoc()['step_order'];
        
        // Update current step to completed
        $update_current_sql = "UPDATE document_workflow SET status = 'COMPLETED' 
                              WHERE document_id = ? AND step_order = ?";
        $update_current_stmt = $conn->prepare($update_current_sql);
        $update_current_stmt->bind_param("ii", $document_id, $current_step);
        $update_current_stmt->execute();
        
        // Update next step to current
        $update_next_sql = "UPDATE document_workflow SET status = 'CURRENT' 
                           WHERE document_id = ? AND step_order = ?";
        $update_next_stmt = $conn->prepare($update_next_sql);
        $update_next_stmt->bind_param("ii", $document_id, $next_step);
        $update_next_stmt->execute();
        
        // Notify next office
        $next_office = get_next_workflow_office($document_id);
        if ($next_office) {
            notify_office_users($next_office, $document_id, 'incoming', [
                'document_title' => 'Document from previous step',
                'from_workflow' => true
            ]);
        }
        
        return true;
    } else {
        // No more steps, mark document as completed
        $complete_sql = "UPDATE documents SET status = 'completed' WHERE document_id = ?";
        $complete_stmt = $conn->prepare($complete_sql);
        $complete_stmt->bind_param("i", $document_id);
        $complete_stmt->execute();
        
        // Notify all workflow users that document is completed
        notify_workflow_users($document_id, 'approved', [
            'reason' => 'Document workflow completed successfully'
        ]);
        
        return true;
    }
}

/**
 * Get next office in workflow
 */
function get_next_workflow_office($document_id) {
    global $conn;
    
    $next_sql = "SELECT office_id FROM document_workflow 
                 WHERE document_id = ? AND status = 'PENDING' 
                 ORDER BY step_order ASC LIMIT 1";
    $next_stmt = $conn->prepare($next_sql);
    $next_stmt->bind_param("i", $document_id);
    $next_stmt->execute();
    $next_result = $next_stmt->get_result();
    
    if ($next_result->num_rows > 0) {
        return $next_result->fetch_assoc()['office_id'];
    }
    
    return null;
}

/**
 * Get notification statistics for a user
 */
function get_notification_stats($user_id) {
    global $conn;
    
    $stats_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
                        SUM(CASE WHEN priority = 'critical' AND is_read = 0 THEN 1 ELSE 0 END) as critical_unread,
                        SUM(CASE WHEN priority = 'high' AND is_read = 0 THEN 1 ELSE 0 END) as high_priority_unread
                    FROM notifications 
                    WHERE user_id = ?";
    
    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->bind_param("i", $user_id);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    
    return $stats_result->fetch_assoc();
}
?>
