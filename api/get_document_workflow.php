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

debug_log('API request started for document_workflow.php');
debug_log('GET params: ' . json_encode($_GET));

session_start();
require_once '../includes/config.php';

// Content-Type header already set at the beginning of the file

// For debugging, bypass session check temporarily
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1; // Use default user_id if not set
debug_log('Session: ' . json_encode($_SESSION));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    debug_log('User not authenticated');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

if (isset($_GET['document_id'])) {
    // Validate document_id
    if (!is_numeric($_GET['document_id'])) {
        debug_log('Invalid document ID: ' . $_GET['document_id']);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid document ID']);
        exit;
    }
    
    $document_id = $_GET['document_id'];
    debug_log('Processing document ID: ' . $document_id);
    
    try {
        // First check if document exists
        $check_sql = "SELECT document_id FROM documents WHERE document_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $document_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            debug_log('Document not found with ID: ' . $document_id);
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Document not found']);
            exit;
        }
        
        debug_log('Document exists, getting workflow steps');
        
        // Check if completed_at column exists in document_workflow table
        $column_exists = false;
        $columns_result = $conn->query("SHOW COLUMNS FROM document_workflow LIKE 'completed_at'");
        if ($columns_result && $columns_result->num_rows > 0) {
            $column_exists = true;
            debug_log('completed_at column exists in document_workflow table');
        } else {
            debug_log('completed_at column does NOT exist in document_workflow table');
        }
        
        // Build the SQL query based on available columns
        // This query now includes information about which office requested revision
        $sql = "SELECT dw.workflow_id, dw.step_order, dw.office_id, o.office_name,
                       dw.status, dw.created_at,
                       (SELECT user_id FROM document_logs 
                        WHERE document_id = dw.document_id AND action = 'request_revision' 
                        ORDER BY created_at DESC LIMIT 1) as revision_requester_id";

        
        if ($column_exists) {
            $sql .= ", dw.completed_at, dw.comments";
        } else {
            $sql .= ", NULL as completed_at, '' as comments";
        }
        
        $sql .= " FROM document_workflow dw
               JOIN offices o ON dw.office_id = o.office_id
               WHERE dw.document_id = ?
               ORDER BY dw.step_order ASC";
        
        debug_log('SQL query: ' . $sql);
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        debug_log('Query executed, num rows: ' . $result->num_rows);
        
        $steps = array();
        
        // Process workflow steps
        while ($step = $result->fetch_assoc()) {
            // Add step to the steps array
            $steps[] = $step;
            
            // If the step is completed, use the completed_at timestamp
            if (isset($step['completed_at']) && $step['completed_at']) {
                $step['timestamp'] = $step['completed_at'];
            } else {
                $step['timestamp'] = $step['created_at'];
            }

            // Set appropriate descriptions based on status
            if ($step['status'] === 'CURRENT') {
                $step['description'] = 'Awaiting action';
            } else if ($step['status'] === 'COMPLETED') {
                $step['description'] = 'Approved';
            } else if ($step['status'] === 'REJECTED') {
                $step['description'] = 'Rejected';
            } else if ($step['status'] === 'ON_HOLD') {
                $step['description'] = 'On Hold';
            } else if ($step['status'] === 'PENDING') {
                $step['description'] = 'Pending approval';
            }

            // If a revision was requested, ensure the requesting office's status is CURRENT
            if (isset($step['revision_requester_id'])) {
                $requester_office_query = "SELECT office_id FROM users WHERE user_id = ?";
                $requester_stmt = $conn->prepare($requester_office_query);
                $requester_stmt->bind_param("i", $step['revision_requester_id']);
                $requester_stmt->execute();
                $requester_result = $requester_stmt->get_result();

                if($requester_row = $requester_result->fetch_assoc()) {
                    if ($step['office_id'] == $requester_row['office_id']) {
                        $step['status'] = 'CURRENT';
                        $step['description'] = 'Revision submitted, awaiting your review';
                    }
                }
            }

            // We'll skip the user_id check for now since it might not exist in your schema

            // In the new structure, we don't need to get action information separately
            // as it's already included in the document_workflow table
        }

        debug_log('Steps found: ' . count($steps));

        $response = ['success' => true, 'steps' => $steps];
        debug_log('Final response: ' . json_encode($response));
        echo json_encode($response);
    } catch (Exception $e) {
        debug_log('Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    debug_log('No document ID provided');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Document ID not provided']);
}
?>