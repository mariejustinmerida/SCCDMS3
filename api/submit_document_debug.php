<?php
/**
 * Submit Document API with Enhanced Debugging
 */

// Ensure clean output
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug log function
function debug_log($message, $type = 'INFO') {
    $log_file = '../logs/debug_submit.log';
    $log_dir = dirname($log_file);
    
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $message = date('[Y-m-d H:i:s]') . " [$type] " . $message . "\n";
    file_put_contents($log_file, $message, FILE_APPEND);
}

debug_log("=== START SUBMIT DOCUMENT DEBUG ===");
debug_log("POST data: " . json_encode($_POST));
debug_log("FILES data: " . json_encode($_FILES));
debug_log("SESSION data: " . json_encode($_SESSION));

try {
    // Include database configuration
    require_once __DIR__ . '/../includes/config.php';
    debug_log("Database connection established");
    
    // Check user authentication
    if (!isset($_SESSION['user_id'])) {
        debug_log("Authentication failed - no user_id in session", "ERROR");
        echo json_encode([
            'success' => false,
            'error' => 'User not logged in'
        ]);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $office_id = $_SESSION['office_id'] ?? 1;
    
    debug_log("User authenticated: $user_id, Office: $office_id");
    
    // Validate required fields
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $type_id = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
    $google_doc_id = isset($_POST['google_doc_id']) ? trim($_POST['google_doc_id']) : '';
    
    if (empty($title)) {
        debug_log("Validation failed - missing title", "ERROR");
        echo json_encode([
            'success' => false,
            'error' => 'Document title is required'
        ]);
        exit();
    }
    
    if ($type_id <= 0) {
        debug_log("Validation failed - invalid type_id: $type_id", "ERROR");
        echo json_encode([
            'success' => false,
            'error' => 'Document type is required'
        ]);
        exit();
    }
    
    if (empty($google_doc_id)) {
        debug_log("Validation failed - missing google_doc_id", "ERROR");
        echo json_encode([
            'success' => false,
            'error' => 'Google Doc ID is required'
        ]);
        exit();
    }
    
    debug_log("Input validation passed");
    
    // Begin transaction
    $conn->begin_transaction();
    debug_log("Transaction started");
    
    // Simple document insert without Google Docs API
    try {
        // Insert document
        $doc_sql = "INSERT INTO documents (title, type_id, creator_id, status, google_doc_id, created_at) 
                  VALUES (?, ?, ?, 'pending', ?, NOW())";
        
        $doc_stmt = $conn->prepare($doc_sql);
        if (!$doc_stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }
        
        $doc_stmt->bind_param('siis', $title, $type_id, $user_id, $google_doc_id);
        
        debug_log("Executing document insert");
        if (!$doc_stmt->execute()) {
            throw new Exception("Document insert failed: " . $doc_stmt->error);
        }
        
        $document_id = $conn->insert_id;
        debug_log("Document inserted with ID: $document_id");
        
        // Check if document_workflow table has status column
        $check_column = "SHOW COLUMNS FROM document_workflow LIKE 'status'";
        $column_result = $conn->query($check_column);
        $has_status_column = ($column_result && $column_result->num_rows > 0);
        
        debug_log("Status column exists: " . ($has_status_column ? "Yes" : "No"));
        
        // Process workflow if provided
        if (isset($_POST['workflow_offices']) && is_array($_POST['workflow_offices'])) {
            $workflow_offices = $_POST['workflow_offices'];
            debug_log("Processing workflow with " . count($workflow_offices) . " steps");
            
            // Get workflow roles if provided
            $workflow_roles = isset($_POST['workflow_roles']) ? $_POST['workflow_roles'] : [];
            $recipient_types = isset($_POST['recipient_types']) ? $_POST['recipient_types'] : [];
            
            for ($i = 0; $i < count($workflow_offices); $i++) {
                $office_id = (int)$workflow_offices[$i];
                $role_id = isset($workflow_roles[$i]) && !empty($workflow_roles[$i]) ? (int)$workflow_roles[$i] : null;
                $recipient_type = isset($recipient_types[$i]) ? $recipient_types[$i] : 'office';
                $step_order = $i + 1;
                $status_value = ($i == 0) ? 'CURRENT' : 'PENDING';
                
                debug_log("Adding workflow step $step_order: Office $office_id, Status: $status_value");
                
                // Dynamic SQL based on column existence
                if ($has_status_column) {
                    $workflow_sql = "INSERT INTO document_workflow 
                                   (document_id, office_id, user_id, recipient_type, step_order, status) 
                                   VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $workflow_stmt = $conn->prepare($workflow_sql);
                    if (!$workflow_stmt) {
                        throw new Exception("Prepare workflow statement failed: " . $conn->error);
                    }
                    
                    $workflow_stmt->bind_param('iiisis', $document_id, $office_id, $role_id, $recipient_type, $step_order, $status_value);
                } else {
                    $workflow_sql = "INSERT INTO document_workflow 
                                   (document_id, office_id, user_id, recipient_type, step_order) 
                                   VALUES (?, ?, ?, ?, ?)";
                    
                    $workflow_stmt = $conn->prepare($workflow_sql);
                    if (!$workflow_stmt) {
                        throw new Exception("Prepare workflow statement failed: " . $conn->error);
                    }
                    
                    $workflow_stmt->bind_param('iiisi', $document_id, $office_id, $role_id, $recipient_type, $step_order);
                }
                
                if (!$workflow_stmt->execute()) {
                    throw new Exception("Workflow insert failed: " . $workflow_stmt->error);
                }
                
                // Create notification for the first step
                if ($i == 0) {
                    $notify_sql = "INSERT INTO notifications (user_id, document_id, message, created_at, is_read) 
                                  SELECT u.user_id, ?, CONCAT('New document \"', ?, '\"', ' requires your attention'), NOW(), 0 
                                  FROM users u WHERE u.office_id = ?";
                    
                    $notify_stmt = $conn->prepare($notify_sql);
                    $notify_stmt->bind_param('isi', $document_id, $title, $office_id);
                    $notify_stmt->execute();
                    
                    debug_log("Notifications created for users in office $office_id");
                }
            }
        } else {
            debug_log("No workflow data provided, creating default workflow");
            
            // Create default workflow to user's office
            if ($has_status_column) {
                $default_workflow_sql = "INSERT INTO document_workflow (document_id, office_id, step_order, status, created_at) 
                                        VALUES (?, ?, 1, 'CURRENT', NOW())";
                
                $default_workflow_stmt = $conn->prepare($default_workflow_sql);
                $default_workflow_stmt->bind_param('ii', $document_id, $office_id);
            } else {
                $default_workflow_sql = "INSERT INTO document_workflow (document_id, office_id, step_order, created_at) 
                                        VALUES (?, ?, 1, NOW())";
                
                $default_workflow_stmt = $conn->prepare($default_workflow_sql);
                $default_workflow_stmt->bind_param('ii', $document_id, $office_id);
            }
            
            if (!$default_workflow_stmt->execute()) {
                throw new Exception("Default workflow insert failed: " . $default_workflow_stmt->error);
            }
        }
        
        // Process attachment if provided
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            debug_log("Processing file attachment");
            
            $file = $_FILES['attachment'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            
            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = 'attachment_' . $document_id . '_' . time() . '.' . $file_ext;
            
            $upload_dir = __DIR__ . '/../uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $destination = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                $attach_sql = "INSERT INTO document_attachments (document_id, file_name, file_path, uploaded_at) 
                              VALUES (?, ?, ?, NOW())";
                
                $attach_stmt = $conn->prepare($attach_sql);
                $attach_stmt->bind_param('iss', $document_id, $file_name, $new_file_name);
                
                if (!$attach_stmt->execute()) {
                    throw new Exception("Attachment insert failed: " . $attach_stmt->error);
                }
                
                debug_log("Attachment saved: $new_file_name");
            } else {
                debug_log("Failed to move uploaded file", "ERROR");
            }
        }
        
        // Commit transaction
        $conn->commit();
        debug_log("Transaction committed successfully");
        
        // Return success response
        $response = [
            'success' => true,
            'document_id' => $document_id,
            'message' => 'Document submitted successfully'
        ];
        
        debug_log("Returning success response: " . json_encode($response));
        
        // Clear any output buffer and set proper headers
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        debug_log("Transaction rolled back due to error: " . $e->getMessage(), "ERROR");
        
        // Return error response
        $error_response = [
            'success' => false,
            'error' => $e->getMessage()
        ];
        
        debug_log("Returning error response: " . json_encode($error_response));
        
        // Clear any output buffer and set proper headers
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($error_response);
    }
    
} catch (Exception $e) {
    debug_log("Fatal error: " . $e->getMessage(), "ERROR");
    
    // Clear any output buffer and set proper headers
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'System error: ' . $e->getMessage()
    ]);
}

debug_log("=== END SUBMIT DOCUMENT DEBUG ===");
?> 