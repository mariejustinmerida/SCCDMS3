<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/settings_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['text'])) {
    echo json_encode(['success' => false, 'error' => 'No text provided']);
    exit;
}

$text = trim($input['text']);

if (empty($text)) {
    echo json_encode(['success' => false, 'error' => 'Empty text provided']);
    exit;
}

try {
    $errors = [];
    
    // 1. TEMPLATE PLACEHOLDER DETECTION (Highest Priority)
    $template_errors = detectTemplatePlaceholders($text);
    $errors = array_merge($errors, $template_errors);
    
    // 2. BASIC GRAMMAR AND SPELLING CHECKS
    $basic_errors = performBasicChecks($text);
    $errors = array_merge($errors, $basic_errors);
    
    // 3. AI-ENHANCED ANALYSIS (if API key available)
    $api_key = get_setting('gemini_api_key');
    if (!empty($api_key)) {
        $ai_errors = performAIAnalysis($text, $api_key);
        $errors = array_merge($errors, $ai_errors);
    }
    
    // 4. REMOVE DUPLICATES AND SORT BY SEVERITY
    $errors = removeDuplicateErrors($errors);
    $errors = sortErrorsBySeverity($errors);
    
    // 5. CALCULATE SCORE
    $template_count = count(array_filter($errors, function($e) { return $e['type'] === 'Template'; }));
    $high_severity = count(array_filter($errors, function($e) { return $e['severity'] === 'high'; }));
    $medium_severity = count(array_filter($errors, function($e) { return $e['severity'] === 'medium'; }));
    $low_severity = count(array_filter($errors, function($e) { return $e['severity'] === 'low'; }));
    
    // Heavy penalty for templates and high severity issues
    $score = 100;
    $score -= $template_count * 25; // 25 points per template
    $score -= $high_severity * 15;  // 15 points per high severity
    $score -= $medium_severity * 8; // 8 points per medium severity
    $score -= $low_severity * 3;    // 3 points per low severity
    $score = max(0, $score);
    
    echo json_encode([
        'success' => true,
        'errors' => $errors,
        'total_errors' => count($errors),
        'template_errors' => $template_count,
        'high_severity_errors' => $high_severity,
        'medium_severity_errors' => $medium_severity,
        'low_severity_errors' => $low_severity,
        'overall_score' => $score
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function detectTemplatePlaceholders($text) {
    $errors = [];
    
    // Multiple patterns for template detection
    $patterns = [
        '/\{\{[^}]+\}\}/',           // {{variable}}
        '/\[\[[^\]]+\]\]/',          // [[variable]]
        '/<[^>]+>/',                 // <variable>
        '/\$\{[^}]+\}/',             // ${variable}
        '/%[A-Z_]+%/',               // %VARIABLE%
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $placeholder = $match[0];
                $offset = $match[1];
                
                $errors[] = [
                    'offset' => $offset,
                    'length' => strlen($placeholder),
                    'type' => 'Template',
                    'message' => "Template placeholder '$placeholder' needs to be replaced with actual content",
                    'suggestions' => [
                        'Replace this placeholder with the actual information',
                        'Complete the document before sending',
                        'Use the compose feature to fill in all required fields'
                    ],
                    'severity' => 'high'
                ];
            }
        }
    }
    
    return $errors;
}

function performBasicChecks($text) {
    $errors = [];
    
    // Check for incomplete sentences
    $sentences = preg_split('/[.!?]+/', $text);
    foreach ($sentences as $index => $sentence) {
        $sentence = trim($sentence);
        if (empty($sentence)) continue;
        
        // Very short sentences (less than 3 words)
        if (str_word_count($sentence) < 3 && !preg_match('/^[A-Z][a-z]+$/', $sentence)) {
            $errors[] = [
                'offset' => strpos($text, $sentence),
                'length' => strlen($sentence),
                'type' => 'Style',
                'message' => "Sentence is too short: '$sentence'",
                'suggestions' => ['Expand this into a complete sentence'],
                'severity' => 'medium'
            ];
        }
        
        // Very long sentences (over 50 words)
        if (str_word_count($sentence) > 50) {
            $errors[] = [
                'offset' => strpos($text, $sentence),
                'length' => strlen($sentence),
                'type' => 'Style',
                'message' => 'Sentence is very long (' . str_word_count($sentence) . ' words)',
                'suggestions' => ['Break this into shorter sentences for better readability'],
                'severity' => 'medium'
            ];
        }
    }
    
    // Check for missing punctuation at end
    if (!preg_match('/[.!?]\s*$/', $text)) {
        $errors[] = [
            'offset' => strlen($text) - 1,
            'length' => 1,
            'type' => 'Punctuation',
            'message' => 'Document should end with proper punctuation',
            'suggestions' => ['Add a period, exclamation mark, or question mark at the end'],
            'severity' => 'medium'
        ];
    }
    
    // Check for repeated words
    $words = str_word_count($text, 1);
    $word_counts = array_count_values(array_map('strtolower', $words));
    foreach ($word_counts as $word => $count) {
        if ($count > 5 && strlen($word) > 3) {
            $errors[] = [
                'offset' => 0,
                'length' => strlen($word),
                'type' => 'Style',
                'message' => "Word '$word' appears $count times",
                'suggestions' => ['Consider using synonyms or varying your vocabulary'],
                'severity' => 'low'
            ];
            break; // Only report one repeated word
        }
    }
    
    // Check for common spelling mistakes (basic dictionary)
    $common_mistakes = [
        'recieve' => 'receive',
        'seperate' => 'separate',
        'definately' => 'definitely',
        'occured' => 'occurred',
        'begining' => 'beginning',
        'accomodate' => 'accommodate',
        'embarass' => 'embarrass',
        'maintainance' => 'maintenance',
        'neccessary' => 'necessary',
        'priviledge' => 'privilege'
    ];
    
    foreach ($common_mistakes as $mistake => $correction) {
        if (stripos($text, $mistake) !== false) {
            $errors[] = [
                'offset' => stripos($text, $mistake),
                'length' => strlen($mistake),
                'type' => 'Spelling',
                'message' => "Common spelling mistake: '$mistake'",
                'suggestions' => ["Did you mean '$correction'?"],
                'severity' => 'high'
            ];
        }
    }
    
    return $errors;
}

function performAIAnalysis($text, $api_key) {
    $errors = [];
    
    $prompt = "You are a professional document editor. Analyze this text for grammar, spelling, punctuation, and style issues.

CRITICAL: Look for template placeholders like {{variable_name}} - these are MAJOR issues.

For each issue found, provide this EXACT JSON format:
{
  \"offset\": number,
  \"length\": number,
  \"type\": \"Template|Spelling|Grammar|Punctuation|Style|Clarity\",
  \"message\": \"clear explanation\",
  \"suggestions\": [\"suggestion1\", \"suggestion2\"],
  \"severity\": \"high|medium|low\"
}

Return ONLY a JSON array. If no issues found, return [].

Text to analyze: " . $text;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'response_mime_type' => 'application/json',
            'temperature' => 0.1,
            'topP' => 0.8,
            'topK' => 40,
            'maxOutputTokens' => 2048,
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
        ],
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_status === 200) {
        $data = json_decode($response, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $ai_response = $data['candidates'][0]['content']['parts'][0]['text'];
            $ai_errors = json_decode($ai_response, true);
            
            if (is_array($ai_errors)) {
                $errors = $ai_errors;
            }
        }
    }
    
    return $errors;
}

function removeDuplicateErrors($errors) {
    $unique_errors = [];
    $seen = [];
    
    foreach ($errors as $error) {
        $key = $error['offset'] . '-' . $error['length'] . '-' . $error['type'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique_errors[] = $error;
        }
    }
    
    return $unique_errors;
}

function sortErrorsBySeverity($errors) {
    $severity_order = ['high' => 3, 'medium' => 2, 'low' => 1];
    
    usort($errors, function($a, $b) use ($severity_order) {
        $a_severity = $severity_order[$a['severity']] ?? 0;
        $b_severity = $severity_order[$b['severity']] ?? 0;
        
        if ($a_severity === $b_severity) {
            return $a['offset'] - $b['offset'];
        }
        
        return $b_severity - $a_severity;
    });
    
    return $errors;
}
?>
