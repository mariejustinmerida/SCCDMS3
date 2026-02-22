<?php
// Include database configuration
require_once '../includes/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if document ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['error' => 'Document ID is required']);
    exit;
}

$document_id = intval($_GET['id']);

try {
    // Get document details
    $stmt = $conn->prepare("
        SELECT d.*, 
               dt.type_name, 
               u.full_name as creator_name, 
               o.office_name
        FROM documents d
        LEFT JOIN document_types dt ON d.type_id = dt.type_id
        LEFT JOIN users u ON d.creator_id = u.user_id
        LEFT JOIN offices o ON u.office_id = o.office_id
        WHERE d.document_id = ?
    ");
    
    // Check if statement preparation was successful
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $document = $result->fetch_assoc();
        
        // Generate or retrieve verification code
        if (empty($document['verification_code'])) {
            // Generate a new 6-digit verification code
            $verification_code = mt_rand(100000, 999999);
            
            // Update verification_code in the database
            $update_stmt = $conn->prepare("UPDATE documents SET verification_code = ? WHERE document_id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("si", $verification_code, $document_id);
                $update_stmt->execute();
                $document['verification_code'] = $verification_code;
            }
        }
        
        // Return document details as JSON
        echo json_encode($document);
    } else {
        echo json_encode(['error' => 'Document not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
} 