<?php
// Set this to true to indicate this file is being included in dashboard
define('INCLUDED_IN_DASHBOARD', true);

session_start();
require_once '../includes/config.php';
require_once '../includes/file_helpers.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'] ?? 0;

// Check if document ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: documents.php");
    exit();
}

$document_id = $_GET['id'];
$document = null;
$filePath = '';
$fileExtension = '';
$hasGoogleDoc = false;
$googleDocId = '';
$documentContent = '';
$can_approve = false;
$document_logs = [];
$workflow_path = [];

// Function to fix file paths for proper display
function fixFilePath($path) {
    // Replace backslashes with forward slashes for web URLs
    $path = str_replace("\\", "/", $path);
    
    // If the path is empty, return an empty string
    if (empty($path)) {
        return '';
    }
    
    // If the path already has a protocol, return it as is
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    
    // Ensure the path is properly formatted for web access
    if (strpos($path, 'storage/') === 0) {
        $path = '../' . $path;
    } else if (strpos($path, '../') !== 0 && strpos($path, '/') !== 0) {
        // Add ../ prefix if it doesn't already have it and isn't an absolute path
        $path = '../' . $path;
    }
    
    return $path;
}

// Function to get document content based on file extension
function getDocumentContent($filePath, $fileExtension) {
    if (empty($filePath)) {
        return "<p class='text-gray-500'>No file content available.</p>";
    }
    
    switch (strtolower($fileExtension)) {
        case 'txt':
            if (file_exists($filePath)) {
                $content = nl2br(htmlspecialchars(file_get_contents($filePath)));
                return "<div class='p-4 bg-white rounded-lg shadow text-gray-800 whitespace-pre-wrap'>$content</div>";
            }
            break;
        case 'html':
        case 'htm':
            if (file_exists($filePath)) {
                return "<iframe src='$filePath' width='100%' height='700px' class='border-0 rounded-lg shadow'></iframe>";
            }
            break;
        case 'pdf':
            return "<iframe src='$filePath' width='100%' height='700px' class='border-0 rounded-lg shadow'></iframe>";
        case 'docx':
        case 'doc':
            return "<div class='text-center p-6 bg-blue-50 rounded-lg shadow mb-4'>
                      <svg xmlns='http://www.w3.org/2000/svg' class='h-16 w-16 text-blue-600 mx-auto mb-4' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                          <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/>
                      </svg>
                      <h3 class='text-xl font-semibold mb-2'>Microsoft Word Document</h3>
                      <p class='text-gray-600 mb-4'>Preview not available for this file type.</p>
                      <a href='$filePath' download class='inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition ease-in-out duration-150'>
                          <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 mr-2' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                              <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'/>
                          </svg>
                          Download Document
                      </a>
                    </div>";
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return "<img src='$filePath' alt='Document Preview' class='max-w-full h-auto mx-auto rounded-lg shadow'>";
        default:
            return "<div class='text-center p-6 bg-gray-50 rounded-lg shadow mb-4'>
                      <svg xmlns='http://www.w3.org/2000/svg' class='h-16 w-16 text-gray-500 mx-auto mb-4' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                          <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h7.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/>
                      </svg>
                      <h3 class='text-xl font-semibold mb-2'>File Preview Unavailable</h3>
                      <p class='text-gray-600 mb-4'>This file type cannot be previewed directly in the browser.</p>
                      <a href='$filePath' download class='inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition ease-in-out duration-150'>
                          <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 mr-2' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                              <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'/>
                          </svg>
                          Download Document
                      </a>
                    </div>";
    }
    
    return "<p class='text-gray-500'>No file content available.</p>";
}

// Get document details from database
$query = "SELECT d.*, dt.type_name, u.full_name as creator_name, u.office_id as creator_office_id, 
           o.office_name as creator_office 
          FROM documents d
          LEFT JOIN document_types dt ON d.type_id = dt.type_id
          LEFT JOIN users u ON d.creator_id = u.user_id
          LEFT JOIN offices o ON u.office_id = o.office_id
          WHERE d.document_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $document = $result->fetch_assoc();
    
    // Get file path and prepare for display
    $filePath = $document['file_path'] ?? '';
    $filePath = fixFilePath($filePath);
    $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
    
    // Check if this is a Google Doc
    $hasGoogleDoc = !empty($document['google_doc_id']);
    $googleDocId = $document['google_doc_id'] ?? '';
    
    // Get document content
    if (!$hasGoogleDoc && !empty($filePath)) {
        $documentContent = getDocumentContent($filePath, $fileExtension);
    } else if ($hasGoogleDoc) {
        $documentContent = "<iframe src='https://docs.google.com/document/d/$googleDocId/preview' width='100%' height='700px' class='border-0 rounded-lg shadow'></iframe>";
    } else {
        $documentContent = "<p class='text-gray-500'>No document content available.</p>";
    }
    
    // Check if the current user can approve this document
    $can_approve = false;
    
    // Check if the document is assigned to the current office
    $check_query = "SELECT * FROM document_workflow 
                   WHERE document_id = ? AND office_id = ? AND status = 'current'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $document_id, $office_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result && $check_result->num_rows > 0) {
        $can_approve = true;
    }
    
    // Get document logs for history
    $logs_query = "SELECT dl.*, u.full_name as user_name
                  FROM document_logs dl
                  LEFT JOIN users u ON dl.user_id = u.user_id
                  WHERE dl.document_id = ?
                  ORDER BY dl.created_at DESC";
    $logs_stmt = $conn->prepare($logs_query);
    $logs_stmt->bind_param("i", $document_id);
    $logs_stmt->execute();
    $logs_result = $logs_stmt->get_result();
    
    if ($logs_result) {
        while ($log = $logs_result->fetch_assoc()) {
            $document_logs[] = $log;
        }
    }
    
    // Get workflow path if available
    $workflow_query = "SELECT dw.*, o.office_name 
                      FROM document_workflow dw
                      JOIN offices o ON dw.office_id = o.office_id
                      WHERE dw.document_id = ?
                      ORDER BY dw.step_order ASC";
    $workflow_stmt = $conn->prepare($workflow_query);
    $workflow_stmt->bind_param("i", $document_id);
    $workflow_stmt->execute();
    $workflow_result = $workflow_stmt->get_result();
    
    if ($workflow_result) {
        while ($step = $workflow_result->fetch_assoc()) {
            $workflow_path[] = $step;
        }
    }
} else {
    // Document not found
    header("Location: documents.php?error=document_not_found");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Plus+Jakarta+Sans%3Awght%40400%3B500%3B700%3B800" />
    <title><?php echo htmlspecialchars($document['title']); ?> - Document Viewer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: "Plus Jakarta Sans", "Noto Sans", sans-serif;
            background-color: #f9fafb;
        }
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
        .badge-hold {
            background-color: #E0E7FF;
            color: #4338CA;
        }
        .badge-revision {
            background-color: #F5D0FE;
            color: #9333EA;
        }
        .timeline-item:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 24px;
            left: 14px;
            width: 2px;
            height: calc(100% - 24px);
            background-color: #e5e7eb;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="p-4 md:p-6 max-w-6xl mx-auto">
        <!-- Document Header -->
        <div class="mb-6 flex flex-col md:flex-row justify-between md:items-center gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($document['title']); ?></h1>
                <div class="flex items-center text-sm text-gray-500 mb-4">
                    <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
                    <span class="mx-2">/</span>
                    <a href="documents.php" class="hover:text-gray-700">Documents</a>
                    <span class="mx-2">/</span>
                    <span>View Document</span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="documents.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Documents
                </a>
                <?php if (!empty($filePath)): ?>
                <a href="<?php echo $filePath; ?>" download class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-download mr-2"></i> Download
                </a>
                <?php endif; ?>
                <button id="analyzeBtn" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    <i class="fas fa-brain mr-2"></i> AI Analysis
                </button>
                <?php if ($can_approve): ?>
                <button id="approveBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-check mr-2"></i> Approve
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Document Info & Preview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Document Metadata -->
            <div class="md:col-span-1">
                <div class="bg-white rounded-lg shadow-sm p-5 mb-6">
                    <h2 class="text-lg font-semibold mb-4 pb-2 border-b">Document Details</h2>
                    
                    <!-- Status Badge -->
                    <div class="mb-4">
                        <p class="text-sm text-gray-500 mb-1">Status</p>
                        <?php
                        $status = $document['status'] ?? 'draft';
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
                            case 'hold':
                            case 'on_hold':
                                $status_class = 'badge-on_hold';
                                $status_text = 'On Hold';
                                break;
                            case 'draft':
                                $status_class = 'badge-pending';
                                $status_text = 'Draft';
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
                        <p class="font-medium"><?php echo htmlspecialchars($document['type_name'] ?? 'Unknown'); ?></p>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-sm text-gray-500 mb-1">Created By</p>
                        <p class="font-medium"><?php echo htmlspecialchars($document['creator_name'] ?? 'Unknown'); ?></p>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-sm text-gray-500 mb-1">From Office</p>
                        <p class="font-medium"><?php echo htmlspecialchars($document['creator_office'] ?? 'Unknown'); ?></p>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-sm text-gray-500 mb-1">Created On</p>
                        <p class="font-medium"><?php echo date('M j, Y', strtotime($document['created_at'])); ?></p>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-sm text-gray-500 mb-1">Last Updated</p>
                        <p class="font-medium"><?php echo date('M j, Y', strtotime($document['updated_at'])); ?></p>
                    </div>
                    
                    <?php if (!empty($workflow_path)): ?>
                    <div class="mb-0">
                        <p class="text-sm text-gray-500 mb-1">Workflow Progress</p>
                        <div class="flex items-center">
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <?php
                                $complete_steps = 0;
                                $total_steps = count($workflow_path);
                                
                                foreach ($workflow_path as $step) {
                                    if ($step['status'] === 'completed') {
                                        $complete_steps++;
                                    }
                                }
                                
                                $progress_percentage = $total_steps > 0 ? ($complete_steps / $total_steps) * 100 : 0;
                                ?>
                                <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $progress_percentage; ?>%"></div>
                            </div>
                            <span class="text-sm text-gray-500 ml-2"><?php echo $complete_steps; ?>/<?php echo $total_steps; ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Document History -->
                <?php if (!empty($document_logs)): ?>
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <h2 class="text-lg font-semibold mb-4 pb-2 border-b">Document History</h2>
                    <div class="space-y-4">
                        <?php foreach ($document_logs as $log): ?>
                        <div class="relative pl-8 timeline-item">
                            <div class="absolute left-0 top-0 bg-blue-100 rounded-full w-7 h-7 flex items-center justify-center">
                                <?php 
                                $icon = 'file';
                                switch($log['action']) {
                                    case 'create':
                                        $icon = 'plus';
                                        break;
                                    case 'update':
                                        $icon = 'pen';
                                        break;
                                    case 'approve':
                                        $icon = 'check';
                                        break;
                                    case 'reject':
                                        $icon = 'times';
                                        break;
                                    case 'hold':
                                        $icon = 'pause';
                                        break;
                                    case 'resume':
                                        $icon = 'play';
                                        break;
                                }
                                ?>
                                <i class="fas fa-<?php echo $icon; ?> text-blue-600 text-xs"></i>
                            </div>
                            <div>
                                <p class="font-medium">
                                    <?php echo ucfirst($log['action']); ?>
                                    <span class="font-normal text-gray-500">by <?php echo htmlspecialchars($log['user_name'] ?? 'Unknown'); ?></span>
                                </p>
                                <p class="text-sm text-gray-500"><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></p>
                                <?php if (!empty($log['comments'])): ?>
                                <p class="text-sm mt-1 text-gray-600 italic">"<?php echo htmlspecialchars($log['comments']); ?>"</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Document Preview -->
            <div class="md:col-span-2">
                <div class="bg-white rounded-lg shadow-sm p-5 mb-6">
                    <h2 class="text-lg font-semibold mb-4 pb-2 border-b">Document Preview</h2>
                    <div class="document-preview">
                        <?php echo $documentContent; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Approval Modal -->
    <?php if ($can_approve): ?>
    <div id="approvalModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Approve Document</h3>
                <button id="closeApprovalModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="approvalForm">
                <input type="hidden" id="documentId" value="<?php echo $document_id; ?>">
                <div class="mb-4">
                    <label for="approvalComments" class="block text-sm font-medium text-gray-700 mb-1">Comments (Optional)</label>
                    <textarea id="approvalComments" name="comments" class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" rows="3" placeholder="Add any comments about this approval"></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" id="cancelApproval" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Approve Document
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- AI Analysis Modal -->
    <div id="aiAnalysisModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] flex flex-col">
            <div class="p-4 border-b flex justify-between items-center bg-purple-50">
                <h3 class="text-xl font-semibold text-purple-800" id="aiAnalysisTitle">AI Document Analysis</h3>
                <button id="closeAiAnalysisModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto flex-grow">
                <div id="aiAnalysisLoading" class="flex flex-col items-center justify-center py-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500 mb-4"></div>
                    <p class="text-gray-600">AI is analyzing your document...</p>
                </div>
                <div id="aiAnalysisContent" class="hidden">
                    <!-- Classification Section -->
                    <div class="mb-6 p-4 bg-purple-50 border-l-4 border-purple-500 rounded">
                        <h4 class="font-semibold text-lg text-purple-800 mb-2">Document Classification</h4>
                        <div id="classificationResults" class="flex flex-wrap gap-2">
                            <!-- Categories will be added here -->
                        </div>
                    </div>
                    
                    <!-- Entities Section -->
                    <div class="mb-6">
                        <h4 class="font-semibold text-lg text-gray-800 mb-2">Extracted Entities</h4>
                        <div id="entitiesResults" class="space-y-2">
                            <!-- Entities will be added here -->
                        </div>
                    </div>
                    
                    <!-- Keywords Section -->
                    <div class="mb-6">
                        <h4 class="font-semibold text-lg text-gray-800 mb-2">Keywords</h4>
                        <div id="keywordsResults" class="flex flex-wrap gap-2">
                            <!-- Keywords will be added here -->
                        </div>
                    </div>
                    
                    <!-- Sentiment Section -->
                    <div class="mb-6 p-4 bg-gray-50 rounded">
                        <h4 class="font-semibold text-lg text-gray-800 mb-2">Sentiment Analysis</h4>
                        <div id="sentimentResults">
                            <!-- Sentiment will be added here -->
                        </div>
                    </div>
                    
                    <!-- Summary Section -->
                    <div class="mb-0">
                        <h4 class="font-semibold text-lg text-gray-800 mb-2">Document Summary</h4>
                        <div id="summaryResults" class="prose max-w-none">
                            <!-- Summary will be added here -->
                        </div>
                    </div>
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
                <button id="copyAiAnalysisBtn" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 flex items-center gap-2">
                    <i class="fas fa-copy"></i>
                    Copy Analysis
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // Approval Modal Handler
        <?php if ($can_approve): ?>
        document.getElementById('approveBtn').addEventListener('click', function() {
            document.getElementById('approvalModal').classList.remove('hidden');
        });
        
        document.getElementById('closeApprovalModal').addEventListener('click', function() {
            document.getElementById('approvalModal').classList.add('hidden');
        });
        
        document.getElementById('cancelApproval').addEventListener('click', function() {
            document.getElementById('approvalModal').classList.add('hidden');
        });
        
        document.getElementById('approvalForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const documentId = document.getElementById('documentId').value;
            const comments = document.getElementById('approvalComments').value;
            
            // Call the approve document API
            fetch('../actions/approve_document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: documentId,
                    comments: comments
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message and reload the page
                    alert('Document approved successfully!');
                    window.location.reload();
                } else {
                    // Show error message
                    alert('Error: ' + (data.error || 'Failed to approve document'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while approving the document');
            });
        });
        <?php endif; ?>
        
        // AI Analysis Modal Handler
        const analyzeBtn = document.getElementById('analyzeBtn');
        const aiAnalysisModal = document.getElementById('aiAnalysisModal');
        const aiAnalysisLoading = document.getElementById('aiAnalysisLoading');
        const aiAnalysisContent = document.getElementById('aiAnalysisContent');
        const aiAnalysisError = document.getElementById('aiAnalysisError');
        
        // Close button handlers
        document.getElementById('closeAiAnalysisModal').addEventListener('click', function() {
            aiAnalysisModal.classList.add('hidden');
        });
        
        document.getElementById('closeAiAnalysisBtn').addEventListener('click', function() {
            aiAnalysisModal.classList.add('hidden');
        });
        
        // Copy analysis handler
        document.getElementById('copyAiAnalysisBtn').addEventListener('click', function() {
            // Get content from all sections
            const classification = Array.from(document.getElementById('classificationResults').querySelectorAll('div'))
                .map(el => el.textContent.trim())
                .join(', ');
                
            const entities = Array.from(document.getElementById('entitiesResults').querySelectorAll('h5'))
                .map(el => {
                    const type = el.textContent;
                    const values = Array.from(el.nextElementSibling.querySelectorAll('div'))
                        .map(div => div.textContent.trim())
                        .join(', ');
                    return `${type}: ${values}`;
                })
                .join('\n');
                
            const keywords = Array.from(document.getElementById('keywordsResults').querySelectorAll('div'))
                .map(el => el.textContent.trim())
                .join(', ');
                
            const sentiment = document.getElementById('sentimentResults').textContent.trim();
            const summary = document.getElementById('summaryResults').textContent.trim();
            
            const fullAnalysis = `DOCUMENT ANALYSIS\n\nCLASSIFICATION:\n${classification}\n\nENTITIES:\n${entities}\n\nKEYWORDS:\n${keywords}\n\nSENTIMENT:\n${sentiment}\n\nSUMMARY:\n${summary}`;
            
            navigator.clipboard.writeText(fullAnalysis).then(function() {
                alert('Analysis copied to clipboard!');
            }, function(err) {
                console.error('Could not copy text: ', err);
                alert('Failed to copy to clipboard');
            });
        });
        
        // AI analysis handler
        analyzeBtn.addEventListener('click', function() {
            const documentId = '<?php echo $document_id; ?>';
            const documentTitle = '<?php echo addslashes($document['title']); ?>';
            
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
                    documentId: documentId,
                    fileName: documentTitle,
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
                    
                    // Display classification results
                    const classificationResults = document.getElementById('classificationResults');
                    classificationResults.innerHTML = '';
                    
                    if (data.classification && data.classification.length > 0) {
                        data.classification.forEach(category => {
                            const categoryBadge = document.createElement('div');
                            categoryBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800';
                            categoryBadge.innerHTML = `${category.name} <span class="text-xs text-purple-600">${category.confidence}%</span>`;
                            classificationResults.appendChild(categoryBadge);
                        });
                    } else {
                        classificationResults.innerHTML = '<p class="text-gray-500">No categories found</p>';
                    }
                    
                    // Display entities results
                    const entitiesResults = document.getElementById('entitiesResults');
                    entitiesResults.innerHTML = '';
                    
                    if (data.entities && data.entities.length > 0) {
                        // Group entities by type
                        const entityTypes = {};
                        data.entities.forEach(entity => {
                            if (!entityTypes[entity.type]) {
                                entityTypes[entity.type] = [];
                            }
                            entityTypes[entity.type].push(entity);
                        });
                        
                        // Create a section for each entity type
                        Object.keys(entityTypes).forEach(type => {
                            const typeSection = document.createElement('div');
                            typeSection.className = 'mb-3';
                            
                            const typeHeading = document.createElement('h5');
                            typeHeading.className = 'font-medium text-gray-700 mb-1';
                            typeHeading.textContent = type;
                            typeSection.appendChild(typeHeading);
                            
                            const entitiesList = document.createElement('div');
                            entitiesList.className = 'flex flex-wrap gap-2';
                            
                            entityTypes[type].forEach(entity => {
                                const entityBadge = document.createElement('div');
                                entityBadge.className = 'px-2 py-1 rounded text-sm bg-gray-100 text-gray-800';
                                entityBadge.textContent = entity.text;
                                entitiesList.appendChild(entityBadge);
                            });
                            
                            typeSection.appendChild(entitiesList);
                            entitiesResults.appendChild(typeSection);
                        });
                    } else {
                        entitiesResults.innerHTML = '<p class="text-gray-500">No entities found</p>';
                    }
                    
                    // Display keywords results
                    const keywordsResults = document.getElementById('keywordsResults');
                    keywordsResults.innerHTML = '';
                    
                    if (data.keywords && data.keywords.length > 0) {
                        data.keywords.forEach(keyword => {
                            const keywordBadge = document.createElement('div');
                            keywordBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800';
                            keywordBadge.textContent = keyword;
                            keywordsResults.appendChild(keywordBadge);
                        });
                    } else {
                        keywordsResults.innerHTML = '<p class="text-gray-500">No keywords found</p>';
                    }
                    
                    // Display sentiment results
                    const sentimentResults = document.getElementById('sentimentResults');
                    sentimentResults.innerHTML = '';
                    
                    if (data.sentiment) {
                        // Create sentiment score visualization
                        const sentimentScore = data.sentiment.overall || 0;
                        const sentimentLabel = data.sentiment.sentiment_label || 'neutral';
                        
                        // Determine color based on sentiment
                        let sentimentColor = 'bg-gray-500'; // neutral
                        if (sentimentLabel === 'positive') {
                            sentimentColor = 'bg-green-500';
                        } else if (sentimentLabel === 'negative') {
                            sentimentColor = 'bg-red-500';
                        }
                        
                        // Create sentiment meter
                        const sentimentMeter = document.createElement('div');
                        sentimentMeter.className = 'mb-4';
                        sentimentMeter.innerHTML = `
                            <div class="flex items-center mb-1">
                                <span class="text-sm font-medium text-gray-700">Sentiment Score: ${sentimentScore}</span>
                                <span class="ml-auto text-sm font-medium capitalize text-gray-700">${sentimentLabel}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="${sentimentColor} h-2.5 rounded-full" style="width: ${Math.round((sentimentScore + 1) / 2 * 100)}%"></div>
                            </div>
                        `;
                        sentimentResults.appendChild(sentimentMeter);
                        
                        // Display emotional tones if available
                        if (data.sentiment.tones && data.sentiment.tones.length > 0) {
                            const tonesSection = document.createElement('div');
                            tonesSection.className = 'mt-3';
                            
                            const tonesHeading = document.createElement('h5');
                            tonesHeading.className = 'font-medium text-gray-700 mb-1';
                            tonesHeading.textContent = 'Emotional Tones';
                            tonesSection.appendChild(tonesHeading);
                            
                            const tonesList = document.createElement('div');
                            tonesList.className = 'flex flex-wrap gap-2';
                            
                            data.sentiment.tones.forEach(tone => {
                                const toneBadge = document.createElement('div');
                                toneBadge.className = 'px-2 py-1 rounded text-sm bg-gray-100 text-gray-800';
                                toneBadge.innerHTML = `${tone.tone} <span class="text-xs text-gray-600">${Math.round(tone.intensity * 100)}%</span>`;
                                tonesList.appendChild(toneBadge);
                            });
                            
                            tonesSection.appendChild(tonesList);
                            sentimentResults.appendChild(tonesSection);
                        }
                    } else {
                        sentimentResults.innerHTML = '<p class="text-gray-500">No sentiment analysis available</p>';
                    }
                    
                    // Display summary results
                    const summaryResults = document.getElementById('summaryResults');
                    summaryResults.innerHTML = '';
                    
                    if (data.summary) {
                        summaryResults.innerHTML = data.summary;
                    } else {
                        // If no summary, fetch it from summarize_document.php
                        fetch('../actions/summarize_document.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                documentId: documentId,
                                fileName: documentTitle
                            })
                        })
                        .then(response => response.json())
                        .then(summaryData => {
                            if (summaryData.success && summaryData.summary) {
                                summaryResults.innerHTML = summaryData.summary;
                            } else {
                                summaryResults.innerHTML = '<p class="text-gray-500">No summary available</p>';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching summary:', error);
                            summaryResults.innerHTML = '<p class="text-gray-500">Unable to generate summary</p>';
                        });
                    }
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
    </script>
</body>
</html> 