<?php
require_once '../includes/config.php';
require_once '../includes/document_workflow.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'] ?? 0;

$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$status = '';
$document = null;

if ($document_id <= 0) {
    $message = "Invalid document ID";
    $status = "error";
} else {

    $doc_query = "SELECT d.*, dt.type_name, u.full_name as creator_name, o.office_name as creator_office
                 FROM documents d
                 LEFT JOIN document_types dt ON d.type_id = dt.type_id
                 LEFT JOIN users u ON d.creator_id = u.user_id
                 LEFT JOIN offices o ON u.office_id = o.office_id
                 WHERE d.document_id = ?";
    
    $stmt = $conn->prepare($doc_query);
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $document = $result->fetch_assoc();

        $check_query = "SELECT * FROM document_workflow 
                       WHERE document_id = ? AND office_id = ? AND status = 'current'";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $document_id, $office_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result && $check_result->num_rows > 0) {
            $workflow = $check_result->fetch_assoc();
            
            // Process action if provided
            if ($action) {
                switch ($action) {
                    case 'approve':
                        // Get next office in workflow - using document_workflow table instead of workflow_steps
                        $next_office_query = "SELECT * FROM document_workflow 
                                            WHERE document_id = ? AND step_order > ? 
                                            ORDER BY step_order ASC LIMIT 1";
                        $next_stmt = $conn->prepare($next_office_query);
                        
                        // Log the query for debugging
                        error_log("Next office query: " . $next_office_query . " with document_id=" . $document_id . " and step_order=" . $workflow['step_order']);
                        
                        if (!$next_stmt) {
                            // Handle prepare error
                            throw new Exception("Database error: " . $conn->error);
                        }
                        
                        $next_stmt->bind_param("ii", $document_id, $workflow['step_order']);
                        $next_stmt->execute();
                        $next_result = $next_stmt->get_result();
                        
                        if ($next_result && $next_result->num_rows > 0) {
                            // There is a next office
                            $next_step = $next_result->fetch_assoc();
                            
                            // Check if this is the same office (duplicate in workflow)
                            if ($next_step['office_id'] == $office_id) {
                                // Skip to final approval since we don't want to send to the same office
                                $conn->begin_transaction();
                                
                                try {
                                    // Check if completed_at column exists
                                    $column_exists = false;
                                    $columns_result = $conn->query("SHOW COLUMNS FROM document_workflow LIKE 'completed_at'");
                                    if ($columns_result && $columns_result->num_rows > 0) {
                                        $column_exists = true;
                                    }
                                    
                                    // Update current workflow step to 'completed'
                                    if ($column_exists) {
                                        $update_current = "UPDATE document_workflow 
                                                     SET status = 'completed', completed_at = NOW() 
                                                     WHERE document_id = ? AND office_id = ? AND status = 'current'";
                                    } else {
                                        $update_current = "UPDATE document_workflow 
                                                     SET status = 'completed'
                                                     WHERE document_id = ? AND office_id = ? AND status = 'current'";
                                    }
                                    $update_stmt = $conn->prepare($update_current);
                                    
                                    if (!$update_stmt) {
                                        throw new Exception("Database error: " . $conn->error);
                                    }
                                    
                                    $update_stmt->bind_param("ii", $document_id, $office_id);
                                    $update_stmt->execute();
                                    
                                    // Update all pending workflow steps to completed
                                    $update_all_steps = "UPDATE document_workflow 
                                                       SET status = 'completed' 
                                                       WHERE document_id = ? AND status = 'pending'";
                                    $update_all_stmt = $conn->prepare($update_all_steps);
                                    $update_all_stmt->bind_param("i", $document_id);
                                    $update_all_stmt->execute();
                                    
                                    // Update document status to approved
                                    $update_doc = "UPDATE documents SET status = 'approved', updated_at = NOW() WHERE document_id = ?";
                                    $update_doc_stmt = $conn->prepare($update_doc);
                                    
                                    if (!$update_doc_stmt) {
                                        throw new Exception("Database error: " . $conn->error);
                                    }
                                    
                                    $update_doc_stmt->bind_param("i", $document_id);
                                    $update_doc_stmt->execute();
                                    
                                    // Commit transaction
                                    $conn->commit();
                                    
                                    $message = "Document has been approved. This was the final approval step.";
                                    $status = "success";
                                    break;
                                } catch (Exception $e) {
                                    // Rollback transaction on error
                                    $conn->rollback();
                                    $message = "Error processing final approval: " . $e->getMessage();
                                    $status = "error";
                                    break;
                                }
                            }
                            
                            // Start transaction
                            $conn->begin_transaction();
                            
                            try {
                                // Check if completed_at column exists
                                $column_exists = false;
                                $columns_result = $conn->query("SHOW COLUMNS FROM document_workflow LIKE 'completed_at'");
                                if ($columns_result && $columns_result->num_rows > 0) {
                                    $column_exists = true;
                                }
                                
                                // Update current workflow step to 'completed'
                                if ($column_exists) {
                                    $update_current = "UPDATE document_workflow 
                                                     SET status = 'completed', completed_at = NOW() 
                                                     WHERE document_id = ? AND office_id = ? AND status = 'current'";
                                } else {
                                    $update_current = "UPDATE document_workflow 
                                                     SET status = 'completed'
                                                     WHERE document_id = ? AND office_id = ? AND status = 'current'";
                                }
                                $update_stmt = $conn->prepare($update_current);
                                
                                if (!$update_stmt) {
                                    throw new Exception("Database error: " . $conn->error);
                                }
                                
                                $update_stmt->bind_param("ii", $document_id, $office_id);
                                $update_stmt->execute();
                                
                                // Check if the next office already has a workflow entry
                                $check_next = "SELECT * FROM document_workflow 
                                             WHERE document_id = ? AND office_id = ?";
                                $check_next_stmt = $conn->prepare($check_next);
                                
                                if (!$check_next_stmt) {
                                    throw new Exception("Database error: " . $conn->error);
                                }
                                
                                $check_next_stmt->bind_param("ii", $document_id, $next_step['office_id']);
                                $check_next_stmt->execute();
                                $check_next_result = $check_next_stmt->get_result();
                                
                                if ($check_next_result && $check_next_result->num_rows > 0) {
                                    // Update existing workflow entry
                                    $update_next = "UPDATE document_workflow 
                                                  SET status = 'current'
                                                  WHERE document_id = ? AND office_id = ?";
                                    $update_next_stmt = $conn->prepare($update_next);
                                    
                                    if (!$update_next_stmt) {
                                        throw new Exception("Database error: " . $conn->error);
                                    }
                                    
                                    $update_next_stmt->bind_param("ii", $document_id, $next_step['office_id']);
                                    $update_next_stmt->execute();
                                } else {
                                    // Create new workflow entry for next office
                                    $insert_next = "INSERT INTO document_workflow 
                                                  (document_id, office_id, step_order, status) 
                                                  VALUES (?, ?, ?, 'current')";
                                    $insert_next_stmt = $conn->prepare($insert_next);
                                    
                                    if (!$insert_next_stmt) {
                                        throw new Exception("Database error: " . $conn->error);
                                    }
                                    
                                    $insert_next_stmt->bind_param("iii", $document_id, $next_step['office_id'], $next_step['step_order']);
                                    $insert_next_stmt->execute();
                                }
                                
                                // Commit transaction
                                $conn->commit();
                                
                                // Check if there's actually a next office in the document workflow
                                $next_office_query = "SELECT COUNT(*) as count FROM document_workflow 
                                                    WHERE document_id = ? AND step_order > ? ";
                                $next_check_stmt = $conn->prepare($next_office_query);
                                $next_check_stmt->bind_param("ii", $document_id, $workflow['step_order']);
                                $next_check_stmt->execute();
                                $next_check_result = $next_check_stmt->get_result();
                                $next_check_data = $next_check_result->fetch_assoc();
                                
                                if ($next_check_data['count'] > 0) {
                                    $message = "Document approved and forwarded to the next office";
                                } else {
                                    $message = "Document has been approved. This was the final approval step.";
                                    
                                    // Update document status to approved since this is the final step
                                    $update_final = "UPDATE documents SET status = 'approved', updated_at = NOW() WHERE document_id = ?";
                                    $update_final_stmt = $conn->prepare($update_final);
                                    $update_final_stmt->bind_param("i", $document_id);
                                    $update_final_stmt->execute();
                                }
                                $status = "success";
                            } catch (Exception $e) {
                                // Rollback transaction on error
                                $conn->rollback();
                                $message = "Error processing approval: " . $e->getMessage();
                                $status = "error";
                            }
                        } else {
                            // This is the final office, mark document as approved
                            $conn->begin_transaction();
                            
                            try {
                                // Check if completed_at column exists
                                $column_exists = false;
                                $columns_result = $conn->query("SHOW COLUMNS FROM document_workflow LIKE 'completed_at'");
                                if ($columns_result && $columns_result->num_rows > 0) {
                                    $column_exists = true;
                                }
                                
                                // Update current workflow step to 'completed'
                                if ($column_exists) {
                                    $update_current = "UPDATE document_workflow 
                                                     SET status = 'completed', completed_at = NOW() 
                                                     WHERE document_id = ? AND office_id = ? AND status = 'current'";
                                } else {
                                    $update_current = "UPDATE document_workflow 
                                                     SET status = 'completed'
                                                     WHERE document_id = ? AND office_id = ? AND status = 'current'";
                                }
                                $update_stmt = $conn->prepare($update_current);
                                
                                if (!$update_stmt) {
                                    throw new Exception("Database error: " . $conn->error);
                                }
                                
                                $update_stmt->bind_param("ii", $document_id, $office_id);
                                $update_stmt->execute();
                                
                                // Update all pending workflow steps to completed
                                $update_all_steps = "UPDATE document_workflow 
                                                   SET status = 'completed' 
                                                   WHERE document_id = ? AND status = 'pending'";
                                $update_all_stmt = $conn->prepare($update_all_steps);
                                $update_all_stmt->bind_param("i", $document_id);
                                $update_all_stmt->execute();
                                
                                // Check if this is truly the final office in the workflow
                                $check_final_query = "SELECT COUNT(*) as count FROM document_workflow 
                                                     WHERE document_id = ? AND status = 'PENDING'";
                                $check_final_stmt = $conn->prepare($check_final_query);
                                $check_final_stmt->bind_param("i", $document_id);
                                $check_final_stmt->execute();
                                $check_final_result = $check_final_stmt->get_result();
                                $check_final_data = $check_final_result->fetch_assoc();
                                
                                // Only mark as approved if there are no pending steps left
                                if ($check_final_data['count'] == 0) {
                                    // Update document status to approved
                                    $update_doc = "UPDATE documents SET status = 'approved', updated_at = NOW() WHERE document_id = ?";
                                    $update_doc_stmt = $conn->prepare($update_doc);
                                    
                                    if (!$update_doc_stmt) {
                                        throw new Exception("Database error: " . $conn->error);
                                    }
                                    
                                    $update_doc_stmt->bind_param("i", $document_id);
                                    $update_doc_stmt->execute();
                                }
                                
                                // Commit transaction
                                $conn->commit();
                                
                                $message = "Document has been approved. This was the final approval step.";
                                $status = "success";
                            } catch (Exception $e) {
                                // Rollback transaction on error
                                $conn->rollback();
                                $message = "Error processing final approval: " . $e->getMessage();
                                $status = "error";
                            }
                        }
                        break;
                        
                    case 'reject':
                        // Update workflow status to rejected
                        $conn->begin_transaction();
                        
                        try {
                            // Check if completed_at column exists
                            $column_exists = false;
                            $columns_result = $conn->query("SHOW COLUMNS FROM document_workflow LIKE 'completed_at'");
                            if ($columns_result && $columns_result->num_rows > 0) {
                                $column_exists = true;
                            }
                            
                            // Update current workflow step to 'rejected'
                            if ($column_exists) {
                                $update_current = "UPDATE document_workflow 
                                                 SET status = 'rejected', completed_at = NOW() 
                                                 WHERE document_id = ? AND office_id = ? AND status = 'current'";
                            } else {
                                $update_current = "UPDATE document_workflow 
                                                 SET status = 'rejected'
                                                 WHERE document_id = ? AND office_id = ? AND status = 'current'";
                            }
                            $update_stmt = $conn->prepare($update_current);
                            
                            if (!$update_stmt) {
                                throw new Exception("Database error: " . $conn->error);
                            }
                            
                            $update_stmt->bind_param("ii", $document_id, $office_id);
                            $update_stmt->execute();
                            
                            // Update document status to rejected
                            $update_doc = "UPDATE documents SET status = 'rejected', updated_at = NOW() WHERE document_id = ?";
                            $update_doc_stmt = $conn->prepare($update_doc);
                            
                            if (!$update_doc_stmt) {
                                throw new Exception("Database error: " . $conn->error);
                            }
                            
                            $update_doc_stmt->bind_param("i", $document_id);
                            $update_doc_stmt->execute();
                            
                            // Commit transaction
                            $conn->commit();
                            
                            $message = "Document has been rejected.";
                            $status = "success";
                        } catch (Exception $e) {
                            // Rollback transaction on error
                            $conn->rollback();
                            $message = "Error processing rejection: " . $e->getMessage();
                            $status = "error";
                        }
                        break;
                        
                    case 'hold':
                        // Update workflow status to hold
                        $conn->begin_transaction();
                        
                        try {
                            // Insert a comment/note about the hold status
                            $comment = "Document placed on hold: Requires meeting or further discussion before approval.";
                            
                            // Add a log entry for the hold action
                            $log_sql = "INSERT INTO document_logs (document_id, user_id, action, details, created_at) 
                                      VALUES (?, ?, 'hold', ?, NOW())";
                            $log_stmt = $conn->prepare($log_sql);
                            
                            if (!$log_stmt) {
                                throw new Exception("Database error: " . $conn->error);
                            }
                            
                            $log_stmt->bind_param("iis", $document_id, $user_id, $comment);
                            $log_stmt->execute();
                            
                            // Keep the document_workflow status as 'CURRENT' since that's an allowed ENUM value
                            // No need to update the workflow status
                            
                            // Update document status in the documents table
                            // We'll use 'pending' status since 'on_hold' is not in the allowed ENUM values
                            // The document_logs table will store the 'hold' action to identify documents on hold
                            $update_doc = "UPDATE documents SET status = 'pending', updated_at = NOW() WHERE document_id = ?";
                            $update_doc_stmt = $conn->prepare($update_doc);
                            
                            if (!$update_doc_stmt) {
                                throw new Exception("Database error: " . $conn->error);
                            }
                            
                            $update_doc_stmt->bind_param("i", $document_id);
                            $update_doc_stmt->execute();
                            
                            // Commit transaction
                            $conn->commit();
                            
                            $message = "Document has been placed on hold.";
                            $status = "success";
                        } catch (Exception $e) {
                            // Rollback transaction on error
                            $conn->rollback();
                            $message = "Error placing document on hold: " . $e->getMessage();
                            $status = "error";
                        }
                        break;
                        
                    default:
                        $message = "Invalid action";
                        $status = "error";
                        break;
                }
            }
        } else {
            $message = "This document is not currently assigned to your office";
            $status = "error";
        }
    } else {
        $message = "Document not found";
        $status = "error";
    }
}

// Redirect back to inbox with status message
header("Location: dashboard.php?page=incoming&status=$status&message=" . urlencode($message));
exit();
