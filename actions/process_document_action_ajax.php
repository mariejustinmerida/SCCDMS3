<?php
/**
 * AJAX endpoint for processing document actions (approve, reject, hold, request_revision)
 * Returns JSON response for use with JavaScript
 */

// Start output buffering to catch any stray output
ob_start();

// Disable error display but keep error logging
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Register error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_WARNING])) {
        ob_clean();
        header('Content-Type: application/json');
        
        // Log the error for debugging
        $errorMsg = "Fatal error in process_document_action_ajax.php: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'];
        error_log($errorMsg);
        
        // Also log to a file for easier debugging
        $logFile = __DIR__ . '/../logs/ajax_errors.log';
        if (!file_exists(dirname($logFile))) {
            @mkdir(dirname($logFile), 0755, true);
        }
        @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $errorMsg . "\n", FILE_APPEND);
        
        echo json_encode([
            'success' => false,
            'message' => 'An internal server error occurred. Error: ' . $error['message'],
            'error_type' => 'fatal_error',
            'error_file' => basename($error['file']),
            'error_line' => $error['line']
        ]);
        exit;
    }
});

session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/logging.php';
require_once '../includes/document_workflow.php';
require_once '../includes/enhanced_notification_system.php';

// Clear any output that may have been generated
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

$document_id = isset($input['document_id']) ? intval($input['document_id']) : 0;
$action = isset($input['action']) ? $input['action'] : '';
$comments = isset($input['comments']) ? trim($input['comments']) : '';
$user_id = $_SESSION['user_id'];
$office_id = isset($_SESSION['office_id']) ? (int)$_SESSION['office_id'] : 0;

if ($document_id === 0 || empty($action)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get document details
$doc_query = "SELECT d.*, dt.type_name, u.username as creator_name 
              FROM documents d 
              JOIN document_types dt ON d.type_id = dt.type_id 
              JOIN users u ON d.creator_id = u.user_id 
              WHERE d.document_id = ?";
$doc_stmt = $conn->prepare($doc_query);
$doc_stmt->bind_param("i", $document_id);
$doc_stmt->execute();
$doc_result = $doc_stmt->get_result();

if ($doc_result->num_rows === 0) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Document not found']);
    exit;
}

$document = $doc_result->fetch_assoc();

// Get workflow information
$workflow_query = "SELECT dw.*, o.office_name 
                  FROM document_workflow dw 
                  JOIN offices o ON dw.office_id = o.office_id 
                  WHERE dw.document_id = ? 
                  ORDER BY dw.step_order ASC";
$workflow_stmt = $conn->prepare($workflow_query);
$workflow_stmt->bind_param("i", $document_id);
$workflow_stmt->execute();
$workflow_result = $workflow_stmt->get_result();

$workflow_steps = [];
while ($step = $workflow_result->fetch_assoc()) {
    $workflow_steps[] = $step;
}

// Check if this office has a current step for this document
$current_step = null;
foreach ($workflow_steps as $step) {
    // Compare office_id as integers to avoid type mismatch issues
    if ((int)$step['office_id'] === $office_id && strtoupper($step['status']) === 'CURRENT') {
        $current_step = $step;
        break;
    }
}

if (!$current_step) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'No current workflow step found for this document and office']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    $comments_escaped = $conn->real_escape_string($comments);
    
    // Handle APPROVE action
    if ($action === 'approve') {
        // Update workflow status to COMPLETED
        $update_workflow = "UPDATE document_workflow SET 
                           status = 'COMPLETED', 
                           comments = ?, 
                           completed_at = NOW() 
                           WHERE document_id = ? AND office_id = ? AND status = 'CURRENT'";
        $update_stmt = $conn->prepare($update_workflow);
        
        if (!$update_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $update_stmt->bind_param("sii", $comments, $document_id, $office_id);
        $update_stmt->execute();
        
        if ($update_stmt->affected_rows === 0) {
            throw new Exception("Failed to update workflow status. It might have been already processed or you don't have permission.");
        }
        
        // Check if this is the final step
        $last_step_query = "SELECT MAX(step_order) as max_step FROM document_workflow WHERE document_id = ?";
        $last_step_stmt = $conn->prepare($last_step_query);
        $last_step_stmt->bind_param("i", $document_id);
        $last_step_stmt->execute();
        $last_step_result = $last_step_stmt->get_result()->fetch_assoc();
        $is_final_step = ($current_step['step_order'] == $last_step_result['max_step']);

        $final_approval = false;
        // If this was the final step, mark the document as 'approved'.
        if ($is_final_step) {
            $update_doc = "UPDATE documents SET status = 'approved', updated_at = NOW() WHERE document_id = ?";
            $doc_update_stmt = $conn->prepare($update_doc);
            $doc_update_stmt->bind_param("i", $document_id);
            $doc_update_stmt->execute();
            $final_approval = true;
        } else {
            // Otherwise, find the next step in the workflow and set it to 'CURRENT'.
            $next_step_query = "UPDATE document_workflow SET status = 'CURRENT' 
                                WHERE document_id = ? AND status = 'PENDING' 
                                ORDER BY step_order ASC LIMIT 1";
            $next_stmt = $conn->prepare($next_step_query);
            $next_stmt->bind_param("i", $document_id);
            $next_stmt->execute();
        }
        
        // Generate verification code (6 digits)
        $verification_code = sprintf('%06d', rand(0, 999999));
        
        // Store verification details if table exists
        $create_table_sql = "CREATE TABLE IF NOT EXISTS simple_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT NOT NULL,
            user_id INT NOT NULL,
            office_id VARCHAR(50) NOT NULL,
            verification_code VARCHAR(10) NOT NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (document_id) REFERENCES documents(document_id)
        )";
        $conn->query($create_table_sql);
        
        $insert_verification = "INSERT INTO simple_verifications 
                              (document_id, user_id, office_id, verification_code, created_at) 
                              VALUES (?, ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_verification);
        
        if ($insert_stmt) {
            $user_id_int = intval($user_id);
            $insert_stmt->bind_param("iiss", $document_id, $user_id_int, $office_id, $verification_code);
            $insert_stmt->execute();
        }
        
        // Log this approval action
        if (function_exists('log_user_action')) {
            log_user_action(
                intval($user_id), 
                'approve_document', 
                "Approved document: " . $document['title'] . " (Verification Code: $verification_code)", 
                $document_id, 
                null, 
                $office_id
            );
        }
        
        // Create enhanced notification for document creator (wrap in try-catch to prevent errors from breaking the process)
        try {
            if (function_exists('notify_document_creator')) {
                notify_document_creator($document_id, 'approved', [
                    'reason' => $comments ?: 'Document has been approved and is ready for the next step',
                    'approved_by' => intval($user_id),
                    'verification_code' => $verification_code
                ]);
            }
        } catch (Exception $e) {
            error_log("Notification error (approve): " . $e->getMessage());
        }
        
        // Notify all users involved in the workflow (wrap in try-catch to prevent errors from breaking the process)
        try {
            if (function_exists('notify_workflow_users')) {
                notify_workflow_users($document_id, 'approved', [
                    'reason' => $comments ?: 'Document has been approved and is ready for the next step',
                    'approved_by' => intval($user_id)
                ]);
            }
        } catch (Exception $e) {
            error_log("Notification error (approve workflow): " . $e->getMessage());
        }
        
        $conn->commit();
        ob_clean();
        echo json_encode([
            'success' => true, 
            'message' => 'Document approved successfully!',
            'action' => 'approve',
            'final_approval' => $final_approval
        ]);
        exit;
    }
    
    // Handle REJECT action
    else if ($action === 'reject') {
        if (empty($comments)) {
            throw new Exception("Please provide a reason for rejection");
        }
        
        // Update workflow status to REJECTED
        $update_workflow = "UPDATE document_workflow SET 
                           status = 'REJECTED', 
                           comments = ?, 
                           completed_at = NOW() 
                           WHERE document_id = ? AND office_id = ? AND status = 'CURRENT'";
        $update_stmt = $conn->prepare($update_workflow);
        
        if (!$update_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $update_stmt->bind_param("sii", $comments, $document_id, $office_id);
        $update_stmt->execute();
        
        if ($update_stmt->affected_rows === 0) {
            throw new Exception("Failed to update workflow status. It might have been already processed or you don't have permission.");
        }
        
        // Update document status to rejected
        $update_doc = "UPDATE documents SET status = 'rejected', updated_at = NOW() WHERE document_id = ?";
        $doc_update_stmt = $conn->prepare($update_doc);
        $doc_update_stmt->bind_param("i", $document_id);
        $doc_update_stmt->execute();
        
        // Set all remaining workflow steps to CANCELLED
        $cancel_remaining = "UPDATE document_workflow SET status = 'CANCELLED' 
                            WHERE document_id = ? AND status = 'PENDING'";
        $cancel_stmt = $conn->prepare($cancel_remaining);
        $cancel_stmt->bind_param("i", $document_id);
        $cancel_stmt->execute();
        
        if (function_exists('log_user_action')) {
            log_user_action(
                intval($user_id), 
                'reject_document', 
                "Rejected document: " . $document['title'] . " - Reason: " . substr($comments, 0, 50), 
                $document_id, 
                null, 
                $office_id
            );
        }
        
        // Create enhanced notification for document creator (wrap in try-catch to prevent errors from breaking the process)
        try {
            if (function_exists('notify_document_creator')) {
                notify_document_creator($document_id, 'rejected', [
                    'reason' => $comments,
                    'rejected_by' => intval($user_id)
                ]);
            }
        } catch (Exception $e) {
            error_log("Notification error (reject): " . $e->getMessage());
        }
        
        // Notify all users involved in the workflow (wrap in try-catch to prevent errors from breaking the process)
        try {
            if (function_exists('notify_workflow_users')) {
                notify_workflow_users($document_id, 'rejected', [
                    'reason' => $comments,
                    'rejected_by' => intval($user_id)
                ]);
            }
        } catch (Exception $e) {
            error_log("Notification error (reject workflow): " . $e->getMessage());
        }
        
        $conn->commit();
        ob_clean();
        echo json_encode([
            'success' => true, 
            'message' => 'Document rejected successfully.',
            'action' => 'reject'
        ]);
        exit;
    }
    
    // Handle HOLD action
    else if ($action === 'hold') {
        if (empty($comments)) {
            throw new Exception("Please provide a reason for putting the document on hold");
        }
        
        // Get current office BEFORE updating status (since we'll change status to ON_HOLD)
        $current_office_id = $office_id; // Use the office_id from current_step
        
        // Update workflow status to ON_HOLD
        $update_workflow = "UPDATE document_workflow SET 
                           status = 'ON_HOLD', 
                           comments = ? 
                           WHERE document_id = ? AND office_id = ? AND status = 'CURRENT'";
        $update_stmt = $conn->prepare($update_workflow);
        
        if (!$update_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $update_stmt->bind_param("sii", $comments, $document_id, $office_id);
        $update_stmt->execute();
        
        if ($update_stmt->affected_rows === 0) {
            throw new Exception("Failed to update workflow status. It might have been already processed or you don't have permission.");
        }
        
        // Update document status to on_hold
        $update_doc = "UPDATE documents SET status = 'on_hold', updated_at = NOW() WHERE document_id = ?";
        $doc_update_stmt = $conn->prepare($update_doc);
        $doc_update_stmt->bind_param("i", $document_id);
        $doc_update_stmt->execute();
        
        // Log hold action in document_logs
        try {
            $log_sql = "INSERT INTO document_logs (document_id, user_id, action, details, created_at) VALUES (?, ?, 'hold', ?, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            if ($log_stmt) {
                $log_details = "Document put on hold. Reason: " . $comments;
                $user_id_int = (int)$user_id; // Must be a variable, not a function call
                $log_stmt->bind_param("iis", $document_id, $user_id_int, $log_details);
                $log_stmt->execute();
            }
        } catch (Exception $e) {
            error_log("Error logging hold action: " . $e->getMessage());
            // Don't fail the transaction for log errors
        }
        
        if (function_exists('log_user_action')) {
            try {
                log_user_action(
                    intval($user_id), 
                    'hold', 
                    "Document put on hold: " . $document['title'] . " - Reason: " . substr($comments, 0, 50), 
                    $document_id, 
                    $comments,
                    $office_id
                );
            } catch (Exception $e) {
                error_log("Error in log_user_action (hold): " . $e->getMessage());
            }
        }
        
        // Create enhanced notification for document creator (wrap in try-catch to prevent errors from breaking the process)
        try {
            if (function_exists('notify_document_creator')) {
                notify_document_creator($document_id, 'on_hold', [
                    'reason' => $comments,
                    'held_by' => intval($user_id)
                ]);
            }
        } catch (Throwable $e) {
            // Log notification error but don't fail the transaction
            error_log("Notification error (hold): " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        }
        
        // Notify current office (use the office_id we saved before updating status)
        try {
            if ($current_office_id && $current_office_id > 0 && function_exists('notify_office_users')) {
                // Ensure office_id is integer
                $current_office_id = (int)$current_office_id;
                notify_office_users($current_office_id, $document_id, 'on_hold', [
                    'reason' => $comments,
                    'held_by' => intval($user_id)
                ]);
            }
        } catch (Throwable $e) {
            // Log notification error but don't fail the transaction
            error_log("Notification error (hold office): " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        }
        
        // Commit transaction - this is critical, must succeed
        if (!$conn->commit()) {
            throw new Exception("Failed to commit transaction: " . $conn->error);
        }
        
        ob_clean();
        echo json_encode([
            'success' => true, 
            'message' => 'Document has been put on hold.',
            'action' => 'hold'
        ]);
        exit;
    }
    
    // Handle REQUEST REVISION action
    else if ($action === 'request_revision') {
        if (empty($comments)) {
            throw new Exception("Please provide a reason for requesting revision");
        }

        // Update workflow status to REVISION_REQUESTED
        $update_workflow = "UPDATE document_workflow SET 
                           status = 'REVISION_REQUESTED', 
                           comments = ? 
                           WHERE document_id = ? AND office_id = ? AND status = 'CURRENT'";
        $update_stmt = $conn->prepare($update_workflow);
        
        if (!$update_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $update_stmt->bind_param("sii", $comments, $document_id, $office_id);
        $update_stmt->execute();
        
        if ($update_stmt->affected_rows === 0) {
            throw new Exception("Failed to update workflow status. It might have been already processed or you don't have permission.");
        }

        // Update document status and set the requesting office ID
        $update_doc_sql = "UPDATE documents 
                           SET status = 'revision', 
                               revision_requesting_office_id = ?,
                               updated_at = NOW()
                           WHERE document_id = ?";
        $update_doc_stmt = $conn->prepare($update_doc_sql);
        $update_doc_stmt->bind_param("ii", $office_id, $document_id);
        if (!$update_doc_stmt->execute()) {
            throw new Exception("Failed to update document for revision: " . $update_doc_stmt->error);
        }

        // Log the action
        if (function_exists('log_user_action')) {
            log_user_action(
                intval($user_id), 
                'request_revision', 
                "Requested revision for document: " . $document['title'] . " - Reason: " . substr($comments, 0, 50),
                $document_id, 
                $comments,
                $office_id
            );
        }
        
        // Create enhanced notification for document creator (wrap in try-catch to prevent errors from breaking the process)
        try {
            if (function_exists('notify_document_creator')) {
                notify_document_creator($document_id, 'revision_requested', [
                    'reason' => $comments,
                    'requested_by' => intval($user_id)
                ]);
            }
        } catch (Exception $e) {
            error_log("Notification error (revision): " . $e->getMessage());
        }

        $conn->commit();
        ob_clean();
        echo json_encode([
            'success' => true, 
            'message' => 'Revision has been requested successfully.',
            'action' => 'request_revision'
        ]);
        exit;
    }
    
    else {
        throw new Exception("Invalid action specified");
    }
    
} catch (Throwable $e) {
    // Rollback transaction on error (mysqli doesn't have in_transaction property, so just try rollback)
    if (isset($conn)) {
        @$conn->rollback();
    }
    ob_clean();
    
    // Log the full error for debugging
    error_log("Error in process_document_action_ajax.php: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine() . " | Trace: " . $e->getTraceAsString());
    
    // Also log to file
    $logFile = __DIR__ . '/../logs/ajax_errors.log';
    if (!file_exists(dirname($logFile))) {
        @mkdir(dirname($logFile), 0755, true);
    }
    @file_put_contents($logFile, date('Y-m-d H:i:s') . " - Exception: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine() . "\n", FILE_APPEND);
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error_type' => get_class($e),
        'error_file' => basename($e->getFile()),
        'error_line' => $e->getLine()
    ]);
    exit;
}

/**
 * Get current office in workflow (helper function if not exists)
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
