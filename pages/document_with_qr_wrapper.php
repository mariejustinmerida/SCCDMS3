<?php
/**
 * Document with QR Code Wrapper
 * 
 * This page serves as a wrapper to include the document_with_qr.php file within the dashboard layout
 */

// This file is included by dashboard.php
if (!defined('INCLUDED_IN_DASHBOARD')) {
    header("Location: dashboard.php?page=documents");
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Include database connection
require_once '../includes/config.php';

// Get document ID and verification code from query parameters
$document_id = isset($_GET['doc']) ? intval($_GET['doc']) : 0;
$verification_code = isset($_GET['code']) ? trim($_GET['code']) : '';

// Validation
if (empty($document_id)) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>Document ID is required.</p>
          </div>';
    exit;
}

// Get document details from database
$document = null;
$error = '';

try {
    // Get document details - simplified query to avoid JOIN issues
    $stmt = $conn->prepare("SELECT * FROM documents WHERE document_id = ?");
    
    // Check if statement preparation was successful
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $document = $result->fetch_assoc();
        
        // If no verification code was provided, use the one from the document
        if (empty($verification_code) && !empty($document['verification_code'])) {
            $verification_code = $document['verification_code'];
        } 
        // If still no verification code, generate one and save it
        else if (empty($verification_code)) {
            $verification_code = mt_rand(100000, 999999);
            
            // Save the verification code to the database
            $update_stmt = $conn->prepare("UPDATE documents SET verification_code = ? WHERE document_id = ?");
            $update_stmt->bind_param("si", $verification_code, $document_id);
            $update_stmt->execute();
        }
    } else {
        $error = "Document not found";
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Create verification URL
$verification_url = "http://" . $_SERVER['HTTP_HOST'] . "/SCCDMS2/simple_verify.php?code=" . $verification_code;

// Get QR code image as base64
$qr_image_url = "../qr_display.php?url=" . urlencode($verification_url) . "&size=120";

// Document content
$doc_title = $document ? htmlspecialchars($document['title']) : 'Document';

// Get file path and prepare for display
function fixFilePath($path) {
    $path = str_replace("\\", "/", $path);
    if (empty($path)) return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) return $path;
    if (strpos($path, 'storage/') === 0) $path = '../' . $path;
    else if (strpos($path, '../') !== 0 && strpos($path, '/') !== 0) $path = '../' . $path;
    return $path;
}

// Get file path and content
$filePath = isset($document['file_path']) ? $document['file_path'] : '';
$filePath = fixFilePath($filePath);
$fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

// Check if this is a Google Doc
$hasGoogleDoc = !empty($document['google_doc_id']);
$googleDocId = $document['google_doc_id'] ?? '';

// Set document content based on available data
$doc_content = '';
$file_content = '';

// First try to get content from file if it exists
if (!$hasGoogleDoc && !empty($filePath) && file_exists($filePath) && $fileExtension == 'txt') {
    $file_content = file_get_contents($filePath);
    $doc_content = $file_content;
} 
// Then check if there's content in the database
else if ($document && isset($document['content']) && !empty($document['content'])) {
    $doc_content = $document['content'];
} 
// Fallback to description if available
else if ($document && isset($document['description']) && !empty($document['description'])) {
    $doc_content = $document['description'];
}

// We intentionally do not inline Google Doc text here; the preview iframe is used instead

// Format the date
$date = date('m/d/Y');
?>

<div class="bg-white rounded-lg shadow-md p-6 non-printable">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold"><?php echo $doc_title; ?></h1>
        <div class="flex space-x-2">
            <a href="dashboard.php?page=view_document&id=<?php echo $document_id; ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                <i class="fas fa-eye mr-2"></i> Standard View
            </a>
            <button id="printBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-print mr-2"></i> Print
            </button>
        </div>
    </div>

    <!-- Print-ready document in an iframe for isolation -->
    <div class="print-container">
        <iframe id="printFrame" class="w-full border-0 bg-white" style="height: 800px;" srcdoc='
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        line-height: 1.5;
                        margin: 0;
                        padding: 20px;
                        color: #333;
                    }
                    .document {
                        max-width: 800px;
                        margin: 0 auto;
                        position: relative;
                    }
                    .document-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: flex-start;
                        margin-bottom: 30px;
                    }
                    .document-title {
                        margin: 0 0 5px 0;
                        font-size: 24px;
                        font-weight: bold;
                    }
                    .document-id {
                        font-size: 14px;
                        color: #666;
                    }
                    .qr-code {
                        text-align: center;
                    }
                    .qr-code img {
                        width: 100px;
                        height: 100px;
                        border: 1px solid #ddd;
                    }
                    .verification-text {
                        font-size: 12px;
                        color: #666;
                        margin-top: 5px;
                    }
                    .verification-code {
                        font-family: monospace;
                        font-weight: bold;
                    }
                    .document-content {
                        margin-bottom: 40px;
                    }
                    .document-footer {
                        font-size: 12px;
                        color: #666;
                        text-align: center;
                        border-top: 1px solid #eee;
                        padding-top: 15px;
                        margin-top: 40px;
                    }
                    .letterhead {
                        text-align: center;
                        margin-bottom: 20px;
                    }
                    .letterhead h1 {
                        color: #1c5738;
                        margin-bottom: 5px;
                    }
                    .letterhead p {
                        margin: 2px 0;
                    }
                    .date {
                        margin-bottom: 20px;
                    }
                    @media print {
                        body {
                            padding: 0;
                            margin: 0;
                            color: #000;
                        }
                        .document {
                            max-width: 100%;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="document">
                    <div class="document-header">
                        <div>
                            <h1 class="document-title"><?php echo htmlspecialchars($doc_title); ?></h1>
                            <p class="document-id">Document ID: <?php echo $document_id; ?></p>
                        </div>
                        <div class="qr-code">
                            <img src="<?php echo $qr_image_url; ?>" alt="QR Code">
                            <div class="verification-text">
                                Verification Code:<br>
                                <span class="verification-code"><?php echo $verification_code; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="document-content">
                        <?php
                        // Get real document content based on file type
                        $display_content = '';
                        
                        // Try to get content from file if it exists
                        if (!$hasGoogleDoc && !empty($filePath) && file_exists($filePath) && strtolower($fileExtension) == 'txt') {
                            $display_content = file_get_contents($filePath);
                        }
                        // Otherwise get content from database
                        else if (!empty($doc_content)) {
                            $display_content = $doc_content;
                        }
                        
                        // If we have no content but have a description, use that
                        else if (!empty($document['description'])) {
                            $display_content = $document['description'];
                        }
                        
                        // Check if document is a specific type that should display template
                        $show_letter_template = false;
                        if (!empty($document['type_id'])) {
                            $type_query = "SELECT type_name FROM document_types WHERE type_id = ?";
                            $type_stmt = $conn->prepare($type_query);
                            if ($type_stmt) {
                                $type_stmt->bind_param("i", $document['type_id']);
                                $type_stmt->execute();
                                $type_result = $type_stmt->get_result();
                                if ($type_row = $type_result->fetch_assoc()) {
                                    // Check if this is a letter type document
                                    $letter_types = ['letter', 'memorandum', 'correspondence', 'application'];
                                    foreach ($letter_types as $letter_type) {
                                        if (stripos($type_row['type_name'], $letter_type) !== false) {
                                            $show_letter_template = true;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        
                        // For PDF/DOCX/Images, we show their existence but can't show content
                        $is_binary_file = false;
                        if (!empty($filePath) && file_exists($filePath)) {
                            $binary_extensions = ['pdf', 'docx', 'doc', 'jpg', 'jpeg', 'png', 'gif'];
                            if (in_array(strtolower($fileExtension), $binary_extensions)) {
                                $is_binary_file = true;
                            }
                        }
                        ?>
                        
                        <?php if ($hasGoogleDoc): ?>
                        <!-- Prefer the REAL Google Doc content, but keep the SCC header -->
                        <div class="letterhead">
                            <h1>SAINT COLUMBAN COLLEGE</h1>
                            <p>Pagadian City, Zamboanga del Sur</p>
                            <p>Tel. No. (062) 214-2174 | Email: scc@saintcolumban.edu.ph</p>
                            <p>A Catholic Educational Institution</p>
                        </div>
                        <div class="date">
                            <p><?php echo date("m/d/Y", strtotime($document["created_at"] ?? "now")); ?></p>
                        </div>
                        <div style="margin: 20px 0;">
                            <iframe src="https://docs.google.com/document/d/<?php echo htmlspecialchars($googleDocId); ?>/preview" width="100%" height="900" style="border:0;" allow="clipboard-write"></iframe>
                        </div>
                        
                        <?php elseif ($is_binary_file): ?>
                        <!-- Embed preview for supported binary types -->
                        <?php if (strtolower($fileExtension) == 'pdf'): ?>
                        <div style="margin: 20px 0;">
                            <iframe src="<?php echo htmlspecialchars($filePath); ?>" width="100%" height="900" style="border:0;" allow="clipboard-write"></iframe>
                        </div>
                        <?php elseif (in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif'])): ?>
                        <div style="text-align:center; margin: 20px 0;">
                            <img src="<?php echo htmlspecialchars($filePath); ?>" alt="Document Image" style="max-width:100%; height:auto; border:1px solid #eee; border-radius:6px;" />
                        </div>
                        <?php else: ?>
                        <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; max-width: 520px; margin: 0 auto; text-align:center;">
                            <p style="color: #555;">This document type cannot be previewed here.</p>
                            <p style="margin-top: 10px;">Please use the Standard View to open or download the file.</p>
                        </div>
                        <?php endif; ?>
                        
                        <?php elseif ($show_letter_template): ?>
                        <!-- Fallback letter template only when no external content is available -->
                        <div class="letterhead">
                            <h1>SAINT COLUMBAN COLLEGE</h1>
                            <p>Pagadian City, Zamboanga del Sur</p>
                            <p>Tel. No. (062) 214-2174 | Email: scc@saintcolumban.edu.ph</p>
                            <p>A Catholic Educational Institution</p>
                        </div>
                        <div class="date">
                            <p><?php echo date("m/d/Y", strtotime($document["created_at"] ?? "now")); ?></p>
                        </div>
                        <div class="content">
                            <div style="margin: 20px 0;">
                                <?php echo nl2br(htmlspecialchars($display_content)); ?>
                            </div>
                        </div>
                        
                        <?php else: ?>
                        <!-- Just display the content directly -->
                        <div style="margin: 20px 0;">
                            <?php 
                            if (!empty($display_content)) {
                                echo nl2br(htmlspecialchars($display_content));
                            } else {
                                echo "<p style=\"text-align: center; color: #666; margin: 40px 0;\">No content available for this document.</p>";
                            }
                            ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="document-footer">
                        <p>Document generated on: <?php echo date("F j, Y"); ?> | Verification Code: <?php echo $verification_code; ?></p>
                    </div>
                </div>
            </body>
            </html>
        '></iframe>
    </div>
</div>

<style>
/* Container styles */
.print-container {
    width: 100%;
    border: 1px solid #e2e8f0;
    background: white;
    border-radius: 0.5rem;
    overflow: hidden;
}

/* Print styles */
@media print {
    body * {
        display: none !important;
    }
    body iframe#printFrame {
        display: block !important;
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
    .non-printable {
        display: none !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle print button click
    var printBtn = document.getElementById('printBtn');
    
    if (printBtn) {
        printBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Print the iframe content only
            var iframe = document.getElementById('printFrame');
            if (iframe) {
                // Focus the iframe for printing
                iframe.contentWindow.focus();
                
                // Print after a delay to ensure content is loaded
                setTimeout(function() {
                    iframe.contentWindow.print();
                }, 500);
            }
        });
    }
    
    // Resize iframe to fit content
    var iframe = document.getElementById('printFrame');
    if (iframe) {
        iframe.onload = function() {
            try {
                var body = iframe.contentWindow.document.body;
                var html = iframe.contentWindow.document.documentElement;
                var height = Math.max(
                    body.scrollHeight, body.offsetHeight,
                    html.clientHeight, html.scrollHeight, html.offsetHeight
                );
                iframe.style.height = (height + 50) + 'px';
            } catch (e) {
                console.error('Error resizing iframe:', e);
            }
        };
    }
});
</script>
