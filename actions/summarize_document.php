<?php
// Include necessary files
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Function to extract content from Google Docs
function extractGoogleDocsContent($doc_id) {
    // Use Google Docs public export URL to get the document as plain text
    $export_url = "https://docs.google.com/document/d/{$doc_id}/export?format=txt";
    
    // Initialize cURL session
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $export_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    // Execute cURL session
    $response = curl_exec($ch);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        error_log("Google Docs extraction cURL error: " . curl_error($ch));
        curl_close($ch);
        return '';
    }
    
    // Get HTTP status code
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check if request was successful
    if ($http_code == 200 && !empty($response)) {
        error_log("DEBUG: Google Docs content extracted successfully, length: " . strlen($response));
        error_log("DEBUG: First 200 chars of content: " . substr($response, 0, 200));
        return $response;
    } else {
        error_log("Google Docs extraction failed with HTTP code: $http_code");
        return '';
    }
}

// Enable error logging but disable display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/ai_errors.log');

// Set content type to JSON
header('Content-Type: application/json');

// Log that this endpoint was called
error_log("DEBUG: summarize_document.php endpoint called at " . date('Y-m-d H:i:s'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Get the request body
$requestData = json_decode(file_get_contents('php://input'), true);

// Validate request
if (!isset($requestData['documentId']) || empty($requestData['documentId'])) {
    echo json_encode(['success' => false, 'message' => 'Document ID is required']);
    exit;
}

$documentId = intval($requestData['documentId']);
$fileName = isset($requestData['fileName']) ? $requestData['fileName'] : '';

// Get document details from database
$stmt = $conn->prepare("SELECT * FROM documents WHERE document_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $documentId);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    error_log("DB prepare failed in summarize_document.php: " . $conn->error);
    $documentId = intval($documentId);
    $fallbackSql = "SELECT * FROM documents WHERE document_id = $documentId";
    $result = $conn->query($fallbackSql);
}
    
if (!$result || $result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Document not found']);
    exit;
}

$document = $result->fetch_assoc();
error_log("DEBUG: Document record: " . json_encode($document));
$filePath = $document['file_path'];

// Extract content from the document based on file type
$fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
error_log("DEBUG: Processing file with extension: '$fileExt', path: '$filePath'");
$content = '';

// Check if this is a Google Doc
if (!empty($document['google_doc_id'])) {
    error_log("DEBUG: Processing Google Doc with ID: " . $document['google_doc_id']);
    $content = extractGoogleDocsContent($document['google_doc_id']);
    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Failed to extract content from Google Doc']);
        exit;
    }
} else {
    // Regular file processing
    switch ($fileExt) {
        case 'pdf':
            $content = extractPdfContent($filePath);
            break;
        case 'docx':
        case 'doc':
            $content = extractDocxContent($filePath);
            break;
        case 'txt':
            $content = extractTxtContent($filePath);
            break;
        case 'html':
        case 'htm':
            $content = extractHtmlContent($filePath);
            break;
        case 'rtf':
            $content = extractTxtContent($filePath); // Try as text
            break;
        case 'odt':
            $content = extractTxtContent($filePath); // Try as text
            break;
        default:
            error_log("DEBUG: Unsupported file type: '$fileExt'");
            echo json_encode(['success' => false, 'message' => "Unsupported file type: $fileExt"]);
            exit;
    }
}

// If content extraction failed or content is empty
if (empty($content)) {
    echo json_encode([
        'success' => false,
        'message' => "Failed to extract content from the document."
    ]);
    exit;
}

// Summarize the document using Gemini
error_log("DEBUG: About to call summarizeWithOpenAI with document ID: " . $documentId);
$summary = summarizeWithOpenAI($content, $document);
error_log("DEBUG: summarizeWithOpenAI returned: " . json_encode($summary));

// Return the summary
echo json_encode($summary);
exit;

// Function to summarize document with Gemini
function summarizeWithOpenAI($content, $document) {
    error_log("DEBUG: summarizeWithOpenAI called with content length: " . strlen($content));
    // Get Gemini API key
    $apiKey = getenv('GEMINI_API_KEY');
    
    if (empty($apiKey)) {
        // Check if API key is stored in database
        global $conn;
        // Prefer Gemini key from settings, fallback to OpenAI if absent
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name = 'gemini_api_key'");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $apiKey = $row['setting_value'];
        }
        if (empty($apiKey)) {
            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name = 'openai_api_key'");
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $apiKey = $row['setting_value'];
            }
        }
    }
    
    if (empty($apiKey)) {
        error_log("No Gemini API key found - using mock response");
        $mockResponse = getMockResponse('summary');
        return $mockResponse;
    }

    // Model/params from settings
    $model = 'gemini-2.5-flash';
    $temperature = 0.2;
    $max_tokens = 900;
    global $conn;
    $modelStmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name = 'ai_model'");
    if ($modelStmt) {
        $modelStmt->execute();
        $modelResult = $modelStmt->get_result();
        if ($modelResult->num_rows > 0) {
            $row = $modelResult->fetch_assoc();
            $configuredModel = trim($row['setting_value']);
            if (stripos($configuredModel, 'gemini') === 0) {
                $model = $configuredModel;
            }
        }
    }
    $tempStmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name = 'ai_temperature'");
    if ($tempStmt) {
        $tempStmt->execute();
        $tempResult = $tempStmt->get_result();
        if ($tempResult->num_rows > 0) {
            $row = $tempResult->fetch_assoc();
            $temperature = floatval($row['setting_value']);
        }
    }

    // Truncate content if very long - increased for better summaries
    $maxContentLength = 15000; // Increased for more comprehensive summaries
    $truncatedContent = strlen($content) > $maxContentLength 
        ? substr($content, 0, $maxContentLength) . "... [Content truncated due to length]"
        : $content;
    $wasTruncated = strlen($content) > $maxContentLength;

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Increase timeout to 2 minutes for longer processing
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $data = [
        'systemInstruction' => [
            'parts' => [[
                'text' => 'You are a document summarization expert. Your task is to provide clear, concise summaries of documents. Always respond in valid JSON format with the structure: {"summary": "detailed summary text", "keyPoints": ["point 1", "point 2", ...]}. The summary should be 2-4 well-formatted paragraphs. Key points should be specific, actionable items (4-8 points) as strings only, not objects.'
            ]]
        ],
        'contents' => [
            [
                'parts' => [[
                    'text' => "Analyze and summarize the following document thoroughly:\n\nTitle: {$document['title']}\n\nContent:\n{$truncatedContent}\n\nProvide:\n1. A comprehensive summary (2-4 paragraphs)\n2. A list of 4-8 key points as an array of strings\n\nReturn ONLY valid JSON with no markdown or code blocks."
                ]]
            ]
        ],
        'generationConfig' => [
            'temperature' => $temperature,
            'maxOutputTokens' => max($max_tokens, 2000), // Ensure enough tokens
            'response_mime_type' => 'application/json',
            'responseSchema' => [
                'type' => 'object',
                'properties' => [
                    'summary' => [
                        'type' => 'string',
                        'description' => 'A comprehensive summary in 2-4 paragraphs'
                    ],
                    'keyPoints' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string'
                        ],
                        'description' => 'Array of 4-8 key points as strings'
                    ]
                ],
                'required' => ['summary', 'keyPoints']
            ]
        ]
    ];
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    error_log("DEBUG: Gemini API Response - Status: $statusCode, Error: $error");
    if (!empty($response)) {
        error_log("DEBUG: Response length: " . strlen($response));
    }

    // Check for request-level error
    if ($statusCode !== 200 || !empty($error)) {
        error_log("Gemini API Error: $error, Status: $statusCode, Response: $response");
        return [
            'success' => false,
            'message' => 'AI summarization failed. ' . ($error ?: ($response ?: 'Unknown error.'))
        ];
    }
    // Parse response
    $responseData = json_decode($response, true);
    if (isset($responseData['error'])) {
        error_log("Gemini API Error: " . json_encode($responseData['error']));
        return [
            'success' => false,
            'message' => 'AI summarization failed: ' . $responseData['error']['message']
        ];
    }
    // Handle no response/finish reason
    $text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (empty($text)) {
        $reason = $responseData['candidates'][0]['finishReason'] ?? '';
        $friendly = ($reason === 'MAX_TOKENS')
            ? 'AI could not return a summary (maximum output length was reached). Try reducing document size.'
            : 'AI could not return a summary. Please try again or check your input.';
        return [
            'success' => false,
            'message' => $friendly
        ];
    }
    // Remove any code block markdown fences
    $text2 = trim($text);
    if (strpos($text2, '```') === 0) {
        $text2 = preg_replace('/^```[a-zA-Z]*\n|\n```$/', '', $text2);
    }
    
    // Remove any leading/trailing non-JSON text
    $text2 = preg_replace('/^(Here is|This is|The result is|JSON:|Response:|Summary:).*?\{/s', '{', $text2, 1);
    
    // Try to parse JSON output
    $jsonResult = json_decode($text2, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Try to pull first JSON object
        if (preg_match('/\{[\s\S]*\}/', $text2, $m)) {
            $jsonResult = json_decode($m[0], true);
        }
        
        // If still failed, try cleaning up common issues
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Remove trailing comma before closing brace
            $text2 = preg_replace('/,\s*}/', '}', $text2);
            // Remove trailing comma before closing bracket
            $text2 = preg_replace('/,\s*]/', ']', $text2);
            $jsonResult = json_decode($text2, true);
        }
    }
    
    // If still not JSON, give friendly text summary with extraction attempt
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($jsonResult)) {
        error_log("Summary JSON parse failed: " . json_last_error_msg() . " - Text preview: " . substr($text2, 0, 200));
        // Try to extract summary and keyPoints from plain text if possible
        $summaryText = $text2;
        $extractedKeyPoints = [];
        
        // Try to find key points if they're mentioned
        if (preg_match('/key\s*points?[:\-]?\s*(.*?)(?:\n\n|$)/is', $summaryText, $kpMatch)) {
            $kpText = $kpMatch[1];
            $extractedKeyPoints = preg_split('/[•\-\*]\s*|\d+\.\s*/', $kpText, -1, PREG_SPLIT_NO_EMPTY);
            $extractedKeyPoints = array_map('trim', $extractedKeyPoints);
            $extractedKeyPoints = array_filter($extractedKeyPoints, function($p) { return strlen($p) > 10; });
        }
        
        return [
            'success' => true,
            'summary' => $summaryText,
            'keyPoints' => array_values($extractedKeyPoints)
        ];
    }
    // Key points must be string array
    $keyPoints = $jsonResult['keyPoints'] ?? [];
    if (!is_array($keyPoints)) $keyPoints = [];
    // Flatten any key points that are not strings
    $keyPointsClean = [];
    foreach ($keyPoints as $kpt) {
        if (is_string($kpt)) $keyPointsClean[] = $kpt;
        else if (is_array($kpt) && isset($kpt['point'])) $keyPointsClean[] = $kpt['point'];
        else if (is_object($kpt) && isset($kpt->point)) $keyPointsClean[] = (string) $kpt->point;
        else if (!is_array($kpt) && !is_object($kpt)) $keyPointsClean[] = (string)$kpt;
    }
    $resp = [
        'success' => true,
        'summary' => $jsonResult['summary'] ?? ($text2 ?: 'No summary available'),
        'keyPoints' => $keyPointsClean
    ];
    if ($wasTruncated) {
        $resp['warning'] = 'Document was truncated for AI processing.';
    }
    return $resp;
}
?>
