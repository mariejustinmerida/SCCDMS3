<?php
/**
 * Submit Document API - Clean JSON Response
 */

// Completely isolate output
ob_start();

// Suppress all error output
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set error handler to prevent any output
set_error_handler(function($severity, $message, $file, $line) {
    // Log errors but don't output them
    error_log("PHP Error: $message in $file on line $line");
    return true; // Don't execute PHP internal error handler
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Include database configuration
    require_once __DIR__ . '/../includes/config.php';
    
    // Check user authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }
    
    $user_id = $_SESSION['user_id'];
    $office_id = $_SESSION['office_id'] ?? 1;
    
    // Validate required fields
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $type_id = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
    $google_doc_id = isset($_POST['google_doc_id']) ? trim($_POST['google_doc_id']) : '';
    
    if (empty($title)) {
        throw new Exception('Document title is required');
    }
    
    if ($type_id <= 0) {
        throw new Exception('Document type is required');
    }
    
    if (empty($google_doc_id)) {
        throw new Exception('Google Doc ID is required');
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Insert document
    $doc_sql = "INSERT INTO documents (title, type_id, creator_id, status, google_doc_id, created_at) 
              VALUES (?, ?, ?, 'pending', ?, NOW())";
    
    $doc_stmt = $conn->prepare($doc_sql);
    if (!$doc_stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $doc_stmt->bind_param('siis', $title, $type_id, $user_id, $google_doc_id);
    
    if (!$doc_stmt->execute()) {
        throw new Exception("Document insert failed: " . $doc_stmt->error);
    }
    
    $document_id = $conn->insert_id;
    
    // Check if document_workflow table has status column
    $check_column = "SHOW COLUMNS FROM document_workflow LIKE 'status'";
    $column_result = $conn->query($check_column);
    $has_status_column = ($column_result && $column_result->num_rows > 0);
    
    // Process workflow if provided
    if (isset($_POST['workflow_offices']) && is_array($_POST['workflow_offices'])) {
        $workflow_offices = $_POST['workflow_offices'];
        $workflow_roles = isset($_POST['workflow_roles']) ? $_POST['workflow_roles'] : [];
        $recipient_types = isset($_POST['recipient_types']) ? $_POST['recipient_types'] : [];
        
        for ($i = 0; $i < count($workflow_offices); $i++) {
            $office_id = (int)$workflow_offices[$i];
            $role_id = isset($workflow_roles[$i]) && !empty($workflow_roles[$i]) ? (int)$workflow_roles[$i] : null;
            $recipient_type = isset($recipient_types[$i]) ? $recipient_types[$i] : 'office';
            $step_order = $i + 1;
            $status_value = ($i == 0) ? 'CURRENT' : 'PENDING';
            
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
            }
        }
    } else {
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
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Prepare success response
    $response = [
        'success' => true,
        'document_id' => $document_id,
        'message' => 'Document submitted successfully'
    ];
    
} catch (Exception $e) {
    // Rollback transaction
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Prepare error response
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// Clean all output and send JSON response
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>