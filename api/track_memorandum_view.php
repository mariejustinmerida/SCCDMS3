<?php
/**
 * Track Memorandum View API
 * 
 * This endpoint tracks when offices view memorandums and updates progress tracking
 */

session_start();
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$documentId = $input['document_id'] ?? null;
$action = $input['action'] ?? 'viewed'; // viewed, downloaded, printed

if (!$documentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Document ID is required']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    // Get user's office
    $userQuery = $conn->prepare("SELECT office_id FROM users WHERE user_id = ?");
    $userQuery->bind_param("i", $userId);
    $userQuery->execute();
    $userResult = $userQuery->get_result();
    $userData = $userResult->fetch_assoc();
    
    if (!$userData || !$userData['office_id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User office not found']);
        exit();
    }
    
    $officeId = $userData['office_id'];
    
    // Check if document is a memorandum
    $docQuery = $conn->prepare("SELECT is_memorandum, memorandum_sent_to_all_offices FROM documents WHERE document_id = ?");
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
    
    // Check if this office is in the memorandum distribution
    $distQuery = $conn->prepare("SELECT distribution_id, is_read FROM memorandum_distribution WHERE document_id = ? AND office_id = ?");
    $distQuery->bind_param("ii", $documentId, $officeId);
    $distQuery->execute();
    $distResult = $distQuery->get_result();
    $distData = $distResult->fetch_assoc();
    
    if (!$distData) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Office not in memorandum distribution']);
        exit();
    }
    
    // Log the action
    $logQuery = $conn->prepare("INSERT INTO memorandum_read_logs (document_id, office_id, user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $logQuery->bind_param("iiisss", $documentId, $officeId, $userId, $action, $ipAddress, $userAgent);
    $logQuery->execute();
    
    // Update distribution read status if not already read
    if (!$distData['is_read']) {
        $updateQuery = $conn->prepare("UPDATE memorandum_distribution SET is_read = 1, read_at = NOW(), read_by_user_id = ? WHERE distribution_id = ?");
        $updateQuery->bind_param("ii", $userId, $distData['distribution_id']);
        $updateQuery->execute();
        
        // Update document read count
        $updateDocQuery = $conn->prepare("UPDATE documents SET memorandum_read_offices = memorandum_read_offices + 1 WHERE document_id = ?");
        $updateDocQuery->bind_param("i", $documentId);
        $updateDocQuery->execute();
    }
    
    // Get updated progress
    $progressQuery = $conn->prepare("
        SELECT 
            d.memorandum_total_offices,
            d.memorandum_read_offices,
            md.office_id,
            md.is_read,
            md.read_at,
            o.office_name
        FROM documents d
        LEFT JOIN memorandum_distribution md ON d.document_id = md.document_id
        LEFT JOIN offices o ON md.office_id = o.office_id
        WHERE d.document_id = ?
        ORDER BY md.office_id
    ");
    $progressQuery->bind_param("i", $documentId);
    $progressQuery->execute();
    $progressResult = $progressQuery->get_result();
    
    $offices = [];
    $totalOffices = 0;
    $readOffices = 0;
    
    while ($row = $progressResult->fetch_assoc()) {
        if ($row['office_id']) {
            $offices[] = [
                'office_id' => $row['office_id'],
                'office_name' => $row['office_name'],
                'is_read' => (bool)$row['is_read'],
                'read_at' => $row['read_at']
            ];
            $totalOffices++;
            if ($row['is_read']) {
                $readOffices++;
            }
        }
    }
    
    $progress = $totalOffices > 0 ? round(($readOffices / $totalOffices) * 100, 1) : 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Memorandum view tracked successfully',
        'data' => [
            'progress' => $progress,
            'total_offices' => $totalOffices,
            'read_offices' => $readOffices,
            'offices' => $offices
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Memorandum tracking error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?> 