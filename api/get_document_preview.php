<?php
/**
 * Get Document Preview API
 * 
 * This endpoint provides basic document information and workflow progress
 * for documents that are coming to an office but not yet available for action.
 */

require_once '../includes/config.php';
// Always return JSON
header('Content-Type: application/json');

// Ensure no stray output corrupts JSON
if (!headers_sent()) {
    // Suppress PHP notices/warnings from being output to the client
    @ini_set('display_errors', '0');
}
ob_start();

function json_response(int $code, array $data): void {
    http_response_code($code);
    // Clear any buffered output to avoid mixing HTML with JSON
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($data);
    // End all buffers to guarantee clean output
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    exit();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    json_response(401, ['success' => false, 'error' => 'Unauthorized']);
}

// Get document ID from request
$document_id = isset($_GET['document_id']) ? (int)$_GET['document_id'] : 0;
$user_office_id = $_SESSION['office_id'] ?? 0;

if (!$document_id) {
    json_response(400, ['success' => false, 'error' => 'Document ID is required']);
}

try {
    // Get basic document information including content
    $doc_sql = "SELECT 
                    d.document_id,
                    d.title,
                    d.status,
                    d.created_at,
                    d.file_path,
                    d.content,
                    d.description,
                    d.google_doc_id,
                    dt.type_name,
                    u.full_name as creator_name,
                    o.office_name as creator_office
                FROM documents d
                LEFT JOIN document_types dt ON d.type_id = dt.type_id
                LEFT JOIN users u ON d.creator_id = u.user_id
                LEFT JOIN offices o ON u.office_id = o.office_id
                WHERE d.document_id = ?";
    
    $doc_stmt = $conn->prepare($doc_sql);
    $doc_stmt->bind_param('i', $document_id);
    $doc_stmt->execute();
    $doc_result = $doc_stmt->get_result();
    
    if (!$doc_result || $doc_result->num_rows === 0) {
        throw new Exception('Document not found');
    }
    
    $document = $doc_result->fetch_assoc();
    $document['document_code'] = 'DOC-' . str_pad($document['document_id'], 3, '0', STR_PAD_LEFT);
    
    // Get workflow information
    $workflow_sql = "SELECT 
                        dw.step_order,
                        dw.status,
                        dw.office_id,
                        o.office_name,
                        CASE 
                            WHEN dw.status = 'CURRENT' THEN 1
                            ELSE 0
                        END as is_current,
                        CASE 
                            WHEN dw.status = 'COMPLETED' OR dw.status = 'APPROVED' THEN 1
                            ELSE 0
                        END as is_completed
                    FROM document_workflow dw
                    LEFT JOIN offices o ON dw.office_id = o.office_id
                    WHERE dw.document_id = ?
                    ORDER BY dw.step_order ASC";
    
    $workflow_stmt = $conn->prepare($workflow_sql);
    $workflow_stmt->bind_param('i', $document_id);
    $workflow_stmt->execute();
    $workflow_result = $workflow_stmt->get_result();
    
    $workflow = [];
    while ($row = $workflow_result->fetch_assoc()) {
        $workflow[] = $row;
    }
    
    // Check if this document is actually coming to the user's office
    $user_step_sql = "SELECT step_order FROM document_workflow 
                      WHERE document_id = ? AND office_id = ?";
    $user_step_stmt = $conn->prepare($user_step_sql);
    $user_step_stmt->bind_param('ii', $document_id, $user_office_id);
    $user_step_stmt->execute();
    $user_step_result = $user_step_stmt->get_result();
    
    if ($user_step_result->num_rows === 0) {
        throw new Exception('Document is not routed to your office');
    }
    
    $user_step = $user_step_result->fetch_assoc();
    $current_step_sql = "SELECT step_order FROM document_workflow 
                        WHERE document_id = ? AND status = 'CURRENT'";
    $current_step_stmt = $conn->prepare($current_step_sql);
    $current_step_stmt->bind_param('i', $document_id);
    $current_step_stmt->execute();
    $current_step_result = $current_step_stmt->get_result();
    
    if ($current_step_result->num_rows === 0) {
        throw new Exception('Document workflow not found');
    }
    
    $current_step = $current_step_result->fetch_assoc();
    
    // Check if document is already at user's office or past it
    if ($user_step['step_order'] <= $current_step['step_order']) {
        throw new Exception('Document is already available for your office');
    }
    
    // Return the preview data
    json_response(200, [
        'success' => true,
        'data' => [
            'document' => $document,
            'workflow' => $workflow,
            'user_step_order' => $user_step['step_order'],
            'current_step_order' => $current_step['step_order'],
            'steps_away' => $user_step['step_order'] - $current_step['step_order']
        ]
    ]);
    
} catch (Exception $e) {
    json_response(400, [
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 