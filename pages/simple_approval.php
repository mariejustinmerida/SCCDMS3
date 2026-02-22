<?php
/**
 * Simple Document Approval with QR Code
 * 
 * This is a completely new approach for document approval with QR codes
 */

// Define this constant to indicate this page is included in dashboard
if (!defined('INCLUDED_IN_DASHBOARD')) {
    define('INCLUDED_IN_DASHBOARD', true);
}

// If accessed directly, redirect to dashboard with this page as parameter
if (basename($_SERVER['PHP_SELF']) == 'simple_approval.php') {
    $document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    header("Location: dashboard.php?page=simple_approval&id=$document_id");
    exit();
}

require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/logging.php'; // Include logging functions
require_once '../includes/document_workflow.php'; // Add this line to include the document workflow functions
require_once '../includes/enhanced_notification_system.php'; // Include enhanced notification system

// Create the simple_verifications table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS simple_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    office_id VARCHAR(50) NOT NULL,
    verification_code VARCHAR(10) NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (document_id) REFERENCES documents(document_id)
)";
$conn->query($create_table_sql);

// Get document ID from URL
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'];

// Check if document exists
$doc_query = "SELECT d.*, dt.type_name, u.username as creator_name 
              FROM documents d 
              JOIN document_types dt ON d.type_id = dt.type_id 
              JOIN users u ON d.creator_id = u.user_id 
              WHERE d.document_id = ?";
$doc_stmt = $conn->prepare($doc_query);
$doc_stmt->bind_param("i", $document_id);
$doc_stmt->execute();
$doc_result = $doc_stmt->get_result();

if ($doc_result->num_rows === 0) {
    die("Document not found");
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

// Check if this office has a current step for this document
$current_step = null;
foreach ($workflow_steps as $step) {
    // Note: Using uppercase 'CURRENT' to match the ENUM value in document_workflow table
    if ($step['office_id'] == $office_id && $step['status'] == 'CURRENT') {
        $current_step = $step;
        break;
    }
}

// Process form submission
$approval_message = '';
$approval_error = '';
$verification_code = '';
$verification_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comments = $conn->real_escape_string($_POST['comments'] ?? '');
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if document is already approved by this office
        if (!$current_step) {
            throw new Exception("No current workflow step found for this document and office");
        }
        
        // APPROVE document
        if (isset($_POST['approve'])) {
            // Update workflow status to COMPLETED
            $update_workflow = "UPDATE document_workflow SET 
                               status = 'COMPLETED', 
                               comments = ?, 
                               completed_at = NOW() 
                               WHERE document_id = ? AND office_id = ? AND status = 'CURRENT'";
            $update_stmt = $conn->prepare($update_workflow);
            
            if (!$update_stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $update_stmt->bind_param("sis", $comments, $document_id, $office_id);
            $update_stmt->execute();
            
            if ($update_stmt->affected_rows === 0) {
                throw new Exception("Failed to update workflow status. It might have been already processed or you don't have permission.");
            }
            
            // --- HARD FIX: Explicitly check if this is the final step ---
            $last_step_query = "SELECT MAX(step_order) as max_step FROM document_workflow WHERE document_id = ?";
            $last_step_stmt = $conn->prepare($last_step_query);
            $last_step_stmt->bind_param("i", $document_id);
            $last_step_stmt->execute();
            $last_step_result = $last_step_stmt->get_result()->fetch_assoc();
            $is_final_step = ($current_step['step_order'] == $last_step_result['max_step']);

            $final_approval = false;
            // If this was the final step, mark the document as 'approved'.
            if ($is_final_step) {
                $update_doc = "UPDATE documents SET status = 'approved', updated_at = NOW() WHERE document_id = ?";
                $doc_update_stmt = $conn->prepare($update_doc);
                $doc_update_stmt->bind_param("i", $document_id);
                $doc_update_stmt->execute();
                $final_approval = true;
            } else {
                // Otherwise, find the next step in the workflow and set it to 'CURRENT'.
                $next_step_query = "UPDATE document_workflow SET status = 'CURRENT' 
                                    WHERE document_id = ? AND status = 'PENDING' 
                                    ORDER BY step_order ASC LIMIT 1";
                $next_stmt = $conn->prepare($next_step_query);
                $next_stmt->bind_param("i", $document_id);
                $next_stmt->execute();
            }
            
            // Generate verification code (6 digits)
            $verification_code = sprintf('%06d', rand(0, 999999));
            
            // Create verification URL with both document ID and verification code
            // This format makes it easier to extract when scanning the QR code
            $verification_url = "http://" . $_SERVER['HTTP_HOST'] . "/SCCDMS2/simple_verify.php?doc=" . $document_id . "&code=" . $verification_code;
            
            // Store verification details
            $insert_verification = "INSERT INTO simple_verifications 
                                  (document_id, user_id, office_id, verification_code, created_at) 
                                  VALUES (?, ?, ?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_verification);
            
            if (!$insert_stmt) {
                throw new Exception("Database error on verification insert: " . $conn->error);
            }
            
            // Make sure user_id is an integer
            $user_id_int = intval($user_id);
            
            $insert_stmt->bind_param("iiss", $document_id, $user_id_int, $office_id, $verification_code);
            $insert_stmt->execute();
            
            // Log this approval action
            if (function_exists('log_user_action')) {
                log_user_action(
                    $user_id_int, 
                    'approve_document', 
                    "Approved document: " . $document['title'] . " (Verification Code: $verification_code)", 
                    $document_id, 
                    null, 
                    $office_id
                );
            }
            
            // Create enhanced notification for document creator
            notify_document_creator($document_id, 'approved', [
                'reason' => 'Document has been approved and is ready for the next step',
                'approved_by' => $user_id_int,
                'verification_code' => $verification_code
            ]);
            
            // Notify all users involved in the workflow
            notify_workflow_users($document_id, 'approved', [
                'reason' => 'Document has been approved and is ready for the next step',
                'approved_by' => $user_id_int
            ]);
            
            $_SESSION['success'] = "Document approved successfully!";
            $conn->commit();

            if ($final_approval) {
                header("Location: dashboard.php?page=approved&status=success");
            } else {
                header("Location: dashboard.php?page=incoming&status=success");
            }
            exit();
        }
        // REJECT document
        else if (isset($_POST['reject'])) {
            if (empty($comments)) {
                throw new Exception("Please provide a reason for rejection");
            }
            
            // Update workflow status to REJECTED
            $update_workflow = "UPDATE document_workflow SET 
                               status = 'REJECTED', 
                               comments = ?, 
                               completed_at = NOW() 
                               WHERE document_id = ? AND office_id = ? AND status = 'CURRENT'";
            $update_stmt = $conn->prepare($update_workflow);
            
            if (!$update_stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $update_stmt->bind_param("sis", $comments, $document_id, $office_id);
            $update_stmt->execute();
            
            if ($update_stmt->affected_rows === 0) {
                throw new Exception("Failed to update workflow status. It might have been already processed or you don't have permission.");
            }
            
            // Update document status to rejected
            $update_doc = "UPDATE documents SET status = 'rejected', updated_at = NOW() WHERE document_id = ?";
            $doc_update_stmt = $conn->prepare($update_doc);
            $doc_update_stmt->bind_param("i", $document_id);
            $doc_update_stmt->execute();
            
            // Set all remaining workflow steps to CANCELLED
            $cancel_remaining = "UPDATE document_workflow SET status = 'CANCELLED' 
                                WHERE document_id = ? AND status = 'PENDING'";
            $cancel_stmt = $conn->prepare($cancel_remaining);
            $cancel_stmt->bind_param("i", $document_id);
            $cancel_stmt->execute();
            
            if (function_exists('log_user_action')) {
                log_user_action(
                    intval($user_id), 
                    'reject_document', 
                    "Rejected document: " . $document['title'] . " - Reason: " . substr($comments, 0, 50) . (strlen($comments) > 50 ? '...' : ''), 
                    $document_id, 
                    null, 
                    $office_id
                );
            }
            
            // Create enhanced notification for document creator
            notify_document_creator($document_id, 'rejected', [
                'reason' => $comments,
                'rejected_by' => intval($user_id)
            ]);
            
            // Notify all users involved in the workflow
            notify_workflow_users($document_id, 'rejected', [
                'reason' => $comments,
                'rejected_by' => intval($user_id)
            ]);
            
            $_SESSION['success'] = 'Document rejected successfully.';
            $conn->commit();
            header("Location: dashboard.php?page=rejected&status=rejected");
            exit();
        }
        // PUT ON HOLD document
        else if (isset($_POST['hold'])) {
            if (empty($comments)) {
                throw new Exception("Please provide a reason for putting the document on hold");
            }
            
            // Update workflow status to ON_HOLD
            $update_workflow = "UPDATE document_workflow SET 
                               status = 'ON_HOLD', 
                               comments = ? 
                               WHERE document_id = ? AND office_id = ? AND status = 'CURRENT'";
            $update_stmt = $conn->prepare($update_workflow);
            
            if (!$update_stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $update_stmt->bind_param("sis", $comments, $document_id, $office_id);
            $update_stmt->execute();
            
            if ($update_stmt->affected_rows === 0) {
                throw new Exception("Failed to update workflow status. It might have been already processed or you don't have permission.");
            }
            
            // Update document status to on_hold
            $update_doc = "UPDATE documents SET status = 'on_hold', updated_at = NOW() WHERE document_id = ?";
            $doc_update_stmt = $conn->prepare($update_doc);
            $doc_update_stmt->bind_param("i", $document_id);
            $doc_update_stmt->execute();
            
            if (function_exists('log_user_action')) {
                log_user_action(
                    intval($user_id), 
                    'hold', 
                    "Document put on hold: " . $document['title'] . " - Reason: " . substr($comments, 0, 50) . (strlen($comments) > 50 ? '...' : ''), 
                    $document_id, 
                    $comments,
                    $office_id
                );
            }
            
            // Create enhanced notification for document creator
            notify_document_creator($document_id, 'on_hold', [
                'reason' => $comments,
                'held_by' => intval($user_id)
            ]);
            
            // Notify current office
            $current_office = get_current_workflow_office($document_id);
            if ($current_office) {
                notify_office_users($current_office, $document_id, 'on_hold', [
                    'reason' => $comments,
                    'held_by' => intval($user_id)
                ]);
            }
            
            $_SESSION['success'] = 'Document has been put on hold.';
            $conn->commit();
            header("Location: dashboard.php?page=hold&status=held");
            exit();
        }
        // REQUEST REVISION
        else if (isset($_POST['request_revision'])) {
            if (empty($comments)) {
                throw new Exception("Please provide a reason for requesting revision");
            }

            // Update workflow status to REVISION_REQUESTED
            $update_workflow = "UPDATE document_workflow SET 
                               status = 'REVISION_REQUESTED', 
                               comments = ? 
                               WHERE document_id = ? AND office_id = ? AND status = 'CURRENT'";
            $update_stmt = $conn->prepare($update_workflow);
            
            if (!$update_stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $update_stmt->bind_param("sis", $comments, $document_id, $office_id);
            $update_stmt->execute();
            
            if ($update_stmt->affected_rows === 0) {
                throw new Exception("Failed to update workflow status. It might have been already processed or you don't have permission.");
            }

            // Update document status and set the requesting office ID
            $update_doc_sql = "UPDATE documents 
                               SET status = 'revision', 
                                   revision_requesting_office_id = ?,
                                   updated_at = NOW()
                               WHERE document_id = ?";
            $update_doc_stmt = $conn->prepare($update_doc_sql);
            $update_doc_stmt->bind_param("ii", $office_id, $document_id);
            if (!$update_doc_stmt->execute()) {
                throw new Exception("Failed to update document for revision: " . $update_doc_stmt->error);
            }

            // Log the action
            if (function_exists('log_user_action')) {
                log_user_action(
                    intval($user_id), 
                    'request_revision', 
                    "Requested revision for document: " . $document['title'] . " - Reason: " . substr($comments, 0, 50),
                    $document_id, 
                    $comments,
                    $office_id
                );
            }
            
            // Create enhanced notification for document creator
            notify_document_creator($document_id, 'revision_requested', [
                'reason' => $comments,
                'requested_by' => intval($user_id)
            ]);

            $_SESSION['success'] = 'Revision has been requested successfully.';
            $conn->commit();
            header("Location: dashboard.php?page=documents_needing_revision&status=revision_requested");
            exit();
        }
        // RESUME document
        else if (isset($_POST['resume'])) {
            // Update workflow status back to CURRENT
            $update_workflow = "UPDATE document_workflow SET 
                               status = 'CURRENT', 
                               comments = ? 
                               WHERE document_id = ? AND office_id = ? AND status = 'ON_HOLD'";
            $update_stmt = $conn->prepare($update_workflow);
            
            if (!$update_stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $update_stmt->bind_param("sis", $comments, $document_id, $office_id);
            $update_stmt->execute();
            
            if ($update_stmt->affected_rows === 0) {
                throw new Exception("Failed to update workflow status. It might have been already processed or you don't have permission.");
            }
            
            // Update document status back to pending
            $update_doc = "UPDATE documents SET status = 'pending', updated_at = NOW() WHERE document_id = ?";
            $doc_update_stmt = $conn->prepare($update_doc);
            $doc_update_stmt->bind_param("i", $document_id);
            $doc_update_stmt->execute();
            
            // Log this resume action in the user_logs table if the function exists
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
            
            $_SESSION['success'] = 'The document has been resumed and is now back in the workflow.';
            $conn->commit();
            header("Location: dashboard.php?page=incoming&status=resumed");
            exit();
        }
        
        // Commit transaction
        $conn->commit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $approval_error = "Error: " . $e->getMessage();
    }
}

// Page title
$page_title = "Simple Document Approval";
include_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-green-700 p-6 text-white">
            <h1 class="text-2xl font-bold">Document Approval</h1>
            <p class="text-green-100 mt-1">Review and approve this document with digital verification</p>
        </div>
        
        <div class="p-6">
            <?php if ($approval_message): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r-md shadow-sm" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium"><?php echo $approval_message; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- QR Code and Verification Container with improved styling -->
                <div class="mb-6 rounded-lg overflow-hidden shadow-md">
                    <div class="bg-indigo-600 p-4 text-white">
                        <h2 class="text-xl font-semibold">Document Verification</h2>
                        <p class="text-indigo-200 text-sm">Scan this QR code to verify this document's authenticity</p>
                    </div>
                    
                    <div class="bg-white p-6">
                        <div class="flex flex-col md:flex-row items-center gap-8">
                            <div class="bg-white rounded-lg shadow-md p-4 text-center">
                                <!-- Using our custom QR display script with higher resolution for better printing -->
                                <img src="../qr_display.php?url=<?php echo urlencode($verification_url); ?>&size=300" 
                                     alt="QR Code" class="w-48 h-48 mx-auto" id="qrCodeImage">
                                
                                <div class="mt-4 space-y-2">
                                    <p class="text-gray-600 text-sm mb-2">Verification Code:</p>
                                    <div class="font-mono text-lg bg-gray-100 px-4 py-2 rounded-lg font-bold text-indigo-700"><?php echo $verification_code; ?></div>
                                </div>
                                
                                <div class="mt-4 flex flex-wrap gap-2 justify-center">
                                    <a href="dashboard.php?page=document_with_qr_wrapper&doc=<?php echo $document_id; ?>&code=<?php echo $verification_code; ?>" 
                                       class="bg-indigo-600 text-white text-sm px-3 py-2 rounded-md hover:bg-indigo-700 transition duration-200">
                                        <i class="fas fa-file-alt mr-1"></i> View with QR
                                    </a>
                                    <a href="../auto_insert_qr.php?doc=<?php echo $document_id; ?>&code=<?php echo $verification_code; ?>&title=<?php echo urlencode($document['title']); ?>" 
                                       class="bg-green-600 text-white text-sm px-3 py-2 rounded-md hover:bg-green-700 transition duration-200">
                                        <i class="fab fa-google mr-1"></i> Google Docs
                                    </a>
                                    <button onclick="downloadQRCode()" 
                                       class="bg-blue-600 text-white text-sm px-3 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                                        <i class="fas fa-download mr-1"></i> Download QR
                                    </button>
                                </div>
                            </div>
                            
                            <div class="flex-1 space-y-4">
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Approval Details</h3>
                                
                                <div class="bg-gray-50 rounded-lg p-4 shadow-sm border border-gray-200">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-gray-500 text-sm">Document Title</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($document['title']); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500 text-sm">Approved On</p>
                                            <p class="font-medium"><?php echo date('F j, Y \a\t g:i a'); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500 text-sm">Approved By</p>
                                            <p class="font-medium"><?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Unknown User'; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500 text-sm">Approving Office</p>
                                            <p class="font-medium"><?php echo isset($_SESSION['office_name']) ? $_SESSION['office_name'] : 'Unknown Office'; ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <a href="<?php echo $verification_url; ?>" target="_blank" 
                                       class="inline-block bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md transition duration-200">
                                        <i class="fas fa-check-circle mr-1"></i> Verify Document
                                    </a>
                                    
                                    <a href="dashboard.php?page=approved" 
                                       class="inline-block ml-2 bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md transition duration-200">
                                        <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($approval_error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-md shadow-sm" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium"><?php echo $approval_error; ?></p>
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
                                    <span class="px-2 py-1 rounded-full text-sm <?php 
                                        switch($document['status']) {
                                            case 'approved': echo 'bg-green-100 text-green-800'; break;
                                            case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                            case 'on_hold': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'pending': echo 'bg-blue-100 text-blue-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?>">
                                        <?php echo ucfirst(htmlspecialchars($document['status'])); ?>
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
                
                <!-- Workflow Steps with improved styling -->
                <div class="mb-8">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-800">Approval Workflow</h2>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Step</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Office</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($workflow_steps as $step): ?>
                                <tr class="<?php echo ($step['office_id'] == $office_id) ? 'bg-indigo-50' : ''; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $step['step_order']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($step['office_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($step['status'] == 'COMPLETED'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-green-400" fill="currentColor" viewBox="0 0 8 8">
                                                    <circle cx="4" cy="4" r="3" />
                                                </svg>
                                                Approved
                                            </span>
                                        <?php elseif ($step['status'] == 'CURRENT'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-blue-400" fill="currentColor" viewBox="0 0 8 8">
                                                    <circle cx="4" cy="4" r="3" />
                                                </svg>
                                                In Progress
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-gray-400" fill="currentColor" viewBox="0 0 8 8">
                                                    <circle cx="4" cy="4" r="3" />
                                                </svg>
                                                Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php if ($current_step): ?>
                <!-- Approval Form with improved styling -->
                <div class="bg-green-50 rounded-lg shadow-md overflow-hidden border border-green-200 mb-6">
                    <div class="p-4 bg-green-600 text-white">
                        <h2 class="text-xl font-semibold">Document Actions</h2>
                        <p class="text-green-100 text-sm">Choose an action for this document</p>
                    </div>
                    
                    <div class="p-6">
                        <form method="POST" action="" id="documentActionForm">
                            <div class="mb-4">
                                <label for="comments" class="block text-sm font-medium text-gray-700 mb-2">Comments:</label>
                                <textarea id="comments" name="comments" rows="3" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500 shadow-sm"
                                          placeholder="Add comments, notes, or reasons for your action"></textarea>
                                <p class="text-sm text-gray-500 mt-1">
                                    <span class="text-red-500">*</span> Required for reject, hold, and revision actions
                                </p>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                                <button type="submit" name="approve" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-md font-medium transition duration-200 shadow-sm">
                                    <i class="fas fa-check-circle mr-1"></i> Approve Document
                                </button>
                                
                                <button type="submit" name="reject" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-md font-medium transition duration-200 shadow-sm">
                                    <i class="fas fa-times-circle mr-1"></i> Reject Document
                                </button>
                                
                                <?php if ($document['status'] != 'on_hold'): ?>
                                <button type="submit" name="hold" class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-3 rounded-md font-medium transition duration-200 shadow-sm">
                                    <i class="fas fa-pause-circle mr-1"></i> Put On Hold
                                </button>
                                <?php else: ?>
                                <button type="submit" name="resume" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-md font-medium transition duration-200 shadow-sm">
                                    <i class="fas fa-play-circle mr-1"></i> Resume Document
                                </button>
                                <?php endif; ?>
                                
                                <button type="submit" name="request_revision" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-md font-medium transition duration-200 shadow-sm">
                                    <i class="fas fa-edit mr-1"></i> Request Revision
                                </button>
                            </div>
                            
                            <div class="mt-6 border-t pt-4">
                                <a href="dashboard.php" class="inline-block bg-gray-100 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-200 border border-gray-300 transition duration-200">
                                    <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <script>
                    // Validate form submission based on which button was clicked
                    document.getElementById('documentActionForm').addEventListener('submit', function(e) {
                        const comments = document.getElementById('comments').value.trim();
                        const submitter = e.submitter.name;
                        
                        if ((submitter === 'reject' || submitter === 'hold' || submitter === 'request_revision') && comments === '') {
                            e.preventDefault();
                            alert('Please provide comments or reasons when ' + 
                                  (submitter === 'reject' ? 'rejecting a document.' : 
                                   submitter === 'hold' ? 'putting a document on hold.' : 
                                   'requesting revisions.'));
                        }
                    });
                </script>
                <?php else: ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-md mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">You don't have permission to approve this document at this time.</p>
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
