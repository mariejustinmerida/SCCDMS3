<?php
/**
 * Document Signature Verification Page
 * 
 * This page verifies the authenticity of document signatures via QR code scanning
 */

require_once 'includes/config.php';
require_once 'includes/header.php';

// Get document ID and verification code from URL
$document_id = $_GET['doc'] ?? '';
$verification_code = $_GET['code'] ?? '';

// Verification result variables
$is_valid = false;
$error_message = '';
$signature_data = null;
$document_data = null;
$workflow_data = [];

if (empty($document_id) || empty($verification_code)) {
    $error_message = 'Invalid verification link. Missing required parameters.';
} else {
    // Get signature information from database using the verification code
    $stmt = $conn->prepare("SELECT s.*, u.username, o.office_name 
                          FROM signatures s 
                          JOIN users u ON s.user_id = u.user_id 
                          JOIN offices o ON s.office_id = o.office_id 
                          WHERE s.document_id = ? AND s.verification_hash = ?");
    $stmt->bind_param("is", $document_id, $verification_code);
    $stmt->execute();
    $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error_message = 'Signature not found.';
        } else {
            $signature_data = $result->fetch_assoc();
            
            // Check if signature is expired or revoked
            $now = new DateTime();
            $expires_at = new DateTime($signature_data['expires_at']);
            
            if ($signature_data['is_revoked']) {
                $error_message = 'This signature has been revoked and is no longer valid.';
            } elseif ($now > $expires_at) {
                $error_message = 'This signature has expired on ' . $expires_at->format('F j, Y, g:i a') . '.';
            } else {
                $is_valid = true;
                
                // Get document information
                $doc_stmt = $conn->prepare("SELECT d.*, dt.type_name, u.username as creator_name 
                                          FROM documents d 
                                          JOIN document_types dt ON d.type_id = dt.type_id 
                                          JOIN users u ON d.creator_id = u.user_id 
                                          WHERE d.id = ?");
                $doc_stmt->bind_param("i", $signature_data['document_id']);
                $doc_stmt->execute();
                $doc_result = $doc_stmt->get_result();
                $document_data = $doc_result->fetch_assoc();
                
                // Get workflow information (all approvals)
                $workflow_stmt = $conn->prepare("SELECT dw.*, o.office_name, u.username 
                                              FROM document_workflow dw 
                                              JOIN offices o ON dw.office_id = o.office_id 
                                              LEFT JOIN users u ON dw.approved_by = u.user_id 
                                              WHERE dw.document_id = ? 
                                              ORDER BY dw.step_order ASC");
                $workflow_stmt->bind_param("i", $signature_data['document_id']);
                $workflow_stmt->execute();
                $workflow_result = $workflow_stmt->get_result();
                
                while ($workflow_row = $workflow_result->fetch_assoc()) {
                    $workflow_data[] = $workflow_row;
                }
            }
        }
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-6">
            <div class="flex items-center justify-center mb-6">
                <img src="assets/images/scc_logo.png" alt="SCC Logo" class="h-16">
            </div>
            
            <h1 class="text-2xl font-bold text-center mb-6">Document Signature Verification</h1>
            
            <?php if ($is_valid): ?>
                <!-- Valid Signature -->
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Valid Signature</p>
                    <p>This document signature has been verified as authentic.</p>
                </div>
                
                <!-- Document Information -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold mb-3">Document Information</h2>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-gray-600">Document Title:</p>
                                <p class="font-medium"><?php echo htmlspecialchars($document_data['title']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Document Type:</p>
                                <p class="font-medium"><?php echo htmlspecialchars($document_data['type_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Created By:</p>
                                <p class="font-medium"><?php echo htmlspecialchars($document_data['creator_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Created Date:</p>
                                <p class="font-medium"><?php echo date('F j, Y, g:i a', strtotime($document_data['created_at'])); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Current Status:</p>
                                <p class="font-medium"><?php echo ucfirst(htmlspecialchars($document_data['status'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Signature Information -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold mb-3">Signature Information</h2>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-gray-600">Signed By:</p>
                                <p class="font-medium"><?php echo htmlspecialchars($signature_data['username']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Signing Office:</p>
                                <p class="font-medium"><?php echo htmlspecialchars($signature_data['office_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Signature Date:</p>
                                <p class="font-medium"><?php echo date('F j, Y, g:i a', strtotime($signature_data['created_at'])); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Signature Expires:</p>
                                <p class="font-medium"><?php echo date('F j, Y, g:i a', strtotime($signature_data['expires_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Workflow Approvals -->
                <div>
                    <h2 class="text-xl font-semibold mb-3">Approval Workflow</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border border-gray-200">
                            <thead>
                                <tr>
                                    <th class="py-2 px-4 border-b text-left">Step</th>
                                    <th class="py-2 px-4 border-b text-left">Office</th>
                                    <th class="py-2 px-4 border-b text-left">Status</th>
                                    <th class="py-2 px-4 border-b text-left">Approved By</th>
                                    <th class="py-2 px-4 border-b text-left">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($workflow_data as $step): ?>
                                <tr>
                                    <td class="py-2 px-4 border-b"><?php echo $step['step_order']; ?></td>
                                    <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($step['office_name']); ?></td>
                                    <td class="py-2 px-4 border-b">
                                        <?php if ($step['status'] == 'COMPLETED'): ?>
                                            <span class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">Approved</span>
                                        <?php elseif ($step['status'] == 'CURRENT'): ?>
                                            <span class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium">In Progress</span>
                                        <?php else: ?>
                                            <span class="inline-block bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs font-medium">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 px-4 border-b"><?php echo $step['username'] ? htmlspecialchars($step['username']) : '-'; ?></td>
                                    <td class="py-2 px-4 border-b">
                                        <?php 
                                        if (isset($step['completed_at']) && $step['completed_at']) {
                                            echo date('M j, Y', strtotime($step['completed_at']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Invalid Signature -->
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Invalid Signature</p>
                    <p><?php echo $error_message; ?></p>
                </div>
                
                <p class="text-center text-gray-600 mb-6">The signature you are trying to verify is not valid. This may be due to tampering, expiration, or revocation.</p>
                
                <div class="text-center">
                    <a href="index.php" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                        Return to Homepage
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="bg-gray-50 px-6 py-4 text-center text-sm text-gray-500">
            <p>This verification was performed on <?php echo date('F j, Y, g:i a'); ?></p>
            <p class="mt-1">SCC Document Management System &copy; <?php echo date('Y'); ?></p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
