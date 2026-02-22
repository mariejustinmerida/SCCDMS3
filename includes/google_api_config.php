<?php
/**
 * Google API Configuration
 * 
 * This file contains the configuration settings for Google API integration.
 * After setting up your Google Cloud Project, update these values with your credentials.
 */

// Google API credentials (from environment; set in includes/.env or server config)
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');

// IMPORTANT: Redirect URI must match one of the Authorized redirect URIs in Google Cloud Console
// Uses env override if present; otherwise auto-selects based on host (localhost vs production)
$envRedirect = getenv('GOOGLE_REDIRECT_URI');
if (!empty($envRedirect)) {
    define('GOOGLE_REDIRECT_URI', $envRedirect);
} else {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (stripos($host, 'localhost') !== false || stripos($host, '127.0.0.1') !== false) {
        define('GOOGLE_REDIRECT_URI', 'http://localhost/SCCDMS2/google_auth_callback.php');
    } else {
        // Production: callback at the web root
        define('GOOGLE_REDIRECT_URI', 'https://' . $host . '/google_auth_callback.php');
    }
}

// Google API scopes required for the application
define('GOOGLE_SCOPES', [
    'https://www.googleapis.com/auth/drive.file',     // Access to files created or opened by the app
    'https://www.googleapis.com/auth/docs',           // Access to Google Docs
    'https://www.googleapis.com/auth/drive.metadata', // Access to file metadata
    'https://www.googleapis.com/auth/drive',          // Full access to Google Drive
    'https://www.googleapis.com/auth/userinfo.profile' // Basic profile information
]);

// Google API application name
define('GOOGLE_APPLICATION_NAME', 'SCCDMS2 Document System');

// Path to store access tokens
define('GOOGLE_TOKEN_PATH', __DIR__ . '/../storage/google_tokens/');

// Create token directory if it doesn't exist
if (!file_exists(GOOGLE_TOKEN_PATH)) {
    mkdir(GOOGLE_TOKEN_PATH, 0777, true);
}
