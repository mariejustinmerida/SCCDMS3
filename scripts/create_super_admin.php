<?php
// One-time script to create Super Admin role and a super admin user
// Run via browser or CLI: http://yourhost/scripts/create_super_admin.php

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

require_once __DIR__ . '/../includes/config.php';

function respond($ok, $data = []) {
	header('Content-Type: application/json');
	echo json_encode(array_merge(['success' => $ok], $data));
	exit;
}

try {
	// Ensure roles table has Super Admin
	$roleName = 'Super Admin';
	$roleCheck = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ?");
	$roleCheck->bind_param('s', $roleName);
	$roleCheck->execute();
	$roleRes = $roleCheck->get_result();

	if ($roleRes && $roleRes->num_rows > 0) {
		$roleRow = $roleRes->fetch_assoc();
		$roleId = (int)$roleRow['role_id'];
	} else {
		$insertRole = $conn->prepare("INSERT INTO roles (role_name) VALUES (?)");
		$insertRole->bind_param('s', $roleName);
		$insertRole->execute();
		$roleId = (int)$conn->insert_id;
	}

	// Create super admin user if not exists (by email)
	$email = isset($_GET['email']) ? trim($_GET['email']) : 'superadmin@sccpag.edu.ph';
	$username = isset($_GET['username']) ? trim($_GET['username']) : 'superadmin';
	$fullName = isset($_GET['full_name']) ? trim($_GET['full_name']) : 'Super Administrator';
	$officeId = 1; // default to President office or main office

	$userCheck = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR username = ?");
	$userCheck->bind_param('ss', $email, $username);
	$userCheck->execute();
	$userRes = $userCheck->get_result();

	if ($userRes && $userRes->num_rows > 0) {
		$userRow = $userRes->fetch_assoc();
		respond(true, [
			'message' => 'Super admin already exists',
			'user_id' => (int)$userRow['user_id'],
			'role_id' => $roleId
		]);
	}

	// Generate a strong random password if not provided
	$plainPassword = isset($_GET['password']) && strlen($_GET['password']) >= 10
		? $_GET['password']
		: bin2hex(random_bytes(8)) . '!Sa1';
	$hash = password_hash($plainPassword, PASSWORD_BCRYPT);

	$insertUser = $conn->prepare(
		"INSERT INTO users (username, password, email, full_name, role_id, office_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())"
	);
	$insertUser->bind_param('ssssii', $username, $hash, $email, $fullName, $roleId, $officeId);
	$insertUser->execute();
	$newUserId = (int)$conn->insert_id;

	respond(true, [
		'message' => 'Super admin created',
		'user_id' => $newUserId,
		'role_id' => $roleId,
		'username' => $username,
		'email' => $email,
		'initial_password' => $plainPassword
	]);
} catch (Throwable $e) {
	respond(false, ['error' => $e->getMessage()]);
}
?>

