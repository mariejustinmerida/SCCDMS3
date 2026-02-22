<?php
/**
 * Insert QR Code to Google Doc
 * 
 * This script inserts a QR code signature image into a Google Doc at the top right corner
 */

session_start();
require_once '../includes/config.php';
require_once '../vendor/autoload.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['document_id']) || empty($data['document_id']) || 
    !isset($data['google_doc_id']) || empty($data['google_doc_id']) || 
    !isset($data['qr_path']) || empty($data['qr_path'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$document_id = $data['document_id'];
$google_doc_id = $data['google_doc_id'];
$qr_path = $data['qr_path'];
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'] ?? 0;

// Check if the QR code file exists
if (!file_exists("../" . $qr_path)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'QR code file not found']);
    exit;
}

// Get the absolute URL for the QR code
$qr_url = 'http://' . $_SERVER['HTTP_HOST'] . '/SCCDMS2/' . $qr_path;

try {
    // Initialize Google API client
    $client = new Google_Client();
    $client->setAuthConfig('../credentials.json');
    $client->addScope(Google_Service_Docs::DOCUMENTS);
    
    // Check if we have a token in the session
    if (isset($_SESSION['google_access_token'])) {
        $client->setAccessToken($_SESSION['google_access_token']);
    }
    
    // If token is expired, refresh it
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $_SESSION['google_access_token'] = $client->getAccessToken();
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Google authentication required', 'auth_url' => $client->createAuthUrl()]);
            exit;
        }
    }
    
    // Create Google Docs service
    $service = new Google_Service_Docs($client);
    
    // Get the document to find its dimensions
    $document = $service->documents->get($google_doc_id);
    
    // Create a request to insert the QR code image at the top right corner
    $requests = [
        new Google_Service_Docs_Request([
            'insertInlineImage' => [
                'uri' => $qr_url,
                'location' => [
                    'index' => 1 // Insert at the beginning of the document
                ],
                'objectSize' => [
                    'height' => [
                        'magnitude' => 100,
                        'unit' => 'PT'
                    ],
                    'width' => [
                        'magnitude' => 100,
                        'unit' => 'PT'
                    ]
                ]
            ]
        ]),
        // Add a text box with verification instructions below the QR code
        new Google_Service_Docs_Request([
            'insertText' => [
                'location' => [
                    'index' => 2 // Insert after the QR code
                ],
                'text' => "\nScan to verify\n"
            ]
        ])
    ];
    
    // Create a batch update request
    $batchUpdateRequest = new Google_Service_Docs_BatchUpdateDocumentRequest([
        'requests' => $requests
    ]);
    
    // Execute the request
    $response = $service->documents->batchUpdate($google_doc_id, $batchUpdateRequest);
    
    // Update document status in database to indicate QR code has been added
    $update_stmt = $conn->prepare("UPDATE documents SET has_qr_signature = 1 WHERE document_id = ?");
    $update_stmt->bind_param("i", $document_id);
    $update_stmt->execute();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'QR code added to document successfully',
        'document_id' => $document_id,
        'google_doc_id' => $google_doc_id
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
