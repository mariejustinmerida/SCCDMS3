<?php
require_once '../includes/config.php';
require_once '../includes/document_workflow.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

// If form is submitted to resume document
if ($action === 'resume' && $document_id > 0) {
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // The document_workflow status should already be 'CURRENT'
        // We're just making sure it's set correctly
        $update_workflow = "UPDATE document_workflow 
                          SET status = 'CURRENT' 
                          WHERE document_id = ? AND office_id = ? AND status = 'CURRENT'";
        $workflow_stmt = $conn->prepare($update_workflow);
        $workflow_stmt->bind_param("ii", $document_id, $office_id);
        $workflow_stmt->execute();
        
        // Add a log entry for resuming the document
        $log_sql = "INSERT INTO document_logs (document_id, user_id, action, details, created_at) 
                   VALUES (?, ?, 'resume', ?, NOW())";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("iis", $document_id, $_SESSION['user_id'], $comments);
        $log_stmt->execute();
        
        // Document status is already 'pending', no need to update it
        // We'll just update the timestamp
        $update_doc = "UPDATE documents 
                      SET updated_at = NOW(), status = 'pending' 
                      WHERE document_id = ?";
        $doc_stmt = $conn->prepare($update_doc);
        $doc_stmt->bind_param("i", $document_id);
        $doc_stmt->execute();
        
        // Log the action
        $log_sql = "INSERT INTO document_logs (document_id, user_id, action, details, created_at) 
                   VALUES (?, ?, 'resume', ?, NOW())";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("iis", $document_id, $_SESSION['user_id'], $comments);
        $log_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $message = "Document has been resumed and is now active in the workflow.";
        $status = "success";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $message = "Error resuming document: " . $e->getMessage();
        $status = "error";
    }
    
    // Redirect back to the hold page with status message
    header("Location: dashboard.php?page=hold&status=$status&message=" . urlencode($message));
    exit();
}

// If no action is specified, show the resume form
$doc_query = "SELECT d.*, dt.type_name, u.full_name as creator_name, o.office_name as creator_office
             FROM documents d
             LEFT JOIN document_types dt ON d.type_id = dt.type_id
             LEFT JOIN users u ON d.creator_id = u.user_id
             LEFT JOIN offices o ON u.office_id = o.office_id
             JOIN document_logs dl ON d.document_id = dl.document_id AND dl.action = 'hold'
             WHERE d.document_id = ? AND d.status = 'pending'
             ORDER BY dl.created_at DESC LIMIT 1";
$stmt = $conn->prepare($doc_query);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $document = $result->fetch_assoc();
    
    // Check if the document is assigned to the current office
    $check_query = "SELECT * FROM document_workflow 
                   WHERE document_id = ? AND office_id = ? AND status = 'CURRENT'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $document_id, $office_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if (!$check_result || $check_result->num_rows === 0) {
        // Redirect with error if document is not on hold in current office
        header("Location: dashboard.php?page=hold&status=error&message=" . urlencode("This document is not on hold in your office."));
        exit();
    }
} else {
    // Redirect with error if document not found
    header("Location: dashboard.php?page=hold&status=error&message=" . urlencode("Document not found or not on hold."));
    exit();
}
?>

<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="border-b px-6 py-3 bg-[#163b20]">
            <h2 class="text-lg font-semibold text-white flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Resume Document
            </h2>
        </div>
        
        <div class="p-6">
            <div class="mb-6">
                <h3 class="text-xl font-semibold mb-2"><?php echo htmlspecialchars($document['title']); ?></h3>
                <p class="text-gray-600">Document Type: <?php echo htmlspecialchars($document['type_name']); ?></p>
                <p class="text-gray-600">Created By: <?php echo htmlspecialchars($document['creator_name']); ?></p>
                <p class="text-gray-600">Status: <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">On Hold</span></p>
            </div>
            
            <form action="?page=resume&id=<?php echo $document_id; ?>&action=resume" method="post">
                <div class="mb-6">
                    <label for="comments" class="block text-sm font-medium text-gray-700 mb-1">Comments (Optional)</label>
                    <textarea id="comments" name="comments" rows="4" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Add any comments about resuming this document..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <a href="dashboard.php?page=hold" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        Resume Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
