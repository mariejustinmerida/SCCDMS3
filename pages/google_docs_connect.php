<?php
/**
 * Google Docs Connection Page
 * 
 * This page handles the connection to Google Docs and displays the connection status.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
require_once '../includes/google_auth_handler.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$authHandler = new GoogleAuthHandler();
$isConnected = $authHandler->hasValidToken($userId);

// Handle connection request
if (isset($_GET['connect']) && $_GET['connect'] == 1) {
    // Store return URL if provided
    if (isset($_GET['return'])) {
        $_SESSION['google_auth_return_url'] = $_GET['return'];
        error_log("Setting return URL to: {$_GET['return']}");
    }
    
    // Generate auth URL and redirect
    try {
        $client = $authHandler->getClient();
        $authUrl = $client->createAuthUrl();
        error_log("Redirecting to Google auth URL: $authUrl");
        header('Location: ' . $authUrl);
        exit();
    } catch (Exception $e) {
        error_log("Error generating auth URL: " . $e->getMessage());
        $_SESSION['google_auth_error'] = 'Failed to generate authentication URL: ' . $e->getMessage();
    }
}

// Handle disconnect request
if (isset($_GET['disconnect']) && $_GET['disconnect'] == 1) {
    try {
        $result = $authHandler->revokeToken($userId);
        if ($result) {
            error_log("Successfully revoked token for user ID: $userId");
            $_SESSION['google_auth_success'] = 'Successfully disconnected from Google Docs';
        } else {
            error_log("Failed to revoke token for user ID: $userId");
            $_SESSION['google_auth_error'] = 'Failed to disconnect from Google Docs';
        }
    } catch (Exception $e) {
        error_log("Error revoking token: " . $e->getMessage());
        $_SESSION['google_auth_error'] = 'Error disconnecting from Google Docs: ' . $e->getMessage();
    }
    
    // Redirect to this page to refresh connection status
    header('Location: google_docs_connect.php');
    exit();
}

// Check for success or error messages
$successMessage = isset($_SESSION['google_auth_success']) ? $_SESSION['google_auth_success'] : '';
$errorMessage = isset($_SESSION['google_auth_error']) ? $_SESSION['google_auth_error'] : '';

// Clear session messages
unset($_SESSION['google_auth_success']);
unset($_SESSION['google_auth_error']);

// Get page title
$pageTitle = 'Google Docs Connection';

// Include header
include '../includes/header.php';
?>

<!DOCTYPE html>
<html>
<head>
  <title>Connect to Google Docs - SCC DMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50">
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold">Connect to Google Docs</h1>
      <div class="flex items-center text-sm text-gray-500">
        <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
        <span class="mx-2">/</span>
        <span>Google Docs Connection</span>
      </div>
    </div>
    
    <?php if ($successMessage): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline"><?php echo $successMessage; ?></span>
      </div>
    <?php endif; ?>
    
    <?php if ($errorMessage): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline"><?php echo $errorMessage; ?></span>
        <p class="text-sm mt-2">If you're experiencing authentication issues, please try the following:</p>
        <ul class="list-disc ml-5 text-sm mt-1">
          <li>Check that you've enabled the Google Drive API and Google Docs API in your Google Cloud Console</li>
          <li>Verify that the redirect URI in your Google Cloud Console matches the one in your application</li>
          <li>Make sure you're using the correct client ID and client secret</li>
        </ul>
      </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-sm p-6">
      <h2 class="text-xl font-semibold mb-4">Google Docs Integration</h2>
      
      <p class="mb-4 text-gray-600">
        Connecting your SCCDMS account with Google Docs allows you to create and edit documents using Google's powerful document editor.
        This integration provides more features and a better editing experience compared to the built-in editor.
      </p>
      
      <div class="bg-gray-50 p-4 rounded-lg mb-6">
        <h3 class="font-medium mb-2">Benefits of Google Docs Integration:</h3>
        <ul class="list-disc pl-5 space-y-1 text-gray-600">
          <li>Rich text formatting with more options</li>
          <li>Real-time collaboration with other users</li>
          <li>Advanced features like comments, suggestions, and revision history</li>
          <li>Automatic saving of your work</li>
          <li>Access your documents from any device</li>
        </ul>
      </div>
      
      <div class="border-t pt-4 mt-4">
        <h3 class="font-medium mb-4">Connection Status</h3>
        
        <?php 
        // Re-check connection status to ensure it's up-to-date
        $isConnected = $authHandler->hasValidToken($userId);
        
        if ($isConnected): 
        ?>
          <div class="flex items-center mb-4 text-green-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Your account is connected to Google Docs</span>
          </div>
          
          <a href="?disconnect=1" class="inline-block px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
            Disconnect from Google Docs
          </a>
        <?php else: ?>
          <div class="flex items-center mb-4 text-red-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Your account is not connected to Google Docs</span>
          </div>
          
          <a href="?connect=1" class="inline-block px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
            Connect to Google Docs
          </a>
        <?php endif; ?>
      </div>
    </div>
    
    <div class="mt-6">
      <a href="dashboard.php?page=documents" class="text-green-600 hover:text-green-700">
        &larr; Back to Documents
      </a>
    </div>
  </div>
</body>
</html>

<?php include '../includes/footer.php'; ?>
