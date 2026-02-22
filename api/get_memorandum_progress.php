<?php
/**
 * Get Memorandum Progress API
 * 
 * This endpoint retrieves the progress and distribution details for a memorandum
 */

session_start();
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get document ID from query parameters
$documentId = $_GET['document_id'] ?? null;

if (!$documentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Document ID is required']);
    exit();
}

try {
    // Check if document is a memorandum
    $docQuery = $conn->prepare("SELECT is_memorandum, memorandum_sent_to_all_offices, memorandum_total_offices, memorandum_read_offices FROM documents WHERE document_id = ?");
    $docQuery->bind_param("i", $documentId);
    $docQuery->execute();
    $docResult = $docQuery->get_result();
    $docData = $docResult->fetch_assoc();
    
    if (!$docData) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Document not found']);
        exit();
    }
    
    if (!$docData['is_memorandum']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Document is not a memorandum']);
        exit();
    }
    
    // Get memorandum distribution details
    $progressQuery = $conn->prepare("
        SELECT 
            md.office_id,
            md.is_read,
            md.read_at,
            md.read_by_user_id,
            o.office_name,
            u.full_name as read_by_name
        FROM memorandum_distribution md
        LEFT JOIN offices o ON md.office_id = o.office_id
        LEFT JOIN users u ON md.read_by_user_id = u.user_id
        WHERE md.document_id = ?
        ORDER BY o.office_name
    ");
    $progressQuery->bind_param("i", $documentId);
    $progressQuery->execute();
    $progressResult = $progressQuery->get_result();
    
    $offices = [];
    $totalOffices = 0;
    $readOffices = 0;
    
    while ($row = $progressResult->fetch_assoc()) {
        $offices[] = [
            'office_id' => (int)$row['office_id'],
            'office_name' => $row['office_name'],
            'is_read' => (bool)$row['is_read'],
            'read_at' => $row['read_at'],
            'read_by_name' => $row['read_by_name']
        ];
        $totalOffices++;
        if ($row['is_read']) {
            $readOffices++;
        }
    }
    
    $progress = $totalOffices > 0 ? round(($readOffices / $totalOffices) * 100, 1) : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'document_id' => (int)$documentId,
            'is_memorandum' => (bool)$docData['is_memorandum'],
            'sent_to_all_offices' => (bool)$docData['memorandum_sent_to_all_offices'],
            'total_offices' => (int)$totalOffices,
            'read_offices' => (int)$readOffices,
            'progress_percentage' => $progress,
            'offices' => $offices
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Memorandum progress error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>
