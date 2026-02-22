<?php
session_start();
require_once '../includes/config.php';

// Database connection function
function connectDB() {
    // Use the same database credentials as in config.php
    $db_host = "localhost";
    $db_user = "root";
    $db_pass = "";
    $db_name = "scc_dms";
    
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_office_id = $_SESSION['office_id'] ?? 0;

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    if (empty($_POST['title']) || empty($_POST['type_id']) || empty($_FILES['document']['name'])) {
        $_SESSION['error'] = "Please fill in all required fields and upload a document.";
        header('Location: dashboard.php?page=documents');
        exit();
    }
    
    // Get form data
    $title = $_POST['title'];
    $type_id = $_POST['type_id'];
    $share_document = isset($_POST['share_document']) ? true : false;
    $target_office_id = $share_document && !empty($_POST['target_office_id']) ? $_POST['target_office_id'] : null;
    $share_comment = $share_document && !empty($_POST['share_comment']) ? $_POST['share_comment'] : '';
    
    // Check if file was uploaded successfully
    if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
            UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.",
            UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded.",
            UPLOAD_ERR_NO_FILE => "No file was uploaded.",
            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
        ];
        
        $error_message = isset($upload_errors[$_FILES['document']['error']]) ? 
                         $upload_errors[$_FILES['document']['error']] : 
                         "Unknown upload error.";
        
        $_SESSION['error'] = "Upload failed: " . $error_message;
        header('Location: dashboard.php?page=documents');
        exit();
    }
    
    // Validate file type
    $allowed_extensions = ['pdf', 'docx', 'doc', 'txt', 'html', 'htm'];
    $file_name = $_FILES['document']['name'];
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        $_SESSION['error'] = "Invalid file type. Allowed types: PDF, DOCX, DOC, TXT, HTML, HTM.";
        header('Location: dashboard.php?page=documents');
        exit();
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = '../storage/uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique file name
    $new_file_name = uniqid() . '_' . preg_replace('/\s+/', '_', $file_name);
    $file_path = $upload_dir . $new_file_name;
    
    // Move uploaded file to destination
    if (!move_uploaded_file($_FILES['document']['tmp_name'], $file_path)) {
        $_SESSION['error'] = "Failed to save the uploaded file.";
        header('Location: dashboard.php?page=documents');
        exit();
    }
    
    // Connect to database
    $conn = connectDB();
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Insert document into database
        $document_query = "INSERT INTO documents (title, file_path, type_id, creator_id, status) VALUES (?, ?, ?, ?, 'draft')";
        $document_stmt = $conn->prepare($document_query);
        
        if ($document_stmt === false) {
            throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error . " - Query: " . $document_query);
        }
        
        $document_stmt->bind_param("ssii", $title, $file_path, $type_id, $user_id);
        $document_stmt->execute();
        
        $document_id = $conn->insert_id;
        
        // Add document log entry
        $log_query = "INSERT INTO document_logs (document_id, user_id, action, comment) VALUES (?, ?, 'upload', 'Document uploaded')";
        $log_stmt = $conn->prepare($log_query);
        
        if ($log_stmt === false) {
            throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error . " - Query: " . $log_query);
        }
        
        $log_stmt->bind_param("ii", $document_id, $user_id);
        $log_stmt->execute();
        
        // If sharing with another office, create document workflow entry
        if ($share_document && $target_office_id) {
            // Validate target office exists
            $office_query = "SELECT office_id FROM offices WHERE office_id = ?";
            $office_stmt = $conn->prepare($office_query);
            
            if ($office_stmt === false) {
                throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error . " - Query: " . $office_query);
            }
            
            $office_stmt->bind_param("i", $target_office_id);
            $office_stmt->execute();
            $office_result = $office_stmt->get_result();
            
            if ($office_result && $office_result->num_rows > 0) {
                // Create workflow entry
                $workflow_query = "INSERT INTO document_workflow (document_id, source_office_id, target_office_id, status, created_at) 
                                  VALUES (?, ?, ?, 'PENDING', NOW())";
                $workflow_stmt = $conn->prepare($workflow_query);
                
                if ($workflow_stmt === false) {
                    throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error . " - Query: " . $workflow_query);
                }
                
                $workflow_stmt->bind_param("iii", $document_id, $user_office_id, $target_office_id);
                $workflow_stmt->execute();
                
                // Update document status to pending
                $update_query = "UPDATE documents SET status = 'pending' WHERE document_id = ?";
                $update_stmt = $conn->prepare($update_query);
                
                if ($update_stmt === false) {
                    throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error . " - Query: " . $update_query);
                }
                
                $update_stmt->bind_param("i", $document_id);
                $update_stmt->execute();
                
                // Add document log entry for sharing
                $share_log_query = "INSERT INTO document_logs (document_id, user_id, action, comment) VALUES (?, ?, 'share', ?)";
                $share_log_stmt = $conn->prepare($share_log_query);
                
                if ($share_log_stmt === false) {
                    throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error . " - Query: " . $share_log_query);
                }
                
                $share_log_stmt->bind_param("iis", $document_id, $user_id, $share_comment);
                $share_log_stmt->execute();
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Document uploaded successfully" . ($share_document && $target_office_id ? " and shared with the selected office." : ".");
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Delete uploaded file
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    // Close database connection
    $conn->close();
    
    // Redirect back to documents page
    header('Location: dashboard.php?page=documents');
    exit();
} else {
    // If not a POST request, redirect to documents page
    header('Location: dashboard.php?page=documents');
    exit();
}
