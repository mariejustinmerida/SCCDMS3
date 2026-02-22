<?php
// This is a fix for the view_document.php file
// Run this script to fix the UI issues in the view_document.php file

require_once 'includes/config.php';

// Check if user is logged in and has admin privileges
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px;'>
            <h3>Access Denied</h3>
            <p>You must be logged in as an administrator to run this script.</p>
            <p><a href='auth/login.php'>Login</a> | <a href='pages/dashboard.php'>Dashboard</a></p>
          </div>";
    exit;
}

// Create a backup of the original file
$original_file = 'pages/view_document.php';
$backup_file = 'pages/view_document.php.bak';

if (!file_exists($backup_file)) {
    copy($original_file, $backup_file);
    echo "<p>Created backup of original file at $backup_file</p>";
}

// New content for the view_document.php file
$new_content = <<<'EOD'
<?php
// Check if this file is being accessed directly (not through dashboard.php)
$is_direct_access = !defined('INCLUDED_IN_DASHBOARD');

// If accessed directly, redirect to dashboard with the correct page parameter
if ($is_direct_access && isset($_GET['id'])) {
    $document_id = $_GET['id'];
    header("Location: dashboard.php?page=view_document&id=$document_id");
    exit();
}

// This file is included in dashboard.php, so we don't need to include session start, etc.
// However, we still need the database connection
require_once '../includes/config.php'; // Need this for database connection
require_once '../includes/file_helpers.php';

// Get user information from session (already started in dashboard.php)
$user_id = $_SESSION['user_id'] ?? 0;
$office_id = $_SESSION['office_id'] ?? 0;

// Ensure we have the database connection
global $conn;

// Function to fix file paths for proper display
function fixFilePath($path) {
    // Replace backslashes with forward slashes for web URLs
    $path = str_replace('\\', '/', $path);
    
    // If the path is empty, return an empty string
    if (empty($path)) {
        return '';
    }
    
    // If the path already has a protocol, return it as is
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    
    // If the path doesn't start with a directory indicator, assume it's in the storage directory
    if (!preg_match('/^(\/|\.\.|storage)/', $path)) {
        $path = 'storage/documents/' . $path;
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

// Function to extract content based on file type
function getDocumentContent($filePath) {
    $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
    $content = '';
    
    switch (strtolower($fileExtension)) {
        case 'txt':
            if (file_exists($filePath)) {
                $content = nl2br(htmlspecialchars(file_get_contents($filePath)));
            }
            break;
        case 'html':
        case 'htm':
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
            }
            break;
        case 'pdf':
            $content = "<iframe src='$filePath' width='100%' height='600px' style='border: none;'></iframe>";
            break;
        case 'doc':
        case 'docx':
            $content = "<p>Microsoft Word document preview is not available. <a href='$filePath' target='_blank'>Download</a> to view.</p>";
            break;
        case 'xls':
        case 'xlsx':
            $content = "<p>Microsoft Excel document preview is not available. <a href='$filePath' target='_blank'>Download</a> to view.</p>";
            break;
        case 'ppt':
        case 'pptx':
            $content = "<p>Microsoft PowerPoint document preview is not available. <a href='$filePath' target='_blank'>Download</a> to view.</p>";
            break;
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            $content = "<img src='$filePath' alt='Document Preview' class='max-w-full h-auto mx-auto'>";
            break;
        default:
            $content = "<p>Preview not available for this file type. <a href='$filePath' target='_blank'>Download</a> to view.</p>";
    }
    
    return $content;
}

if (!isset($_GET['file']) && !isset($_GET['id'])) {
    // Redirect within the dashboard framework
    echo '<script>window.location.href = "?page=documents";</script>';
    exit();
} elseif (isset($_GET['file'])) {
    // Direct file view (not implemented in this version)
    $filePath = $_GET['file'];
    $filePath = fixFilePath($filePath);
    $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
    $documentContent = getDocumentContent($filePath);
    $hasGoogleDoc = false;
    $googleDocId = '';
    $can_approve = false; // Cannot approve when viewing direct file
} elseif (isset($_GET['id'])) {
    $document_id = $_GET['id'];
    
    // Get document details from database
    $query = "SELECT d.*, dt.type_name, u.full_name as creator_name, o.office_name as creator_office 
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
        
        // Get file path
        $filePath = $document['file_path'] ?? '';
        $filePath = fixFilePath($filePath);
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        // Check if this is a Google Doc
        $hasGoogleDoc = !empty($document['google_doc_id']);
        $googleDocId = $document['google_doc_id'] ?? '';
        
        // Get document content if not a Google Doc
        if (!$hasGoogleDoc && !empty($filePath)) {
            $documentContent = getDocumentContent($filePath);
        } else {
            $documentContent = '';
        }
        
        // Check if the current user can approve this document
        $can_approve = false;
        $workflow = null;
        $has_next_step = false;
        $next_office_name = "<span class=\"text-gray-500\">Not in workflow</span>";
        $workflow_path = [];
        
        if ($conn) {
            // Check if the document is assigned to the current office
            $check_query = "SELECT * FROM document_workflow 
                           WHERE document_id = ? AND office_id = ? AND status = 'current'";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("ii", $document_id, $office_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result && $check_result->num_rows > 0) {
                $workflow = $check_result->fetch_assoc();
                $can_approve = true;
            }
        }
    } else {
        // Document not found
        echo "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4' role='alert'>
                <p>Document not found or you don't have permission to view it.</p>
              </div>";
        exit();
    }
} else {
    // No database connection
    $can_approve = false;
}

// Get next office in workflow if we have a workflow
if ($can_approve && $conn && $workflow) {
    // Get all workflow steps for this document type to determine the full path
    $workflow_steps_query = "SELECT ws.step_order, ws.office_id, o.office_name 
                           FROM workflow_steps ws
                           JOIN offices o ON ws.office_id = o.office_id 
                           WHERE ws.type_id = ? 
                           GROUP BY ws.office_id
                           ORDER BY ws.step_order ASC";
    
    // Check if document has a type_id before proceeding
    if (!isset($document['type_id']) || !isset($workflow['step_order'])) {
        $has_next_step = false;
        $next_office_name = "<span class=\"text-red-500\">Unknown</span>";
        $workflow_path = [];
    } else {
        $workflow_stmt = $conn->prepare($workflow_steps_query);
        
        if ($workflow_stmt) {
            $workflow_stmt->bind_param("i", $document['type_id']);
            $workflow_stmt->execute();
            $workflow_result = $workflow_stmt->get_result();
            
            // Get all workflow steps
            $workflow_path = [];
            $current_step_found = false;
            $next_step_index = -1;
            
            if ($workflow_result && $workflow_result->num_rows > 0) {
                while ($step = $workflow_result->fetch_assoc()) {
                    $workflow_path[] = $step;
                    
                    // Mark the current step
                    if ($step['step_order'] == $workflow['step_order']) {
                        $current_step_found = true;
                        $next_step_index = count($workflow_path);
                    }
                }
                
                // Determine next office
                if ($current_step_found && $next_step_index < count($workflow_path)) {
                    $has_next_step = true;
                    $next_office_name = $workflow_path[$next_step_index]['office_name'];
                } else {
                    $has_next_step = false;
                    $next_office_name = "<span class=\"text-green-600 font-medium\">Final Approval</span>";
                    
                    // If this is the only office in the workflow, show it as Final Approval
                    if (count($workflow_path) <= 1) {
                        $next_office_name = "<span class=\"text-green-600 font-medium\">Final Approval</span>";
                    }
                }
            } else {
                $has_next_step = false;
                $next_office_name = "<span class=\"text-yellow-600\">No workflow defined</span>";
            }
        } else {
            // Handle prepare error
            $has_next_step = false;
            $next_office_name = "<span class=\"text-red-500\">Error determining next office</span>";
            $workflow_path = [];
        }
    }
}
?>

<!-- Main Content Container -->
<div class="container mx-auto py-6">
  <!-- Document Preview Section -->
  <div class="mb-6">
    <?php if (!empty($filePath) && file_exists($filePath) || $hasGoogleDoc): ?>
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="border-b px-6 py-3 bg-[#163b20] flex justify-between items-center">
          <h2 class="text-lg font-semibold text-white flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Document Preview
          </h2>
          <?php if ($hasGoogleDoc): ?>
          <a href="https://docs.google.com/document/d/<?php echo $googleDocId; ?>/edit" target="_blank" class="bg-white text-[#163b20] hover:bg-gray-100 px-3 py-1 rounded-full text-sm font-medium flex items-center transition-colors duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
            Open in Google Docs
          </a>
          <?php endif; ?>
        </div>
        <div class="p-6">
          <?php if ($fileExtension === 'pdf'): ?>
            <div class="aspect-video">
              <iframe src="<?php echo $filePath; ?>" class="w-full h-full border-0" allowfullscreen></iframe>
            </div>
          <?php elseif (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
            <img src="<?php echo $filePath; ?>" alt="Document Preview" class="max-w-full h-auto mx-auto">
          <?php elseif ($hasGoogleDoc): ?>
            <div class="aspect-video">
              <iframe src="https://docs.google.com/document/d/<?php echo $googleDocId; ?>/preview" class="w-full h-full border-0" allowfullscreen></iframe>
            </div>
          <?php else: ?>
            <div class="p-6 text-center">
              <p class="text-gray-500 mb-4">Preview not available for this file type. <a href="<?php echo $filePath; ?>" target="_blank" class="text-blue-600 hover:underline">Download the file</a> to view it.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="bg-white rounded-lg shadow p-8 text-center">
        <div class="bg-gray-100 p-5 rounded-full mb-4 inline-block">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Document Preview Available</h3>
        <p class="text-gray-500 max-w-md mx-auto">This document does not have any content to preview. It may be a physical document or the content has not been uploaded yet.</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Document Details and Actions -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Document Details -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <div class="border-b px-6 py-3 bg-[#163b20]">
        <h2 class="text-lg font-semibold text-white flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Document Details
        </h2>
      </div>
      <div class="p-6">
        <table class="w-full border-collapse table-fixed">
          <tr class="border-b">
            <td class="py-3 text-gray-600 font-medium w-1/3">Document ID:</td>
            <td class="py-3 font-medium break-words">DOC-<?php echo str_pad($document_id, 3, '0', STR_PAD_LEFT); ?></td>
          </tr>
          <tr class="border-b">
            <td class="py-3 text-gray-600 font-medium">Type:</td>
            <td class="py-3 break-words"><?php echo htmlspecialchars($document['type_name'] ?? 'Not specified'); ?></td>
          </tr>
          <tr class="border-b">
            <td class="py-3 text-gray-600 font-medium">Created By:</td>
            <td class="py-3 break-words"><?php echo htmlspecialchars($document['creator_name'] ?? 'Unknown'); ?></td>
          </tr>
          <tr class="border-b">
            <td class="py-3 text-gray-600 font-medium">Created On:</td>
            <td class="py-3 break-words"><?php echo isset($document['created_at']) ? date('M j, Y', strtotime($document['created_at'])) : 'Unknown'; ?></td>
          </tr>
          <tr class="border-b">
            <td class="py-3 text-gray-600 font-medium">Status:</td>
            <td class="py-3">
              <span class="px-3 py-1 rounded-full text-sm font-medium inline-block <?php 
                echo match($document['status'] ?? '') {
                    'approved' => 'bg-green-100 text-green-800',
                    'rejected' => 'bg-red-100 text-red-800',
                    'on_hold' => 'bg-yellow-100 text-yellow-800',
                    default => 'bg-blue-100 text-blue-800'
                }; 
              ?>">
                <?php echo ucfirst($document['status'] ?? 'Pending'); ?>
              </span>
            </td>
          </tr>
          <tr class="border-b">
            <td class="py-3 text-gray-600 font-medium">Next Office:</td>
            <td class="py-3 break-words"><?php echo $next_office_name; ?></td>
          </tr>
          
          <?php if (isset($workflow_path) && !empty($workflow_path)): ?>
          <tr class="border-b">
            <td class="py-3 text-gray-600 font-medium align-top">Workflow Path:</td>
            <td class="py-3">
              <div class="flex flex-wrap items-center">
                <?php foreach ($workflow_path as $index => $step): ?>
                  <?php 
                    $is_current = isset($workflow['step_order']) && $step['step_order'] == $workflow['step_order'];
                    $is_completed = isset($workflow['step_order']) && $step['step_order'] < $workflow['step_order'];
                    $is_next = isset($workflow['step_order']) && $step['step_order'] > $workflow['step_order'] && $step['step_order'] == $workflow['step_order'] + 1;
                    
                    $step_class = $is_current ? 'bg-blue-100 text-blue-800 border-blue-300' : 
                                 ($is_completed ? 'bg-green-100 text-green-800 border-green-300' : 
                                 ($is_next ? 'bg-yellow-100 text-yellow-800 border-yellow-300' : 'bg-gray-100 text-gray-800 border-gray-300'));
                  ?>
                  <span class="px-2 py-1 rounded-full text-xs font-medium border inline-block <?php echo $step_class; ?> mr-1 mb-1">
                    <?php echo htmlspecialchars($step['office_name']); ?>
                  </span>
                  <?php if ($index < count($workflow_path) - 1): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-400 mr-1 mb-1 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            </td>
          </tr>
          <?php endif; ?>
        </table>
      </div>
    </div>
    
    <!-- Document Actions -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <div class="border-b px-6 py-3 bg-[#163b20]">
        <h2 class="text-lg font-semibold text-white flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          Document Actions
        </h2>
      </div>
      <div class="p-6">
        <?php if ($can_approve): ?>
        <div class="grid grid-cols-3 gap-3">
          <a href="?page=approve_document&id=<?php echo $document_id; ?>&action=approve" 
             class="bg-[#1e8449] text-white px-4 py-3 rounded-lg hover:bg-[#196f3d] flex items-center justify-center font-medium transition-colors duration-200 shadow-sm"
             onclick="return confirm('Are you sure you want to approve this document?');">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Approve
          </a>
          
          <a href="?page=approve_document&id=<?php echo $document_id; ?>&action=reject" 
             class="bg-[#c0392b] text-white px-4 py-3 rounded-lg hover:bg-[#a93226] flex items-center justify-center font-medium transition-colors duration-200 shadow-sm"
             onclick="return confirm('Are you sure you want to reject this document?');">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Reject
          </a>
          
          <a href="?page=approve_document&id=<?php echo $document_id; ?>&action=hold" 
             class="bg-[#f39c12] text-white px-4 py-3 rounded-lg hover:bg-[#d68910] flex items-center justify-center font-medium transition-colors duration-200 shadow-sm"
             onclick="return confirm('Are you sure you want to put this document on hold?');">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Hold
          </a>
        </div>
        <?php else: ?>
        <div class="p-6 text-center">
          <div class="bg-gray-100 rounded-full p-4 inline-block mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-700 mb-2">No Actions Available</h3>
          <p class="text-gray-500">You don't have permission to perform actions on this document or it has already been fully processed.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
EOD;

// Write the new content to the file
file_put_contents($original_file, $new_content);

echo "<div style='background-color: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 4px; margin: 20px;'>
        <h3>Success</h3>
        <p>The view_document.php file has been fixed successfully.</p>
        <p><a href='pages/dashboard.php?page=incoming'>Return to Dashboard</a></p>
      </div>";
?>
