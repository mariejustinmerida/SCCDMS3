<?php
// Ensure clean JSON output, no PHP warnings leak
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_error_handler(function($severity, $message){ error_log("delete_document.php: $message"); return true; });

// Set content type to JSON first
header('Content-Type: application/json');

function safeDelete($conn, $sql, $bindTypes, ...$params) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false; // Table probably doesn't exist, skip it
    }
    if (!empty($bindTypes) && !empty($params)) {
        $stmt->bind_param($bindTypes, ...$params);
    }
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

try {
    session_start();
    require_once '../includes/config.php';

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'User not logged in']);
        exit;
    }

    // Enforce Super Admin only for document deletion
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Super Admin') {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Only Super Admin can delete documents']);
        exit;
    }

    // Get the request data
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) { $data = []; }

    // Check if document ID is provided
    if (!isset($data['id']) || empty($data['id'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'No document ID provided']);
        exit;
    }

    $document_id = (int)$data['id'];
    
    // Get the document details from the database
    $sql = "SELECT document_id, title, file_path FROM documents WHERE document_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $document_id);
    if (!$stmt->execute()) {
        $stmt->close();
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
        exit;
    }
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows === 0) {
        $stmt->close();
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Document not found']);
        exit;
    }
    
    $document = $result->fetch_assoc();
    $filePath = $document['file_path'];
    $file_name = $document['title'];
    $stmt->close();
    
    // Start transaction for atomic deletion
    $conn->begin_transaction();
    
    try {
        // Delete related records from tables WITHOUT CASCADE delete
        // Use safeDelete which silently fails if table doesn't exist
        
        // Tables with int document_id
        safeDelete($conn, "DELETE FROM document_actions WHERE document_id = ?", 'i', $document_id);
        safeDelete($conn, "DELETE FROM document_workflow WHERE document_id = ?", 'i', $document_id);
        safeDelete($conn, "DELETE FROM simple_verifications WHERE document_id = ?", 'i', $document_id);
        safeDelete($conn, "DELETE FROM document_logs WHERE document_id = ?", 'i', $document_id);
        
        // Delete document_attachments and their files
        $getAttachments = $conn->prepare("SELECT id, file_path FROM document_attachments WHERE document_id = ?");
        if ($getAttachments) {
            $getAttachments->bind_param('i', $document_id);
            if ($getAttachments->execute()) {
                $attachmentsResult = $getAttachments->get_result();
                $basePath = realpath(dirname(dirname(__FILE__)));
                while ($attachment = $attachmentsResult->fetch_assoc()) {
                    if (!empty($attachment['file_path'])) {
                        $attachmentPath = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $attachment['file_path']);
                        if (file_exists($attachmentPath)) {
                            @unlink($attachmentPath);
                        }
                    }
                }
            }
            $getAttachments->close();
        }
        safeDelete($conn, "DELETE FROM document_attachments WHERE document_id = ?", 'i', $document_id);
        
        // Convert document_id to string for tables that use varchar
        $document_id_str = (string)$document_id;
        
        // Delete document_versions and their diffs
        $getVersions = $conn->prepare("SELECT version_id FROM document_versions WHERE document_id = ?");
        if ($getVersions) {
            $getVersions->bind_param('s', $document_id_str);
            if ($getVersions->execute()) {
                $versionsResult = $getVersions->get_result();
                while ($version = $versionsResult->fetch_assoc()) {
                    $versionId = $version['version_id'];
                    safeDelete($conn, "DELETE FROM version_diffs WHERE version_id = ? OR previous_version_id = ?", 'ii', $versionId, $versionId);
                }
            }
            $getVersions->close();
        }
        safeDelete($conn, "DELETE FROM document_versions WHERE document_id = ?", 's', $document_id_str);
        
        // Tables with varchar document_id
        safeDelete($conn, "DELETE FROM document_collaborators WHERE document_id = ?", 's', $document_id_str);
        safeDelete($conn, "DELETE FROM document_comments WHERE document_id = ?", 's', $document_id_str);
        safeDelete($conn, "DELETE FROM document_changes WHERE document_id = ?", 's', $document_id_str);
        safeDelete($conn, "DELETE FROM document_edit_sessions WHERE document_id = ?", 's', $document_id_str);
        safeDelete($conn, "DELETE FROM document_locks WHERE document_id = ?", 's', $document_id_str);
        
        // Now delete the document (this will automatically cascade delete records from tables with ON DELETE CASCADE)
        $deleteDocument = $conn->prepare("DELETE FROM documents WHERE document_id = ?");
        if (!$deleteDocument) {
            throw new Exception("Failed to prepare document delete: " . $conn->error);
        }
        $deleteDocument->bind_param('i', $document_id);
        if (!$deleteDocument->execute()) {
            $errorMsg = $deleteDocument->error;
            $deleteDocument->close();
            throw new Exception("Failed to delete document: " . $errorMsg);
        }
        $deleteDocument->close();
        
        if ($conn->affected_rows === 0) {
            $conn->rollback();
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Failed to delete document - no rows affected']);
            exit;
        }
        
        // Try to log the deletion (but don't fail if it doesn't work)
        // Check if activity_logs table exists first, then attempt to log
        $user_id = $_SESSION['user_id'];
        try {
            // Check if table exists first
            $check_table = $conn->query("SHOW TABLES LIKE 'activity_logs'");
            if ($check_table && $check_table->num_rows > 0) {
                $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, timestamp) VALUES (?, 'delete_document', ?, NOW())");
                if ($log_stmt) {
                    $log_details = "Permanently deleted document: " . $file_name;
                    $log_stmt->bind_param("is", $user_id, $log_details);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
            }
        } catch (Exception $log_error) {
            // Silently ignore logging errors - deletion is more important than logging
            error_log("Failed to log document deletion: " . $log_error->getMessage());
        }
        
        // Delete the physical file if it exists
        if (!empty($filePath)) {
            // Ensure the path is relative to the storage directory
            if (strpos($filePath, 'storage/') !== 0 && strpos($filePath, 'uploads/') !== 0) {
                if (strpos($filePath, '/') === 0 || strpos($filePath, '\\') === 0) {
                    $filePath = ltrim($filePath, '/\\');
                }
                $filePath = 'storage/' . $filePath;
            }
            
            // Get the absolute path
            $basePath = realpath(dirname(dirname(__FILE__)));
            $absolutePath = $basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);
            
            // Delete the file if it exists
            if ($absolutePath && file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
        }
        
        // Commit transaction
        $conn->commit();
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Document permanently deleted']);
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Deletion failed: ' . $e->getMessage()]);
        exit;
    }

} catch (Throwable $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    error_log("delete_document.php exception: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
}
?>
