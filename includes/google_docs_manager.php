<?php
/**
 * Google Docs Manager
 * 
 * This class handles interactions with the Google Docs API.
 */

require_once 'google_auth_handler.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Import Google API classes
use Google\Client as GoogleClient;
use Google\Service\Docs as GoogleDocs;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Docs\Document as GoogleDocument;

class GoogleDocsManager {
    private $client;
    private $docsService;
    private $driveService;
    private $userId;
    
    /**
     * Constructor
     * 
     * @param int $userId User ID
     * @throws Exception If authentication fails
     */
    public function __construct($userId) {
        $this->userId = $userId;
        
        try {
            // Initialize Google Auth Handler
            $authHandler = new GoogleAuthHandler();
            
            // Check if user has a valid token
            if (!$authHandler->hasValidToken($userId)) {
                error_log("GoogleDocsManager: User ID $userId does not have a valid token");
                throw new Exception('Authentication required: Your Google Docs connection has expired or is invalid. Please reconnect your account.');
            }
            
            // Get client
            $this->client = $authHandler->getClient();
            
            // Load token
            $token = $authHandler->loadToken($userId);
            if (!$token) {
                error_log("GoogleDocsManager: Failed to load token for user ID $userId");
                throw new Exception('Authentication required: No valid token found. Please connect your Google account.');
            }
            
            $this->client->setAccessToken($token);
            
            // Refresh token if expired
            if ($this->client->isAccessTokenExpired()) {
                error_log("GoogleDocsManager: Token expired for user ID $userId, attempting to refresh");
                if (isset($token['refresh_token'])) {
                    $newToken = $this->client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
                    if (!isset($newToken['error'])) {
                        $authHandler->saveToken($userId, $newToken);
                    } else {
                        error_log("GoogleDocsManager: Failed to refresh token: " . $newToken['error']);
                        throw new Exception('Authentication required: Failed to refresh token. Please reconnect your Google account.');
                    }
                } else {
                    error_log("GoogleDocsManager: No refresh token available for user ID $userId");
                    throw new Exception('Authentication required: No refresh token available. Please reconnect your Google account.');
                }
            }
            
            // Initialize services
            $this->docsService = new GoogleDocs($this->client);
            $this->driveService = new GoogleDrive($this->client);
            
            error_log("GoogleDocsManager: Successfully initialized for user ID $userId");
        } catch (Exception $e) {
            error_log("GoogleDocsManager initialization error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create a new Google Doc
     * 
     * @param string $title Document title
     * @param string $content Optional initial content
     * @return array Document information
     * @throws Exception If document creation fails
     */
    public function createDocument($title, $content = '') {
        try {
            error_log("GoogleDocsManager: Creating document with title '$title' for user ID {$this->userId}");
            
            // Create document
            $doc = new GoogleDocument(['title' => $title]);
            $doc = $this->docsService->documents->create($doc);
            
            $documentId = $doc->getDocumentId();
            error_log("GoogleDocsManager: Document created with ID $documentId");
            
            // Ensure the creator has explicit writer (editor) permissions
            $this->ensureCreatorHasEditPermissions($documentId);
            
            // Apply default sharing setting (e.g., anyone with the link)
            $this->applySharingDefault($documentId);

            // Add content if provided
            if (!empty($content)) {
                $this->updateDocumentContent($documentId, $content);
            }
            
            // Return document information
            return [
                'id' => $documentId,
                'title' => $title,
                'url' => "https://docs.google.com/document/d/$documentId/edit"
            ];
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            error_log("GoogleDocsManager: Error creating document: $errorMessage");
            
            // Check if this is an authentication error
            if (strpos($errorMessage, 'authentication') !== false || 
                strpos($errorMessage, 'expired') !== false || 
                strpos($errorMessage, 'token') !== false || 
                strpos($errorMessage, 'auth') !== false) {
                
                throw new Exception('Authentication required: ' . $errorMessage);
            }
            
            throw new Exception('Failed to create document: ' . $errorMessage);
        }
    }

    /**
     * Ensure the document creator has explicit writer (editor) permissions
     * This prevents "Suggesting" mode and ensures "Editing" mode
     */
    private function ensureCreatorHasEditPermissions(string $fileId): void {
        try {
            // Get the user's email from the token or database
            $userEmail = null;
            global $conn;
            if ($conn instanceof \mysqli) {
                $stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $this->userId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        $userEmail = $row['email'] ?? null;
                    }
                    $stmt->close();
                }
            }
            
            // If we have the user's email, ensure they have writer permissions
            if ($userEmail) {
                try {
                    $perm = new \Google\Service\Drive\Permission([
                        'type' => 'user',
                        'role' => 'writer', // Editor role
                        'emailAddress' => $userEmail
                    ]);
                    $this->driveService->permissions->create($fileId, $perm, [
                        'sendNotificationEmail' => false,
                        'transferOwnership' => false
                    ]);
                    error_log("GoogleDocsManager: Ensured writer permissions for user $userEmail on document $fileId");
                } catch (\Exception $e) {
                    // Permission might already exist, that's okay
                    error_log("GoogleDocsManager: Note - Could not set explicit writer permission (may already exist): " . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            error_log('ensureCreatorHasEditPermissions error: ' . $e->getMessage());
        }
    }
    
    /**
     * Apply default sharing based on settings
     * - gdocs_sharing_default: 'anyone' | 'domain' | 'restricted'
     */
    private function applySharingDefault(string $fileId): void {
        try {
            // Read setting from DB; default to 'anyone'
            $sharing = 'anyone';
            global $conn;
            if ($conn instanceof \mysqli) {
                $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name = 'gdocs_sharing_default' LIMIT 1");
                if ($stmt && $stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        $sharing = strtolower(trim($row['setting_value']));
                    }
                    $stmt->close();
                }
            }

            // 'restricted' means leave as default (no change)
            if ($sharing === 'restricted') {
                return;
            }

            // Build permission
            if ($sharing === 'domain') {
                // Try to infer domain from user's email; fallback to 'anyone'
                $domain = null;
                if ($conn instanceof \mysqli) {
                    $ustmt = $conn->prepare("SELECT email FROM users WHERE user_id = ? LIMIT 1");
                    if ($ustmt) {
                        $ustmt->bind_param('i', $this->userId);
                        $ustmt->execute();
                        $ures = $ustmt->get_result();
                        if ($u = $ures->fetch_assoc()) {
                            if (!empty($u['email']) && strpos($u['email'], '@') !== false) {
                                $domain = substr(strrchr($u['email'], '@'), 1);
                            }
                        }
                        $ustmt->close();
                    }
                }
                if ($domain) {
                    $perm = new \Google\Service\Drive\Permission([
                        'type' => 'domain',
                        'role' => 'reader',
                        'domain' => $domain,
                        'allowFileDiscovery' => false
                    ]);
                    $this->driveService->permissions->create($fileId, $perm, ['sendNotificationEmail' => false]);
                    return;
                }
                // If no domain, fall through to anyone
            }

            // Default: anyone with the link (viewer)
            $perm = new \Google\Service\Drive\Permission([
                'type' => 'anyone',
                'role' => 'reader',
                'allowFileDiscovery' => false
            ]);
            $this->driveService->permissions->create($fileId, $perm, ['sendNotificationEmail' => false]);
        } catch (\Throwable $e) {
            error_log('applySharingDefault error: ' . $e->getMessage());
        }
    }
    
    /**
     * Create a document from a template
     * 
     * @param string $templateType Template type
     * @param array $data Template data
     * @return array Document info (id, url)
     */
    public function createDocumentFromTemplate($templateType, $data = []) {
        // Get template content based on template type
        $templateContent = $this->getTemplateContent($templateType);
        $title = $this->getTemplateTitle($templateType);
        
        // Replace placeholders in template content with actual data
        $content = $this->replacePlaceholders($templateContent, $data);
        
        // Create a new document with the template content
        return $this->createDocument($title, $content);
    }
    
    /**
     * Generate a document using AI
     * 
     * @param string $prompt AI prompt
     * @return array Document info (id, url)
     */
    public function generateDocumentWithAI($prompt) {
        try {
            // Generate content using Gemini AI
            $aiContent = $this->generateAIContent($prompt);
            
            // Create a title from the prompt
            $title = $this->generateDocumentTitle($prompt);
            
            // Create the document with AI-generated content
            return $this->createDocument($title, $aiContent);
        } catch (Exception $e) {
            error_log('Error generating AI document: ' . $e->getMessage());
            
            // Fallback to basic document creation
            $title = "AI Generated: " . substr($prompt, 0, 30) . "...";
            $content = "<h1>AI Generated Document</h1><p>Based on prompt: {$prompt}</p><p>Error generating content: " . $e->getMessage() . "</p>";
            
            return $this->createDocument($title, $content);
        }
    }
    
    /**
     * Generate AI content using Gemini API
     */
    private function generateAIContent($prompt) {
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
        
        // Build enhanced prompt
        $enhancedPrompt = $this->buildEnhancedPrompt($prompt);
        
        // Call Gemini API
        return $this->callGeminiAPI($enhancedPrompt, $api_key);
    }
    
    /**
     * Generate a document title from the prompt
     */
    private function generateDocumentTitle($prompt) {
        // Extract key words from prompt for title
        $words = explode(' ', $prompt);
        $titleWords = array_slice($words, 0, 6); // Take first 6 words
        $title = implode(' ', $titleWords);
        
        // Clean up the title
        $title = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
        $title = trim($title);
        
        // Add prefix if needed
        if (strlen($title) < 10) {
            $title = "AI Generated Document: " . $title;
        }
        
        return $title;
    }
    
    /**
     * Build enhanced prompt with context
     */
    private function buildEnhancedPrompt($userPrompt) {
        $context = "You are a professional document generator for Saint Columban College in Pagadian City, Zamboanga del Sur. ";
        $context .= "Generate a well-formatted, professional document based on the user's request. ";
        $context .= "Use proper business letter/memo formatting with appropriate headers, salutations, and closings. ";
        $context .= "Include relevant details like dates, office names, and formal language appropriate for an educational institution.\n\n";
        
        $context .= "IMPORTANT FORMATTING RULES:\n";
        $context .= "- Do NOT use markdown formatting (no ** or * symbols)\n";
        $context .= "- Use plain text with proper line breaks\n";
        $context .= "- Center the institution name and location at the top\n";
        $context .= "- Use proper memo/letter structure with DATE, TO, FROM, SUBJECT fields\n";
        $context .= "- Include proper salutations and closings\n";
        $context .= "- Use formal, professional language\n\n";
        
        $context .= "User Request: " . $userPrompt . "\n\n";
        $context .= "Generate a complete, professional document. Use plain text formatting only - no markdown symbols. ";
        $context .= "Make it ready for immediate use in a professional setting with proper line breaks and structure.";
        
        return $context;
    }
    
    /**
     * Call Gemini API to generate content
     */
    private function callGeminiAPI($prompt, $api_key) {
        $data = [
            'systemInstruction' => [
                'parts' => [['text' => 'You are a professional document generator for educational institutions. Create well-formatted documents using plain text only - NO markdown formatting (no ** or * symbols). Use proper business letter/memo structure with clear headers, formal language, and appropriate line breaks.']]
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 2000,
                'response_mime_type' => 'text/plain'
            ],
            'safetySettings' => [
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
        
        $model = 'gemini-1.5-flash';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($api_key);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('cURL Error: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('API Error: HTTP ' . $httpCode . ' - ' . $response);
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['error'])) {
            throw new Exception('Gemini API Error: ' . json_encode($result['error']));
        }
        
        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception('Unexpected API response format: ' . $response);
        }
        
        $content = $result['candidates'][0]['content']['parts'][0]['text'];
        
        // Clean up the content
        $content = trim($content);
        
        // Convert to HTML if it's plain text
        if (!preg_match('/<[^>]+>/', $content)) {
            $content = nl2br(htmlspecialchars($content));
        }
        
        return $content;
    }
    
    /**
     * Get a document by ID
     * 
     * @param string $documentId Google Doc ID
     * @return array Document info
     */
    public function getDocument($documentId) {
        try {
            $doc = $this->docsService->documents->get($documentId);
            
            return [
                'id' => $doc->getDocumentId(),
                'title' => $doc->getTitle(),
                'url' => "https://docs.google.com/document/d/{$doc->getDocumentId()}/edit"
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to get Google Doc: ' . $e->getMessage());
        }
    }
    
    /**
     * Get a document's content
     * 
     * @param string $documentId Google Doc ID
     * @return string Document content
     */
    public function getDocumentContent($documentId) {
        try {
            $doc = $this->docsService->documents->get($documentId);
            
            // Extract text content from the document
            $content = '';
            foreach ($doc->getBody()->getContent() as $element) {
                if ($element->getParagraph()) {
                    foreach ($element->getParagraph()->getElements() as $paragraphElement) {
                        if ($paragraphElement->getTextRun()) {
                            $content .= $paragraphElement->getTextRun()->getContent();
                        }
                    }
                }
            }
            
            return $content;
        } catch (Exception $e) {
            throw new Exception('Failed to get Google Doc content: ' . $e->getMessage());
        }
    }
    
    /**
     * Update a document's content
     * 
     * @param string $documentId Google Doc ID
     * @param string $content HTML content to add
     * @return bool Success status
     */
    public function updateDocumentContent($documentId, $content) {
        try {
            // Clear existing content by deleting it.
            $doc = $this->docsService->documents->get($documentId, ['fields' => 'body(content)']);
            $docContent = $doc->getBody()->getContent();
            $endOfDocIndex = 1;
            if (count($docContent) > 1) {
                 $lastElement = end($docContent);
                 $endOfDocIndex = $lastElement->getEndIndex() -1;
            }

            $requests = [];
            if ($endOfDocIndex > 1) {
                $requests[] = new \Google\Service\Docs\Request([
                    'deleteContentRange' => [
                        'range' => [
                            'startIndex' => 1,
                            'endIndex' => $endOfDocIndex,
                        ]
                    ]
                ]);
            }
            
            // Convert HTML content to plain text for Google Docs
            $plainTextContent = $this->convertHtmlToPlainText($content);
            
            // Insert the new content
            $requests[] = new \Google\Service\Docs\Request([
                'insertText' => [
                    'location' => [
                        'index' => 1
                    ],
                    'text' => $plainTextContent
                ]
            ]);

            // Apply basic formatting
            $this->applyBasicFormatting($requests, $plainTextContent);

            $batchUpdateRequest = new \Google\Service\Docs\BatchUpdateDocumentRequest([
                'requests' => $requests
            ]);
            
            $this->docsService->documents->batchUpdate($documentId, $batchUpdateRequest);
            
            return true;
        } catch (\Exception $e) {
            error_log('Error updating document content: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Convert HTML content to plain text for Google Docs
     */
    private function convertHtmlToPlainText($htmlContent) {
        // First, convert markdown-style formatting to HTML
        $htmlContent = $this->convertMarkdownToHtml($htmlContent);
        
        // Remove HTML tags but preserve line breaks
        $text = strip_tags($htmlContent, '<br><p>');
        
        // Convert <br> tags to newlines
        $text = str_replace(['<br>', '<br/>', '<br />'], "\n", $text);
        
        // Convert <p> tags to double newlines for paragraph breaks
        $text = str_replace(['<p>', '</p>'], '', $text);
        
        // Clean up extra whitespace
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Convert markdown-style formatting to HTML
     */
    private function convertMarkdownToHtml($content) {
        // Convert **text** to <strong>text</strong>
        $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
        
        // Convert *text* to <em>text</em>
        $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
        
        // Convert line breaks to <br> tags
        $content = nl2br($content);
        
        return $content;
    }
    
    /**
     * Apply basic formatting to the document
     */
    private function applyBasicFormatting(&$requests, $content) {
        $lines = explode("\n", $content);
        $currentIndex = 1;
        
        foreach ($lines as $lineIndex => $line) {
            $lineLength = strlen($line);
            if ($lineLength === 0) {
                $currentIndex += 1; // Account for newline
                continue;
            }
            
            $lineStartIndex = $currentIndex;
            $lineEndIndex = $currentIndex + $lineLength;
            
            // Center and bold the first line (institution name)
            if ($lineIndex === 0) {
                $requests[] = new \Google\Service\Docs\Request([
                    'updateParagraphStyle' => [
                        'range' => [
                            'startIndex' => $lineStartIndex,
                            'endIndex' => $lineEndIndex
                        ],
                        'paragraphStyle' => [
                            'alignment' => 'CENTER'
                        ],
                        'fields' => 'alignment'
                    ]
                ]);
                
                $requests[] = new \Google\Service\Docs\Request([
                    'updateTextStyle' => [
                        'range' => [
                            'startIndex' => $lineStartIndex,
                            'endIndex' => $lineEndIndex
                        ],
                        'textStyle' => [
                            'bold' => true
                        ],
                        'fields' => 'bold'
                    ]
                ]);
            }
            
            // Center the second line (location)
            if ($lineIndex === 1) {
                $requests[] = new \Google\Service\Docs\Request([
                    'updateParagraphStyle' => [
                        'range' => [
                            'startIndex' => $lineStartIndex,
                            'endIndex' => $lineEndIndex
                        ],
                        'paragraphStyle' => [
                            'alignment' => 'CENTER'
                        ],
                        'fields' => 'alignment'
                    ]
                ]);
            }
            
            // Bold the MEMORANDUM line
            if (stripos($line, 'MEMORANDUM') !== false) {
                $requests[] = new \Google\Service\Docs\Request([
                    'updateTextStyle' => [
                        'range' => [
                            'startIndex' => $lineStartIndex,
                            'endIndex' => $lineEndIndex
                        ],
                        'textStyle' => [
                            'bold' => true
                        ],
                        'fields' => 'bold'
                    ]
                ]);
            }
            
            // Bold field labels like "DATE:", "TO:", "FROM:", "SUBJECT:"
            if (preg_match('/^(DATE|TO|FROM|SUBJECT):/', $line)) {
                $requests[] = new \Google\Service\Docs\Request([
                    'updateTextStyle' => [
                        'range' => [
                            'startIndex' => $lineStartIndex,
                            'endIndex' => $lineEndIndex
                        ],
                        'textStyle' => [
                            'bold' => true
                        ],
                        'fields' => 'bold'
                    ]
                ]);
            }
            
            $currentIndex = $lineEndIndex + 1; // +1 for newline
        }
    }
    
    /**
     * Apply a set of requests to a document using batchUpdate.
     *
     * @param string $documentId
     * @param array $requests
     * @return boolean
     */
    private function applyTemplateRequests($documentId, $requests) {
        if (empty($requests)) {
            return true;
        }

        try {
            $batchUpdateRequest = new \Google\Service\Docs\BatchUpdateDocumentRequest([
                'requests' => $requests
            ]);
            
            $this->docsService->documents->batchUpdate($documentId, $batchUpdateRequest);
            
            return true;
        } catch (\Exception $e) {
            error_log('Error applying template requests: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the embed URL for a document
     * 
     * @param string $documentId Google Doc ID
     * @param bool $editable Whether the document should be editable
     * @return string Embed URL
     */
    public function getDocumentEmbedUrl($documentId, $editable = true) {
        $mode = $editable ? 'edit' : 'preview';
        return "https://docs.google.com/document/d/{$documentId}/{$mode}?embedded=true";
    }
    
    /**
     * Get template content based on template type
     * 
     * @param string $templateType Template type
     * @return string Template content
     */
    public function getTemplateContent($templateType) {
        switch ($templateType) {
            case 'memo':
                return $this->getMemoTemplate();
            case 'letter':
                return $this->getLetterTemplate();
            case 'report':
                return $this->getReportTemplate();
            case 'travel-memo':
                return $this->getTravelMemoTemplate();
            case 'budget-request':
                return $this->getBudgetRequestTemplate();
            case 'warning':
                return $this->getWarningLetterTemplate();
            case 'leave':
                return $this->getLeaveLetterTemplate();
            case 'apology':
                return $this->getApologyTemplate();
            case 'confidential':
                return $this->getConfidentialTemplate();
            case 'activity':
                return $this->getActivityTemplate();
            case 'resignation':
                return $this->getResignationTemplate();
            case 'engagement':
                return $this->getEngagementTemplate();
            case 'urgency':
                return $this->getUrgencyTemplate();
            default:
                throw new Exception('Unknown template type: ' . $templateType);
        }
    }
    
    /**
     * Get template title based on template type
     * 
     * @param string $templateType Template type
     * @return string Template title
     */
    public function getTemplateTitle($templateType) {
        switch ($templateType) {
            case 'memo':
                return 'Memorandum';
            case 'letter':
                return 'Formal Letter';
            case 'report':
                return 'Report';
            case 'travel-memo':
                return 'Travel Memorandum';
            case 'budget-request':
                return 'Budget Request';
            case 'warning':
                return 'Warning Letter';
            case 'leave':
                return 'Leave Request';
            case 'apology':
                return 'Apology Letter';
            case 'confidential':
                return 'Confidential Letter';
            case 'activity':
                return 'Activity Letter';
            case 'resignation':
                return 'Resignation Letter';
            case 'engagement':
                return 'Engagement Letter';
            case 'urgency':
                return 'Letter of Urgency';
            default:
                return 'Untitled Document';
        }
    }

    /**
     * Replace placeholders in content with data
     * 
     * @param string $content Template content with placeholders
     * @param array $data Data to fill in
     * @return string Content with placeholders replaced
     */
    public function replacePlaceholders($content, $data) {
        if (!is_array($data)) {
            error_log('replacePlaceholders was called with non-array data.');
            return $content;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Handle nested arrays (like for project items)
                $blockPattern = "/{{\s*#{$key}\s*}}(.*?){{\s*\/{$key}\s*}}/s";
                preg_match($blockPattern, $content, $matches);
                if (isset($matches[1])) {
                    $itemTemplate = $matches[1];
                    $renderedItems = '';
                    foreach ($value as $item) {
                        $renderedItems .= $this->replacePlaceholders($itemTemplate, $item);
                    }
                    $content = preg_replace($blockPattern, $renderedItems, $content);
                }
            } else {
                // Handle simple key-value pairs
                $content = str_replace('{{' . $key . '}}', htmlspecialchars($value), $content);
            }
        }
        
        return $content;
    }
    
    /**
     * Get header and footer template content
     * 
     * @param string $type Type of letterhead ('long' or 'short')
     * @return array Header and footer template content
     */
    private function getHeaderFooterTemplate($type = 'long') {
        // This functionality will be handled differently.
        // For now, we'll build the content directly in the templates.
        return [
            'header' => '',
            'footer' => ''
        ];
    }
    
    /**
     * Get memorandum template content
     * 
     * @return string Memo template content
     */
    private function getMemoTemplate() {
        return "SAINT COLUMBAN COLLEGE\n\n\n\n" .
            "TO:             {{to}}\n" .
            "FROM:           {{from_office}}\n" .
            "                {{from_name}}\n" .
            "                {{from_position}}\n" .
            "SUBJECT:        {{subject}}\n" .
            "DATE:           {{date}}\n" .
            "COPY TO:        {{copy_to}}\n\n" .
            "----------------------------------------------------------------------------------------------------------------\n\n" .
            "Panagdait sa Dios, sa tanan, ug sa tanang kabuhatan!\n\n" .
            "{{body_intro}}\n\n" .
            "{{body_details}}\n\n" .
            "{{body_closing}}\n\n" .
            "{{motto}}\n\n\n" .
            "{{signature_name}}\n" .
            "{{signature_position}}\n";
    }
    
    /**
     * Get letter template content
     * 
     * @return string Letter template content
     */
    private function getLetterTemplate() {
        $templates = $this->getHeaderFooterTemplate('long');
        
        return $templates['header'] . '
                <p style="text-align: right;">{{date}}</p>
                <p>{{recipient_name}}<br>
                {{recipient_position}}<br>
                {{recipient_address}}</p>
                <p>Dear {{salutation}},</p>
                <p>{{content}}</p>
                <p>Sincerely,</p>
                <p>{{sender_name}}<br>
                {{sender_position}}</p>' . $templates['footer'];
    }
    
    /**
     * Get report template content
     * 
     * @return string Report template content
     */
    private function getReportTemplate() {
        $templates = $this->getHeaderFooterTemplate('long');
        
        return $templates['header'] . '
                <h1 style="text-align: center;">{{title}}</h1>
                <h2 style="text-align: center;">{{subtitle}}</h2>
                <p style="text-align: right;">Prepared by: {{author}}<br>
                Date: {{date}}</p>
                <h2>Executive Summary</h2>
                <p>{{summary}}</p>
                <h2>Introduction</h2>
                <p>{{introduction}}</p>
                <h2>Findings</h2>
                <p>{{findings}}</p>
                <h2>Conclusion</h2>
                <p>{{conclusion}}</p>
                <h2>Recommendations</h2>
                <p>{{recommendations}}</p>' . $templates['footer'];
    }
    
    /**
     * Get travel memo template content
     * 
     * @return string Travel memo template content
     */
    private function getTravelMemoTemplate() {
        return "SAINT COLUMBAN COLLEGE\n\n" .
            "TRAVEL MEMO\n\n" .
            "TO:             {{to}}\n" .
            "FROM:           {{from_office}}\n" .
            "                {{from_name}}\n" .
            "                {{from_position}}\n" .
            "SUBJECT:        Travel to {{destination}}\n" .
            "DATE:           {{date}}\n" .
            "COPY TO:        {{copy_to}}\n\n" .
            "----------------------------------------------------------------------------------------------------------------\n\n" .
            "Purpose:        {{purpose}}\n" .
            "Travel Dates:   {{travel_dates}}\n" .
            "Destination:    {{destination}}\n" .
            "Participants:   {{participants}}\n" .
            "Funding:        {{funding_source}}\n\n" .
            "{{body_details}}\n\n" .
            "Kindly allow and facilitate the necessary arrangements for this travel.\n\n" .
            "In Saint Columban,\n\n" .
            "{{signature_name}}\n" .
            "{{signature_position}}\n";
    }

    private function getBudgetRequestTemplate() {
        return <<<HTML
Saint Columban College
Management Information System
Pagadian City

{{date}}

{{recipient_name}}
{{recipient_position}}
{{recipient_institution}}

{{salutation}}

{{greeting}}

{{body_introduction}}

{{#projects}}
{{name}}
{{cost}}
{{source_of_funds_title}}
{{#sources}}
- {{item}}: {{amount}}
{{/sources}}
{{total}}

{{/projects}}

{{body_conclusion}}

{{closing_salutation}}

{{sender_name}}
{{sender_position}}

Recommending Approval by:
{{recommending_approval_name}}
{{recommending_approval_position}}

Approved by:
{{approved_by_name}}
{{approved_by_position}}
HTML;
    }

    private function getWarningLetterTemplate() {
        return "SAINT COLUMBAN COLLEGE\nManagement Information System\nPagadian City\n\n" .
            "{{date}}\n\n" .
            "{{recipient_name}}\n" .
            "{{recipient_position}}\n\n" .
            "Subject: Warning Letter – {{issue}}\n\n" .
            "Dear {{salutation}},\n\n" .
            "This serves as an official warning regarding {{issue}}. Our records show {{details}}.\n\n" .
            "This behavior disrupts office operations and violates policy. Continued violations may result in further disciplinary action.\n\n" .
            "We expect immediate and sustained improvement. If you are experiencing personal circumstances, please coordinate with HR.\n\n" .
            "Sincerely,\n\n" .
            "{{sender_name}}\n" .
            "{{sender_position}}\n";
    }
    
    /**
     * Get leave letter template content
     * 
     * @return string Leave letter template content
     */
    private function getLeaveLetterTemplate() {
        return "SAINT COLUMBAN COLLEGE\nManagement Information System\nPagadian City\n\n" .
            "{{date}}\n\n" .
            "{{recipient_name}}\n" .
            "{{recipient_position}}\n" .
            "{{recipient_institution}}\n\n" .
            "Dear {{salutation}},\n\n" .
            "I am respectfully requesting leave from {{start_date}} to {{end_date}} ({{number_of_days}} days) due to {{reason}}. I will complete pending tasks and coordinate a proper handover to ensure continuity of work.\n\n" .
            "Thank you for your consideration.\n\n" .
            "Sincerely,\n\n" .
            "{{sender_name}}\n" .
            "{{sender_position}}\n";
    }

    // Additional templates used by the template picker
    private function getApologyTemplate() {
        return "SAINT COLUMBAN COLLEGE\nManagement Information System\nPagadian City\n\n" .
            "{{date}}\n\n" .
            "{{recipient_name}}\n" .
            "{{recipient_position}}\n" .
            "{{recipient_institution}}\n\n" .
            "Subject: Apology for {{subject_reason}}\n\n" .
            "Dear {{salutation}},\n\n" .
            "I sincerely apologize for {{incident_description}}. I understand the inconvenience this may have caused and take full responsibility. I have taken steps to ensure this does not happen again, including {{remedial_actions}}.\n\n" .
            "Thank you for your understanding.\n\n" .
            "Sincerely,\n\n" .
            "{{sender_name}}\n" .
            "{{sender_position}}\n";
    }

    private function getConfidentialTemplate() {
        return "SAINT COLUMBAN COLLEGE\nManagement Information System\nPagadian City\n\n" .
            "CONFIDENTIAL\n\n" .
            "{{date}}\n\n" .
            "{{recipient_name}}\n" .
            "{{recipient_position}}\n" .
            "{{recipient_institution}}\n\n" .
            "Subject: {{subject}}\n\n" .
            "Dear {{salutation}},\n\n" .
            "This letter contains confidential information regarding {{confidential_topic}}. Please treat the contents with discretion and share only with authorized personnel.\n\n" .
            "{{body_details}}\n\n" .
            "Sincerely,\n\n" .
            "{{sender_name}}\n" .
            "{{sender_position}}\n";
    }

    private function getActivityTemplate() {
        return "SAINT COLUMBAN COLLEGE\nManagement Information System\nPagadian City\n\n" .
            "{{date}}\n\n" .
            "{{recipient_name}}\n\n" .
            "Subject: Activity – {{activity_title}}\n\n" .
            "Dear {{salutation}},\n\n" .
            "We will conduct {{activity_title}} on {{activity_date}} at {{activity_location}}. The activity aims to {{activity_objective}}.\n\n" .
            "Kindly ensure participants are informed and necessary preparations are made.\n\n" .
            "In Saint Columban,\n\n" .
            "{{sender_name}}\n" .
            "{{sender_position}}\n";
    }

    private function getResignationTemplate() {
        return "SAINT COLUMBAN COLLEGE\nManagement Information System\nPagadian City\n\n" .
            "{{date}}\n\n" .
            "{{recipient_name}}\n" .
            "{{recipient_position}}\n" .
            "{{recipient_institution}}\n\n" .
            "Subject: Resignation Letter\n\n" .
            "Dear {{salutation}},\n\n" .
            "I am formally resigning from my position as {{current_position}} at {{institution}}, effective {{last_working_day}}. I am grateful for the opportunities and will ensure a smooth transition and proper handover.\n\n" .
            "Thank you for the support and experience.\n\n" .
            "Respectfully,\n\n" .
            "{{sender_name}}\n" .
            "{{sender_position}}\n";
    }

    private function getEngagementTemplate() {
        return "SAINT COLUMBAN COLLEGE\nManagement Information System\nPagadian City\n\n" .
            "{{date}}\n\n" .
            "{{recipient_name}}\n\n" .
            "Subject: Letter of Engagement\n\n" .
            "Dear {{salutation}},\n\n" .
            "We are pleased to formally engage your services as {{role_title}} effective {{start_date}}. This engagement is governed by the terms in the attached agreement.\n\n" .
            "Please confirm acceptance by signing below.\n\n" .
            "Sincerely,\n\n" .
            "{{sender_name}}\n" .
            "{{sender_position}}\n";
    }

    private function getUrgencyTemplate() {
        return "SAINT COLUMBAN COLLEGE\nManagement Information System\nPagadian City\n\n" .
            "{{date}}\n\n" .
            "Subject: Letter of Urgency – {{subject}}\n\n" .
            "Dear {{salutation}},\n\n" .
            "This letter requests immediate attention regarding {{urgency_topic}}.\n\n" .
            "{{body_details}}\n\n" .
            "Thank you for your prompt action.\n\n" .
            "In Saint Columban,\n\n" .
            "{{sender_name}}\n" .
            "{{sender_position}}\n";
    }
}
