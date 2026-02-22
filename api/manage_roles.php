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
    echo json_encode(['success' => false, 'error' => 'Only Super Admin can manage roles']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // List all roles
            $rows = [];
            $res = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_name ASC");
            while ($res && ($row = $res->fetch_assoc())) {
                $rows[] = $row;
            }
            echo json_encode(['success' => true, 'roles' => $rows]);
            break;
            
        case 'POST':
            // Create new role
            $raw_input = file_get_contents('php://input');
            $data = json_decode($raw_input, true);
            
            if (!isset($data['role_name']) || empty(trim($data['role_name']))) {
                echo json_encode(['success' => false, 'error' => 'Role name is required']);
                exit;
            }
            
            $role_name = trim($data['role_name']);
            
            // Check if role already exists
            $check_stmt = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ?");
            $check_stmt->bind_param("s", $role_name);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo json_encode(['success' => false, 'error' => 'Role already exists']);
                exit;
            }
            
            // Insert new role
            $stmt = $conn->prepare("INSERT INTO roles (role_name) VALUES (?)");
            $stmt->bind_param("s", $role_name);
            
            if ($stmt->execute()) {
                $role_id = $conn->insert_id;
                echo json_encode(['success' => true, 'role_id' => $role_id, 'message' => 'Role created successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create role: ' . $conn->error]);
            }
            break;
            
        case 'PUT':
            // Update role
            $raw_input = file_get_contents('php://input');
            $data = json_decode($raw_input, true);
            
            if (!isset($data['role_id']) || !isset($data['role_name']) || empty(trim($data['role_name']))) {
                echo json_encode(['success' => false, 'error' => 'Role ID and name are required']);
                exit;
            }
            
            $role_id = (int)$data['role_id'];
            $role_name = trim($data['role_name']);
            
            // Check if role exists
            $check_stmt = $conn->prepare("SELECT role_id FROM roles WHERE role_id = ?");
            $check_stmt->bind_param("i", $role_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'error' => 'Role not found']);
                exit;
            }
            
            // Check if another role with same name exists
            $check_stmt2 = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ? AND role_id != ?");
            $check_stmt2->bind_param("si", $role_name, $role_id);
            $check_stmt2->execute();
            $result2 = $check_stmt2->get_result();
            
            if ($result2->num_rows > 0) {
                echo json_encode(['success' => false, 'error' => 'Role name already exists']);
                exit;
            }
            
            // Update role
            $stmt = $conn->prepare("UPDATE roles SET role_name = ? WHERE role_id = ?");
            $stmt->bind_param("si", $role_name, $role_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update role: ' . $conn->error]);
            }
            break;
            
        case 'DELETE':
            // Delete role - handle all foreign key constraints
            $raw_input = file_get_contents('php://input');
            $data = json_decode($raw_input, true);
            
            if (!isset($data['role_id'])) {
                echo json_encode(['success' => false, 'error' => 'Role ID is required']);
                exit;
            }
            
            $role_id = (int)$data['role_id'];
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Temporarily disable foreign key checks
                $conn->query("SET FOREIGN_KEY_CHECKS = 0");
                
                // Delete from role_office_mapping first
                $delete_mapping_stmt = $conn->prepare("DELETE FROM role_office_mapping WHERE role_id = ?");
                $delete_mapping_stmt->bind_param("i", $role_id);
                $delete_mapping_stmt->execute();
                
                // Set users with this role to a default role
                // First, check if there's a default role (like 'User' or role_id = 3)
                $default_role_check = $conn->prepare("SELECT role_id FROM roles WHERE role_name = 'User' LIMIT 1");
                $default_role_check->execute();
                $default_role_result = $default_role_check->get_result();
                
                if ($default_role_result && $default_role_result->num_rows > 0) {
                    $default_role = $default_role_result->fetch_assoc();
                    $default_role_id = $default_role['role_id'];
                    $update_users_stmt = $conn->prepare("UPDATE users SET role_id = ? WHERE role_id = ?");
                    $update_users_stmt->bind_param("ii", $default_role_id, $role_id);
                    $update_users_stmt->execute();
                } else {
                    // If no default role, try to set to first available role
                    $first_role_check = $conn->prepare("SELECT role_id FROM roles WHERE role_id != ? LIMIT 1");
                    $first_role_check->bind_param("i", $role_id);
                    $first_role_check->execute();
                    $first_role_result = $first_role_check->get_result();
                    
                    if ($first_role_result && $first_role_result->num_rows > 0) {
                        $first_role = $first_role_result->fetch_assoc();
                        $first_role_id = $first_role['role_id'];
                        $update_users_stmt = $conn->prepare("UPDATE users SET role_id = ? WHERE role_id = ?");
                        $update_users_stmt->bind_param("ii", $first_role_id, $role_id);
                        $update_users_stmt->execute();
                    }
                }
                
                // Delete the role
                $delete_stmt = $conn->prepare("DELETE FROM roles WHERE role_id = ?");
                $delete_stmt->bind_param("i", $role_id);
                $delete_stmt->execute();
                
                // Re-enable foreign key checks
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                
                // Commit transaction
                $conn->commit();
                
                echo json_encode(['success' => true, 'message' => 'Role deleted successfully (related records cleaned up)']);
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                echo json_encode(['success' => false, 'error' => 'Failed to delete role: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
ob_end_flush();
?>

