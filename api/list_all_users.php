<?php
// Ensure clean JSON output
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_error_handler(function($severity, $message){ error_log("list_all_users.php: $message"); return true; });

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
	echo json_encode(['success' => false, 'error' => 'Only Super Admin can view all users']);
	exit;
}

$users = [];
try {
    $q = $conn->query("SELECT u.user_id, u.username, u.email, u.full_name, u.role_id, u.office_id, r.role_name, o.office_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN offices o ON u.office_id = o.office_id
    ORDER BY u.created_at DESC");

    while ($q && ($row = $q->fetch_assoc())) {
    	$users[] = $row;
    }

    echo json_encode(['success' => true, 'users' => $users]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

