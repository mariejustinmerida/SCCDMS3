<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';

// Only allow authenticated users
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

header('Content-Type: application/json');

// Check if a file was uploaded
if (!isset($_FILES['template']) || $_FILES['template']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

// Get file information
$file = $_FILES['template'];
$fileName = $file['name'];
$fileTmpPath = $file['tmp_name'];
$fileSize = $file['size'];
$fileType = $file['type'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Check file size (limit to 10MB)
if ($fileSize > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds the limit (10MB)']);
    exit;
}

// Check file extension
$allowedExtensions = ['doc', 'docx', 'pdf'];
if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only DOC, DOCX, and PDF files are allowed']);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = 'uploads/templates/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate a unique filename
$newFileName = 'template_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileExtension;
$uploadFilePath = $uploadDir . $newFileName;

// Move the uploaded file to the uploads directory
if (!move_uploaded_file($fileTmpPath, $uploadFilePath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded file']);
    exit;
}

// Process the template based on file type
try {
    if ($fileExtension === 'pdf') {
        $content = processPdfTemplate($uploadFilePath);
    } else {
        $content = processWordTemplate($uploadFilePath);
    }
    
    // Extract title from filename
    $title = pathinfo($fileName, PATHINFO_FILENAME);
    $title = ucwords(str_replace(['_', '-'], ' ', $title));
    
    // Try to determine document type
    $type_id = determineDocumentType($content, $title);
    
    // Return the processed content
    echo json_encode([
        'success' => true,
        'content' => $content,
        'title' => $title,
        'type_id' => $type_id
    ]);
    
} catch (Exception $e) {
    // Delete the uploaded file if processing failed
    if (file_exists($uploadFilePath)) {
        unlink($uploadFilePath);
    }
    
    echo json_encode(['success' => false, 'message' => 'Error processing template: ' . $e->getMessage()]);
}

/**
 * Process a PDF template
 * 
 * @param string $filePath Path to the PDF file
 * @return string HTML content extracted from the PDF
 */
function processPdfTemplate($filePath) {
    // Check if we have the required libraries
    if (!extension_loaded('imagick') && !class_exists('Spatie\PdfToText\Pdf')) {
        // Fallback to a simple message if we can't process PDFs
        return '<div style="font-family: Arial, sans-serif; line-height: 1.6;">
                <p>Your PDF template has been uploaded, but automatic content extraction is not available.</p>
                <p>Please enter your content in this editor using the structure from your template.</p>
                </div>';
    }
    
    // Try to extract text from PDF
    $text = '';
    
    // Method 1: Try using pdftotext if available
    $pdftotext = findExecutable('pdftotext');
    if ($pdftotext) {
        $outputFile = tempnam(sys_get_temp_dir(), 'pdf_');
        exec("$pdftotext -layout \"$filePath\" \"$outputFile\"");
        if (file_exists($outputFile)) {
            $text = file_get_contents($outputFile);
            unlink($outputFile);
        }
    }
    
    // Method 2: Try using Imagick if available
    if (empty($text) && extension_loaded('imagick')) {
        try {
            $imagick = new Imagick();
            $imagick->readImage($filePath);
            $text = $imagick->getImageProperties();
            if (isset($text['text'])) {
                $text = $text['text'];
            } else {
                $text = '';
            }
        } catch (Exception $e) {
            $text = '';
        }
    }
    
    // If we couldn't extract text, return a placeholder
    if (empty($text)) {
        return '<div style="font-family: Arial, sans-serif; line-height: 1.6;">
                <p>Your PDF template has been uploaded, but the content could not be extracted.</p>
                <p>Please enter your content in this editor using the structure from your template.</p>
                </div>';
    }
    
    // Convert plain text to HTML with proper formatting
    $html = '<div style="font-family: Arial, sans-serif; line-height: 1.6;">';
    
    // Split text into paragraphs
    $paragraphs = preg_split('/\n\s*\n/', $text);
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if (!empty($paragraph)) {
            // Check if this looks like a heading
            if (strlen($paragraph) < 100 && (strtoupper($paragraph) === $paragraph || ucwords($paragraph) === $paragraph)) {
                $html .= '<h2 style="font-size: 16px; font-weight: bold; margin-top: 20px;">' . htmlspecialchars($paragraph) . '</h2>';
            } else {
                $html .= '<p>' . nl2br(htmlspecialchars($paragraph)) . '</p>';
            }
        }
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Process a Word template
 * 
 * @param string $filePath Path to the Word file
 * @return string HTML content extracted from the Word document
 */
function processWordTemplate($filePath) {
    // Check if we have the required libraries
    if (!class_exists('ZipArchive')) {
        // Fallback to a simple message if we can't process Word docs
        return '<div style="font-family: Arial, sans-serif; line-height: 1.6;">
                <p>Your Word template has been uploaded, but automatic content extraction is not available.</p>
                <p>Please enter your content in this editor using the structure from your template.</p>
                </div>';
    }
    
    // For DOCX files (Office Open XML)
    if (pathinfo($filePath, PATHINFO_EXTENSION) === 'docx') {
        $content = extractDocxContent($filePath);
    } 
    // For DOC files (older Word format)
    else {
        $content = extractDocContent($filePath);
    }
    
    return $content;
}

/**
 * Extract content from a DOCX file
 * 
 * @param string $filePath Path to the DOCX file
 * @return string HTML content
 */
function extractDocxContent($filePath) {
    $zip = new ZipArchive();
    
    // Open the DOCX file (it's actually a ZIP file)
    if ($zip->open($filePath) !== true) {
        throw new Exception("Could not open the DOCX file");
    }
    
    // Get the document.xml file which contains the main content
    $content = $zip->getFromName('word/document.xml');
    $zip->close();
    
    if (!$content) {
        throw new Exception("Could not extract content from DOCX file");
    }
    
    // Convert the XML content to HTML
    $html = '<div style="font-family: Arial, sans-serif; line-height: 1.6;">';
    
    // Extract paragraphs using regex (a simple approach)
    preg_match_all('/<w:p[^>]*>.*?<\/w:p>/s', $content, $paragraphs);
    
    foreach ($paragraphs[0] as $paragraph) {
        // Extract text from paragraph
        preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $paragraph, $texts);
        
        $paragraphText = '';
        foreach ($texts[1] as $text) {
            $paragraphText .= $text;
        }
        
        $paragraphText = trim($paragraphText);
        
        if (!empty($paragraphText)) {
            // Check if this is a heading (based on style or formatting)
            if (strpos($paragraph, 'w:val="Heading') !== false || 
                strpos($paragraph, 'w:val="heading') !== false ||
                (strlen($paragraphText) < 100 && (strtoupper($paragraphText) === $paragraphText || ucwords($paragraphText) === $paragraphText))) {
                $html .= '<h2 style="font-size: 16px; font-weight: bold; margin-top: 20px;">' . htmlspecialchars($paragraphText) . '</h2>';
            } else {
                $html .= '<p>' . htmlspecialchars($paragraphText) . '</p>';
            }
        }
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Extract content from a DOC file
 * 
 * @param string $filePath Path to the DOC file
 * @return string HTML content
 */
function extractDocContent($filePath) {
    // DOC files are binary and harder to parse
    // We'll use a simple approach to extract text
    
    // Try using antiword if available
    $antiword = findExecutable('antiword');
    if ($antiword) {
        $text = shell_exec("$antiword -w 0 \"$filePath\"");
        if (!empty($text)) {
            return convertTextToHtml($text);
        }
    }
    
    // Try using catdoc if available
    $catdoc = findExecutable('catdoc');
    if ($catdoc) {
        $text = shell_exec("$catdoc \"$filePath\"");
        if (!empty($text)) {
            return convertTextToHtml($text);
        }
    }
    
    // If we couldn't extract text, return a placeholder
    return '<div style="font-family: Arial, sans-serif; line-height: 1.6;">
            <p>Your DOC template has been uploaded, but the content could not be extracted.</p>
            <p>Please enter your content in this editor using the structure from your template.</p>
            </div>';
}

/**
 * Convert plain text to HTML
 * 
 * @param string $text Plain text content
 * @return string HTML content
 */
function convertTextToHtml($text) {
    $html = '<div style="font-family: Arial, sans-serif; line-height: 1.6;">';
    
    // Split text into paragraphs
    $paragraphs = preg_split('/\n\s*\n/', $text);
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if (!empty($paragraph)) {
            // Check if this looks like a heading
            if (strlen($paragraph) < 100 && (strtoupper($paragraph) === $paragraph || ucwords($paragraph) === $paragraph)) {
                $html .= '<h2 style="font-size: 16px; font-weight: bold; margin-top: 20px;">' . htmlspecialchars($paragraph) . '</h2>';
            } else {
                $html .= '<p>' . nl2br(htmlspecialchars($paragraph)) . '</p>';
            }
        }
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Find an executable in the system path
 * 
 * @param string $executable Name of the executable
 * @return string|false Path to the executable or false if not found
 */
function findExecutable($executable) {
    $paths = explode(PATH_SEPARATOR, getenv('PATH'));
    
    // Add common locations
    $paths[] = '/usr/bin';
    $paths[] = '/usr/local/bin';
    $paths[] = 'C:\\Program Files\\';
    $paths[] = 'C:\\Program Files (x86)\\';
    
    foreach ($paths as $path) {
        $file = $path . DIRECTORY_SEPARATOR . $executable;
        if (file_exists($file)) {
            return $file;
        }
        
        // For Windows
        $file = $path . DIRECTORY_SEPARATOR . $executable . '.exe';
        if (file_exists($file)) {
            return $file;
        }
    }
    
    return false;
}

/**
 * Try to determine the document type based on content and title
 * 
 * @param string $content Document content
 * @param string $title Document title
 * @return int|null Document type ID or null if not determined
 */
function determineDocumentType($content, $title) {
    global $conn;
    
    // Get all document types from the database
    $types_query = "SELECT type_id, type_name FROM document_types";
    $types_result = $conn->query($types_query);
    
    if (!$types_result) {
        return null;
    }
    
    $types = [];
    while ($type = $types_result->fetch_assoc()) {
        $types[$type['type_id']] = strtolower($type['type_name']);
    }
    
    // Check title and content for keywords
    $content_lower = strtolower($content);
    $title_lower = strtolower($title);
    
    $keywords = [
        'leave' => ['leave', 'absence', 'vacation', 'time off', 'sick'],
        'memo' => ['memo', 'memorandum', 'announcement'],
        'request' => ['request', 'requisition', 'application'],
        'report' => ['report', 'summary', 'analysis', 'findings'],
        'letter' => ['letter', 'correspondence'],
        'complaint' => ['complaint', 'grievance'],
        'application' => ['application', 'job', 'employment']
    ];
    
    $scores = [];
    
    // Score each document type based on keyword matches
    foreach ($types as $type_id => $type_name) {
        $scores[$type_id] = 0;
        
        // Direct match with type name
        if (strpos($title_lower, $type_name) !== false) {
            $scores[$type_id] += 5;
        }
        
        if (strpos($content_lower, $type_name) !== false) {
            $scores[$type_id] += 3;
        }
        
        // Check for related keywords
        foreach ($keywords as $keyword_type => $keyword_list) {
            if (strpos($type_name, $keyword_type) !== false) {
                foreach ($keyword_list as $keyword) {
                    if (strpos($title_lower, $keyword) !== false) {
                        $scores[$type_id] += 2;
                    }
                    if (strpos($content_lower, $keyword) !== false) {
                        $scores[$type_id] += 1;
                    }
                }
            }
        }
    }
    
    // Get the type with the highest score
    arsort($scores);
    $top_type_id = key($scores);
    
    // Only return if the score is above a threshold
    return $scores[$top_type_id] > 0 ? $top_type_id : null;
}
?> 