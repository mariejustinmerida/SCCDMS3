<?php
// This file is included by dashboard.php, so we don't need session_start() or config.php

// Initialize status and message variables
$status = '';
$message = '';
$requesting_office = '';

// Get user information
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'] ?? 0;

// Check if document ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>Error!</strong>
            <span class='block sm:inline'>No document ID provided.</span>
          </div>";
    exit();
}

$document_id = (int)$_GET['id'];

// Check if the document belongs to the user
$check_sql = "SELECT d.* FROM documents d
              WHERE d.document_id = ? AND d.creator_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $document_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if (!$check_result) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>Error!</strong>
            <span class='block sm:inline'>Database error: " . $conn->error . "</span>
          </div>";
    exit();
}

if ($check_result->num_rows === 0) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>Error!</strong>
            <span class='block sm:inline'>You don't have permission to revise this document.</span>
          </div>";
    exit();
}

// Fetch document details
$sql = "SELECT d.*, dt.type_name, 
        (SELECT dw.comments FROM document_workflow dw 
         WHERE dw.document_id = d.document_id AND dw.status = 'CURRENT' AND dw.comments IS NOT NULL
         ORDER BY dw.created_at DESC LIMIT 1) as revision_comments,
        (SELECT o.office_name FROM document_workflow dw JOIN offices o ON dw.office_id = o.office_id 
         WHERE dw.document_id = d.document_id AND dw.status = 'CURRENT' AND dw.comments IS NOT NULL
         ORDER BY dw.created_at DESC LIMIT 1) as requesting_office
        FROM documents d
        JOIN document_types dt ON d.type_id = dt.type_id
        WHERE d.document_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>Error!</strong>
            <span class='block sm:inline'>Database error: " . $conn->error . "</span>
          </div>";
    exit();
}

$document = $result->fetch_assoc();

// Get the requesting office
if (isset($document['requesting_office'])) {
    $requesting_office = $document['requesting_office'];
}

// Check if document can be revised (only revision documents can be revised)
if ($document['status'] !== 'revision') {
    echo "<div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>Warning!</strong>
            <span class='block sm:inline'>This document is not marked for revision.</span>
          </div>";
    exit();
}

// Check if document_attachments table exists
$attachments = [];
$table_exists = false;
$check_table_sql = "SHOW TABLES LIKE 'document_attachments'";
$check_table_result = $conn->query($check_table_sql);
if ($check_table_result && $check_table_result->num_rows > 0) {
    $table_exists = true;
    
    // Fetch document attachments if table exists
    if ($table_exists) {
        $attachments_sql = "SELECT * FROM document_attachments WHERE document_id = $document_id";
        $attachments_result = $conn->query($attachments_sql);
        
        if ($attachments_result) {
            while ($attachment = $attachments_result->fetch_assoc()) {
                $attachments[] = $attachment;
            }
        }
    }
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $title = trim($_POST['title']);
    $revision_comments = trim($_POST['revision_comments'] ?? '');
    
    if (empty($title)) {
        $error_message = "Document title is required.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update document
            $title = $conn->real_escape_string($title);
            $update_sql = "UPDATE documents SET 
                          title = '$title', 
                          status = 'pending',
                          updated_at = NOW() 
                          WHERE document_id = $document_id";
            $update_result = $conn->query($update_sql);
            
            // Update workflow status
            $update_workflow = "UPDATE document_workflow 
                               SET status = 'COMPLETED', comments = CONCAT(comments, '\n[REVISED] ', ?)
                               WHERE document_id = ? AND status = 'CURRENT'";
            $workflow_stmt = $conn->prepare($update_workflow);
            $workflow_stmt->bind_param('si', $revision_comments, $document_id);
            $workflow_stmt->execute();
            
            if (!$update_result) {
                throw new Exception("Error updating document: " . $conn->error);
            }
            
            // Handle file uploads if attachments table exists
            if ($table_exists && isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                $upload_dir = "../uploads/";
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Process each file
                for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                    if ($_FILES['attachments']['error'][$i] === 0) {
                        $file_name = $_FILES['attachments']['name'][$i];
                        $file_size = $_FILES['attachments']['size'][$i];
                        $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                        $file_type = $_FILES['attachments']['type'][$i];
                        
                        // Generate unique filename
                        $file_path = uniqid() . '_' . $file_name;
                        
                        // Move file to uploads directory
                        if (move_uploaded_file($file_tmp, $upload_dir . $file_path)) {
                            // Insert attachment record
                            $file_name = $conn->real_escape_string($file_name);
                            $file_path = $conn->real_escape_string($file_path);
                            $file_type = $conn->real_escape_string($file_type);
                            $attach_sql = "INSERT INTO document_attachments (document_id, file_name, file_path, file_size, file_type, uploaded_at) 
                                          VALUES ($document_id, '$file_name', '$file_path', $file_size, '$file_type', NOW())";
                            $attach_result = $conn->query($attach_sql);
                            
                            if (!$attach_result) {
                                throw new Exception("Error adding attachment: " . $conn->error);
                            }
                        }
                    }
                }
            }
            
            // Handle attachment deletions if table exists
            if ($table_exists && isset($_POST['delete_attachments']) && is_array($_POST['delete_attachments'])) {
                foreach ($_POST['delete_attachments'] as $attachment_id) {
                    $attachment_id = (int)$attachment_id;
                    // Get file path
                    $file_sql = "SELECT file_path FROM document_attachments WHERE attachment_id = $attachment_id AND document_id = $document_id";
                    $file_result = $conn->query($file_sql);
                    
                    if (!$file_result) {
                        throw new Exception("Error getting file path: " . $conn->error);
                    }
                    
                    if ($file_result->num_rows > 0) {
                        $file_path = $file_result->fetch_assoc()['file_path'];
                        
                        // Delete file from filesystem
                        $full_path = "../uploads/" . $file_path;
                        if (file_exists($full_path)) {
                            unlink($full_path);
                        }
                        
                        // Delete record from database
                        $delete_sql = "DELETE FROM document_attachments WHERE attachment_id = $attachment_id AND document_id = $document_id";
                        $delete_result = $conn->query($delete_sql);
                        
                        if (!$delete_result) {
                            throw new Exception("Error deleting attachment: " . $conn->error);
                        }
                    }
                }
            }
            
            // Log the revision
            $log_sql = "INSERT INTO document_logs (document_id, user_id, action, details, created_at) 
                       VALUES (?, ?, 'revised', ?, NOW())";
            $log_details = "Document revised after requested changes.";
            if (!empty($revision_comments)) {
                $log_details .= " Comments: " . $revision_comments;
            }
            
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("iis", $document_id, $user_id, $log_details);
            $log_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Document has been successfully revised and will continue through the workflow process.";
            
            // Redirect to documents page
            header("Location: dashboard.php?page=documents&status=success&message=" . urlencode($success_message));
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error revising document: " . $e->getMessage();
        }
    }
}

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>

<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="border-b px-6 py-3 bg-purple-100">
            <h2 class="text-lg font-semibold text-purple-800 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Revise Document
            </h2>
        </div>
        
        <div class="p-6">
            <?php if (!empty($error_message)): ?>
                <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 text-red-700">
                    <p class="font-medium">Error</p>
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 text-green-700">
                    <p class="font-medium">Success</p>
                    <p><?php echo $success_message; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="mb-6 bg-amber-50 border-l-4 border-amber-500 p-4 text-amber-700">
                <h3 class="font-medium text-lg mb-2">Revision Requested</h3>
                <p class="mb-2"><strong>Requested By:</strong> <?php echo htmlspecialchars($requesting_office); ?></p>
                <p class="mb-2"><strong>Comments:</strong></p>
                <div class="p-3 bg-white rounded border border-amber-200">
                    <?php echo nl2br(htmlspecialchars($document['revision_comments'] ?? 'No comments provided')); ?>
                </div>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-6">
                    <h3 class="font-medium text-lg mb-4">Document Information</h3>
                    
                    <div class="mb-4">
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Document Title</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($document['title']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600 focus:border-transparent" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
                        <input type="text" value="<?php echo htmlspecialchars($document['type_name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <div class="px-3 py-2 inline-block rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                            Revision Requested
                        </div>
                    </div>
                </div>
                
                <?php if ($table_exists): ?>
                <div class="mb-6">
                    <h3 class="font-medium text-lg mb-4">Attachments</h3>
                    
                    <?php if (!empty($attachments)): ?>
                    <div class="mb-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Current Attachments</h4>
                        <div class="space-y-2">
                            <?php foreach ($attachments as $attachment): ?>
                            <div class="flex items-center justify-between p-3 border rounded-md">
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium"><?php echo htmlspecialchars($attachment['file_name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo formatFileSize($attachment['file_size']); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <a href="../uploads/<?php echo $attachment['file_path']; ?>" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">View</a>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="delete_attachments[]" value="<?php echo $attachment['attachment_id']; ?>" class="form-checkbox h-4 w-4 text-red-600">
                                        <span class="ml-2 text-sm text-red-600">Delete</span>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Add New Attachments</h4>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                            <div class="space-y-1 text-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <label for="file-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-purple-600 hover:text-purple-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-purple-500">
                                        <span>Upload files</span>
                                        <input id="file-upload" name="attachments[]" type="file" class="sr-only" multiple>
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PDF, Word, Excel, PowerPoint, etc.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mb-6">
                    <h3 class="font-medium text-lg mb-4">Revision Comments</h3>
                    <div class="mb-4">
                        <label for="revision_comments" class="block text-sm font-medium text-gray-700 mb-1">Your Comments (Optional)</label>
                        <textarea id="revision_comments" name="revision_comments" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-600 focus:border-transparent" placeholder="Add any comments about the changes you've made..."></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <a href="dashboard.php?page=documents_needing_revision" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">Submit Revised Document</button>
                </div>
            </form>
        </div>
    </div>
</div>
