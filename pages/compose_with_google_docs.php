<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../includes/config.php';
require_once '../includes/google_auth_handler.php';
require_once '../includes/google_docs_handler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Check if the user is connected to Google Docs
$authHandler = new GoogleAuthHandler();
$isConnected = $authHandler->hasValidToken($userId);

// If not connected, redirect to the connection page
if (!$isConnected) {
    $_SESSION['google_auth_error'] = 'You need to connect to Google Docs before creating a document';
    header('Location: google_docs_connect.php');
    exit();
}

// Initialize Google Docs handler
$client = $authHandler->getClient();
$token = $authHandler->loadToken($userId);
$client->setAccessToken($token);
$docsHandler = new GoogleDocsHandler($client);

// Handle form submission
$googleDocId = '';
$googleDocUrl = '';
$googleDocEmbedUrl = '';
$documentId = 0;
$documentCreated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_document') {
    $title = trim($_POST['title'] ?? 'Untitled Document');
    $type_id = (int)($_POST['type_id'] ?? 0);
    
    // Create a Google Doc
    $docInfo = $docsHandler->createDocument($title);
    
    if ($docInfo) {
        $googleDocId = $docInfo['id'];
        $googleDocUrl = $docInfo['edit_url'];
        $googleDocEmbedUrl = $docsHandler->getEmbedUrl($googleDocId);
        
        // Save document to SCCDMS database
        $title = $conn->real_escape_string($title);
        $creator_id = (int)$_SESSION['user_id'];
        $office_id = (int)$_SESSION['office_id'];
        
        // Check the structure of the documents table to determine the correct column names
        $check_columns = $conn->query("SHOW COLUMNS FROM documents");
        $columns = [];
        while ($col = $check_columns->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        
        // Determine if we have a 'content' or 'document_content' column
        $content_column = in_array('content', $columns) ? 'content' : 
                         (in_array('document_content', $columns) ? 'document_content' : '');
        
        if (empty($content_column)) {
            $_SESSION['error_message'] = "Error: Could not determine content column in documents table";
            // Continue anyway with our best guess
            $content_column = 'content';
        }
        
        // Build the SQL query dynamically based on available columns
        $sql = "INSERT INTO documents (title, $content_column, type_id, creator_id, office_id, status, created_at, updated_at) 
                VALUES ('$title', 'This document is edited in Google Docs (ID: $googleDocId)', $type_id, $creator_id, $office_id, 'pending', NOW(), NOW())";
        
        if ($conn->query($sql)) {
            $documentId = $conn->insert_id;
            $documentCreated = true;
            
            // Map the SCCDMS document to the Google Doc
            $docsHandler->mapDocumentToGoogleDoc($documentId, $googleDocId, $userId);
            
            // Handle workflow if provided
            if (isset($_POST['workflow_offices']) && is_array($_POST['workflow_offices'])) {
                $workflowOffices = $_POST['workflow_offices'];
                $workflowRoles = $_POST['workflow_roles'] ?? [];
                
                // Check if workflow_steps table exists
                $check_workflow_sql = "SHOW TABLES LIKE 'workflow_steps'";
                $check_workflow_result = $conn->query($check_workflow_sql);
                
                if ($check_workflow_result && $check_workflow_result->num_rows > 0) {
                    // Insert workflow steps
                    foreach ($workflowOffices as $index => $officeId) {
                        if (empty($officeId)) continue;
                        
                        $officeId = (int)$officeId;
                        $roleId = isset($workflowRoles[$index]) ? (int)$workflowRoles[$index] : 0;
                        $stepOrder = $index + 1;
                        
                        $workflow_sql = "INSERT INTO workflow_steps (document_id, office_id, role_id, step_order, status, created_at) 
                                        VALUES ($documentId, $officeId, $roleId, $stepOrder, 'pending', NOW())";
                        $conn->query($workflow_sql);
                    }
                }
            }
            
            // Set success message
            $_SESSION['success_message'] = "Document created successfully. You can now edit it in Google Docs.";
        } else {
            // Set error message
            $_SESSION['error_message'] = "Error creating document: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = "Error creating Google Doc";
    }
}

// Fetch all document types for dropdown
$types_sql = "SELECT * FROM document_types ORDER BY type_name ASC";
$types_result = $conn->query($types_sql);
$document_types = [];
if ($types_result) {
    while ($type = $types_result->fetch_assoc()) {
        $document_types[] = $type;
    }
}

// Fetch all offices for workflow
$offices_sql = "SELECT * FROM offices ORDER BY office_name ASC";
$offices_result = $conn->query($offices_sql);
$offices = [];
if ($offices_result) {
    while ($office = $offices_result->fetch_assoc()) {
        $offices[] = $office;
    }
}

// Fetch roles by office
$roles_sql = "SELECT r.*, o.office_name 
              FROM roles r 
              JOIN offices o ON r.office_id = o.office_id 
              ORDER BY o.office_name, r.role_name";
$roles_result = $conn->query($roles_sql);
$roles_by_office = [];

if ($roles_result) {
    while ($role = $roles_result->fetch_assoc()) {
        if (!isset($roles_by_office[$role['office_id']])) {
            $roles_by_office[$role['office_id']] = [];
        }
        $roles_by_office[$role['office_id']][] = $role;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Compose Document - SCC DMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Hide filtered-out options */
    select option[style*="display: none"] {
      display: none !important;
    }
    
    /* Highlight matching text in dropdowns */
    .highlight-match {
      background-color: #FFFF00;
      font-weight: bold;
    }
    
    /* Google Docs iframe */
    .google-docs-container {
      width: 100%;
      height: 700px;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      overflow: hidden;
      margin-bottom: 20px;
    }
    
    .google-docs-iframe {
      width: 100%;
      height: 100%;
      border: none;
    }
  </style>
</head>
<body class="min-h-screen bg-gray-50">
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold">Create New Document</h1>
      <div class="flex items-center text-sm text-gray-500">
        <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
        <span class="mx-2">/</span>
        <span>Compose with Google Docs</span>
      </div>
    </div>
    
    <?php if (isset($_SESSION['error_message'])): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
      </div>
      <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_message'])): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline"><?php echo $_SESSION['success_message']; ?></span>
      </div>
      <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if ($documentCreated && !empty($googleDocId)): ?>
      <!-- Document created, show Google Docs editor -->
      <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-xl font-semibold mb-4">Edit Document in Google Docs</h2>
        
        <p class="mb-4 text-gray-600">
          Your document has been created. You can edit it directly in Google Docs below.
          All changes will be automatically saved to your Google Drive.
        </p>
        
        <div class="google-docs-container mb-4">
          <iframe src="https://docs.google.com/document/d/<?php echo $googleDocId; ?>/edit?embedded=true" 
                  class="google-docs-iframe" 
                  allow="autoplay" 
                  allowfullscreen="true"></iframe>
        </div>
        
        <div class="flex justify-between items-center">
          <a href="dashboard.php?page=documents" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
            Back to Documents
          </a>
          
          <a href="<?php echo $googleDocUrl; ?>" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
            Open in Google Docs
          </a>
        </div>
      </div>
    <?php else: ?>
      <!-- Show document creation form -->
      <div class="bg-white rounded-lg shadow-sm">
        <form id="documentForm" method="POST" class="space-y-6">
          <input type="hidden" name="action" value="create_document">
          
          <div class="p-6 space-y-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Document Title</label>
              <input type="text" name="title" id="docTitle" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
              <select name="type_id" id="docType" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="">Select Document Type</option>
                <?php foreach ($document_types as $type): ?>
                  <option value="<?php echo $type['type_id']; ?>">
                    <?php echo htmlspecialchars($type['type_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div class="border rounded-lg p-4">
              <h3 class="font-medium mb-4">Routing Workflow</h3>
              <div class="mb-4">
                <input type="text" id="officeSearch" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Search for an office...">
              </div>
              
              <div id="workflowBuilder" class="space-y-3">
                <div class="workflow-step flex items-center space-x-2">
                  <div class="flex-none">
                    <span class="bg-gray-200 text-gray-700 px-2 py-1 rounded-full text-sm">Step 1</span>
                  </div>
                  
                  <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div class="office-select-container">
                      <select name="workflow_offices[]" class="office-select w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">Select Office</option>
                        <?php foreach ($offices as $office): ?>
                          <option value="<?php echo $office['office_id']; ?>" data-search="<?php echo strtolower(htmlspecialchars($office['office_name'])); ?>">
                            <?php echo htmlspecialchars($office['office_name']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    
                    <div class="role-select-container">
                      <select name="workflow_roles[]" class="role-select w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">Any Role</option>
                      </select>
                    </div>
                  </div>
                  
                  <div class="flex-none">
                    <button type="button" class="remove-step bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
              
              <div class="mt-3">
                <button type="button" id="addWorkflowStep" class="flex items-center text-green-600 hover:text-green-700">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                  </svg>
                  Add Workflow Step
                </button>
              </div>
            </div>
          </div>
          
          <div class="p-4 bg-gray-50 rounded-b-lg flex justify-end gap-3">
            <a href="dashboard.php?page=documents" class="px-4 py-2 text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
              Cancel
            </a>
            
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
              Create Document
            </button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </div>
  
  <script>
    // Office search functionality
    document.addEventListener('DOMContentLoaded', function() {
      const officeSearch = document.getElementById('officeSearch');
      const officeSelects = document.querySelectorAll('.office-select');
      
      if (officeSearch) {
        officeSearch.addEventListener('input', function() {
          const searchTerm = this.value.toLowerCase();
          
          officeSelects.forEach(select => {
            const options = select.querySelectorAll('option');
            
            options.forEach(option => {
              if (option.value === '') return; // Skip placeholder option
              
              const searchText = option.getAttribute('data-search');
              if (searchText && searchText.includes(searchTerm)) {
                option.style.display = '';
                
                // Highlight matching text
                const displayText = option.textContent;
                const matchIndex = displayText.toLowerCase().indexOf(searchTerm);
                
                if (matchIndex >= 0 && searchTerm.length > 0) {
                  const before = displayText.substring(0, matchIndex);
                  const match = displayText.substring(matchIndex, matchIndex + searchTerm.length);
                  const after = displayText.substring(matchIndex + searchTerm.length);
                  
                  option.innerHTML = before + '<span class="highlight-match">' + match + '</span>' + after;
                } else {
                  option.textContent = displayText;
                }
              } else {
                option.style.display = 'none';
              }
            });
          });
        });
      }
    });
    
    // Roles by office data
    const rolesByOffice = <?php echo json_encode($roles_by_office); ?>;
    
    // Update roles dropdown when office is selected
    function updateRolesDropdown(officeSelect) {
      const officeId = officeSelect.value;
      const workflowStep = officeSelect.closest('.workflow-step');
      const roleSelect = workflowStep.querySelector('.role-select');
      
      // Clear current options
      roleSelect.innerHTML = '<option value="">Any Role</option>';
      
      // Add roles for selected office
      if (officeId && rolesByOffice[officeId]) {
        rolesByOffice[officeId].forEach(role => {
          const option = document.createElement('option');
          option.value = role.role_id;
          option.textContent = role.role_name;
          roleSelect.appendChild(option);
        });
      }
    }
    
    // Add event listeners to all office selects
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.office-select').forEach(select => {
        select.addEventListener('change', function() {
          updateRolesDropdown(this);
        });
      });
      
      // Add workflow step button
      const addWorkflowStepBtn = document.getElementById('addWorkflowStep');
      if (addWorkflowStepBtn) {
        addWorkflowStepBtn.addEventListener('click', addWorkflowStep);
      }
      
      // Remove workflow step buttons
      document.querySelectorAll('.remove-step').forEach(button => {
        button.addEventListener('click', removeWorkflowStep);
      });
    });
    
    // Add a new workflow step
    function addWorkflowStep() {
      const workflowBuilder = document.getElementById('workflowBuilder');
      const stepCount = workflowBuilder.querySelectorAll('.workflow-step').length + 1;
      
      const stepHtml = `
        <div class="workflow-step flex items-center space-x-2">
          <div class="flex-none">
            <span class="bg-gray-200 text-gray-700 px-2 py-1 rounded-full text-sm">Step ${stepCount}</span>
          </div>
          
          <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-2">
            <div class="office-select-container">
              <select name="workflow_offices[]" class="office-select w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="">Select Office</option>
                ${Array.from(document.querySelectorAll('.office-select option')).map(opt => {
                  if (opt.value === '') return '<option value="">Select Office</option>';
                  return `<option value="${opt.value}" data-search="${opt.getAttribute('data-search')}">${opt.textContent}</option>`;
                }).join('')}
              </select>
            </div>
            
            <div class="role-select-container">
              <select name="workflow_roles[]" class="role-select w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="">Any Role</option>
              </select>
            </div>
          </div>
          
          <div class="flex-none">
            <button type="button" class="remove-step bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      `;
      
      // Add the new step
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = stepHtml.trim();
      const newStep = tempDiv.firstChild;
      workflowBuilder.appendChild(newStep);
      
      // Add event listeners to the new step
      const officeSelect = newStep.querySelector('.office-select');
      officeSelect.addEventListener('change', function() {
        updateRolesDropdown(this);
      });
      
      const removeBtn = newStep.querySelector('.remove-step');
      removeBtn.addEventListener('click', removeWorkflowStep);
      
      // Update step numbers
      updateStepNumbers();
    }
    
    // Remove a workflow step
    function removeWorkflowStep() {
      const workflowBuilder = document.getElementById('workflowBuilder');
      const steps = workflowBuilder.querySelectorAll('.workflow-step');
      
      // Don't remove if it's the only step
      if (steps.length <= 1) return;
      
      // Remove the step
      const step = this.closest('.workflow-step');
      step.remove();
      
      // Update step numbers
      updateStepNumbers();
    }
    
    // Update step numbers
    function updateStepNumbers() {
      const steps = document.querySelectorAll('.workflow-step');
      steps.forEach((step, index) => {
        const stepNumber = index + 1;
        const stepLabel = step.querySelector('.bg-gray-200');
        stepLabel.textContent = `Step ${stepNumber}`;
      });
    }
  </script>
</body>
</html>
