<?php
// MOCKUP: This is a mock version of the AI document analysis script.
// It returns a predefined response to simulate AI-powered search results.

header('Content-Type: application/json');

// Get the request body
$input = json_decode(file_get_contents('php://input'), true);
$query = $input['query'] ?? '';
$documents = $input['documents'] ?? [];

// --- MOCK AI RESPONSE ---
// This section simulates the response you would get from an AI service.
// We'll randomly select a few documents to be "relevant".
$relevant_documents = [];
if (!empty($documents)) {
    $num_relevant = rand(1, min(3, count($documents))); // Pick 1 to 3 relevant docs
    $random_keys = array_rand($documents, $num_relevant);
    
    if (is_array($random_keys)) {
        foreach ($random_keys as $key) {
            $relevant_documents[] = [
                'document_id' => $documents[$key]['document_id'],
                'reason' => 'This document contains keywords related to "' . htmlspecialchars($query) . '".'
            ];
        }
    } else {
        // If only one document is available or returned by array_rand
        $relevant_documents[] = [
            'document_id' => $documents[$random_keys]['document_id'],
            'reason' => 'This document appears to be the most relevant to your query.'
        ];
    }
}

// Create a mock explanation and suggested queries
$mock_explanation = "Based on your query for \"" . htmlspecialchars($query) . "\", I found " . count($relevant_documents) . " relevant document(s) that discuss similar topics.";
$mock_suggested_queries = [
    "Find all reports from last quarter",
    "Summarize documents about budget proposals",
    "Show me all urgent project updates"
];

// --- FINAL RESPONSE ---
$response = [
    'success' => true,
    'explanation' => $mock_explanation,
    'relevantDocuments' => $relevant_documents,
    'suggestedQueries' => $mock_suggested_queries,
];

// Send the JSON response
echo json_encode($response);
