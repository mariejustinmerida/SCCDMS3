<?php
// This file is included by dashboard.php
if (!defined('INCLUDED_IN_DASHBOARD')) {
    header("Location: dashboard.php?page=documents");
    exit();
}

// Get document ID from query parameter
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If no document ID provided, redirect to documents page
if (empty($document_id)) {
    header("Location: dashboard.php?page=documents");
    exit();
}

// Get document details from database for generating QR code
$document = null;
$verification_code = '';

try {
    // Get document details
    $stmt = $conn->prepare("SELECT * FROM documents WHERE document_id = ?");
    
    // Check if statement preparation was successful
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $document = $result->fetch_assoc();
        
        // Generate or retrieve verification code
        if (!empty($document['verification_code'])) {
            $verification_code = $document['verification_code'];
        } else {
            // Generate a new 6-digit verification code
            $verification_code = mt_rand(100000, 999999);
            
            // Try to update verification_code in the database
            try {
                $update_stmt = $conn->prepare("UPDATE documents SET verification_code = ? WHERE document_id = ?");
                
                // Check if statement preparation was successful
                if (!$update_stmt) {
                    // Just log the error but continue with the generated code
                    error_log("Could not prepare statement to update verification code: " . $conn->error);
                } else {
                    $update_stmt->bind_param("si", $verification_code, $document_id);
                    $update_stmt->execute();
                    // No need to check for errors here, we'll continue even if update fails
                }
            } catch (Exception $e) {
                // Just log the error but continue with the generated code
                error_log("Error updating verification code: " . $e->getMessage());
            }
        }
    } else {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p>Document not found.</p>
              </div>';
        exit();
    }
} catch (Exception $e) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>Error: ' . $e->getMessage() . '</p>
          </div>';
    exit();
}

// Create verification URL for QR code
$verification_url = "http://" . $_SERVER['HTTP_HOST'] . "/SCCDMS2/document_with_qr.php?doc=" . $document_id . "&code=" . $verification_code;

// Get QR code image URL
$qr_image_url = "../qr_display.php?url=" . urlencode($verification_url) . "&size=150";

// Check if the current user can take action on this document (similar to incoming.php)
$can_take_action = false;
$user_office_id = $_SESSION['office_id'] ?? 0;

if ($user_office_id > 0) {
    // Check if document is in current office's workflow with status 'CURRENT'
    $check_query = "SELECT * FROM document_workflow 
                   WHERE document_id = ? AND office_id = ? AND UPPER(status) = 'CURRENT'";
    $check_stmt = $conn->prepare($check_query);
    
    if ($check_stmt) {
        $check_stmt->bind_param("ii", $document_id, $user_office_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result && $check_result->num_rows > 0) {
            $can_take_action = true;
        }
    }
}
?>

<!-- Tung Tung Sahur -->
<div class="bg-white rounded-lg shadow-md">
    <div class="flex flex-col md:flex-row justify-between items-center p-4 border-b">
        <div>
            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($document['title']); ?></h1>
            <div class="flex items-center text-sm text-gray-500">
                <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
                <span class="mx-2">/</span>
                <a href="dashboard.php?page=documents" class="hover:text-gray-700">Documents</a>
                <span class="mx-2">/</span>
                <span>View Document</span>
            </div>
        </div>
        <div class="flex items-center gap-2 mt-4 md:mt-0">
            <a href="dashboard.php?page=documents" class="px-3 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                <i class="fas fa-arrow-left mr-1"></i> Back to Documents
            </a>
            <a href="dashboard.php?page=document_with_qr_wrapper&doc=<?php echo $document_id; ?>&code=<?php echo $verification_code; ?>" class="px-3 py-2 bg-purple-600 text-white rounded hover:bg-purple-700">
                <i class="fas fa-qrcode mr-1"></i> Document with QR
            </a>
            <?php if ($can_take_action): ?>
            <a href="dashboard.php?page=incoming" class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                <i class="fas fa-inbox mr-1"></i> Back to Inbox
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Document Content -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6">
        <!-- Document Metadata -->
        <div class="md:col-span-1">
            <div class="bg-white rounded-lg border p-5 mb-6">
                <h2 class="text-lg font-semibold mb-4 pb-2 border-b">Document Details</h2>
                
                <!-- Status Badge -->
                <div class="mb-4">
                    <p class="text-sm text-gray-500 mb-1">Status</p>
                    <?php
                    $status = $document['status'] ?? 'pending';
                    $status_class = 'badge-pending';
                    $status_text = 'Pending';
                    
                    switch($status) {
                        case 'approved':
                            $status_class = 'badge-approved';
                            $status_text = 'Approved';
                            break;
                        case 'rejected':
                            $status_class = 'badge-rejected';
                            $status_text = 'Rejected';
                            break;
                        case 'revision':
                        case 'revision_requested':
                            $status_class = 'badge-revision_requested';
                            $status_text = 'Revision Requested';
                            break;
                        case 'on_hold':
                        case 'hold':
                            $status_class = 'badge-on_hold';
                            $status_text = 'On Hold';
                            break;
                        default:
                            $status_class = 'badge-pending';
                            $status_text = 'Pending';
                    }
                    ?>
                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-500 mb-1">Document ID</p>
                    <p class="font-medium">DOC-<?php echo str_pad($document['document_id'], 3, '0', STR_PAD_LEFT); ?></p>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-500 mb-1">Document Type</p>
                    <p class="font-medium"><?php 
                        // Get document type from database
                        $type_name = "Unknown";
                        if (!empty($document['type_id'])) {
                            $type_query = "SELECT type_name FROM document_types WHERE type_id = ?";
                            $type_stmt = $conn->prepare($type_query);
                            
                            // Check if statement preparation was successful
                            if (!$type_stmt) {
                                echo "Unknown (DB Error)";
                            } else {
                                $type_stmt->bind_param("i", $document['type_id']);
                                $type_stmt->execute();
                                $type_result = $type_stmt->get_result();
                                if ($type_row = $type_result->fetch_assoc()) {
                                    $type_name = $type_row['type_name'];
                                }
                            }
                        }
                        echo htmlspecialchars($type_name);
                    ?></p>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-500 mb-1">Created By</p>
                    <p class="font-medium"><?php 
                        // Get creator name from database
                        $creator_name = "Unknown";
                        if (!empty($document['creator_id'])) {
                            $creator_query = "SELECT full_name FROM users WHERE user_id = ?";
                            $creator_stmt = $conn->prepare($creator_query);
                            
                            // Check if statement preparation was successful
                            if (!$creator_stmt) {
                                echo "Unknown (DB Error)";
                            } else {
                                $creator_stmt->bind_param("i", $document['creator_id']);
                                $creator_stmt->execute();
                                $creator_result = $creator_stmt->get_result();
                                if ($creator_row = $creator_result->fetch_assoc()) {
                                    $creator_name = $creator_row['full_name'];
                                }
                            }
                        }
                        echo htmlspecialchars($creator_name);
                    ?></p>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-500 mb-1">From Office</p>
                    <p class="font-medium"><?php 
                        // Get office name from database
                        $office_name = "Unknown";
                        if (!empty($document['creator_id'])) {
                            $office_query = "SELECT o.office_name FROM users u 
                                            JOIN offices o ON u.office_id = o.office_id 
                                            WHERE u.user_id = ?";
                            $office_stmt = $conn->prepare($office_query);
                            
                            // Check if statement preparation was successful
                            if (!$office_stmt) {
                                echo "Unknown (DB Error)";
                            } else {
                                $office_stmt->bind_param("i", $document['creator_id']);
                                $office_stmt->execute();
                                $office_result = $office_stmt->get_result();
                                if ($office_row = $office_result->fetch_assoc()) {
                                    $office_name = $office_row['office_name'];
                                }
                            }
                        }
                        echo htmlspecialchars($office_name);
                    ?></p>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-500 mb-1">Created On</p>
                    <p class="font-medium"><?php echo date('M j, Y', strtotime($document['created_at'] ?? 'now')); ?></p>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-500 mb-1">Last Updated</p>
                    <p class="font-medium"><?php echo date('M j, Y', strtotime($document['updated_at'] ?? 'now')); ?></p>
                </div>
                
                <!-- Document Attachments -->
                <?php
                // Get document attachments
                $attachments = [];
                $attachments_query = "SELECT * FROM document_attachments WHERE document_id = ? ORDER BY created_at DESC";
                $attachments_stmt = $conn->prepare($attachments_query);
                
                if ($attachments_stmt) {
                    $attachments_stmt->bind_param("i", $document_id);
                    $attachments_stmt->execute();
                    $attachments_result = $attachments_stmt->get_result();
                    
                    while ($attachment = $attachments_result->fetch_assoc()) {
                        $attachments[] = $attachment;
                    }
                }
                ?>
                
                <?php if (!empty($attachments)): ?>
                <div class="mb-4">
                    <p class="text-sm text-gray-500 mb-2">Attachments</p>
                    <div class="space-y-2">
                        <?php foreach ($attachments as $attachment): ?>
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($attachment['file_name']); ?></p>
                                    <p class="text-xs text-gray-500">
                                        <?php 
                                        $file_size = $attachment['file_size'] ?? 0;
                                        if ($file_size > 0) {
                                            // Format file size
                                            if ($file_size < 1024) {
                                                echo $file_size . ' B';
                                            } elseif ($file_size < 1024 * 1024) {
                                                echo round($file_size / 1024, 1) . ' KB';
                                            } else {
                                                echo round($file_size / (1024 * 1024), 1) . ' MB';
                                            }
                                        }
                                        ?>
                                        • <?php echo date("M j, Y", strtotime($attachment['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            <a href="../uploads/<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                               download="<?php echo htmlspecialchars($attachment['file_name']); ?>"
                               class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                Download
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- QR Code Section -->
                <div class="mt-6 pt-4 border-t">
                    <h3 class="text-md font-semibold mb-3">QR Verification</h3>
                    <div class="flex flex-col items-center">
                        <img src="<?php echo $qr_image_url; ?>" alt="QR Code" class="w-32 h-32 mb-2">
                        <p class="text-xs text-gray-500 text-center mb-1">Scan to verify document</p>
                        <p class="text-sm font-mono bg-gray-100 px-2 py-1 rounded"><?php echo $verification_code; ?></p>
                    </div>
                </div>
                
                <!-- Document Actions Section (only show if user can take action) -->
                <?php if ($can_take_action): ?>
                <div class="mt-6 pt-4 border-t">
                    <h3 class="text-md font-semibold mb-3">Document Actions</h3>
                    <div class="space-y-2">
                        <button type="button" 
                                onclick="openActionModal(<?php echo $document_id; ?>, 'approve', '<?php echo addslashes($document['title']); ?>', event)"
                                class="w-full text-left block px-3 py-2 hover:bg-green-50 text-sm flex items-center cursor-pointer border border-green-200 rounded-lg transition-colors">
                            <i class="fas fa-check mr-2 text-green-700"></i>
                            <span class="font-medium text-green-700">Approve</span>
                        </button>
                        <button type="button" 
                                onclick="openActionModal(<?php echo $document_id; ?>, 'reject', '<?php echo addslashes($document['title']); ?>', event)"
                                class="w-full text-left block px-3 py-2 hover:bg-red-50 text-sm flex items-center cursor-pointer border border-red-200 rounded-lg transition-colors">
                            <i class="fas fa-times mr-2 text-red-600"></i>
                            <span class="font-medium text-red-600">Reject</span>
                        </button>
                        <button type="button" 
                                onclick="openActionModal(<?php echo $document_id; ?>, 'request_revision', '<?php echo addslashes($document['title']); ?>', event)"
                                class="w-full text-left block px-3 py-2 hover:bg-amber-50 text-sm flex items-center cursor-pointer border border-amber-200 rounded-lg transition-colors">
                            <i class="fas fa-edit mr-2 text-amber-600"></i>
                            <span class="font-medium text-amber-600">Request Revision</span>
                        </button>
                        <button type="button" 
                                onclick="openActionModal(<?php echo $document_id; ?>, 'hold', '<?php echo addslashes($document['title']); ?>', event)"
                                class="w-full text-left block px-3 py-2 hover:bg-blue-50 text-sm flex items-center cursor-pointer border border-blue-200 rounded-lg transition-colors">
                            <i class="fas fa-pause-circle mr-2 text-blue-600"></i>
                            <span class="font-medium text-blue-600">Put on Hold</span>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Document Preview -->
        <div class="md:col-span-2">
            <div class="bg-white rounded-lg border p-5">
                <div class="flex justify-between items-center mb-4 pb-2 border-b">
                    <h2 class="text-lg font-semibold">Document Preview</h2>
                    <div class="flex gap-2">
                        <button id="checkGrammarBtn" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 flex items-center">
                            <i class="fas fa-spell-check mr-1"></i> Check Grammar
                        </button>
                        <button id="toggleOriginalBtn" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center hidden">
                            <i class="fas fa-eye mr-1"></i> Show Original
                        </button>
                    </div>
                </div>
                <div id="documentPreviewContainer">
                <?php
                // Function to fix file paths for proper display
                function fixFilePath($path) {
                    $path = str_replace("\\", "/", $path);
                    if (empty($path)) return '';
                    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) return $path;
                    if (strpos($path, 'storage/') === 0) $path = '../' . $path;
                    else if (strpos($path, '../') !== 0 && strpos($path, '/') !== 0) $path = '../' . $path;
                    return $path;
                }
                
                // Get file path and prepare for display
                $filePath = $document['file_path'] ?? '';
                $filePath = fixFilePath($filePath);
                $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
                
                // Check if this is a Google Doc
                $hasGoogleDoc = !empty($document['google_doc_id']);
                $googleDocId = $document['google_doc_id'] ?? '';
                
                // Display document based on file type
                if (!$hasGoogleDoc && !empty($filePath)) {
                    switch (strtolower($fileExtension)) {
                        case 'txt':
                            if (file_exists($filePath)) {
                                $content = nl2br(htmlspecialchars(file_get_contents($filePath)));
                                echo "<div id='documentContent' class='p-4 bg-white rounded-lg border text-gray-800 whitespace-pre-wrap'>$content</div>";
                            }
                            break;
                        case 'html':
                        case 'htm':
                            echo "<iframe id='documentFrame' src='$filePath' width='100%' height='700px' class='border-0 rounded-lg'></iframe>";
                            break;
                        case 'pdf':
                            echo "<iframe id='documentFrame' src='$filePath' width='100%' height='700px' class='border-0 rounded-lg'></iframe>";
                            break;
                        case 'docx':
                        case 'doc':
                            echo "<div class='text-center p-6 bg-blue-50 rounded-lg mb-4'>
                                  <svg xmlns='http://www.w3.org/2000/svg' class='h-16 w-16 text-blue-600 mx-auto mb-4' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                      <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/>
                                  </svg>
                                  <h3 class='text-xl font-semibold mb-2'>Microsoft Word Document</h3>
                                  <p class='text-gray-600 mb-4'>Preview not available for this file type.</p>
                                  <a href='$filePath' download class='inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700'>
                                      <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 mr-2' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                          <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'/>
                                      </svg>
                                      Download Document
                                  </a>
                                </div>";
                            break;
                        case 'jpg':
                        case 'jpeg':
                        case 'png':
                        case 'gif':
                            echo "<img src='$filePath' alt='Document Preview' class='max-w-full h-auto mx-auto rounded-lg'>";
                            break;
                        default:
                            echo "<div class='text-center p-6 bg-gray-50 rounded-lg mb-4'>
                                  <svg xmlns='http://www.w3.org/2000/svg' class='h-16 w-16 text-gray-500 mx-auto mb-4' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                      <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h7.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/>
                                  </svg>
                                  <h3 class='text-xl font-semibold mb-2'>File Preview Unavailable</h3>
                                  <p class='text-gray-600 mb-4'>This file type cannot be previewed directly in the browser.</p>
                                  <a href='$filePath' download class='inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700'>
                                      <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 mr-2' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                          <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'/>
                                      </svg>
                                      Download Document
                                  </a>
                                </div>";
                    }
                } else if ($hasGoogleDoc) {
                    echo "<iframe id='documentFrame' src='https://docs.google.com/document/d/$googleDocId/preview' width='100%' height='700px' class='border-0 rounded-lg'></iframe>";
                } else {
                    echo "<p class='text-gray-500'>No document content available.</p>";
                }
                ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grammar Check Modal (Hidden by default) -->
<div id="grammarModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000] hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] flex flex-col">
        <div class="p-4 border-b flex justify-between items-center bg-green-50">
            <h3 class="text-xl font-semibold text-green-800">Grammar & Spelling Check</h3>
            <button id="closeGrammarModal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto flex-grow">
            <div id="grammarLoading" class="flex flex-col items-center justify-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-500 mb-4"></div>
                <p class="text-gray-600">Checking document for grammar and spelling issues...</p>
            </div>
            <div id="grammarContent" class="hidden">
                <!-- Grammar check results will be populated here by JavaScript -->
            </div>
            <div id="grammarError" class="hidden">
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                        <p class="text-red-700" id="grammarErrorText">Error checking grammar. Please try again later.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 bg-gray-50 rounded-b-lg flex justify-end gap-3">
            <button id="closeGrammarBtn" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100">
                Close
            </button>
        </div>
    </div>
</div>

<!-- AI Analysis Modal (Hidden by default) -->
<div id="aiAnalysisModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000] hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] flex flex-col">
        <div class="p-4 border-b flex justify-between items-center bg-indigo-50">
            <h3 class="text-xl font-semibold text-indigo-800">AI Document Analysis</h3>
            <button id="closeAiAnalysisModal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto flex-grow">
            <div id="aiAnalysisLoading" class="flex flex-col items-center justify-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-500 mb-4"></div>
                <p class="text-gray-600">AI is analyzing your document...</p>
            </div>
            <div id="aiAnalysisContent" class="hidden">
                <!-- Analysis content will be populated here by JavaScript -->
            </div>
            <div id="aiAnalysisError" class="hidden">
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                        <p class="text-red-700" id="aiAnalysisErrorText">Error analyzing document. Please try again later.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 bg-gray-50 rounded-b-lg flex justify-end gap-3">
            <button id="closeAiAnalysisBtn" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100">
                Close
            </button>
            <button id="copyAiAnalysisBtn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 flex items-center gap-2">
                <i class="fas fa-copy"></i>
                Copy Analysis
            </button>
        </div>
    </div>
</div>

<style>
    .badge {
        display: inline-block;
        padding: 0.25em 0.6em;
        font-size: 75%;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.375rem;
    }
    .badge-pending {
        background-color: #FEF3C7;
        color: #92400E;
    }
    .badge-approved {
        background-color: #D1FAE5;
        color: #065F46;
    }
    .badge-rejected {
        background-color: #FEE2E2;
        color: #B91C1C;
    }
    .badge-hold, .badge-on_hold {
        background-color: #E0F2FE;
        color: #0369A1;
    }
    .badge-revision, .badge-revision_requested {
        background-color: #F3E8FF;
        color: #7E22CE;
    }
</style>

<script>
    // AI Analysis Modal Handler
    document.getElementById('aiAnalysisBtn').addEventListener('click', function() {
        const aiAnalysisModal = document.getElementById('aiAnalysisModal');
        const aiAnalysisLoading = document.getElementById('aiAnalysisLoading');
        const aiAnalysisContent = document.getElementById('aiAnalysisContent');
        const aiAnalysisError = document.getElementById('aiAnalysisError');
        
        // Show the modal
        aiAnalysisModal.classList.remove('hidden');
        aiAnalysisLoading.classList.remove('hidden');
        aiAnalysisContent.classList.add('hidden');
        aiAnalysisError.classList.add('hidden');
        
        // Call AI analysis endpoint
        fetch('../actions/ai_document_processor.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                documentId: '<?php echo $document_id; ?>',
                fileName: '<?php echo addslashes($document['title'] ?? ''); ?>',
                operation: 'all'
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            aiAnalysisLoading.classList.add('hidden');
            
            if (data.success !== false) {
                // Display the analysis results
                aiAnalysisContent.classList.remove('hidden');
                aiAnalysisContent.innerHTML = `
                    <div class="mb-6 p-4 bg-indigo-50 border-l-4 border-indigo-500 rounded">
                        <h4 class="font-semibold text-lg text-indigo-800 mb-2">Document Summary</h4>
                        <div class="prose max-w-none">${data.summary || 'No summary available.'}</div>
                    </div>
                    
                    <div class="mb-6">
                        <h4 class="font-semibold text-lg text-gray-800 mb-2">Key Points</h4>
                        <ul class="list-disc pl-5 space-y-1 text-gray-700">
                            ${data.key_points ? data.key_points.map(point => `<li>${point}</li>`).join('') : '<li>No key points identified.</li>'}
                        </ul>
                    </div>
                    
                    <div class="mb-6">
                        <h4 class="font-semibold text-lg text-gray-800 mb-2">Sentiment Analysis</h4>
                        <div class="p-3 bg-gray-50 rounded">
                            <p class="text-gray-700">${data.sentiment || 'Sentiment analysis not available.'}</p>
                        </div>
                    </div>
                `;
            } else {
                // Show error
                aiAnalysisError.classList.remove('hidden');
                document.getElementById('aiAnalysisErrorText').textContent = data.error || 'Error analyzing document. Please try again later.';
            }
        })
        .catch(error => {
            aiAnalysisLoading.classList.add('hidden');
            aiAnalysisError.classList.remove('hidden');
            document.getElementById('aiAnalysisErrorText').textContent = error.message || 'Failed to analyze document. Please try again later.';
        });
    });
    
    // Close modal handlers
    document.getElementById('closeAiAnalysisModal').addEventListener('click', function() {
        document.getElementById('aiAnalysisModal').classList.add('hidden');
    });
    
    document.getElementById('closeAiAnalysisBtn').addEventListener('click', function() {
        document.getElementById('aiAnalysisModal').classList.add('hidden');
    });
    
    // Copy analysis handler
    document.getElementById('copyAiAnalysisBtn').addEventListener('click', function() {
        const content = document.getElementById('aiAnalysisContent').innerText;
        
        navigator.clipboard.writeText(content).then(function() {
            alert('Analysis copied to clipboard!');
        }, function(err) {
            console.error('Could not copy text: ', err);
            alert('Failed to copy to clipboard');
        });
    });
</script>

<!-- Include Grammar Checker Script -->
<script src="../assets/js/grammar-checker.js"></script>

<!-- Include Memorandum Tracking Script -->
<script src="../assets/js/memorandum-tracking.js"></script>

<!-- Document Action Modal (from incoming.php) -->
<div id="actionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000] hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
        <div class="p-4 border-b flex justify-between items-center">
            <h3 class="text-xl font-semibold text-gray-800" id="actionModalTitle">Document Action</h3>
            <button id="closeActionModal" class="text-gray-500 hover:text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="p-6">
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-2">Document: <span class="font-medium" id="actionDocumentTitle"></span></p>
            </div>
            <div class="mb-4">
                <label for="actionComments" class="block text-sm font-medium text-gray-700 mb-2">
                    <span id="actionLabel">Reason/Comments:</span>
                    <span id="actionRequired" class="text-red-500 ml-1">*</span>
                </label>
                <textarea id="actionComments" name="comments" rows="4" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                          placeholder="Enter your reason or comments here..."></textarea>
                <p class="text-xs text-gray-500 mt-1" id="actionHelpText">This field is required for reject, hold, and revision actions.</p>
            </div>
            <div id="actionError" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm"></div>
            <div class="flex justify-end space-x-2">
                <button id="cancelActionBtn" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Cancel</button>
                <button id="submitActionBtn" class="px-4 py-2 rounded text-white font-medium" style="background-color: #16a34a;">
                    <span id="submitActionText">Submit</span>
                    <span id="submitActionSpinner" class="hidden"><i class="fas fa-spinner fa-spin"></i> Processing...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Document Action Modal Script -->
<script>
    // Document Action Modal Functions (from incoming.php)
    let currentActionData = { documentId: null, action: null, title: null };

    // Make function globally accessible
    window.openActionModal = function(documentId, action, title, e) {
        console.log('openActionModal called:', { documentId, action, title });
        
        // Prevent any default behavior if event is provided
        if (e) {
            if (e.preventDefault) e.preventDefault();
            if (e.stopPropagation) e.stopPropagation();
        }
        
        currentActionData = { documentId, action, title };
        const modal = document.getElementById('actionModal');
        
        if (!modal) {
            console.error('Action modal not found');
            alert('Error: Modal not found. Please refresh the page.');
            return false;
        }
        
        console.log('Modal found, showing modal...');
        
        const modalTitle = document.getElementById('actionModalTitle');
        const documentTitle = document.getElementById('actionDocumentTitle');
        const actionLabel = document.getElementById('actionLabel');
        const actionRequired = document.getElementById('actionRequired');
        const actionHelpText = document.getElementById('actionHelpText');
        const submitBtn = document.getElementById('submitActionBtn');
        const submitText = document.getElementById('submitActionText');
        const actionError = document.getElementById('actionError');
        const commentsField = document.getElementById('actionComments');
        
        if (!modalTitle || !documentTitle || !actionLabel || !submitBtn || !commentsField) {
            console.error('One or more modal elements not found');
            alert('Error: Modal elements not found. Please refresh the page.');
            return false;
        }
        
        // Reset form
        if (commentsField) commentsField.value = '';
        if (actionError) {
            actionError.classList.add('hidden');
            actionError.textContent = '';
        }
        
        // Set titles and labels based on action
        documentTitle.textContent = title;
        
        const actionLabels = {
            'approve': { title: 'Approve Document', label: 'Comments (Optional):', required: false, help: 'Optional: Add any comments or notes about this approval.', color: '#16a34a' },
            'reject': { title: 'Reject Document', label: 'Reason for Rejection:', required: true, help: 'Please provide a reason for rejecting this document.', color: '#dc2626' },
            'hold': { title: 'Put Document on Hold', label: 'Reason for Hold:', required: true, help: 'Please provide a reason for putting this document on hold.', color: '#2563eb' },
            'request_revision': { title: 'Request Revision', label: 'Revision Details:', required: true, help: 'Please provide details about what needs to be revised.', color: '#d97706' }
        };
        
        const actionConfig = actionLabels[action] || actionLabels.approve;
        modalTitle.textContent = actionConfig.title;
        actionLabel.textContent = actionConfig.label;
        actionRequired.style.display = actionConfig.required ? 'inline' : 'none';
        actionHelpText.textContent = actionConfig.help;
        submitBtn.style.backgroundColor = actionConfig.color;
        submitText.textContent = actionConfig.title.split(' ')[0]; // "Approve", "Reject", etc.
        
        // Show modal
        modal.classList.remove('hidden');
        
        // Focus on textarea
        setTimeout(() => {
            if (commentsField) commentsField.focus();
        }, 100);
        
        return false;
    }

    function closeActionModal() {
        const modal = document.getElementById('actionModal');
        if (modal) {
            modal.classList.add('hidden');
        }
        currentActionData = { documentId: null, action: null, title: null };
    }

    function submitDocumentAction() {
        const { documentId, action } = currentActionData;
        const comments = document.getElementById('actionComments').value.trim();
        const submitBtn = document.getElementById('submitActionBtn');
        const submitText = document.getElementById('submitActionText');
        const submitSpinner = document.getElementById('submitActionSpinner');
        const actionError = document.getElementById('actionError');
        const commentsField = document.getElementById('actionComments');
        
        // Validate required fields
        const requiredActions = ['reject', 'hold', 'request_revision'];
        if (requiredActions.includes(action) && comments === '') {
            actionError.classList.remove('hidden');
            actionError.textContent = 'Please provide a reason before submitting.';
            commentsField.focus();
            return;
        }
        
        // Disable submit button and show spinner
        submitBtn.disabled = true;
        submitText.classList.add('hidden');
        submitSpinner.classList.remove('hidden');
        actionError.classList.add('hidden');
        
        // Make AJAX request
        fetch('../actions/process_document_action_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                document_id: documentId,
                action: action,
                comments: comments
            })
        })
        .then(async response => {
            const contentType = response.headers.get('content-type');
            
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                const errorMatch = text.match(/<b>(.*?)<\/b>/i);
                const errorMsg = errorMatch ? errorMatch[1] : 'Server error occurred';
                throw new Error('Server returned non-JSON response: ' + errorMsg);
            }
            
            try {
                return await response.json();
            } catch (jsonError) {
                console.error('JSON parse error:', jsonError);
                throw new Error('Invalid JSON response from server.');
            }
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                // Show success message
                alert(data.message || 'Action completed successfully!');
                
                // Close modal
                closeActionModal();
                
                // Reload the page to reflect changes
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                // Show error
                actionError.classList.remove('hidden');
                actionError.textContent = data.message || 'An error occurred. Please try again.';
                submitBtn.disabled = false;
                submitText.classList.remove('hidden');
                submitSpinner.classList.add('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            actionError.classList.remove('hidden');
            actionError.textContent = error.message || 'Network error. Please check your connection and try again.';
            submitBtn.disabled = false;
            submitText.classList.remove('hidden');
            submitSpinner.classList.add('hidden');
        });
    }

    // Event listeners for action modal
    document.addEventListener('DOMContentLoaded', function() {
        const actionModal = document.getElementById('actionModal');
        const closeActionModalBtn = document.getElementById('closeActionModal');
        const cancelActionBtn = document.getElementById('cancelActionBtn');
        const submitActionBtn = document.getElementById('submitActionBtn');
        
        // Close modal handlers
        if (closeActionModalBtn) {
            closeActionModalBtn.addEventListener('click', closeActionModal);
        }
        if (cancelActionBtn) {
            cancelActionBtn.addEventListener('click', closeActionModal);
        }
        if (submitActionBtn) {
            submitActionBtn.addEventListener('click', submitDocumentAction);
        }
        
        // Close modal on outside click
        if (actionModal) {
            actionModal.addEventListener('click', function(e) {
                if (e.target === actionModal) {
                    closeActionModal();
                }
            });
        }
        
        // Submit on Enter key (Ctrl+Enter or Cmd+Enter)
        const commentsField = document.getElementById('actionComments');
        if (commentsField) {
            commentsField.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    submitDocumentAction();
                }
            });
        }
    });
</script>

<!-- Grammar Checker Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const documentFrame = document.getElementById('documentFrame');
        const documentContent = document.getElementById('documentContent');
        const checkGrammarBtn = document.getElementById('checkGrammarBtn');
        const toggleOriginalBtn = document.getElementById('toggleOriginalBtn');
        
        let originalIframeDisplay = 'block';
        let isGrammarCheckActive = false;
        
        // Initialize grammar checker
        if (documentContent || documentFrame) {
            // Initialize the grammar checker
            grammarChecker.init('documentPreviewContainer', documentContent ? '#documentContent' : '#extracted-content');
            
            // Add event listener to the grammar check button
            checkGrammarBtn.addEventListener('click', function() {
                isGrammarCheckActive = true;
                
                // If we have an iframe, show the toggle button
                if (documentFrame) {
                    originalIframeDisplay = documentFrame.style.display || 'block';
                    toggleOriginalBtn.classList.remove('hidden');
                }
                
                grammarChecker.checkGrammar();
            });
            
            // Add event listener to the toggle original button
            toggleOriginalBtn.addEventListener('click', function() {
                if (documentFrame.style.display === 'none') {
                    // Show original
                    documentFrame.style.display = originalIframeDisplay;
                    const extractedContent = document.getElementById('extracted-content');
                    if (extractedContent) {
                        extractedContent.style.display = 'none';
                    }
                    toggleOriginalBtn.innerHTML = '<i class="fas fa-spell-check mr-1"></i> Show Grammar Check';
                    
                    // Hide error summary if it exists
                    const errorSummary = document.getElementById('grammar-error-summary');
                    if (errorSummary) {
                        errorSummary.style.display = 'none';
                    }
                } else {
                    // Show grammar check
                    documentFrame.style.display = 'none';
                    const extractedContent = document.getElementById('extracted-content');
                    if (extractedContent) {
                        extractedContent.style.display = 'block';
                    }
                    toggleOriginalBtn.innerHTML = '<i class="fas fa-eye mr-1"></i> Show Original';
                    
                    // Show error summary if it exists
                    const errorSummary = document.getElementById('grammar-error-summary');
                    if (errorSummary) {
                        errorSummary.style.display = 'block';
                    }
                }
            });
            
            // Add event listeners to close the grammar modal
            document.getElementById('closeGrammarModal')?.addEventListener('click', function() {
                document.getElementById('grammarModal').classList.add('hidden');
            });
            
            document.getElementById('closeGrammarBtn')?.addEventListener('click', function() {
                document.getElementById('grammarModal').classList.add('hidden');
            });
            
            // Check if we should automatically run grammar check
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('check_grammar')) {
                // Run grammar check automatically after a short delay to ensure page is loaded
                setTimeout(() => {
                    isGrammarCheckActive = true;
                    
                    // If we have an iframe, show the toggle button
                    if (documentFrame) {
                        originalIframeDisplay = documentFrame.style.display || 'block';
                        toggleOriginalBtn.classList.remove('hidden');
                    }
                    
                    grammarChecker.checkGrammar();
                }, 1000);
            }
        } else {
            // Hide grammar check button if there's no content to check
            if (checkGrammarBtn) {
                checkGrammarBtn.style.display = 'none';
            }
        }
        
        // Initialize memorandum tracking if this is a memorandum
        const documentData = <?php echo json_encode($document); ?>;
        if (documentData && documentData.is_memorandum) {
            console.log('Initializing memorandum tracking for document:', documentData.document_id);
            if (window.memorandumTracker) {
                window.memorandumTracker.initTracking(documentData.document_id);
            }
        }
    });
</script>
