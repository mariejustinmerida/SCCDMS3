<?php
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

$userId = $_SESSION['user_id'];

// Check if the user is connected to Google Docs
$authHandler = new GoogleAuthHandler();
$isConnected = $authHandler->hasValidToken($userId);

// If not connected, redirect to the connection page
if (!$isConnected) {
    $_SESSION['google_auth_error'] = 'You need to connect to Google Docs before using this feature';
    header('Location: google_docs_connect.php');
    exit();
}

// Create a new Google Doc directly for testing
if (isset($_GET['create']) && $_GET['create'] == 1) {
    require_once '../includes/google_docs_handler.php';
    
    // Initialize Google Docs handler
    $client = $authHandler->getClient();
    $token = $authHandler->loadToken($userId);
    $client->setAccessToken($token);
    $docsHandler = new GoogleDocsHandler($client);
    
    // Create a test document
    $docInfo = $docsHandler->createDocument("Test Document " . date('Y-m-d H:i:s'));
    
    if ($docInfo) {
        $googleDocId = $docInfo['id'];
        $googleDocUrl = $docInfo['edit_url'];
        
        // Store the document info in session for display
        $_SESSION['test_doc_id'] = $googleDocId;
        $_SESSION['test_doc_url'] = $googleDocUrl;
        
        // Redirect to remove the create parameter
        header('Location: google_docs_test.php');
        exit();
    } else {
        $_SESSION['error_message'] = "Error creating Google Doc";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Google Docs Test - SCC DMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
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
      <h1 class="text-2xl font-bold">Google Docs Test</h1>
      <div class="flex items-center text-sm text-gray-500">
        <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
        <span class="mx-2">/</span>
        <span>Google Docs Test</span>
      </div>
    </div>
    
    <?php if (isset($_SESSION['error_message'])): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
      </div>
      <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
      <h2 class="text-xl font-semibold mb-4">Google Docs Integration Test</h2>
      
      <p class="mb-4 text-gray-600">
        This page tests the Google Docs integration. You can create a test document and view it embedded below.
      </p>
      
      <div class="mb-6">
        <a href="?create=1" class="inline-block px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
          Create Test Document
        </a>
      </div>
      
      <?php if (isset($_SESSION['test_doc_id']) && isset($_SESSION['test_doc_url'])): ?>
        <div class="mb-4">
          <h3 class="font-medium mb-2">Test Document Created</h3>
          <p class="text-gray-600 mb-2">
            Document ID: <?php echo $_SESSION['test_doc_id']; ?><br>
            <a href="<?php echo $_SESSION['test_doc_url']; ?>" target="_blank" class="text-blue-600 hover:underline">
              Open in Google Docs
            </a>
          </p>
        </div>
        
        <div class="google-docs-container">
          <iframe src="https://docs.google.com/document/d/<?php echo $_SESSION['test_doc_id']; ?>/edit?embedded=true" 
                  class="google-docs-iframe"
                  allow="autoplay"
                  allowfullscreen="true"></iframe>
        </div>
      <?php else: ?>
        <div class="bg-gray-100 p-4 rounded-lg">
          <p class="text-gray-600">
            Click "Create Test Document" to create a new Google Doc and test the integration.
          </p>
        </div>
      <?php endif; ?>
    </div>
    
    <div class="mt-6">
      <a href="dashboard.php?page=documents" class="text-green-600 hover:text-green-700">
        &larr; Back to Documents
      </a>
    </div>
  </div>
</body>
</html>
