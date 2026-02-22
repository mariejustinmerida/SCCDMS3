<?php
// Document approval functions

function update_document_approval($conn, $document_id, $office_id, $action_type) {
    // Start a transaction
    $conn->begin_transaction();
    
    try {
        // Get document details
        $doc_query = "SELECT current_step, type_id FROM documents WHERE document_id = ?";
        $doc_stmt = $conn->prepare($doc_query);
        $doc_stmt->bind_param("i", $document_id);
        $doc_stmt->execute();
        $doc_result = $doc_stmt->get_result();
        
        if (!$doc_result || $doc_result->num_rows === 0) {
            throw new Exception("Document not found");
        }
        
        $doc_data = $doc_result->fetch_assoc();
        $current_step = $doc_data["current_step"];
        $type_id = $doc_data["type_id"];
        
        if ($action_type === "approve") {
            // 1. Mark current step as COMPLETED and not current
            $update_current = "UPDATE document_workflow 
                              SET status = 'COMPLETED', is_current = 0 
                              WHERE document_id = ? AND office_id = ?";
            $current_stmt = $conn->prepare($update_current);
            $current_stmt->bind_param("ii", $document_id, $office_id);
            $current_stmt->execute();
            
            // 2. Find the next step in the workflow
            $next_step_query = "SELECT ws.id, ws.office_id 
                               FROM workflow_steps ws 
                               JOIN workflow_steps current_ws ON current_ws.id = ? 
                               WHERE ws.type_id = ? 
                               AND ws.step_order > current_ws.step_order 
                               ORDER BY ws.step_order ASC LIMIT 1";
            $next_stmt = $conn->prepare($next_step_query);
            $next_stmt->bind_param("ii", $current_step, $type_id);
            $next_stmt->execute();
            $next_result = $next_stmt->get_result();
            
            if ($next_result && $next_result->num_rows > 0) {
                // There's a next step
                $next_data = $next_result->fetch_assoc();
                $next_step_id = $next_data["id"];
                $next_office_id = $next_data["office_id"];
                
                // Update document to point to next step
                $update_doc = "UPDATE documents 
                              SET current_step = ?, status = 'pending' 
                              WHERE document_id = ?";
                $doc_update_stmt = $conn->prepare($update_doc);
                $doc_update_stmt->bind_param("ii", $next_step_id, $document_id);
                $doc_update_stmt->execute();
                
                // Mark next workflow step as CURRENT
                $update_next = "UPDATE document_workflow 
                               SET status = 'CURRENT', is_current = 1 
                               WHERE document_id = ? AND office_id = ?";
                $next_update_stmt = $conn->prepare($update_next);
                $next_update_stmt->bind_param("ii", $document_id, $next_office_id);
                $next_update_stmt->execute();
                
                $result = [
                    "success" => true,
                    "has_next_step" => true,
                    "next_step_id" => $next_step_id,
                    "next_office_id" => $next_office_id
                ];
            } else {
                // No next step, document is fully approved
                $update_doc = "UPDATE documents 
                              SET current_step = NULL, status = 'approved' 
                              WHERE document_id = ?";
                $doc_update_stmt = $conn->prepare($update_doc);
                $doc_update_stmt->bind_param("i", $document_id);
                $doc_update_stmt->execute();
                
                // Mark all workflow steps as COMPLETED
                $update_all = "UPDATE document_workflow 
                              SET status = 'COMPLETED', is_current = 0 
                              WHERE document_id = ?";
                $all_update_stmt = $conn->prepare($update_all);
                $all_update_stmt->bind_param("i", $document_id);
                $all_update_stmt->execute();
                
                $result = [
                    "success" => true,
                    "has_next_step" => false
                ];
            }
        } elseif ($action_type === "reject") {
            // Mark current workflow step as REJECTED
            $update_current = "UPDATE document_workflow 
                              SET status = 'REJECTED', is_current = 0 
                              WHERE document_id = ? AND office_id = ?";
            $current_stmt = $conn->prepare($update_current);
            $current_stmt->bind_param("ii", $document_id, $office_id);
            $current_stmt->execute();
            
            // Update document status
            $update_doc = "UPDATE documents 
                          SET current_step = NULL, status = 'rejected' 
                          WHERE document_id = ?";
            $doc_update_stmt = $conn->prepare($update_doc);
            $doc_update_stmt->bind_param("i", $document_id);
            $doc_update_stmt->execute();
            
            $result = [
                "success" => true,
                "action" => "reject"
            ];
        } elseif ($action_type === "hold") {
            // Mark current workflow step as ON_HOLD
            $update_current = "UPDATE document_workflow 
                              SET status = 'ON_HOLD', is_current = 0 
                              WHERE document_id = ? AND office_id = ?";
            $current_stmt = $conn->prepare($update_current);
            $current_stmt->bind_param("ii", $document_id, $office_id);
            $current_stmt->execute();
            
            // Update document status
            $update_doc = "UPDATE documents 
                          SET status = 'on_hold' 
                          WHERE document_id = ?";
            $doc_update_stmt = $conn->prepare($update_doc);
            $doc_update_stmt->bind_param("i", $document_id);
            $doc_update_stmt->execute();
            
            $result = [
                "success" => true,
                "action" => "hold"
            ];
        }
        
        // Commit the transaction
        $conn->commit();
        return $result;
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        return [
            "success" => false,
            "error" => $e->getMessage()
        ];
    }
}
?>