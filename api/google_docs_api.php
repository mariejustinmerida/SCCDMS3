<?php
/**
 * Google Docs API Endpoint
 * 
 * This file handles API requests for Google Docs operations.
 */

// Prevent any output before headers are sent
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Disable direct error output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Custom error handler to capture errors
function handleError($errno, $errstr, $errfile, $errline) {
    $error = [
        'success' => false,
        'error' => 'PHP Error: ' . $errstr,
        'debug_info' => [
            'file' => $errfile,
            'line' => $errline,
            'type' => $errno
        ]
    ];
    
    // Clear any output that might have been sent
    ob_clean();
    
    // Return JSON error
    echo json_encode($error);
    exit();
}

// Set the custom error handler
set_error_handler('handleError');

try {
    require_once '../includes/config.php';
    require_once '../includes/google_docs_manager.php';
    require_once '../includes/google_auth_handler.php';
    
    /**
     * Extract main content from document, filtering out headers/footers and extracting key topic
     */
    function extractMainContent($content) {
        if (empty($content)) {
            return '';
        }
        
        // Split into lines
        $lines = explode("\n", $content);
        $skipPatterns = [
            '/^saint\s+columban\s+college/i',
            '/^management\s+information\s+system/i',
            '/^pagadian\s+city/i',
            '/^scc\s+dms/i',
            '/^phone\s*:/i',
            '/^email\s*:/i',
            '/^address\s*:/i',
            '/^contact\s+information/i',
            '/^office\s+of\s+the\s+president/i',
            '/^department\s+of/i',
            '/^[a-z\s]+\s+office$/i',
            '/^(to|from|cc|bcc|subject|re|regarding|date):\s*/i',
            '/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/',
            '/^(january|february|march|april|may|june|july|august|september|october|november|december)\s+\d{1,2},?\s+\d{4}/i',
            '/^all\s+(faculty|staff|employees|members)/i',
            '/^dear\s+(faculty|staff|employees|members|colleagues|sir|madam|ma\'am)/i',
            '/^panagdait\s+sa\s+dios/i', // Greeting
            '/^this\s+institution$/i', // Common header text
            '/^(mr|mrs|ms|dr|prof|sir|madam|ma\'am)\.?\s+/i', // Titles like MRS., MR., DR.
            '/^dear\s+(mr|mrs|ms|dr|prof)/i', // Dear Mr., Dear Mrs.
        ];
        
        $subjectLine = '';
        $bodyLines = [];
        $skipCount = 0;
        $foundGreeting = false;
        $foundBodyStart = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                if ($foundBodyStart) {
                    $bodyLines[] = ''; // Keep paragraph breaks in body
                }
                continue;
            }
            
            // Skip header/footer patterns
            $shouldSkip = false;
            foreach ($skipPatterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $shouldSkip = true;
                    $skipCount++;
                    break;
                }
            }
            
            if ($shouldSkip) {
                continue;
            }
            
            // Look for Subject, Re, Regarding lines
            if (preg_match('/^(subject|re|regarding|topic):\s*(.+)/i', $line, $matches)) {
                $subjectLine = trim($matches[2]);
                $subjectLine = preg_replace('/^(to|from|all|dear)\s+/i', '', $subjectLine);
                continue;
            }
            
            // Skip greeting lines and recipient names
            if (preg_match('/^(dear|panagdait|greetings|hello|hi)/i', $line)) {
                $foundGreeting = true;
                continue;
            }
            
            // Skip lines with titles and names (MRS., MR., DR., etc.)
            if (preg_match('/^(mr|mrs|ms|dr|prof|sir|madam|ma\'am)\.?\s+[A-Z]/i', $line)) {
                continue;
            }
            
            // Skip lines that are just names (all caps or title case with commas)
            if (preg_match('/^[A-Z][A-Z\s,\.]+(?:CPA|MBA|PhD|MD|Jr|Sr|II|III|IV)?$/i', $line) && strlen($line) > 5 && strlen($line) < 50) {
                continue;
            }
            
            // After greeting, we should find the body
            if ($foundGreeting || $skipCount >= 3) {
                // This is likely body content
                if (strlen($line) > 15) { // Meaningful content line
                    $foundBodyStart = true;
                    $bodyLines[] = $line;
                } else if ($foundBodyStart) {
                    // Keep short lines if we're already in body (might be part of sentence)
                    $bodyLines[] = $line;
                }
            }
        }
        
        // Extract key topic from body - look for phrases that indicate the topic
        $bodyText = implode(' ', $bodyLines);
        
        // Remove common opening phrases that are NOT the topic
        $bodyText = preg_replace('/^(this\s+(?:memorandum|letter|document|notice)\s+(?:serves\s+to\s+)?(?:is\s+to\s+)?(?:aims\s+to\s+)?(?:will\s+)?(?:informs?|announces?|requests?|informs?|notifies?)\s+(?:all|the)?\s*[^\.]+)/i', '', $bodyText);
        $bodyText = preg_replace('/^(we\s+are\s+writing\s+to\s+(?:inform|notify|announce|request)\s+(?:you|all)\s+(?:of|about|regarding)\s*[^\.]+)/i', '', $bodyText);
        $bodyText = preg_replace('/^(this\s+is\s+to\s+(?:inform|notify|announce|request)\s+(?:you|all)\s+(?:of|about|regarding)\s*[^\.]+)/i', '', $bodyText);
        $bodyText = preg_replace('/^(the\s+purpose\s+of\s+this\s+(?:memorandum|letter|document)\s+is\s+to\s+[^\.]+)/i', '', $bodyText);
        // Remove "As part of our ongoing commitment..." pattern
        $bodyText = preg_replace('/^as\s+part\s+of\s+our\s+ongoing\s+commitment\s+to\s+excellence,\s+we\s+have\s+identified\s+[^\.]+/i', '', $bodyText);
        $bodyText = preg_replace('/^as\s+part\s+of\s+our\s+ongoing\s+commitment[^\.]+/i', '', $bodyText);
        $bodyText = preg_replace('/^as\s+part\s+of[^\.]+/i', '', $bodyText);
        
        // Look for key phrases that indicate the actual topic (after removing opening fluff)
        $topicPatterns = [
            '/inform\s+(?:you|all)\s+of\s+(?:an|a|the)?\s*([^\.]+)/i',
            '/inform\s+(?:you|all)\s+about\s+(?:an|a|the)?\s*([^\.]+)/i',
            '/announce\s+(?:an|a|the)?\s*([^\.]+)/i',
            '/regarding\s+(?:the\s+)?([^\.]+)/i',
            '/about\s+(?:the\s+)?([^\.]+)/i',
            '/subject[:\s]+([^\.]+)/i',
            '/concerning\s+(?:the\s+)?([^\.]+)/i',
        ];
        
        $extractedTopic = '';
        foreach ($topicPatterns as $pattern) {
            if (preg_match($pattern, $bodyText, $matches)) {
                $extractedTopic = trim($matches[1]);
                // Clean up the topic - remove common filler words
                $extractedTopic = preg_replace('/^(upcoming|scheduled|planned|regarding|about|the|an|a|all|of|for|to)\s+/i', '', $extractedTopic);
                // Remove trailing filler
                $extractedTopic = preg_replace('/\s+(members?|faculty|staff|employees?|colleagues?)$/i', '', $extractedTopic);
                if (strlen($extractedTopic) > 10 && strlen($extractedTopic) < 150) {
                    break;
                }
            }
        }
        
        // If no topic found via patterns, extract from first meaningful sentence after opening phrases
        if (empty($extractedTopic) && !empty($bodyText)) {
            // Split into sentences
            $sentences = preg_split('/([.!?]+)/', $bodyText, 3, PREG_SPLIT_DELIM_CAPTURE);
            if (count($sentences) >= 3) {
                // Take first or second sentence (skip opening fluff)
                $meaningfulSentence = trim($sentences[0] . ($sentences[1] ?? ''));
                if (strlen($meaningfulSentence) > 20 && strlen($meaningfulSentence) < 200) {
                    // Extract key nouns and verbs
                    $words = preg_split('/\s+/', $meaningfulSentence);
                    $keywords = array_filter($words, function($w) {
                        $w = strtolower(trim($w, '.,!?;:'));
                        return strlen($w) > 3 && 
                               !preg_match('/^(this|that|these|those|the|an|a|is|are|was|were|will|would|should|can|could|must|may|might|have|has|had|been|being|been|do|does|did|said|says|tell|tells|told|inform|informs|informed|announce|announces|announced|request|requests|requested|serves|serve|served|aims|aim|aimed|purpose|member|members|faculty|staff|employees|colleagues|all|you|we|our|your|their|them|they)$/i', $w);
                    });
                    if (count($keywords) >= 2) {
                        $extractedTopic = implode(' ', array_slice($keywords, 0, 8)); // Take up to 8 keywords
                    }
                }
            }
        }
        
        // Clean up extracted topic - remove filler words and make it concise
        if (!empty($extractedTopic)) {
            // Remove common filler words at start
            $extractedTopic = preg_replace('/^(part|ongoing|commitment|excellence|identified|several|critical|proposals|that|are|not|included|in|the|annual|budget|but|essential|for|enhancing|our|it|infrastructure|specifically|we|propose|two|initiatives|the|managed|firewall|and|additional|internet|bandwidth)\s+/i', '', $extractedTopic);
            // Remove trailing filler
            $extractedTopic = preg_replace('/\s+(members?|faculty|staff|employees?|colleagues?|proposals?|initiatives?|systems?|services?)$/i', '', $extractedTopic);
            // Limit to first 100 characters
            $extractedTopic = substr($extractedTopic, 0, 100);
        }
        
        // If we found a subject line, use it
        if (!empty($subjectLine) && strlen($subjectLine) > 5) {
            $result = "Subject: " . $subjectLine;
            if (!empty($bodyText)) {
                $result .= "\n\n" . substr($bodyText, 0, 500); // Include first 500 chars of body for context
            }
        } else if (!empty($extractedTopic) && strlen($extractedTopic) > 10) {
            // Only use extracted topic if it's meaningful
            // Clean it up more - extract key nouns
            $words = preg_split('/\s+/', $extractedTopic);
            $keywords = array_filter($words, function($w) {
                $w = strtolower(trim($w, '.,!?;:'));
                return strlen($w) > 3 && 
                       !preg_match('/^(the|and|for|are|but|not|you|all|can|her|was|one|our|out|day|get|has|him|his|how|its|may|new|now|old|see|two|who|way|use|this|that|these|those|will|would|should|can|could|must|may|might|have|has|had|been|being|do|does|did|part|ongoing|commitment|excellence|identified|several|critical|proposals|that|are|not|included|in|the|annual|budget|but|essential|for|enhancing|our|it|infrastructure|specifically|we|propose|two|initiatives|the|managed|firewall|and|additional|internet|bandwidth|systems?|services?)$/i', $w);
            });
            if (count($keywords) >= 2) {
                $cleanTopic = implode(' ', array_slice($keywords, 0, 6)); // Take up to 6 keywords
                $result = "Topic: " . $cleanTopic;
            } else {
                $result = substr($bodyText, 0, 800); // Fallback to body text
            }
            if (!empty($bodyText)) {
                $result .= "\n\n" . substr($bodyText, 0, 500);
            }
        } else {
            // Use body text, but skip first few words that might be greetings
            $bodyText = preg_replace('/^(we\s+are|this\s+is|i\s+am|we\s+would|part\s+of|ongoing\s+commitment)\s+/i', '', $bodyText);
            $result = substr($bodyText, 0, 800); // Take meaningful chunk
        }
        
        return trim($result);
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'error' => 'User not logged in'
        ]);
        exit();
    }

    $userId = $_SESSION['user_id'];

    // Get the action from the request
    $action = '';
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

    if (strpos($contentType, 'application/json') !== false) {
        $json_params = file_get_contents("php://input");
        // Decode but don't overwrite the global request variables yet
        $decoded_params = json_decode($json_params, true); 
        $action = $decoded_params['action'] ?? '';
    } else {
        $action = $_REQUEST['action'] ?? '';
    }

    // Check authentication before proceeding with any action
    $authHandler = new GoogleAuthHandler();
    $isConnected = $authHandler->hasValidToken($userId);

    if (!$isConnected && $action !== 'check_auth') {
        // Log the authentication failure
        error_log("Google Docs API: Authentication failed for user ID $userId");
        
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required: Your Google Docs connection has expired or is invalid. Please reconnect your account.',
            'auth_required' => true
        ]);
        exit();
    }

    // Initialize Google Docs manager
    try {
        $docsManager = new GoogleDocsManager($userId);
    } catch (Exception $e) {
        // Log the error with detailed information
        error_log("Google Docs API: Failed to initialize Google Docs manager: " . $e->getMessage());
        error_log("Error details: " . $e->getTraceAsString());
        
        // Check if this is an authentication error
        if (strpos($e->getMessage(), 'authentication') !== false || 
            strpos($e->getMessage(), 'expired') !== false || 
            strpos($e->getMessage(), 'token') !== false) {
            
            echo json_encode([
                'success' => false,
                'error' => 'Authentication error: Your Google Docs connection has expired. Please reconnect your account.',
                'auth_required' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to initialize Google Docs manager: ' . $e->getMessage(),
                'debug_info' => [
                    'error_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ]);
        }
        exit();
    }
    
    switch ($action) {
        case 'check_auth':
            // Check if user is authenticated
            $authHandler = new GoogleAuthHandler();
            $isConnected = $authHandler->hasValidToken($userId);
            
            echo json_encode([
                'success' => true,
                'is_connected' => $isConnected
            ]);
            break;
            
        case 'create_document':
            // Create a new document
            $title = $_REQUEST['title'] ?? 'Untitled Document';
            $content = $_REQUEST['content'] ?? '';
            
            try {
                $docInfo = $docsManager->createDocument($title, $content);
                
                echo json_encode([
                    'success' => true,
                    'document' => $docInfo
                ]);
            } catch (Exception $e) {
                // Log the error
                error_log('Error creating Google Doc: ' . $e->getMessage());
                
                echo json_encode([
                    'success' => false,
                    'error' => 'Error creating Google Doc: ' . $e->getMessage(),
                    'debug_info' => [
                        'error_type' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]
                ]);
            }
            break;
            
        case 'create_from_template':
            // Handle JSON request body
            if (strpos($contentType, 'application/json') !== false) {
                // We already decoded this, just use it
                $templateType = $decoded_params['template_type'] ?? '';
                $data = $decoded_params['data'] ?? [];
                $documentId = $decoded_params['document_id'] ?? '';
            } else {
                // Fallback for form-data
                $templateType = $_REQUEST['template_type'] ?? '';
                $data = isset($_REQUEST['data']) ? json_decode($_REQUEST['data'], true) : [];
                $documentId = $_REQUEST['document_id'] ?? '';
            }

            // Check for JSON decoding errors. This check is now redundant for JSON requests
            // as we would have failed earlier, but good for form-data.
            if (json_last_error() !== JSON_ERROR_NONE && strpos($contentType, 'application/json') === false) {
                error_log('Google Docs API: JSON decode error in form-data: ' . json_last_error_msg());
                throw new Exception('Invalid data format received in form-data: ' . json_last_error_msg());
            }
            
            if (empty($templateType)) {
                throw new Exception('Template type is required');
            }
            
            // Enrich template data with current user and date if missing
            if (!is_array($data)) { $data = []; }
            $today = date('F j, Y');
            if (empty($data['date'])) { $data['date'] = $today; }

            // Try to get current user info
            $currentUser = null;
            if (isset($_SESSION) && isset($_SESSION['user_id'])) {
                $uid = (int)$_SESSION['user_id'];
                // Check what columns exist in users table first
                $check_columns = $conn->query("SHOW COLUMNS FROM users");
                $user_columns = [];
                while ($col = $check_columns->fetch_assoc()) {
                    $user_columns[] = $col['Field'];
                }
                
                // Build query based on available columns (robust against missing u.position)
                $hasFullName = in_array('full_name', $user_columns);
                $hasPosition = in_array('position', $user_columns);

                $selectParts = [];
                if ($hasFullName) {
                    $selectParts[] = 'u.full_name';
                } else {
                    $selectParts[] = 'u.username AS full_name';
                }
                // Always return a position field; if users.position doesn't exist, fall back to roles.role_name
                $joinRoles = false;
                if ($hasPosition) {
                    $selectParts[] = 'u.position';
                } else {
                    $selectParts[] = 'r.role_name AS position';
                    $joinRoles = true;
                }
                $selectParts[] = 'o.office_name';

                $joins = 'LEFT JOIN offices o ON u.office_id = o.office_id';
                if ($joinRoles) {
                    $joins .= ' LEFT JOIN roles r ON u.role_id = r.role_id';
                }

                $sqlUser = 'SELECT ' . implode(', ', $selectParts) . ' FROM users u ' . $joins . ' WHERE u.user_id = ? LIMIT 1';
                $stmtUser = $conn->prepare($sqlUser);
                if ($stmtUser) {
                    $stmtUser->bind_param('i', $uid);
                    if ($stmtUser->execute()) {
                        $res = $stmtUser->get_result();
                        $currentUser = $res ? $res->fetch_assoc() : null;
                    }
                }
            }
            if ($currentUser) {
                if (empty($data['sender_name'])) { 
                    $data['sender_name'] = $currentUser['full_name'] ?? $currentUser['username'] ?? ''; 
                }
                if (empty($data['sender_position'])) { 
                    $data['sender_position'] = $currentUser['position'] ?? ''; 
                }
                if (empty($data['sender_office'])) { 
                    $data['sender_office'] = $currentUser['office_name'] ?? ''; 
                }
            }

            if (!empty($documentId)) {
                // Apply template to existing document
                $content = $docsManager->getTemplateContent($templateType);
                
                // Replace placeholders with data
                if (!empty($data)) {
                    $content = $docsManager->replacePlaceholders($content, $data);
                }
                
                // Update the document with the template content
                $success = $docsManager->updateDocumentContent($documentId, $content);
                
                echo json_encode([
                    'success' => $success,
                    'message' => 'Template applied to document'
                ]);
            } else {
                // Create a new document from template
                $docInfo = $docsManager->createDocumentFromTemplate($templateType, $data);
                
                echo json_encode([
                    'success' => true,
                    'document' => $docInfo
                ]);
            }
            break;
            
        case 'generate_with_ai':
            // Generate a document using AI
            $prompt = $_REQUEST['prompt'] ?? '';
            $documentId = $_REQUEST['document_id'] ?? '';
            
            if (empty($prompt)) {
                throw new Exception('Prompt is required');
            }
            
            try {
                // Enrich prompt with current date and user signature defaults
                $today = date('F j, Y');
                $sigLine = '';
                if (isset($_SESSION) && isset($_SESSION['user_id'])) {
                    $uid = (int)$_SESSION['user_id'];
                    // Detect available columns in users table to avoid unknown column errors
                    $user_columns = [];
                    if ($resultCols = $conn->query("SHOW COLUMNS FROM users")) {
                        while ($col = $resultCols->fetch_assoc()) {
                            $user_columns[] = $col['Field'];
                        }
                    }

                    $hasFullName = in_array('full_name', $user_columns);
                    $hasPosition = in_array('position', $user_columns);

                    $selectFields = [];
                    if ($hasFullName) {
                        $selectFields[] = 'u.full_name';
                    } else {
                        // Fallback to username but alias as full_name for downstream code
                        $selectFields[] = 'u.username AS full_name';
                    }
                    // Position: prefer users.position; otherwise use role_name as position
                    $joinRoles = false;
                    if ($hasPosition) {
                        $selectFields[] = 'u.position';
                    } else {
                        $selectFields[] = 'r.role_name AS position';
                        $joinRoles = true;
                    }
                    $selectFields[] = 'o.office_name';

                    $joins = 'LEFT JOIN offices o ON u.office_id = o.office_id';
                    if ($joinRoles) {
                        $joins .= ' LEFT JOIN roles r ON u.role_id = r.role_id';
                    }

                    $sql = 'SELECT ' . implode(', ', $selectFields) . ' FROM users u ' . $joins . ' WHERE u.user_id = ? LIMIT 1';
                    $stmtUser = $conn->prepare($sql);
                    if ($stmtUser) {
                        $stmtUser->bind_param('i', $uid);
                        if ($stmtUser->execute()) {
                            $res = $stmtUser->get_result();
                            if ($res && ($row = $res->fetch_assoc())) {
                                $sigParts = [];
                                if (!empty($row['full_name'])) { $sigParts[] = $row['full_name']; }
                                if (!empty($row['position'])) { $sigParts[] = $row['position']; }
                                if (!empty($row['office_name'])) { $sigParts[] = $row['office_name']; }
                                $sigLine = implode(', ', $sigParts);
                            }
                        }
                        $stmtUser->close();
                    }
                }
                if ($sigLine) {
                    $prompt .= "\n\nUse the current date ($today) and signatory details: $sigLine.";
                } else {
                    $prompt .= "\n\nUse the current date ($today).";
            }
            
            // If document ID is provided, update that document
            if (!empty($documentId)) {
                // Generate content based on prompt
                $aiContent = generateAIContent($prompt);
                
                // Update the document with the AI-generated content
                $success = $docsManager->updateDocumentContent($documentId, $aiContent);
                
                // Return the generated content so frontend can use it for title generation
                echo json_encode([
                    'success' => $success,
                    'message' => 'Document updated with AI-generated content',
                    'content' => $aiContent, // Include generated content for immediate title generation
                    'document_id' => $documentId
                ]);
            } else {
                // Create a new document with AI-generated content
                $docInfo = $docsManager->generateDocumentWithAI($prompt);
                
                echo json_encode([
                    'success' => true,
                    'document' => $docInfo
                    ]);
                }
            } catch (Exception $e) {
                error_log('AI Generation Error: ' . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'message' => 'Failed to generate document: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'suggest_title':
            // Suggest a concise document title using Gemini
            $documentId = $_REQUEST['document_id'] ?? '';
            $prompt = $_REQUEST['prompt'] ?? '';
            $templateType = $_REQUEST['template_type'] ?? '';
            $documentType = $_REQUEST['document_type'] ?? $_REQUEST['doc_type'] ?? '';
            $senderInfo = $_REQUEST['sender_info'] ?? '';
            // Accept content directly if provided (avoids reading from Google Docs)
            $providedContent = $_REQUEST['content'] ?? '';

            // Fetch Gemini API key (env or settings)
            $api_key = getenv('GEMINI_API_KEY');
            if (empty($api_key)) {
                $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name = 'gemini_api_key'");
                if ($stmt && $stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $api_key = $row['setting_value'];
                    }
                }
            }
            if (empty($api_key)) {
                throw new Exception('Gemini API key not configured. Please set it in AI Settings.');
            }

            // Use provided content if available, otherwise try to read from Google Docs
            $content = $providedContent;
            if (empty($content) && !empty($documentId)) {
                try {
                    $content = $docsManager->getDocumentContent($documentId) ?? '';
                } catch (Exception $e) {
                    error_log('suggest_title getDocumentContent error: ' . $e->getMessage());
                }
            }

            // Strip HTML tags from content to get clean text for title generation
            if (!empty($content)) {
                // Convert <br> and <br/> tags to newlines
                $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
                // Remove all remaining HTML tags
                $content = strip_tags($content);
                // Clean up multiple newlines and whitespace
                $content = preg_replace('/\n\s*\n/', "\n", $content);
                $content = trim($content);
                
                // Extract meaningful content - remove headers/footers and focus on body
                $content = extractMainContent($content);
            }

            // Build comprehensive context for title generation
            $contextBits = [];
            if (!empty($documentType)) { 
                $contextBits[] = "Document Type: {$documentType}"; 
            }
            if (!empty($templateType)) { 
                $contextBits[] = "Template Type: {$templateType}"; 
            }
            if (!empty($senderInfo)) { 
                $contextBits[] = "From/Sender: {$senderInfo}"; 
            }
            if (!empty($prompt)) { 
                $contextBits[] = "User Request: {$prompt}"; 
            }
            $meta = implode("\n", $contextBits);

            // Truncate content to keep payload reasonable, but preserve important parts
            $contentLength = strlen($content);
            if ($contentLength > 10000) {
                // Keep first 5000 chars and last 5000 chars to preserve both intro and conclusion
                $firstPart = substr($content, 0, 5000);
                $lastPart = substr($content, -5000);
                $content = $firstPart . "\n\n[... content truncated ...]\n\n" . $lastPart;
            } elseif ($contentLength > 8000) {
                $content = substr($content, 0, 8000);
            }

            // Enhanced title generation prompt with emphasis on simple, direct titles
            $titleInstruction = "You are an expert at creating simple, direct, professional document titles.\n\n"
                . "CRITICAL RULES:\n"
                . "1. DO NOT copy the first sentence or opening phrase - CREATE a simple title\n"
                . "2. NEVER use phrases like 'This memorandum serves to inform...' or 'We are writing to...' - these are NOT titles\n"
                . "3. KEEP IT SIMPLE - 3-6 words is ideal. Shorter is often better if it's clear\n"
                . "4. IGNORE ALL metadata: dates, 'TO:', 'FROM:', recipient names, titles (MRS., MR., DR.), letter headers, institutional headers, greetings\n"
                . "5. NEVER use names or titles like 'MRS.', 'MR.', 'DR.', 'VIRGINIA RUBEN', etc. - these are NOT titles\n"
                . "6. Focus on the DOCUMENT TYPE and TOPIC - e.g., 'Budget Request', 'Leave Request', 'Internal Memo', 'Facility Inspection'\n"
                . "7. If it's a memo, title it simply: 'Internal Memo' or 'Memo Faculty' or 'Faculty Memo'\n"
                . "8. If it's a letter, use: 'Budget Request', 'Leave Request', or just the topic\n"
                . "9. NEVER include dates, recipient names, titles (MRS/MR/DR), 'TO:', 'FROM:', or any letter metadata\n"
                . "10. NEVER use institutional names like 'Saint Columban College' or 'Management Information System'\n"
                . "11. Use proper Title Case (capitalize important words)\n"
                . "12. MAXIMUM 6 WORDS - But prefer 3-5 words when clear and simple\n"
                . "13. If the user provided a specific request/prompt, use that as the primary source\n\n"
                . "FORMAT: Return ONLY a JSON object: {\"title\": \"Simple Title Here\"}\n"
                . "Do NOT include quotes around JSON, do NOT use markdown, do NOT add trailing punctuation.\n\n"
                . "HOW TO CREATE THE TITLE:\n"
                . "1. If user provided a prompt like 'Write internal memo faculty', use that directly: 'Internal Memo Faculty'\n"
                . "2. If there's a 'Subject:' line, extract the KEY TOPIC from it (not the whole sentence)\n"
                . "3. If there's a 'Topic:' line, extract only the KEY NOUNS - ignore filler words like 'part', 'ongoing', 'commitment', 'excellence', 'identified', etc.\n"
                . "4. Identify the document type: Memo? Letter? Request? Announcement?\n"
                . "5. Identify the main subject: Budget? Leave? Inspection? Meeting? Evaluation?\n"
                . "6. Create a simple title: [Document Type] + [Main Subject] OR just [Main Subject]\n"
                . "7. Examples: 'Budget Request', 'Leave Request', 'Internal Memo', 'Facility Inspection', 'Faculty Meeting'\n"
                . "8. DO NOT copy text fragments - CREATE a clean, simple title with proper words\n"
                . "9. DO NOT use phrases like 'part ongoing commitment' - extract the actual topic\n\n"
                . "EXCELLENT SIMPLE TITLE EXAMPLES:\n"
                . "- 'Internal Memo Faculty' (3 words - perfect for memos to faculty)\n"
                . "- 'Laboratory Inspection Notice' (3 words)\n"
                . "- 'Leave Request Form' (3 words)\n"
                . "- 'Budget Request Proposal' (3 words)\n"
                . "- 'Faculty Evaluation Notice' (3 words)\n"
                . "- 'Travel Authorization Request' (3 words)\n"
                . "- 'Meeting Announcement Faculty' (3 words)\n"
                . "- 'Advent Recollection 2024' (3 words)\n\n"
                . "TERRIBLE TITLES (DO NOT CREATE THESE - RETURN EMPTY INSTEAD):\n"
                . "- 'As part of our ongoing commitment to excellence, we have...' (this is a sentence, not a title)\n"
                . "- 'Topic: part ongoing commitment' (this is a fragment, not a title)\n"
                . "- 'part ongoing commitment excellence' (incomplete fragments)\n"
                . "- 'identified several critical proposals' (sentence fragments)\n"
                . "- 'We have identified several critical proposals' (opening sentence)\n"
                . "- 'MRS.' or 'MR.' or 'DR.' (these are titles, not document titles)\n"
                . "- 'VIRGINIA RUBEN' or any person's name\n"
                . "- 'This memorandum serves to inform'\n"
                . "- 'We are writing to inform you'\n"
                . "- 'This Institution'\n"
                . "- 'Panagdait sa Dios'\n"
                . "- 'November 4, 2025 TO ALL FACULTY'\n"
                . "- 'Dear Faculty Members'\n"
                . "- 'Write internal memo faculty' (this is the prompt, not a title)\n"
                . "- Any opening sentence or phrase from the document\n"
                . "- Any title with dates, TO:, FROM:, greetings, recipient names, or person titles\n"
                . "- Any title longer than 6 words\n"
                . "- Any single word that's a name or title\n"
                . "- Any title that starts with 'Topic:' or 'Subject:' - remove those prefixes\n"
                . "- Any incomplete fragments or random word combinations\n"
                . "- Any title that starts with 'As part of', 'We have', 'We are', 'We propose'\n\n"
                . "IMPORTANT: If you cannot create a proper 3-6 word title that describes the document type and topic, return an empty title (empty string) rather than creating a bad title.\n\n"
                . "Remember: KEEP IT SIMPLE. For memos to faculty, use 'Internal Memo Faculty'. Now analyze the content and create a simple 3-6 word title:";

            // Prioritize user prompt if available - it's the most reliable source
            $analysisContent = '';
            if (!empty($prompt)) {
                // Clean up the prompt to extract a simple title
                $cleanPrompt = strtolower(trim($prompt));
                // If prompt is like "Write internal memo faculty", extract "Internal Memo Faculty"
                if (preg_match('/write\s+(?:an?\s+)?(internal\s+)?(memo|letter|request|announcement|notice)\s+(?:to\s+)?(.+)/i', $prompt, $matches)) {
                    $docType = ucwords(trim($matches[2]));
                    $audience = ucwords(trim($matches[3]));
                    $suggestedTitle = $matches[1] ? 'Internal ' . $docType . ' ' . $audience : $docType . ' ' . $audience;
                    $analysisContent .= "USER'S REQUEST TRANSLATED TO TITLE: " . $suggestedTitle . "\n\n";
                }
                $analysisContent .= "USER'S ORIGINAL REQUEST:\n" . $prompt . "\n\n";
            }
            if (!empty($meta)) {
                $analysisContent .= "DOCUMENT METADATA:\n" . $meta . "\n\n";
            }
            $analysisContent .= "DOCUMENT BODY (ignore headers/footers):\n" . $content;
            
            $geminiPayload = $titleInstruction . "\n\n" . $analysisContent;

            try {
                $responseText = callGeminiAPITitle($geminiPayload, $api_key);
                
                // callGeminiAPITitle now returns the title directly (already cleaned and parsed)
                $suggested = $responseText;
                
                // Final cleanups - remove quotes, extra whitespace, etc.
                $suggested = trim($suggested, " \t\r\n\"'\-_.,;:");
                
                // Remove "Topic:" or "Subject:" prefixes if present
                $suggested = preg_replace('/^(topic|subject):\s*/i', '', $suggested);
                
                // Remove common opening phrases that are NOT titles - AGGRESSIVE FILTERING
                $suggested = preg_replace('/^(as\s+part\s+of\s+our\s+ongoing\s+commitment\s+to\s+excellence,\s+we\s+have)/i', '', $suggested);
                $suggested = preg_replace('/^(as\s+part\s+of\s+our\s+ongoing\s+commitment)/i', '', $suggested);
                $suggested = preg_replace('/^(as\s+part\s+of)/i', '', $suggested);
                $suggested = preg_replace('/^(we\s+have\s+identified|we\s+are\s+writing|this\s+memorandum\s+serves|we\s+propose|part\s+of\s+our|ongoing\s+commitment|excellence|identified|several|critical|proposals|that|are|not|included|in|the|annual|budget|but|essential|for|enhancing|our|it|infrastructure|specifically|we|propose|two|initiatives|the|managed|firewall|and|additional|internet|bandwidth)\s+/i', '', $suggested);
                
                // Reject if it starts with opening sentence patterns - STRICT CHECK
                if (preg_match('/^(as\s+part|we\s+have|we\s+are|this\s+memorandum|we\s+propose|part\s+of|ongoing\s+commitment|excellence,\s+we)/i', $suggested)) {
                    $suggested = ''; // Reject this title immediately
                }
                
                // Also check if it contains the full problematic phrase anywhere
                if (preg_match('/as\s+part\s+of\s+our\s+ongoing\s+commitment/i', $suggested)) {
                    $suggested = ''; // Reject if this phrase appears anywhere
                }
                
                // Enforce 5-6 word maximum limit
                $words = preg_split('/\s+/', $suggested);
                // Filter out filler words
                $words = array_filter($words, function($w) {
                    $w = strtolower(trim($w, '.,!?;:'));
                    return strlen($w) > 2 && !preg_match('/^(part|ongoing|commitment|excellence|identified|several|critical|proposals|that|are|not|included|in|the|annual|budget|but|essential|for|enhancing|our|it|infrastructure|specifically|we|propose|two|initiatives|the|managed|firewall|and|additional|internet|bandwidth|systems?|services?)$/i', $w);
                });
                $words = array_values($words); // Reindex array
                
                if (count($words) > 6) {
                    // Take first 6 meaningful words
                    $suggested = implode(' ', array_slice($words, 0, 6));
                } else if (count($words) > 0) {
                    $suggested = implode(' ', $words);
                }
                
                // If result is still garbage, try to extract from prompt or identify document type
                if (empty($suggested) || strlen($suggested) < 3 || preg_match('/^(part|ongoing|commitment|excellence|identified|topic|subject)/i', $suggested)) {
                    // First, try to identify document type from content
                    $docTypeKeywords = [
                        'budget' => 'Budget Request',
                        'firewall' => 'Budget Request',
                        'bandwidth' => 'Budget Request',
                        'leave' => 'Leave Request',
                        'inspection' => 'Inspection Notice',
                        'laboratory' => 'Laboratory Inspection',
                        'facility' => 'Facility Inspection',
                        'meeting' => 'Meeting Notice',
                        'memo' => 'Internal Memo',
                        'memorandum' => 'Internal Memo',
                        'evaluation' => 'Evaluation Notice',
                        'travel' => 'Travel Request',
                        'warning' => 'Warning Letter',
                    ];
                    
                    $contentLower = strtolower($content);
                    $foundType = '';
                    foreach ($docTypeKeywords as $keyword => $title) {
                        if (strpos($contentLower, $keyword) !== false) {
                            $foundType = $title;
                            break;
                        }
                    }
                    
                    if (!empty($foundType)) {
                        $suggested = $foundType;
                    } else if (!empty($prompt)) {
                        // Try extracting from prompt
                        $promptLower = strtolower($prompt);
                        foreach ($docTypeKeywords as $keyword => $title) {
                            if (strpos($promptLower, $keyword) !== false) {
                                $suggested = $title;
                                break;
                            }
                        }
                    }
                    
                    // If still nothing, try extracting key nouns from content
                    if (empty($suggested)) {
                        $contentWords = preg_split('/\s+/', $content);
                        $contentWords = array_filter($contentWords, function($w) {
                            $w = strtolower(trim($w, '.,!?;:'));
                            return strlen($w) > 4 && 
                                   !preg_match('/^(part|ongoing|commitment|excellence|identified|several|critical|proposals|that|are|not|included|in|the|annual|budget|but|essential|for|enhancing|our|it|infrastructure|specifically|we|propose|two|initiatives|the|managed|firewall|and|additional|internet|bandwidth|systems?|services?|this|that|these|those|will|would|should|can|could|must|may|might|have|has|had|been|being|do|does|did|memorandum|serves|inform|announce|request|informs|informed|announces|announced|requests|requested|members|faculty|staff|employees)$/i', $w);
                        });
                        // Look for meaningful document-related words
                        $meaningfulWords = ['budget', 'request', 'proposal', 'leave', 'inspection', 'meeting', 'memo', 'letter', 'notice', 'evaluation', 'travel', 'authorization', 'facility', 'laboratory'];
                        $foundWords = [];
                        foreach ($contentWords as $word) {
                            if (in_array($word, $meaningfulWords) || (strlen($word) > 5 && !in_array($word, ['faculty', 'members', 'staff', 'employees']))) {
                                $foundWords[] = ucwords($word);
                                if (count($foundWords) >= 3) break;
                            }
                        }
                        if (count($foundWords) >= 2) {
                            $suggested = implode(' ', $foundWords);
                        }
                    }
                }
                
                // Ensure it's not empty and has reasonable length
                if (empty($suggested) || strlen($suggested) < 3) {
                    // Fallback: generate from content keywords (max 6 words)
                    $contentWords = preg_split('/\s+/', $content);
                    $contentWords = array_filter($contentWords, function($w) {
                        $w = strtolower(trim($w, '.,!?;:'));
                        return strlen($w) > 3 && !preg_match('/^(the|and|for|are|but|not|you|all|can|her|was|one|our|out|day|get|has|him|his|how|its|may|new|now|old|see|two|who|way|use|her|she|him|has|had|how|who|its|may|now|old|see|two|way|use|this|that|these|those|will|would|should|from|with|about|into|upon|this|that|memorandum|serves|inform|announce|request|informs|informed|announces|announced|requests|requested|members|faculty|staff|employees)$/i', $w);
                    });
                    $contentWords = array_slice($contentWords, 0, 6); // Max 6 words
                    $suggested = implode(' ', $contentWords);
                    if (empty($suggested)) {
                        $suggested = 'Document';
                    }
                }
                
                // Final word count check - ensure it's 6 words or less
                $words = preg_split('/\s+/', $suggested);
                if (count($words) > 6) {
                    $suggested = implode(' ', array_slice($words, 0, 6));
                }
                
                // Remove common opening phrases that might have slipped through
                $suggested = preg_replace('/^(this\s+(?:memorandum|letter|document|notice)\s+(?:serves\s+to\s+)?(?:informs?|announces?|requests?))/i', '', $suggested);
                $suggested = preg_replace('/^(we\s+are\s+writing\s+to)/i', '', $suggested);
                
                // Remove titles and names (MRS., MR., DR., etc.)
                $suggested = preg_replace('/\b(mr|mrs|ms|dr|prof|sir|madam|ma\'am)\.?\s+[A-Z][A-Z\s,\.]+/i', '', $suggested);
                $suggested = preg_replace('/\b(mr|mrs|ms|dr|prof|sir|madam|ma\'am)\./i', '', $suggested);
                
                // Remove common name patterns
                $suggested = preg_replace('/\b[A-Z][A-Z\s,\.]+(?:CPA|MBA|PhD|MD|Jr|Sr|II|III|IV)\b/i', '', $suggested);
                
                $suggested = preg_replace('/\s+/', ' ', $suggested);
                $suggested = trim($suggested);
                
                // Final cleanup - remove any remaining metadata patterns
                // Remove dates, TO:, FROM:, etc. that might have slipped through
                $suggested = preg_replace('/\b(november|december|january|february|march|april|may|june|july|august|september|october)\s+\d{1,2},?\s+\d{4}\b/i', '', $suggested);
                $suggested = preg_replace('/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/', '', $suggested);
                $suggested = preg_replace('/\b(to|from|date|cc|bcc):\s*/i', '', $suggested);
                $suggested = preg_replace('/\ball\s+(faculty|staff|employees|members)\b/i', '', $suggested);
                $suggested = preg_replace('/\bsaint\s+columban\s+college\b/i', '', $suggested);
                $suggested = preg_replace('/\bmanagement\s+information\s+system\b/i', '', $suggested);
                $suggested = preg_replace('/\bdear\s+(mr|mrs|ms|dr|ma\'am|sir)\b/i', '', $suggested);
                $suggested = preg_replace('/\s+/', ' ', $suggested); // Clean up multiple spaces
                $suggested = trim($suggested);
                
                // If title is just a single word or seems like a name/title, reject it
                $words = preg_split('/\s+/', $suggested);
                if (count($words) === 1 && (strlen($suggested) < 5 || preg_match('/^(mr|mrs|ms|dr|prof|sir|madam|ma\'am|dear)$/i', $suggested))) {
                    $suggested = ''; // Force regeneration
                }
                
                // If cleanup made it too short, use fallback
                if (empty($suggested) || strlen($suggested) < 3) {
                    // Try to extract from prompt if available
                    if (!empty($prompt)) {
                        $promptWords = preg_split('/\s+/', $prompt);
                        $promptWords = array_filter($promptWords, function($w) {
                            return strlen($w) > 3 && !preg_match('/^(the|and|for|are|but|not|you|all|can|her|was|one|our|out|day|get|has|him|his|how|its|may|new|now|old|see|two|who|way|use|create|write|generate|make|document)$/i', $w);
                        });
                        $promptWords = array_slice($promptWords, 0, 5);
                        $suggested = implode(' ', $promptWords);
                    }
                    if (empty($suggested)) {
                        $suggested = 'Document';
                    }
                }
                
                // Final validation - reject titles that are opening sentences - VERY STRICT
                $suggestedLower = strtolower(trim($suggested));
                $rejectPatterns = [
                    '/as\s+part\s+of\s+our\s+ongoing\s+commitment/i', // Reject if this phrase appears ANYWHERE
                    '/^as\s+part\s+of/',
                    '/^we\s+have\s+identified/',
                    '/^we\s+are\s+writing/',
                    '/^this\s+memorandum\s+serves/',
                    '/^we\s+propose/',
                    '/^part\s+of\s+our/',
                    '/^ongoing\s+commitment/',
                    '/^identified\s+several/',
                    '/^excellence,\s+we\s+have/',
                    '/excellence,\s+we\s+have/i', // Anywhere in the title
                ];
                
                $shouldReject = false;
                foreach ($rejectPatterns as $pattern) {
                    if (preg_match($pattern, $suggestedLower)) {
                        $shouldReject = true;
                        break;
                    }
                }
                
                // Reject if it's too long (likely a sentence - more than 50 chars or 7+ words)
                $finalWords = preg_split('/\s+/', $suggested);
                if (strlen($suggested) > 50 || count($finalWords) > 7) {
                    $shouldReject = true;
                }
                
                // Reject if it contains comma (likely a sentence fragment)
                if (strpos($suggested, ',') !== false && strlen($suggested) > 30) {
                    $shouldReject = true;
                }
                
                if ($shouldReject) {
                    // Try document type detection as fallback
                    $contentLower = strtolower($content);
                    $docTypeKeywords = [
                        'budget' => 'Budget Request',
                        'firewall' => 'Budget Request',
                        'bandwidth' => 'Budget Request',
                        'leave' => 'Leave Request',
                        'inspection' => 'Inspection Notice',
                        'laboratory' => 'Laboratory Inspection',
                        'memo' => 'Internal Memo',
                        'memorandum' => 'Internal Memo',
                    ];
                    
                    $foundType = '';
                    foreach ($docTypeKeywords as $keyword => $title) {
                        if (strpos($contentLower, $keyword) !== false) {
                            $foundType = $title;
                            break;
                        }
                    }
                    
                    if (!empty($foundType)) {
                        $suggested = $foundType;
                    } else if (!empty($prompt)) {
                        $promptLower = strtolower($prompt);
                        if (preg_match('/budget|firewall|bandwidth/i', $promptLower)) {
                            $suggested = 'Budget Request';
                        } else if (preg_match('/memo|memorandum/i', $promptLower)) {
                            $suggested = 'Internal Memo';
                        } else {
                            $suggested = ''; // Return empty if we can't determine
                        }
                    } else {
                        $suggested = ''; // Return empty title rather than a bad one
                    }
                }
                
                // Final word count check after cleanup
                $words = preg_split('/\s+/', $suggested);
                if (count($words) > 6) {
                    $suggested = implode(' ', array_slice($words, 0, 6));
                }
                
                echo json_encode([
                    'success' => true,
                    'title' => $suggested ? $suggested : '' // Return empty string if no valid title
                ]);
            } catch (Exception $e) {
                error_log('Suggest title error: ' . $e->getMessage());
                
                // Try fallback: extract title from document content or prompt
                $fallbackTitle = '';
                if (!empty($content)) {
                    // Take first sentence or first 60 characters
                    $sentences = preg_split('/([.!?]+)/', $content, 2, PREG_SPLIT_DELIM_CAPTURE);
                    if (!empty($sentences[0])) {
                        $fallbackTitle = trim($sentences[0] . ($sentences[1] ?? ''));
                    }
                    if (strlen($fallbackTitle) > 60) {
                        $fallbackTitle = substr($fallbackTitle, 0, 57) . '...';
                    }
                }
                
                if (empty($fallbackTitle) && !empty($prompt)) {
                    // Use prompt keywords
                    $promptWords = preg_split('/\s+/', $prompt);
                    $promptWords = array_filter($promptWords, function($w) {
                        return strlen($w) > 3;
                    });
                    $fallbackTitle = implode(' ', array_slice($promptWords, 0, 4));
                }
                
                if (empty($fallbackTitle)) {
                    $fallbackTitle = 'New Document';
                }
                
                // Return error but with fallback title
                echo json_encode([
                    'success' => true, // Still return success with fallback
                    'title' => $fallbackTitle,
                    'note' => 'AI title generation failed, using fallback. Error: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'get_document':
            // Get a document by ID
            $documentId = $_REQUEST['document_id'] ?? '';
            
            if (empty($documentId)) {
                throw new Exception('Document ID is required');
            }
            
            $document = $docsManager->getDocument($documentId);
            
            echo json_encode([
                'success' => true,
                'document' => $document
            ]);
            break;
            
        case 'get_document_content':
            // Get a document's content
            $documentId = $_REQUEST['document_id'] ?? '';
            
            if (empty($documentId)) {
                throw new Exception('Document ID is required');
            }
            
            $content = $docsManager->getDocumentContent($documentId);
            
            echo json_encode([
                'success' => true,
                'content' => $content
            ]);
            break;
            
        case 'test_ai':
            // Test AI generation without Google Docs
            $prompt = $_REQUEST['prompt'] ?? 'Test prompt';
            
            try {
                $aiContent = generateAIContent($prompt);
                echo json_encode([
                    'success' => true,
                    'content' => $aiContent,
                    'message' => 'AI generation test successful'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'message' => 'AI generation test failed'
                ]);
            }
            break;
            
        case 'update_document_content':
            // Update a document's content
            $documentId = $_REQUEST['document_id'] ?? '';
            $content = $_REQUEST['content'] ?? '';
            
            if (empty($documentId)) {
                throw new Exception('Document ID is required');
            }
            
            $success = $docsManager->updateDocumentContent($documentId, $content);
            
            echo json_encode([
                'success' => $success
            ]);
            break;
            
        case 'get_embed_url':
            // Get the embed URL for a document
            $documentId = $_REQUEST['document_id'] ?? '';
            $editable = isset($_REQUEST['editable']) ? (bool)$_REQUEST['editable'] : true;
            
            if (empty($documentId)) {
                throw new Exception('Document ID is required');
            }
            
            $embedUrl = $docsManager->getDocumentEmbedUrl($documentId, $editable);
            
            echo json_encode([
                'success' => true,
                'embed_url' => $embedUrl
            ]);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    // Clear any output that might have been sent
    ob_clean();
    
    // Log the error
    error_log('Google Docs API Exception: ' . $e->getMessage());
    
    // Return JSON error
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'error_type' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
    exit();
}

/**
 * Generate content using Gemini AI based on a prompt
 * 
 * @param string $prompt The prompt for AI
 * @return string The generated content
 */
function generateAIContent($prompt) {
    // Get Gemini API key
    $api_key = getenv('GEMINI_API_KEY');
    if (empty($api_key)) {
        // Try to get from database settings
        global $conn;
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name = 'gemini_api_key'");
        if ($stmt && $stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $api_key = $row['setting_value'];
            }
        }
    }
    
    if (empty($api_key)) {
        throw new Exception('Gemini API key not configured. Please set it in AI Settings.');
    }
    
    // Get existing documents for context
    $existingDocs = getExistingDocumentsForContext();
    
    // Build enhanced prompt with context
    $enhancedPrompt = buildEnhancedPrompt($prompt, $existingDocs);
    
    // Prepend system guidance directly to the prompt for compatibility
    $systemGuide = 'You are a professional document generator. Create well-formatted, professional documents for educational institutions. Always include proper headers, formatting, and formal language.';
    $finalPrompt = $systemGuide . "\n\n" . $enhancedPrompt;
    
    // Call Gemini API
    $content = callGeminiAPI($finalPrompt, $api_key);
    
    return $content;
}

/**
 * Get existing documents for context
 */
function getExistingDocumentsForContext() {
    global $conn;
    $docs = [];
    
    try {
        $stmt = $conn->prepare("SELECT title, type_id, created_at FROM documents ORDER BY created_at DESC LIMIT 10");
        if ($stmt && $stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $docs[] = [
                    'title' => $row['title'],
                    'type' => $row['type_id'],
                    'date' => $row['created_at']
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting existing documents: " . $e->getMessage());
    }
    
    return $docs;
}

/**
 * Build enhanced prompt with context
 */
function buildEnhancedPrompt($userPrompt, $existingDocs) {
    $context = "You are a professional document generator for Saint Columban College in Pagadian City, Zamboanga del Sur. Strictly output PLAIN TEXT (no markdown).\n\n";

    if (!empty($existingDocs)) {
        $context .= "Recent documents:\n";
        foreach ($existingDocs as $doc) {
            $context .= "- " . $doc['title'] . " (" . $doc['date'] . ")\n";
        }
        $context .= "\n";
    }

    $context .= "MUST FOLLOW THIS SCHOOL LAYOUT:\n";
    $context .= "Header (centered, exactly three lines):\n";
    $context .= "Saint Columban College\nManagement Information System\nPagadian City\n\n";
    $context .= "Date aligned left (Month D, YYYY).\n";
    $context .= "Recipient block: BOLD name (ALL CAPS if applicable), then position, then 'This Institution'.\n";
    $context .= "Salutation: 'Dear Ma’am <Last Name>,'\n";
    $context .= "Cebuano greeting line: 'Panagdait sa Dios, sa tanan, ug sa tanang kauhatani!'\n";
    $context .= "Body: concise, lively yet professional.\n";
    $context .= "For budgets/lists: clear sections using labels like 'Project:', 'Cost:', 'Source of Funds:' with hyphen bullets under each.\n";
    $context .= "Closing thanks + readiness to coordinate.\n";
    $context .= "Closing line: 'In Saint Columban,' then signature block with FULL NAME (bold) and Title.\n";
    $context .= "Optionally include 'Budget Verified' and 'Approved by' placeholders on separate lines when relevant.\n\n";

    $context .= "TONE: Warm, respectful, proactive; vivid but succinct.\n\n";
    $context .= "REQUEST: " . $userPrompt . "\n\n";
    $context .= "Return only the final letter text, ready to paste into Google Docs, using line breaks only.";

    return $context;
}

/**
 * Internal helper: send generateContent with model/version fallbacks
 */
function gemini_generate_content($payload, $api_key, $modelOverride = null) {
    // Optional model override from env or DB settings
    if ($modelOverride === null) {
        $modelOverride = getenv('GEMINI_MODEL');
        if (empty($modelOverride)) {
            global $conn;
            try {
                $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name = 'gemini_model' LIMIT 1");
                if ($stmt && $stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($r = $res->fetch_assoc()) { $modelOverride = trim($r['setting_value']); }
                }
            } catch (Exception $e) { /* ignore */ }
        }
    }
    
    $attempts = [];
    if (!empty($modelOverride)) {
        // Try v1 and v1beta with the override model
        $attempts[] = ['version' => 'v1', 'model' => $modelOverride];
        $attempts[] = ['version' => 'v1beta', 'model' => $modelOverride];
    }
    // Default fallbacks (ordered by your key's available models)
    $attempts = array_merge($attempts, [
        // v1 primary options from your ListModels output
        ['version' => 'v1', 'model' => 'gemini-2.5-flash'],
        ['version' => 'v1', 'model' => 'gemini-2.5-pro'],
        ['version' => 'v1', 'model' => 'gemini-2.0-flash'],
        ['version' => 'v1', 'model' => 'gemini-2.0-flash-001'],
        ['version' => 'v1', 'model' => 'gemini-2.0-flash-lite-001'],
        ['version' => 'v1', 'model' => 'gemini-2.5-flash-lite'],
        // Minimal legacy fallbacks (some projects still expose these only on v1beta)
        ['version' => 'v1beta', 'model' => 'gemini-pro']
    ]);
    
    $lastError = null;
    foreach ($attempts as $attempt) {
        $url = 'https://generativelanguage.googleapis.com/' . $attempt['version'] . '/models/' . urlencode($attempt['model']) . ':generateContent?key=' . urlencode($api_key);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            $lastError = 'cURL Error: ' . $curlError;
            break; // network error; no point retrying different models
        }
        
        if ($httpCode === 200) {
            return $response; // success
        }
        
        // Parse error to decide whether to retry
        $lastError = 'HTTP ' . $httpCode . ' - ' . $response;
        if ($httpCode === 404) {
            // try next attempt
            continue;
        }
        
        // For other errors, return immediately
        break;
    }
    
    throw new Exception('API Error: ' . $lastError);
}

/**
 * Robust Gemini parser: always extracts .text if present, otherwise throws a contextual error.
 */
function parseGeminiText($result, $raw) {
    // Check if result has error
    if (isset($result['error'])) {
        $errorMsg = is_array($result['error']) ? json_encode($result['error']) : $result['error'];
        throw new Exception('Gemini API Error: ' . $errorMsg);
    }
    
    // Check if candidates array exists and has items
    if (!isset($result['candidates']) || !is_array($result['candidates']) || empty($result['candidates'])) {
        throw new Exception('No candidates in Gemini response. Raw: ' . substr($raw, 0, 500));
    }
    
    $candidate = $result['candidates'][0];
    
    // Check finish reason
    $finishReason = $candidate['finishReason'] ?? 'UNKNOWN';
    if ($finishReason === 'SAFETY' || $finishReason === 'RECITATION' || $finishReason === 'OTHER') {
        $safetyRatings = $candidate['safetyRatings'] ?? [];
        throw new Exception('Content blocked by safety filter. Reason: ' . $finishReason . '. Safety ratings: ' . json_encode($safetyRatings));
    }
    
    // Try to extract text from various possible locations
    $text = '';
    if (isset($candidate['content']['parts'])) {
        foreach ($candidate['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $text = $part['text'];
                break;
            }
            if (isset($part['inlineData']['text'])) {
                $text = $part['inlineData']['text'];
                break;
            }
        }
    }
    
    if (empty($text)) {
        // Try alternative paths
        $text = $candidate['content']['parts'][0]['text'] ?? 
                $candidate['content']['parts'][0]['inlineData']['text'] ?? 
                '';
    }
    
    if (empty($text)) {
        $errorDetails = [
            'finishReason' => $finishReason,
            'hasContent' => isset($candidate['content']),
            'hasParts' => isset($candidate['content']['parts']),
            'partsCount' => isset($candidate['content']['parts']) ? count($candidate['content']['parts']) : 0
        ];
        throw new Exception('No AI content returned. ' . json_encode($errorDetails) . '. Raw: ' . substr($raw, 0, 1000));
    }
    
    return trim($text);
}

/**
 * Call Gemini API to generate content
 */
function callGeminiAPI($prompt, $api_key) {
    $data = [
        'contents' => [
            [
                'parts' => [['text' => $prompt]]
            ]
        ],
        'generation_config' => [
            'temperature' => 0.3,
            'max_output_tokens' => 4000
        ],
        'safety_settings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ]
        ]
    ];
    
    // Use helper with fallbacks
    $raw = gemini_generate_content($data, $api_key);
    
    $result = json_decode($raw, true);
    
    if (isset($result['error'])) {
        throw new Exception('Gemini API Error: ' . json_encode($result['error']));
    }
    
    $content = parseGeminiText($result, $raw);
    
    // Convert to HTML if it's plain text
    if (!preg_match('/<[^>]+>/', $content)) {
        $content = nl2br(htmlspecialchars($content));
    }
    
    return $content;
}

/**
 * Lightweight Gemini call tuned for title suggestion
 */
function callGeminiAPITitle($prompt, $api_key) {
    // Build prompt with instruction
    $fullPrompt = "You are helping name a professional document. Return ONLY a JSON object with a 'title' field containing a short, descriptive title (max 80 characters). No markdown, no code blocks, just JSON.\n\n" . $prompt;
    
    // Try with and without responseMimeType (some API versions handle it differently)
    $payloads = [
        [
            'contents' => [
                [
                    'parts' => [ [ 'text' => $fullPrompt ] ]
                ]
            ],
            'generation_config' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 100
            ]
        ],
        [
            'contents' => [
                [
                    'parts' => [ [ 'text' => $fullPrompt ] ]
                ]
            ],
            'generation_config' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 100,
                'responseMimeType' => 'application/json'
            ]
        ]
    ];
    
    $lastError = null;
    foreach ($payloads as $payload) {
        try {
            // Use helper with fallbacks
            $raw = gemini_generate_content($payload, $api_key);
            
            // Decode response
            $data = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $lastError = 'Invalid JSON response: ' . json_last_error_msg();
                continue;
            }
            
            // Check for API errors
            if (isset($data['error'])) {
                $code = $data['error']['code'] ?? 0;
                $message = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : $data['error'];
                $lastError = 'Gemini error (' . $code . '): ' . $message;
                continue;
            }
            
            // Parse text from response
            $titleText = parseGeminiText($data, $raw);
            
            // If we got text, try to extract title from it
            if (!empty($titleText)) {
                // Clean up JSON if wrapped in markdown
                $cleanText = trim($titleText);
                if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $cleanText, $matches)) {
                    $cleanText = trim($matches[1]);
                } elseif (strpos($cleanText, '```') === 0) {
                    $cleanText = preg_replace('/^```[a-zA-Z]*\n|\n```$/', '', $cleanText);
                }
                
                // Try to parse as JSON
                $titleData = json_decode($cleanText, true);
                if (is_array($titleData) && isset($titleData['title'])) {
                    return trim($titleData['title']);
                } elseif (is_string($titleData)) {
                    return trim($titleData);
                } else {
                    // If not JSON, return the text as-is (might be just the title)
                    return trim($cleanText);
                }
            }
        } catch (Exception $e) {
            $lastError = $e->getMessage();
            error_log('callGeminiAPITitle attempt failed: ' . $e->getMessage());
            continue; // Try next payload
        }
    }
    
    // If all attempts failed, throw error
    throw new Exception('Failed to generate title after multiple attempts: ' . ($lastError ?? 'Unknown error'));
}

/**
 * Extract keywords from a prompt with improved natural language understanding
 * 
 * @param string $prompt The prompt text
 * @return array Array of keywords and their values
 */
function extractKeywords($prompt) {
    $keywords = [];
    
    // Extract institution/organization name with improved pattern
    if (preg_match('/(Saint Columban College|SCC|college|university|school|institution|company|organization)/i', $prompt, $matches)) {
        $keywords['institution'] = 'Saint Columban College';
        $keywords['location'] = 'Pagadian City, Zamboanga del Sur';
    }
    
    // Extract office/department with improved pattern
    if (preg_match('/(office of the|from the|department of|faculty of) ([^,.]+)/i', $prompt, $matches)) {
        $keywords['office'] = 'Office of the ' . trim($matches[2]);
    }
    
    // Extract dates with improved pattern
    if (preg_match('/([A-Z][a-z]+ \d{1,2}(?:–|,| to | until )?\d{4})/i', $prompt, $matches)) {
        $keywords['date'] = $matches[1];
    } elseif (preg_match('/(\d{1,2}(?:st|nd|rd|th)? [A-Z][a-z]+ \d{4})/i', $prompt, $matches)) {
        $keywords['date'] = $matches[1];
    } elseif (preg_match('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i', $prompt, $matches)) {
        $keywords['date'] = $matches[1];
    } else {
        // Default to current date
        $keywords['date'] = date('F j, Y');
    }
    
    // Extract recipient with improved pattern
    if (preg_match('/(to|for|addressed to)[ :]+([^,.]+)/i', $prompt, $matches)) {
        $keywords['recipient'] = trim($matches[2]);
    }
    
    // Extract sender with improved pattern
    if (preg_match('/(from|by|signed by|sincerely)[ :]+([^,.]+)/i', $prompt, $matches)) {
        $keywords['sender'] = trim($matches[2]);
    }
    
    // Extract subject with improved pattern
    if (preg_match('/(about|regarding|subject|topic|re|concerning)[ :]+([^,.]+)/i', $prompt, $matches)) {
        $keywords['subject'] = trim($matches[2]);
    } elseif (preg_match('/(inspection|meeting|leave|holiday|evaluation|assessment|policy|procedure)/i', $prompt, $matches)) {
        $keywords['subject'] = $matches[1];
    }
    
    // Extract time period with improved pattern
    if (preg_match('/([A-Z][a-z]+ \d{1,2}(?:–|-| - | to | until | through )\d{1,2}, \d{4})/i', $prompt, $matches)) {
        $keywords['timeperiod'] = $matches[1];
    } elseif (preg_match('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}\s*(?:to|until|through)\s*\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i', $prompt, $matches)) {
        $keywords['timeperiod'] = $matches[1];
    }
    
    return $keywords;
}

/**
 * Generate an enhanced memo based on a prompt
 * 
 * @param string $prompt The prompt text
 * @param array $keywords Extracted keywords
 * @return string Generated memo content
 */
function generateEnhancedMemo($prompt, $keywords) {
    // Extract or provide default values
    $institution = isset($keywords['institution']) ? $keywords['institution'] : 'Saint Columban College';
    $location = isset($keywords['location']) ? $keywords['location'] : 'Pagadian City, Zamboanga del Sur';
    $office = isset($keywords['office']) ? $keywords['office'] : 'Office of the Academic Affairs';
    $date = isset($keywords['date']) ? $keywords['date'] : date('F j, Y');
    $recipient = isset($keywords['recipient']) ? $keywords['recipient'] : 'All Faculty Members';
    $sender = isset($keywords['sender']) ? $keywords['sender'] : 'Office of the Vice President for Academic Affairs';
    $subject = isset($keywords['subject']) ? $keywords['subject'] : 'Important Announcement';
    
    // Content for the memo with improved spacing
    $content = $institution . "\n";
    $content .= $location . "\n";
    $content .= $office . "\n";
    $content .= "Internal Memorandum\n\n";
    
    $content .= "Date: " . $date . "\n\n";
    $content .= "To: " . $recipient . "\n\n";
    $content .= "From: " . $sender . "\n\n";
    $content .= "Subject: " . $subject . "\n\n";
    
    // Generate body paragraphs based on prompt analysis
    $content .= "Dear " . $recipient . ",\n\n";
    
    // Extract main information from the prompt
    $information = extractMainInformation($prompt);
    
    // If no specific information found, create generic content
    if (empty($information)) {
        if (stripos($prompt, 'meeting') !== false) {
            $content .= "Please be informed that a meeting will be held on [DATE] at [TIME] in [LOCATION]. This meeting is mandatory for all concerned parties.\n\n";
            $content .= "The agenda will include discussion of " . $subject . " and related matters. Please come prepared with any relevant documents or information.\n\n";
            $content .= "During the meeting, we will be covering several important topics related to " . $subject . ". These topics include current status updates, challenges faced, proposed solutions, and implementation strategies. Your input and expertise will be valuable in shaping our collective approach moving forward.\n\n";
            $content .= "Please also note that this meeting will serve as an opportunity to address any concerns or questions you may have regarding the subject matter. To ensure a productive discussion, kindly review all relevant materials beforehand and prepare any points you wish to raise.\n\n";
            $content .= "If you have any specific items you would like to add to the agenda, please submit them to the office no later than 24 hours before the scheduled meeting time. This will allow us to allocate appropriate time for each discussion point.\n\n";
        } elseif (stripos($prompt, 'inspection') !== false || stripos($prompt, 'evaluation') !== false) {
            $timeperiod = isset($keywords['timeperiod']) ? $keywords['timeperiod'] : 'next week';
            $content .= "Please be informed that starting " . $timeperiod . ", the " . $office . " will be conducting inspections/evaluations of all facilities. This is part of our ongoing commitment to maintaining quality standards.\n\n";
            $content .= "All department heads and concerned staff are requested to ensure that all records and facilities are in order. Any issues identified during the inspection will be documented and should be addressed promptly.\n\n";
            $content .= "The inspection process will be comprehensive and will cover physical facilities, documentation, compliance with regulatory requirements, and adherence to institutional policies and procedures. The evaluation team will consist of representatives from various departments to ensure a fair and thorough assessment.\n\n";
            $content .= "To facilitate a smooth inspection process, please prepare the following documents for review: updated inventory lists, maintenance records, safety compliance documentation, and relevant departmental reports from the past academic year. Having these documents readily available will expedite the inspection process.\n\n";
            $content .= "Following the inspection, a detailed report will be provided to each department highlighting areas of excellence and identifying opportunities for improvement. Departments will be given reasonable time to address any concerns raised during the inspection.\n\n";
        } else {
            $content .= "Please be informed about important matters regarding " . $subject . ". This memorandum aims to provide you with essential information and guidelines that require your attention and compliance.\n\n";
            $content .= "All concerned individuals are expected to take note of the details provided and act accordingly. Your cooperation in this matter is highly appreciated.\n\n";
            $content .= "The " . $office . " has been monitoring developments related to " . $subject . " and has determined that certain actions must be taken to address current challenges and prepare for future opportunities. These actions will require coordination across departments and active participation from all stakeholders.\n\n";
            $content .= "We have developed a comprehensive plan to address these matters, which includes several key components: assessment of the current situation, identification of areas needing improvement, development of targeted interventions, implementation of new procedures, and ongoing monitoring and evaluation.\n\n"; 
            $content .= "To ensure successful implementation, we will be organizing training sessions and providing resource materials to support your understanding and compliance with these new guidelines. Your feedback during this process will be valuable in refining our approach.\n\n";
        }
    } else {
        // Use extracted information
        foreach ($information as $paragraph) {
            $content .= $paragraph . "\n\n";
        }
        
        // Add additional general paragraphs to extend the content
        $content .= "Furthermore, we would like to emphasize the importance of adhering to the institutional policies and procedures related to this matter. Compliance is not only a regulatory requirement but also contributes significantly to maintaining our standards of excellence.\n\n";
        $content .= "The administration acknowledges the dedication and hard work of all faculty and staff in upholding the values and mission of " . $institution . ". Your continued support and cooperation in implementing these directives will be instrumental in achieving our collective goals.\n\n";
        $content .= "Regular updates on this matter will be provided through departmental meetings and official communications. We encourage open dialogue and welcome constructive suggestions that might enhance our approach to addressing these important concerns.\n\n";
    }
    
    $content .= "Should you have any questions or require further clarification regarding this memorandum, please do not hesitate to contact the " . $office . " at your earliest convenience. We are committed to providing you with the support and information you need to fully understand and comply with these directives.\n\n";
    
    $content .= "Thank you for your attention and cooperation.\n\n";
    
    $content .= "Respectfully,\n\n";
    
    $senderName = extractSenderName($sender);
    $senderPosition = extractSenderPosition($sender);
    $content .= $senderName . "\n";
    $content .= $senderPosition . "\n";
    $content .= $institution;
    
    return $content;
}

/**
 * Generate an enhanced letter based on a prompt
 * 
 * @param string $prompt The prompt text
 * @param array $keywords Extracted keywords
 * @return string Generated letter content
 */
function generateEnhancedLetter($prompt, $keywords) {
    // Extract or provide default values
    $institution = isset($keywords['institution']) ? $keywords['institution'] : 'Saint Columban College';
    $location = isset($keywords['location']) ? $keywords['location'] : 'Pagadian City, Zamboanga del Sur';
    $office = isset($keywords['office']) ? $keywords['office'] : 'Office of the Academic Affairs';
    $date = isset($keywords['date']) ? $keywords['date'] : date('F j, Y');
    $recipient = isset($keywords['recipient']) ? $keywords['recipient'] : 'Recipient';
    $sender = isset($keywords['sender']) ? $keywords['sender'] : 'Your Name';
    $subject = isset($keywords['subject']) ? $keywords['subject'] : 'General Inquiry';
    
    // Content for the letter with proper text formatting and improved spacing
    $content = $institution . "\n";
    $content .= $location . "\n";
    $content .= $office . "\n\n";
    
    $content .= $date . "\n\n";
    
    $content .= $recipient . "\n";
    $content .= "Saint Columban College\n";
    $content .= "Pagadian City\n\n";
    
    $content .= "Dear " . $recipient . ",\n\n";

    // Extract main information from the prompt
    $information = extractMainInformation($prompt);
    
    if (!empty($information)) {
        foreach ($information as $paragraph) {
            $content .= $paragraph . "\n\n";
        }
        
        // Add additional paragraphs to extend the content
        $content .= "I would like to provide some additional context regarding this matter. Over the past several months, we have been carefully monitoring developments and gathering information to ensure that our approach is both comprehensive and well-informed. This has involved consultation with various stakeholders and a thorough review of relevant policies and best practices.\n\n";
        $content .= "Our institution is committed to maintaining the highest standards in all its operations and interactions. This commitment guides our decision-making process and informs the recommendations and requests outlined in this letter. We believe that by working together in a spirit of collaboration and mutual respect, we can effectively address the matters at hand.\n\n";
        $content .= "It is worth noting that similar situations have been successfully managed in the past through a combination of clear communication, strategic planning, and timely intervention. We hope to build upon these past experiences while also incorporating new insights and approaches that reflect current realities and future aspirations.\n\n";
    } else {
        // Default letter content if no specific information extracted
        $content .= "I am writing to you regarding " . $subject . ". ";
        
        if (stripos($prompt, 'request') !== false) {
            $content .= "I would like to formally request your consideration on this matter. This request is being made in order to [reason for request].\n\n";
            $content .= "I appreciate your time and attention to this request. I am available to provide any additional information that you might need.\n\n";
            $content .= "The rationale behind this request stems from a careful analysis of our current situation and future needs. We have identified several key factors that make this request not only beneficial but necessary for achieving our strategic objectives. These factors include changing operational requirements, evolving stakeholder expectations, and the need to align our practices with industry standards.\n\n";
            $content .= "In preparing this request, we have taken into account potential impacts on resources, timelines, and other organizational priorities. We believe that the benefits of approving this request far outweigh any temporary adjustments that may be required. Moreover, we have developed a detailed implementation plan to ensure a smooth transition and minimize any disruptions.\n\n";
            $content .= "We understand that you must consider various factors in your decision-making process. To facilitate this, I have attached relevant supporting documentation that provides additional context and justification for this request. Should you require any clarification or have questions about specific aspects, I would be more than happy to discuss them in detail.\n\n";
        } else {
            $content .= "I wanted to bring this matter to your attention as it requires your input and consideration. Your expertise and guidance would be greatly appreciated.\n\n";
            $content .= "Thank you for your time and consideration. I look forward to your response.\n\n";
            $content .= "The matter at hand has significant implications for our ongoing operations and future planning. Based on our preliminary assessment, several key aspects require careful consideration. First, there are practical considerations related to implementation and resource allocation. Second, there are strategic implications that align with our institutional mission and long-term objectives. Finally, there are stakeholder interests that must be balanced to ensure broad support and successful outcomes.\n\n";
            $content .= "Our analysis suggests that addressing this matter promptly and effectively will yield substantial benefits. These include enhanced operational efficiency, improved stakeholder satisfaction, and stronger alignment with our core values and strategic priorities. Conversely, delaying action could potentially lead to missed opportunities and increased challenges down the line.\n\n";
            $content .= "We believe that your unique perspective and experience would be invaluable in shaping our approach. Your insights would help us refine our understanding of the issues involved and develop more robust solutions that address both immediate concerns and long-term aspirations.\n\n";
        }
    }
    
    $content .= "Looking ahead, we anticipate that resolving this matter will contribute significantly to our collective goals and strengthen our institutional capacity. We remain committed to open dialogue and collaborative problem-solving throughout this process.\n\n";
    
    $content .= "Once again, I appreciate your attention to this matter and look forward to discussing it further at your convenience. Please feel free to contact me directly should you have any questions or require additional information.\n\n";
    
    $content .= "Respectfully,\n\n";
    
    $senderName = extractSenderName($sender);
    $senderPosition = extractSenderPosition($sender);
    $content .= $senderName . "\n";
    $content .= $senderPosition . "\n";
    $content .= $institution;
    
    return $content;
}

/**
 * Generate an enhanced leave request based on a prompt
 * 
 * @param string $prompt The prompt text
 * @param array $keywords Extracted keywords
 * @return string Generated leave request content
 */
function generateEnhancedLeaveRequest($prompt, $keywords) {
    // Extract or provide default values
    $institution = isset($keywords['institution']) ? $keywords['institution'] : 'Saint Columban College';
    $location = isset($keywords['location']) ? $keywords['location'] : 'Pagadian City, Zamboanga del Sur';
    $office = isset($keywords['office']) ? $keywords['office'] : 'Office of Human Resources';
    $date = isset($keywords['date']) ? $keywords['date'] : date('F j, Y');
    $recipient = isset($keywords['recipient']) ? $keywords['recipient'] : 'Human Resources Director';
    $sender = isset($keywords['sender']) ? $keywords['sender'] : 'Your Name';
    
    // Extract leave period
    $leaveDates = isset($keywords['timeperiod']) ? $keywords['timeperiod'] : '[specify dates]';
    
    // Extract reason for leave
    $reason = '';
    if (preg_match('/(?:because|due to|for|reason|as)\s+([^,.]+)/i', $prompt, $matches)) {
        $reason = $matches[1];
    } else {
        $reason = '[specify reason]';
    }
    
    // Content for the leave request with improved spacing
    $content = $institution . "\n";
    $content .= $location . "\n";
    $content .= $office . "\n\n";
    
    $content .= $date . "\n\n";
    
    $content .= $recipient . "\n";
    $content .= $institution . "\n";
    $content .= "Pagadian City\n\n";
    
    $content .= "Dear " . $recipient . ",\n\n";
    
    $content .= "Subject: Application for Leave of Absence\n\n";
    
    $content .= "I am writing to formally request a leave of absence from " . $leaveDates . ". The reason for my leave is " . $reason . ".\n\n";
    
    $content .= "During my absence, I have arranged for [Colleague Name] to handle my responsibilities. They can be contacted at [Contact Information] if any urgent matters arise that require immediate attention.\n\n";
    
    $content .= "I will ensure that all pending tasks are completed before my leave begins, and I will be available via email for any critical issues that may require my input.\n\n";

    $content .= "To ensure a smooth transition, I have prepared detailed documentation of all current projects and ongoing responsibilities. This documentation includes status updates, contact information for relevant stakeholders, and specific instructions for handling routine matters. I have shared these materials with my designated colleague and other team members who may need to address matters in my absence.\n\n";
    
    $content .= "Additionally, I have scheduled briefing sessions with key personnel to discuss any complex or sensitive issues that might arise during my leave period. These sessions are intended to provide context and guidance that might not be easily conveyed through written documentation alone.\n\n";
    
    $content .= "In accordance with departmental policy, I have accrued sufficient leave days to cover the requested period. Based on my calculations and the records in the HR system, I will have [number] leave days remaining after this absence. Please confirm this information at your convenience.\n\n";
    
    $content .= "I understand that my absence may create some temporary adjustments in workload for my colleagues. I have discussed this with my department head, who has expressed support for this leave request and confirmed that arrangements can be made to manage essential functions during my absence.\n\n";
    
    $content .= "Thank you for considering my request. I look forward to your approval.\n\n";
    
    $content .= "Respectfully,\n\n";
    
    $senderName = extractSenderName($sender);
    $senderPosition = extractSenderPosition($sender);
    $content .= $senderName . "\n";
    $content .= $senderPosition . "\n";
    $content .= $institution;
    
    return $content;
}

/**
 * Generate an enhanced announcement based on a prompt
 * 
 * @param string $prompt The prompt text
 * @param array $keywords Extracted keywords
 * @return string Generated announcement content
 */
function generateEnhancedAnnouncement($prompt, $keywords) {
    // Extract or provide default values
    $institution = isset($keywords['institution']) ? $keywords['institution'] : 'Saint Columban College';
    $location = isset($keywords['location']) ? $keywords['location'] : 'Pagadian City, Zamboanga del Sur';
    $office = isset($keywords['office']) ? $keywords['office'] : 'Office of the Academic Affairs';
    $date = isset($keywords['date']) ? $keywords['date'] : date('F j, Y');
    $subject = isset($keywords['subject']) ? $keywords['subject'] : 'Important Announcement';
    
    // Content for the announcement with improved spacing
    $content = $institution . "\n";
    $content .= $location . "\n";
    $content .= $office . "\n\n";
    
    $content .= "ANNOUNCEMENT\n\n";
    $content .= $subject . "\n\n";
    $content .= "Date: " . $date . "\n\n";
    
    // Extract main information from the prompt
    $information = extractMainInformation($prompt);
    
    if (!empty($information)) {
        foreach ($information as $paragraph) {
            $content .= $paragraph . "\n\n";
        }
        
        // Add additional paragraphs to extend the content
        $content .= "This announcement serves as an official communication from the " . $office . " and requires attention from all concerned parties. Please review all details carefully and note any deadlines or required actions.\n\n";
        $content .= "The decision to make this announcement comes after careful consideration of various factors including institutional priorities, stakeholder feedback, and operational requirements. We believe this information is essential for maintaining transparent communication within our academic community.\n\n";
        $content .= "Additional resources and support will be available to assist with any transitions or adjustments related to this announcement. Further details about these support mechanisms will be communicated through departmental channels in the coming days.\n\n";
    } else {
        // Default announcement content with expanded information
        $content .= "This is to announce that [event/change] will take place on [date]. This [event/change] is important because [reason].\n\n";
        $content .= "All [concerned individuals] are requested to take note of this announcement and make necessary preparations. Your cooperation and support are essential for the successful implementation of this initiative.\n\n";
        $content .= "Background and Context:\n\n";
        $content .= "This announcement follows several months of planning and consultation with key stakeholders across the institution. The decision to proceed with this [event/change] was made after thorough consideration of various alternatives and their potential impacts on our academic community.\n\n";
        $content .= "The timing of this initiative has been strategically determined to minimize disruption to regular activities while maximizing potential benefits. We have also taken into account feedback received during previous similar experiences to refine our approach.\n\n";
        $content .= "Key Details and Timeline:\n\n";
        $content .= "• Preparation Phase: [Start date] to [End date]\n";
        $content .= "• Implementation Phase: [Start date] to [End date]\n";
        $content .= "• Evaluation and Review: [Start date] to [End date]\n\n";
        $content .= "During each phase, specific activities and responsibilities will be assigned to relevant departments and individuals. A detailed schedule will be distributed to department heads within the next week.\n\n";
        $content .= "Expected Impact and Benefits:\n\n";
        $content .= "This initiative is expected to [describe anticipated positive outcomes]. While we recognize that any change can present challenges, we believe that the long-term benefits for our institution and community are substantial.\n\n";
        $content .= "Some specific benefits include:\n";
        $content .= "• [Benefit 1]\n";
        $content .= "• [Benefit 2]\n";
        $content .= "• [Benefit 3]\n\n";
    }
    
    $content .= "Required Actions:\n\n";
    $content .= "To ensure a smooth implementation, please take note of the following required actions:\n\n";
    $content .= "1. Review all information provided in this announcement and any accompanying documents\n";
    $content .= "2. Share relevant details with your department or team members\n";
    $content .= "3. Mark important dates on your calendar\n";
    $content .= "4. Prepare any necessary resources or materials\n";
    $content .= "5. Submit any questions or concerns to the contact person listed below\n\n";
    
    $content .= "For inquiries and clarifications, please contact [Contact Person] at [Contact Information]. We are committed to addressing your questions promptly and providing any additional information you may need.\n\n";
    
    $content .= "Regular updates regarding this matter will be provided through official channels, including email communications, departmental meetings, and the institutional portal. Please check these sources regularly for the most current information.\n\n";
    
    $content .= "Thank you for your attention and cooperation.\n\n";
    
    $content .= "Sincerely,\n\n\n";
    
    $content .= "____________________\n";
    $content .= "[Name]\n";
    $content .= "[Position]\n";
    $content .= $office . "\n";
    $content .= $institution;
    
    return $content;
}

/**
 * Helper function to extract sender's name from sender string
 */
function extractSenderName($sender) {
    // If sender includes a title (Prof., Dr., etc.), keep it with the name
    if (preg_match('/((?:Prof\.|Dr\.|Mr\.|Mrs\.|Ms\.|Engr\.|Atty\.) [A-Z][a-z]+ [A-Z]. [A-Z][a-z]+)/', $sender, $matches)) {
        return $matches[1];
    }
    
    // If sender includes "Office of the", extract a name if possible or return generic
    if (stripos($sender, 'Office of the') !== false) {
        return 'Prof. Juan Dela Cruz';
    }
    
    return $sender;
}

/**
 * Helper function to extract sender's position from sender string
 */
function extractSenderPosition($sender) {
    if (stripos($sender, 'President') !== false) {
        return 'President';
    } elseif (stripos($sender, 'Vice President') !== false || stripos($sender, 'VP') !== false) {
        if (stripos($sender, 'Academic') !== false) {
            return 'Vice President for Academic Affairs';
        } elseif (stripos($sender, 'Admin') !== false) {
            return 'Vice President for Administration';
        } else {
            return 'Vice President';
        }
    } elseif (stripos($sender, 'Dean') !== false) {
        return 'Dean';
    } elseif (stripos($sender, 'Director') !== false) {
        return 'Director';
    } elseif (stripos($sender, 'Department Head') !== false || stripos($sender, 'Chair') !== false) {
        return 'Department Head';
    } else {
        return 'Faculty Member';
    }
}

/**
 * Helper function to extract main information from the prompt
 */
function extractMainInformation($prompt) {
    $paragraphs = [];
    
    // If the prompt contains multiple paragraphs, try to extract meaningful content
    $lines = explode("\n", $prompt);
    $contentLines = [];
    $inContent = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip empty lines and header/footer information
        if (empty($line) || preg_match('/(Saint Columban College|Pagadian City|Office of|Internal Memorandum|Date:|To:|From:|Subject:|Respectfully,|Sincerely,)/', $line)) {
            continue;
        }
        
        // Start collecting content after "Dear" line
        if (stripos($line, 'Dear') !== false) {
            $inContent = true;
            continue;
        }
        
        // Stop collecting before signature
        if (stripos($line, 'Respectfully') !== false || stripos($line, 'Sincerely') !== false) {
            break;
        }
        
        // Collect content lines
        if ($inContent && !empty($line)) {
            $contentLines[] = $line;
        }
    }
    
    // If we have extracted content lines, use them
    if (!empty($contentLines)) {
        $currentParagraph = '';
        
        foreach ($contentLines as $line) {
            // If the line ends with a period, question mark, or exclamation point, it's the end of a paragraph
            if (preg_match('/[.!?]$/', $line)) {
                $currentParagraph .= ' ' . $line;
                $paragraphs[] = trim($currentParagraph);
                $currentParagraph = '';
            } else {
                $currentParagraph .= ' ' . $line;
            }
        }
        
        // Add any remaining content
        if (!empty($currentParagraph)) {
            $paragraphs[] = trim($currentParagraph);
        }
    }
    
    return $paragraphs;
}

/**
 * Generate an enhanced report based on a prompt
 * 
 * @param string $prompt The prompt text
 * @param array $keywords Extracted keywords
 * @return string Generated report content
 */
function generateEnhancedReport($prompt, $keywords) {
    // Extract or provide default values
    $institution = isset($keywords['institution']) ? $keywords['institution'] : 'Saint Columban College';
    $location = isset($keywords['location']) ? $keywords['location'] : 'Pagadian City, Zamboanga del Sur';
    $office = isset($keywords['office']) ? $keywords['office'] : 'Office of the Academic Affairs';
    $date = isset($keywords['date']) ? $keywords['date'] : date('F j, Y');
    $subject = isset($keywords['subject']) ? $keywords['subject'] : 'Status Update';
    
    // Content for the report with improved spacing
    $content = $institution . "\n";
    $content .= $location . "\n";
    $content .= $office . "\n";
    $content .= "REPORT\n\n";
    
    $content .= $subject . " Report\n\n";
    $content .= "Date: " . $date . "\n\n";
    
    $content .= "Executive Summary\n\n";
    $content .= "This report provides a comprehensive overview and analysis of " . $subject . ". It aims to inform stakeholders about the current status, findings, and recommendations related to this matter. The following pages detail our methodology, key findings, analysis, and strategic recommendations for moving forward.\n\n";
    $content .= "The investigation conducted over the past [timeframe] has revealed significant insights that will help guide institutional decision-making and resource allocation. This executive summary highlights the most critical elements, while the full report provides detailed evidence and contextual information for each finding and recommendation.\n\n";
    
    $content .= "Background and Context\n\n";
    $content .= "The " . $subject . " has been an important area of focus for our institution. This report examines the key aspects and developments in this area over the past [timeframe].\n\n";
    $content .= "Historically, our institution has approached this matter through various initiatives and policies, with varying degrees of success. Recent developments, including [relevant changes or events], have necessitated a fresh examination of our approaches and outcomes. This report builds upon previous assessments while incorporating new data and emerging best practices in the field.\n\n";
    $content .= "The institutional context is particularly relevant for understanding both the constraints and opportunities associated with this subject. Our unique position as a Catholic educational institution in Pagadian City shapes both our responsibilities and our potential impact in addressing these matters.\n\n";
    
    $content .= "Objectives and Scope\n\n";
    $content .= "This report aims to achieve the following objectives:\n\n";
    $content .= "1. Assess the current status of " . $subject . " within our institution\n";
    $content .= "2. Identify strengths, weaknesses, opportunities, and threats related to this area\n";
    $content .= "3. Evaluate the effectiveness of existing policies and procedures\n";
    $content .= "4. Develop evidence-based recommendations for improvement\n";
    $content .= "5. Outline an implementation plan with clear timelines and responsibilities\n\n";
    $content .= "The scope of this report encompasses all aspects of " . $subject . " across academic and administrative departments, focusing particularly on developments within the past academic year. While the primary focus is internal, relevant external factors and benchmarking against peer institutions have also been considered where appropriate.\n\n";
    
    $content .= "Methodology\n\n";
    $content .= "The information presented in this report was gathered through multiple data collection methods and analyzed using both quantitative and qualitative approaches. Our methodology included:\n\n";
    $content .= "• Document review: Examination of institutional policies, previous reports, meeting minutes, and relevant correspondence\n";
    $content .= "• Surveys: Collection of structured feedback from [number] stakeholders across various departments\n";
    $content .= "• Interviews: In-depth discussions with key personnel, including department heads, faculty representatives, and administrative staff\n";
    $content .= "• Focus groups: Facilitated discussions with targeted stakeholder groups to explore specific themes and concerns\n";
    $content .= "• Data analysis: Statistical analysis of relevant institutional data, including trends over time and comparative benchmarks\n\n";
    $content .= "This multi-method approach was designed to ensure comprehensive coverage of the subject matter and triangulation of findings across different sources and perspectives. Limitations of the methodology, including potential biases and gaps in available data, are acknowledged and discussed in the appropriate sections of this report.\n\n";
    
    $content .= "Findings and Analysis\n\n";
    $content .= "Based on our investigation, the following key findings have been identified:\n\n";
    $content .= "1. Finding 1: [Specific finding related to the subject]\n";
    $content .= "   Analysis: This finding suggests that [interpretation of data/evidence]. The implications for our institution include [consequences or impacts]. Several factors appear to contribute to this situation, including [contributing factors]. Stakeholder feedback indicates that [relevant perspectives].\n\n";
    $content .= "2. Finding 2: [Specific finding related to the subject]\n";
    $content .= "   Analysis: Our examination reveals that [interpretation of data/evidence]. This finding is particularly significant because [explanation of importance]. Compared to previous assessments, this represents [change or consistency]. If not addressed, this could lead to [potential consequences].\n\n";
    $content .= "3. Finding 3: [Specific finding related to the subject]\n";
    $content .= "   Analysis: The evidence demonstrates that [interpretation of data/evidence]. This pattern is consistent across [scope of observation]. Notable exceptions include [exceptions if any], which warrant further investigation. This finding aligns with/contradicts [relevant theories or expectations].\n\n";
    $content .= "4. Finding 4: [Specific finding related to the subject]\n";
    $content .= "   Analysis: Our research indicates that [interpretation of data/evidence]. This finding was unexpected and reveals important insights about [subject area]. The data suggests a correlation between [related factors], though causation cannot be definitively established without further study.\n\n";
    
    $content .= "Cross-cutting Themes\n\n";
    $content .= "Several themes emerged consistently across our findings. These cross-cutting issues provide important context for understanding the overall situation:\n\n";
    $content .= "• Communication: Patterns of information sharing and feedback mechanisms affect multiple aspects of " . $subject . "\n";
    $content .= "• Resource allocation: Questions of budgeting, staffing, and prioritization influence capabilities and outcomes\n";
    $content .= "• Institutional culture: Shared values, assumptions, and practices shape approaches and responses\n";
    $content .= "• External factors: Regulatory requirements, community expectations, and broader trends create both constraints and opportunities\n\n";
    
    $content .= "Recommendations\n\n";
    $content .= "Based on the findings, the following recommendations are proposed:\n\n";
    $content .= "1. Strategic Recommendation: [Primary recommendation]\n";
    $content .= "   Rationale: This recommendation addresses the core issues identified in Findings 1 and 3.\n";
    $content .= "   Implementation steps:\n";
    $content .= "   • Step 1: [Specific action] - Responsible party: [role/department] - Timeline: [timeframe]\n";
    $content .= "   • Step 2: [Specific action] - Responsible party: [role/department] - Timeline: [timeframe]\n";
    $content .= "   • Step 3: [Specific action] - Responsible party: [role/department] - Timeline: [timeframe]\n";
    $content .= "   Expected outcomes: [Anticipated results and benefits]\n";
    $content .= "   Resource requirements: [Staff, budget, time, or other resources needed]\n\n";
    
    $content .= "2. Operational Recommendation: [Secondary recommendation]\n";
    $content .= "   Rationale: This recommendation builds upon Finding 2 and addresses practical implementation challenges.\n";
    $content .= "   Implementation steps: [Detailed implementation plan with responsibilities and timeline]\n";
    $content .= "   Expected outcomes: [Anticipated results and benefits]\n";
    $content .= "   Resource requirements: [Staff, budget, time, or other resources needed]\n\n";
    
    $content .= "3. Policy Recommendation: [Policy-related recommendation]\n";
    $content .= "   Rationale: This recommendation responds to Finding 4 and aligns with institutional values and goals.\n";
    $content .= "   Implementation steps: [Detailed implementation plan with responsibilities and timeline]\n";
    $content .= "   Expected outcomes: [Anticipated results and benefits]\n";
    $content .= "   Resource requirements: [Staff, budget, time, or other resources needed]\n\n";
    
    $content .= "Monitoring and Evaluation Framework\n\n";
    $content .= "To ensure effective implementation and assess progress, we recommend the following monitoring and evaluation approach:\n\n";
    $content .= "• Key performance indicators (KPIs): [Specific, measurable indicators aligned with recommendations]\n";
    $content .= "• Reporting schedule: [Regular reporting timeline and responsible parties]\n";
    $content .= "• Review mechanism: [Process for periodic review and adjustment]\n";
    $content .= "• Stakeholder feedback: [Methods for ongoing collection of feedback]\n\n";
    
    $content .= "Conclusion\n\n";
    $content .= "This report has provided a comprehensive analysis of " . $subject . ". The findings indicate [summary of findings], and the recommendations outlined are designed to [purpose of recommendations].\n\n";
    $content .= "While implementation will require dedicated effort and resources, the potential benefits for the institution are substantial. By addressing these issues systematically and strategically, " . $institution . " can enhance its effectiveness, strengthen its reputation, and better fulfill its mission to provide quality education within a Catholic framework.\n\n";
    $content .= "We recommend that this report be shared with key stakeholders and that the implementation plan be initiated within the next [timeframe]. Regular updates on progress should be provided to [relevant authority or body].\n\n";
    
    $content .= "Respectfully submitted,\n\n\n";
    
    $content .= "____________________\n";
    $content .= "Name and Signature\n";
    $content .= "Position\n";
    $content .= $institution;
    
    return $content;
}

// Keep existing functions for backward compatibility
function generateLetter($prompt, $keywords) {
    return generateEnhancedLetter($prompt, $keywords);
}

function generateMemo($prompt, $keywords) {
    return generateEnhancedMemo($prompt, $keywords);
}

function generateReport($prompt, $keywords) {
    return generateEnhancedReport($prompt, $keywords);
}

function generateLeaveRequest($prompt, $keywords) {
    return generateEnhancedLeaveRequest($prompt, $keywords);
}

function generateGenericDocument($prompt, $keywords) {
    // Call one of the enhanced generators based on prompt analysis
    if (preg_match('/(memo|meeting|notice|announcement)/i', $prompt)) {
        return generateEnhancedMemo($prompt, $keywords);
    } else {
        return generateEnhancedLetter($prompt, $keywords);
    }
}
