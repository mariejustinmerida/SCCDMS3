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
    $res = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_name ASC");
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    echo json_encode(['success' => true, 'roles' => $rows]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


