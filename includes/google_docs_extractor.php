<?php
/**
 * Google Docs Content Extractor
 * 
 * This script acts as a server-side proxy to extract content from Google Docs
 * It bypasses cross-origin restrictions by making the request from the server
 */

// Include config and required files
require_once 'config.php';

// Set headers to allow CORS
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Get document URL from request
$doc_url = isset($_GET['url']) ? $_GET['url'] : '';
$doc_id = isset($_GET['doc_id']) ? $_GET['doc_id'] : '';
$use_api = isset($_GET['use_api']) && $_GET['use_api'] === '1';
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';

// Extract document ID from URL if provided
if (empty($doc_id) && !empty($doc_url)) {
    // Extract Google Doc ID from URL
    preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $doc_url, $matches);
    if (isset($matches[1])) {
        $doc_id = $matches[1];
    }
}

// Check if we have a document ID
if (empty($doc_id)) {
    echo json_encode([
        'success' => false,
        'error' => 'No document ID provided'
    ]);
    exit;
}

// Function to extract text from Google Docs using public export
function extractGoogleDocsContent($doc_id) {
    // Use Google Docs public export URL to get the document as plain text
    $export_url = "https://docs.google.com/document/d/{$doc_id}/export?format=txt";
    
    // Initialize cURL session
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $export_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Skip SSL verification (not recommended for production)
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    // Execute cURL session
    $response = curl_exec($ch);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        return [
            'success' => false,
            'error' => 'cURL Error: ' . curl_error($ch)
        ];
    }
    
    // Get HTTP status code
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Close cURL session
    curl_close($ch);
    
    // Check if request was successful
    if ($http_code == 200) {
        return [
            'success' => true,
            'content' => $response
        ];
    } else {
        return [
            'success' => false,
            'error' => "HTTP Error: {$http_code}",
            'note' => 'The document might be private or require authentication'
        ];
    }
}

// Function to extract text from Google Docs using HTML preview
function extractGoogleDocsContentFromPreview($doc_id) {
    // Use Google Docs preview URL
    $preview_url = "https://docs.google.com/document/d/{$doc_id}/preview";
    
    // Initialize cURL session
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $preview_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    // Execute cURL session
    $response = curl_exec($ch);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        return [
            'success' => false,
            'error' => 'cURL Error: ' . curl_error($ch)
        ];
    }
    
    // Get HTTP status code
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Close cURL session
    curl_close($ch);
    
    // Check if request was successful
    if ($http_code == 200) {
        // Extract text from HTML
        $text = extractTextFromHtml($response);
        
        if (!empty($text)) {
            return [
                'success' => true,
                'content' => $text,
                'method' => 'preview'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Could not extract text from preview',
                'note' => 'The document might be using advanced formatting'
            ];
        }
    } else {
        return [
            'success' => false,
            'error' => "HTTP Error on preview: {$http_code}",
            'note' => 'The document might be private or require authentication'
        ];
    }
}

// Function to extract text from HTML
function extractTextFromHtml($html) {
    // Remove scripts and styles
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
    
    // Try to find the content container
    if (preg_match('/<div[^>]*id="contents"[^>]*>(.*?)<\/div>/is', $html, $matches)) {
        $content = $matches[1];
    } else {
        $content = $html;
    }
    
    // Convert HTML entities
    $content = html_entity_decode($content);
    
    // Remove HTML tags
    $text = strip_tags($content);
    
    // Clean up whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    return $text;
}

// Function to extract content using Google Drive API (if configured)
function extractGoogleDocsContentUsingAPI($doc_id) {
    // Check if Google API credentials are configured
    if (!file_exists('../vendor/autoload.php')) {
        return [
            'success' => false,
            'error' => 'Google API client not available',
            'note' => 'The server is not configured with Google API access'
        ];
    }
    
    try {
        require_once '../vendor/autoload.php';
        
        // Check if we have a service account key file - try multiple possible locations
        $possibleKeyPaths = [
            '../storage/google_service_account.json',
            'storage/google_service_account.json',
            dirname(__DIR__) . '/storage/google_service_account.json',
            $_SERVER['DOCUMENT_ROOT'] . '/storage/google_service_account.json',
            $_SERVER['DOCUMENT_ROOT'] . '/SCCDMS2/storage/google_service_account.json'
        ];
        
        $keyFilePath = null;
        foreach ($possibleKeyPaths as $path) {
            if (file_exists($path)) {
                $keyFilePath = $path;
                break;
            }
        }
        
        if (!$keyFilePath) {
            return [
                'success' => false,
                'error' => 'Service account key file not found',
                'note' => 'The server is not configured with Google API service account',
                'debug' => [
                    'searched_paths' => $possibleKeyPaths,
                    'current_dir' => __DIR__,
                    'document_root' => $_SERVER['DOCUMENT_ROOT']
                ]
            ];
        }
        
        // Create Google client
        $client = new Google_Client();
        $client->setAuthConfig($keyFilePath);
        $client->setScopes(['https://www.googleapis.com/auth/drive.readonly']);
        
        // Create Drive service
        $service = new Google_Service_Drive($client);
        
        try {
            // Get file metadata
            $file = $service->files->get($doc_id, ['fields' => 'id,name,mimeType']);
            
            // Check if this is a Google Doc
            if ($file->getMimeType() !== 'application/vnd.google-apps.document') {
                return [
                    'success' => false,
                    'error' => 'Not a Google Doc',
                    'note' => 'The specified ID is not a Google Document',
                    'mime_type' => $file->getMimeType()
                ];
            }
            
            // Export as plain text
            $content = $service->files->export($doc_id, 'text/plain', ['alt' => 'media']);
            
            return [
                'success' => true,
                'content' => (string)$content->getBody(),
                'method' => 'api',
                'file_info' => [
                    'name' => $file->getName(),
                    'id' => $file->getId()
                ]
            ];
        } catch (Google_Service_Exception $e) {
            $error = json_decode($e->getMessage(), true);
            $errorMessage = isset($error['error']['message']) ? $error['error']['message'] : $e->getMessage();
            
            // Check if this is a permission error
            if (strpos($errorMessage, 'permission') !== false || $e->getCode() == 403) {
                return [
                    'success' => false,
                    'error' => 'Permission denied: ' . $errorMessage,
                    'note' => 'Make sure you have shared the document with the service account email',
                    'service_account' => json_decode(file_get_contents($keyFilePath), true)['client_email'] ?? 'Unknown'
                ];
            }
            
            return [
                'success' => false,
                'error' => 'API Error: ' . $errorMessage,
                'code' => $e->getCode()
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'API Error: ' . $e->getMessage(),
            'note' => 'Error using Google Drive API',
            'trace' => $e->getTraceAsString()
        ];
    }
}

// Function to create a fallback response with instructions
function createFallbackResponse($doc_id) {
    $preview_url = "https://docs.google.com/document/d/{$doc_id}/preview";
    
    return [
        'success' => false,
        'error' => 'Could not automatically extract content',
        'fallback_available' => true,
        'preview_url' => $preview_url,
        'instructions' => [
            'Open the document in a new tab',
            'Copy the text content',
            'Use the paste option in the grammar checker'
        ],
        'note' => 'The document requires authentication or has restricted access'
    ];
}

// Function to verify the service account JSON file
function verifyServiceAccountFile($filePath) {
    if (!file_exists($filePath)) {
        return [
            'valid' => false,
            'error' => 'File does not exist'
        ];
    }
    
    if (!is_readable($filePath)) {
        return [
            'valid' => false,
            'error' => 'File is not readable'
        ];
    }
    
    $content = file_get_contents($filePath);
    if (empty($content)) {
        return [
            'valid' => false,
            'error' => 'File is empty'
        ];
    }
    
    $json = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'valid' => false,
            'error' => 'Invalid JSON: ' . json_last_error_msg()
        ];
    }
    
    // Check for required fields in service account JSON
    $requiredFields = ['type', 'project_id', 'private_key_id', 'private_key', 'client_email', 'client_id'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($json[$field]) || empty($json[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        return [
            'valid' => false,
            'error' => 'Missing required fields: ' . implode(', ', $missingFields)
        ];
    }
    
    // Verify this is a service account key
    if ($json['type'] !== 'service_account') {
        return [
            'valid' => false,
            'error' => 'Not a service account key file'
        ];
    }
    
    return [
        'valid' => true,
        'project_id' => $json['project_id'],
        'client_email' => $json['client_email']
    ];
}

// Try different methods to extract content
$result = null;

// If API usage is requested and available
if ($use_api) {
    $result = extractGoogleDocsContentUsingAPI($doc_id);
    if ($result['success']) {
        echo json_encode($result);
        exit;
    }
    
    // If debug mode is enabled, include detailed error information
    if ($debug_mode && !$result['success']) {
        // Add additional debug information
        $result['debug_info'] = [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'],
            'script_filename' => $_SERVER['SCRIPT_FILENAME'],
            'current_dir' => __DIR__,
            'parent_dir' => dirname(__DIR__),
            'google_api_installed' => class_exists('Google_Client')
        ];
        
        // Check if service account file exists and is readable
        $serviceAccountPath = dirname(__DIR__) . '/storage/google_service_account.json';
        $result['service_account_file'] = [
            'path' => $serviceAccountPath,
            'exists' => file_exists($serviceAccountPath),
            'readable' => is_readable($serviceAccountPath),
            'size' => file_exists($serviceAccountPath) ? filesize($serviceAccountPath) : 0
        ];
        
        // Verify service account file
        $verification = verifyServiceAccountFile($serviceAccountPath);
        $result['service_account_verification'] = $verification;
        
        // If service account file exists, get the client email
        if ($verification['valid']) {
            $result['service_account_email'] = $verification['client_email'];
        }
        
        echo json_encode($result);
        exit;
    }
}

// Try direct export first (works for public documents)
$result = extractGoogleDocsContent($doc_id);
if ($result['success']) {
    echo json_encode($result);
    exit;
}

// If export fails, try preview extraction
$result = extractGoogleDocsContentFromPreview($doc_id);
if ($result['success']) {
    echo json_encode($result);
    exit;
}

// If all methods fail, return fallback response
echo json_encode(createFallbackResponse($doc_id)); 