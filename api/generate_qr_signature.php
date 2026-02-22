<?php
/**
 * QR Code Signature Generator
 * 
 * This script generates a QR code for document signatures and stores signature information in the database
 */

session_start();
require_once '../includes/config.php';
require_once '../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Color\Color;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['document_id']) || empty($data['document_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Document ID is required']);
    exit;
}

$document_id = $data['document_id'];
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'] ?? 0;

// Verify this user/office has permission to sign this document
$permission_check = "SELECT dw.workflow_id 
                    FROM document_workflow dw 
                    WHERE dw.document_id = ? 
                    AND dw.office_id = ? 
                    AND dw.status = 'CURRENT'";
$stmt = $conn->prepare($permission_check);
$stmt->bind_param("ii", $document_id, $office_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'You do not have permission to sign this document']);
    exit;
}

// Generate unique signature ID
$signature_id = uniqid('sig_', true);

// Create verification hash for security
$secret_key = 'SCC_DMS_SECRET_KEY_2025'; // In production, use an environment variable
$verification_hash = hash_hmac('sha256', $signature_id, $secret_key);

// Set expiration date (1 year from now)
$expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));

// Store in database
try {
    $stmt = $conn->prepare("INSERT INTO signatures (id, document_id, user_id, office_id, created_at, expires_at, verification_hash) VALUES (?, ?, ?, ?, NOW(), ?, ?)");
    $stmt->bind_param("siisss", $signature_id, $document_id, $user_id, $office_id, $expires_at, $verification_hash);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Failed to store signature information");
    }
    
    // Create QR code with verification URL and hash
    $verification_url = "https://" . $_SERVER['HTTP_HOST'] . "/SCCDMS2/verify.php?sig=" . $signature_id . "&h=" . $verification_hash;
    
    // Create QR code
    $qrCode = QrCode::create($verification_url)
        ->setSize(300)
        ->setMargin(10)
        ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh())
        ->setForegroundColor(new Color(0, 128, 0)); // Green color
    
    $writer = new PngWriter();
    $result = $writer->write($qrCode);
    
    // Save QR code image
    $tempPath = '../temp_qrcodes/' . $signature_id . '.png';
    $result->saveToFile($tempPath);
    
    // Get document information for response
    $doc_query = "SELECT d.title, d.google_doc_id, u.username, o.office_name 
                 FROM documents d 
                 JOIN users u ON d.creator_id = u.user_id 
                 JOIN offices o ON o.office_id = ? 
                 WHERE d.document_id = ?";
    $doc_stmt = $conn->prepare($doc_query);
    $doc_stmt->bind_param("ii", $office_id, $document_id);
    $doc_stmt->execute();
    $doc_result = $doc_stmt->get_result();
    $doc_data = $doc_result->fetch_assoc();
    
    // Return success response with QR code path and document info
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'signature_id' => $signature_id,
        'qr_path' => 'temp_qrcodes/' . $signature_id . '.png',
        'verification_url' => $verification_url,
        'document' => $doc_data
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
