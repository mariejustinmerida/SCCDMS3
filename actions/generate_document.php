<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../vendor/autoload.php';

// Only allow authenticated users
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

header('Content-Type: application/json');

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);
$prompt = isset($data['prompt']) ? $data['prompt'] : '';

if (empty($prompt)) {
    echo json_encode(['success' => false, 'message' => 'Empty prompt']);
    exit;
}

// Log the prompt for debugging
error_log("Document generation prompt: " . $prompt);

// Generate document based on prompt
$result = generateDocument($prompt);

// Return the result
echo json_encode($result);

/**
 * Generate a document based on a prompt
 * 
 * @param string $prompt The user's prompt
 * @return array The generated document data
 */
function generateDocument($prompt) {
    // Extract document type from prompt
    $docType = "general";
    $title = "Document";
    
    // Determine document type based on keywords in the prompt
    if (preg_match('/(leave|absence|vacation|time off|sick)/i', $prompt)) {
        $docType = "leave";
        $title = "Leave Request Letter";
    } elseif (preg_match('/(resign|resignation|quit|leaving|stepping down)/i', $prompt)) {
        $docType = "resignation";
        $title = "Resignation Letter";
    } elseif (preg_match('/(memo|memorandum|announcement)/i', $prompt)) {
        $docType = "memo";
        $title = "Memorandum";
    } elseif (preg_match('/(request|asking for)\s+([^.]+)/i', $prompt)) {
        $docType = "request";
        $title = "Request Letter";
    } elseif (preg_match('/(report|summary|analysis|findings)/i', $prompt)) {
        $docType = "report";
        $title = "Report";
    } elseif (preg_match('/(complaint|grievance)/i', $prompt)) {
        $docType = "complaint";
        $title = "Complaint Letter";
    } elseif (preg_match('/(application|job|position|employment)/i', $prompt)) {
        $docType = "application";
        $title = "Application Letter";
    }
    
    // Extract dates from the prompt
    $dates = extractDates($prompt);
    $startDate = $dates['start_date'] ?? date("F d, Y");
    $endDate = $dates['end_date'] ?? '';
    
    // Extract names from the prompt
    $names = extractNames($prompt);
    $recipientName = $names['recipient'] ?? "Sir/Madam";
    $senderName = $names['sender'] ?? $_SESSION['username'] ?? "Sender";
    
    // Extract reason or subject from the prompt
    $reason = extractReason($prompt);
    
    // Generate a better title from the prompt
    $extractedTitle = extractTitle($prompt, $docType);
    if (!empty($extractedTitle)) {
        $title = $extractedTitle;
    }
    
    // Get the template for the document type
    $content = getDocumentTemplate($docType);
    
    // Replace placeholders with extracted information
    $content = str_replace("[Current Date]", date("F d, Y"), $content);
    $content = str_replace("[start date]", $startDate, $content);
    $content = str_replace("[end date]", $endDate, $content);
    $content = str_replace("[reason for leave]", $reason, $content);
    $content = str_replace("[Recipient's Name/Sir/Madam]", $recipientName, $content);
    $content = str_replace("[Your Name]", $senderName, $content);
    $content = str_replace("[Document Subject]", $title, $content);
    $content = str_replace("[REPORT TITLE]", strtoupper($title), $content);
    $content = str_replace("[Memo Subject]", $title, $content);
    $content = str_replace("[Subject of Request]", $reason, $content);
    
    // Add more context from the prompt to the body
    $content = enhanceContentFromPrompt($content, $prompt, $docType);
    
    return [
        'success' => true,
        'content' => $content,
        'title' => $title
    ];
}

/**
 * Extract dates from the prompt
 * 
 * @param string $prompt The user's prompt
 * @return array Associative array with start_date and end_date
 */
function extractDates($prompt) {
    $dates = [
        'start_date' => '',
        'end_date' => ''
    ];
    
    // Match date ranges like "December 15-20, 2023" or "from December 15 to December 20, 2023"
    if (preg_match('/(?:from\s+)?(\w+\s+\d{1,2}(?:st|nd|rd|th)?(?:,\s*\d{4})?)\s*(?:to|-|–|until|through)\s*(\w+\s+\d{1,2}(?:st|nd|rd|th)?(?:,\s*\d{4})?)/i', $prompt, $matches)) {
        $dates['start_date'] = $matches[1];
        $dates['end_date'] = $matches[2];
    } 
    // Match single dates like "on December 15, 2023"
    elseif (preg_match('/(?:on|for|at)\s+(\w+\s+\d{1,2}(?:st|nd|rd|th)?(?:,\s*\d{4})?)/i', $prompt, $matches)) {
        $dates['start_date'] = $matches[1];
    }
    
    return $dates;
}

/**
 * Extract names from the prompt
 * 
 * @param string $prompt The user's prompt
 * @return array Associative array with recipient and sender names
 */
function extractNames($prompt) {
    $names = [
        'recipient' => '',
        'sender' => ''
    ];
    
    // Try to extract recipient name
    if (preg_match('/(?:to|for|addressed to)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,2})/i', $prompt, $matches)) {
        $names['recipient'] = $matches[1];
    }
    
    // Try to extract sender name
    if (preg_match('/(?:from|by|sincerely|regards)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,2})/i', $prompt, $matches)) {
        $names['sender'] = $matches[1];
    }
    
    return $names;
}

/**
 * Extract reason or subject from the prompt
 * 
 * @param string $prompt The user's prompt
 * @return string The extracted reason
 */
function extractReason($prompt) {
    $reason = '';
    
    // Try to extract reason patterns
    if (preg_match('/(?:due to|because of|reason|for)\s+([^.,]+)/i', $prompt, $matches)) {
        $reason = trim($matches[1]);
    } elseif (preg_match('/(?:regarding|about|concerning|on the matter of)\s+([^.,]+)/i', $prompt, $matches)) {
        $reason = trim($matches[1]);
    }
    
    return $reason;
}

/**
 * Extract a title from the prompt
 * 
 * @param string $prompt The user's prompt
 * @param string $docType The document type
 * @return string The extracted title
 */
function extractTitle($prompt, $docType) {
    $title = '';
    
    // Try to extract title patterns
    if (preg_match('/(?:letter|request|memo|report)\s+(?:for|about|on|regarding)\s+([^.,]+)/i', $prompt, $matches)) {
        $title = ucwords(trim($matches[1]));
    } elseif (preg_match('/(?:write|create|generate)\s+(?:a|an)\s+([^.,]+)/i', $prompt, $matches)) {
        $title = ucwords(trim($matches[1]));
    }
    
    // If we couldn't extract a good title, create one based on document type
    if (empty($title)) {
        switch ($docType) {
            case 'leave':
                $title = "Leave Request";
                break;
            case 'resignation':
                $title = "Resignation Letter";
                break;
            case 'memo':
                $title = "Office Memorandum";
                break;
            case 'request':
                $title = "Request Letter";
                break;
            case 'report':
                $title = "Status Report";
                break;
            case 'complaint':
                $title = "Formal Complaint";
                break;
            case 'application':
                $title = "Job Application";
                break;
            default:
                $title = "Official Document";
        }
    }
    
    return $title;
}

/**
 * Enhance the content with more context from the prompt
 * 
 * @param string $content The template content
 * @param string $prompt The user's prompt
 * @param string $docType The document type
 * @return string The enhanced content
 */
function enhanceContentFromPrompt($content, $prompt, $docType) {
    // Extract key details from the prompt
    $details = [];
    
    // Look for specific details based on document type
    switch ($docType) {
        case 'leave':
            if (preg_match('/(?:due to|because of|reason|for)\s+([^.]+)/i', $prompt, $matches)) {
                $details['reason'] = trim($matches[1]);
                $content = str_replace("[Main content of the memo - Paragraph 1]", "I am requesting leave " . $details['reason'] . ".", $content);
            }
            break;
            
        case 'resignation':
            if (preg_match('/(?:resign|quit)\s+(?:due to|because of|for)\s+([^.]+)/i', $prompt, $matches)) {
                $details['reason'] = trim($matches[1]);
                $content = str_replace("[Body paragraph 1 - provide details and supporting information]", 
                    "I have decided to resign " . $details['reason'] . ". This was not an easy decision to make, but I believe it is the right step for my career at this time.", $content);
            }
            break;
            
        case 'request':
            if (preg_match('/(?:request|asking for)\s+([^.]+)/i', $prompt, $matches)) {
                $details['request'] = trim($matches[1]);
                $content = str_replace("[Additional details about your request - Paragraph 1]", 
                    "I am specifically requesting " . $details['request'] . ". This is important because it will help me to perform my duties more effectively.", $content);
            }
            break;
    }
    
    // Replace generic placeholders with more specific content
    $content = str_replace("[Introduction paragraph - briefly state the purpose of the document]", 
        "I am writing this letter regarding " . extractReason($prompt) . ".", $content);
    
    $content = str_replace("[Body paragraph 1 - provide details and supporting information]", 
        "As mentioned in my request, I would like to formally document this matter for your consideration and appropriate action.", $content);
    
    $content = str_replace("[Body paragraph 2 - additional information or context]", 
        "I believe this is important for our department and will contribute positively to our overall objectives.", $content);
    
    $content = str_replace("[Closing paragraph - summarize and include any call to action or next steps]", 
        "Thank you for your time and consideration. I look forward to your response and am available to provide any additional information if needed.", $content);
    
    return $content;
}

/**
 * Get the document template for a specific document type
 * 
 * @param string $docType The document type
 * @return string The template HTML
 */
function getDocumentTemplate($docType) {
    $templates = [
        "leave" => "<div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <div style='text-align: right;'>[Current Date]</div>
                    <div style='margin-top: 20px;'>
                        <p>The Department Head<br>
                        School of Computing and Communications<br>
                        Your University<br>
                        City, State ZIP</p>
                    </div>
                    <div style='margin-top: 20px;'>
                        <p>Subject: Request for Leave of Absence</p>
                    </div>
                    <div style='margin-top: 20px;'>
                        <p>Dear Sir/Madam,</p>
                        <p>I am writing to request a leave of absence from [start date] to [end date] due to [reason for leave].</p>
                        <p>During my absence, I have arranged for my colleagues to cover my responsibilities. I will ensure all pending tasks are completed before my leave begins, and I will be available via email for any urgent matters.</p>
                        <p>Thank you for considering my request. I look forward to your approval.</p>
                    </div>
                    <div style='margin-top: 30px;'>
                        <p>Sincerely,</p>
                        <p>[Your Name]<br>
                        [Your Position]<br>
                        [Your Department]<br>
                        [Your Contact Information]</p>
                    </div>
                </div>",
        
        "resignation" => "<div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <div style='text-align: right;'>[Current Date]</div>
                    <div style='margin-top: 20px;'>
                        <p>The Department Head<br>
                        School of Computing and Communications<br>
                        Your University<br>
                        City, State ZIP</p>
                    </div>
                    <div style='margin-top: 20px;'>
                        <p>Subject: Letter of Resignation</p>
                    </div>
                    <div style='margin-top: 20px;'>
                        <p>Dear Sir/Madam,</p>
                        <p>I am writing to inform you of my decision to resign from my position as [Your Position] at [Department/Organization], effective [Last Working Day, typically two weeks from now].</p>
                        <p>I appreciate the opportunities for professional growth and development that have been provided to me during my time here. The experience and skills I have gained will be invaluable to my career.</p>
                        <p>Please let me know how I can assist with the transition process. I am willing to help train my replacement and ensure all my responsibilities are properly handed over before my departure.</p>
                        <p>Thank you for your understanding and support during my time at [Department/Organization].</p>
                    </div>
                    <div style='margin-top: 30px;'>
                        <p>Sincerely,</p>
                        <p>[Your Name]<br>
                        [Your Position]<br>
                        [Your Department]<br>
                        [Your Contact Information]</p>
                    </div>
                </div>",
                
        "memo" => "<div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <div style='text-align: center; font-weight: bold; font-size: 18px; margin-bottom: 20px;'>MEMORANDUM</div>
                    <div>
                        <p><strong>DATE:</strong> [Current Date]</p>
                        <p><strong>TO:</strong> All Concerned</p>
                        <p><strong>FROM:</strong> [Your Name]</p>
                        <p><strong>SUBJECT:</strong> [Memo Subject]</p>
                    </div>
                    <div style='margin-top: 20px;'>
                        <p>This memorandum is to inform all concerned about the following matter:</p>
                        <p>[Main content of the memo - Paragraph 1]</p>
                        <p>[Main content of the memo - Paragraph 2]</p>
                        <p>Please note that this information is effective immediately.</p>
                        <p>For questions or clarifications, please contact the undersigned.</p>
                    </div>
                </div>",
                
        "request" => "<div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <div style='text-align: right;'>[Current Date]</div>
                    <div style='margin-top: 20px;'>
                        <p>The Department Head<br>
                        School of Computing and Communications<br>
                        Your University<br>
                        City, State ZIP</p>
                    </div>
                    <div style='margin-top: 20px;'>
                        <p>Subject: Request for [Subject of Request]</p>
                    </div>
                    <div style='margin-top: 20px;'>
                        <p>Dear [Recipient's Name/Sir/Madam],</p>
                        <p>I am writing to request your approval for [Subject of Request].</p>
                        <p>[Additional details about your request - Paragraph 1]</p>
                        <p>[Additional details about your request - Paragraph 2]</p>
                        <p>I would appreciate your consideration of this request. Please let me know if you need any additional information.</p>
                        <p>Thank you for your time and consideration.</p>
                    </div>
                    <div style='margin-top: 30px;'>
                        <p>Sincerely,</p>
                        <p>[Your Name]<br>
                        [Your Position]<br>
                        [Your Department]<br>
                        [Your Contact Information]</p>
                    </div>
                </div>",
                
        "report" => "<div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <div style='text-align: center; font-weight: bold; font-size: 18px; margin-bottom: 10px;'>[REPORT TITLE]</div>
                    <div style='text-align: center; margin-bottom: 20px;'>Prepared by: [Your Name] | Date: [Current Date]</div>
                    
                    <div style='margin-top: 20px;'>
                        <h2 style='font-size: 16px;'>1. Executive Summary</h2>
                        <p>This report provides a comprehensive analysis of the subject matter with key findings and recommendations.</p>
                    </div>
                    
                    <div style='margin-top: 20px;'>
                        <h2 style='font-size: 16px;'>2. Introduction</h2>
                        <p>This report was prepared to address the need for information regarding the subject matter.</p>
                    </div>
                    
                    <div style='margin-top: 20px;'>
                        <h2 style='font-size: 16px;'>3. Methodology</h2>
                        <p>Information was gathered through research, interviews, and analysis of relevant data.</p>
                    </div>
                    
                    <div style='margin-top: 20px;'>
                        <h2 style='font-size: 16px;'>4. Findings</h2>
                        <p>The investigation revealed several important findings that are detailed in this section.</p>
                    </div>
                    
                    <div style='margin-top: 20px;'>
                        <h2 style='font-size: 16px;'>5. Conclusions</h2>
                        <p>Based on the findings, we can conclude that certain actions are recommended.</p>
                    </div>
                    
                    <div style='margin-top: 20px;'>
                        <h2 style='font-size: 16px;'>6. Recommendations</h2>
                        <p>We recommend the following actions based on our analysis.</p>
                    </div>
                </div>",
                
        "complaint" => "<div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <div style='text-align: right;'>[Current Date]</div>
                    <div style='margin-top: 20px;'>
                        <p>The Department Head<br>
                        School of Computing and Communications<br>
                        Your University<br>
                        City, State ZIP</p>
                    </div>
                    <div style='margin-top: 20px;'>
                        <p>Subject: Formal Complaint</p>
                    </div>
                    <div style='margin-top: 20px;'>
                        <p>Dear [Recipient's Name/Sir/Madam],</p>
                        <p>I am writing to formally complain about an issue that has been affecting my work environment.</p>
                        <p>The issue in question involves [brief description of the complaint]. This has been ongoing since [timeframe] and has impacted [describe impact].</p>
                        <p>I have attempted to resolve this matter by [describe previous attempts at resolution], but unfortunately, the issue persists.</p>
                        <p>I would appreciate your intervention in this matter. I am available to discuss this further at your convenience.</p>
                        <p>Thank you for your attention to this matter.</p>
                    </div>
                    <div style='margin-top: 30px;'>
                        <p>Sincerely,</p>
                        <p>[Your Name]<br>
                        [Your Position]<br>
                        [Your Department]<br>
                        [Your Contact Information]</p>
                    </div>
                </div>",
                
        "application" => "<div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <div style='text-align: right;'>[Current Date]</div>
                    <div style='margin-top: 20px;'>
                        <p>The Hiring Manager<br>
                        [Company/Organization Name]<br>
                        [Address]</p>
                    </div>
                    <div style='margin-top: 20px;'>
                        <p>Subject: Application for [Position]</p>
                    </div>
                    <div style='margin-top: 20px;'>
                        <p>Dear [Recipient's Name/Sir/Madam],</p>
                        <p>I am writing to express my interest in the [Position] position at [Company/Organization Name] as advertised on [where you saw the job posting].</p>
                        <p>With my background in [relevant field] and experience in [relevant skills/experience], I believe I am well-qualified for this position. My [specific achievement or qualification] has prepared me to make significant contributions to your team.</p>
                        <p>I am particularly interested in this position because [reason for interest in the position/company]. I am confident that my skills in [key skills relevant to the job] align well with the requirements outlined in the job description.</p>
                        <p>Thank you for considering my application. I look forward to the opportunity to discuss how my experience and skills would benefit your organization.</p>
                    </div>
                    <div style='margin-top: 30px;'>
                        <p>Sincerely,</p>
                        <p>[Your Name]<br>
                        [Your Contact Information]</p>
                    </div>
                </div>",
                
        "general" => "<div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <div style='text-align: right;'>[Current Date]</div>
                    <div style='margin-top: 20px;'>
                        <p>The Department Head<br>
                        School of Computing and Communications<br>
                        Your University<br>
                        City, State ZIP</p>
                    </div>
                    <div style='margin-top: 20px;'>
                        <p>Subject: [Document Subject]</p>
                    </div>
                    <div style='margin-top: 20px;'>
                        <p>Dear [Recipient's Name/Sir/Madam],</p>
                        <p>[Introduction paragraph - briefly state the purpose of the document]</p>
                        <p>[Body paragraph 1 - provide details and supporting information]</p>
                        <p>[Body paragraph 2 - additional information or context]</p>
                        <p>[Closing paragraph - summarize and include any call to action or next steps]</p>
                    </div>
                    <div style='margin-top: 30px;'>
                        <p>Sincerely,</p>
                        <p>[Your Name]<br>
                        [Your Position]<br>
                        [Your Department]<br>
                        [Your Contact Information]</p>
                    </div>
                </div>"
    ];
    
    return $templates[$docType] ?? $templates["general"];
}
?>
