<!DOCTYPE html>
<html>
<head>
  <title>Compose Document - SCC DMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Google API libraries -->
  <script src="https://apis.google.com/js/api.js"></script>
  <script src="https://accounts.google.com/gsi/client"></script>
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
      height: 800px;
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
    
    /* Right-side notification styles */
    .notification-container {
      position: fixed;
      top: 80px;
      right: 20px;
      z-index: 1000;
      width: 300px;
      max-width: 90%;
    }
    
    .notification {
      margin-bottom: 10px;
      padding: 15px;
      border-radius: 5px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      animation: slideIn 0.3s ease-out forwards;
      opacity: 0;
      transform: translateX(50px);
    }
    
    .notification-success {
      background-color: #d1fae5;
      border-left: 4px solid #10b981;
      color: #065f46;
    }
    
    .notification-error {
      background-color: #fee2e2;
      border-left: 4px solid #ef4444;
      color: #b91c1c;
    }
    
    .notification-warning {
      background-color: #fef3c7;
      border-left: 4px solid #f59e0b;
      color: #92400e;
    }
    
    .notification-info {
      background-color: #e0f2fe;
      border-left: 4px solid #0ea5e9;
      color: #0369a1;
    }
    
    @keyframes slideIn {
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
    
    @keyframes fadeOut {
      to {
        opacity: 0;
        transform: translateX(50px);
      }
    }
    
    .notification-close {
      float: right;
      cursor: pointer;
      font-weight: bold;
      font-size: 18px;
      line-height: 1;
    }
  </style>
</head>
<body class="min-h-screen bg-gray-50">
  <!-- Notification container -->
  <div id="notificationContainer" class="notification-container"></div>
  
  <?php
  /**
   * Compose Page
   * 
   * This page allows users to create new documents.
   */

  if (session_status() === PHP_SESSION_NONE) {
      session_start();
  }

  require_once '../includes/config.php';
  require_once '../includes/google_auth_handler.php';

  // Check if user is logged in
  if (!isset($_SESSION['user_id'])) {
      header("Location: login.php");
      exit();
  }
  
  // Check for success message
  $showSuccessModal = false;
  if (isset($_SESSION['document_success']) && isset($_SESSION['show_new_document_dialog'])) {
      $showSuccessModal = true;
      $successMessage = $_SESSION['document_success'];
      unset($_SESSION['document_success']);
      unset($_SESSION['show_new_document_dialog']);
  }

  $userId = $_SESSION['user_id'];

  // Handle reconnect request - force a new authentication
  if (isset($_GET['reconnect']) && $_GET['reconnect'] == 1) {
      // Store return URL
      $_SESSION['google_auth_return_url'] = $_SERVER['PHP_SELF'];
      
      // Generate auth URL and redirect
      $authHandler = new GoogleAuthHandler();
      $client = $authHandler->getClient();
      $authUrl = $client->createAuthUrl();
      header('Location: ' . $authUrl);
      exit();
  }

  // Check if user is connected to Google Docs
  $authHandler = new GoogleAuthHandler();
  $isConnectedToGoogle = $authHandler->hasValidToken($userId);

  // Check for error message in session or URL
  $errorMessage = '';
  if (isset($_SESSION['google_auth_error'])) {
      $errorMessage = $_SESSION['google_auth_error'];
      unset($_SESSION['google_auth_error']);
  } elseif (isset($_GET['error'])) {
      $errorMessage = urldecode($_GET['error']);
  }

  // Check for success message
  $successMessage = '';
  if (isset($_SESSION['google_auth_success'])) {
      $successMessage = $_SESSION['google_auth_success'];
      unset($_SESSION['google_auth_success']);
  } elseif (isset($_GET['success']) && $_GET['success'] == 1) {
      $successMessage = 'Successfully connected to Google Docs';
  }

  // Get document types
  $document_types = [];
  $types_query = $conn->query("SELECT * FROM document_types ORDER BY type_name");
  if ($types_query) {
      while ($type = $types_query->fetch_assoc()) {
          $document_types[] = $type;
      }
  }
  ?>
  <div class="p-6">
    <div id="alertContainer">
      <?php if (!empty($errorMessage)): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline"><?php echo $errorMessage; ?></span>
      </div>
      <?php endif; ?>
      
      <?php if (!empty($successMessage)): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline"><?php echo $successMessage; ?></span>
      </div>
      <?php endif; ?>
    </div>
    
    <div class="mb-6">
      <h1 class="text-2xl font-bold">Create New Document</h1>
      <div class="flex items-center text-sm text-gray-500">
        <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
        <span class="mx-2">/</span>
        <span>Compose</span>
      </div>
    </div>
    
    <!-- Google Docs Connection Status -->
    <?php if (!$isConnectedToGoogle): ?>
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
      <div class="flex">
        <div class="py-1">
          <svg class="fill-current h-6 w-6 text-yellow-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
            <path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/>
          </svg>
        </div>
        <div>
          <p class="font-bold">Not Connected to Google Docs</p>
          <p class="text-sm">You need to connect to Google Docs to create and edit documents.</p>
          <div class="mt-3">
            <a href="google_docs_connect.php?connect=1&return=compose.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 inline-block">Connect to Google Docs</a>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- AI Document Generator Section -->
    <div class="bg-white rounded-lg shadow-sm mb-6">
      <div class="p-4 border-b flex justify-between items-center">
        <h2 class="text-xl font-semibold">AI Document Generator</h2>
        <button id="toggleAiGenerator" class="text-gray-500 hover:text-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
      </div>
      <div id="aiGeneratorSection" class="p-6 hidden">
        <p class="text-gray-600 mb-4">Describe the document you need, and our advanced AI will generate it for you. Be specific about the type, content, dates, and other details.</p>
        
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm text-blue-700">
                <strong>Examples:</strong><br>
                "Write an internal memo to all faculty members about random inspection of laboratory facilities next week."<br>
                "Create a leave request letter for December 15-20, 2023 due to a family emergency."<br>
                "Generate a memo from the Office of Academic Affairs about the upcoming faculty evaluation."
              </p>
            </div>
          </div>
        </div>
        
        <div class="flex flex-col space-y-4">
          <div class="flex flex-col gap-3">
            <div class="flex items-center gap-4">
              <div class="w-full">
                <label for="aiPrompt" class="block text-sm font-medium text-gray-700 mb-1">Your Document Request:</label>
                <textarea id="aiPrompt" rows="3" class="w-full px-4 py-2 border rounded-lg" placeholder="Describe the document you need in detail..."></textarea>
              </div>
            </div>
            
            <div class="flex items-center gap-4">
              <div class="w-1/2">
                <label for="docTypeSelect" class="block text-sm font-medium text-gray-700 mb-1">Document Type (Optional):</label>
                <select id="docTypeSelect" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                  <option value="">Auto-detect from your request</option>
                  <option value="memo">Memorandum</option>
                  <option value="letter">Formal Letter</option>
                  <option value="leave">Leave Request</option>
                  <option value="report">Report</option>
                  <option value="announcement">Announcement</option>
                </select>
              </div>
              
              <div class="w-1/2">
                <label for="senderInfo" class="block text-sm font-medium text-gray-700 mb-1">From/Sender (Optional):</label>
                <input type="text" id="senderInfo" class="w-full px-3 py-2 border rounded-lg" placeholder="Your name, position, or office">
              </div>
            </div>
            
            <button id="generateBtn" class="w-full px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center justify-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
              </svg>
              Generate Document
            </button>
          </div>
          
          <div id="generationStatus" class="hidden">
            <div class="flex items-center text-yellow-600 bg-yellow-50 p-3 rounded-lg">
              <svg class="animate-spin -ml-1 mr-3 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>AI is generating your professional document... This may take a few moments.</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Template Uploader Section -->
    <div class="bg-white rounded-lg shadow-sm mb-6">
      <div class="p-4 border-b flex justify-between items-center">
        <h2 class="text-xl font-semibold">Use Document Template</h2>
        <button id="toggleTemplateUploader" class="text-gray-500 hover:text-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="transform: rotate(180deg);">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
      </div>
      <div id="templateUploaderSection" class="p-6">
        <!-- Pre-defined Templates Section -->
        <div class="mb-6">
          <h3 class="text-lg font-medium mb-3">Pre-defined Templates</h3>
          <p class="text-gray-600 mb-4">Select from our pre-defined templates to quickly create your document.</p>
          
          <?php
          // Get user's office information
          $userOfficeId = null;
          $userOfficeName = '';
          if (isset($_SESSION['user_id'])) {
              $userId = $_SESSION['user_id'];
              $userQuery = $conn->prepare("SELECT u.office_id, o.office_name FROM users u 
                                         LEFT JOIN offices o ON u.office_id = o.office_id 
                                         WHERE u.user_id = ?");
              $userQuery->bind_param("i", $userId);
              $userQuery->execute();
              $userResult = $userQuery->get_result();
              if ($userResult && $userRow = $userResult->fetch_assoc()) {
                  $userOfficeId = $userRow['office_id'];
                  $userOfficeName = $userRow['office_name'];
              }
          }
          
                     // Define office-specific templates
           $officeTemplates = [
               // President Office - All templates available (including Memorandum)
               1 => [
                   'memo' => ['name' => 'Memorandum', 'description' => 'For President, VP, or Unit Head', 'icon' => 'blue'],
                   'travel-memo' => ['name' => 'Travel Memo', 'description' => 'For seminars or outside activities', 'icon' => 'indigo'],
                   'budget-request' => ['name' => 'Budget Request', 'description' => 'For office or cleaning supplies', 'icon' => 'green'],
                   'confidential' => ['name' => 'Confidential Letter', 'description' => 'Grievance-related', 'icon' => 'red'],
                   'activity' => ['name' => 'Activity Letter', 'description' => 'HR-related', 'icon' => 'purple'],
                   'warning' => ['name' => 'Warning Letter', 'description' => 'For attendance issues', 'icon' => 'yellow'],
                   'resignation' => ['name' => 'Resignation Letter', 'description' => 'Formal resignation notice', 'icon' => 'red'],
                   'engagement' => ['name' => 'Engagement Letter', 'description' => 'Formal commitment', 'icon' => 'green'],
                   'urgency' => ['name' => 'Letter of Urgency', 'description' => 'Requires immediate attention', 'icon' => 'orange']
               ],
               // HR Department - HR-specific templates (no Memorandum)
               3 => [
                   'activity' => ['name' => 'Activity Letter', 'description' => 'HR-related', 'icon' => 'purple'],
                   'warning' => ['name' => 'Warning Letter', 'description' => 'For attendance issues', 'icon' => 'yellow'],
                   'confidential' => ['name' => 'Confidential Letter', 'description' => 'Grievance-related', 'icon' => 'red'],
                   'resignation' => ['name' => 'Resignation Letter', 'description' => 'Formal resignation notice', 'icon' => 'red'],
                   'engagement' => ['name' => 'Engagement Letter', 'description' => 'Formal commitment', 'icon' => 'green']
               ],
               // Finance Department - Financial templates (no Memorandum)
               5 => [
                   'budget-request' => ['name' => 'Budget Request', 'description' => 'For office or cleaning supplies', 'icon' => 'green'],
                   'urgency' => ['name' => 'Letter of Urgency', 'description' => 'Requires immediate attention', 'icon' => 'orange']
               ],
               // IT Department - Technical templates (no Memorandum)
               4 => [
                   'budget-request' => ['name' => 'Budget Request', 'description' => 'For office or cleaning supplies', 'icon' => 'green'],
                   'urgency' => ['name' => 'Letter of Urgency', 'description' => 'Requires immediate attention', 'icon' => 'orange']
               ],
               // Vice Presidents - Administrative templates (no Memorandum)
               6 => [ // VP for Academic Affairs
                   'travel-memo' => ['name' => 'Travel Memo', 'description' => 'For seminars or outside activities', 'icon' => 'indigo'],
                   'urgency' => ['name' => 'Letter of Urgency', 'description' => 'Requires immediate attention', 'icon' => 'orange']
               ],
               7 => [ // VP for Spirituality and Formation
                   'travel-memo' => ['name' => 'Travel Memo', 'description' => 'For seminars or outside activities', 'icon' => 'indigo'],
                   'activity' => ['name' => 'Activity Letter', 'description' => 'HR-related', 'icon' => 'purple']
               ],
               8 => [ // VP for Administration
                   'budget-request' => ['name' => 'Budget Request', 'description' => 'For office or cleaning supplies', 'icon' => 'green'],
                   'urgency' => ['name' => 'Letter of Urgency', 'description' => 'Requires immediate attention', 'icon' => 'orange']
               ],
               9 => [ // VP for Finance
                   'budget-request' => ['name' => 'Budget Request', 'description' => 'For office or cleaning supplies', 'icon' => 'green'],
                   'urgency' => ['name' => 'Letter of Urgency', 'description' => 'Requires immediate attention', 'icon' => 'orange']
               ],
               // Academic Offices - Academic templates (no Memorandum)
               10 => [ // Law School
                   'travel-memo' => ['name' => 'Travel Memo', 'description' => 'For seminars or outside activities', 'icon' => 'indigo']
               ],
               11 => [ // Graduate School
                   'travel-memo' => ['name' => 'Travel Memo', 'description' => 'For seminars or outside activities', 'icon' => 'indigo']
               ],
               12 => [ // Colleges Office
                   'travel-memo' => ['name' => 'Travel Memo', 'description' => 'For seminars or outside activities', 'icon' => 'indigo']
               ],
               13 => [ // Principals Office
                   'travel-memo' => ['name' => 'Travel Memo', 'description' => 'For seminars or outside activities', 'icon' => 'indigo']
               ],
               // Support Offices - Limited templates (no Memorandum)
               15 => [ // Registrar Office
                   'leave' => ['name' => 'Leave Request', 'description' => 'Request for leave of absence', 'icon' => 'green']
               ],
               16 => [ // Library Office
                   'budget-request' => ['name' => 'Budget Request', 'description' => 'For office or cleaning supplies', 'icon' => 'green']
               ],
               17 => [ // Guidance Office
                   'leave' => ['name' => 'Leave Request', 'description' => 'Request for leave of absence', 'icon' => 'green']
               ],
               18 => [ // Student Affairs Office
                   'activity' => ['name' => 'Activity Letter', 'description' => 'HR-related', 'icon' => 'purple']
               ]
           ];
          
                     // Get templates for user's office, or default to basic templates
           $userTemplates = isset($officeTemplates[$userOfficeId]) ? $officeTemplates[$userOfficeId] : [];
          
          // Icon mapping
          $iconMapping = [
              'blue' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>',
              'indigo' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>',
              'green' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
              'red' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" /></svg>',
              'purple' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>',
              'yellow' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>',
              'orange' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
          ];
          
          // Background color mapping
          $bgMapping = [
              'blue' => 'bg-blue-100',
              'indigo' => 'bg-indigo-100',
              'green' => 'bg-green-100',
              'red' => 'bg-red-100',
              'purple' => 'bg-purple-100',
              'yellow' => 'bg-yellow-100',
              'orange' => 'bg-orange-100'
          ];
          ?>
          
          <!-- Office-specific templates notice -->
          <?php if ($userOfficeName): ?>
          <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                </div>
              <div class="ml-3">
                <p class="text-sm text-blue-700">
                  <strong>Office-specific templates for <?php echo htmlspecialchars($userOfficeName); ?></strong><br>
                  These templates are tailored for your office's specific needs and responsibilities.
                </p>
                </div>
              </div>
            </div>
          <?php endif; ?>
            
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($userTemplates as $templateKey => $template): ?>
            <div class="template-card border rounded-lg p-4 hover:bg-gray-50 cursor-pointer" data-template="<?php echo $templateKey; ?>">
              <div class="flex items-start">
                <div class="<?php echo $bgMapping[$template['icon']]; ?> p-2 rounded-lg mr-3">
                  <?php echo $iconMapping[$template['icon']]; ?>
                </div>
                <div>
                  <h4 class="font-medium"><?php echo htmlspecialchars($template['name']); ?></h4>
                  <p class="text-sm text-gray-500"><?php echo htmlspecialchars($template['description']); ?></p>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            </div>
            
          <!-- Show message if no templates available for office -->
          <?php if (empty($userTemplates)): ?>
          <div class="text-center py-8">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
            <p class="text-gray-500">No specific templates available for your office.</p>
            <p class="text-sm text-gray-400">You can still use custom templates or create documents from scratch.</p>
                </div>
          <?php endif; ?>
        </div>
        
        <div class="border-t pt-6 mt-6">
          <h3 class="text-lg font-medium mb-3">Upload Custom Template</h3>
          <p class="text-gray-600 mb-4">Upload a Word or PDF document to use as a template for your new document. The system will extract the content and structure.</p>
          <div class="flex flex-col space-y-4">
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-4">
                <input type="file" id="templateFile" accept=".docx,.doc,.pdf" class="hidden">
                <label for="templateFile" class="flex-1 px-4 py-2 border border-dashed border-gray-300 rounded-lg text-center cursor-pointer hover:bg-gray-50">
                  <div class="flex flex-col items-center justify-center py-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <span class="text-sm text-gray-500">Click to select or drag and drop a template file</span>
                    <span class="text-xs text-gray-400 mt-1">Supported formats: .docx, .doc, .pdf</span>
                  </div>
                </label>
                <button id="useTemplateBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2" disabled>
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                  </svg>
                  Use Template
                </button>
              </div>
              <div id="selectedTemplate" class="hidden">
                <div class="flex items-center p-2 bg-blue-50 rounded-lg">
                  <svg class="animate-spin -ml-1 mr-3 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span id="templateFileName" class="text-sm text-blue-700"></span>
                  <button id="removeTemplate" class="ml-auto text-red-500 hover:text-red-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
            <div id="templateProcessingStatus" class="hidden">
              <div class="flex items-center text-yellow-600">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Processing template...</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm">
      <form id="documentForm" class="space-y-6" method="POST" enctype="multipart/form-data">
        <div class="p-6 space-y-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Document Title</label>
            <input type="text" name="title" id="docTitle" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
            <div class="relative">
              <select name="type_id" id="documentType" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 appearance-none">
                <option value="">Select Type</option>
                <?php foreach ($document_types as $type): ?>
                <option value="<?php echo $type['type_id']; ?>"><?php echo $type['type_name']; ?></option>
                <?php endforeach; ?>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
              </div>
            </div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Document Content</label>
            <!-- Google Docs Editor Container -->
            <div id="google-docs-editor" class="border rounded-lg min-h-[800px] w-full bg-white">
              <div class="flex flex-col items-center justify-center h-full p-8 bg-gray-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-gray-600 mb-4">Your document will appear here</p>
                <button id="createDocBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                  </svg>
                  Create New Google Doc
                </button>
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Routing Workflow</label>
              <div class="flex flex-col space-y-4">
                <div class="flex items-center gap-4">
                  <input type="text" id="officeSearch" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Search for an office...">
                </div>
                <div id="workflowBuilder" class="space-y-4">
                  <div class="workflow-step flex flex-col gap-2">
                    <div class="flex items-center gap-4">
                      <span class="step-number w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center">1</span>
                      <div class="flex-1">
                        <div class="flex items-center gap-4 mb-2">
                          <label class="inline-flex items-center">
                            <input type="radio" name="recipient_type_1" value="office" class="recipient-type" checked>
                            <span class="ml-2">Office</span>
                          </label>
                          <label class="inline-flex items-center">
                            <input type="radio" name="recipient_type_1" value="person" class="recipient-type">
                            <span class="ml-2">Specific Person</span>
                          </label>
                        </div>
                        <div class="office-select-container">
                          <select name="workflow_offices[]" required class="office-select flex-1 w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Select Office</option>
                            <?php
                            $offices = $conn->query("SELECT * FROM offices ORDER BY office_name");
                            while ($office = $offices->fetch_assoc()) {
                              echo "<option value='" . $office['office_id'] . "'>" . $office['office_name'] . "</option>";
                            }
                            ?>
                          </select>
                          <input type="hidden" name="workflow_roles[]" value="">
                          <input type="hidden" name="recipient_types[]" value="office">
                        </div>
                        <div class="user-select-container hidden">
                          <div class="grid grid-cols-1 gap-2">
                            <div>
                              <label class="block text-sm font-medium text-gray-700 mb-1">Select Office First</label>
                              <select class="filter-office w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                                <option value="">Select Office</option>
                                <?php
                                $offices = $conn->query("SELECT * FROM offices ORDER BY office_name");
                                while ($office = $offices->fetch_assoc()) {
                                  echo "<option value='" . $office['office_id'] . "'>" . $office['office_name'] . "</option>";
                                }
                                ?>
                              </select>
                            </div>
                            <div>
                              <label class="block text-sm font-medium text-gray-700 mb-1">Select Person</label>
                              <select name="user_workflow[]" class="user-select w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                                <option value="">Select Person</option>
                                <?php
                                // Check what columns exist in users table
                                $check_columns = $conn->query("SHOW COLUMNS FROM users");
                                $user_columns = [];
                                while ($col = $check_columns->fetch_assoc()) {
                                    $user_columns[] = $col['Field'];
                                }
                                
                                // Use full_name if available, otherwise use username
                                $name_field = in_array('full_name', $user_columns) ? 'full_name' : 'username';
                                $users = $conn->query("SELECT u.user_id, u.$name_field, u.office_id FROM users u ORDER BY u.$name_field");
                                while ($user = $users->fetch_assoc()) {
                                  $display_name = $user[$name_field];
                                  echo "<option value='" . $user['user_id'] . "' data-office='" . $user['office_id'] . "'>" . htmlspecialchars($display_name) . "</option>";
                                }
                                ?>
                              </select>
                            </div>
                          </div>
                        </div>
                      </div>
                      <button type="button" class="remove-step px-2 py-1 text-red-600 hover:bg-red-50 rounded">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>
                <button type="button" id="addStep" class="mt-4 px-4 py-2 text-sm bg-green-50 text-green-600 rounded-lg hover:bg-green-100">
                  + Add Step
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="p-4 bg-gray-50 rounded-b-lg flex justify-end gap-3">
          <button type="button" id="discardBtn" class="px-4 py-2 text-red-600 bg-white border border-red-300 rounded-lg hover:bg-red-50">
            Discard
          </button>
          <button type="button" id="saveDraftBtn" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
            Save as Draft
          </button>
          <input type="hidden" name="status" value="pending">
          <input type="hidden" name="google_doc_id" id="google_doc_id_input" value="">
          <button type="submit" id="submitBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
            Submit Document
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Success Modal -->
  <div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000] hidden">
    <div class="bg-white rounded-lg w-full max-w-md">
      <div class="p-4 border-b flex justify-between items-center bg-green-50">
        <h3 class="text-lg font-medium text-green-700">Document Submitted Successfully</h3>
        <button id="closeSuccessModal" class="text-gray-500 hover:text-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <div class="p-6">
        <div class="flex items-center mb-4">
          <div class="bg-green-100 p-2 rounded-full mr-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <p class="text-gray-700">Your document has been submitted successfully.</p>
        </div>
        <p class="text-gray-600 mb-6">Would you like to create a new document or view your documents?</p>
        <div class="flex justify-end gap-3">
          <a href="dashboard.php?page=documents" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
            View Documents
          </a>
          <button id="createNewDocBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
            Create New Document
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Draft List Modal -->
  <div id="draftModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000] hidden">
    <div class="bg-white rounded-lg w-full max-w-2xl">
      <div class="p-4 border-b flex justify-between items-center">
        <h3 class="text-lg font-medium">Saved Drafts</h3>
        <button id="closeDraftModal" class="text-gray-500 hover:text-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <div class="p-4 max-h-96 overflow-y-auto">
        <div id="draftsList" class="space-y-3">
          <!-- Drafts will be loaded here -->
          <p class="text-gray-500 text-center py-4">No drafts found</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Memo Options Modal -->
  <div id="memoOptionsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000] hidden">
    <div class="bg-white rounded-lg w-full max-w-md">
      <div class="p-4 border-b flex justify-between items-center">
        <h3 class="text-lg font-medium">Memorandum Routing</h3>
        <button id="closeMemoOptionsModal" class="text-gray-500 hover:text-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <div class="p-6">
        <p class="text-gray-600 mb-6">How would you like to route this memorandum?</p>
        <div class="flex justify-end gap-3">
          <button id="memoSendToAllBtn" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
            Send to All Offices
          </button>
          <button id="memoSelectManuallyBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
            Select Manually
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Google Docs Integration and Drafts
    let currentDocumentId = '';
    let currentDraftId = null;
    
    // Direct link to Google authentication
    function connectToGoogleDocs() {
      window.location.href = 'google_docs_connect.php?connect=1&return=compose.php';
    }
    
    // Reconnect to Google Docs
    function reconnectToGoogleDocs() {
      window.location.href = '?reconnect=1';
    }
    
  // Create a new Google Doc when the page loads
  window.addEventListener('DOMContentLoaded', function() {
      // If clone param is present, load that document's data into the form
      const cloneIdParam = new URLSearchParams(window.location.search).get('clone');
      if (cloneIdParam) {
        fetch('../actions/get_document_details.php?id=' + encodeURIComponent(cloneIdParam), { credentials: 'same-origin' })
          .then(r => r.json())
          .then(d => {
            if (d && !d.error) {
              const titleField = document.getElementById('docTitle');
              if (titleField) titleField.value = (d.title || '') + ' (Copy)';
              const typeSelect = document.getElementById('documentType');
              if (typeSelect && d.type_id) typeSelect.value = String(d.type_id);
              // If there is an associated Google Doc, load it into the iframe so user can edit
              if (d.google_doc_id) {
                currentDocumentId = d.google_doc_id;
                if (typeof updateGoogleDocsIframe === 'function') {
                  updateGoogleDocsIframe(currentDocumentId);
                }
                const hidden = document.getElementById('google_doc_id_input');
                if (hidden) hidden.value = currentDocumentId;
              }
              // Best-effort: try to reconstruct minimal workflow route if available via API later
            }
          })
          .catch(()=>{});
      }

      // Load draft if draft_id provided
      const draftIdParam = new URLSearchParams(window.location.search).get('draft_id');
      if (draftIdParam) {
        fetch('../actions/save_draft.php?action=get_draft&draft_id=' + encodeURIComponent(draftIdParam), { credentials: 'same-origin' })
          .then(r => r.json())
          .then(d => {
            if (!d.success) { showErrorMessage(d.message || 'Failed to load draft'); return; }
            currentDraftId = d.draft.draft_id;
            // Populate fields
            const titleField = document.getElementById('docTitle');
            if (titleField) titleField.value = d.draft.title || '';
            const typeSelect = document.getElementById('documentType');
            if (typeSelect && d.draft.type_id) typeSelect.value = String(d.draft.type_id);
            if (d.draft.content) { currentDocumentId = d.draft.content; updateGoogleDocsIframe(currentDocumentId); document.getElementById('google_doc_id_input').value = currentDocumentId; }
            // Rebuild workflow (best-effort)
            if (Array.isArray(d.draft.workflow)) {
              const builder = document.getElementById('workflowBuilder');
              builder.innerHTML = '';
              d.draft.workflow.forEach((step, idx) => {
                const div = document.createElement('div');
                div.className = 'workflow-step flex flex-col gap-2';
                div.innerHTML = `
                  <div class="flex items-center gap-4">
                    <span class="step-number w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center">${idx+1}</span>
                    <div class="flex-1">
                      <div class="office-select-container">
                        <select name="workflow_offices[]" required class="office-select flex-1 w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                          <option value="">Select Office</option>
                          <?php $offices = $conn->query("SELECT * FROM offices ORDER BY office_name"); while($office = $offices->fetch_assoc()){ echo "<option value='".$office['office_id']."'>".$office['office_name']."</option>"; } ?>
                        </select>
                        <input type="hidden" name="workflow_roles[]" value="">
                        <input type="hidden" name="recipient_types[]" value="office">
                      </div>
                    </div>
                  </div>`;
                builder.appendChild(div);
                const select = div.querySelector('select');
                if (select && step && step.recipient && step.recipient.type==='office') select.value = String(step.recipient.id);
              });
            }
          })
          .catch(e => showErrorMessage(e.message || 'Network error loading draft'));
      }
      <?php if ($isConnectedToGoogle) { ?>
      // Only create a new Google Doc when not editing a draft
      const draftIdParamEarly = new URLSearchParams(window.location.search).get('draft_id');
      if (!draftIdParamEarly) {
        createNewGoogleDoc();
      }
      <?php } ?>
      
      // Add click event to the connect button
      const connectButton = document.getElementById('connectGoogleDocsBtn');
      if (connectButton) {
        connectButton.addEventListener('click', connectToGoogleDocs);
      }
      
      // Set up AI Generator
      setupAIGenerator();
      
      // Set up template cards
      setupTemplateCards();
      
      // Set up form submission
      setupFormSubmission();
      
      // Set up the Create New Google Doc button
      const createDocBtn = document.getElementById('createDocBtn');
      if (createDocBtn) {
        createDocBtn.addEventListener('click', function() {
          createNewGoogleDoc();
        });
      }
    });
    
    // AI Document Generator
    function setupAIGenerator() {
      const aiGeneratorToggle = document.getElementById('toggleAiGenerator');
      const aiGeneratorSection = document.getElementById('aiGeneratorSection');
      const generateBtn = document.getElementById('generateBtn');
      const aiPrompt = document.getElementById('aiPrompt');
      const docTypeSelect = document.getElementById('docTypeSelect');
      const senderInfo = document.getElementById('senderInfo');
      const generationStatus = document.getElementById('generationStatus');
      
      // Toggle AI Generator section - make visible by default
      if (aiGeneratorToggle) {
        // Show the AI generator by default to make it more discoverable
        aiGeneratorSection.classList.remove('hidden');
        aiGeneratorToggle.querySelector('svg').style.transform = 'rotate(180deg)';
        
        aiGeneratorToggle.addEventListener('click', function() {
          aiGeneratorSection.classList.toggle('hidden');
          
          // Update the toggle icon
          const icon = this.querySelector('svg');
          if (aiGeneratorSection.classList.contains('hidden')) {
            icon.style.transform = '';
          } else {
            icon.style.transform = 'rotate(180deg)';
          }
        });
      }
      
      // Generate document with AI
      if (generateBtn) {
        generateBtn.addEventListener('click', function() {
          const prompt = aiPrompt.value.trim();
          const docType = docTypeSelect.value.trim();
          const sender = senderInfo.value.trim();
          
          // Store these in a scope accessible to nested functions
          const titleGenerationContext = {
            prompt: prompt,
            docType: docType,
            sender: sender
          };
          
          if (!prompt) {
            showErrorMessage('Please enter a prompt for the AI generator');
            return;
          }
          
          if (!currentDocumentId) {
            showErrorMessage('No active Google Doc. Please refresh the page and try again.');
            return;
          }
          
          // Show generation status
          generationStatus.classList.remove('hidden');
          
          // Build the complete prompt with additional information
          let fullPrompt = prompt;
          
          // Add document type if specified
          if (docType) {
            fullPrompt = `Create a ${docType}: ${fullPrompt}`;
          }
          
          // Add sender information if provided
          if (sender) {
            fullPrompt = `${fullPrompt} From: ${sender}`;
          }
          
          // Add Saint Columban College context if not already mentioned
          if (!fullPrompt.includes('Saint Columban College') && !fullPrompt.includes('SCC')) {
            fullPrompt = `${fullPrompt} For Saint Columban College in Pagadian City.`;
          }
          
          // Add an instruction for proper spacing
          fullPrompt += " Ensure proper spacing between paragraphs and elements.";
          
          console.log('Full AI prompt:', fullPrompt);
          
          // Call the API to generate content
          fetch('../api/google_docs_api.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
              'action': 'generate_with_ai',
              'prompt': fullPrompt,
              'document_id': currentDocumentId
            })
          })
          .then(response => {
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
              throw new Error('Server returned non-JSON response. This might be an HTML error page.');
            }
            return response.json();
          })
          .then(data => {
            // Hide generation status
            generationStatus.classList.add('hidden');
            
            if (data.success) {
              showSuccessMessage('Document generated successfully');
              showNotification('Professional document has been created', 'success');
              
              // Update document ID if provided
              if (data.document_id) {
                currentDocumentId = data.document_id;
              } else if (data.document && data.document.id) {
                currentDocumentId = data.document.id;
              }
              
              // Update the iframe if we have a document ID
              if (currentDocumentId) {
                updateGoogleDocsIframe(currentDocumentId);
                
                // Set the value in the hidden input field
                document.getElementById('google_doc_id_input').value = currentDocumentId;
              }
              
              // Always auto-generate and populate the document title based on content
              const titleField = document.getElementById('docTitle');
              if (titleField) {
                // Use the content returned from the API if available (much faster and more reliable)
                const generatedContent = data.content || '';
                
                // Generate title immediately using the content we already have
                if (generatedContent && currentDocumentId) {
                  console.log('Generating title from returned content...');
                  
                  // Get document type for better context
                  const docTypeSelect = document.getElementById('documentType');
                  const docTypeText = docTypeSelect && docTypeSelect.options[docTypeSelect.selectedIndex] 
                    ? docTypeSelect.options[docTypeSelect.selectedIndex].text 
                    : '';
                  
                  fetch('../api/google_docs_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                      action: 'suggest_title',
                      document_id: currentDocumentId,
                      prompt: titleGenerationContext.prompt, // Include the original prompt for context
                      content: generatedContent, // Pass content directly - no need to read from Google Docs
                      document_type: docTypeText, // Include document type for better context
                      doc_type: titleGenerationContext.docType, // Include the selected doc type from AI generator
                      sender_info: titleGenerationContext.sender // Include sender info if provided
                    })
                  })
                  .then(r => {
                    if (!r.ok) {
                      throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                    }
                    return r.json();
                  })
                  .then(t => {
                    if (t && t.success && t.title && t.title.trim() !== '') {
                      titleField.value = t.title.trim();
                      showNotification('Document title generated: ' + t.title, 'success');
                      console.log('AI-generated title:', t.title);
                    } else {
                      console.warn('Title generation returned empty:', t);
                    }
                  })
                  .catch(error => {
                    console.error('Error generating title:', error);
                    // Non-fatal - document is still created
                  });
                } else if (currentDocumentId) {
                  // Fallback: if content wasn't returned, try reading from Google Docs (with retries)
                  console.log('Content not in response, attempting to read from Google Docs...');
                  const generateTitle = (retryCount = 0, maxRetries = 2) => {
                    const delay = retryCount === 0 ? 2000 : 2000;
                    
                    setTimeout(() => {
                      // Get document type for better context
                      const docTypeSelect = document.getElementById('documentType');
                      const docTypeText = docTypeSelect && docTypeSelect.options[docTypeSelect.selectedIndex] 
                        ? docTypeSelect.options[docTypeSelect.selectedIndex].text 
                        : '';
                      
                      fetch('../api/google_docs_api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                          action: 'suggest_title',
                          document_id: currentDocumentId,
                          prompt: titleGenerationContext.prompt,
                          document_type: docTypeText, // Include document type for better context
                          doc_type: titleGenerationContext.docType,
                          sender_info: titleGenerationContext.sender
                        })
                      })
                      .then(r => {
                        if (!r.ok) {
                          throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                        }
                        return r.json();
                      })
                      .then(t => {
                        if (t && t.success && t.title && t.title.trim() !== '') {
                          titleField.value = t.title.trim();
                          showNotification('Document title generated: ' + t.title, 'success');
                          console.log('AI-generated title:', t.title);
                        } else if (retryCount < maxRetries) {
                          console.warn(`Title generation returned empty (attempt ${retryCount + 1}), retrying...`);
                          generateTitle(retryCount + 1, maxRetries);
                        } else {
                          console.warn('Title generation failed after all retries:', t);
                        }
                      })
                      .catch(error => {
                        console.error(`Error generating title (attempt ${retryCount + 1}):`, error);
                        if (retryCount < maxRetries) {
                          generateTitle(retryCount + 1, maxRetries);
                        }
                      });
                    }, delay);
                  };
                  
                  generateTitle();
                }
              }
              
              // Auto-select document type if not already selected
              const documentTypeSelect = document.getElementById('documentType');
              if (documentTypeSelect && documentTypeSelect.value === '') {
                // Map AI document type to document type in the select
                const typeMapping = {
                  'memo': 'Memorandum',
                  'letter': 'Letter',
                  'leave': 'Leave Request',
                  'report': 'Report',
                  'announcement': 'Announcement'
                };
                
                // Try to find a matching document type
                if (docType && typeMapping[docType]) {
                  const typeName = typeMapping[docType];
                  
                  // Look for this type in the select options
                  for (let i = 0; i < documentTypeSelect.options.length; i++) {
                    if (documentTypeSelect.options[i].text.includes(typeName)) {
                      documentTypeSelect.selectedIndex = i;
                      break;
                    }
                  }
                }
              }
              
              // Don't close the AI generator section automatically to allow for quick refinements
              // aiGeneratorSection.classList.add('hidden');
              // aiGeneratorToggle.querySelector('svg').style.transform = '';
            } else {
              showErrorMessage('Error generating document: ' + data.error);
            }
          })
          .catch(error => {
            // Hide generation status
            generationStatus.classList.add('hidden');
            
            console.error('AI Generation Error:', error);
            
            // Provide more helpful error message
            let errorMessage = 'Error generating document: ' + error.message;
            
            if (error.message.includes('non-JSON response')) {
              errorMessage = 'Server error: The AI service returned an invalid response. Please check your Gemini API key in AI Settings and try again.';
            } else if (error.message.includes('JSON')) {
              errorMessage = 'Response parsing error: The server returned invalid data. Please try again or contact support.';
            }
            
            showErrorMessage(errorMessage);
            showNotification(errorMessage, 'error');
          });
        });
      }
      
      // Add keyboard shortcut (Ctrl+Enter or Cmd+Enter) to generate document
      if (aiPrompt) {
        aiPrompt.addEventListener('keydown', function(e) {
          // Check if Ctrl/Cmd + Enter was pressed
          if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault(); // Prevent default behavior
            generateBtn.click(); // Trigger generate button click
          }
        });
      }
    }
    
    // Create a new Google Doc
    function createNewGoogleDoc() {
      const editorContainer = document.getElementById('google-docs-editor');
      if (!editorContainer) return;
      
      // Show loading state
      editorContainer.innerHTML = `
        <div class="flex flex-col items-center justify-center h-full bg-gray-50">
          <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-blue-500 mb-4"></div>
          <p class="text-gray-600">Creating a new blank document...</p>
        </div>
      `;
      
      console.log('Attempting to create a new blank Google Doc...');
      
      // Get document title from the title field or use a default
      const titleField = document.getElementById('docTitle');
      const documentTitle = titleField && titleField.value ? titleField.value : 'New Document ' + new Date().toLocaleString();
      
      // First create a new document
      fetch('../api/google_docs_api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          'action': 'create_document',
          'title': documentTitle,
          'content': '' // Create an empty document without any default template
        })
      })
      .then(response => response.text())
      .then(text => {
        console.log('Response text:', text);
        
        try {
          const data = JSON.parse(text);
          console.log('Data received:', data);
          
          if (data.success) {
            currentDocumentId = data.document.id;
            
            // Set the value in the hidden input field
            document.getElementById('google_doc_id_input').value = currentDocumentId;
            
            // Update the iframe with the new document
            updateGoogleDocsIframe(currentDocumentId);
            showSuccessMessage('Empty Google Doc created successfully');
            
            // If title field is empty, set it to the document title
            if (titleField && !titleField.value) {
              titleField.value = data.document.title;
            }
          } else {
            // Check if this is an authentication error
            const isAuthError = data.auth_required || 
              (data.error && (
                data.error.includes('authentication') || 
                data.error.includes('auth') || 
                data.error.includes('token') || 
                data.error.includes('expired')
              ));
            
            if (isAuthError) {
              showAuthErrorMessage(data.error || 'Authentication error');
            } else {
              showErrorMessage(data.error || 'Failed to create Google Doc');
            }
            
            // Show error in editor container
            editorContainer.innerHTML = `
              <div class="flex flex-col items-center justify-center h-full bg-gray-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p class="text-red-600 font-medium mb-2">Error creating Google Doc</p>
                <p class="text-gray-600 text-sm mb-4">${data.error || 'Unknown error'}</p>
                ${isAuthError ? `
                <button onclick="reconnectToGoogleDocs()" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                  Reconnect to Google Docs
                </button>
                ` : ''}
              </div>
            `;
          }
        } catch (e) {
          console.error('Error parsing JSON:', e);
          showErrorMessage('Error parsing response: ' + e.message);
          
          // Show error in editor container
          editorContainer.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full bg-gray-50">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <p class="text-red-600 font-medium mb-2">Error parsing response</p>
              <p class="text-gray-600 text-sm mb-4">${e.message}</p>
              <button onclick="createNewGoogleDoc()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Try Again
              </button>
            </div>
          `;
        }
      })
      .catch(error => {
        console.error('Fetch error:', error);
        showErrorMessage('Network error: ' + error.message);
        
        // Show error in editor container
        editorContainer.innerHTML = `
          <div class="flex flex-col items-center justify-center h-full bg-gray-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p class="text-red-600 font-medium mb-2">Network Error</p>
            <p class="text-gray-600 text-sm mb-4">${error.message}</p>
            <button onclick="createNewGoogleDoc()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
              Try Again
            </button>
          </div>
        `;
      });
    }
    
    // Draft helpers
    function saveDraft(){
      const title = (document.getElementById('docTitle')?.value || '').trim();
      const typeId = document.getElementById('documentType')?.value || '';
      if (!currentDocumentId) { showErrorMessage('No active Google Doc to save.'); return; }
      const workflow = collectWorkflowData();
      const body = new URLSearchParams();
      body.set('action','save_draft');
      if (currentDraftId) body.set('draft_id', String(currentDraftId));
      body.set('title', title);
      if (typeId) body.set('type_id', String(typeId));
      body.set('content', currentDocumentId);
      body.set('workflow', JSON.stringify(workflow));
      fetch('../actions/save_draft.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        credentials:'same-origin',
        body
      }).then(r=>r.json()).then(d=>{
        if (d.success){
          currentDraftId = d.draft_id || currentDraftId; 
          showSuccessMessage('Draft saved');
        } else {
          showErrorMessage(d.message||'Failed to save draft');
        }
      }).catch(e=>showErrorMessage(e.message||'Network error'))
    }

    function discardDraft(){
      if (!confirm('Discard this unsent document? This will remove any saved draft.')) return;
      if (currentDraftId){
        const body = new URLSearchParams();
        body.set('action','delete_draft');
        body.set('draft_id', String(currentDraftId));
        fetch('../actions/save_draft.php',{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, credentials:'same-origin', body })
          .then(r=>r.json()).then(()=>{ window.location.href = 'dashboard.php?page=drafts'; })
          .catch(()=>{ window.location.href = 'dashboard.php?page=drafts'; });
      } else {
        window.location.href = 'dashboard.php?page=drafts';
      }
    }

    // Wire buttons
    document.getElementById('saveDraftBtn')?.addEventListener('click', saveDraft);
    document.getElementById('discardBtn')?.addEventListener('click', discardDraft);

    // Show authentication error message with reconnect button
    function showAuthErrorMessage(message) {
      const alertContainer = document.getElementById('alertContainer');
      if (!alertContainer) return;
      
      alertContainer.innerHTML = `
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          <strong class="font-bold">Authentication Error!</strong>
          <span class="block sm:inline">${message}</span>
          <div class="mt-2">
            <button onclick="reconnectToGoogleDocs()" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 inline-block">
              Reconnect to Google Docs
            </button>
          </div>
        </div>
      `;
    }
    
    // Show error message
    function showErrorMessage(message) {
      const alertContainer = document.getElementById('alertContainer');
      if (!alertContainer) return;
      
      alertContainer.innerHTML = `
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          <strong class="font-bold">Error!</strong>
          <span class="block sm:inline">${message}</span>
        </div>
      `;
    }
    
    // Show success message
    function showSuccessMessage(message, autoHide = true) {
      const alertContainer = document.getElementById('alertContainer');
      if (!alertContainer) return;
      
      alertContainer.innerHTML = `
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
          <strong class="font-bold">Success!</strong>
          <span class="block sm:inline">${message}</span>
        </div>
      `;
      
      if (autoHide) {
        setTimeout(() => {
          alertContainer.innerHTML = '';
        }, 3000);
      }
    }
    
    // Update the Google Docs iframe with the document ID
    function updateGoogleDocsIframe(documentId) {
      if (!documentId) return;
      
      const editorContainer = document.getElementById('google-docs-editor');
      if (!editorContainer) return;
      
      console.log('Updating Google Docs iframe with document ID:', documentId);
      
      // Set the current document ID
      currentDocumentId = documentId;
      
      // Create the iframe for the Google Doc (force editing mode, not suggesting)
      // Using rm=minimal removes UI elements and ensures edit mode
      const iframeHtml = `
        <iframe 
          src="https://docs.google.com/document/d/${documentId}/edit?rm=minimal&embedded=true" 
          frameborder="0" 
          class="w-full h-[800px] border-0"
        ></iframe>
      `;
      
      // Update the editor container
      editorContainer.innerHTML = iframeHtml;
    }
    
    // Apply a template to the Google Doc
    function applyTemplate(templateType, data) {
      if (!currentDocumentId) {
        showErrorMessage('No active Google Doc. Please create a new document first.');
        return;
      }
      
      console.log('Applying template:', templateType, 'with data:', data);
      
      // Show loading notification
      showNotification(`Applying ${templateType} template...`, 'info');
      
      fetch('../api/google_docs_api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'create_from_template',
          document_id: currentDocumentId,
          template_type: templateType,
          data: data
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showSuccessMessage('Template applied successfully');
          showNotification('Template applied with header and footer', 'success');
          // Ask backend to suggest a title based on resulting content if title empty
          const titleField = document.getElementById('docTitle');
          const needsTitle = titleField && (!titleField.value.trim() || /^New Document/i.test(titleField.value.trim()));
          if (needsTitle) {
            fetch('../api/google_docs_api.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({
                action: 'suggest_title',
                document_id: currentDocumentId,
                template_type: templateType
              })
            })
            .then(r => r.json())
            .then(t => { if (t && t.success && t.title) { titleField.value = t.title; } })
            .catch(() => {});
          }
        } else {
          showErrorMessage('Error applying template: ' + data.error);
          showNotification('Error applying template: ' + data.error, 'error');
        }
      })
      .catch(error => {
        showErrorMessage('Error applying template: ' + error.message);
        showNotification('Error applying template: ' + error.message, 'error');
      });
    }
    
    // Handle template card clicks
    function setupTemplateCards() {
      document.querySelectorAll('.template-card').forEach(card => {
        card.addEventListener('click', function() {
          const templateType = this.getAttribute('data-template');
          const memoOptionsModal = document.getElementById('memoOptionsModal');
          
          // Example data for templates - in a real app, you would collect this from the user
          const templateData = {
            'memo': {
              to: 'ALL EMPLOYEES',
              from_name: 'SR. AGNES Y. SUARIN, CB.',
              from_position: 'Vice President for Spirituality and Formation',
              date: 'December 12, 2024',
              subject: 'ADVENT RECOLLECTION 2024',
              copy_to: 'President, VPs, Deans, Unit Heads, and Principals',
              signature_name: 'SR. AGNES Y. SUARIN, CB.',
              signature_position: 'Vice President for Spirituality and Formation'
            },
            'travel-memo': {
              recipient: 'All Department Heads',
              sender: 'Office of the President',
              date: new Date().toLocaleDateString(),
              subject: 'Travel Authorization',
              destination: 'Manila',
              travel_dates: 'June 15-20, 2023',
              purpose: 'Annual Conference',
              transportation: 'Air Travel',
              accommodation: 'Hotel Accommodation',
              expenses: 'PHP 25,000'
            },
            'budget-request': {
                date: 'December 4, 2024',
                recipient_name: 'MRS. VIRGINIA A. RUBEN, CPA, MBA',
                recipient_position: 'Vice President for Finance',
                recipient_institution: 'This Institution',
                salutation: "Dear Ma'am Ruben,",
                greeting: 'Panagdait sa Dios, sa tanan, ug sa tanang kauhatani',
                body_introduction: "As part of our ongoing commitment to excellence, we have identified several critical proposals that are not included in the annual budget but are essential for enhancing our IT infrastructure. Specifically, we propose two initiatives: the Managed Firewall and Additional Internet Bandwidth.\n\nThe Managed Firewall is vital for boosting our security measures and facilitating improved digital communication between the San Francisco and Buenavista campuses, particularly as both campuses rely on a single point server located at the San Francisco campus. Additionally, we need to address the urgent requirement for a stable internet connection for the San Francisco campus.\n\nBoth proposals have undergone comprehensive financial analysis to evaluate the necessary investments in our IT infrastructure. I would like to formally request a realignment of funds to support these initiatives. Below is a breakdown of the identified items for realignment:",
                projects: [
                    {
                        name: 'Project: Managed Firewall',
                        cost: 'Cost: Php 1,700,000.00',
                        source_of_funds_title: 'Source of Funds:',
                        sources: [
                            { item: 'PLDT-MAIN', amount: 'Php 528,000.00' },
                            { item: 'Solar Power System', amount: 'Php 600,000.00' },
                            { item: 'CCTV Rehabilitation', amount: 'Php 172,000.00' },
                            { item: 'Computer Units for Buenavista Campus', amount: 'Php 400,000.00' }
                        ],
                        total: 'Total: Php 1,700,000.00'
                    },
                    {
                        name: 'Project: Additional Bandwidth',
                        cost: 'Cost: Php 864,000.00',
                        source_of_funds_title: 'Source of Funds:',
                        sources: [
                            { item: 'PLDT-MAIN', amount: 'Php 672,000.00' },
                            { item: 'CCTV Rehabilitation', amount: 'Php 178,000.00' },
                            { item: 'Google Workspace Subscription', amount: 'Php 14,000.00' }
                        ],
                        total: 'Total: Php 864,000.00'
                    }
                ],
                body_conclusion: 'I appreciate your consideration of this request and hope for your approval. Thank you for your attention to this matter, and may we continue to move forward together successfully.',
                closing_salutation: 'In Saint Columban,',
                sender_name: 'ENGR. NATHANIEL L. DEMANDACO',
                sender_position: 'MIS Head',
                recommending_approval_name: 'BRO. REY CATERBAS',
                recommending_approval_position: 'Budget Officer',
                approved_by_name: 'MRS. VIRGINIA A. RUBEN, CPA, MBA',
                approved_by_position: 'Vice President for Finance'
            },
            'letter': {
              recipient_name: 'Dr. Juan Dela Cruz',
              recipient_position: 'Dean, College of Engineering',
              recipient_address: 'Saint Columban College, Pagadian City',
              salutation: 'Dr. Dela Cruz',
              content: 'I am writing to inform you about...',
              sender_name: document.querySelector('input[name="title"]').value || 'Your Name',
              sender_position: 'Your Position'
            },
            'warning': {
              recipient_name: 'John Doe',
              recipient_position: 'Staff',
              recipient_address: 'Department of Administration',
              salutation: 'Mr. Doe',
              issue: 'repeated tardiness',
              sender_name: 'Your Name',
              sender_position: 'Department Head',
              date: new Date().toLocaleDateString()
            }
          };
          
          const data = templateData[templateType] || {};
          
          // Special handling for memorandum template
          if (templateType === 'memo') {
            applyTemplate(templateType, data);
            memoOptionsModal.classList.remove('hidden');
            return; // Return here to apply template data separately
          }
          
          applyTemplate(templateType, data);
        });
      });
      
      // Memo options modal logic
      const memoOptionsModal = document.getElementById('memoOptionsModal');
      const closeMemoOptionsModal = document.getElementById('closeMemoOptionsModal');
      const memoSendToAllBtn = document.getElementById('memoSendToAllBtn');
      const memoSelectManuallyBtn = document.getElementById('memoSelectManuallyBtn');
      const workflowBuilder = document.getElementById('workflowBuilder');
      const addStepBtn = document.getElementById('addStep');
      
      if (closeMemoOptionsModal) {
        closeMemoOptionsModal.addEventListener('click', () => memoOptionsModal.classList.add('hidden'));
      }
      
      if (memoSendToAllBtn) {
        memoSendToAllBtn.addEventListener('click', () => {
          // Set document title to Memorandum
          document.getElementById('docTitle').value = 'Memorandum';
          
          // Clear existing workflow steps
          workflowBuilder.innerHTML = '';
          
          // Get current user's office ID to exclude it
          const currentUserOfficeId = <?php echo json_encode($userOfficeId ?? 1); ?>;
          
          // Fetch all offices from the API
          fetch('../api/get_offices.php')
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                // Filter out the President's office (ID 1) and current user's office
                const filteredOffices = data.offices.filter(office => 
                  office.office_id != 1 && office.office_id != currentUserOfficeId
                );
                
                // Add filtered offices to the workflow
                console.log('Adding offices to workflow:', filteredOffices);
                filteredOffices.forEach((office, index) => {
                  // Create a workflow step for each office
                  const stepDiv = document.createElement('div');
                  stepDiv.className = 'workflow-step bg-gray-50 p-4 rounded-lg border relative';
                  stepDiv.innerHTML = `
                    <div class="flex justify-between items-center mb-2">
                      <span class="font-medium">Step ${index + 1}: View Only</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="text-sm text-gray-600">Send to:</span>
                      <span class="font-medium">${office.office_name}</span>
                      <input type="hidden" name="workflow_offices[]" value="${office.office_id}" class="office-select" />
                      <input type="hidden" name="workflow_roles[]" value="" />
                      <input type="hidden" name="recipient_types[]" value="office" />
                      <input type="hidden" name="view_only[]" value="1" />
                    </div>
                  `;
                  workflowBuilder.appendChild(stepDiv);
                  console.log(`Added office ${office.office_name} (ID: ${office.office_id}) to workflow`);
                });
                
                // Disable the Add Step button for memorandums
                if (addStepBtn) {
                  addStepBtn.disabled = true;
                  addStepBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
                
                // Show a notification
                showNotification(`Memorandum will be sent to ${filteredOffices.length} offices as view-only`, 'info');
              } else {
                showErrorMessage('Failed to fetch offices: ' + data.error);
              }
            })
            .catch(error => {
              showErrorMessage('Error fetching offices: ' + error.message);
            });
          
          memoOptionsModal.classList.add('hidden');
        });
      }
      
      if (memoSelectManuallyBtn) {
        memoSelectManuallyBtn.addEventListener('click', () => {
          document.getElementById('docTitle').value = 'Memorandum';
          workflowBuilder.innerHTML = ''; // Clear just in case
          
          // Ensure "Add Step" is enabled
          if (addStepBtn) {
            addStepBtn.disabled = false;
            addStepBtn.classList.remove('opacity-50', 'cursor-not-allowed');
          }
          
          memoOptionsModal.classList.add('hidden');
        });
      }
    }
    
    // Office Search Functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Check if we need to show the success modal
      <?php if ($showSuccessModal): ?>
      const successModal = document.getElementById('successModal');
      if (successModal) {
        successModal.classList.remove('hidden');
        
        // Handle close button
        document.getElementById('closeSuccessModal').addEventListener('click', function() {
          successModal.classList.add('hidden');
        });
        
        // Handle create new document button
        document.getElementById('createNewDocBtn').addEventListener('click', function() {
          // Reset the form
          document.getElementById('documentForm').reset();
          
          // Clear the Google Doc iframe
          const editorContainer = document.getElementById('google-docs-editor');
          if (editorContainer) {
            editorContainer.innerHTML = `
              <div class="flex flex-col items-center justify-center h-full p-8 bg-gray-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-gray-600 mb-4">Your document will appear here</p>
                <button id="createDocBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                  </svg>
                  Create New Google Doc
                </button>
              </div>
            `;
            
            // Reset the current document ID
            currentDocumentId = '';
            document.getElementById('google_doc_id_input').value = '';
            
            // Re-attach event listener to the create doc button
            const createDocBtn = document.getElementById('createDocBtn');
            if (createDocBtn) {
              createDocBtn.addEventListener('click', createNewGoogleDoc);
            }
          }
          
          // Hide the modal
          successModal.classList.add('hidden');
        });
      }
      <?php endif; ?>
      
      // Initialize form submission handlers (disabled - using direct handler below)
      // setupFormSubmission();
      
      // Remove any existing event listeners and add a single, clean one
      const submitBtn = document.getElementById('submitBtn');
      if (submitBtn) {
        // Clone the button to remove all event listeners
        const newSubmitBtn = submitBtn.cloneNode(true);
        submitBtn.parentNode.replaceChild(newSubmitBtn, submitBtn);
        
        console.log('Adding clean event handler to submit button');
        newSubmitBtn.addEventListener('click', function(e) {
          e.preventDefault(); // Always prevent default form submission
          e.stopPropagation(); // Stop event bubbling
          
          console.log('Submit button clicked - JavaScript handling submission');
          showNotification('JavaScript handling form submission...', 'info');
          
          // Check if Google Doc exists
          if (!currentDocumentId) {
            showErrorMessage('No active Google Doc. Please create or select a Google Doc first.');
            return;
          }
          
          // Validate basic form fields
          const title = document.getElementById('docTitle').value.trim();
          const typeId = document.getElementById('documentType').value;
          
          if (!title) {
            showErrorMessage('Please enter a document title');
            return;
          }
          
          if (!typeId) {
            showErrorMessage('Please select a document type');
            return;
          }
          
          // Show loading state
          this.disabled = true;
          this.innerHTML = `<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Submitting...`;
          
          // Collect form data
          const formData = new FormData();
          formData.append('title', title);
          formData.append('type_id', typeId);
          formData.append('status', 'pending');
          formData.append('google_doc_id', currentDocumentId);
          
          // Attachment removed
          
          // Add workflow data
          const workflow = collectWorkflowData();
          console.log('Workflow data:', workflow);
          
          if (workflow.length === 0) {
            showErrorMessage('Please add at least one workflow step');
            this.disabled = false;
            this.innerHTML = 'Submit Document';
            return;
          }
          
          // Check if this is a memorandum sent to all offices
          const isMemorandumToAll = checkIfMemorandumToAll();
          if (isMemorandumToAll) {
            formData.append('is_memorandum_to_all', '1');
            console.log('Detected memorandum to all offices - will process simultaneously');
          }
          
          // Add workflow offices and roles
          workflow.forEach((step, index) => {
            if (step.recipient.type === 'office') {
              formData.append('workflow_offices[]', step.recipient.id);
              formData.append('workflow_roles[]', '');
              formData.append('recipient_types[]', 'office');
            } else if (step.recipient.type === 'person') {
              // Get the office ID for this user
              const userSelect = document.querySelector(`.workflow-step:nth-child(${index + 1}) .user-select`);
              const officeId = userSelect.options[userSelect.selectedIndex].getAttribute('data-office');
              
              formData.append('workflow_offices[]', officeId);
              formData.append('workflow_roles[]', step.recipient.id);
              formData.append('recipient_types[]', 'person');
            }
          });
          
          // Debug: Log all form data
          for (const pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
          }
          
          // Submit the form to the API endpoint
          fetch('../api/submit_document_clean.php', {
            method: 'POST',
            body: formData
          })
          .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            // Check if the response is JSON
            const contentType = response.headers.get('content-type');
            console.log('Content-Type:', contentType);
            
            if (contentType && contentType.includes('application/json')) {
              return response.json();
            } else {
              // If not JSON, get the text and log it for debugging
              return response.text().then(text => {
                console.error('Non-JSON response received:');
                console.error('Response text:', text);
                console.error('Response length:', text.length);
                throw new Error('Unexpected response format from server. Check console for details.');
              });
            }
          })
          .then(data => {
            // Reset button state
            this.disabled = false;
            this.innerHTML = 'Submit Document';
            
            if (data.success) {
              showSuccessMessage('Document submitted successfully');
              showNotification('Document submitted successfully! Refreshing page...', 'success');
              console.log('SUCCESS: Document submitted successfully');
              console.log('Response data:', data);
              
              // Clear the form and refresh the page after a short delay
              setTimeout(() => {
                document.getElementById('documentForm').reset();
                window.location.reload();
              }, 2000);
            } else {
              showErrorMessage('Error submitting document: ' + data.error);
              console.log('ERROR: Document submission failed');
              console.log('Error data:', data);
            }
          })
          .catch(error => {
            // Reset button state
            this.disabled = false;
            this.innerHTML = 'Submit Document';
            
            showErrorMessage('Error: ' + error.message);
            console.error('Submission error:', error);
          });
        });
      } else {
        console.error('Submit button not found!');
      }
      
      // Office Search Functionality
      document.getElementById('officeSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        // Function to filter a select dropdown based on search term
        function filterOfficeDropdown(selectElement) {
          const options = selectElement.options;
          let visibleCount = 0;
          
          for (let i = 0; i < options.length; i++) {
            const option = options[i];
            // Always show the placeholder option
            if (option.value === '') {
              option.style.display = '';
              continue;
            }
            
            const text = option.text.toLowerCase();
            if (text.includes(searchTerm)) {
              option.style.display = '';
              visibleCount++;
            } else {
              option.style.display = 'none';
            }
          }
          
          return visibleCount;
        }
        
        // Filter all office dropdowns in the workflow
        const selects = document.querySelectorAll('#workflowBuilder select');
        selects.forEach(select => {
          filterOfficeDropdown(select);
        });
      });
    });
    
    // Add event listener for the Add Step button to ensure new steps get the filtered options
    document.addEventListener('DOMContentLoaded', function() {
      const addStepBtn = document.getElementById('addStep');
      if (addStepBtn) {
        const originalAddStepHandler = addStepBtn.onclick;
        
        addStepBtn.addEventListener('click', function() {
          // After adding a new step, apply the current search filter
          setTimeout(() => {
            const searchTerm = document.getElementById('officeSearch').value.toLowerCase();
            if (searchTerm) {
              const selects = document.querySelectorAll('#workflowBuilder select');
              const lastSelect = selects[selects.length - 1];
              
              const options = lastSelect.options;
              for (let i = 0; i < options.length; i++) {
                const option = options[i];
                if (option.value === '') {
                  option.style.display = '';
                  continue;
                }
                
                const text = option.text.toLowerCase();
                if (text.includes(searchTerm)) {
                  option.style.display = '';
                } else {
                  option.style.display = 'none';
                }
              }
            }
          }, 100);
        });
      }
    });
  </script>
  
  <script>
    // Notification system
    function showNotification(message, type = 'info', duration = 5000) {
      const container = document.getElementById('notificationContainer');
      
      // Create notification element
      const notification = document.createElement('div');
      notification.className = `notification notification-${type}`;
      
      // Create close button
      const closeBtn = document.createElement('span');
      closeBtn.className = 'notification-close';
      closeBtn.innerHTML = '&times;';
      closeBtn.onclick = function() {
        removeNotification(notification);
      };
      
      // Create message element
      const messageEl = document.createElement('div');
      messageEl.innerHTML = message;
      
      // Append elements
      notification.appendChild(closeBtn);
      notification.appendChild(messageEl);
      container.appendChild(notification);
      
      // Auto-remove after duration
      if (duration > 0) {
        setTimeout(() => {
          removeNotification(notification);
        }, duration);
      }
      
      return notification;
    }
    
    function removeNotification(notification) {
      notification.style.animation = 'fadeOut 0.3s ease-out forwards';
      setTimeout(() => {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      }, 300);
    }

    function showSuccessMessage(message) {
      showNotification(message, 'success');
    }

    function showErrorMessage(message) {
      showNotification(message, 'error');
    }

    function showWarningMessage(message) {
      showNotification(message, 'warning');
    }

    function showInfoMessage(message) {
      showNotification(message, 'info');
    }
  </script>
  
  <script>
    // Form submission handling
    function setupFormSubmission() {
      console.log('Setting up form submission handlers');
      const submitBtn = document.getElementById('submitBtn');
      const saveDraftBtn = document.getElementById('saveDraftBtn');
      const discardBtn = document.getElementById('discardBtn');
      const documentForm = document.getElementById('documentForm');
      
      // Debug: Log if elements were found
      console.log('Submit button found:', !!submitBtn);
      console.log('Save draft button found:', !!saveDraftBtn);
      console.log('Discard button found:', !!discardBtn);
      console.log('Document form found:', !!documentForm);
      
      // Submit document
      if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
          e.preventDefault(); // Prevent default form submission
          
          // Show a notification that we're starting the submission process
          showNotification('Starting form submission process...', 'info');
          
          // Validate form
          if (!validateForm()) {
            showErrorMessage('Form validation failed');
            return;
          }
          
          // Make sure we have a Google Doc ID
          if (!currentDocumentId) {
            showErrorMessage('No active Google Doc. Please refresh the page and try again.');
            return;
          }
          
          // Log to console for debugging
          console.log('Submit button clicked, validation passed, proceeding with submission');
          
          // Show loading state
          submitBtn.disabled = true;
          submitBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Submitting...
          `;
          
          // Collect form data
          const formData = new FormData();
          formData.append('title', document.getElementById('docTitle').value.trim());
          formData.append('type_id', document.getElementById('documentType').value);
          formData.append('status', 'pending');
          formData.append('google_doc_id', currentDocumentId);
          
          // Attachment removed
          
          // Add workflow data
          const workflow = collectWorkflowData();
          
          if (workflow.length === 0) {
            showErrorMessage('Please add at least one workflow step');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Submit Document';
            return;
          }
          
          console.log('Workflow data:', workflow);
          
          // Add workflow offices and roles
          workflow.forEach((step, index) => {
            if (step.recipient.type === 'office') {
              formData.append('workflow_offices[]', step.recipient.id);
              formData.append('workflow_roles[]', '');
              formData.append('recipient_types[]', 'office');
            } else if (step.recipient.type === 'person') {
              // Get the office ID for this user
              const userSelect = document.querySelector(`.workflow-step:nth-child(${index + 1}) .user-select`);
              const officeId = userSelect.options[userSelect.selectedIndex].getAttribute('data-office');
              
              formData.append('workflow_offices[]', officeId);
              formData.append('workflow_roles[]', step.recipient.id);
              formData.append('recipient_types[]', 'person');
            }
          });
          
          // Debug: Log all form data
          for (const pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
          }
          
          // Submit the form to the correct API endpoint
          fetch('../api/submit_document_clean.php', {
            method: 'POST',
            body: formData
          })
          .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            // Check if the response is JSON
            const contentType = response.headers.get('content-type');
            console.log('Content-Type:', contentType);
            
            if (contentType && contentType.includes('application/json')) {
              return response.json();
            } else {
              // If not JSON, get the text and log it for debugging
              return response.text().then(text => {
                console.error('Non-JSON response received:');
                console.error('Response text:', text);
                console.error('Response length:', text.length);
                throw new Error('Unexpected response format from server. Check console for details.');
              });
            }
          })
          .then(data => {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Submit Document';
            
            if (data.success) {
              showSuccessMessage('Document submitted successfully');
              showNotification('Document submitted successfully! Check console for details.', 'success');
              // Don't redirect automatically - let user see the console
              console.log('SUCCESS: Document submitted successfully');
              console.log('Response data:', data);
            } else {
              showErrorMessage('Error submitting document: ' + data.error);
              console.log('ERROR: Document submission failed');
              console.log('Error data:', data);
            }
          })
          .catch(error => {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Submit Document';
            
            showErrorMessage('Error: ' + error.message);
            console.error('Submission error:', error);
          });
        });
      }
      
      // Save draft
      if (saveDraftBtn) {
        saveDraftBtn.addEventListener('click', function(e) {
          e.preventDefault(); // Prevent default form submission
          // Make sure we have a Google Doc ID
          if (!currentDocumentId) {
            showErrorMessage('No active Google Doc. Please refresh the page and try again.');
            return;
          }
          
          // Show loading state
          saveDraftBtn.disabled = true;
          saveDraftBtn.innerHTML = `
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Saving...
          `;
          
          // Collect form data
          const formData = new FormData();
          formData.append('title', document.getElementById('docTitle').value.trim());
          formData.append('type_id', document.getElementById('documentType').value);
          formData.append('status', 'draft');
          formData.append('google_doc_id', currentDocumentId);
          
          // Add file attachment if present
          const fileInput = document.getElementById('docFile');
          if (fileInput && fileInput.files.length > 0) {
            formData.append('attachment', fileInput.files[0]);
          }
          
          // Add workflow data
          const workflow = collectWorkflowData();
          
          // Add workflow offices and roles
          workflow.forEach((step, index) => {
            if (step.recipient.type === 'office') {
              formData.append('workflow_offices[]', step.recipient.id);
              formData.append('workflow_roles[]', '');
              formData.append('recipient_types[]', 'office');
            } else if (step.recipient.type === 'person') {
              // Get the office ID for this user
              const userSelect = document.querySelector(`.workflow-step:nth-child(${index + 1}) .user-select`);
              const officeId = userSelect.options[userSelect.selectedIndex].getAttribute('data-office');
              
              formData.append('workflow_offices[]', officeId);
              formData.append('workflow_roles[]', step.recipient.id);
              formData.append('recipient_types[]', 'person');
            }
          });
          
          // Submit the form to the correct API endpoint
          fetch('../api/submit_document_clean.php', {
            method: 'POST',
            body: formData
          })
          .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            // Check if the response is JSON
            const contentType = response.headers.get('content-type');
            console.log('Content-Type:', contentType);
            
            if (contentType && contentType.includes('application/json')) {
              return response.json();
            } else {
              // If not JSON, get the text and log it for debugging
              return response.text().then(text => {
                console.error('Non-JSON response received:');
                console.error('Response text:', text);
                console.error('Response length:', text.length);
                throw new Error('Unexpected response format from server. Check console for details.');
              });
            }
          })
          .then(data => {
            // Reset button state
            saveDraftBtn.disabled = false;
            saveDraftBtn.innerHTML = 'Save as Draft';
            
            if (data.success) {
              showSuccessMessage('Draft saved successfully');
              console.log('SUCCESS: Draft saved successfully');
              console.log('Response data:', data);
            } else {
              showErrorMessage('Error saving draft: ' + data.error);
              console.log('ERROR: Draft save failed');
              console.log('Error data:', data);
            }
          })
          .catch(error => {
            // Reset button state
            saveDraftBtn.disabled = false;
            saveDraftBtn.innerHTML = 'Save as Draft';
            
            console.error('Save draft error:', error);
            showErrorMessage('Error saving draft: ' + error.message);
          });
        });
      }
      
      // Discard document
      if (discardBtn) {
        discardBtn.addEventListener('click', function() {
          if (confirm('Are you sure you want to discard this document? All changes will be lost.')) {
            window.location.href = 'dashboard.php';
          }
        });
      }
    }
    
    // Validate the form
    function validateForm() {
      const title = document.getElementById('docTitle').value.trim();
      const type = document.getElementById('documentType').value;
      
      if (!title) {
        showErrorMessage('Please enter a document title');
        return false;
      }
      
      if (!type) {
        showErrorMessage('Please select a document type');
        return false;
      }
      
      if (!currentDocumentId) {
        showErrorMessage('No active Google Doc. Please refresh the page and try again.');
        return false;
      }
      
      // Check if at least one workflow step is defined
      const workflowSteps = document.querySelectorAll('.workflow-step');
      if (workflowSteps.length === 0) {
        showErrorMessage('Please add at least one workflow step');
        return false;
      }
      
      let hasValidStep = false;
      
      // First check for hidden office inputs (used for Memorandum)
      const hiddenOfficeInputs = document.querySelectorAll('input[name="workflow_offices[]"]');
      if (hiddenOfficeInputs.length > 0) {
        // If we have hidden office inputs, we have valid steps
        hasValidStep = true;
      } else {
        // Otherwise check the regular workflow steps
        for (const step of workflowSteps) {
          const recipientTypeRadios = step.querySelectorAll('input[name^="recipient_type_"]');
          let selectedRadio = null;
          
          for (const radio of recipientTypeRadios) {
            if (radio.checked) {
              selectedRadio = radio;
              break;
            }
          }
          
          if (!selectedRadio) {
            continue;
          }
          
          const recipientType = selectedRadio.value;
          
          if (recipientType === 'office') {
            const officeSelect = step.querySelector('.office-select');
            if (officeSelect && officeSelect.value) {
              hasValidStep = true;
              break;
            }
          } else if (recipientType === 'person') {
            const userSelect = step.querySelector('.user-select');
            if (userSelect && userSelect.value) {
            hasValidStep = true;
            break;
          }
        }
      }
      
      if (!hasValidStep) {
        showErrorMessage('Please define at least one valid workflow step');
        return false;
      }
      
      return true;
    }
    
    // Collect workflow data
    function collectWorkflowData() {
      const workflowSteps = document.querySelectorAll('.workflow-step');
      const workflow = [];
      
      workflowSteps.forEach((step, index) => {
        const recipientType = step.querySelector('input[type="radio"]:checked').value;
        let recipient = null;
        
        if (recipientType === 'office') {
          const officeSelect = step.querySelector('.office-select');
          if (officeSelect && officeSelect.value) {
            recipient = {
              type: 'office',
              id: officeSelect.value,
              name: officeSelect.options[officeSelect.selectedIndex].text
            };
          }
        } else if (recipientType === 'person') {
          const userSelect = step.querySelector('.user-select');
          if (userSelect && userSelect.value) {
            recipient = {
              type: 'person',
              id: userSelect.value,
              name: userSelect.options[userSelect.selectedIndex].text
            };
          }
        }
        
        if (recipient) {
          workflow.push({
            step: index + 1,
            recipient: recipient
          });
        }
      });
      
      return workflow;
    }
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize variables for workflow steps
      let workflowStepCounter = 1; // Start at 1 since we already have step 1
      const workflowBuilder = document.getElementById('workflowBuilder');
      const addStepBtn = document.getElementById('addStep');
      
      // Function to add a new workflow step
      function addWorkflowStep() {
        workflowStepCounter++;
        
        const newStep = document.createElement('div');
        newStep.className = 'workflow-step flex flex-col gap-2';
        
        newStep.innerHTML = `
          <div class="flex items-center gap-4">
            <span class="step-number w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center">${workflowStepCounter}</span>
            <div class="flex-1">
              <div class="flex items-center gap-4 mb-2">
                <label class="inline-flex items-center">
                  <input type="radio" name="recipient_type_${workflowStepCounter}" value="office" class="recipient-type" checked>
                  <span class="ml-2">Office</span>
                </label>
                <label class="inline-flex items-center">
                  <input type="radio" name="recipient_type_${workflowStepCounter}" value="person" class="recipient-type">
                  <span class="ml-2">Specific Person</span>
                </label>
              </div>
              <div class="office-select-container">
                <select name="workflow_offices[]" required class="office-select flex-1 w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                  <option value="">Select Office</option>
                  <?php
                  $offices = $conn->query("SELECT * FROM offices ORDER BY office_name");
                  while ($office = $offices->fetch_assoc()) {
                    echo "<option value='" . $office['office_id'] . "'>" . $office['office_name'] . "</option>";
                  }
                  ?>
                </select>
                <input type="hidden" name="workflow_roles[]" value="">
                <input type="hidden" name="recipient_types[]" value="office">
              </div>
              <div class="user-select-container hidden">
                <div class="grid grid-cols-1 gap-2">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Office First</label>
                    <select class="filter-office w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                      <option value="">Select Office</option>
                      <?php
                      $offices = $conn->query("SELECT * FROM offices ORDER BY office_name");
                      while ($office = $offices->fetch_assoc()) {
                        echo "<option value='" . $office['office_id'] . "'>" . $office['office_name'] . "</option>";
                      }
                      ?>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Person</label>
                    <select name="user_workflow[]" class="user-select w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                      <option value="">Select Person</option>
                      <?php
                      // Use the same logic as above for consistency
                      $name_field = in_array('full_name', $user_columns) ? 'full_name' : 'username';
                      $users = $conn->query("SELECT u.user_id, u.$name_field, u.office_id FROM users u ORDER BY u.$name_field");
                      while ($user = $users->fetch_assoc()) {
                        $display_name = $user[$name_field];
                        echo "<option value='" . $user['user_id'] . "' data-office='" . $user['office_id'] . "'>" . htmlspecialchars($display_name) . "</option>";
                      }
                      ?>
                    </select>
                  </div>
                </div>
              </div>
            </div>
            <button type="button" class="remove-step px-2 py-1 text-red-600 hover:bg-red-50 rounded">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        `;
        
        workflowBuilder.appendChild(newStep);
        setupStepEventListeners(newStep);
        
        // Apply any existing search filter
        const searchTerm = document.getElementById('officeSearch').value.toLowerCase();
        if (searchTerm) {
          filterOfficeDropdown(newStep.querySelector('.office-select'), searchTerm);
          filterOfficeDropdown(newStep.querySelector('.filter-office'), searchTerm);
        }
        
        return newStep;
      }
      
      // Set up event listeners for the Add Step button
      if (addStepBtn) {
        addStepBtn.addEventListener('click', function() {
          addWorkflowStep();
        });
      }
      
      // Set up event listeners for the first step
      setupStepEventListeners(document.querySelector('.workflow-step'));
      
      // Function to set up event listeners for a workflow step
      function setupStepEventListeners(stepElement) {
        // Set up recipient type radio buttons
        const recipientTypeRadios = stepElement.querySelectorAll('.recipient-type');
        const officeSelectContainer = stepElement.querySelector('.office-select-container');
        const userSelectContainer = stepElement.querySelector('.user-select-container');
        
        recipientTypeRadios.forEach(radio => {
          radio.addEventListener('change', function() {
            if (this.value === 'office') {
              officeSelectContainer.style.display = 'block';
              userSelectContainer.style.display = 'none';
            } else if (this.value === 'person') {
              officeSelectContainer.style.display = 'block';
              userSelectContainer.style.display = 'block';
            }
          });
        });
        
        // Set up office filter for user selection
        const filterOffice = stepElement.querySelector('.filter-office');
        const userSelect = stepElement.querySelector('.user-select');
        
        if (filterOffice && userSelect) {
          filterOffice.addEventListener('change', function() {
            const officeId = this.value;
            const options = userSelect.options;
            
            // Reset user selection
            userSelect.value = '';
            
            // Show/hide users based on office
            for (let i = 0; i < options.length; i++) {
              const option = options[i];
              if (option.value === '') {
                option.style.display = '';
                continue;
              }
              
              const userOfficeId = option.getAttribute('data-office');
              if (!officeId || userOfficeId === officeId) {
                option.style.display = '';
              } else {
                option.style.display = 'none';
              }
            }
          });
        }
        
        // Set up remove step button
        const removeBtn = stepElement.querySelector('.remove-step');
        if (removeBtn) {
          removeBtn.addEventListener('click', function() {
            stepElement.remove();
            
            // Update step numbers
            const steps = document.querySelectorAll('.workflow-step');
            steps.forEach((step, index) => {
              step.querySelector('.step-number').textContent = index + 1;
            });
          });
          
          // Only show remove button if there's more than one step
          const updateRemoveButtonVisibility = function() {
            const steps = document.querySelectorAll('.workflow-step');
            if (steps.length <= 1) {
              removeBtn.style.display = 'none';
            } else {
              removeBtn.style.display = 'block';
            }
          };
          
          updateRemoveButtonVisibility();
          
          // Set up a mutation observer to watch for changes in the workflow builder
          const observer = new MutationObserver(updateRemoveButtonVisibility);
          observer.observe(workflowBuilder, { childList: true });
        }
      }
      
      // Function to filter office dropdowns based on search term
      function filterOfficeDropdown(selectElement, searchTerm) {
        if (!selectElement) return 0;
        
        const options = selectElement.options;
        let visibleCount = 0;
        
        for (let i = 0; i < options.length; i++) {
          const option = options[i];
          // Always show the placeholder option
          if (option.value === '') {
            option.style.display = '';
            continue;
          }
          
          const text = option.text.toLowerCase();
          if (text.includes(searchTerm)) {
            option.style.display = '';
            visibleCount++;
          } else {
            option.style.display = 'none';
          }
        }
        
        return visibleCount;
      }
      
      // Set up office search functionality
      const officeSearch = document.getElementById('officeSearch');
      if (officeSearch) {
        officeSearch.addEventListener('input', function() {
          const searchTerm = this.value.toLowerCase();
          
          // Filter all office dropdowns in the workflow
          const officeSelects = document.querySelectorAll('.office-select');
          officeSelects.forEach(select => {
            filterOfficeDropdown(select, searchTerm);
          });
          
          const filterOffices = document.querySelectorAll('.filter-office');
          filterOffices.forEach(select => {
            filterOfficeDropdown(select, searchTerm);
          });
        });
      }
      
      // Function to check if this is a memorandum sent to all offices
      window.checkIfMemorandumToAll = function() {
        // Check if document title contains "Memorandum" or "Memo"
        const title = document.getElementById('docTitle').value.toLowerCase();
        const isMemorandum = title.includes('memorandum') || title.includes('memo');
        
        // Check if we have multiple workflow steps with view-only flag
        const viewOnlyInputs = document.querySelectorAll('input[name="view_only[]"]');
        const hasViewOnlySteps = viewOnlyInputs.length > 0;
        
        // Check if we have multiple offices in the workflow
        const hiddenOfficeInputs = document.querySelectorAll('input[name="workflow_offices[]"]');
        const hasMultipleOffices = hiddenOfficeInputs.length > 1;
        
        console.log('Memorandum check:', {
          isMemorandum,
          hasViewOnlySteps,
          hasMultipleOffices,
          title
        });
        
        return isMemorandum && hasViewOnlySteps && hasMultipleOffices;
      };
      
      // Function to collect workflow data for form submission
      window.collectWorkflowData = function() {
        const workflow = [];
        
        // First check for hidden office inputs (used for Memorandum)
        const hiddenOfficeInputs = document.querySelectorAll('input[name="workflow_offices[]"]');
        if (hiddenOfficeInputs.length > 0) {
          console.log('Found hidden office inputs for memorandum:', hiddenOfficeInputs.length);
          hiddenOfficeInputs.forEach((input, index) => {
            if (input.value) {
              // Get the office name from the parent element
              const stepElement = input.closest('.workflow-step');
              const officeNameElement = stepElement ? stepElement.querySelector('span.font-medium') : null;
              const officeName = officeNameElement ? officeNameElement.textContent : `Office ${input.value}`;
              
              workflow.push({
                step: index + 1,
                recipient: {
                  type: 'office',
                  id: input.value,
                  name: officeName
                }
              });
            }
          });
          return workflow;
        }
        
        // Otherwise, check regular workflow steps
        const workflowSteps = document.querySelectorAll('.workflow-step');
        console.log('Found regular workflow steps:', workflowSteps.length);
        
        workflowSteps.forEach((step, index) => {
          const checkedRadio = step.querySelector('input[type="radio"]:checked');
          if (!checkedRadio) return; // Skip if no radio is checked
          
          const recipientType = checkedRadio.value;
          let recipient = null;
          
          if (recipientType === 'office') {
            const officeSelect = step.querySelector('.office-select');
            if (officeSelect && officeSelect.value) {
              recipient = {
                type: 'office',
                id: officeSelect.value,
                name: officeSelect.options[officeSelect.selectedIndex].text
              };
            }
          } else if (recipientType === 'person') {
            const userSelect = step.querySelector('.user-select');
            if (userSelect && userSelect.value) {
              recipient = {
                type: 'person',
                id: userSelect.value,
                name: userSelect.options[userSelect.selectedIndex].text
              };
            }
          }
          
          if (recipient) {
            workflow.push({
              step: index + 1,
              recipient: recipient
            });
          }
        });
        
        return workflow;
      };
      
      // Add the missing setupFormSubmission function
      window.setupFormSubmission = function() {
        console.log('setupFormSubmission called - this is a placeholder function');
      };
    });
  </script>
</body>
</html>

