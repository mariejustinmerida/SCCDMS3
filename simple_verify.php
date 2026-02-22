<?php
/**
 * Simple Document Verification
 * 
 * This is a completely new approach for document verification with QR codes
 */

require_once 'includes/config.php';

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

// Get document ID and verification code from URL
$document_id = isset($_GET['doc']) ? intval($_GET['doc']) : 0;
$verification_code = isset($_GET['code']) ? $_GET['code'] : '';

// Verification result variables
$is_valid = false;
$error_message = '';
$verification_data = null;
$document_data = null;
$workflow_data = [];

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

if (empty($document_id) || empty($verification_code)) {
    $error_message = 'Invalid verification link. Missing required parameters.';
} else {
    // Get verification information from database
    $stmt = $conn->prepare("SELECT v.*, u.username, o.office_name 
                          FROM simple_verifications v 
                          JOIN users u ON v.user_id = u.user_id 
                          JOIN offices o ON v.office_id = o.office_id 
                          WHERE v.document_id = ? AND v.verification_code = ?
                          ORDER BY v.created_at DESC LIMIT 1");
    $stmt->bind_param("is", $document_id, $verification_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error_message = 'Verification code not found or invalid.';
    } else {
        $verification_data = $result->fetch_assoc();
        $is_valid = true;
        
        // Get document information
        $doc_stmt = $conn->prepare("SELECT d.*, dt.type_name, u.username as creator_name 
                                  FROM documents d 
                                  JOIN document_types dt ON d.type_id = dt.type_id 
                                  JOIN users u ON d.creator_id = u.user_id 
                                  WHERE d.document_id = ?");
        $doc_stmt->bind_param("i", $document_id);
        $doc_stmt->execute();
        $doc_result = $doc_stmt->get_result();
        $document_data = $doc_result->fetch_assoc();
        
        // Get workflow information (all approvals)
        $workflow_stmt = $conn->prepare("SELECT dw.*, o.office_name 
                                      FROM document_workflow dw 
                                      JOIN offices o ON dw.office_id = o.office_id 
                                      WHERE dw.document_id = ? 
                                      ORDER BY dw.step_order ASC");
        
        if (!$workflow_stmt) {
            $error_message = "Database error: " . $conn->error;
            $is_valid = false;
            // Don't use break here since we're not in a loop
            return;
        }
        
        $workflow_stmt->bind_param("i", $document_id);
        $workflow_stmt->execute();
        $workflow_result = $workflow_stmt->get_result();
        
        while ($workflow_row = $workflow_result->fetch_assoc()) {
            $workflow_data[] = $workflow_row;
        }
    }
}

// Page title
$page_title = "Document Verification";
include_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-6">
            <div class="flex items-center justify-center mb-6">
                <img src="assets/images/scc_logo.png" alt="SCC Logo" class="h-16">
            </div>
            
            <h1 class="text-2xl font-bold text-center mb-6">Document Verification</h1>
            
            <?php if ($is_valid): ?>
                <!-- Valid Verification -->
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Valid Document</p>
                    <p>This document has been verified as authentic.</p>
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
                
                <!-- Verification Information -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold mb-3">Verification Information</h2>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-gray-600">Verified By:</p>
                                <p class="font-medium"><?php echo htmlspecialchars($verification_data['username']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Verifying Office:</p>
                                <p class="font-medium"><?php echo htmlspecialchars($verification_data['office_name']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Verification Date:</p>
                                <p class="font-medium"><?php echo date('F j, Y, g:i a', strtotime($verification_data['created_at'])); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600">Verification Code:</p>
                                <p class="font-medium font-mono"><?php echo htmlspecialchars($verification_data['verification_code']); ?></p>
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
                                    <td class="py-2 px-4 border-b"><?php echo isset($step['username']) ? htmlspecialchars($step['username']) : '-'; ?></td>
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
                <!-- Invalid Verification -->
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Invalid Verification</p>
                    <p><?php echo $error_message; ?></p>
                </div>
                
                <div class="text-center mt-6">
                    <a href="index.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        Return to Home
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
