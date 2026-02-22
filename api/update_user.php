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
    echo json_encode(['success' => false, 'error' => 'Only Super Admin can update users']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$targetUserId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
$fullName = trim($data['full_name'] ?? '');
$email = trim($data['email'] ?? '');
$roleId = isset($data['role_id']) ? (int)$data['role_id'] : 0;
$officeId = isset($data['office_id']) ? (int)$data['office_id'] : 0;
$password = $data['password'] ?? '';

if ($targetUserId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user id']);
    exit;
}

try {
    // prevent removing own super admin rights accidentally
    if ($targetUserId === (int)$_SESSION['user_id']) {
        // allow changing own name/email/office, but keep role id unchanged
        $roleId = 0; // ignore role change
    }

    $conn->begin_transaction();

    // Basic update
    $fields = [];
    $params = [];
    $types = '';
    if ($fullName !== '') { $fields[] = 'full_name = ?'; $params[] = $fullName; $types .= 's'; }
    if ($email !== '') { $fields[] = 'email = ?'; $params[] = $email; $types .= 's'; }
    if ($officeId > 0) { $fields[] = 'office_id = ?'; $params[] = $officeId; $types .= 'i'; }
    if ($roleId > 0) { $fields[] = 'role_id = ?'; $params[] = $roleId; $types .= 'i'; }
    if ($password !== '') { $fields[] = 'password = ?'; $params[] = password_hash($password, PASSWORD_BCRYPT); $types .= 's'; }

    if (!empty($fields)) {
        // Check if updated_at column exists
        $hasUpdatedAt = false;
        $colRes = $conn->query("SHOW COLUMNS FROM users LIKE 'updated_at'");
        if ($colRes && $colRes->num_rows > 0) { $hasUpdatedAt = true; }

        $sql = 'UPDATE users SET ' . implode(', ', $fields);
        if ($hasUpdatedAt) { $sql .= ', updated_at = NOW()'; }
        $sql .= ' WHERE user_id = ?';
        $params[] = $targetUserId; $types .= 'i';
        $stmt = $conn->prepare($sql);
        if (!$stmt) { throw new Exception('DB error: ' . $conn->error); }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'User updated successfully']);
} catch (Throwable $e) {
    @$conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


