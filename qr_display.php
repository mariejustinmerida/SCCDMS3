<?php
// Enhanced QR code display with Google Docs integration

// Get parameters from the query string
$verification_url = isset($_GET['url']) ? $_GET['url'] : '';
$size = isset($_GET['size']) ? intval($_GET['size']) : 200;
$for_gdocs = isset($_GET['gdocs']) ? (bool)$_GET['gdocs'] : false;

// Parse the verification URL to extract document ID and verification code
$doc_id = '';
$verification_code = '';
if (preg_match('/doc=([0-9]+)&code=([0-9]+)/', $verification_url, $matches)) {
    $doc_id = $matches[1];
    $verification_code = $matches[2];
}

// If no URL is provided, use a default one
if (empty($verification_url)) {
    $verification_url = 'https://example.com';
}

// Set content type to PNG image
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Use the QR Server API (no PHP extensions required)
$qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($verification_url);

// Get the QR code image from the API
$qr_image = file_get_contents($qr_url);

// For Google Docs integration, we'll create a special formatted image
if ($for_gdocs && !empty($doc_id) && !empty($verification_code)) {
    // We'd normally use GD library to create a composite image here
    // But since we're avoiding PHP extensions, we'll just use the basic QR code
    // In a production environment, you would:
    // 1. Create a new image with space for text
    // 2. Copy the QR code onto it
    // 3. Add the verification code and document ID as text
    // 4. Output the composite image
    
    // For now, we'll just use the basic QR code
    echo $qr_image;
} else {
    // Output the standard QR code image
    echo $qr_image;
}
