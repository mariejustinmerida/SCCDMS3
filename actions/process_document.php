<?php
session_start();
require_once '../includes/config.php';

// Disable error display in output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Log errors to a file instead
ini_set('log_errors', 1);
ini_set('error_log', '../php_errors.log');

// Debug: Log all POST data
error_log("POST data received in process_document.php: " . print_r($_POST, true));

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    error_log("Error: User not logged in");
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'An error occurred'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $title = isset($_POST['title']) ? $_POST['title'] : '';
    $type_id = isset($_POST['type_id']) ? intval($_POST['type_id']) : 0;
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    
    error_log("Form data: title=$title, type_id=$type_id, content length=" . strlen($content));
    
    if (empty($title) || empty($type_id) || empty($content)) {
        error_log("Error: Missing required fields");
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Create absolute paths for all necessary directories
        $base_dir = realpath(dirname(dirname(__FILE__))); // Get the absolute path to the project root
        $upload_dir = $base_dir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        $content_dir = $base_dir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR;
        
        // Debug information
        error_log("Base directory: " . $base_dir);
        error_log("Upload directory: " . $upload_dir);
        error_log("Content directory: " . $content_dir);
        
        // Create storage directory if it doesn't exist
        $storage_dir = $base_dir . DIRECTORY_SEPARATOR . 'storage';
        if (!file_exists($storage_dir)) {
            if (!mkdir($storage_dir, 0777)) {
                throw new Exception("Failed to create storage directory: " . $storage_dir);
            }
            chmod($storage_dir, 0777);
            error_log("Created storage directory: " . $storage_dir);
        }
        
        // Create uploads directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777)) {
                throw new Exception("Failed to create uploads directory: " . $upload_dir);
            }
            chmod($upload_dir, 0777);
            error_log("Created uploads directory: " . $upload_dir);
        }
        
        // Create documents directory if it doesn't exist
        if (!file_exists($content_dir)) {
            if (!mkdir($content_dir, 0777)) {
                throw new Exception("Failed to create documents directory: " . $content_dir);
            }
            chmod($content_dir, 0777);
            error_log("Created documents directory: " . $content_dir);
        }
        
        // Save the content to an HTML file first
        $content_filename = uniqid() . '.html';
        $content_path = $content_dir . $content_filename;
        file_put_contents($content_path, $content);
        error_log("Saved content to: " . $content_path);
        
        // Process file upload if provided
        $file_path = null;
        if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
            $allowed_types = ['pdf', 'doc', 'docx'];
            $file = $_FILES['document'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_types)) {
                $unique_id = uniqid();
                $original_filename = pathinfo($file['name'], PATHINFO_FILENAME);
                // Sanitize the original filename to remove special characters
                $original_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $original_filename);
                $new_filename = $unique_id . '_' . $original_filename . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                    throw new Exception("Failed to move uploaded file to: " . $upload_path);
                }
                error_log("Uploaded file to: " . $upload_path);
                
                // Store relative path in database
                $file_path = 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $new_filename;
            } else {
                throw new Exception("Invalid file type");
            }
        } else {
            // If no file was uploaded, create a HTML document from the content
            
            $unique_id = uniqid();
            $title_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $title); // Sanitize title for filename
            $new_filename = $unique_id . '_' . $title_filename . '.html';
            $upload_path = $upload_dir . $new_filename;
            
            // Store relative path in database
            $file_path = 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $new_filename;
            
            // Create a simple HTML wrapper with basic styling
            $full_html = '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>' . htmlspecialchars($title) . '</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 1in; }
                    h1, h2, h3 { color: #333; }
                    table { border-collapse: collapse; width: 100%; }
                    table, th, td { border: 1px solid #ddd; padding: 8px; }
                </style>
            </head>
            <body>' . $content . '</body>
            </html>';
            
            try {
                // Save the content directly as a DOCX file (which is actually HTML)
                file_put_contents($upload_path, $full_html);
                
                // Save a copy of the content for search
                $temp_html = $content_dir . 'temp_' . $content_filename;
                file_put_contents($temp_html, $content);
                
                // Save a plain text version for search functionality
                $search_content = strip_tags($content);
                $search_file = $content_dir . 'search_' . $content_filename;
                file_put_contents($search_file, $search_content);
            } catch (Exception $file_error) {
                error_log("Document generation error: " . $file_error->getMessage());
                throw new Exception("Error generating document: " . $file_error->getMessage());
            }
        }
        
        // Insert document into database
        $status = 'pending';
        $sql = "INSERT INTO documents (title, type_id, creator_id, file_path, status, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siiss", $title, $type_id, $user_id, $file_path, $status);
        
        if (!$stmt->execute()) {
            throw new Exception("Error saving document: " . $conn->error);
        }
        
        $document_id = $conn->insert_id;
        
        // Process workflow steps if present
        if (isset($_POST['workflow']) && is_array($_POST['workflow'])) {
            $workflow = $_POST['workflow'];
            $recipient_types = isset($_POST['recipient_types']) ? $_POST['recipient_types'] : [];
            $user_workflow = isset($_POST['user_workflow']) ? $_POST['user_workflow'] : [];
            
            error_log("Workflow data: " . print_r($workflow, true));
            error_log("Recipient types: " . print_r($recipient_types, true));
            error_log("User workflow: " . print_r($user_workflow, true));
            
            // Clean the workflow by removing duplicates while preserving order
            $cleanWorkflow = [];
            $uniqueOffices = [];
            
            foreach ($workflow as $index => $office_id) {
                if (!in_array($office_id, $uniqueOffices)) {
                    $uniqueOffices[] = $office_id;
                    $cleanWorkflow[] = [
                        'office_id' => $office_id,
                        'recipient_type' => isset($recipient_types[$index]) ? $recipient_types[$index] : 'office',
                        'user_id' => (isset($recipient_types[$index]) && $recipient_types[$index] == 'person' && isset($user_workflow[$index])) ? $user_workflow[$index] : null
                    ];
                }
            }
            
            error_log("Clean workflow: " . print_r($cleanWorkflow, true));
            
            // First, save the entire workflow for future reference
            foreach ($cleanWorkflow as $index => $step) {
                $step_order = $index + 1;
                $workflow_sql = "INSERT INTO document_workflow (document_id, office_id, user_id, recipient_type, step_order) VALUES (?, ?, ?, ?, ?)";
                $workflow_stmt = $conn->prepare($workflow_sql);
                
                error_log("Inserting workflow step: document_id=$document_id, office_id={$step['office_id']}, user_id={$step['user_id']}, recipient_type={$step['recipient_type']}, step_order=$step_order");
                
                $workflow_stmt->bind_param("iiisi", $document_id, $step['office_id'], $step['user_id'], $step['recipient_type'], $step_order);
                
                if (!$workflow_stmt->execute()) {
                    error_log("Error inserting workflow step: " . $conn->error);
                    throw new Exception("Error inserting workflow step: " . $conn->error);
                }
            }
            
            // Find a valid step_id for the first office in the workflow
            $office_id = $cleanWorkflow[0]['office_id'];
            $step_sql = "SELECT step_id FROM workflow_steps WHERE office_id = ? LIMIT 1";
            $step_stmt = $conn->prepare($step_sql);
            $step_stmt->bind_param("i", $office_id);
            $step_stmt->execute();
            $step_result = $step_stmt->get_result();
            
            if ($step_result->num_rows > 0) {
                $step_row = $step_result->fetch_assoc();
                $current_step = $step_row['step_id'];
                
                // Update document with current step
                $update_sql = "UPDATE documents SET current_step = ? WHERE document_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $current_step, $document_id);
                
                if (!$update_stmt->execute()) {
                    error_log("Error updating workflow: " . $conn->error);
                    throw new Exception("Error updating workflow: " . $conn->error);
                }
            } else {
                // If no matching step_id is found, create a new workflow step for this document type and office
                $create_step_sql = "INSERT INTO workflow_steps (type_id, office_id, step_order) VALUES (?, ?, 1)";
                $create_step_stmt = $conn->prepare($create_step_sql);
                $create_step_stmt->bind_param("ii", $type_id, $office_id);
                
                if ($create_step_stmt->execute()) {
                    $new_step_id = $conn->insert_id;
                    
                    // Update document with the new step
                    $update_sql = "UPDATE documents SET current_step = ? WHERE document_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ii", $new_step_id, $document_id);
                    
                    if (!$update_stmt->execute()) {
                        error_log("Error updating workflow with new step: " . $conn->error);
                        throw new Exception("Error updating workflow with new step: " . $conn->error);
                    }
                } else {
                    error_log("Error creating workflow step: " . $conn->error);
                    throw new Exception("Error creating workflow step: " . $conn->error);
                }
            }
        }
        
        // Extract text content for AI search
        $text_content = strip_tags($content);
        
        // Store text content in a separate file for AI search
        $search_content_file = $content_dir . 'search_' . $content_filename;
        file_put_contents($search_content_file, $text_content);
        
        // Commit transaction
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = 'Document processed successfully!';
        $response['document_id'] = $document_id;
        $response['redirect'] = 'dashboard.php';
        
        error_log("Document processed successfully: document_id=$document_id");
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        
        $response['success'] = false;
        $response['message'] = $e->getMessage();
        
        error_log("Error in process_document.php: " . $e->getMessage());
    }
}

echo json_encode($response);
?>
