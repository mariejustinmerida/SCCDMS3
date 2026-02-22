<?php
require_once '../includes/config.php';
require_once '../includes/document_workflow.php';
require_once '../includes/notification_helper.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if document_logs table exists and create it if it doesn't
$check_logs_table = "SHOW TABLES LIKE 'document_logs'";
$logs_result = $conn->query($check_logs_table);
if ($logs_result->num_rows == 0) {
    // Create document_logs table
    $create_logs_table = "CREATE TABLE document_logs (
        log_id INT(11) NOT NULL AUTO_INCREMENT,
        document_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        action VARCHAR(50) NOT NULL,
        details TEXT,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (log_id),
        KEY document_id (document_id),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    $conn->query($create_logs_table);
}

// Check if notifications table exists and create it if it doesn't
$check_notifications_table = "SHOW TABLES LIKE 'notifications'";
$notifications_result = $conn->query($check_notifications_table);
if ($notifications_result->num_rows == 0) {
    // Create notifications table
    $create_notifications_table = "CREATE TABLE notifications (
        notification_id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        document_id INT(11) NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (notification_id),
        KEY user_id (user_id),
        KEY document_id (document_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    $conn->query($create_notifications_table);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if document ID is provided
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$office_id = $_SESSION['office_id'] ?? 0;

$message = "";
$status = "";

// If form is submitted to request revision
if ($action === 'submit' && $document_id > 0) {
    // Validate comments
    if (empty($comments)) {
        $message = "Please provide comments explaining what needs to be revised.";
        $status = "error";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Get document creator information
            $creator_query = "SELECT d.creator_id, u.office_id as creator_office_id 
                            FROM documents d 
                            JOIN users u ON d.creator_id = u.user_id 
                            WHERE d.document_id = ?";
            $creator_stmt = $conn->prepare($creator_query);
            $creator_stmt->bind_param("i", $document_id);
            $creator_stmt->execute();
            $creator_result = $creator_stmt->get_result();
            
            if (!$creator_result || $creator_result->num_rows === 0) {
                throw new Exception("Document creator not found");
            }
            
            $creator_data = $creator_result->fetch_assoc();
            $creator_id = $creator_data['creator_id'];
            $creator_office_id = $creator_data['creator_office_id'];
            
            // Check if there's a current workflow step for this office
            $check_current = "SELECT workflow_id FROM document_workflow 
                            WHERE document_id = ? AND office_id = ? AND status = 'current'";
            $check_stmt = $conn->prepare($check_current);
            $check_stmt->bind_param("ii", $document_id, $office_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result && $check_result->num_rows > 0) {
                // Mark current workflow step as 'CURRENT' with revision comments
                $update_current = "UPDATE document_workflow 
                                  SET status = 'CURRENT', comments = ? 
                                  WHERE document_id = ? AND office_id = ? AND status = 'CURRENT'";
                $current_stmt = $conn->prepare($update_current);
                $current_stmt->bind_param("sii", $comments, $document_id, $office_id);
                $current_stmt->execute();
            } else {
                // No current step found, find the latest step for this document and office
                $latest_step = "SELECT workflow_id FROM document_workflow 
                               WHERE document_id = ? AND office_id = ? 
                               ORDER BY step_order DESC LIMIT 1";
                $latest_stmt = $conn->prepare($latest_step);
                $latest_stmt->bind_param("ii", $document_id, $office_id);
                $latest_stmt->execute();
                $latest_result = $latest_stmt->get_result();
                
                if ($latest_result && $latest_result->num_rows > 0) {
                    // Update the latest step to CURRENT with revision comments
                    $workflow_id = $latest_result->fetch_assoc()['workflow_id'];
                    $update_latest = "UPDATE document_workflow 
                                     SET status = 'CURRENT', comments = ? 
                                     WHERE workflow_id = ?";
                    $update_stmt = $conn->prepare($update_latest);
                    $update_stmt->bind_param("si", $comments, $workflow_id);
                    $update_stmt->execute();
                } else {
                    // No workflow step found for this office, create one
                    $insert_step = "INSERT INTO document_workflow (document_id, office_id, step_order, status, comments, created_at) 
                                   VALUES (?, ?, 999, 'CURRENT', ?, NOW())";
                    $insert_stmt = $conn->prepare($insert_step);
                    $insert_stmt->bind_param("iis", $document_id, $office_id, $comments);
                    $insert_stmt->execute();
                }
            }
            
            // Update document status to 'revision'
            $update_doc = "UPDATE documents 
                          SET status = 'revision', updated_at = NOW() 
                          WHERE document_id = ?";
            $doc_stmt = $conn->prepare($update_doc);
            $doc_stmt->bind_param("i", $document_id);
            $doc_stmt->execute();
            
            // Log the revision request
            $log_sql = "INSERT INTO document_logs (document_id, user_id, action, details, created_at) 
                       VALUES (?, ?, 'revision', ?, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            if ($log_stmt === false) {
                // Check if there's an error with the SQL statement
                throw new Exception("Error preparing log statement: " . $conn->error);
            }
            $log_stmt->bind_param("iis", $document_id, $_SESSION['user_id'], $comments);
            if (!$log_stmt->execute()) {
                throw new Exception("Error executing log statement: " . $log_stmt->error);
            }
            
            // Create a notification for the document creator
            create_document_notification(
                $document_id,
                $creator_id,
                'revision_requested',
                'Revision Requested',
                "Revision requested for document #DOC-" . str_pad($document_id, 3, '0', STR_PAD_LEFT) . ": " . substr($comments, 0, 100) . (strlen($comments) > 100 ? '...' : '')
            );
            
            // Notify all users involved in the workflow
            notify_document_workflow_users(
                $document_id,
                'revision_requested',
                'Revision Requested',
                "Revision requested: " . substr($comments, 0, 100) . (strlen($comments) > 100 ? '...' : '')
            );
            
            // Commit transaction
            $conn->commit();
            
            $message = "Revision has been requested. The document has been sent back to the creator.";
            $status = "success";
            
            // Redirect back to incoming with status message
            header("Location: dashboard.php?page=incoming&status=$status&message=" . urlencode($message));
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $message = "Error requesting revision: " . $e->getMessage();
            $status = "error";
        }
    }
}

// Get document details
$doc_query = "SELECT d.*, dt.type_name, u.full_name as creator_name, o.office_name as creator_office
             FROM documents d
             LEFT JOIN document_types dt ON d.type_id = dt.type_id
             LEFT JOIN users u ON d.creator_id = u.user_id
             LEFT JOIN offices o ON u.office_id = o.office_id
             WHERE d.document_id = ?";
$stmt = $conn->prepare($doc_query);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $document = $result->fetch_assoc();
    
    // Check if the document is assigned to the current office
    $check_query = "SELECT * FROM document_workflow 
                   WHERE document_id = ? AND office_id = ? AND status = 'current'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $document_id, $office_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if (!$check_result || $check_result->num_rows === 0) {
        // Redirect with error if document is not assigned to current office
        header("Location: dashboard.php?page=incoming&status=error&message=" . urlencode("This document is not currently assigned to your office."));
        exit();
    }
} else {
    // Redirect with error if document not found
    header("Location: dashboard.php?page=incoming&status=error&message=" . urlencode("Document not found."));
    exit();
}
?>

<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="border-b px-6 py-3 bg-[#163b20]">
            <h2 class="text-lg font-semibold text-white flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Request Document Revision
            </h2>
        </div>
        
        <div class="p-6">
            <?php if ($status === "error" && !empty($message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="mb-6">
                <h3 class="text-xl font-semibold mb-2"><?php echo htmlspecialchars($document['title']); ?></h3>
                <p class="text-gray-600">Document Type: <?php echo htmlspecialchars($document['type_name']); ?></p>
                <p class="text-gray-600">Created By: <?php echo htmlspecialchars($document['creator_name']); ?> (<?php echo htmlspecialchars($document['creator_office']); ?>)</p>
                <p class="text-gray-600">Status: 
                    <span class="px-2 py-1 rounded-full text-sm font-medium inline-block <?php 
                      echo match($document['status'] ?? '') {
                          'approved' => 'bg-green-100 text-green-800',
                          'rejected' => 'bg-red-100 text-red-800',
                          'on_hold' => 'bg-yellow-100 text-yellow-800',
                          default => 'bg-blue-100 text-blue-800'
                      }; 
                    ?>">
                      <?php echo ucfirst($document['status'] ?? 'Pending'); ?>
                    </span>
                </p>
            </div>
            
            <div class="mb-6 p-4 bg-purple-50 border-l-4 border-purple-500 text-purple-700">
                <p class="font-medium">Important:</p>
                <p>When you request a revision, the document will be sent back to the creator for modifications. After the creator makes the necessary changes, the document will skip offices that have already approved it and will be sent directly to your office for review.</p>
            </div>
            
            <form action="?page=request_revision&id=<?php echo $document_id; ?>&action=submit" method="post">
                <div class="mb-6">
                    <label for="comments" class="block text-sm font-medium text-gray-700 mb-1">Revision Comments <span class="text-red-500">*</span></label>
                    <textarea id="comments" name="comments" rows="4" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="Please explain what needs to be revised in this document..." required><?php echo htmlspecialchars($comments); ?></textarea>
                    <p class="text-sm text-gray-500 mt-1">Be specific about what changes are needed. These comments will be visible to the document creator.</p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <a href="dashboard.php?page=view_document&id=<?php echo $document_id; ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                        Request Revision
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
