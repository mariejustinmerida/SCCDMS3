<?php
/**
 * Notification Reminder Cron Job
 * 
 * This script runs periodically to check for stuck documents and send AI-powered reminders
 * Should be run every hour via cron job
 */

// Set time limit for long-running script
set_time_limit(300); // 5 minutes

// Include required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/enhanced_notification_system.php';

// Log file for cron job
$log_file = __DIR__ . '/../logs/notification_cron.log';

/**
 * Log message to file
 */
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

try {
    log_message("Starting notification reminder cron job");
    
    // Check for stuck documents
    $stuck_reminders = check_stuck_documents();
    log_message("Created $stuck_reminders stuck document reminders");
    
    // Check for urgent documents
    $urgent_reminders = check_urgent_documents();
    log_message("Created $urgent_reminders urgent document reminders");
    
    // Check for deadline documents
    $deadline_reminders = check_deadline_documents();
    log_message("Created $deadline_reminders deadline reminders");
    
    // Clean up old notifications (older than 30 days)
    $cleanup_sql = "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $cleanup_result = $conn->query($cleanup_sql);
    $deleted_count = $conn->affected_rows;
    log_message("Cleaned up $deleted_count old notifications");
    
    // Update document urgency based on age
    update_document_urgency();
    
    log_message("Notification reminder cron job completed successfully");
    
} catch (Exception $e) {
    log_message("Error in notification reminder cron job: " . $e->getMessage());
}

/**
 * Update document urgency based on age and other factors
 */
function update_document_urgency() {
    global $conn;
    
    // Mark documents as urgent if they've been pending for more than 5 days
    $urgency_sql = "UPDATE documents 
                    SET is_urgent = 1 
                    WHERE status = 'pending' 
                    AND DATEDIFF(NOW(), created_at) >= 5 
                    AND is_urgent = 0";
    
    $urgency_result = $conn->query($urgency_sql);
    $updated_count = $conn->affected_rows;
    
    if ($updated_count > 0) {
        log_message("Marked $updated_count documents as urgent");
    }
    
    // Create urgent notifications for newly marked urgent documents
    $urgent_docs_sql = "SELECT d.document_id, d.creator_id, d.title
                        FROM documents d
                        WHERE d.is_urgent = 1 
                        AND d.status = 'pending'
                        AND d.document_id NOT IN (
                            SELECT DISTINCT document_id FROM notifications 
                            WHERE event_type = 'urgent_reminder' 
                            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                        )";
    
    $urgent_docs_result = $conn->query($urgent_docs_sql);
    $urgent_notifications = 0;
    
    while ($doc = $urgent_docs_result->fetch_assoc()) {
        if (create_enhanced_notification($doc['document_id'], $doc['creator_id'], 'urgent_reminder', [
            'days_stuck' => 5,
            'auto_marked' => true
        ])) {
            $urgent_notifications++;
        }
    }
    
    if ($urgent_notifications > 0) {
        log_message("Created $urgent_notifications urgent notifications for newly marked documents");
    }
}

/**
 * Check for documents that need workflow advancement
 */
function check_workflow_advancement() {
    global $conn;
    
    // Find documents that have been approved but not moved to next step
    $workflow_sql = "SELECT d.document_id, d.title, dw.office_id as current_office
                     FROM documents d
                     JOIN document_workflow dw ON d.document_id = dw.document_id
                     WHERE d.status = 'approved'
                     AND dw.status = 'CURRENT'
                     AND d.updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    
    $workflow_result = $conn->query($workflow_sql);
    $workflow_advances = 0;
    
    while ($doc = $workflow_result->fetch_assoc()) {
        // Move to next step
        if (move_to_next_workflow_step($doc['document_id'])) {
            $workflow_advances++;
            
            // Notify next office
            $next_office = get_next_workflow_office($doc['document_id']);
            if ($next_office) {
                notify_office_users($next_office, $doc['document_id'], 'incoming', [
                    'document_title' => $doc['title'],
                    'from_office' => $doc['current_office']
                ]);
            }
        }
    }
    
    if ($workflow_advances > 0) {
        log_message("Advanced $workflow_advances documents in workflow");
    }
    
    return $workflow_advances;
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
        
        return true;
    } else {
        // No more steps, mark document as completed
        $complete_sql = "UPDATE documents SET status = 'completed' WHERE document_id = ?";
        $complete_stmt = $conn->prepare($complete_sql);
        $complete_stmt->bind_param("i", $document_id);
        $complete_stmt->execute();
        
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
?>
