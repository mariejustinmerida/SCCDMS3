<?php
/**
 * Resume Document
 * 
 * This file handles resuming documents that were previously put on hold
 */

// Define this constant to indicate this page is included in dashboard
if (!defined('INCLUDED_IN_DASHBOARD')) {
    define('INCLUDED_IN_DASHBOARD', true);
}

// If accessed directly via GET (not POST from form), redirect to dashboard with this page as parameter
if (basename($_SERVER['PHP_SELF']) == 'resume_document.php' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    header("Location: dashboard.php?page=resume&id=$document_id");
    exit();
}

require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/logging.php'; // Include logging functions

// Get document ID from URL or POST (for form submissions from hold.php)
$document_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['document_id']) ? intval($_POST['document_id']) : 0);
$user_id = $_SESSION['user_id'];
$office_id = isset($_SESSION['office_id']) ? (int)$_SESSION['office_id'] : 0;
$auto_resume = isset($_GET['auto_resume']) && $_GET['auto_resume'] == 1;

// Check if document exists and is on hold
// Use the same logic as hold.php: check document_logs for 'hold' action and ensure no later 'resume' action
$doc_query = "SELECT d.*, dt.type_name, u.username as creator_name,
              dl.details as hold_reason,
              dl.created_at as hold_date
              FROM documents d 
              JOIN document_types dt ON d.type_id = dt.type_id 
              JOIN users u ON d.creator_id = u.user_id 
              JOIN document_logs dl ON d.document_id = dl.document_id AND dl.action = 'hold'
              LEFT JOIN (
                  SELECT document_id, MAX(created_at) as latest_resume
                  FROM document_logs
                  WHERE action = 'resume'
                  GROUP BY document_id
              ) resume_logs ON d.document_id = resume_logs.document_id
              WHERE d.document_id = ?
              AND (resume_logs.document_id IS NULL OR dl.created_at > resume_logs.latest_resume)
              ORDER BY dl.created_at DESC
              LIMIT 1";
$doc_stmt = $conn->prepare($doc_query);
$doc_stmt->bind_param("i", $document_id);
$doc_stmt->execute();
$doc_result = $doc_stmt->get_result();

if ($doc_result->num_rows === 0) {
    if ($auto_resume) {
        // For auto-resume, return a JSON response
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Document not found or not on hold']);
        exit;
    } else {
        // Redirect back to hold page with error message
        header("Location: dashboard.php?page=hold&status=error&message=" . urlencode("Document not found or not on hold"));
        exit;
    }
}

$document = $doc_result->fetch_assoc();

// Get workflow information
$workflow_query = "SELECT dw.*, o.office_name 
                  FROM document_workflow dw 
                  JOIN offices o ON dw.office_id = o.office_id 
                  WHERE dw.document_id = ? 
                  ORDER BY dw.step_order ASC";
$workflow_stmt = $conn->prepare($workflow_query);
$workflow_stmt->bind_param("i", $document_id);
$workflow_stmt->execute();
$workflow_result = $workflow_stmt->get_result();

$workflow_steps = [];
while ($step = $workflow_result->fetch_assoc()) {
    $workflow_steps[] = $step;
}

// Check if this office has a current step for this document (including ON_HOLD status)
$current_step = null;
foreach ($workflow_steps as $step) {
    // Document can be CURRENT or ON_HOLD - both mean it's at this office
    $step_status = strtoupper($step['status'] ?? '');
    if ((int)$step['office_id'] === (int)$office_id && ($step_status === 'CURRENT' || $step_status === 'ON_HOLD')) {
        $current_step = $step;
        break;
    }
}

// Process form submission
$resume_message = '';
$resume_error = '';

// Process auto resume request
if ($auto_resume && $current_step) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Document status should already be 'pending' (hold is tracked via document_logs, not status)
        // Just ensure it's pending
        $update_doc = "UPDATE documents SET status = 'pending', updated_at = NOW() WHERE document_id = ?";
        $doc_update_stmt = $conn->prepare($update_doc);
        $doc_update_stmt->bind_param("i", $document_id);
        $doc_update_stmt->execute();
        
        // Ensure workflow status is CURRENT for this office (in case it was changed)
        $update_workflow = "UPDATE document_workflow SET status = 'CURRENT' WHERE document_id = ? AND office_id = ? AND status IN ('CURRENT', 'ON_HOLD', 'HOLD', 'on_hold', 'hold')";
        $workflow_update_stmt = $conn->prepare($update_workflow);
        if ($workflow_update_stmt) {
            $workflow_update_stmt->bind_param("ii", $document_id, $office_id);
            $workflow_update_stmt->execute();
        }
        
        // Log this resume action
        $auto_comments = "Document automatically resumed by user " . $_SESSION['username'];
        $insert_log = "INSERT INTO document_logs 
                       (document_id, user_id, action, details, created_at) 
                       VALUES (?, ?, 'resume', ?, NOW())";
        $log_stmt = $conn->prepare($insert_log);
        $log_stmt->bind_param("iis", $document_id, $user_id, $auto_comments);
        $log_stmt->execute();
        
        // Also log through the user_logs system if available
        if (function_exists('log_user_action')) {
            log_user_action(
                intval($user_id), 
                'resume_document', 
                "Auto-resumed document from hold: " . $document['title'], 
                $document_id, 
                $auto_comments,
                $office_id
            );
        }
        
        // Commit transaction
        $conn->commit();
        
        // Return JSON response for fetch API
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'document_id' => $document_id]);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Process normal form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resume'])) {
    $comments = $conn->real_escape_string($_POST['comments'] ?? '');
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if document is already approved by this office
        if (!$current_step) {
            throw new Exception("No current workflow step found for this document and office");
        }
        
        // Update document status from 'on_hold' back to 'pending'
        $update_doc = "UPDATE documents SET status = 'pending', updated_at = NOW() WHERE document_id = ?";
        $doc_update_stmt = $conn->prepare($update_doc);
        $doc_update_stmt->bind_param("i", $document_id);
        $doc_update_stmt->execute();
        
        // Update workflow status from 'ON_HOLD' back to 'CURRENT'
        $update_workflow = "UPDATE document_workflow SET status = 'CURRENT' 
                           WHERE document_id = ? AND office_id = ? 
                           AND (UPPER(status) = 'ON_HOLD' OR status = 'ON_HOLD')";
        $workflow_update_stmt = $conn->prepare($update_workflow);
        if (!$workflow_update_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        $workflow_update_stmt->bind_param("ii", $document_id, $office_id);
        $workflow_update_stmt->execute();
        
        if ($workflow_update_stmt->affected_rows === 0) {
            throw new Exception("Failed to update workflow status. The document may not be on hold or you don't have permission.");
        }
        
        // Log this resume action
        $insert_log = "INSERT INTO document_logs 
                       (document_id, user_id, action, details, created_at) 
                       VALUES (?, ?, 'resume', ?, NOW())";
        $log_stmt = $conn->prepare($insert_log);
        
        if (!$log_stmt) {
            throw new Exception("Database error on log insertion: " . $conn->error);
        }
        
        $log_stmt->bind_param("iis", $document_id, $user_id, $comments);
        $log_stmt->execute();
        
        // Also log through the user_logs system if available
        if (function_exists('log_user_action')) {
            log_user_action(
                intval($user_id), 
                'resume_document', 
                "Resumed document from hold: " . $document['title'], 
                $document_id, 
                $comments,
                $office_id
            );
        }
        
        // Commit transaction
        $conn->commit();
        
        $resume_message = "Document has been resumed and is now back in the workflow.";
        
        // Auto-redirect to incoming page after successful resume
        // Use a slightly longer delay to ensure database transaction is fully committed
        echo "<script>
            setTimeout(function() {
                window.location.href = 'dashboard.php?page=incoming&resumed=1';
            }, 1500);
        </script>";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $resume_error = "Error: " . $e->getMessage();
    }
}

// Page title
$page_title = "Resume Document";
include_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-purple-700 p-6 text-white">
            <h1 class="text-2xl font-bold">Resume Document</h1>
            <p class="text-purple-100 mt-1">Resume a document that was previously put on hold</p>
        </div>
        
        <div class="p-6">
            <?php if ($resume_message): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r-md shadow-sm" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium"><?php echo $resume_message; ?> Redirecting to incoming documents...</p>
                        </div>
                    </div>
                </div>
                
                <div class="text-center my-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-700 mx-auto mb-4"></div>
                    <p class="text-gray-600">Redirecting to incoming documents...</p>
                </div>
                
                <div class="flex justify-center my-8">
                    <a href="dashboard.php?page=incoming" 
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition duration-200 mr-4">
                        <i class="fas fa-check-circle mr-1"></i> Go to Incoming Documents
                    </a>
                    <a href="dashboard.php" 
                       class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md transition duration-200">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                    </a>
                </div>
            <?php elseif ($resume_error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-md shadow-sm" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium"><?php echo $resume_error; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex">
                    <a href="dashboard.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md transition duration-200">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                    </a>
                </div>
            <?php else: ?>
                <!-- Document Information with improved styling -->
                <div class="mb-8">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-800">Document Information</h2>
                    </div>
                    
                    <div class="bg-yellow-50 p-6 rounded-lg shadow-md border border-yellow-200 mb-6">
                        <div class="flex items-center mb-4 text-yellow-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <h3 class="font-medium">This document is currently on hold</h3>
                        </div>
                        
                        <div class="mb-4">
                            <p class="text-gray-700 font-medium">Hold Reason:</p>
                            <p class="bg-white p-3 rounded border border-yellow-200 mt-1"><?php echo nl2br(htmlspecialchars($document['hold_reason'] ?? 'No reason provided')); ?></p>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <p class="text-gray-500 text-sm">Document Title</p>
                                <p class="font-medium text-lg"><?php echo htmlspecialchars($document['title']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Document Type</p>
                                <p class="font-medium"><?php echo htmlspecialchars($document['type_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Created By</p>
                                <p class="font-medium"><?php echo htmlspecialchars($document['creator_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Created Date</p>
                                <p class="font-medium"><?php echo date('F j, Y', strtotime($document['created_at'])); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Current Status</p>
                                <p class="font-medium">
                                    <span class="px-2 py-1 rounded-full text-sm bg-yellow-100 text-yellow-800">
                                        On Hold
                                    </span>
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm">Document ID</p>
                                <p class="font-medium">DOC-<?php echo str_pad($document_id, 3, '0', STR_PAD_LEFT); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($current_step): ?>
                <!-- Resume Form -->
                <div class="bg-purple-50 rounded-lg shadow-md overflow-hidden border border-purple-200 mb-6">
                    <div class="p-4 bg-purple-600 text-white">
                        <h2 class="text-xl font-semibold">Resume Document</h2>
                        <p class="text-purple-100 text-sm">Continue with the document approval workflow</p>
                    </div>
                    
                    <div class="p-6">
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label for="comments" class="block text-sm font-medium text-gray-700 mb-2">Comments (Optional):</label>
                                <textarea id="comments" name="comments" rows="3" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-purple-500 focus:border-purple-500 shadow-sm"
                                          placeholder="Add any comments about resuming this document"></textarea>
                            </div>
                            
                            <div class="flex items-center justify-end space-x-4 mt-6">
                                <a href="dashboard.php?page=hold" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-200 border border-gray-300 transition duration-200">
                                    Cancel
                                </a>
                                <button type="submit" name="resume" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-md font-medium transition duration-200 shadow-sm">
                                    <i class="fas fa-play-circle mr-1"></i> Resume Document
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-md mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">You don't have permission to resume this document at this time.</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <a href="dashboard.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md transition duration-200">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                    </a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?> 