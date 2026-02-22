<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';

// Only allow authenticated users
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

header('Content-Type: application/json');

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$templateType = isset($data['template_type']) ? $data['template_type'] : '';

if (empty($templateType)) {
    echo json_encode(['success' => false, 'message' => 'Template type not specified']);
    exit;
}

// Get document type ID based on template type
$typeId = null;
switch ($templateType) {
    case 'leave':
        $typeId = 3; // Leave Application
        break;
    case 'request':
        $typeId = 1; // Requisition Letter
        break;
    case 'travel':
        $typeId = 2; // Travel Order
        break;
    // Add more mappings as needed
}

// Get template content
$content = getDocumentTemplate($templateType);
$title = getTemplateTitle($templateType);

// Return the template data
echo json_encode([
    'success' => true,
    'content' => $content,
    'title' => $title,
    'type_id' => $typeId
]);

/**
 * Get the document template for a specific document type
 * 
 * @param string $docType The document type
 * @return string The template HTML
 */
function getDocumentTemplate($docType) {
    // Common letterhead HTML for all templates
    if (!isset($letterhead_header)) {
        $letterhead_header = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6;">
            <!-- Letterhead Header -->
            <div style="background: linear-gradient(to right, #006400, #008000); padding: 15px; position: relative; overflow: hidden; border-bottom: 5px solid #FFD700;">
                <div style="display: flex; align-items: center;">
                    <div style="width: 80px; margin-right: 10px;">
                        <img src="../pages/assets/images/scc-logo.php" alt="SCC Logo" style="width: 100%; height: auto; border-radius: 50%;">
                    </div>
                    <div style="flex-grow: 1;">
                        <h1 style="color: #FFD700; font-size: 28px; margin: 0; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">SAINT COLUMBAN COLLEGE</h1>
                        <div style="color: white; font-size: 11px; margin-top: 5px;">
                            <p style="margin: 0;">Corner V. Cañizares - Sagunt Streets, San Francisco District, Pagadian City</p>
                            <p style="margin: 0;">Tel Nos: 2151799 / 2151800 | Fax No: 2141200 | Website: www.sccpag.edu.ph | E-mail: saintcolumbanpagadian@sccpag.edu.ph</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Document Content -->
            <div style="min-height: 600px; padding: 30px; background-image: url(\'../pages/assets/images/scc-watermark.php\'); background-repeat: no-repeat; background-position: center; background-size: 50% auto; background-opacity: 0.05;">
        ';
    }

    if (!isset($letterhead_footer)) {
        $letterhead_footer = '
            </div>
            
            <!-- Letterhead Footer -->
            <div style="background: linear-gradient(to right, #006400, #008000); padding: 10px; border-top: 5px solid #FFD700; position: relative;">
                <div style="display: flex; justify-content: center; align-items: center;">
                    <div style="text-align: center;">
                        <img src="../pages/assets/images/scc-acts-logo.php" alt="SCC ACTs Logo" style="height: 50px; width: auto;">
                        <p style="color: white; font-size: 10px; margin: 5px 0 0 0;">Achieves Excellence | Cultivates a peaceful environment | Takes care of Mother Earth | Serves humanity</p>
                    </div>
                </div>
            </div>
        </div>
        ';
    }
    
    $templates = [
        "leave" => $letterhead_header . '
                    <div style="text-align: right;">' . date("F d, Y") . '</div>
                    <div style="margin-top: 20px;">
                        <p>The Department Head<br>
                        School of Computing and Communications<br>
                        Saint Columban College<br>
                        Pagadian City</p>
                    </div>
                    <div style="margin-top: 20px;">
                        <p><strong>Subject: Request for Leave of Absence</strong></p>
                    </div>
                    <div style="margin-top: 20px;">
                        <p>Dear Sir/Madam,</p>
                        <p>I am writing to request a leave of absence from [start date] to [end date] due to [reason for leave].</p>
                        <p>During my absence, I have arranged for my colleagues to cover my responsibilities. I will ensure all pending tasks are completed before my leave begins, and I will be available via email for any urgent matters.</p>
                        <p>Thank you for considering my request. I look forward to your approval.</p>
                    </div>
                    <div style="margin-top: 30px;">
                        <p>Respectfully yours,</p>
                        <p>' . ($_SESSION['username'] ?? "[Your Name]") . '<br>
                        [Your Position]<br>
                        [Your Department]<br>
                        [Your Contact Information]</p>
                    </div>
                ' . $letterhead_footer,
        
        "resignation" => $letterhead_header . '
                    <div style="text-align: right;">' . date("F d, Y") . '</div>
                    <div style="margin-top: 20px;">
                        <p>The Department Head<br>
                        School of Computing and Communications<br>
                        Saint Columban College<br>
                        Pagadian City</p>
                    </div>
                    <div style="margin-top: 20px;">
                        <p><strong>Subject: Letter of Resignation</strong></p>
                    </div>
                    <div style="margin-top: 20px;">
                        <p>Dear Sir/Madam,</p>
                        <p>I am writing to formally notify you of my resignation from my position as [Your Position] at the School of Computing and Communications, effective [Last Working Day, typically two weeks from the current date].</p>
                        <p>I appreciate the opportunities for professional development and growth that you have provided during my time here. I have enjoyed working with the team and am grateful for the support provided to me.</p>
                        <p>I will do everything possible to ensure a smooth transition. I am willing to assist in the recruitment and training of my replacement if necessary.</p>
                        <p>Thank you for your understanding. I wish you and the department continued success.</p>
                    </div>
                    <div style="margin-top: 30px;">
                        <p>Respectfully yours,</p>
                        <p>' . ($_SESSION['username'] ?? "[Your Name]") . '<br>
                        [Your Position]<br>
                        [Your Department]<br>
                        [Your Contact Information]</p>
                    </div>
                ' . $letterhead_footer,
                
        "memo" => $letterhead_header . '
                    <div style="text-align: center; font-weight: bold; font-size: 18px; margin-bottom: 20px;">MEMORANDUM</div>
                    <div style="margin-bottom: 20px;">
                        <table style="width: 100%;">
                            <tr>
                                <td style="width: 15%; font-weight: bold;">TO:</td>
                                <td>[Recipient(s)]</td>
                            </tr>
                            <tr>
                                <td style="width: 15%; font-weight: bold;">FROM:</td>
                                <td>' . ($_SESSION['username'] ?? "[Your Name]") . ', [Your Position]</td>
                            </tr>
                            <tr>
                                <td style="width: 15%; font-weight: bold;">DATE:</td>
                                <td>' . date("F d, Y") . '</td>
                            </tr>
                            <tr>
                                <td style="width: 15%; font-weight: bold;">SUBJECT:</td>
                                <td>[Memo Subject]</td>
                            </tr>
                        </table>
                    </div>
                    <hr style="margin-bottom: 20px;">
                    <div>
                        <p>[Introduction paragraph - briefly state the purpose of the memo]</p>
                        <p>[Body paragraph 1 - provide details and supporting information]</p>
                        <p>[Body paragraph 2 - additional information or context]</p>
                        <p>[Closing paragraph - summarize and include any call to action or next steps]</p>
                    </div>
                    <div style="margin-top: 30px;">
                        <p>[Your Signature]</p>
                        <p>' . ($_SESSION['username'] ?? "[Your Name]") . '<br>
                        [Your Position]</p>
                    </div>
                ' . $letterhead_footer,
                
        "request" => $letterhead_header . '
                    <div style="text-align: right;">' . date("F d, Y") . '</div>
                    <div style="margin-top: 20px;">
                        <p>The Department Head<br>
                        School of Computing and Communications<br>
                        Saint Columban College<br>
                        Pagadian City</p>
                    </div>
                    <div style="margin-top: 20px;">
                        <p><strong>Subject: Request for [Subject of Request]</strong></p>
                    </div>
                    <div style="margin-top: 20px;">
                        <p>Dear Sir/Madam,</p>
                        <p>I am writing to request [clearly state what you are requesting] for [state the purpose or reason].</p>
                        <p>The [requested item/service/permission] is necessary because [provide justification and explain importance]. This will [explain benefits or positive outcomes].</p>
                        <p>I would appreciate if this request could be processed by [mention deadline if applicable]. Please let me know if you require any additional information to process this request.</p>
                        <p>Thank you for your time and consideration.</p>
                    </div>
                    <div style="margin-top: 30px;">
                        <p>Respectfully yours,</p>
                        <p>' . ($_SESSION['username'] ?? "[Your Name]") . '<br>
                        [Your Position]<br>
                        [Your Department]<br>
                        [Your Contact Information]</p>
                    </div>
                ' . $letterhead_footer,
                
        "report" => $letterhead_header . '
                    <div style="text-align: center; font-weight: bold; font-size: 18px; margin-bottom: 10px;">[REPORT TITLE]</div>
                    <div style="text-align: center; margin-bottom: 20px;">Prepared by: ' . ($_SESSION['username'] ?? "[Your Name]") . '<br>Date: ' . date("F d, Y") . '</div>
                    
                    <div style="margin-top: 30px;">
                        <h2 style="font-size: 16px; font-weight: bold;">1. Executive Summary</h2>
                        <p>[Brief overview of the entire report - key findings, recommendations, and conclusions]</p>
                        
                        <h2 style="font-size: 16px; font-weight: bold; margin-top: 20px;">2. Introduction</h2>
                        <p>[Background information, purpose of the report, scope, and methodology]</p>
                        
                        <h2 style="font-size: 16px; font-weight: bold; margin-top: 20px;">3. Findings</h2>
                        <p>[Detailed presentation of data, analysis, and findings]</p>
                        <p>[You can organize this section with subheadings if needed]</p>
                        
                        <h2 style="font-size: 16px; font-weight: bold; margin-top: 20px;">4. Discussion</h2>
                        <p>[Interpretation of findings, implications, and significance]</p>
                        
                        <h2 style="font-size: 16px; font-weight: bold; margin-top: 20px;">5. Recommendations</h2>
                        <p>[Specific actionable recommendations based on findings]</p>
                        
                        <h2 style="font-size: 16px; font-weight: bold; margin-top: 20px;">6. Conclusion</h2>
                        <p>[Summary of key points and closing thoughts]</p>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <p>Submitted by:</p>
                        <p>' . ($_SESSION['username'] ?? "[Your Name]") . '<br>
                        [Your Position]<br>
                        [Your Department]<br>
                        [Your Contact Information]</p>
                    </div>
                ' . $letterhead_footer,
                
        "complaint" => $letterhead_header . '
                    <div style="text-align: right;">' . date("F d, Y") . '</div>
                    <div style="margin-top: 20px;">
                        <p>The Department Head<br>
                        School of Computing and Communications<br>
                        Saint Columban College<br>
                        Pagadian City</p>
                    </div>
                    <div style="margin-top: 20px;">
                        <p><strong>Subject: Formal Complaint Regarding [Issue]</strong></p>
                    </div>
                    <div style="margin-top: 20px;">
                        <p>Dear Sir/Madam,</p>
                        <p>I am writing to formally complain about [clearly state the issue or problem] that occurred on [date of incident].</p>
                        <p>The details of the incident are as follows: [provide a factual, chronological account of what happened, including relevant details such as names, locations, and times].</p>
                        <p>This situation has [explain the impact or consequences of the issue]. I have already taken the following steps to resolve this matter: [mention any previous attempts to resolve the issue].</p>
                        <p>I would appreciate if you could [state clearly what you want to happen as a result of your complaint]. I look forward to your prompt attention to this matter and a resolution within [specify a reasonable timeframe if applicable].</p>
                        <p>I can be contacted at [your phone number] or [your email] to discuss this matter further.</p>
                    </div>
                    <div style="margin-top: 30px;">
                        <p>Respectfully yours,</p>
                        <p>' . ($_SESSION['username'] ?? "[Your Name]") . '<br>
                        [Your Position]<br>
                        [Your Department]<br>
                        [Your Contact Information]</p>
                    </div>
                ' . $letterhead_footer,
                
        "general" => $letterhead_header . '
                    <div style="text-align: right;">' . date("F d, Y") . '</div>
                    <div style="margin-top: 20px;">
                        <p>The Department Head<br>
                        School of Computing and Communications<br>
                        Saint Columban College<br>
                        Pagadian City</p>
                    </div>
                    <div style="margin-top: 20px;">
                        <p><strong>Subject: [Document Subject]</strong></p>
                    </div>
                    <div style="margin-top: 20px;">
                        <p>Dear [Recipient\'s Name/Sir/Madam],</p>
                        <p>[Introduction paragraph - briefly state the purpose of the document]</p>
                        <p>[Body paragraph 1 - provide details and supporting information]</p>
                        <p>[Body paragraph 2 - additional information or context]</p>
                        <p>[Closing paragraph - summarize and include any call to action or next steps]</p>
                    </div>
                    <div style="margin-top: 30px;">
                        <p>Respectfully yours,</p>
                        <p>' . ($_SESSION['username'] ?? "[Your Name]") . '<br>
                        [Your Position]<br>
                        [Your Department]<br>
                        [Your Contact Information]</p>
                    </div>
                ' . $letterhead_footer
    ];
    
    return $templates[$docType] ?? $templates["general"];
}

/**
 * Get the title for a specific template type
 * 
 * @param string $templateType The template type
 * @return string The template title
 */
function getTemplateTitle($templateType) {
    $titles = [
        'leave' => 'Leave Request Letter',
        'resignation' => 'Resignation Letter',
        'memo' => 'Memorandum',
        'request' => 'Request Letter',
        'report' => 'Report',
        'complaint' => 'Complaint Letter',
        'general' => 'New Document'
    ];
    
    return $titles[$templateType] ?? 'New Document';
}
?> 