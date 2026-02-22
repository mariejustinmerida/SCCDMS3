<?php
// Disable direct error output to prevent HTML in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set content type to JSON before any output
header('Content-Type: application/json');

// Create a log file for debugging
function debug_log($message) {
    $log_file = '../logs/api_debug.log';
    $log_dir = dirname($log_file);
    
    // Create logs directory if it doesn't exist
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    // Add timestamp to message
    $message = date('[Y-m-d H:i:s] ') . $message . "\n";
    
    // Append to log file
    file_put_contents($log_file, $message, FILE_APPEND);
}

debug_log('API request started for get_workflow.php');
debug_log('GET params: ' . json_encode($_GET));

session_start();
require_once '../includes/config.php';

// Content-Type header already set at the beginning of the file

// For debugging, bypass session check temporarily
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;  // Use default user_id if not set
debug_log('Session: ' . json_encode($_SESSION));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    debug_log('User not authenticated');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

if (isset($_GET['type_id'])) {
    // Validate type_id
    if (!is_numeric($_GET['type_id'])) {
        debug_log('Invalid document type ID');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid document type ID']);
        exit;
    }
    
    $type_id = $_GET['type_id'];
    
    try {
        // Get workflow steps for document type
        $sql = "SELECT ws.*, o.office_name 
               FROM workflow_steps ws
               JOIN offices o ON ws.office_id = o.office_id
               WHERE ws.type_id = ?
               ORDER BY ws.step_order ASC";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param("i", $type_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $steps = array();
        
        while ($row = $result->fetch_assoc()) {
            $steps[] = $row;
        }
        
        echo json_encode(['success' => true, 'steps' => $steps]);
    } catch (Exception $e) {
        debug_log('Database error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} elseif (isset($_GET['document_id'])) {
    // Validate document_id
    if (!is_numeric($_GET['document_id'])) {
        debug_log('Invalid document ID');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid document ID']);
        exit;
    }
    
    // Get document tracking info
    $document_id = $_GET['document_id'];
    $user_id = $_SESSION['user_id'];
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
    $office_id = isset($_SESSION['office_id']) ? $_SESSION['office_id'] : 0;
    
    try {
        // Check if document exists first
        $check_sql = "SELECT document_id FROM documents WHERE document_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $check_stmt->bind_param("i", $document_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            debug_log('Document not found');
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Document not found']);
            exit;
        }
        
        // Get document details
        $doc_sql = "SELECT d.*, dt.type_name, u.username as creator_name 
                  FROM documents d
                  LEFT JOIN document_types dt ON d.type_id = dt.type_id
                  LEFT JOIN users u ON d.creator_id = u.user_id
                  WHERE d.document_id = ?";
        
        $doc_stmt = $conn->prepare($doc_sql);
        if (!$doc_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $doc_stmt->bind_param("i", $document_id);
        $doc_stmt->execute();
        $doc_result = $doc_stmt->get_result();
        
        if ($doc_result->num_rows > 0) {
            $document = $doc_result->fetch_assoc();
            
            // Format the status for better display
            if (empty($document['status'])) {
                $document['status'] = 'Pending';
            } else {
                $document['status'] = ucfirst($document['status']);
            }
            
            // Check if all workflow steps are completed and if there are any pending steps
            $workflow_status_sql = "SELECT 
                                    COUNT(*) as total, 
                                    SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed,
                                    SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                                    SUM(CASE WHEN status = 'CURRENT' THEN 1 ELSE 0 END) as current
                                FROM document_workflow 
                                WHERE document_id = ?";
            $workflow_status_stmt = $conn->prepare($workflow_status_sql);
            if ($workflow_status_stmt) {
                $workflow_status_stmt->bind_param("i", $document_id);
                $workflow_status_stmt->execute();
                $workflow_status_result = $workflow_status_stmt->get_result();
                
                if ($workflow_status_result && $workflow_status_result->num_rows > 0) {
                    $counts = $workflow_status_result->fetch_assoc();
                    debug_log('Workflow status counts for document ID ' . $document_id . ': ' . json_encode($counts));
                    
                    // Only mark as approved if all steps are completed AND there are no pending or current steps
                    if ($counts['total'] > 0 && 
                        $counts['completed'] == $counts['total'] && 
                        $counts['pending'] == 0 && 
                        $counts['current'] == 0 && 
                        strtolower($document['status']) == 'pending') {
                        
                        $document['status'] = 'Approved';
                        
                        // Update the document status in the database
                        $update_status_sql = "UPDATE documents SET status = 'approved', updated_at = NOW() WHERE document_id = ?";
                        $update_status_stmt = $conn->prepare($update_status_sql);
                        if ($update_status_stmt) {
                            $update_status_stmt->bind_param("i", $document_id);
                            $update_status_stmt->execute();
                            debug_log('Updated document status from pending to approved for document ID: ' . $document_id);
                        }
                    } else {
                        // If there are still pending or current steps, ensure the status is pending
                        if (($counts['pending'] > 0 || $counts['current'] > 0) && strtolower($document['status']) == 'approved') {
                            $document['status'] = 'Pending';
                            
                            // Update the document status in the database to ensure consistency
                            $update_status_sql = "UPDATE documents SET status = 'pending', updated_at = NOW() WHERE document_id = ?";
                            $update_status_stmt = $conn->prepare($update_status_sql);
                            if ($update_status_stmt) {
                                $update_status_stmt->bind_param("i", $document_id);
                                $update_status_stmt->execute();
                                debug_log('Updated document status from approved to pending for document ID: ' . $document_id);
                            }
                        }
                    }
                }
            }
            
            // Get current workflow step information
            $current_step_sql = "SELECT dw.workflow_id, dw.step_order, o.office_name, dw.status
                               FROM document_workflow dw
                               JOIN offices o ON dw.office_id = o.office_id
                               WHERE dw.document_id = ? AND (dw.status = 'current' OR dw.status = 'CURRENT')";
            
            $current_stmt = $conn->prepare($current_step_sql);
            if ($current_stmt) {
                $current_stmt->bind_param("i", $document_id);
                $current_stmt->execute();
                $current_result = $current_stmt->get_result();
                
                if ($current_result->num_rows > 0) {
                    $current_step = $current_result->fetch_assoc();
                    $document['current_step_info'] = $current_step;
                }
            }
            
            // Get workflow history
            $history_sql = "SELECT dw.*, o.office_name 
                          FROM document_workflow dw
                          JOIN offices o ON dw.office_id = o.office_id
                          WHERE dw.document_id = ?
                          ORDER BY dw.step_order ASC";
            
            $history_stmt = $conn->prepare($history_sql);
            if (!$history_stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $history_stmt->bind_param("i", $document_id);
            $history_stmt->execute();
            $history_result = $history_stmt->get_result();
            
            $history = array();
            
            while ($step = $history_result->fetch_assoc()) {
                $history[] = $step;
            }
            
            $document['workflow_history'] = $history;
            
            // Return document with workflow info
            echo json_encode(['success' => true, 'document' => $document]);
        } else {
            debug_log('Document details not found');
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Document details not found']);
        }
    } catch (Exception $e) {
        debug_log('Database error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    debug_log('Missing parameters');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
}
?>
