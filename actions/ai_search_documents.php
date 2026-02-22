<?php
// Natural language document search using Gemini
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Helpers
function extractGoogleDocsContent($doc_id) {
    $export_url = "https://docs.google.com/document/d/{$doc_id}/export?format=txt";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $export_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http === 200 && !empty($response)) { return $response; }
    return '';
}

function extractFileContent($path) {
    if (empty($path) || !file_exists($path)) { return ''; }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    try {
        if ($ext === 'txt') { return file_get_contents($path); }
        if ($ext === 'html' || $ext === 'htm') { return strip_tags(@file_get_contents($path)); }
        if ($ext === 'pdf') {
            if (class_exists('Smalot\\PdfParser\\Parser')) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($path);
                return $pdf->getText();
            }
        }
        if ($ext === 'docx' || $ext === 'doc') {
            // Lightweight best-effort for docx
            $zip = new ZipArchive();
            if ($zip->open($path) === TRUE) {
                if (($index = $zip->locateName('word/document.xml')) !== FALSE) {
                    $data = $zip->getFromIndex($index);
                    $xml = new DOMDocument();
                    $xml->loadXML($data);
                    $zip->close();
                    return strip_tags($xml->saveXML());
                }
                $zip->close();
            }
        }
    } catch (Throwable $e) { /* ignore */ }
    return '';
}

// Read request
$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$query = trim($payload['query'] ?? '');
if ($query === '') {
    echo json_encode(['success' => false, 'message' => 'Query is required']);
    exit;
}

// Gather candidate documents (limit to recent 100 for speed)
$sql = "SELECT d.document_id, d.title, d.file_path, d.google_doc_id, d.created_at, dt.type_name
        FROM documents d
        LEFT JOIN document_types dt ON d.type_id = dt.type_id
        ORDER BY d.updated_at DESC LIMIT 100";
$res = $conn->query($sql);

$docs = [];
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $content = '';
        if (!empty($row['google_doc_id'])) {
            $content = extractGoogleDocsContent($row['google_doc_id']);
        }
        if ($content === '') {
            $content = extractFileContent($row['file_path']);
        }
        // Fallback minimal content
        if ($content === '') {
            $content = $row['title'] . ' ' . ($row['type_name'] ?? '');
        }
        // Truncate for token limits
        if (strlen($content) > 4000) { $content = substr($content, 0, 4000) . '...'; }
        $docs[] = [
            'document_id' => (int)$row['document_id'],
            'title' => $row['title'],
            'created_at' => $row['created_at'],
            'content' => $content
        ];
    }
}

// Simple keyword scoring helper
function keywordScore($text, $keywords) {
    if ($text === '' || empty($keywords)) return 0;
    $score = 0;
    $lower = mb_strtolower($text);
    foreach ($keywords as $kw) {
        if ($kw === '') continue;
        $kwLower = mb_strtolower($kw);
        $count = substr_count($lower, $kwLower);
        if ($count > 0) { $score += 10 + min($count, 5); }
    }
    return $score;
}

// Build Gemini request
$apiKey = getenv('GEMINI_API_KEY');
if (empty($apiKey)) {
    $r = $conn->query("SELECT setting_value FROM settings WHERE setting_name='gemini_api_key'");
    if ($r && $r->num_rows > 0) { $apiKey = $r->fetch_assoc()['setting_value']; }
}
if (empty($apiKey)) {
    echo json_encode(['success' => true, 'results' => [], 'note' => 'No API key; returning empty']);
    exit;
}

// Build the search prompt
$systemPrompt = "You are a semantic search engine. Analyze the user's query and find the most relevant documents. Return ONLY a JSON array with objects containing: document_id (number), title (string), score (0-100), reason (string). No markdown, no extra text.";
$userPrompt = "Query: {$query}\n\nDocuments:\n" . json_encode($docs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// Try different API versions and models for compatibility
$models = [
    ['version' => 'v1', 'model' => 'gemini-1.5-flash'],
    ['version' => 'v1', 'model' => 'gemini-2.0-flash-exp'],
    ['version' => 'v1beta', 'model' => 'gemini-1.5-flash']
];

$aiResults = [];
$lastError = null;

foreach ($models as $modelConfig) {
    $model = $modelConfig['model'];
    $version = $modelConfig['version'];
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $systemPrompt . "\n\n" . $userPrompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => 800,
            'responseMimeType' => 'application/json'
        ]
    ];

    $url = 'https://generativelanguage.googleapis.com/' . $version . '/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $resp = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($curlError) {
        $lastError = 'cURL Error: ' . $curlError;
        continue;
    }
    
    if ($status !== 200) {
        $lastError = "HTTP {$status}: " . substr($resp, 0, 200);
        continue;
    }
    
    $decoded = json_decode($resp, true);
    if (!$decoded || isset($decoded['error'])) {
        $lastError = isset($decoded['error']) ? json_encode($decoded['error']) : 'Invalid JSON response';
        continue;
    }
    
    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (empty($text)) {
        $lastError = 'Empty response from API';
        continue;
    }
    
    // Clean up JSON if wrapped in markdown code blocks
    $text = trim($text);
    if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $text, $matches)) {
        $text = $matches[1];
    } elseif (strpos($text, '```') === 0) {
        $text = preg_replace('/^```[a-zA-Z]*\n|\n```$/', '', $text);
    }
    
    // Try to decode AI results
    $parsedResults = json_decode($text, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($parsedResults)) {
        $aiResults = $parsedResults;
        break; // Success, exit loop
    } else {
        $lastError = 'JSON decode error: ' . json_last_error_msg() . ' - Response: ' . substr($text, 0, 200);
    }
}

// Keyword fallback/merge
$keywords = preg_split('/\s+|,|\./', $query);
$kwCandidates = [];
foreach ($docs as $d) {
    $score = keywordScore(($d['title'] ?? '') . ' ' . ($d['content'] ?? ''), $keywords);
    if ($score > 0) {
        $kwCandidates[] = [
            'document_id' => $d['document_id'],
            'title' => $d['title'],
            'score' => min(100, $score),
            'reason' => 'Keyword match: ' . implode(', ', array_slice($keywords, 0, 5))
        ];
    }
}

// Merge AI and KW, dedupe by document_id
$byId = [];
foreach (array_merge((array)$aiResults, $kwCandidates) as $r) {
    if (!isset($r['document_id'])) continue;
    $id = (int)$r['document_id'];
    if (!isset($byId[$id]) || (($r['score'] ?? 0) > ($byId[$id]['score'] ?? 0))) {
        $byId[$id] = [
            'document_id' => $id,
            'title' => $r['title'] ?? '',
            'score' => (int)($r['score'] ?? 50),
            'reason' => $r['reason'] ?? 'Relevant match'
        ];
    }
}

// Sort by score desc and limit 10
$final = array_values($byId);
usort($final, function($a,$b){ return ($b['score'] ?? 0) <=> ($a['score'] ?? 0); });
$final = array_slice($final, 0, 10);

// Log for debugging (can be removed in production)
if (empty($final) && !empty($docs)) {
    error_log("AI Search: Query '{$query}' returned 0 results from " . count($docs) . " documents. Last error: " . ($lastError ?? 'None'));
}

// Ensure we always return success with results array
echo json_encode([
    'success' => true, 
    'results' => $final,
    'debug' => [
        'total_documents_searched' => count($docs),
        'ai_results_count' => count($aiResults),
        'keyword_results_count' => count($kwCandidates),
        'final_results_count' => count($final)
    ]
], JSON_UNESCAPED_UNICODE);
?>


