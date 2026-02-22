<?php
/**
 * Document with QR Code
 * 
 * This script generates a document with a QR code automatically inserted in the top right corner.
 */

session_start();
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

// Get document ID and verification code from query parameters
$document_id = isset($_GET['doc']) ? intval($_GET['doc']) : 0;
$verification_code = isset($_GET['code']) ? trim($_GET['code']) : '';

// Validation
if (empty($document_id)) {
    die('Document ID is required');
}

// Get document details from database
$document = null;
$error = '';

try {
    // Get document details - simplified query to avoid JOIN issues
    $stmt = $conn->prepare("SELECT * FROM documents WHERE document_id = ?");
    
    // Check if statement preparation was successful
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $document = $result->fetch_assoc();
    } else {
        $error = "Document not found";
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Create verification URL
$verification_url = "http://" . $_SERVER['HTTP_HOST'] . "/SCCDMS2/simple_verify.php?code=" . $verification_code;

// Get QR code image as base64
$qr_image_url = "qr_display.php?url=" . urlencode($verification_url) . "&size=120";

// Document content
$doc_title = $document ? htmlspecialchars($document['title']) : 'Document';

// For content, check if it exists or use a template
$doc_content = '';
if ($document && isset($document['content'])) {
    $doc_content = htmlspecialchars($document['content']);
} else if ($document && isset($document['description'])) {
    // Use description if content doesn't exist
    $doc_content = htmlspecialchars($document['description']);
} else {
    // Default template
    $doc_content = "This is a sample document content. Replace this with the actual document content.";
}

// Format the date
$date = date('m/d/Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $doc_title; ?> - SCC DMS</title>
    <style>
        @page {
            size: letter;
            margin: 1in;
        }
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            position: relative;
        }
        .container {
            max-width: 8.5in;
            margin: 0 auto;
            padding: 0.5in;
            position: relative;
        }
        .letterhead {
            text-align: center;
            margin-bottom: 1in;
        }
        .letterhead h1 {
            font-size: 18pt;
            margin: 0;
            color: #1a5632;
        }
        .letterhead p {
            margin: 5px 0;
            font-size: 10pt;
        }
        .date {
            margin-bottom: 0.5in;
        }
        .content {
            margin-bottom: 1in;
        }
        .signature {
            margin-top: 1in;
        }
        .footer {
            text-align: center;
            font-size: 9pt;
            color: #666;
            margin-top: 1in;
        }
        .qr-code {
            position: absolute;
            top: 0.5in;
            right: 0.5in;
            width: 120px;
            height: 150px;
            text-align: center;
        }
        .qr-code img {
            width: 100px;
            height: 100px;
        }
        .qr-code .verification {
            font-size: 8pt;
            margin-top: 5px;
        }
        .qr-code .verification-code {
            font-weight: bold;
            font-family: monospace;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #1a5632;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            z-index: 1000;
        }
        .print-button:hover {
            background-color: #0d3d1f;
        }
        @media print {
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">Print Document</button>
    
    <div class="container">
        <!-- QR Code in top right corner -->
        <div class="qr-code">
            <img src="<?php echo $qr_image_url; ?>" alt="QR Code">
            <div class="verification">
                Verification Code:<br>
                <span class="verification-code"><?php echo $verification_code; ?></span>
            </div>
        </div>
        
        <!-- Letterhead -->
        <div class="letterhead">
            <h1>SAINT COLUMBAN COLLEGE</h1>
            <p>Pagadian City, Zamboanga del Sur</p>
            <p>Tel. No. (062) 214-2174 | Email: scc@saintcolumban.edu.ph</p>
            <p>A Catholic Educational Institution</p>
        </div>
        
        <!-- Date -->
        <div class="date">
            <p><?php echo $date; ?></p>
        </div>
        
        <!-- Content -->
        <div class="content">
            <p>Dear Sir/Madam,</p>
            
            <p><?php echo $doc_content; ?></p>
            
            <p>Thank you for your attention to this matter.</p>
        </div>
        
        <!-- Signature -->
        <div class="signature">
            <p>Sincerely,</p>
            <p>&nbsp;</p>
            <p>&nbsp;</p>
            <p>Saint Columban College | Pagadian City, Zamboanga del Sur</p>
            <p>www.saintcolumban.edu.ph</p>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>This document was generated by SCC Document Management System</p>
            <p>Document ID: <?php echo $document_id; ?> | Generated on: <?php echo date('F j, Y, g:i a'); ?></p>
        </div>
    </div>
    
    <script>
        // Auto-print when the page loads (optional)
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>
