<?php
// Document workflow management functions

/**
 * Process a document revision
 * 
 * @param object $conn Database connection
 * @param int $document_id Document ID
 * @param int $creator_id Creator user ID
 * @param string $comments Optional comments
 * @return array Result with success status and additional data
 */
function process_document_revision($conn, $document_id, $creator_id, $comments = "") {
    // Start a transaction
    $conn->begin_transaction();
    
    try {
        // Get the office that requested the revision
        $revision_query = "SELECT dw.office_id, dw.step_order 
                          FROM document_workflow dw 
                          WHERE dw.document_id = ? AND dw.status = 'revision_requested'
                          ORDER BY dw.step_order DESC LIMIT 1";
        $revision_stmt = $conn->prepare($revision_query);
        $revision_stmt->bind_param("i", $document_id);
        $revision_stmt->execute();
        $revision_result = $revision_stmt->get_result();
        
        if (!$revision_result || $revision_result->num_rows === 0) {
            throw new Exception("No revision request found for this document");
        }
        
        $revision_data = $revision_result->fetch_assoc();
        $requesting_office_id = $revision_data['office_id'];
        $requesting_step_order = $revision_data['step_order'];
        
        // Update all completed steps to remain completed
        // Update the revision_requested step to pending (will be set to current when reached)
        // Set all other steps to pending
        $update_steps = "UPDATE document_workflow 
                        SET status = CASE 
                            WHEN status = 'COMPLETED' THEN 'COMPLETED'
                            WHEN office_id = ? THEN 'PENDING'
                            ELSE 'PENDING'
                        END
                        WHERE document_id = ?";
        $update_stmt = $conn->prepare($update_steps);
        $update_stmt->bind_param("ii", $requesting_office_id, $document_id);
        $update_stmt->execute();
        
        // Find the first non-completed step and set it to current
        $next_step_query = "SELECT workflow_id FROM document_workflow 
                           WHERE document_id = ? AND status = 'PENDING'
                           ORDER BY step_order ASC LIMIT 1";
        $next_stmt = $conn->prepare($next_step_query);
        $next_stmt->bind_param("i", $document_id);
        $next_stmt->execute();
        $next_result = $next_stmt->get_result();
        
        if ($next_result && $next_result->num_rows > 0) {
            $next_data = $next_result->fetch_assoc();
            $next_workflow_id = $next_data['workflow_id'];
            
            // Set the next step to current
            $update_next = "UPDATE document_workflow SET status = 'CURRENT' WHERE workflow_id = ?";
            $next_update_stmt = $conn->prepare($update_next);
            $next_update_stmt->bind_param("i", $next_workflow_id);
            $next_update_stmt->execute();
        }
        
        // Update document status to pending
        $update_doc = "UPDATE documents SET status = 'pending', updated_at = NOW() WHERE document_id = ?";
        $doc_update_stmt = $conn->prepare($update_doc);
        $doc_update_stmt->bind_param("i", $document_id);
        $doc_update_stmt->execute();
        
        // Log the revision action
        $log_sql = "INSERT INTO document_logs (document_id, user_id, action, details, created_at) 
                   VALUES (?, ?, 'revised', ?, NOW())";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("iis", $document_id, $creator_id, $comments);
        $log_stmt->execute();
        
        // Commit the transaction
        $conn->commit();
        
        return [
            "success" => true,
            "action" => "revision",
            "requesting_office_id" => $requesting_office_id
        ];
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        return [
            "success" => false,
            "error" => $e->getMessage()
        ];
    }
}

/**
 * Process a document action (approve, reject, hold)
 * 
 * @param object $conn Database connection
 * @param int $document_id Document ID
 * @param int $office_id Current office ID
 * @param string $action_type Action type (approve, reject, hold)
 * @param string $comments Optional comments
 * @return array Result with success status and additional data
 */
function process_document_action($conn, $document_id, $office_id, $action_type, $comments = "") {
    // No transaction here, assume it's handled by the caller
    
    try {
        // 1. Get current workflow step for this document and office
        // CORRECTED: Use 'CURRENT' (uppercase) to match the database ENUM value
        $workflow_query = "SELECT workflow_id, step_order, status FROM document_workflow 
                          WHERE document_id = ? AND office_id = ? AND (status = 'CURRENT' OR status = 'ON_HOLD')";  
        $workflow_stmt = $conn->prepare($workflow_query);
        $workflow_stmt->bind_param("is", $document_id, $office_id);
        $workflow_stmt->execute();
        $workflow_result = $workflow_stmt->get_result();
        
        if (!$workflow_result || $workflow_result->num_rows === 0) {
            throw new Exception("No current or on-hold workflow step found for this document and office. DocID: $document_id, OfficeID: $office_id");
        }
        
        $workflow_data = $workflow_result->fetch_assoc();
        $workflow_id = $workflow_data["workflow_id"];

        // 2. Process based on action type
        switch ($action_type) {
            case "approve":
                $update_current = "UPDATE document_workflow SET status = 'COMPLETED', completed_at = NOW(), comments = ? WHERE workflow_id = ?";
                $current_stmt = $conn->prepare($update_current);
                $current_stmt->bind_param("si", $comments, $workflow_id);
                $current_stmt->execute();

                $next_step_query = "SELECT workflow_id FROM document_workflow WHERE document_id = ? AND status = 'PENDING' ORDER BY step_order ASC LIMIT 1";
                $next_stmt = $conn->prepare($next_step_query);
                $next_stmt->bind_param("i", $document_id);
                $next_stmt->execute();
                $next_result = $next_stmt->get_result();

                if ($next_result->num_rows > 0) {
                    $next_workflow_id = $next_result->fetch_assoc()['workflow_id'];
                    $update_next = "UPDATE document_workflow SET status = 'CURRENT' WHERE workflow_id = ?";
                    $next_update_stmt = $conn->prepare($update_next);
                    $next_update_stmt->bind_param("i", $next_workflow_id);
                    $next_update_stmt->execute();
                    $update_doc = "UPDATE documents SET status = 'pending', updated_at = NOW() WHERE document_id = ?";
                    $doc_update_stmt = $conn->prepare($update_doc);
                    $doc_update_stmt->bind_param("i", $document_id);
                    $doc_update_stmt->execute();
                    $result = ["success" => true, "final_approval" => false];
                } else {
                    $update_doc = "UPDATE documents SET status = 'approved', updated_at = NOW() WHERE document_id = ?";
                    $doc_update_stmt = $conn->prepare($update_doc);
                    $doc_update_stmt->bind_param("i", $document_id);
                    $doc_update_stmt->execute();
                    $result = ["success" => true, "final_approval" => true];
                }
                break;

            case "reject":
                $update_workflow = "UPDATE document_workflow SET status = 'REJECTED', comments = ? WHERE workflow_id = ?";
                $workflow_update_stmt = $conn->prepare($update_workflow);
                $workflow_update_stmt->bind_param("si", $comments, $workflow_id);
                $workflow_update_stmt->execute();
                $update_doc = "UPDATE documents SET status = 'rejected', updated_at = NOW() WHERE document_id = ?";
                $doc_update_stmt = $conn->prepare($update_doc);
                $doc_update_stmt->bind_param("i", $document_id);
                $doc_update_stmt->execute();
                $result = ["success" => true];
                break;

            case "hold":
                $update_doc = "UPDATE documents SET status = 'on_hold', updated_at = NOW() WHERE document_id = ?";
                $doc_update_stmt = $conn->prepare($update_doc);
                $doc_update_stmt->bind_param("i", $document_id);
                $doc_update_stmt->execute();

                $update_workflow = "UPDATE document_workflow SET status = 'ON_HOLD', comments = ? WHERE workflow_id = ?";
                $workflow_update_stmt = $conn->prepare($update_workflow);
                $workflow_update_stmt->bind_param("si", $comments, $workflow_id);
                $workflow_update_stmt->execute();
                
                $result = ["success" => true];
                break;

            case "request_revision":
                // This action only updates the workflow step, the document status is handled in the calling script
                $update_workflow = "UPDATE document_workflow SET status = 'REVISION_REQUESTED', comments = ? WHERE workflow_id = ?";
                $workflow_update_stmt = $conn->prepare($update_workflow);
                if (!$workflow_update_stmt) {
                    throw new Exception("Prepare failed for workflow update: " . $conn->error);
                }
                $workflow_update_stmt->bind_param("si", $comments, $workflow_id);
                if (!$workflow_update_stmt->execute()) {
                    throw new Exception("Execute failed for workflow update: " . $workflow_update_stmt->error);
                }
                
                // Also update the main document status to 'revision'
                $update_doc = "UPDATE documents SET status = 'revision' WHERE document_id = ?";
                $doc_update_stmt = $conn->prepare($update_doc);
                if (!$doc_update_stmt) {
                    throw new Exception("Prepare failed for document update: " . $conn->error);
                }
                $doc_update_stmt->bind_param("i", $document_id);
                if (!$doc_update_stmt->execute()) {
                    throw new Exception("Execute failed for document update: " . $doc_update_stmt->error);
                }
                
                $result = ["success" => true];
                break;

            case "resume":
                $update_doc = "UPDATE documents SET status = 'pending', updated_at = NOW() WHERE document_id = ?";
                $doc_update_stmt = $conn->prepare($update_doc);
                $doc_update_stmt->bind_param("i", $document_id);
                $doc_update_stmt->execute();

                $update_workflow = "UPDATE document_workflow SET status = 'CURRENT', comments = ? WHERE workflow_id = ?";
                $workflow_update_stmt = $conn->prepare($update_workflow);
                $workflow_update_stmt->bind_param("si", $comments, $workflow_id);
                $workflow_update_stmt->execute();
                
                $result = ["success" => true];
                break;
            
            case "revision":
                $update_workflow = "UPDATE document_workflow SET status = 'REVISION_REQUESTED', comments = ? WHERE workflow_id = ?";
                $workflow_update_stmt = $conn->prepare($update_workflow);
                $workflow_update_stmt->bind_param("si", $comments, $workflow_id);
                $workflow_update_stmt->execute();
                $update_doc = "UPDATE documents SET status = 'revision', updated_at = NOW() WHERE document_id = ?";
                $doc_update_stmt = $conn->prepare($update_doc);
                $doc_update_stmt->bind_param("i", $document_id);
                $doc_update_stmt->execute();
                $result = ["success" => true];
                break;

            default:
                throw new Exception("Invalid action type specified.");
        }
        
        return $result;
        
    } catch (Exception $e) {
        // We are not rolling back here, assuming the caller will handle it
        return ["success" => false, "error" => $e->getMessage()];
    }
}

/**
 * Create a new document with its workflow steps
 * 
 * @param object $conn Database connection
 * @param string $title Document title
 * @param int $type_id Document type ID
 * @param int $creator_id Creator user ID
 * @param string $google_doc_id Optional Google Doc ID
 * @param array $office_ids Ordered array of office IDs for workflow
 * @return array Result with success status and document ID
 */
function create_document_with_workflow($conn, $title, $type_id, $creator_id, $google_doc_id = null, $office_ids = []) {
    // Start a transaction
    $conn->begin_transaction();
    
    try {
        // 1. Insert the document
        $doc_query = "INSERT INTO documents (title, type_id, creator_id, google_doc_id, status) 
                      VALUES (?, ?, ?, ?, 'pending')";  
        $doc_stmt = $conn->prepare($doc_query);
        $doc_stmt->bind_param("siis", $title, $type_id, $creator_id, $google_doc_id);
        $doc_stmt->execute();
        
        $document_id = $conn->insert_id;
        
        if (empty($office_ids)) {
            // If no offices specified, get default workflow for this document type
            $workflow_query = "SELECT office_id, step_order FROM workflow_steps 
                              WHERE type_id = ? ORDER BY step_order ASC";  
            $workflow_stmt = $conn->prepare($workflow_query);
            $workflow_stmt->bind_param("i", $type_id);
            $workflow_stmt->execute();
            $workflow_result = $workflow_stmt->get_result();
            
            if ($workflow_result && $workflow_result->num_rows > 0) {
                $step_order = 1;
                $first_step = true;
                
                while ($step = $workflow_result->fetch_assoc()) {
                    $office_id = $step["office_id"];
                    $status = $first_step ? "current" : "pending";
                    
                    // Create workflow step
                    $step_query = "INSERT INTO document_workflow (document_id, office_id, step_order, status) 
                                  VALUES (?, ?, ?, ?)";  
                    $step_stmt = $conn->prepare($step_query);
                    $step_stmt->bind_param("iiis", $document_id, $office_id, $step_order, $status);
                    $step_stmt->execute();
                    
                    $step_order++;
                    $first_step = false;
                }
            } else {
                throw new Exception("No workflow steps defined for this document type");
            }
        } else {
            // Use the provided office IDs for workflow
            $step_order = 1;
            foreach ($office_ids as $office_id) {
                $status = ($step_order === 1) ? "current" : "pending";
                
                // Create workflow step
                $step_query = "INSERT INTO document_workflow (document_id, office_id, step_order, status) 
                              VALUES (?, ?, ?, ?)";  
                $step_stmt = $conn->prepare($step_query);
                $step_stmt->bind_param("iiis", $document_id, $office_id, $step_order, $status);
                $step_stmt->execute();
                
                $step_order++;
            }
        }
        
        // Create a log entry
        $log_query = "INSERT INTO document_logs (document_id, user_id, action, details) 
                      VALUES (?, ?, 'create', 'Document created')";  
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bind_param("ii", $document_id, $creator_id);
        $log_stmt->execute();
        
        // Commit the transaction
        $conn->commit();
        
        return [
            "success" => true,
            "document_id" => $document_id
        ];
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        return [
            "success" => false,
            "error" => $e->getMessage()
        ];
    }
}

/**
 * Get documents awaiting action for a specific office
 * 
 * @param object $conn Database connection
 * @param int $office_id Office ID
 * @param int $page Page number for pagination
 * @param int $items_per_page Items per page
 * @param string $search Optional search term
 * @return array Result with documents and pagination info
 */
function get_incoming_documents($conn, $office_id, $page = 1, $items_per_page = 10, $search = "") {
    $offset = ($page - 1) * $items_per_page;
    $search_condition = "";
    
    if (!empty($search)) {
        $search = "%" . $search . "%";
        $search_condition = " AND (d.title LIKE ? OR u.full_name LIKE ?)"; 
    }
    
    // Count total records
    $count_sql = "SELECT COUNT(*) as total FROM documents d
                  JOIN document_workflow dw ON d.document_id = dw.document_id
                  JOIN users u ON d.creator_id = u.user_id
                  WHERE dw.office_id = ? AND dw.status = 'current' $search_condition";
    
    $count_stmt = $conn->prepare($count_sql);
    
    if (!empty($search)) {
        $count_stmt->bind_param("iss", $office_id, $search, $search);
    } else {
        $count_stmt->bind_param("i", $office_id);
    }
    
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_records = $count_result->fetch_assoc()["total"];
    $total_pages = ceil($total_records / $items_per_page);
    
    // Get documents
    $sql = "SELECT d.document_id, d.title, d.created_at, d.status, 
                  u.full_name as creator_name, dt.type_name,
                  dw.workflow_id, dw.status as workflow_status
           FROM documents d
           JOIN document_workflow dw ON d.document_id = dw.document_id
           JOIN users u ON d.creator_id = u.user_id
           JOIN document_types dt ON d.type_id = dt.type_id
           WHERE dw.office_id = ? AND dw.status = 'current' $search_condition
           ORDER BY d.created_at DESC
           LIMIT ?, ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($search)) {
        $stmt->bind_param("issii", $office_id, $search, $search, $offset, $items_per_page);
    } else {
        $stmt->bind_param("iii", $office_id, $offset, $items_per_page);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    
    return [
        "documents" => $documents,
        "total_records" => $total_records,
        "total_pages" => $total_pages,
        "current_page" => $page
    ];
}
?>