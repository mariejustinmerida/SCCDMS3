<?php
// Start output buffering to catch any unexpected output
ob_start();

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {

// Get the request body
$requestData = json_decode(file_get_contents('php://input'), true);

// Validate request
if (!isset($requestData['operation']) || empty($requestData['operation'])) {
    echo json_encode(['success' => false, 'message' => 'Operation is required']);
        exit;
    }

if (!isset($requestData['documentId']) || empty($requestData['documentId'])) {
    echo json_encode(['success' => false, 'message' => 'Document ID is required']);
    exit;
}

$operation = $requestData['operation'];
$documentId = intval($requestData['documentId']);
$analysisType = isset($requestData['analysisType']) ? $requestData['analysisType'] : 'full';

// Handle 'all' operation - convert to 'analyze' with 'full' type
if ($operation === 'all') {
    $operation = 'analyze';
    $analysisType = 'full';
}

// Get document details from database
$stmt = $conn->prepare("SELECT d.*, u.full_name AS creator_name 
                       FROM documents d 
                       LEFT JOIN users u ON d.creator_id = u.user_id
                       WHERE d.document_id = ?");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $documentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Document not found']);
    exit;
}

$document = $result->fetch_assoc();
$filePath = $document['file_path'] ?? '';

// Function to extract content from Google Docs
function extractGoogleDocsContentForAnalysis($doc_id) {
    // Use Google Docs public export URL to get the document as plain text
    $export_url = "https://docs.google.com/document/d/{$doc_id}/export?format=txt";
    
    // Initialize cURL session
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $export_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
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
        return trim($response);
    } else {
        error_log("Google Docs extraction failed with HTTP code: $http_code");
        return '';
    }
}

$content = '';

// Check if this is a Google Doc (priority - AI generated and template documents use Google Docs)
if (!empty($document['google_doc_id'])) {
    error_log("DEBUG: Processing Google Doc with ID: " . $document['google_doc_id']);
    $content = extractGoogleDocsContentForAnalysis($document['google_doc_id']);
    if (empty($content)) {
        error_log("DEBUG: Google Docs extraction failed, trying fallback");
        // Fallback: try to use file_path if available
        if (!empty($filePath) && file_exists($filePath)) {
            $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
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
            }
        }
        
        // If still empty, use title and description as fallback
        if (empty($content)) {
            $content = ($document['title'] ?? '') . ' ' . ($document['description'] ?? '');
            error_log("DEBUG: Using title/description as fallback content");
        }
    }
} else if (!empty($filePath) && file_exists($filePath)) {
    // Regular file processing
    $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
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
        default:
            error_log("DEBUG: Unsupported file type: $fileExt");
            // Don't exit immediately - use title/description as fallback
            $content = ($document['title'] ?? '') . ' ' . ($document['description'] ?? '');
            break;
    }
} else {
    // No file path and no Google Doc - use title and description
    error_log("DEBUG: No file_path or google_doc_id, using metadata");
    $content = ($document['title'] ?? '') . ' ' . ($document['description'] ?? '');
}

// If content extraction failed or content is empty after all attempts
if (empty($content) || trim($content) === '') {
    error_log("DEBUG: Content extraction completely failed for document ID: $documentId");
    echo json_encode([
        'success' => false,
        'message' => "Failed to extract content from the document. The document may be empty or inaccessible."
    ]);
    exit;
}

error_log("DEBUG: Content extracted successfully, length: " . strlen($content) . " characters");

// Process the document based on the requested operation
$result = [];

switch ($operation) {
    case 'summarize':
        // Call the summarize document function
        $result = analyzeDocument('summarize', $content);
        break;
    case 'analyze':
        // Call the analyze document function with the specified analysis type
        $result = analyzeDocument('analyze', $content, $analysisType);
        break;
    case 'extract_entities':
        // Extract entities from the document
        $result = analyzeDocument('entities', $content);
        break;
    case 'sentiment':
        // Analyze sentiment of the document
        $result = analyzeDocument('sentiment', $content);
        break;
    case 'classify':
        // Classify the document
        $result = analyzeDocument('classification', $content);
        break;
    case 'extract_keywords':
        // Extract keywords from the document
        $result = analyzeDocument('keywords', $content);
        break;
    default:
        $result = [
            'success' => false,
            'message' => 'Invalid operation specified'
        ];
        break;
}

// Add document metadata to the result
$result['document'] = [
    'id' => $document['document_id'],
    'title' => $document['title'] ?? basename($document['file_path']),
    'type' => pathinfo($document['file_path'], PATHINFO_EXTENSION),
    'creator' => $document['creator_name'] ?? 'Unknown',
    'created_at' => $document['created_date'] ?? date('Y-m-d H:i:s')
];

// Return the result
echo json_encode($result);
exit;

// Function to analyze document using Gemini API
function analyzeDocument($operation, $content, $analysisType = 'full') {
    // Get Gemini API key
    $apiKey = getenv('GEMINI_API_KEY');
    
    if (empty($apiKey)) {
        // Check if API key is stored in database
        global $conn;
        // Prefer Gemini key from settings, fallback to OpenAI
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
        return getMockResponse($operation === 'summarize' ? 'summary' : 'analysis');
    }
    
    // Get AI settings - use same model as summarize for consistency
    $model = 'gemini-2.5-flash';
    $maxTokens = 1200;
    $temperature = 0.2;
    
    global $conn;
    $modelResult = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'ai_model'");
    if ($modelResult && $modelResult->num_rows > 0) {
        $row = $modelResult->fetch_assoc();
        $configuredModel = trim($row['setting_value']);
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
    
    // Truncate content if it's too long to avoid token limits - significantly increased for better analysis
    $maxContentLength = 50000; // Significantly increased for comprehensive analysis (was 15000)
    $truncatedContent = strlen($content) > $maxContentLength 
        ? substr($content, 0, $maxContentLength) . "... [Content truncated due to length]" 
        : $content;
    
    // Get document title for context (like summarize does)
    global $document;
    $documentTitle = $document['title'] ?? '';
    
    // Prepare system and user prompts based on operation
    $systemPrompt = '';
    $userPrompt = '';
    
    if ($operation === 'summarize') {
        $systemPrompt = 'You are a document summarization expert. Your task is to provide concise summaries of documents. Always respond in valid JSON format with the following structure: {"summary": "detailed summary text", "keyPoints": ["point 1", "point 2", ...]}. The summary should be a well-formatted paragraph. Key points should be specific, actionable items from the document.';
        $userPrompt = "Analyze the following document and provide:\n1. A comprehensive summary (2-3 paragraphs)\n2. A list of 5-8 key points\n\nDocument content:\n" . $truncatedContent;
    } else {
        // Enhanced system prompt for comprehensive analysis - matching summarize style exactly
        $systemPrompt = 'You are an expert document analyst. Your task is to provide thorough, accurate, and detailed analysis of documents. Always respond in valid JSON format. Be precise, comprehensive, and ensure all extracted information is accurate and relevant to the document content. Return ONLY valid JSON with no markdown or code blocks.';
        
        switch ($analysisType) {
            case 'entities':
                $systemPrompt = 'You are an expert document analyst. Extract ALL named entities from documents. Always respond in valid JSON format with an "entities" array. Each entity should have "type" (Person, Organization, Location, Date, Money, etc.) and "text" fields. Return ONLY valid JSON with no markdown or code blocks.';
                $userPrompt = "Analyze the following document and extract ALL named entities:\n\nTitle: " . $documentTitle . "\n\nContent:\n" . $truncatedContent . "\n\nExtract ALL entities including people, organizations, locations, dates, monetary values, and other important entities. Return as JSON: {\"entities\": [{\"type\": \"Person\", \"text\": \"John Doe\"}, ...]}";
                break;
            case 'sentiment':
                $systemPrompt = 'You are an expert document analyst. Analyze sentiment and emotional tone of documents. Always respond in valid JSON format. Return ONLY valid JSON with no markdown or code blocks.';
                $userPrompt = "Analyze the sentiment and emotional tone of the following document:\n\nTitle: " . $documentTitle . "\n\nContent:\n" . $truncatedContent . "\n\nReturn as JSON: {\"sentiment\": {\"sentiment_label\": \"positive\", \"overall\": 0.75, \"tones\": [{\"tone\": \"Formal\", \"intensity\": 0.8}]}}";
                break;
            case 'classification':
                $systemPrompt = 'You are an expert document analyst. Classify documents into appropriate categories. Always respond in valid JSON format. Return ONLY valid JSON with no markdown or code blocks.';
                $userPrompt = "Classify the following document:\n\nTitle: " . $documentTitle . "\n\nContent:\n" . $truncatedContent . "\n\nReturn as JSON: {\"classification\": [{\"name\": \"Letter\", \"confidence\": 90}, ...]}";
                break;
            case 'keywords':
                $systemPrompt = 'You are an expert document analyst. Extract important keywords and key phrases from documents. Always respond in valid JSON format. Return ONLY valid JSON with no markdown or code blocks.';
                $userPrompt = "Extract the most important keywords and key phrases from the following document:\n\nTitle: " . $documentTitle . "\n\nContent:\n" . $truncatedContent . "\n\nReturn as JSON: {\"keywords\": [\"keyword1\", \"keyword2\", ...]}";
                break;
            case 'full':
            default:
                $systemPrompt = 'You are an expert document analyst. Your task is to provide comprehensive analysis of documents including classification, entities, sentiment, and keywords. Always respond in valid JSON format with the structure: {"classification": [{"name": "...", "confidence": 0-100}], "entities": [{"type": "...", "text": "..."}], "sentiment": {"sentiment_label": "...", "overall": -1 to 1, "tones": [{"tone": "...", "intensity": 0-1}]}, "keywords": ["..."]}. Return ONLY valid JSON with no markdown or code blocks.';
                $userPrompt = "Analyze the following document thoroughly:\n\nTitle: " . $documentTitle . "\n\nContent:\n" . $truncatedContent . "\n\nProvide:\n1. Document classification (categories with confidence 0-100%)\n2. Named entities (ALL people, organizations, locations, dates, monetary values, etc.)\n3. Sentiment analysis (label, score -1 to 1, emotional tones with intensities)\n4. Important keywords and key phrases\n\nReturn ONLY valid JSON with all these fields properly structured.";
                break;
        }
    }
    
    // Call Gemini API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Increase timeout to 5 minutes for comprehensive analysis (was 120)
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
        $data = [
        'systemInstruction' => [
            'parts' => [[ 'text' => $systemPrompt ]]
        ],
        'contents' => [
            [
                'parts' => [[ 'text' => $userPrompt ]]
            ]
        ],
        'generationConfig' => [
            'temperature' => $temperature,
            'maxOutputTokens' => max($maxTokens, 8000), // Significantly increased for comprehensive analysis (was 4000)
            'response_mime_type' => 'application/json',
            'responseSchema' => $operation === 'summarize' ? [
                'type' => 'object',
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'keyPoints' => ['type' => 'array', 'items' => ['type' => 'string']]
                ],
                'required' => ['summary', 'keyPoints']
            ] : [
                'type' => 'object',
                'properties' => [
                    'classification' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => [
                                    'type' => 'string',
                                    'description' => 'Category name (e.g., Report, Letter, Memo, Proposal, Contract, Policy)'
                                ],
                                'confidence' => [
                                    'type' => 'number',
                                    'description' => 'Confidence score from 0 to 100'
                                ]
                            ],
                            'required' => ['name', 'confidence']
                        ],
                        'description' => 'Array of document classifications with confidence scores'
                    ],
                    'entities' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'type' => [
                                    'type' => 'string',
                                    'description' => 'Entity type (Person, Organization, Location, Date, Money, etc.)'
                                ],
                                'text' => [
                                    'type' => 'string',
                                    'description' => 'Entity text content'
                                ]
                            ],
                            'required' => ['type', 'text']
                        ],
                        'description' => 'Array of all named entities found in the document'
                    ],
                    'sentiment' => [
                        'type' => 'object',
                        'properties' => [
                            'sentiment_label' => [
                                'type' => 'string',
                                'description' => 'Overall sentiment: positive, negative, or neutral'
                            ],
                            'overall' => [
                                'type' => 'number',
                                'description' => 'Sentiment score from -1 (very negative) to 1 (very positive)'
                            ],
                            'tones' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'tone' => [
                                            'type' => 'string',
                                            'description' => 'Emotional tone (formal, informal, urgent, professional, etc.)'
                                        ],
                                        'intensity' => [
                                            'type' => 'number',
                                            'description' => 'Tone intensity from 0 to 1'
                                        ]
                                    ],
                                    'required' => ['tone', 'intensity']
                                ],
                                'description' => 'Array of emotional tones with intensities'
                            ]
                        ],
                        'required' => ['sentiment_label', 'overall']
                    ],
                    'keywords' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Array of important keywords and key phrases from the document'
                    ]
                ],
                'required' => ['classification', 'entities', 'sentiment', 'keywords']
            ]
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE']
        ]
    ];
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Check for errors
    if ($statusCode !== 200 || !empty($error)) {
        error_log("Gemini API Error: $error, Status: $statusCode, Response: $response");
        return getMockResponse($operation === 'summarize' ? 'summary' : 'analysis');
    }
    
    // Parse the response
    try {
        $responseData = json_decode($response, true);
        
        if (isset($responseData['error'])) {
            error_log("Gemini API Error: " . json_encode($responseData['error']));
            return getMockResponse($operation === 'summarize' ? 'summary' : 'analysis');
        }
        
        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            error_log("Gemini API returned unexpected format: " . json_encode($responseData));
            return getMockResponse($operation === 'summarize' ? 'summary' : 'analysis');
        }
        
        $contentText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        // Handle empty response
        if (empty($contentText)) {
            $reason = $responseData['candidates'][0]['finishReason'] ?? '';
            $friendly = ($reason === 'MAX_TOKENS')
                ? 'AI could not return analysis (maximum output length was reached). Try reducing document size.'
                : 'AI could not return analysis. Please try again or check your input.';
            return [
                'success' => false,
                'message' => $friendly
            ];
        }
        
        // Remove markdown code blocks if present (like summarize does)
        $text2 = trim($contentText);
        if (strpos($text2, '```') === 0) {
            $text2 = preg_replace('/^```[a-zA-Z]*\n|\n```$/', '', $text2);
        }
        
        // Remove any leading/trailing non-JSON text (like summarize does)
        $text2 = preg_replace('/^(Here is|This is|The result is|JSON:|Response:|Analysis:|Summary:).*?\{/s', '{', $text2, 1);
        
        // Try to parse JSON output (matching summarize's approach)
        $jsonResult = json_decode($text2, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to pull first JSON object
            if (preg_match('/\{[\s\S]*\}/', $text2, $m)) {
                $jsonResult = json_decode($m[0], true);
            }
            
            // If still failed, try cleaning up common issues (like summarize does)
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Remove trailing comma before closing brace
                $text2 = preg_replace('/,\s*}/', '}', $text2);
                // Remove trailing comma before closing bracket
                $text2 = preg_replace('/,\s*]/', ']', $text2);
                $jsonResult = json_decode($text2, true);
            }
        }
        
        // If still not JSON, log and return error (matching summarize's approach)
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($jsonResult)) {
            error_log("Analysis JSON parse failed: " . json_last_error_msg() . " - Text preview: " . substr($text2, 0, 200));
            return [
                'success' => false,
                'message' => 'Failed to parse AI response. The AI may have returned invalid JSON. Please try again.'
            ];
        }
        
        // Ensure resultData is an array
        if (!is_array($jsonResult)) {
            $jsonResult = ['content' => $jsonResult];
        }
        
        // Add success flag
        $jsonResult['success'] = true;
        
        return $jsonResult;
    } catch (Exception $e) {
        error_log("Error processing Gemini response: " . $e->getMessage());
        return getMockResponse($operation === 'summarize' ? 'summary' : 'analysis');
    }
}

} catch (Exception $e) {
    // Clear any output buffer content
    ob_clean();
    
    // Log the error
    error_log("AI Document Processor Error: " . $e->getMessage());
    
    // Return JSON error response
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while processing the document: ' . $e->getMessage()
    ]);
    exit;
}
?> 