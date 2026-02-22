<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['document_id']) || !isset($data['is_urgent'])) {
    echo json_encode(['success' => false, 'error' => 'Missing document_id or is_urgent status.']);
    exit;
}

$document_id = (int)$data['document_id'];
$is_urgent = (bool)$data['is_urgent'] ? 1 : 0;
$user_id = $_SESSION['user_id'];

// Check if the user has permission to change the urgency.
// Allow if user is the creator OR if the document is in their office's workflow (they can see it in inbox)
$user_office_id = $_SESSION['office_id'] ?? 0;

$check_sql = "SELECT d.creator_id, 
                     CASE WHEN d.creator_id = ? THEN 1 ELSE 0 END as is_creator,
                     CASE WHEN EXISTS (
                         SELECT 1 FROM document_workflow dw 
                         WHERE dw.document_id = d.document_id 
                         AND dw.office_id = ? 
                         AND UPPER(dw.status) = 'CURRENT'
                     ) THEN 1 ELSE 0 END as is_in_workflow
              FROM documents d 
              WHERE d.document_id = ?";
$stmt_check = $conn->prepare($check_sql);
$stmt_check->bind_param("iii", $user_id, $user_office_id, $document_id);
$stmt_check->execute();
$result = $stmt_check->get_result();
$document = $result->fetch_assoc();

if (!$document) {
    echo json_encode(['success' => false, 'error' => 'Document not found.']);
    exit;
}

// Allow if user is creator OR document is in their office workflow
if (!$document['is_creator'] && !$document['is_in_workflow']) {
    echo json_encode(['success' => false, 'error' => 'You do not have permission to modify this document.']);
    exit;
}

$sql = "UPDATE documents SET is_urgent = ? WHERE document_id = ?";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to prepare statement: ' . $conn->error]);
    exit;
}

$stmt->bind_param("ii", $is_urgent, $document_id);

if ($stmt->execute()) {
    // Optionally, log this action
    // require_once '../includes/activity_logger.php';
    // log_activity($user_id, 'set_urgency', "Set document ID $document_id urgency to " . ($is_urgent ? 'urgent' : 'not urgent'));
    echo json_encode(['success' => true, 'message' => 'Document urgency updated successfully.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update document urgency: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?> 