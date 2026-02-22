<?php
/**
 * Submit Document API - Ultra Clean Version
 */

// Start output buffering immediately
ob_start();

// Completely suppress all error output
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// Custom error handler that does nothing
set_error_handler(function($severity, $message, $file, $line) {
    return true;
});

// Custom exception handler
set_exception_handler(function($exception) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'System error occurred']);
    exit;
});

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to send clean JSON response
function sendJsonResponse($data) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Check authentication first
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'error' => 'User not logged in']);
    }
    
    $user_id = $_SESSION['user_id'];
    $office_id = $_SESSION['office_id'] ?? 1;
    
    // Validate required fields
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $type_id = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
    $google_doc_id = isset($_POST['google_doc_id']) ? trim($_POST['google_doc_id']) : '';
    
    if (empty($title)) {
        sendJsonResponse(['success' => false, 'error' => 'Document title is required']);
    }
    
    if ($type_id <= 0) {
        sendJsonResponse(['success' => false, 'error' => 'Document type is required']);
    }
    
    if (empty($google_doc_id)) {
        sendJsonResponse(['success' => false, 'error' => 'Google Doc ID is required']);
    }
    
    // Include database configuration with error suppression
    $old_error_reporting = error_reporting(0);
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/enhanced_notification_system.php';
    error_reporting($old_error_reporting);
    
    // Check if database connection is available
    if (!isset($conn) || !$conn) {
        sendJsonResponse(['success' => false, 'error' => 'Database connection failed']);
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Check if this is a memorandum sent to all offices
    $is_memorandum_to_all = isset($_POST['is_memorandum_to_all']) && $_POST['is_memorandum_to_all'] == '1';
    
    // Insert document with memorandum flags if applicable
    if ($is_memorandum_to_all) {
        // Count total offices that will receive this memorandum
        $total_offices = isset($_POST['workflow_offices']) ? count($_POST['workflow_offices']) : 0;
        
        $doc_sql = "INSERT INTO documents (title, type_id, creator_id, status, google_doc_id, is_memorandum, memorandum_sent_to_all_offices, memorandum_total_offices, created_at) 
                  VALUES (?, ?, ?, 'pending', ?, 1, 1, ?, NOW())";
        
        $doc_stmt = $conn->prepare($doc_sql);
        if (!$doc_stmt) {
            throw new Exception("Prepare statement failed");
        }
        
        $doc_stmt->bind_param('siisi', $title, $type_id, $user_id, $google_doc_id, $total_offices);
    } else {
        $doc_sql = "INSERT INTO documents (title, type_id, creator_id, status, google_doc_id, created_at) 
                  VALUES (?, ?, ?, 'pending', ?, NOW())";
        
        $doc_stmt = $conn->prepare($doc_sql);
        if (!$doc_stmt) {
            throw new Exception("Prepare statement failed");
        }
        
        $doc_stmt->bind_param('siis', $title, $type_id, $user_id, $google_doc_id);
    }
    
    if (!$doc_stmt->execute()) {
        throw new Exception("Document insert failed");
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
        
        // For memorandum to all offices, all offices should get CURRENT status simultaneously
        if ($is_memorandum_to_all) {
            // Process all offices with CURRENT status for simultaneous distribution
            for ($i = 0; $i < count($workflow_offices); $i++) {
                $office_id = (int)$workflow_offices[$i];
                $role_id = isset($workflow_roles[$i]) && !empty($workflow_roles[$i]) ? (int)$workflow_roles[$i] : null;
                $recipient_type = isset($recipient_types[$i]) ? $recipient_types[$i] : 'office';
                $step_order = $i + 1;
                $status_value = 'CURRENT'; // All offices get CURRENT status for simultaneous viewing
                
                // Dynamic SQL based on column existence
                if ($has_status_column) {
                    $workflow_sql = "INSERT INTO document_workflow 
                                   (document_id, office_id, user_id, recipient_type, step_order, status) 
                                   VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $workflow_stmt = $conn->prepare($workflow_sql);
                    if (!$workflow_stmt) {
                        throw new Exception("Prepare workflow statement failed");
                    }
                    
                    $workflow_stmt->bind_param('iiisis', $document_id, $office_id, $role_id, $recipient_type, $step_order, $status_value);
                } else {
                    $workflow_sql = "INSERT INTO document_workflow 
                                   (document_id, office_id, user_id, recipient_type, step_order) 
                                   VALUES (?, ?, ?, ?, ?)";
                    
                    $workflow_stmt = $conn->prepare($workflow_sql);
                    if (!$workflow_stmt) {
                        throw new Exception("Prepare workflow statement failed");
                    }
                    
                    $workflow_stmt->bind_param('iiisi', $document_id, $office_id, $role_id, $recipient_type, $step_order);
                }
                
                if (!$workflow_stmt->execute()) {
                    throw new Exception("Workflow insert failed");
                }
                
                // Create enhanced notification for each office (all offices get notified simultaneously)
                notify_office_users($office_id, $document_id, 'memorandum_received', [
                    'memorandum_title' => $title,
                    'total_offices' => $total_offices
                ]);
                
                // Create memorandum distribution entry for tracking
                $dist_sql = "INSERT INTO memorandum_distribution (document_id, office_id, is_read, created_at) 
                            VALUES (?, ?, 0, NOW())";
                
                $dist_stmt = $conn->prepare($dist_sql);
                if ($dist_stmt) {
                    $dist_stmt->bind_param('ii', $document_id, $office_id);
                    $dist_stmt->execute();
                }
            }
        } else {
            // Regular sequential workflow processing
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
                        throw new Exception("Prepare workflow statement failed");
                    }
                    
                    $workflow_stmt->bind_param('iiisis', $document_id, $office_id, $role_id, $recipient_type, $step_order, $status_value);
                } else {
                    $workflow_sql = "INSERT INTO document_workflow 
                                   (document_id, office_id, user_id, recipient_type, step_order) 
                                   VALUES (?, ?, ?, ?, ?)";
                    
                    $workflow_stmt = $conn->prepare($workflow_sql);
                    if (!$workflow_stmt) {
                        throw new Exception("Prepare workflow statement failed");
                    }
                    
                    $workflow_stmt->bind_param('iiisi', $document_id, $office_id, $role_id, $recipient_type, $step_order);
                }
                
                if (!$workflow_stmt->execute()) {
                    throw new Exception("Workflow insert failed");
                }
                
                // Create enhanced notification for the first step only
                if ($i == 0) {
                    notify_office_users($office_id, $document_id, 'incoming', [
                        'document_title' => $title,
                        'step_order' => $step_order
                    ]);
                }
            }
        }
    } else {
        // Create default workflow to user's office
        if ($has_status_column) {
            $default_workflow_sql = "INSERT INTO document_workflow (document_id, office_id, step_order, status, created_at) 
                                    VALUES (?, ?, 1, 'CURRENT', NOW())";
            
            $default_workflow_stmt = $conn->prepare($default_workflow_sql);
            if ($default_workflow_stmt) {
                $default_workflow_stmt->bind_param('ii', $document_id, $office_id);
                $default_workflow_stmt->execute();
            }
        } else {
            $default_workflow_sql = "INSERT INTO document_workflow (document_id, office_id, step_order, created_at) 
                                    VALUES (?, ?, 1, NOW())";
            
            $default_workflow_stmt = $conn->prepare($default_workflow_sql);
            if ($default_workflow_stmt) {
                $default_workflow_stmt->bind_param('ii', $document_id, $office_id);
                $default_workflow_stmt->execute();
            }
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
            if ($attach_stmt) {
                $attach_stmt->bind_param('iss', $document_id, $file_name, $new_file_name);
                $attach_stmt->execute();
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Send success response
    sendJsonResponse([
        'success' => true,
        'document_id' => $document_id,
        'message' => 'Document submitted successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Send error response
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
