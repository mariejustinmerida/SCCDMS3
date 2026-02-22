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
    echo json_encode(['success' => false, 'error' => 'Only Super Admin can manage offices']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // List all offices
            $rows = [];
            $res = $conn->query("SELECT office_id, office_name FROM offices ORDER BY office_name ASC");
            while ($res && ($row = $res->fetch_assoc())) {
                $rows[] = $row;
            }
            echo json_encode(['success' => true, 'offices' => $rows]);
            break;
            
        case 'POST':
            // Create new office
            $raw_input = file_get_contents('php://input');
            $data = json_decode($raw_input, true);
            
            if (!isset($data['office_name']) || empty(trim($data['office_name']))) {
                echo json_encode(['success' => false, 'error' => 'Office name is required']);
                exit;
            }
            
            $office_name = trim($data['office_name']);
            
            // Check if office already exists
            $check_stmt = $conn->prepare("SELECT office_id FROM offices WHERE office_name = ?");
            $check_stmt->bind_param("s", $office_name);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo json_encode(['success' => false, 'error' => 'Office already exists']);
                exit;
            }
            
            // Insert new office
            $stmt = $conn->prepare("INSERT INTO offices (office_name) VALUES (?)");
            $stmt->bind_param("s", $office_name);
            
            if ($stmt->execute()) {
                $office_id = $conn->insert_id;
                echo json_encode(['success' => true, 'office_id' => $office_id, 'message' => 'Office created successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create office: ' . $conn->error]);
            }
            break;
            
        case 'PUT':
            // Update office
            $raw_input = file_get_contents('php://input');
            $data = json_decode($raw_input, true);
            
            if (!isset($data['office_id']) || !isset($data['office_name']) || empty(trim($data['office_name']))) {
                echo json_encode(['success' => false, 'error' => 'Office ID and name are required']);
                exit;
            }
            
            $office_id = (int)$data['office_id'];
            $office_name = trim($data['office_name']);
            
            // Check if office exists
            $check_stmt = $conn->prepare("SELECT office_id FROM offices WHERE office_id = ?");
            $check_stmt->bind_param("i", $office_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'error' => 'Office not found']);
                exit;
            }
            
            // Check if another office with same name exists
            $check_stmt2 = $conn->prepare("SELECT office_id FROM offices WHERE office_name = ? AND office_id != ?");
            $check_stmt2->bind_param("si", $office_name, $office_id);
            $check_stmt2->execute();
            $result2 = $check_stmt2->get_result();
            
            if ($result2->num_rows > 0) {
                echo json_encode(['success' => false, 'error' => 'Office name already exists']);
                exit;
            }
            
            // Update office
            $stmt = $conn->prepare("UPDATE offices SET office_name = ? WHERE office_id = ?");
            $stmt->bind_param("si", $office_name, $office_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Office updated successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update office: ' . $conn->error]);
            }
            break;
            
        case 'DELETE':
            // Delete office - handle all foreign key constraints
            $raw_input = file_get_contents('php://input');
            $data = json_decode($raw_input, true);
            
            if (!isset($data['office_id'])) {
                echo json_encode(['success' => false, 'error' => 'Office ID is required']);
                exit;
            }
            
            $office_id = (int)$data['office_id'];
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Temporarily disable foreign key checks
                $conn->query("SET FOREIGN_KEY_CHECKS = 0");
                
                // Delete from role_office_mapping first
                $delete_mapping_stmt = $conn->prepare("DELETE FROM role_office_mapping WHERE office_id = ?");
                $delete_mapping_stmt->bind_param("i", $office_id);
                $delete_mapping_stmt->execute();
                
                // Set users with this office to a default office (like Admin Office or office_id = 2)
                $default_office_check = $conn->prepare("SELECT office_id FROM offices WHERE office_name = 'Admin Office' AND office_id != ? LIMIT 1");
                $default_office_check->bind_param("i", $office_id);
                $default_office_check->execute();
                $default_office_result = $default_office_check->get_result();
                
                if ($default_office_result && $default_office_result->num_rows > 0) {
                    $default_office = $default_office_result->fetch_assoc();
                    $default_office_id = $default_office['office_id'];
                    $update_users_stmt = $conn->prepare("UPDATE users SET office_id = ? WHERE office_id = ?");
                    $update_users_stmt->bind_param("ii", $default_office_id, $office_id);
                    $update_users_stmt->execute();
                } else {
                    // If no Admin Office, try to get first available office
                    $first_office_check = $conn->prepare("SELECT office_id FROM offices WHERE office_id != ? LIMIT 1");
                    $first_office_check->bind_param("i", $office_id);
                    $first_office_check->execute();
                    $first_office_result = $first_office_check->get_result();
                    
                    if ($first_office_result && $first_office_result->num_rows > 0) {
                        $first_office = $first_office_result->fetch_assoc();
                        $first_office_id = $first_office['office_id'];
                        $update_users_stmt = $conn->prepare("UPDATE users SET office_id = ? WHERE office_id = ?");
                        $update_users_stmt->bind_param("ii", $first_office_id, $office_id);
                        $update_users_stmt->execute();
                    }
                }
                
                // Delete from signature_approvals
                $delete_signature_stmt = $conn->prepare("DELETE FROM signature_approvals WHERE office_id = ?");
                $delete_signature_stmt->bind_param("i", $office_id);
                $delete_signature_stmt->execute();
                
                // Delete from document_workflow (or set to NULL if allowed)
                // Check if office_id can be NULL in document_workflow
                $delete_workflow_stmt = $conn->prepare("DELETE FROM document_workflow WHERE office_id = ?");
                $delete_workflow_stmt->bind_param("i", $office_id);
                $delete_workflow_stmt->execute();
                
                // Delete the office
                $delete_stmt = $conn->prepare("DELETE FROM offices WHERE office_id = ?");
                $delete_stmt->bind_param("i", $office_id);
                $delete_stmt->execute();
                
                // Re-enable foreign key checks
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                
                // Commit transaction
                $conn->commit();
                
                echo json_encode(['success' => true, 'message' => 'Office deleted successfully (related records cleaned up)']);
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                echo json_encode(['success' => false, 'error' => 'Failed to delete office: ' . $e->getMessage()]);
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

