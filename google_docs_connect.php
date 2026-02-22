<?php
/**
 * Google Docs Connect Page
 * 
 * This page handles the Google authentication flow for connecting to Google Docs.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'includes/google_auth_handler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: pages/login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Store the return URL if provided
if (isset($_GET['return'])) {
    $_SESSION['google_auth_return_url'] = $_GET['return'];
} elseif (!isset($_SESSION['google_auth_return_url'])) {
    // Default return URL
    $_SESSION['google_auth_return_url'] = 'pages/google_docs_test_simple.php';
}

// Create the Google Auth Handler
$authHandler = new GoogleAuthHandler();

// Handle the connect request
if (isset($_GET['connect']) && $_GET['connect'] == 1) {
    // Generate the auth URL and redirect
    $client = $authHandler->getClient();
    $authUrl = $client->createAuthUrl();
    
    // Log the redirect URI for debugging
    error_log("Google Docs Connect: Redirecting to auth URL with redirect URI: " . GOOGLE_REDIRECT_URI);
    
    header('Location: ' . $authUrl);
    exit();
}

// Handle the disconnect request
if (isset($_GET['disconnect']) && $_GET['disconnect'] == 1) {
    // Revoke the token
    $authHandler->revokeToken($userId);
    
    // Set success message
    $_SESSION['google_auth_success'] = 'Successfully disconnected from Google Docs';
    
    // Redirect to return URL
    $returnUrl = isset($_SESSION['google_auth_return_url']) ? $_SESSION['google_auth_return_url'] : 'pages/google_docs_test_simple.php';
    header('Location: ' . $returnUrl);
    exit();
}

// Check if user is already connected
$isConnectedToGoogle = $authHandler->hasValidToken($userId);

// If already connected, redirect to the return URL
if ($isConnectedToGoogle) {
    $returnUrl = isset($_SESSION['google_auth_return_url']) ? $_SESSION['google_auth_return_url'] : 'pages/google_docs_test_simple.php';
    
    // Set success message
    $_SESSION['google_auth_success'] = 'Already connected to Google Docs';
    
    header('Location: ' . $returnUrl);
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Connect to Google Docs - SCC DMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-6">
    <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full">
        <div class="text-center mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-blue-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h1 class="text-2xl font-bold text-gray-800">Connect to Google Docs</h1>
            <p class="text-gray-600 mt-2">You need to connect to Google Docs to create and edit documents.</p>
        </div>
        
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        By connecting, you'll allow this application to create and edit Google Docs on your behalf.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="flex justify-center">
            <a href="?connect=1" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg flex items-center">
                <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 0C5.372 0 0 5.372 0 12C0 18.628 5.372 24 12 24C18.628 24 24 18.628 24 12C24 5.372 18.628 0 12 0ZM12 19.5C8.086 19.5 4.9 16.314 4.9 12.4C4.9 8.486 8.086 5.3 12 5.3C13.857 5.3 15.486 6.014 16.714 7.143L14.343 9.514C13.8 8.971 12.971 8.6 12 8.6C9.9 8.6 8.2 10.3 8.2 12.4C8.2 14.5 9.9 16.2 12 16.2C13.757 16.2 15.257 15 15.686 13.343H12V10.043H19.029C19.129 10.471 19.186 10.929 19.186 11.4C19.186 15.971 16.143 19.5 12 19.5Z"/>
                </svg>
                Connect with Google
            </a>
        </div>
        
        <div class="mt-6 text-center">
            <a href="<?php echo isset($_SESSION['google_auth_return_url']) ? $_SESSION['google_auth_return_url'] : 'pages/google_docs_test_simple.php'; ?>" class="text-gray-500 hover:text-gray-700">
                Cancel and go back
            </a>
        </div>
    </div>
</body>
</html>
