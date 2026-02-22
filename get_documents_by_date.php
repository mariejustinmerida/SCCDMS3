<?php
session_start();
require_once './includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

if (!isset($_GET['date'])) {
    echo json_encode(['error' => 'Date parameter is required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'];
$date = $_GET['date'];

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
    exit;
}

try {
    // Get documents for the specific date
    $query = "SELECT d.document_id, d.title, d.status, dt.type_name, 
              CASE 
                WHEN d.current_step IN (
                  SELECT step_id FROM workflow_steps WHERE office_id = ?
                ) AND d.status = 'pending' THEN 'inbox'
                WHEN d.creator_id = ? THEN 'outgoing'
                ELSE 'other'
              END as document_type
              FROM documents d 
              JOIN document_types dt ON d.type_id = dt.type_id 
              WHERE DATE(d.created_at) = ? 
              AND (d.creator_id = ? OR d.current_step IN (
                  SELECT step_id FROM workflow_steps WHERE office_id = ?))
              ORDER BY d.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisii", $office_id, $user_id, $date, $user_id, $office_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }

    echo json_encode($documents);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error retrieving documents: ' . $e->getMessage()]);
}
?> 