<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Debug information
$debug = [
    'session_user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set',
    'session_office_id' => isset($_SESSION['office_id']) ? $_SESSION['office_id'] : 'not set',
    'date_param' => isset($_GET['date']) ? $_GET['date'] : 'not set'
];

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not authenticated', 'debug' => $debug]);
    exit;
}

if (!isset($_GET['date'])) {
    echo json_encode(['error' => 'Date parameter is required', 'debug' => $debug]);
    exit;
}

$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'];
$date = $_GET['date'];

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD', 'debug' => $debug]);
    exit;
}

try {
    // Get documents for the specific date
    $query = "SELECT d.document_id, d.title, d.status, dt.type_name, 
              CASE 
                WHEN dw.office_id = ? AND dw.status = 'current' THEN 'inbox'
                WHEN d.creator_id = ? THEN 'outgoing'
                ELSE 'other'
              END as document_type
              FROM documents d 
              JOIN document_types dt ON d.type_id = dt.type_id 
              LEFT JOIN document_workflow dw ON d.document_id = dw.document_id
              WHERE DATE(d.created_at) = ? 
              AND (d.creator_id = ? OR dw.office_id = ?)
              GROUP BY d.document_id
              ORDER BY d.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisii", $office_id, $user_id, $date, $user_id, $office_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }

    // Add debug info to the response
    $response = [
        'documents' => $documents,
        'debug' => [
            'user_id' => $user_id,
            'office_id' => $office_id,
            'date' => $date,
            'query' => $query,
            'document_count' => count($documents)
        ]
    ];

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Error retrieving documents: ' . $e->getMessage(),
        'debug' => $debug
    ]);
}
?> 