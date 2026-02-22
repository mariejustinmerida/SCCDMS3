<?php
// Lightweight Gemini connectivity test (no auth required)
// Usage: http://localhost/SCCDMS2/includes/test_gemini.php

header('Content-Type: text/plain; charset=utf-8');

// Minimal bootstrap
@error_reporting(E_ALL);
@ini_set('display_errors', 1);

$diagnostics = [];

// Load DB config if available
$conn = null;
try {
    require_once __DIR__ . '/config.php';
    if (isset($conn)) {
        $diagnostics[] = 'DB: connected';
    }
} catch (Throwable $e) {
    $diagnostics[] = 'DB: unavailable (' . $e->getMessage() . ')';
}

// Resolve API key: env first, then settings table
$apiKey = getenv('GEMINI_API_KEY');
if (empty($apiKey) && $conn) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name = 'gemini_api_key'");
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $apiKey = $row['setting_value'];
                $diagnostics[] = 'Key: loaded from DB settings (gemini_api_key)';
            }
        }
    } catch (Throwable $e) {
        $diagnostics[] = 'Key lookup error: ' . $e->getMessage();
    }
}
if (empty($apiKey)) {
    echo "Gemini key not found. Set GEMINI_API_KEY env var or save it in AI Settings.\n";
    foreach ($diagnostics as $d) echo $d . "\n";
    exit(1);
}

// Prepare minimal request
$model = 'gemini-1.5-flash';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
$payload = [
    'systemInstruction' => [
        'parts' => [ ['text' => 'You are a simple test. Respond with a JSON object {"ok":true} only.'] ]
    ],
    'contents' => [
        [
            'role' => 'user',
            'parts' => [ ['text' => 'Return {"ok":true}'] ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0,
        'maxOutputTokens' => 64
    ]
];

// Execute cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
$ssl_verify = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
curl_close($ch);

echo "Gemini connectivity test\n";
echo "Endpoint: $url\n";
echo "HTTP Status: $status\n";
echo "cURL Error: " . ($err ?: 'none') . "\n";
echo "SSL Verify Result: $ssl_verify\n";

if (!empty($response)) {
    echo "Raw Response:\n";
    echo $response . "\n";
    echo "\nParsed Snippet:\n";
    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            echo $decoded['candidates'][0]['content']['parts'][0]['text'] . "\n";
        } elseif (isset($decoded['error'])) {
            echo 'Error: ' . json_encode($decoded['error']) . "\n";
        } else {
            echo 'Unexpected format' . "\n";
        }
    } else {
        echo 'Non-JSON response' . "\n";
    }
}

echo "\nDiagnostics:\n";
foreach ($diagnostics as $d) echo $d . "\n";

// Helpful hints for common Windows issues
echo "\nIf you see SSL or status 0 errors, ensure php.ini has:\n";
echo "  curl.cainfo=\"C:\\xampp\\php\\extras\\ssl\\cacert.pem\"\n";
echo "  openssl.cafile=\"C:\\xampp\\php\\extras\\ssl\\cacert.pem\"\n";
?>


