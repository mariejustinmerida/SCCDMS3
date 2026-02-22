<?php
/**
 * Update Document Status API
 * 
 * This endpoint handles document status changes and triggers appropriate notifications
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/enhanced_notification_system.php';

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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$document_id = $input['document_id'] ?? null;
$new_status = $input['status'] ?? null;
$reason = $input['reason'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$document_id || !$new_status) {
    echo json_encode([
        'success' => false,
        'error' => 'Document ID and status are required'
    ]);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Get current document details
    $doc_query = "SELECT d.*, u.full_name as creator_name, o.office_name as creator_office
                  FROM documents d
                  LEFT JOIN users u ON d.creator_id = u.user_id
                  LEFT JOIN offices o ON u.office_id = o.office_id
                  WHERE d.document_id = ?";
    $doc_stmt = $conn->prepare($doc_query);
    $doc_stmt->bind_param("i", $document_id);
    $doc_stmt->execute();
    $doc_result = $doc_stmt->get_result();
    
    if ($doc_result->num_rows === 0) {
        throw new Exception('Document not found');
    }
    
    $document = $doc_result->fetch_assoc();
    $old_status = $document['status'];
    
    // Update document status
    $update_sql = "UPDATE documents SET status = ?, updated_at = NOW() WHERE document_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $document_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update document status');
    }
    
    // Log the status change
    $log_sql = "INSERT INTO document_logs (document_id, user_id, action, details, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    $log_details = "Status changed from '$old_status' to '$new_status'" . ($reason ? ". Reason: $reason" : "");
    $log_stmt->bind_param("iiss", $document_id, $user_id, $new_status, $log_details);
    $log_stmt->execute();
    
    // Handle workflow status changes
    if (in_array($new_status, ['approved', 'rejected', 'on_hold', 'revision_requested'])) {
        // Update workflow status
        $workflow_sql = "UPDATE document_workflow SET status = ? WHERE document_id = ? AND status = 'CURRENT'";
        $workflow_stmt = $conn->prepare($workflow_sql);
        $workflow_stmt->bind_param("si", $new_status, $document_id);
        $workflow_stmt->execute();
        
        // Move to next step if approved
        if ($new_status === 'approved') {
            move_to_next_workflow_step($document_id);
        }
    }
    
    // Create appropriate notifications based on status change
    $additional_data = ['reason' => $reason];
    
    switch ($new_status) {
        case 'approved':
            // Notify document creator
            notify_document_creator($document_id, 'approved', $additional_data);
            
            // Notify next office in workflow if any
            $next_office = get_next_workflow_office($document_id);
            if ($next_office) {
                notify_office_users($next_office, $document_id, 'incoming', $additional_data);
            }
            break;
            
        case 'rejected':
            // Notify document creator
            notify_document_creator($document_id, 'rejected', $additional_data);
            
            // Notify all workflow users
            notify_workflow_users($document_id, 'rejected', $additional_data);
            break;
            
        case 'on_hold':
            // Notify document creator
            notify_document_creator($document_id, 'on_hold', $additional_data);
            
            // Notify current office
            $current_office = get_current_workflow_office($document_id);
            if ($current_office) {
                notify_office_users($current_office, $document_id, 'on_hold', $additional_data);
            }
            break;
            
        case 'revision_requested':
            // Notify document creator
            notify_document_creator($document_id, 'revision_requested', $additional_data);
            break;
            
        case 'completed':
            // Notify all workflow users
            notify_workflow_users($document_id, 'approved', $additional_data);
            break;
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Document status updated successfully',
        'data' => [
            'document_id' => $document_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'notifications_sent' => true
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
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