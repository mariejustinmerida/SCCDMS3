<?php
// Ensure clean JSON output
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'Super Admin') {
    echo json_encode(['success' => false, 'error' => 'Only Super Admin can manage users']);
    exit;
}

try {
    $rows = [];
    $res = $conn->query("SELECT office_id, office_name FROM offices ORDER BY office_name ASC");
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    echo json_encode(['success' => true, 'offices' => $rows]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


