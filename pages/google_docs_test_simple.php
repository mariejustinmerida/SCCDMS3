<?php
/**
 * Google Docs Simple Test Page
 * 
 * This page tests the basic Google Docs integration.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
require_once '../includes/google_auth_handler.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$authHandler = new GoogleAuthHandler();

// Handle disconnect request
if (isset($_GET['disconnect']) && $_GET['disconnect'] == 1) {
    $authHandler->revokeToken($userId);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle reconnect request - force a new authentication
if (isset($_GET['reconnect']) && $_GET['reconnect'] == 1) {
    // Store return URL
    $_SESSION['google_auth_return_url'] = $_SERVER['PHP_SELF'];
    
    // Generate auth URL and redirect
    $client = $authHandler->getClient();
    $authUrl = $client->createAuthUrl();
    header('Location: ' . $authUrl);
    exit();
}

// Check if the user is connected to Google Docs
$isConnected = $authHandler->hasValidToken($userId);

// Get auth URL for connection
$authUrl = $authHandler->createAuthUrl();

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Docs Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-6">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-2xl font-bold mb-4">Google Docs Integration Test</h1>
        
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
        
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Connection Status</h2>
            
            <?php if ($isConnected): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <strong class="font-bold">Connected!</strong>
                    <span class="block sm:inline">You are connected to Google Docs.</span>
                </div>
                
                <div class="flex space-x-4 mb-4">
                    <a href="?disconnect=1" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 inline-block">Disconnect from Google Docs</a>
                    <a href="?reconnect=1" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 inline-block">Reconnect to Google Docs</a>
                </div>
                
                <div class="mt-6">
                    <h3 class="text-lg font-medium mb-2">Create a Test Document</h3>
                    <p class="text-gray-600 mb-4">Click the button below to create a test document in Google Docs.</p>
                    
                    <button id="createDocBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Create Test Document</button>
                    
                    <div id="createDocStatus" class="mt-4 hidden">
                        <div class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Creating document...</span>
                        </div>
                    </div>
                    
                    <div id="createDocResult" class="mt-4 hidden"></div>
                </div>
                
                <div class="mt-6">
                    <h3 class="text-lg font-medium mb-2">Debug Information</h3>
                    <div class="bg-gray-100 p-4 rounded-lg">
                        <pre id="debugInfo" class="text-xs overflow-auto max-h-60"></pre>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                    <strong class="font-bold">Not Connected!</strong>
                    <span class="block sm:inline">You need to connect to Google Docs to use this feature.</span>
                </div>
                
                <a href="<?php echo $authUrl; ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 inline-block">
                    Connect to Google Docs
                </a>
            <?php endif; ?>
        </div>
        
        <?php if ($isConnected): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Test Document</h2>
            
            <div class="mb-4">
                <button id="createTestDocBtn" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                    Create Test Document
                </button>
            </div>
            
            <div id="docContainer" class="border rounded-lg overflow-hidden" style="height: 500px; display: none;">
                <iframe id="docIframe" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
            
            <div id="errorContainer" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mt-4" style="display: none;">
                <strong class="font-bold">Error!</strong>
                <span id="errorMessage" class="block sm:inline"></span>
                <div id="errorDetails" class="mt-2 text-xs"></div>
                <div class="mt-4">
                    <a href="?reconnect=1" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 inline-block">Reconnect to Google Docs</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Debug info display
            const debugInfo = document.getElementById('debugInfo');
            if (debugInfo) {
                debugInfo.textContent = 'Debug information will appear here...';
            }
            
            // Create document button
            const createDocBtn = document.getElementById('createDocBtn');
            const createDocStatus = document.getElementById('createDocStatus');
            const createDocResult = document.getElementById('createDocResult');
            
            // Test document button
            const createTestDocBtn = document.getElementById('createTestDocBtn');
            const docContainer = document.getElementById('docContainer');
            const docIframe = document.getElementById('docIframe');
            const errorContainer = document.getElementById('errorContainer');
            const errorMessage = document.getElementById('errorMessage');
            const errorDetails = document.getElementById('errorDetails');
            
            // First check auth status
            if (debugInfo) {
                fetch('../api/google_docs_api.php?action=check_auth')
                .then(response => {
                    debugInfo.textContent += '\nAuth check response status: ' + response.status;
                    return response.text();
                })
                .then(text => {
                    debugInfo.textContent += '\nAuth check response: ' + text;
                    try {
                        const data = JSON.parse(text);
                        debugInfo.textContent += '\nAuth status: ' + (data.is_connected ? 'Connected' : 'Not connected');
                        
                        // If not connected, show a warning and suggestion to reconnect
                        if (!data.is_connected) {
                            debugInfo.textContent += '\nWARNING: Not connected to Google Docs. Try clicking the Reconnect button.';
                        }
                    } catch (e) {
                        debugInfo.textContent += '\nError parsing auth check response: ' + e.message;
                    }
                })
                .catch(error => {
                    debugInfo.textContent += '\nError checking auth status: ' + error.message;
                });
            }
            
            if (createDocBtn) {
                createDocBtn.addEventListener('click', function() {
                    // Show loading status
                    createDocStatus.classList.remove('hidden');
                    createDocResult.classList.add('hidden');
                    if (debugInfo) {
                        debugInfo.textContent = 'Sending request to create document...';
                    }
                    
                    // Call the API to create a document
                    fetch('../api/google_docs_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            'action': 'create_document',
                            'title': 'Test Document ' + new Date().toLocaleString()
                        })
                    })
                    .then(response => {
                        if (debugInfo) {
                            debugInfo.textContent += '\nResponse received. Status: ' + response.status;
                        }
                        return response.text();
                    })
                    .then(text => {
                        if (debugInfo) {
                            debugInfo.textContent += '\nResponse text: ' + text;
                        }
                        
                        try {
                            const data = JSON.parse(text);
                            if (debugInfo) {
                                debugInfo.textContent += '\nParsed JSON: ' + JSON.stringify(data, null, 2);
                            }
                            
                            // Hide loading status
                            createDocStatus.classList.add('hidden');
                            createDocResult.classList.remove('hidden');
                            
                            if (data.success) {
                                createDocResult.innerHTML = `
                                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                                        <strong class="font-bold">Success!</strong>
                                        <span class="block sm:inline">Document created successfully.</span>
                                        <div class="mt-2">
                                            <a href="${data.document.url}" target="_blank" class="text-blue-600 hover:underline">Open document in Google Docs</a>
                                        </div>
                                    </div>
                                `;
                            } else {
                                // Check if this is an authentication error
                                const isAuthError = data.auth_required || 
                                    (data.error && (
                                        data.error.includes('authentication') || 
                                        data.error.includes('auth') || 
                                        data.error.includes('token') || 
                                        data.error.includes('expired')
                                    ));
                                
                                createDocResult.innerHTML = `
                                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                                        <strong class="font-bold">Error!</strong>
                                        <span class="block sm:inline">${data.error}</span>
                                        ${isAuthError ? `
                                        <div class="mt-4">
                                            <a href="?reconnect=1" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 inline-block">Reconnect to Google Docs</a>
                                        </div>
                                        ` : ''}
                                    </div>
                                `;
                            }
                        } catch (e) {
                            if (debugInfo) {
                                debugInfo.textContent += '\nError parsing JSON: ' + e.message;
                            }
                            
                            // Hide loading status
                            createDocStatus.classList.add('hidden');
                            createDocResult.classList.remove('hidden');
                            
                            createDocResult.innerHTML = `
                                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                                    <strong class="font-bold">Error!</strong>
                                    <span class="block sm:inline">Failed to parse server response. See debug info for details.</span>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        if (debugInfo) {
                            debugInfo.textContent += '\nFetch error: ' + error.message;
                        }
                        
                        // Hide loading status
                        createDocStatus.classList.add('hidden');
                        createDocResult.classList.remove('hidden');
                        
                        createDocResult.innerHTML = `
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                                <strong class="font-bold">Error!</strong>
                                <span class="block sm:inline">Network error: ${error.message}</span>
                            </div>
                        `;
                    });
                });
            }
            
            if (createTestDocBtn) {
                createTestDocBtn.addEventListener('click', function() {
                    // Hide any previous errors
                    errorContainer.style.display = 'none';
                    docContainer.style.display = 'none';
                    
                    if (debugInfo) {
                        debugInfo.textContent = 'Creating test document...';
                    }
                    
                    // Call the API to create a document
                    fetch('../api/google_docs_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            'action': 'create_document',
                            'title': 'Simple Test Document ' + new Date().toLocaleString()
                        })
                    })
                    .then(response => {
                        if (debugInfo) {
                            debugInfo.textContent += '\nResponse status: ' + response.status;
                        }
                        return response.text();
                    })
                    .then(text => {
                        if (debugInfo) {
                            debugInfo.textContent += '\nResponse text: ' + text;
                        }
                        
                        try {
                            const data = JSON.parse(text);
                            
                            if (data.success) {
                                // Show document in iframe
                                docIframe.src = data.document.url;
                                docContainer.style.display = 'block';
                                
                                if (debugInfo) {
                                    debugInfo.textContent += '\nDocument created successfully. ID: ' + data.document.id;
                                }
                            } else {
                                // Show error
                                errorMessage.textContent = data.error || 'Unknown error';
                                
                                if (data.debug_info) {
                                    errorDetails.textContent = JSON.stringify(data.debug_info, null, 2);
                                }
                                
                                errorContainer.style.display = 'block';
                                
                                if (debugInfo) {
                                    debugInfo.textContent += '\nError: ' + (data.error || 'Unknown error');
                                    if (data.debug_info) {
                                        debugInfo.textContent += '\nDebug info: ' + JSON.stringify(data.debug_info, null, 2);
                                    }
                                }
                            }
                        } catch (e) {
                            // Show parsing error
                            errorMessage.textContent = 'Failed to parse server response: ' + e.message;
                            errorContainer.style.display = 'block';
                            
                            if (debugInfo) {
                                debugInfo.textContent += '\nError parsing JSON: ' + e.message;
                            }
                        }
                    })
                    .catch(error => {
                        // Show network error
                        errorMessage.textContent = 'Network error: ' + error.message;
                        errorContainer.style.display = 'block';
                        
                        if (debugInfo) {
                            debugInfo.textContent += '\nFetch error: ' + error.message;
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
