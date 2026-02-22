<?php
/**
 * PDF Content Extractor
 * 
 * This script extracts text content from PDF files using the Smalot PDF Parser
 */

// Include config and required files
require_once 'config.php';
require_once '../vendor/autoload.php';

// Set headers to allow CORS
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Get PDF file path from request
$pdf_path = isset($_GET['path']) ? $_GET['path'] : '';

// Check if we have a file path
if (empty($pdf_path)) {
    echo json_encode([
        'success' => false,
        'error' => 'No PDF file path provided'
    ]);
    exit;
}

// Fix the file path if needed
function fixFilePath($path) {
    $path = str_replace("\\", "/", $path);
    if (empty($path)) return '';
    
    // Remove any URL parameters
    $path = strtok($path, '?');
    
    // Handle relative paths
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        // For URLs, we'll need to download the file first
        return $path;
    } else {
        // For local files, make sure we have the correct path
        if (strpos($path, '../') === 0) {
            $path = dirname(dirname(__FILE__)) . '/' . substr($path, 3);
        } else if (strpos($path, '/') === 0) {
            $path = $_SERVER['DOCUMENT_ROOT'] . $path;
        } else {
            $path = dirname(dirname(__FILE__)) . '/' . $path;
        }
    }
    
    return $path;
}

// Function to extract text from PDF
function extractPdfContent($pdf_path) {
    try {
        // Check if file exists
        if (!file_exists($pdf_path)) {
            return [
                'success' => false,
                'error' => 'PDF file not found',
                'path' => $pdf_path
            ];
        }
        
        // Check if file is a PDF
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $pdf_path);
        finfo_close($finfo);
        
        if ($mime !== 'application/pdf') {
            return [
                'success' => false,
                'error' => 'File is not a PDF',
                'mime' => $mime
            ];
        }
        
        // Use Smalot PDF Parser to extract text
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($pdf_path);
        
        // Extract text from all pages
        $text = $pdf->getText();
        
        return [
            'success' => true,
            'content' => $text
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'PDF Parsing Error: ' . $e->getMessage()
        ];
    }
}

// Fix the file path
$fixed_path = fixFilePath($pdf_path);

// Try to extract content
$result = extractPdfContent($fixed_path);

// Return the result as JSON
echo json_encode($result); 