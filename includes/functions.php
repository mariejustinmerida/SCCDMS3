<?php
/**
 * Common utility functions for the application
 */

/**
 * Extract content from PDF files
 * @param string $filePath Path to the PDF file
 * @return string Extracted text content
 */
function extractPdfContent($filePath) {
    // Check if the PDF parser is available
    if (class_exists('Smalot\PdfParser\Parser')) {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            return $pdf->getText();
        } catch (Exception $e) {
            error_log("PDF extraction error: " . $e->getMessage());
            return "Error extracting PDF content: " . $e->getMessage();
        }
    } else {
        // Fallback to command line tools if available
        if (function_exists('shell_exec')) {
            $content = shell_exec("pdftotext -q -nopgbrk \"$filePath\" -");
            if ($content) {
                return $content;
            }
        }
        return "PDF content extraction not available.";
    }
}

/**
 * Extract content from DOCX files
 * @param string $filePath Path to the DOCX file
 * @return string Extracted text content
 */
function extractDocxContent($filePath) {
    $content = '';
    
    // Try using ZipArchive to extract content
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($filePath) === TRUE) {
            if (($index = $zip->locateName('word/document.xml')) !== false) {
                $content = $zip->getFromIndex($index);
                $zip->close();
                
                // Convert the XML to plain text
                $content = str_replace('</w:p>', "\n", $content);
                $content = str_replace('</w:r>', " ", $content);
                $content = strip_tags($content);
                return $content;
            }
            $zip->close();
        }
    }
    
    // Fallback to command line tools if available
    if (function_exists('shell_exec')) {
        $content = shell_exec("catdoc \"$filePath\"");
        if ($content) {
            return $content;
        }
    }
    
    return "DOCX content extraction not available.";
}

/**
 * Extract content from TXT files
 * @param string $filePath Path to the TXT file
 * @return string Extracted text content
 */
function extractTxtContent($filePath) {
    return file_get_contents($filePath);
}

/**
 * Extract content from HTML files
 * @param string $filePath Path to the HTML file
 * @return string Extracted text content
 */
function extractHtmlContent($filePath) {
    $html = file_get_contents($filePath);
    return strip_tags($html);
}

/**
 * Get a mock response for testing when API is unavailable
 * @param string $type Type of mock response to generate
 * @return array Mock response data
 */
function getMockResponse($type = 'summary') {
    // Use our test mock responses instead
    $url = 'http://' . $_SERVER['HTTP_HOST'] . '/SCCDMS2/actions/test_mock_responses.php?type=' . urlencode($type);
    
    // Try to get the mock response from our dedicated file
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // If we got a successful response, return it
    if ($httpCode == 200 && !empty($response)) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['success']) && $data['success'] === true) {
            return $data;
        }
    }
    
    // Fallback to hardcoded mock responses
    switch ($type) {
        case 'summary':
            return [
                'success' => true,
                'summary' => 'This is a mock summary generated because the AI service is unavailable. The document appears to contain important information related to business processes, documentation requirements, and organizational procedures. It may include dates, responsibilities, and action items that should be reviewed manually.',
                'keyPoints' => [
                    'Key point 1: Document contains important business information',
                    'Key point 2: Several deadlines and dates are mentioned',
                    'Key point 3: Multiple stakeholders are involved',
                    'Key point 4: There are specific action items requiring attention',
                    'Key point 5: Follow-up procedures are outlined'
                ]
            ];
            
        case 'analysis':
            return [
                'success' => true,
                'classification' => [
                    ['name' => 'Business Document', 'confidence' => 92],
                    ['name' => 'Internal Memo', 'confidence' => 85],
                    ['name' => 'Procedural Document', 'confidence' => 78],
                    ['name' => 'Policy Document', 'confidence' => 65]
                ],
                'entities' => [
                    ['text' => 'Southern Cross College', 'type' => 'ORGANIZATION', 'relevance' => 95],
                    ['text' => 'John Smith', 'type' => 'PERSON', 'relevance' => 88],
                    ['text' => 'October 15, 2023', 'type' => 'DATE', 'relevance' => 90],
                    ['text' => 'Administrative Building', 'type' => 'LOCATION', 'relevance' => 82],
                    ['text' => 'Document Management System', 'type' => 'PRODUCT', 'relevance' => 87]
                ],
                'sentiment' => [
                    'overall' => 0.2,
                    'sentiment_label' => 'Slightly Positive',
                    'tones' => [
                        ['tone' => 'Formal', 'intensity' => 0.9],
                        ['tone' => 'Instructive', 'intensity' => 0.7],
                        ['tone' => 'Informative', 'intensity' => 0.85]
                    ]
                ],
                'keywords' => [
                    'document management', 'procedure', 'approval process', 
                    'deadline', 'requirements', 'submission', 'review'
                ],
                'summary' => 'This document outlines the procedures for document submission and approval within the Southern Cross College document management system.',
                'keyPoints' => [
                    'Establishes document submission workflow',
                    'Defines approval authorities and responsibilities',
                    'Sets timeline expectations for document processing',
                    'Includes quality control measures',
                    'Provides contact information for support'
                ]
            ];
            
        default:
            return [
                'success' => false,
                'message' => 'Unknown mock response type requested'
            ];
    }
} 