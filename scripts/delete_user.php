<?php
// Script to safely delete a user and all related records
// Usage: http://yourhost/scripts/delete_user.php?user_id=7

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
    // Get user_id from query parameter
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    
    if ($userId <= 0) {
        respond(false, ['error' => 'Invalid user_id. Usage: ?user_id=7']);
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Verify user exists
        $userCheck = $conn->prepare("SELECT user_id, username, email, full_name FROM users WHERE user_id = ?");
        $userCheck->bind_param('i', $userId);
        $userCheck->execute();
        $userResult = $userCheck->get_result();
        
        if ($userResult->num_rows === 0) {
            $conn->rollback();
            respond(false, ['error' => 'User not found']);
        }
        
        $userData = $userResult->fetch_assoc();
        
        // Delete related records from tables without CASCADE delete
        // These must be deleted manually before deleting the user
        
        $deletedCounts = [];
        
        // 1. Delete user_logs
        $deleteLogs = $conn->prepare("DELETE FROM user_logs WHERE user_id = ?");
        $deleteLogs->bind_param('i', $userId);
        $deleteLogs->execute();
        $deletedCounts['user_logs'] = $conn->affected_rows;
        
        // 2. Delete collaborative_cursors
        $deleteCursors = $conn->prepare("DELETE FROM collaborative_cursors WHERE user_id = ?");
        $deleteCursors->bind_param('i', $userId);
        $deleteCursors->execute();
        $deletedCounts['collaborative_cursors'] = $conn->affected_rows;
        
        // 3. Delete document_actions
        $deleteActions = $conn->prepare("DELETE FROM document_actions WHERE user_id = ?");
        $deleteActions->bind_param('i', $userId);
        $deleteActions->execute();
        $deletedCounts['document_actions'] = $conn->affected_rows;
        
        // 4. Delete document_drafts
        $deleteDrafts = $conn->prepare("DELETE FROM document_drafts WHERE user_id = ?");
        $deleteDrafts->bind_param('i', $userId);
        $deleteDrafts->execute();
        $deletedCounts['document_drafts'] = $conn->affected_rows;
        
        // 5. Delete signature_approvals
        $deleteSignatures = $conn->prepare("DELETE FROM signature_approvals WHERE user_id = ?");
        $deleteSignatures->bind_param('i', $userId);
        $deleteSignatures->execute();
        $deletedCounts['signature_approvals'] = $conn->affected_rows;
        
        // 6. Delete edit_conflicts (as user_id)
        $deleteConflicts1 = $conn->prepare("DELETE FROM edit_conflicts WHERE user_id = ?");
        $deleteConflicts1->bind_param('i', $userId);
        $deleteConflicts1->execute();
        $deletedCounts['edit_conflicts_user'] = $conn->affected_rows;
        
        // 7. Delete edit_conflicts (as conflicting_user_id)
        $deleteConflicts2 = $conn->prepare("DELETE FROM edit_conflicts WHERE conflicting_user_id = ?");
        $deleteConflicts2->bind_param('i', $userId);
        $deleteConflicts2->execute();
        $deletedCounts['edit_conflicts_conflicting'] = $conn->affected_rows;
        
        // Now delete the user (this will automatically cascade delete records from tables with ON DELETE CASCADE)
        $deleteUser = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $deleteUser->bind_param('i', $userId);
        $deleteUser->execute();
        
        if ($conn->affected_rows === 0) {
            $conn->rollback();
            respond(false, ['error' => 'Failed to delete user']);
        }
        
        // Commit transaction
        $conn->commit();
        
        respond(true, [
            'message' => 'User deleted successfully',
            'deleted_user' => $userData,
            'deleted_records' => $deletedCounts,
            'note' => 'Records from tables with ON DELETE CASCADE were automatically deleted'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Throwable $e) {
    respond(false, ['error' => $e->getMessage()]);
}
?>

