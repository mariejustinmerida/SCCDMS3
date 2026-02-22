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

// Set content type to JSON
header('Content-Type: application/json');

// Log that this endpoint was called
error_log("DEBUG: analyze_document.php endpoint called at " . date('Y-m-d H:i:s'));

// Enable error logging but disable display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/ai_errors.log');

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
$analysisType = isset($requestData['analysisType']) ? $requestData['analysisType'] : 'full';

// Get document details from database
$stmt = $conn->prepare("SELECT * FROM documents WHERE document_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $documentId);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    error_log("DB prepare failed in analyze_document.php: " . $conn->error);
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
    error_log("DEBUG: Content extraction failed - content is empty");
    echo json_encode([
        'success' => false,
        'message' => "Failed to extract content from the document."
    ]);
    exit;
}

error_log("DEBUG: Content extracted successfully, length: " . strlen($content));
error_log("DEBUG: First 200 chars of content: " . substr($content, 0, 200));

// Analyze the document using Gemini
error_log("DEBUG: About to call analyzeDocumentWithOpenAI with document ID: " . $documentId);
$analysis = analyzeDocumentWithOpenAI($content, $document, $analysisType);
error_log("DEBUG: analyzeDocumentWithOpenAI returned: " . json_encode($analysis));

// Return the analysis
echo json_encode($analysis);
exit;

// Function to analyze document with Gemini
function analyzeDocumentWithOpenAI($content, $document, $analysisType) {
    error_log("DEBUG: analyzeDocumentWithOpenAI called with content length: " . strlen($content));
    // Check if we have a Gemini API key
    $apiKey = getenv('GEMINI_API_KEY');
    
    if (empty($apiKey)) {
        // Check if API key is stored in database
        global $conn;
        // Prefer Gemini key from settings
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name = 'gemini_api_key'");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $apiKey = $row['setting_value'];
        }
        // Optional fallback to OpenAI key if Gemini key not set (legacy)
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
        // Log that we're using mock response
        error_log("No Gemini API key found - using mock response");
        $mockResponse = getMockResponse('analysis');
        return $mockResponse;
    }
    
    error_log("DEBUG: Using Gemini API key: " . substr($apiKey, 0, 10) . "...");
    
    // Get AI settings
    $model = 'gemini-1.5-flash';
    $maxTokens = 1200;
    $temperature = 0.2;
    
    $modelResult = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'ai_model'");
    if ($modelResult && $modelResult->num_rows > 0) {
        $row = $modelResult->fetch_assoc();
        $configuredModel = trim($row['setting_value']);
        // Only honor configured model if it's a Gemini model
        if (stripos($configuredModel, 'gemini') === 0) {
            $model = $configuredModel;
        }
    }
    
    $tokensResult = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'ai_max_tokens'");
    if ($tokensResult && $tokensResult->num_rows > 0) {
        $row = $tokensResult->fetch_assoc();
        $maxTokens = (int)$row['setting_value'];
    }
    
    $tempResult = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'ai_temperature'");
    if ($tempResult && $tempResult->num_rows > 0) {
        $row = $tempResult->fetch_assoc();
        $temperature = (float)$row['setting_value'];
    }
    
    // Truncate content if it's too long to avoid token limits
    $maxContentLength = 8000;
    $truncatedContent = strlen($content) > $maxContentLength 
        ? substr($content, 0, $maxContentLength) . "... [Content truncated due to length]" 
        : $content;
    
    // Prepare system and user prompts based on analysis type
    $systemPrompt = 'You are a document analysis expert. Analyze the provided document and return your analysis in JSON format.';
    $userPrompt = "Please analyze the following document:\n\nTitle: {$document['title']}\n\nContent:\n{$truncatedContent}";
    
    switch ($analysisType) {
        case 'entities':
            $systemPrompt .= ' Focus on identifying entities such as people, organizations, locations, dates, and other named entities.';
            break;
        case 'sentiment':
            $systemPrompt .= ' Focus on sentiment analysis, identifying the overall tone and emotional content.';
            break;
        case 'classification':
            $systemPrompt .= ' Focus on classifying the document into appropriate categories.';
            break;
        case 'keywords':
            $systemPrompt .= ' Focus on extracting key terms and phrases from the document.';
            break;
        case 'summary':
            $systemPrompt .= ' Focus on providing a concise summary of the document.';
            break;
        case 'full':
        default:
            $systemPrompt .= ' Provide a comprehensive analysis including entities, sentiment, classification, keywords, and a summary.';
            break;
    }
    
    // Prepare the request to Gemini API
    $data = [
        'systemInstruction' => [
            'parts' => [ ['text' => $systemPrompt] ]
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [ ['text' => $userPrompt] ]
            ]
        ],
        'generationConfig' => [
            'temperature' => $temperature,
            'maxOutputTokens' => $maxTokens,
            'response_mime_type' => 'application/json'
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE']
        ]
    ];
    
    // Call the Gemini API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    error_log("DEBUG: Gemini API Response - Status: $statusCode, Error: $error");
    if (!empty($response)) {
        error_log("DEBUG: Response length: " . strlen($response));
    }
    
    // Check for errors
    if ($statusCode !== 200 || !empty($error)) {
        error_log("Gemini API Error: $error, Status: $statusCode, Response: $response");
        
        // Check for specific quota exceeded error
        if ($statusCode === 429) {
            error_log("DEBUG: API quota exceeded - returning quota error instead of mock");
            return [
                'success' => false,
                'message' => 'AI analysis quota exceeded. Please try again tomorrow or upgrade your API plan.',
                'quota_exceeded' => true
            ];
        }
        
        error_log("DEBUG: Falling back to mock response due to API error");
        return getMockResponse('analysis');
    }
    
    // Parse the response
    try {
        $responseData = json_decode($response, true);
        if (isset($responseData['error'])) {
            error_log("Gemini API Error: " . json_encode($responseData['error']));
            error_log("DEBUG: Falling back to mock response due to API error in response");
            return getMockResponse('analysis');
        }
        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            error_log("Gemini API returned unexpected format: " . json_encode($responseData));
            error_log("DEBUG: Falling back to mock response due to unexpected API format");
            return getMockResponse('analysis');
        }
        $contentText = $responseData['candidates'][0]['content']['parts'][0]['text'];
        if (strpos(trim($contentText), '```') === 0) {
            $contentText = preg_replace('/^```[a-zA-Z]*\n|\n```$/', '', trim($contentText));
        }
        $analysisData = json_decode($contentText, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (preg_match('/\{[\s\S]*\}/', $contentText, $m)) {
                $analysisData = json_decode($m[0], true);
            }
        }
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Failed to parse Gemini response as JSON: " . json_last_error_msg());
            error_log("Raw content: " . $contentText);
            error_log("DEBUG: Falling back to mock response due to JSON parse error");
            return getMockResponse('analysis');
        }
        
        // Add success flag
        $analysisData['success'] = true;
        
        return $analysisData;
    } catch (Exception $e) {
        error_log("Error processing Gemini response: " . $e->getMessage());
        error_log("DEBUG: Falling back to mock response due to exception");
        return getMockResponse('analysis');
}
}
