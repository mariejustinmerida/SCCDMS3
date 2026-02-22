<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

header('Content-Type: application/json');

// Get the date parameter
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'];

// Get all documents created on the specified date that are relevant to the user's office
$query = "SELECT d.document_id, d.title, d.status, d.created_at, dt.type_name 
          FROM documents d
          JOIN document_types dt ON d.type_id = dt.type_id
          WHERE DATE(d.created_at) = ? 
          AND (d.creator_id = ? 
               OR d.current_step IN (
                  SELECT step_id FROM workflow_steps 
                  WHERE office_id = ?
               ))
          ORDER BY d.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("sii", $date, $user_id, $office_id);
$stmt->execute();
$result = $stmt->get_result();

$documents = [];
while ($row = $result->fetch_assoc()) {
    // Format the date for display
    $row['created_at'] = date('M j, Y g:i A', strtotime($row['created_at']));
    $documents[] = $row;
}

echo json_encode($documents);
?>
<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user_id'];
$documents = [];

if (isset($_GET['date']) && !empty($_GET['date'])) {
    $date = $_GET['date'];
    
    // Validate date format
    if (strtotime($date) === false) {
        echo json_encode([]);
        exit;
    }
    
    // Get documents for this date
    $query = "SELECT d.document_id, d.title, d.status, dt.type_name 
              FROM documents d
              JOIN document_types dt ON d.type_id = dt.type_id
              WHERE DATE(d.created_at) = ? 
              AND (d.creator_id = ? OR d.current_step IN (
                SELECT step_id FROM workflow_steps WHERE office_id = (
                  SELECT office_id FROM users WHERE user_id = ?)))
              ORDER BY d.created_at DESC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $date, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
}

echo json_encode($documents);
?>
