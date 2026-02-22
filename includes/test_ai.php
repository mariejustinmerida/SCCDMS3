<?php
require_once 'config.php';
require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

try {
    $openai = new \OpenAI([
        'api_key' => getenv('OPENAI_API_KEY')
    ]);

    // Simple test completion
    $response = $openai->chat->completions->create([
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'user', 'content' => 'Hello, this is a test message.']
        ]
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'OpenAI API connection successful',
        'response' => $response->choices[0]->message->content
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
