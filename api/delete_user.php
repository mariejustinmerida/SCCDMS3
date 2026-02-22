<?php
// Ensure clean JSON output
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_error_handler(function($severity, $message){ error_log("delete_user.php: $message"); return true; });

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
	echo json_encode(['success' => false, 'error' => 'Only Super Admin can delete users']);
	exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { $data = []; }
$targetUserId = isset($data['user_id']) ? (int)$data['user_id'] : 0;

if ($targetUserId <= 0) {
	echo json_encode(['success' => false, 'error' => 'Invalid user id']);
	exit;
}

try {
	// Prevent deleting self
	if ($targetUserId === (int)$_SESSION['user_id']) {
		echo json_encode(['success' => false, 'error' => 'You cannot delete your own account']);
		exit;
	}

	// Start transaction
	$conn->begin_transaction();

	// Temporarily disable foreign key checks for all cleanup operations
	$conn->query("SET FOREIGN_KEY_CHECKS = 0");

	// Check if user has created documents (foreign key constraint issue)
	$docCountQuery = "SELECT COUNT(*) as doc_count FROM documents WHERE creator_id = ?";
	$docCountStmt = $conn->prepare($docCountQuery);
	$docCountStmt->bind_param('i', $targetUserId);
	$docCountStmt->execute();
	$docCountResult = $docCountStmt->get_result()->fetch_assoc();
	$docCount = (int)($docCountResult['doc_count'] ?? 0);

	// If user has created documents, transfer ownership
	if ($docCount > 0) {
		// Try to find another user to transfer ownership to (priority: Super Admin > Admin > current user > any user)
		$currentUserId = (int)$_SESSION['user_id'];
		$newCreatorId = null;
		
		// First, try to find another Super Admin (excluding target and current user)
		$adminQuery = "SELECT u.user_id FROM users u
		               JOIN roles r ON u.role_id = r.role_id
		               WHERE u.user_id != ? AND u.user_id != ?
		               AND r.role_name = 'Super Admin'
		               LIMIT 1";
		$adminStmt = $conn->prepare($adminQuery);
		$adminStmt->bind_param('ii', $targetUserId, $currentUserId);
		$adminStmt->execute();
		$adminResult = $adminStmt->get_result();
		
		if ($adminResult->num_rows > 0) {
			$adminRow = $adminResult->fetch_assoc();
			$newCreatorId = (int)$adminRow['user_id'];
		} else {
			// Fallback to Admin role
			$adminQuery2 = "SELECT u.user_id FROM users u
			                JOIN roles r ON u.role_id = r.role_id
			                WHERE u.user_id != ? AND u.user_id != ?
			                AND r.role_name = 'Admin'
			                LIMIT 1";
			$adminStmt2 = $conn->prepare($adminQuery2);
			$adminStmt2->bind_param('ii', $targetUserId, $currentUserId);
			$adminStmt2->execute();
			$adminResult2 = $adminStmt2->get_result();
			
			if ($adminResult2->num_rows > 0) {
				$adminRow2 = $adminResult2->fetch_assoc();
				$newCreatorId = (int)$adminRow2['user_id'];
			} else {
				// Last resort: use current user (the one performing the deletion)
				$newCreatorId = $currentUserId;
			}
		}
		
		// Update documents to transfer ownership
		$updateDocs = "UPDATE documents SET creator_id = ? WHERE creator_id = ?";
		$updateStmt = $conn->prepare($updateDocs);
		$updateStmt->bind_param('ii', $newCreatorId, $targetUserId);
		$updateStmt->execute();
	}

	// Handle tables with foreign key constraints that don't have CASCADE DELETE
	
	// Delete user_logs records (audit logs - safe to delete)
	$deleteLogs = "DELETE FROM user_logs WHERE user_id = ?";
	$deleteLogsStmt = $conn->prepare($deleteLogs);
	$deleteLogsStmt->bind_param('i', $targetUserId);
	$deleteLogsStmt->execute();
	
	// Delete document_actions records
	$deleteActions = "DELETE FROM document_actions WHERE user_id = ?";
	$deleteActionsStmt = $conn->prepare($deleteActions);
	$deleteActionsStmt->bind_param('i', $targetUserId);
	$deleteActionsStmt->execute();
	
	// Delete document_drafts records
	$deleteDrafts = "DELETE FROM document_drafts WHERE user_id = ?";
	$deleteDraftsStmt = $conn->prepare($deleteDrafts);
	$deleteDraftsStmt->bind_param('i', $targetUserId);
	$deleteDraftsStmt->execute();
	
	// Delete collaborative_cursors records
	$deleteCursors = "DELETE FROM collaborative_cursors WHERE user_id = ?";
	$deleteCursorsStmt = $conn->prepare($deleteCursors);
	$deleteCursorsStmt->bind_param('i', $targetUserId);
	$deleteCursorsStmt->execute();
	
	// Delete edit_conflicts records (user_id and conflicting_user_id)
	$deleteConflicts1 = "DELETE FROM edit_conflicts WHERE user_id = ? OR conflicting_user_id = ?";
	$deleteConflictsStmt = $conn->prepare($deleteConflicts1);
	$deleteConflictsStmt->bind_param('ii', $targetUserId, $targetUserId);
	$deleteConflictsStmt->execute();
	
	// Delete signature_approvals records
	$deleteSignatureApprovals = "DELETE FROM signature_approvals WHERE user_id = ?";
	$deleteSignatureApprovalsStmt = $conn->prepare($deleteSignatureApprovals);
	$deleteSignatureApprovalsStmt->bind_param('i', $targetUserId);
	$deleteSignatureApprovalsStmt->execute();
	
	// Delete document_logs records if table exists (might have user_id)
	$deleteDocLogs = "DELETE FROM document_logs WHERE user_id = ?";
	$deleteDocLogsStmt = $conn->prepare($deleteDocLogs);
	if ($deleteDocLogsStmt) {
		$deleteDocLogsStmt->bind_param('i', $targetUserId);
		$deleteDocLogsStmt->execute();
	}

	// Re-enable foreign key checks before deleting the user
	$conn->query("SET FOREIGN_KEY_CHECKS = 1");

	// Soft delete if status column exists, else hard delete
	$hasStatus = false;
	$colRes = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
	if ($colRes && $colRes->num_rows > 0) { $hasStatus = true; }

	if ($hasStatus) {
		$stmt = $conn->prepare("UPDATE users SET status = 'deleted' WHERE user_id = ?");
		$stmt->bind_param('i', $targetUserId);
		$stmt->execute();
		$ok = $stmt->affected_rows >= 0;
	} else {
		$stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
		$stmt->bind_param('i', $targetUserId);
		$stmt->execute();
		$ok = $stmt->affected_rows > 0;
	}

	if ($ok) {
		$conn->commit();
		ob_clean();
		echo json_encode(['success' => true, 'message' => $docCount > 0 ? "User deleted. {$docCount} document(s) ownership was transferred." : 'User deleted successfully']);
	} else {
		$conn->rollback();
		ob_clean();
		echo json_encode(['success' => false, 'error' => 'Delete failed or no change']);
	}
} catch (Throwable $e) {
	if (isset($conn)) {
		$conn->rollback();
		// Re-enable foreign key checks in case of error
		$conn->query("SET FOREIGN_KEY_CHECKS = 1");
	}
	ob_clean();
	error_log("delete_user.php error: " . $e->getMessage());
	echo json_encode(['success' => false, 'error' => 'Failed to delete user: ' . $e->getMessage()]);
}
?>

