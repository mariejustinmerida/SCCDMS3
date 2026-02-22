<?php
/**
 * Admin Document Verification Page
 * 
 * This page allows administrators and presidents to verify documents
 * using the verification code system.
 */

// This page should be included in dashboard.php
// Check if accessed directly
if (!defined('INCLUDED_IN_DASHBOARD')) {
    // Redirect to dashboard with the correct page parameter
    header("Location: dashboard.php?page=admin_verify");
    exit();
}

// Make sure we have access to the database connection
global $conn;

// Initialize variables
$verification_result = null;
$document_data = null;
$workflow_data = [];
$verification_data = null;
$error_message = '';
$document_id = 0;
$verification_type = null;

// Process verification form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify']) || 
    (isset($_GET['verify']) && isset($_GET['verification_code']))) {
    
    // Get verification code from POST or GET
    $verification_code = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verification_code'])) {
        $verification_code = trim($_POST['verification_code']);
    } elseif (isset($_GET['verification_code'])) {
        $verification_code = trim($_GET['verification_code']);
    }
    
    if (empty($verification_code)) {
        $error_message = "Please provide a verification code.";
    } else {
        // First try to verify using the documents table (new method)
        $stmt = $conn->prepare("SELECT d.*, d.title, d.status as doc_status, d.created_at, 
                                 d.file_path, d.google_doc_id,
                                 u.username, u.full_name as created_by_name, o.office_name 
                              FROM documents d
                              LEFT JOIN users u ON d.creator_id = u.user_id 
                              LEFT JOIN offices o ON u.office_id = o.office_id 
                              WHERE d.verification_code = ? LIMIT 1");
        
        if (!$stmt) {
            $error_message = "Database error: " . $conn->error;
            $verification_result = false;
        } else {
            $stmt->bind_param("s", $verification_code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Found in documents table
                $verification_data = $result->fetch_assoc();
                $verification_result = true;
                $document_id = $verification_data['document_id'];
                $document_data = $verification_data; // Use the data we already have
                $verification_type = 'document'; // Mark as document-based verification
            } else {
                // Not found in documents table, try the simple_verifications table
                $stmt = $conn->prepare("SELECT v.*, d.title, d.status as doc_status, d.created_at, 
                                       d.file_path, d.google_doc_id,
                                       u.username, u.full_name as created_by_name, o.office_name, v.document_id 
                                      FROM simple_verifications v 
                                      JOIN documents d ON v.document_id = d.document_id 
                                      JOIN users u ON v.user_id = u.user_id 
                                      JOIN offices o ON v.office_id = o.office_id 
                                      WHERE v.verification_code = ? LIMIT 1");
                
                if (!$stmt) {
                    $error_message = "Database error: " . $conn->error;
                    $verification_result = false;
                } else {
                    $stmt->bind_param("s", $verification_code);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        // Found in simple_verifications table
                        $verification_data = $result->fetch_assoc();
                        $verification_result = true;
                        $document_id = $verification_data['document_id'];
                        $document_data = $verification_data; // Use the data we already have
                        $verification_type = 'simple'; // Mark as simple verification
                    } else {
                        $verification_result = false;
                        $error_message = "Invalid verification code. Please check and try again.";
                    }
                }
            }
            
            // Get workflow information if verification was successful
            if ($verification_result && $document_id > 0) {
                $workflow_stmt = $conn->prepare("SELECT dw.*, o.office_name 
                                              FROM document_workflow dw 
                                              JOIN offices o ON dw.office_id = o.office_id 
                                              WHERE dw.document_id = ? 
                                              ORDER BY dw.step_order ASC");
                
                if ($workflow_stmt) {
                    $workflow_stmt->bind_param("i", $document_id);
                    $workflow_stmt->execute();
                    $workflow_result = $workflow_stmt->get_result();
                    
                    while ($workflow_row = $workflow_result->fetch_assoc()) {
                        $workflow_data[] = $workflow_row;
                    }
                }
            }
        }
    }
}
?>

<!-- Admin Document Verification Content -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mt-4 mb-6">
    <div class="border-b px-6 py-3 bg-[#163b20] flex justify-between items-center">
        <h1 class="text-xl font-semibold text-white flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            Document Verification
        </h1>
    </div>
    
    <div class="p-6">
        <p class="text-gray-600 mb-6">Enter a verification code to verify the authenticity of a document and view its details.</p>
        
        <?php if ($verification_result === true): ?>
            <!-- Valid Verification -->
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p class="font-bold">Valid Document</p>
                <p>This document has been verified as authentic.</p>
            </div>
            
            <!-- Document Details -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-3">Document Details</h2>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="mb-2"><strong>Title:</strong> <?php echo htmlspecialchars($document_data['title']); ?></p>
                    <p class="mb-2"><strong>Status:</strong> <?php echo htmlspecialchars($document_data['doc_status']); ?></p>
                    <p class="mb-2"><strong>Created On:</strong> <?php echo date('F j, Y, g:i a', strtotime($document_data['created_at'])); ?></p>
                </div>
            </div>
            
            <!-- QR Code for Document -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-3">QR Verification</h2>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- QR Code -->
                        <div class="text-center">
                            <div class="qr-code-container mx-auto" style="max-width: 200px;">
                                <?php
                                // Create URL for QR code verification
                                $verification_url = "http://" . $_SERVER['HTTP_HOST'] . "/SCCDMS2/document_with_qr.php?doc=" . 
                                                  $document_id . "&code=" . $verification_data['verification_code'];
                                
                                // Generate QR code image using Google Chart API
                                $qr_url = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=" . urlencode($verification_url) . "&choe=UTF-8";
                                ?>
                                <img src="<?php echo $qr_url; ?>" alt="QR Code" class="mb-2 mx-auto">
                                <p class="text-sm text-center text-gray-600">Scan to verify document</p>
                                <p class="font-mono text-sm text-center mt-2"><?php echo htmlspecialchars($verification_data['verification_code']); ?></p>
                                
                                <a href="<?php echo $verification_url; ?>" target="_blank" class="inline-block mt-3 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                    <i class="fas fa-external-link-alt mr-1"></i> View QR Document
                                </a>
                            </div>
                        </div>
                        
                        <!-- Document Preview -->
                        <div>
                            <h3 class="text-md font-semibold mb-2">Document Preview</h3>
                            <div class="border rounded-lg overflow-hidden bg-white <?php echo $hasGoogleDoc ? 'h-96' : 'h-64'; ?>">
                                <?php
                                // Get file path from database
                                $file_path = '';
                                $file_extension = '';
                                
                                // Check if this is a Google Doc
                                $hasGoogleDoc = !empty($verification_data['google_doc_id']);
                                $googleDocId = $verification_data['google_doc_id'] ?? '';
                                
                                if (isset($verification_data['file_path']) && !empty($verification_data['file_path'])) {
                                    $file_path = $verification_data['file_path'];
                                    
                                    // Function to fix file paths for proper display (from view_document.php)
                                    function fixFilePath($path) {
                                        $path = str_replace("\\", "/", $path);
                                        if (empty($path)) return '';
                                        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) return $path;
                                        if (strpos($path, 'storage/') === 0) $path = '../' . $path;
                                        else if (strpos($path, '../') !== 0 && strpos($path, '/') !== 0) $path = '../' . $path;
                                        return $path;
                                    }
                                    
                                    // Fix the file path using the approach from view_document.php
                                    $file_path = fixFilePath($file_path);
                                    $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
                                }
                                    
                                // Display document based on file type
                                if (!$hasGoogleDoc && !empty($file_path)) {
                                        switch (strtolower($file_extension)) {
                                            case 'txt':
                                                if (file_exists($file_path)) {
                                                    $content = nl2br(htmlspecialchars(file_get_contents($file_path)));
                                                    echo "<div class='p-4 bg-white rounded-lg border text-gray-800 whitespace-pre-wrap overflow-auto h-full'>$content</div>";
                                                }
                                                break;
                                            case 'html':
                                            case 'htm':
                                                echo "<iframe src='$file_path' width='100%' height='100%' class='border-0 rounded-lg'></iframe>";
                                                break;
                                            case 'pdf':
                                                echo "<iframe src='$file_path' width='100%' height='100%' class='border-0 rounded-lg'></iframe>";
                                                break;
                                            case 'docx':
                                            case 'doc':
                                                echo "<div class='text-center p-6 bg-blue-50 rounded-lg h-full flex flex-col items-center justify-center'>
                                                      <svg xmlns='http://www.w3.org/2000/svg' class='h-12 w-12 text-blue-600 mx-auto mb-4' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                                          <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/>
                                                      </svg>
                                                      <h3 class='text-md font-semibold mb-2'>Microsoft Word Document</h3>
                                                      <p class='text-gray-600 mb-4 text-sm'>Preview not available for this file type.</p>
                                                      <a href='$file_path' download class='inline-flex items-center px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm'>
                                                          <i class='fas fa-download mr-1'></i> Download
                                                      </a>
                                                    </div>";
                                                break;
                                            case 'jpg':
                                            case 'jpeg':
                                            case 'png':
                                            case 'gif':
                                                echo "<img src='$file_path' alt='Document Preview' class='max-w-full max-h-full object-contain mx-auto'>";
                                                break;
                                            default:
                                                echo "<div class='text-center p-6 bg-gray-50 rounded-lg h-full flex flex-col items-center justify-center'>
                                                      <svg xmlns='http://www.w3.org/2000/svg' class='h-12 w-12 text-gray-500 mx-auto mb-4' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                                          <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h7.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/>
                                                      </svg>
                                                      <h3 class='text-md font-semibold mb-2'>File Preview Unavailable</h3>
                                                      <p class='text-gray-600 mb-4 text-sm'>This file type cannot be previewed directly.</p>
                                                      <a href='$file_path' download class='inline-flex items-center px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm'>
                                                          <i class='fas fa-download mr-1'></i> Download
                                                      </a>
                                                    </div>";
                                        }
                                } else if ($hasGoogleDoc) {
                                    // Display Google Doc in an iframe
                                    echo "<iframe src='https://docs.google.com/document/d/$googleDocId/preview' width='100%' height='100%' class='border-0 rounded-lg'></iframe>";
                                } else {
                                    echo "<div class='flex items-center justify-center h-full'>
                                            <p class='text-sm text-gray-500'>No document content available.</p>
                                          </div>";
                                }
                                ?>
                            </div>
                            
                            <?php if (!empty($file_path) || $hasGoogleDoc): ?>
                            <div class="mt-2 text-center">
                                <?php if (!empty($file_path)): ?>
                                <a href="<?php echo $file_path; ?>" target="_blank" class="inline-block px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    <i class="fas fa-external-link-alt mr-1"></i> Open Full Document
                                </a>
                                <?php endif; ?>
                                <?php if ($hasGoogleDoc): ?>
                                <a href="https://docs.google.com/document/d/<?php echo $googleDocId; ?>/edit" target="_blank" class="inline-block px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    <i class="fas fa-external-link-alt mr-1"></i> Open in Google Docs
                                </a>
                                <?php endif; ?>
                                <a href="dashboard.php?page=view_document&id=<?php echo $verification_data['document_id']; ?>" class="inline-block px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700 ml-2">
                                    <i class="fas fa-eye mr-1"></i> View in Document Viewer
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Verification Details -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-3">Verification Details</h2>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="mb-2"><strong>Document ID:</strong> DOC-<?php echo str_pad($verification_data['document_id'], 3, '0', STR_PAD_LEFT); ?></p>
                    
                    <?php if ($verification_type == 'simple'): ?>
                    <!-- Display verification details for simple verification -->
                    <p class="mb-2"><strong>Verified By:</strong> <?php echo htmlspecialchars($verification_data['username'] ?? 'Unknown'); ?></p>
                    <p class="mb-2"><strong>Verifying Office:</strong> <?php echo htmlspecialchars($verification_data['office_name'] ?? 'Unknown'); ?></p>
                    <p class="mb-2"><strong>Verification Code:</strong> <span class="font-mono bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($verification_data['verification_code']); ?></span></p>
                    <p class="mb-2"><strong>Verified On:</strong> <?php echo date('F j, Y, g:i a', strtotime($verification_data['created_at'])); ?></p>
                    <?php else: ?>
                    <!-- Display document details for document-based verification -->
                    <p class="mb-2"><strong>Creator:</strong> <?php echo htmlspecialchars($verification_data['created_by_name'] ?? 'Unknown'); ?></p>
                    <p class="mb-2"><strong>Office:</strong> <?php echo htmlspecialchars($verification_data['office_name'] ?? 'Unknown'); ?></p>
                    <p class="mb-2"><strong>Verification Code:</strong> <span class="font-mono bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($verification_data['verification_code']); ?></span></p>
                    <p class="mb-2"><strong>Created On:</strong> <?php echo date('F j, Y, g:i a', strtotime($verification_data['created_at'])); ?></p>
                    <p class="mb-2"><strong>Last Updated:</strong> <?php echo date('F j, Y, g:i a', strtotime($verification_data['updated_at'] ?? $verification_data['created_at'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
                
                <!-- Approval Workflow -->
                <div>
                    <h2 class="text-xl font-semibold mb-3">Approval Workflow</h2>
                    <?php if (!empty($workflow_data)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border border-gray-200">
                            <thead>
                                <tr>
                                    <th class="py-2 px-4 border-b bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Step</th>
                                    <th class="py-2 px-4 border-b bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Office</th>
                                    <th class="py-2 px-4 border-b bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="py-2 px-4 border-b bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
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
                    <?php else: ?>
                    <div class="bg-gray-50 p-4 rounded-lg text-gray-500">
                        No workflow information available for this document.
                    </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($verification_result === false): ?>
                <!-- Invalid Verification -->
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Invalid Verification</p>
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>
                        <!-- Verification Form -->
            <div class="mt-8">
                <h2 class="text-xl font-semibold mb-4">Verify a Document</h2>
                <form method="POST" action="">
                    <div class="mb-4 max-w-md">
                        <label for="verification_code" class="block text-sm font-medium text-gray-700 mb-1">Enter Verification Code</label>
                        <input type="text" id="verification_code" name="verification_code" required 
                               placeholder="Enter 6-digit code" pattern="[0-9]{6}"
                               value="<?php echo isset($_GET['verification_code']) ? htmlspecialchars($_GET['verification_code']) : ''; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-lg font-mono tracking-wider">
                        <p class="text-sm text-gray-500 mt-1">Only the verification code is needed. The system will find the matching document automatically.</p>
                    </div>
                    <div>
                        <button type="submit" name="verify" value="1" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Verify Document
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- No back button needed since we're already in the dashboard -->
        </div>
    </div>

    <!-- No footer needed here as it's included in the dashboard layout -->
