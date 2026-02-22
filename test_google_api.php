<?php
/**
 * Google API Test Script
 * 
 * This script tests the Google API configuration and service account setup
 */

// Set content type to HTML
header('Content-Type: text/html');

echo '<html><head><title>Google API Test</title>';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
    h1 { color: #333; }
    h2 { color: #555; margin-top: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
    .container { max-width: 800px; margin: 0 auto; }
    .test-result { margin-bottom: 20px; padding: 10px; border-radius: 5px; }
    .test-success { background-color: #d4edda; border: 1px solid #c3e6cb; }
    .test-error { background-color: #f8d7da; border: 1px solid #f5c6cb; }
    .test-warning { background-color: #fff3cd; border: 1px solid #ffeeba; }
</style>';
echo '</head><body><div class="container">';
echo '<h1>Google API Configuration Test</h1>';

// Function to check if a file exists and is readable
function checkFile($path, $description) {
    echo "<h2>$description</h2>";
    
    if (file_exists($path)) {
        echo "<p class='success'>✓ File exists at: $path</p>";
        
        if (is_readable($path)) {
            echo "<p class='success'>✓ File is readable</p>";
            
            $size = filesize($path);
            echo "<p>File size: $size bytes</p>";
            
            if ($size > 0) {
                return true;
            } else {
                echo "<p class='error'>✗ File is empty</p>";
                return false;
            }
        } else {
            echo "<p class='error'>✗ File is not readable</p>";
            return false;
        }
    } else {
        echo "<p class='error'>✗ File not found at: $path</p>";
        echo "<p>Absolute path: " . realpath(dirname($path)) . "/" . basename($path) . "</p>";
        return false;
    }
}

// Check for vendor/autoload.php
$autoloadPath = __DIR__ . '/vendor/autoload.php';
$autoloadExists = checkFile($autoloadPath, 'Checking Google API Client Library');

if (!$autoloadExists) {
    echo "<div class='test-result test-error'>";
    echo "<p class='error'>Google API Client Library not found. Please make sure you have installed the required dependencies.</p>";
    echo "<p>Run the following command to install the Google API Client Library:</p>";
    echo "<pre>composer require google/apiclient:^2.0</pre>";
    echo "</div>";
} else {
    // Try to load the Google API Client
    require_once $autoloadPath;
    
    echo "<h2>Checking Google API Client</h2>";
    if (class_exists('Google_Client')) {
        echo "<p class='success'>✓ Google API Client class found</p>";
        
        // Check service account key file
        $possiblePaths = [
            __DIR__ . '/storage/google_service_account.json',
            __DIR__ . '/SCCDMS2/storage/google_service_account.json',
            dirname(__DIR__) . '/storage/google_service_account.json'
        ];
        
        $serviceAccountPath = null;
        $serviceAccountFound = false;
        
        echo "<h2>Checking Service Account Key File</h2>";
        echo "<p>Searching for service account key file in possible locations:</p>";
        echo "<ul>";
        foreach ($possiblePaths as $path) {
            echo "<li>" . $path . ": ";
            if (file_exists($path)) {
                echo "<span class='success'>Found</span>";
                $serviceAccountPath = $path;
                $serviceAccountFound = true;
                echo " (Using this path)";
            } else {
                echo "<span class='error'>Not found</span>";
            }
            echo "</li>";
        }
        echo "</ul>";
        
        if (!$serviceAccountFound) {
            echo "<div class='test-result test-error'>";
            echo "<p class='error'>Service account key file not found in any of the expected locations.</p>";
            echo "<p>Please make sure you have placed the google_service_account.json file in the storage directory.</p>";
            echo "</div>";
        } else {
            // Verify service account key file
            $serviceAccountContent = file_get_contents($serviceAccountPath);
            $serviceAccountJson = json_decode($serviceAccountContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "<div class='test-result test-error'>";
                echo "<p class='error'>Invalid JSON in service account key file: " . json_last_error_msg() . "</p>";
                echo "</div>";
            } else {
                echo "<div class='test-result test-success'>";
                echo "<p class='success'>✓ Service account key file is valid JSON</p>";
                
                // Check required fields
                $requiredFields = ['type', 'project_id', 'private_key_id', 'private_key', 'client_email', 'client_id'];
                $missingFields = [];
                
                foreach ($requiredFields as $field) {
                    if (!isset($serviceAccountJson[$field]) || empty($serviceAccountJson[$field])) {
                        $missingFields[] = $field;
                    }
                }
                
                if (!empty($missingFields)) {
                    echo "<p class='error'>✗ Missing required fields: " . implode(', ', $missingFields) . "</p>";
                } else {
                    echo "<p class='success'>✓ All required fields are present</p>";
                    
                    // Verify this is a service account key
                    if ($serviceAccountJson['type'] !== 'service_account') {
                        echo "<p class='error'>✗ Not a service account key file (type is " . $serviceAccountJson['type'] . ")</p>";
                    } else {
                        echo "<p class='success'>✓ Confirmed as service account key file</p>";
                        echo "<p><strong>Project ID:</strong> " . $serviceAccountJson['project_id'] . "</p>";
                        echo "<p><strong>Service Account Email:</strong> " . $serviceAccountJson['client_email'] . "</p>";
                        
                        // Try to create a Google client
                        try {
                            $client = new Google_Client();
                            $client->setAuthConfig($serviceAccountPath);
                            $client->setScopes(['https://www.googleapis.com/auth/drive.readonly']);
                            
                            echo "<p class='success'>✓ Successfully created Google API client with service account authentication</p>";
                            
                            // Test if we can access the Drive API
                            try {
                                $service = new Google_Service_Drive($client);
                                echo "<p class='success'>✓ Successfully created Google Drive service</p>";
                                
                                echo "<h3>Test Document Access</h3>";
                                echo "<p>To test document access, please enter a Google Doc ID:</p>";
                                echo "<form method='post'>";
                                echo "<input type='text' name='doc_id' placeholder='Google Doc ID' style='padding: 5px; width: 300px;'>";
                                echo "<input type='submit' value='Test Access' style='padding: 5px 10px; margin-left: 10px;'>";
                                echo "</form>";
                                
                                // Test document access if a document ID was provided
                                if (isset($_POST['doc_id']) && !empty($_POST['doc_id'])) {
                                    $docId = $_POST['doc_id'];
                                    echo "<h4>Testing access to document: $docId</h4>";
                                    
                                    try {
                                        $file = $service->files->get($docId, ['fields' => 'id,name,mimeType']);
                                        
                                        echo "<div class='test-result test-success'>";
                                        echo "<p class='success'>✓ Successfully accessed document metadata</p>";
                                        echo "<p><strong>Document Name:</strong> " . $file->getName() . "</p>";
                                        echo "<p><strong>MIME Type:</strong> " . $file->getMimeType() . "</p>";
                                        
                                        // Try to export the document
                                        if ($file->getMimeType() === 'application/vnd.google-apps.document') {
                                            try {
                                                $content = $service->files->export($docId, 'text/plain', ['alt' => 'media']);
                                                $textContent = (string)$content->getBody();
                                                
                                                echo "<p class='success'>✓ Successfully exported document content</p>";
                                                echo "<p><strong>Content Preview:</strong></p>";
                                                echo "<pre>" . htmlspecialchars(substr($textContent, 0, 200)) . (strlen($textContent) > 200 ? '...' : '') . "</pre>";
                                            } catch (Exception $e) {
                                                echo "<p class='error'>✗ Error exporting document content: " . $e->getMessage() . "</p>";
                                            }
                                        } else {
                                            echo "<p class='warning'>⚠ Document is not a Google Doc, cannot export content</p>";
                                        }
                                        echo "</div>";
                                    } catch (Exception $e) {
                                        echo "<div class='test-result test-error'>";
                                        echo "<p class='error'>✗ Error accessing document: " . $e->getMessage() . "</p>";
                                        echo "<p>This is likely a permission issue. Make sure you have shared the document with the service account email:</p>";
                                        echo "<pre>" . $serviceAccountJson['client_email'] . "</pre>";
                                        echo "</div>";
                                    }
                                }
                            } catch (Exception $e) {
                                echo "<p class='error'>✗ Error creating Google Drive service: " . $e->getMessage() . "</p>";
                            }
                        } catch (Exception $e) {
                            echo "<p class='error'>✗ Error creating Google API client: " . $e->getMessage() . "</p>";
                        }
                    }
                }
                echo "</div>";
            }
        }
    } else {
        echo "<p class='error'>✗ Google API Client class not found</p>";
        echo "<p>Make sure you have installed the Google API Client Library correctly.</p>";
    }
}

echo '<h2>Environment Information</h2>';
echo '<pre>';
echo 'PHP Version: ' . PHP_VERSION . "\n";
echo 'Server Software: ' . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo 'Document Root: ' . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo 'Script Filename: ' . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo 'Current Directory: ' . __DIR__ . "\n";
echo '</pre>';

echo '</div></body></html>'; 