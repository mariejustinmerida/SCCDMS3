<?php
/**
 * Document Extractor API
 * Extracts content from various document types
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include necessary files
require_once 'config.php';
require_once 'db_connect.php';
require_once 'auth_check.php';

// Initialize response
$response = [
    'success' => false,
    'content' => '',
    'error' => '',
    'debug' => []
];

// Check if debug mode is enabled
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';

// Get document ID and type
$documentId = isset($_GET['id']) ? $_GET['id'] : '';
$documentType = isset($_GET['type']) ? $_GET['type'] : '';

// Validate document ID
if (empty($documentId)) {
    $response['error'] = 'Document ID is required';
    echo json_encode($response);
    exit;
}

// If document type is not specified, try to determine it from the database
if (empty($documentType)) {
    $documentType = getDocumentTypeFromDatabase($documentId, $conn);
}

try {
    // Extract content based on document type
    switch ($documentType) {
        case 'google_docs':
            extractGoogleDocsContent($documentId, $response);
            break;
        case 'pdf':
            extractPdfContent($documentId, $response);
            break;
        case 'docx':
            extractDocxContent($documentId, $response);
            break;
        default:
            // Try to extract content using a generic method
            extractGenericContent($documentId, $response);
            break;
    }
} catch (Exception $e) {
    $response['error'] = 'Error extracting document content: ' . $e->getMessage();
    if ($debug) {
        $response['debug'][] = [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

// Return response
echo json_encode($response);
exit;

/**
 * Get document type from database
 * @param string $documentId The document ID
 * @param mysqli $conn Database connection
 * @return string Document type
 */
function getDocumentTypeFromDatabase($documentId, $conn) {
    try {
        // Remove any non-numeric characters from document ID for security
        $docId = preg_replace('/[^0-9]/', '', $documentId);
        
        // Query the database for document type
        $stmt = $conn->prepare("SELECT file_type, file_path FROM documents WHERE id = ?");
        $stmt->bind_param("s", $docId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $fileType = $row['file_type'];
            $filePath = $row['file_path'];
            
            // Determine document type based on file type or path
            if (!empty($fileType)) {
                return $fileType;
            } elseif (!empty($filePath)) {
                $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                switch (strtolower($extension)) {
                    case 'pdf':
                        return 'pdf';
                    case 'docx':
                    case 'doc':
                        return 'docx';
                    case 'txt':
                        return 'text';
                    case 'html':
                    case 'htm':
                        return 'html';
                    default:
                        return 'unknown';
                }
            }
        }
    } catch (Exception $e) {
        // Log error but continue with default type
        error_log('Error determining document type: ' . $e->getMessage());
    }
    
    return 'unknown';
}

/**
 * Extract content from Google Docs
 * @param string $documentId The document ID
 * @param array &$response The response array
 */
function extractGoogleDocsContent($documentId, &$response) {
    global $debug;
    
    // Check if Google API is available
    if (!file_exists('../vendor/autoload.php')) {
        $response['error'] = 'Google API client not available';
        return;
    }
    
    try {
        require_once '../vendor/autoload.php';
        
        // Check if service account credentials file exists
        $credentialsPath = '../storage/google_service_account.json';
        if (!file_exists($credentialsPath)) {
            $response['error'] = 'Google service account credentials not found';
            if ($debug) {
                $response['debug'][] = [
                    'credentials_path' => $credentialsPath,
                    'file_exists' => false
                ];
            }
            return;
        }
        
        // Initialize Google API client
        $client = new Google_Client();
        $client->setApplicationName('SCCDMS Document Extractor');
        $client->setAuthConfig($credentialsPath);
        $client->setScopes(['https://www.googleapis.com/auth/documents.readonly']);
        
        // Create Google Docs service
        $service = new Google_Service_Docs($client);
        
        // Get document content
        $doc = $service->documents->get($documentId);
        
        // Extract text content
        $content = '';
        $body = $doc->getBody();
        $content = extractTextFromGoogleDocsBody($body);
        
        $response['success'] = true;
        $response['content'] = $content;
    } catch (Exception $e) {
        $response['error'] = 'Error extracting Google Docs content: ' . $e->getMessage();
        if ($debug) {
            $response['debug'][] = [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
        
        // Try alternative method - Google Docs export as text
        try {
            $client = new Google_Client();
            $client->setApplicationName('SCCDMS Document Extractor');
            $client->setAuthConfig($credentialsPath);
            $client->setScopes(['https://www.googleapis.com/auth/drive.readonly']);
            
            $driveService = new Google_Service_Drive($client);
            
            // Export as plain text
            $response = $driveService->files->export($documentId, 'text/plain', array(
                'alt' => 'media'
            ));
            
            $content = $response->getBody()->getContents();
            
            $response['success'] = true;
            $response['content'] = $content;
        } catch (Exception $e2) {
            $response['error'] .= ' Alternative method also failed: ' . $e2->getMessage();
            if ($debug) {
                $response['debug'][] = [
                    'alternative_exception' => $e2->getMessage(),
                    'alternative_trace' => $e2->getTraceAsString()
                ];
            }
        }
    }
}

/**
 * Extract text from Google Docs body
 * @param Google_Service_Docs_Body $body The document body
 * @return string The extracted text
 */
function extractTextFromGoogleDocsBody($body) {
    $content = '';
    $elements = $body->getContent();
    
    foreach ($elements as $element) {
        if ($element->getParagraph()) {
            $paragraph = $element->getParagraph();
            $elements = $paragraph->getElements();
            
            foreach ($elements as $element) {
                $textRun = $element->getTextRun();
                if ($textRun) {
                    $content .= $textRun->getContent();
                }
            }
        } elseif ($element->getTable()) {
            $table = $element->getTable();
            $rows = $table->getTableRows();
            
            foreach ($rows as $row) {
                $cells = $row->getTableCells();
                
                foreach ($cells as $cell) {
                    $content .= extractTextFromGoogleDocsBody($cell->getContent());
                }
            }
        }
    }
    
    return $content;
}

/**
 * Extract content from PDF
 * @param string $documentId The document ID
 * @param array &$response The response array
 */
function extractPdfContent($documentId, &$response) {
    global $debug;
    
    try {
        // Get PDF file path from database
        $filePath = getDocumentFilePath($documentId);
        
        if (empty($filePath) || !file_exists($filePath)) {
            $response['error'] = 'PDF file not found';
            if ($debug) {
                $response['debug'][] = [
                    'file_path' => $filePath,
                    'file_exists' => file_exists($filePath)
                ];
            }
            return;
        }
        
        // Check if PDF parser is available
        if (!class_exists('Smalot\PdfParser\Parser')) {
            if (file_exists('../vendor/autoload.php')) {
                require_once '../vendor/autoload.php';
            } else {
                $response['error'] = 'PDF parser not available';
                return;
            }
        }
        
        // Parse PDF
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        $content = $pdf->getText();
        
        $response['success'] = true;
        $response['content'] = $content;
    } catch (Exception $e) {
        $response['error'] = 'Error extracting PDF content: ' . $e->getMessage();
        if ($debug) {
            $response['debug'][] = [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
}

/**
 * Extract content from DOCX
 * @param string $documentId The document ID
 * @param array &$response The response array
 */
function extractDocxContent($documentId, &$response) {
    global $debug;
    
    try {
        // Get DOCX file path from database
        $filePath = getDocumentFilePath($documentId);
        
        if (empty($filePath) || !file_exists($filePath)) {
            $response['error'] = 'DOCX file not found';
            if ($debug) {
                $response['debug'][] = [
                    'file_path' => $filePath,
                    'file_exists' => file_exists($filePath)
                ];
            }
            return;
        }
        
        // Extract text using ZipArchive and XML parsing
        $content = '';
        
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            if (($index = $zip->locateName('word/document.xml')) !== false) {
                $data = $zip->getFromIndex($index);
                $xml = new DOMDocument();
                $xml->loadXML($data, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $content = strip_tags($xml->saveXML());
            }
            $zip->close();
        } else {
            throw new Exception('Unable to open DOCX file');
        }
        
        $response['success'] = true;
        $response['content'] = $content;
    } catch (Exception $e) {
        $response['error'] = 'Error extracting DOCX content: ' . $e->getMessage();
        if ($debug) {
            $response['debug'][] = [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
}

/**
 * Extract content using a generic method
 * @param string $documentId The document ID
 * @param array &$response The response array
 */
function extractGenericContent($documentId, &$response) {
    global $debug;
    
    try {
        // Get file path from database
        $filePath = getDocumentFilePath($documentId);
        
        if (empty($filePath) || !file_exists($filePath)) {
            $response['error'] = 'Document file not found';
            if ($debug) {
                $response['debug'][] = [
                    'file_path' => $filePath,
                    'file_exists' => file_exists($filePath)
                ];
            }
            return;
        }
        
        // Get file extension
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // Extract content based on file extension
        switch ($extension) {
            case 'pdf':
                extractPdfContent($documentId, $response);
                break;
            case 'docx':
            case 'doc':
                extractDocxContent($documentId, $response);
                break;
            case 'txt':
                $content = file_get_contents($filePath);
                $response['success'] = true;
                $response['content'] = $content;
                break;
            case 'html':
            case 'htm':
                $content = file_get_contents($filePath);
                $content = strip_tags($content);
                $response['success'] = true;
                $response['content'] = $content;
                break;
            default:
                $response['error'] = 'Unsupported file type: ' . $extension;
                break;
        }
    } catch (Exception $e) {
        $response['error'] = 'Error extracting document content: ' . $e->getMessage();
        if ($debug) {
            $response['debug'][] = [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
}

/**
 * Get document file path from database
 * @param string $documentId The document ID
 * @return string The file path
 */
function getDocumentFilePath($documentId) {
    global $conn, $debug;
    
    try {
        // Remove any non-numeric characters from document ID for security
        $docId = preg_replace('/[^0-9]/', '', $documentId);
        
        // Query the database for file path
        $stmt = $conn->prepare("SELECT file_path FROM documents WHERE id = ?");
        $stmt->bind_param("s", $docId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['file_path'];
        }
    } catch (Exception $e) {
        if ($debug) {
            error_log('Error getting document file path: ' . $e->getMessage());
        }
    }
    
    return '';
} 