<?php
/**
 * Google Authentication Callback
 * 
 * This file handles the OAuth callback from Google and stores the authentication token.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'includes/google_auth_handler.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to user
ini_set('log_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('Google Auth Callback: User not logged in');
    header('Location: pages/login.php');
    exit();
}

$userId = $_SESSION['user_id'];
error_log("Google Auth Callback: Processing for user ID: $userId");

// Check if there's an error in the callback
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    error_log("Google Auth Callback: Error received: $error");
    $_SESSION['google_auth_error'] = 'Authentication error: ' . $error;
    
    // Redirect to the return URL if available, or to the connect page
    if (isset($_SESSION['google_auth_return_url'])) {
        $returnUrl = $_SESSION['google_auth_return_url'];
        unset($_SESSION['google_auth_return_url']);
        header('Location: ' . $returnUrl . '?error=' . urlencode('Authentication error: ' . $error));
    } else {
        header('Location: pages/google_docs_connect.php');
    }
    exit();
}

// Check if we have an authorization code
if (!isset($_GET['code'])) {
    error_log('Google Auth Callback: No authorization code received');
    $_SESSION['google_auth_error'] = 'No authorization code received';
    
    // Redirect to the return URL if available, or to the connect page
    if (isset($_SESSION['google_auth_return_url'])) {
        $returnUrl = $_SESSION['google_auth_return_url'];
        unset($_SESSION['google_auth_return_url']);
        header('Location: ' . $returnUrl . '?error=' . urlencode('No authorization code received'));
    } else {
        header('Location: pages/google_docs_connect.php');
    }
    exit();
}

$authCode = $_GET['code'];
error_log('Google Auth Callback: Authorization code received: ' . substr($authCode, 0, 10) . '...');

try {
    // Initialize the Google Auth Handler
    $authHandler = new GoogleAuthHandler();
    $client = $authHandler->getClient();
    
    // Exchange authorization code for access token
    error_log('Google Auth Callback: Exchanging authorization code for access token');
    $token = $authHandler->fetchAccessTokenWithAuthCode($authCode);
    
    // Log token details (without sensitive info)
    if (is_array($token)) {
        $tokenInfo = $token;
        if (isset($tokenInfo['access_token'])) {
            $tokenInfo['access_token'] = substr($tokenInfo['access_token'], 0, 10) . '...';
        }
        if (isset($tokenInfo['refresh_token'])) {
            $tokenInfo['refresh_token'] = substr($tokenInfo['refresh_token'], 0, 10) . '...';
        }
        error_log('Google Auth Callback: Token received: ' . json_encode($tokenInfo));
    }
    
    // Check for errors
    if (isset($token['error'])) {
        $errorMessage = $token['error_description'] ?? $token['error'];
        error_log("Google Auth Callback: Token error: $errorMessage");
        $_SESSION['google_auth_error'] = $errorMessage;
        
        // Redirect to the return URL if available, or to the connect page
        if (isset($_SESSION['google_auth_return_url'])) {
            $returnUrl = $_SESSION['google_auth_return_url'];
            unset($_SESSION['google_auth_return_url']);
            header('Location: ' . $returnUrl . '?error=' . urlencode($errorMessage));
        } else {
            header('Location: pages/google_docs_connect.php');
        }
        exit();
    }
    
    // Save the token
    error_log('Google Auth Callback: Saving token to database');
    $saveResult = $authHandler->saveToken($userId, $token);
    
    if (!$saveResult) {
        error_log('Google Auth Callback: Failed to save token to database');
        $_SESSION['google_auth_error'] = 'Failed to save authentication token';
        
        // Redirect to the return URL if available, or to the connect page
        if (isset($_SESSION['google_auth_return_url'])) {
            $returnUrl = $_SESSION['google_auth_return_url'];
            unset($_SESSION['google_auth_return_url']);
            header('Location: ' . $returnUrl . '?error=' . urlencode('Failed to save authentication token'));
        } else {
            header('Location: pages/google_docs_connect.php');
        }
        exit();
    }
    
    // Verify the token works by making a test API call
    try {
        $client->setAccessToken($token);
        $oauth2 = new \Google\Service\Oauth2($client);
        $userInfo = $oauth2->userinfo->get();
        error_log('Google Auth Callback: Successfully verified token with API call');
    } catch (\Exception $e) {
        error_log('Google Auth Callback: Token verification failed: ' . $e->getMessage());
        $_SESSION['google_auth_error'] = 'Token verification failed: ' . $e->getMessage();
        
        // Redirect to the return URL if available, or to the connect page
        if (isset($_SESSION['google_auth_return_url'])) {
            $returnUrl = $_SESSION['google_auth_return_url'];
            unset($_SESSION['google_auth_return_url']);
            header('Location: ' . $returnUrl . '?error=' . urlencode('Token verification failed: ' . $e->getMessage()));
        } else {
            header('Location: pages/google_docs_connect.php');
        }
        exit();
    }
    
    // Set success message
    error_log('Google Auth Callback: Authentication successful');
    $_SESSION['google_auth_success'] = 'Successfully connected to Google Docs';
    
    // Check if there's a return URL in the session
    if (isset($_SESSION['google_auth_return_url'])) {
        $returnUrl = $_SESSION['google_auth_return_url'];
        unset($_SESSION['google_auth_return_url']);
        error_log("Google Auth Callback: Redirecting to return URL: $returnUrl");
        header('Location: ' . $returnUrl . '?success=1');
    } else {
        // Default redirect to the connection page
        error_log('Google Auth Callback: Redirecting to compose page');
        header('Location: pages/compose.php');
    }
    exit();
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    error_log("Google Auth Callback: Exception: $errorMessage");
    $_SESSION['google_auth_error'] = 'Authentication error: ' . $errorMessage;
    
    // Redirect to the return URL if available, or to the connect page
    if (isset($_SESSION['google_auth_return_url'])) {
        $returnUrl = $_SESSION['google_auth_return_url'];
        unset($_SESSION['google_auth_return_url']);
        header('Location: ' . $returnUrl . '?error=' . urlencode('Authentication error: ' . $errorMessage));
    } else {
        header('Location: pages/google_docs_connect.php');
    }
    exit();
}
