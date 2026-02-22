<?php
// Suppress PHP warnings to avoid displaying them on the page
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to normalize file paths for consistent handling
function fixFilePath($path) {
    // Replace backslashes with forward slashes for web URLs
    $path = str_replace("\\", "/", $path);
    
    // Ensure the path is relative to the web root for browser access
    $base_dir = realpath(dirname(dirname(__FILE__)));
    $web_path = str_replace($base_dir . "/", "", $path);
    
    return $web_path;
}
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../includes/config.php';
require_once '../includes/file_helpers.php';

// Create uploads directory if it doesn't exist
$upload_dir = "../storage/uploads/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Function to call Gemini API using cURL
function callOpenAI($query, $documents) {
    // Backward-compatible name; now uses Gemini
    $api_key = getenv('GEMINI_API_KEY');

    $documentContext = array_map(function($doc) {
        return [
            'name' => $doc['name'],
            'type' => pathinfo($doc['name'], PATHINFO_EXTENSION),
            'content' => $doc['content'] ?? 'No content available',
            'upload_date' => $doc['upload_date']
        ];
    }, $documents);

    $data = [
        'systemInstruction' => [
                'role' => 'system',
            'parts' => [ ['text' => 'You are a document analysis expert. Find relevant documents based on content and metadata. Always respond in JSON.'] ]
            ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [ ['text' => "Query: \"$query\"\n\nDocuments:\n" . json_encode($documentContext, JSON_PRETTY_PRINT)] ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 800
        ]
    ];

    $model = getenv('AI_MODEL') ?: 'gemini-1.5-flash-latest';
    $ch = curl_init('https://generativelanguage.googleapis.com/v1/models/' . urlencode($model) . ':generateContent?key=' . urlencode($api_key));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new Exception('cURL Error: ' . $err);
    }

    $result = json_decode($response, true);
    if (isset($result['error'])) {
        throw new Exception('API Error: ' . (is_array($result['error']) ? json_encode($result['error']) : $result['error']));
    }
    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception('Unexpected API response format.');
    }
    return json_decode($result['candidates'][0]['content']['parts'][0]['text'], true);
}

// Function to extract text from PDF
function extractPdfContent($filePath) {
    $parser = new \Smalot\PdfParser\Parser();
    try {
        $pdf = $parser->parseFile($filePath);
        return $pdf->getText();
    } catch (Exception $e) {
        error_log("PDF extraction error: " . $e->getMessage());
        return '';
    }
}

// Function to extract text from DOCX
function extractDocxContent($filePath) {
    $content = '';
    $zip = new ZipArchive();

    // First try to extract content from standard Word document
    if ($zip->open($filePath) === TRUE) {
        if (($index = $zip->locateName("word/document.xml")) !== FALSE) {
            $data = $zip->getFromIndex($index);
            $xml = new DOMDocument();
            $xml->loadXML($data, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
            $content = strip_tags($xml->saveXML());
        }
        $zip->close();
    }
    
    // If no content was found and this might be our custom-generated DOCX (which is actually HTML)
    if (empty($content)) {
        // Try to read it as HTML
        $html_content = @file_get_contents($filePath);
        if ($html_content !== false) {
            $content = strip_tags($html_content);
        }
        
        // Check if there's a corresponding text file for AI search
        $content_dir = "document_contents/";
        $base_name = basename($filePath, ".docx");
        $search_content_file = $content_dir . 'search_' . $base_name . '.txt';
        
        if (file_exists($search_content_file)) {
            $content = file_get_contents($search_content_file);
        }
    }
    
    return $content;
}

// Function to extract text from TXT files
function extractTxtContent($filePath) {
    try {
        return file_get_contents($filePath);
    } catch (Exception $e) {
        error_log("TXT extraction error: " . $e->getMessage());
        return '';
    }
}

// Function to extract text from HTML files
function extractHtmlContent($filePath) {
    try {
        $html_content = file_get_contents($filePath);
        return strip_tags($html_content);
    } catch (Exception $e) {
        error_log("HTML extraction error: " . $e->getMessage());
        return '';
    }
}

// Function to analyze documents using AI
function analyzeDocuments($query, $documents) {
    try {
        $analysis = callOpenAI($query, $documents);
        return $analysis;
    } catch (Exception $e) {
        error_log("AI analysis error: " . $e->getMessage());
        return null;
    }
}

// Get all documents sorted by status, office and folder
function getDocumentsByStatus() {
    global $conn;
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        return ['error' => 'User not logged in'];
    }
    $user_id = $_SESSION['user_id'];
    $user_office_id = $_SESSION['office_id'];
    
    // Create status-based folders
    $status_folders = [];
    
    // All Documents folder
    $status_folders['all'] = [
        'name' => 'All Documents',
        'icon' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        'color' => 'bg-blue-100 text-blue-800',
        'files' => []
    ];
    
    $status_folders['approved'] = [
        'name' => 'Approved Documents',
        'icon' => 'M5 13l4 4L19 7',
        'color' => 'bg-green-100 text-green-800',
        'files' => []
    ];
    
    $status_folders['pending'] = [
        'name' => 'Pending Documents',
        'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'color' => 'bg-yellow-100 text-yellow-800',
        'files' => []
    ];
    
    $status_folders['rejected'] = [
        'name' => 'Rejected Documents',
        'icon' => 'M6 18L18 6M6 6l12 12',
        'color' => 'bg-red-100 text-red-800',
        'files' => []
    ];
    
    // Get all documents from the database with office-specific filtering
    $document_query = "SELECT DISTINCT d.*, dt.type_name, u.full_name as creator_name, o.office_id, o.office_name, d.is_urgent, 
                d.is_memorandum, d.memorandum_sent_to_all_offices, d.memorandum_total_offices, d.memorandum_read_offices
                FROM documents d
                LEFT JOIN document_types dt ON d.type_id = dt.type_id
                LEFT JOIN users u ON d.creator_id = u.user_id
                LEFT JOIN offices o ON u.office_id = o.office_id
                LEFT JOIN document_workflow dw ON d.document_id = dw.document_id
                LEFT JOIN document_logs dl ON d.document_id = dl.document_id";

    if ($user_office_id != 1) { // 1 is President's Office - can see all documents
        $document_query .= " WHERE (
            -- Documents created by users in this office
            u.office_id = ? 
            OR 
            -- Documents currently in this office's workflow
            dw.office_id = ? AND dw.status = 'CURRENT'
            OR 
            -- Documents that have been routed through this office (past or present)
            dw.office_id = ?
            OR 
            -- Documents where this office has taken action (approved, rejected, held, etc.)
            dl.user_id IN (SELECT user_id FROM users WHERE office_id = ?)
            OR 
            -- Memorandums sent to all offices (if this office is included)
            (d.is_memorandum = 1 AND d.memorandum_sent_to_all_offices = 1)
        )";
    }

    $document_query .= " ORDER BY d.updated_at DESC";
    
    error_log("Document query for user {$user_id} (office {$user_office_id}): " . $document_query);
    
    // Use prepared statement
    $stmt = $conn->prepare($document_query);

    if (!$stmt) {
        error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        return ['status_folders' => $status_folders, 'office_folders' => []];
    }

    if ($user_office_id != 1) {
        $stmt->bind_param("iiii", $user_office_id, $user_office_id, $user_office_id, $user_office_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        error_log("Error executing query: " . $stmt->error);
        return ['status_folders' => $status_folders, 'office_folders' => []];
    }
    
    // Debug: Log the number of results
    $num_rows = $result ? $result->num_rows : 0;
    error_log("Number of documents found: " . $num_rows);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Extract document text content for search
            $content = "";
            
            // If there's a file path, try to extract content
            if (!empty($row['file_path'])) {
                $filePath = $row['file_path'];
                $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                
                // Only extract content for files that exist
                if (file_exists($filePath)) {
                    switch($fileExt) {
                        case 'pdf':
                            if (function_exists('extractPdfContent')) {
                                $content = extractPdfContent($filePath);
                            }
                            break;
                        case 'docx':
                        case 'doc':
                            if (function_exists('extractDocxContent')) {
                                $content = extractDocxContent($filePath);
                            }
                            break;
                        case 'txt':
                            if (function_exists('extractTxtContent')) {
                                $content = extractTxtContent($filePath);
                            } else {
                                // Simple fallback
                                $content = @file_get_contents($filePath);
                            }
                            break;
                        case 'html':
                        case 'htm':
                            if (function_exists('extractHtmlContent')) {
                                $content = extractHtmlContent($filePath);
                            } else {
                                // Simple fallback
                                $html = @file_get_contents($filePath);
                                $content = strip_tags($html);
                            }
                            break;
                    }
                }
            }
            
            // If we couldn't extract content or there's no file, use title and other metadata as content
            if (empty($content)) {
                $content = $row['title'] . ' ' . ($row['description'] ?? '') . ' ' . $row['type_name'];
            }
            
            // Create a file data array for this document
            $file_data = [
                'document_id' => $row['document_id'],
                'name' => basename($row['file_path'] ?? 'Google Doc'),
                'filename' => $row['title'],
                'display_filename' => $row['title'],
                'path' => $row['file_path'],
                'web_path' => !empty($row['file_path']) ? 'storage/uploads/' . basename($row['file_path']) : '',
                'extension' => !empty($row['file_path']) ? pathinfo($row['file_path'], PATHINFO_EXTENSION) : 'GDOCS',
                'size' => 'Unknown',
                'upload_time' => $row['created_at'],
                'content' => $content,
                'office_id' => $row['office_id'],
                'google_doc_id' => $row['google_doc_id'],
                'creator_id' => $row['creator_id'],
                'creator_name' => $row['creator_name'],
                'type_name' => $row['type_name'],
                'is_urgent' => $row['is_urgent']
            ];
            
            // Add to All Documents folder
            $status_folders['all']['files'][] = $file_data;
            
            // Add to status-specific folder
            $status = $row['status'] ?? 'draft';
            
            // Map document status to our folder structure
            if ($status === 'revision_requested') {
                $status = 'revision';
            }
            
            // Check if document is on hold by looking for hold action
            if ($status === 'pending') {
                $hold_query = "SELECT * FROM document_logs 
                            WHERE document_id = ? AND (action = 'hold' OR action = 'resume')
                            ORDER BY created_at DESC LIMIT 1";
                $hold_stmt = $conn->prepare($hold_query);
                
                if ($hold_stmt) {
                    $hold_stmt->bind_param("i", $row['document_id']);
                    $hold_stmt->execute();
                    $hold_result = $hold_stmt->get_result();
                    
                    if ($hold_result && $hold_result->num_rows > 0) {
                        $log_row = $hold_result->fetch_assoc();
                        if ($log_row['action'] === 'hold') {
                            $status = 'hold';
                        }
                    }
                }
            }
            
            if (isset($status_folders[$status])) {
                $status_folders[$status]['files'][] = $file_data;
            }
            
            // Add to office folder based on user role
            if ($user_office_id != 1) { // Not President
                // For regular users, all their relevant documents go into their own office folder.
                if (isset($office_folders[$user_office_id])) {
                    $office_folders[$user_office_id]['files'][] = $file_data;
                }
            } else { // Is President
                // For the President, documents are sorted into folders by their creator's office.
                if (isset($office_folders[$row['office_id']])) {
                    $office_folders[$row['office_id']]['files'][] = $file_data;
                }
            }
        }
        
        return ['status_folders' => $status_folders, 'office_folders' => []];
    }
    
    // If we get here, there were no documents in the database
    return ['status_folders' => $status_folders, 'office_folders' => []];
}

// This function is used to scan the file system for documents
// It's no longer used since we're getting documents from the database
function scanFileSystem($conn, $user_id, $user_office_id) {
    // Create status-based folders
    $status_folders = [];
    $office_folders = [];
    
    // Scan the file system for documents
    $upload_dir = 'storage/uploads';
    if (is_dir($upload_dir)) {
        $files = scandir($upload_dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $file_path = $upload_dir . '/' . $file;
                if(is_file($file_path)) {
                    $file_info = pathinfo($file);
                    $extension = strtolower($file_info['extension']);
                    
                    $upload_time = date("M j, Y", filemtime($file_path));
                    $file_size = filesize($file_path);
                    $size_formatted = $file_size < 1024 ? $file_size . ' B' : 
                                        ($file_size < 1048576 ? round($file_size/1024, 1) . ' KB' : 
                                        round($file_size/1048576, 1) . ' MB');
                    
                    // Extract document content
                    $content = '';
                    if($extension === 'pdf') {
                        $content = extractPdfContent($file_path);
                    } elseif($extension === 'docx') {
                        $content = extractDocxContent($file_path);
                    } elseif($extension === 'txt') {
                        $content = extractTxtContent($file_path);
                    } elseif($extension === 'html') {
                        $content = extractHtmlContent($file_path);
                    }
                    
                    // Create a web-accessible path for the file
                    $web_path = 'storage/uploads/' . $file;
                    
                    // Get document metadata from database if available
                    $document_office_id = $user_office_id; // Default to user's office
                    $document_title = $file_info['filename'];
                    
                    $sql = "SELECT d.document_id, d.title, d.creator_id, u.office_id, o.office_name 
                            FROM documents d 
                            LEFT JOIN users u ON d.creator_id = u.user_id 
                            LEFT JOIN offices o ON u.office_id = o.office_id
                            WHERE d.file_path LIKE ?";
                    $stmt = $conn->prepare($sql);
                    $search_path = '%' . str_replace('\\', '\\\\', $file) . '%';
                    $stmt->bind_param("s", $search_path);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result && $result->num_rows > 0) {
                        $doc_data = $result->fetch_assoc();
                        $document_office_id = $doc_data['office_id'];
                        $document_title = $doc_data['title'];
                    }
                    
                    $file_data = [
                        'name' => $file,
                        'filename' => $file_info['filename'],
                        'display_filename' => $document_title ?? getDisplayFilename($file),
                        'path' => $file_path,
                        'web_path' => $web_path,
                        'extension' => $extension,
                        'size' => $size_formatted,
                        'upload_time' => $upload_time,
                        'content' => $content,
                        'office_id' => $document_office_id
                    ];
                    
                    // Add to All Documents folder
                    $status_folders['all']['files'][] = $file_data;
                    error_log("Added document to 'all' folder: " . $file_data['display_filename']);
                    
                    // Add to status-specific folder
                    $status = $row['status'] ?? 'draft';
                    error_log("Document status from database: " . $status);
                    
                    // Map document status to our folder structure
                    // Remember that the database ENUM only allows: 'draft','pending','approved','rejected','revision'
                    // But we also have a 'hold' folder for documents that are on hold
                    
                    // Check if document is on hold by looking for hold action in document_logs
                    // Documents on hold will have status 'pending' in the database but should go in the 'hold' folder
                    if ($status === 'pending') {
                        $is_on_hold = false;
                        
                        // Check for hold/resume actions in document_logs
                        $hold_query = "SELECT * FROM document_logs 
                                      WHERE document_id = ? AND (action = 'hold' OR action = 'resume')
                                      ORDER BY created_at DESC LIMIT 1";
                        $hold_stmt = $conn->prepare($hold_query);
                        
                        if ($hold_stmt) {
                            $hold_stmt->bind_param("i", $row['document_id']);
                            $hold_stmt->execute();
                            $hold_result = $hold_stmt->get_result();
                            
                            // If the most recent action is 'hold', then the document is on hold
                            if ($hold_result && $hold_result->num_rows > 0) {
                                $log_row = $hold_result->fetch_assoc();
                                if ($log_row['action'] === 'hold') {
                                    $is_on_hold = true;
                                    $status = 'hold';  // Override status for our folder structure
                                    error_log("Document ID " . $row['document_id'] . " is on hold");
                                }
                            }
                        }
                    }
                    
                    // For documents with status 'revision_requested', map to our 'revision' folder
                    if ($status === 'revision_requested') {
                        $status = 'revision';
                    }
                    
                    if (isset($status_folders[$status])) {
                        $status_folders[$status]['files'][] = $file_data;
                        error_log("Added document to '$status' folder: " . $file_data['display_filename']);
                    } else {
                        error_log("Status folder '$status' does not exist for document: " . $file_data['display_filename']);
                    }
                    
                    // Add to office folder if it exists
                    if (isset($office_folders[$document_office_id])) {
                        $office_folders[$document_office_id]['files'][] = $file_data;
                    }
                }
            }
        }
        
        // Sort each folder's files by upload time (newest first)
        foreach($status_folders as $key => $folder) {
            if (!empty($folder['files'])) {
                usort($status_folders[$key]['files'], function($a, $b) {
                    return strtotime($b['upload_time']) - strtotime($a['upload_time']);
                });
            } else {
                // Remove empty folders except All Documents
                if ($key !== 'all') {
                    unset($status_folders[$key]);
                }
            }
        }
        
        // Also sort office folders
        foreach($office_folders as $key => $folder) {
            if (!empty($folder['files'])) {
                usort($office_folders[$key]['files'], function($a, $b) {
                    return strtotime($b['upload_time']) - strtotime($a['upload_time']);
                });
            } else {
                // Remove empty folders except user's own office
                if ($key !== $user_office_id) {
                    unset($office_folders[$key]);
                }
            }
        }
    }
    // Combine status and office folders
    $result = [
        'status_folders' => $status_folders,
        'office_folders' => $office_folders
    ];
    
    return $result;
}

// Get current user's office ID
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT office_id FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $_SESSION['office_id'] = $user_data['office_id'];
    }
}

// Get documents organized by status and office
$folders_data = getDocumentsByStatus();
$status_folders = $folders_data['status_folders'];
$office_folders = $folders_data['office_folders'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Documents - SCC DMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Hide filtered-out options */
        select option[style*="display: none"] {
            display: none !important;
        }
        
        /* Force document container to be visible */
        #documents-container {
            display: block !important;
        }
        
        /* Force folder content to be displayed when open */
        .folder-content.open {
            display: grid !important;
        }
        
        .folder-content {
            display: none;
        }
        
        /* Fix document grid layout */
        .document-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        
        /* Responsive grid adjustments */
        @media (max-width: 768px) {
            .document-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .document-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
        }
        
        /* Document card styles */
        .document-card {
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
            background-color: white;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            min-height: 200px;
        }
        
        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .document-card-header {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            background-color: #f9fafb;
        }
        
        .document-card-body {
            padding: 0.75rem;
            flex: 1;
        }
        
        .document-card-footer {
            padding: 0.5rem;
            border-top: 1px solid #e5e7eb;
            background-color: #f9fafb;
        }
        
        /* Document title styles */
        .document-title {
            word-wrap: break-word;
            word-break: break-word;
            hyphens: auto;
            line-height: 1.4;
            max-height: 3.5em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        
        .document-description {
            word-wrap: break-word;
            word-break: break-word;
            hyphens: auto;
            line-height: 1.3;
            max-height: 2.6em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .document-card.search-highlight {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
        }
        
        /* Folder toggle animations */
        .folder-toggle.open .folder-icon {
            transform: rotate(90deg);
        }
        
        /* File badges */
        .file-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.125rem 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            border-radius: 0.25rem;
            margin-left: 0.5rem;
        }
        
        .file-badge-pdf {
            background-color: #ef4444;
        }
        
        .file-badge-docx {
            background-color: #2563eb;
        }
        
        .file-badge-txt {
            background-color: #10b981;
        }
        
        .file-badge-html {
            background-color: #f59e0b;
        }
        
        .file-badge-other {
            background-color: #6b7280;
        }
        
        /* Button styles */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background-color: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2563eb;
        }
        
        .btn-secondary {
            background-color: #f3f4f6;
            color: #4b5563;
        }
        
        .btn-secondary:hover {
            background-color: #e5e7eb;
        }
        
        /* AI suggestions styling */
        .ai-suggestions {
            background-color: #f0f9ff;
            border: 1px solid #e0f2fe;
            border-left: 4px solid #38bdf8;
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin: 1rem 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .ai-scanning {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
        
        /* Highlight search terms in document list */
        .highlight-match {
            background-color: #fef3c7;
            font-weight: bold;
        }
        
        /* Suggested query styling */
        .suggested-query {
            color: #1d4ed8;
            text-decoration: none;
        }
        
        .suggested-query:hover {
            text-decoration: underline;
        }
        
        /* Folder styles */
        .folder-overview-card {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .folder-overview-card:hover {
            transform: translateY(-2px);
        }
        
        .folder-header {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .folder-header:hover {
            background-color: rgba(0,0,0,0.03);
        }
        .badge {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.375rem;
        }
        .badge-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .badge-approved {
            background-color: #D1FAE5;
            color: #065F46;
        }
        .badge-rejected {
            background-color: #FEE2E2;
            color: #B91C1C;
        }
        .badge-hold, .badge-on_hold {
            background-color: #E0F2FE;
            color: #0369A1;
        }
        .badge-revision, .badge-revision_requested {
            background-color: #F3E8FF;
            color: #7E22CE;
        }
        .document-card.flex-row .document-card-footer {
            width: 200px;
            border-top: none;
            border-left: 1px solid #e5e7eb;
        }
        .document-card.flex-row .document-actions {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        .document-card.flex-row .document-actions > * {
            margin: 0;
        }
        .document-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .document-actions button, 
        .document-actions a {
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
        }
    </style>
    <!-- Urgent script to show folders immediately -->
    <script>
        // Run as soon as possible
        function showAllFolders() {
            // Force document container to be visible
            var container = document.getElementById('documents-container');
            if (container) {
                container.style.display = 'block';
                container.style.visibility = 'visible';
                console.log('Container made visible');
            }
            
            // Show the All Documents folder by default
            var allDocsFolder = document.querySelector('.folder-content[data-folder="all"]');
            if (allDocsFolder) {
                allDocsFolder.style.display = 'grid';
                allDocsFolder.style.visibility = 'visible';
                console.log('All documents folder made visible');
            }
            
            // Add style to force visibility
            var style = document.createElement('style');
            style.textContent = `
                #documents-container { display: block !important; visibility: visible !important; }
                .folder-content[data-folder="all"] { display: grid !important; visibility: visible !important; }
            `;
            document.head.appendChild(style);
        }
        
        // Run on load
        window.addEventListener('load', showAllFolders);
        
        // Also run after a timeout to ensure it happens
        setTimeout(showAllFolders, 500);
        setTimeout(showAllFolders, 1000);
        setTimeout(showAllFolders, 2000);
    </script>
    <script>
        // Function to force open a specific folder
        function forceOpenFolder(folderId) {
            console.log('Opening folder:', folderId);
            
            // Hide all folder contents first
            const allContents = document.querySelectorAll('.folder-content');
            allContents.forEach(content => {
                content.style.display = 'none';
            });
            
            // Reset all folder headers to default state
            const allHeaders = document.querySelectorAll('.folder-header');
            allHeaders.forEach(header => {
                header.classList.remove('active-folder');
                
                // Reset the folder toggle icon
                const icon = header.querySelector('.folder-toggle-icon');
                if (icon) {
                    icon.style.transform = 'rotate(0deg)';
                }
            });
            
            // Show the selected folder content
            const selectedContent = document.querySelector(`.folder-content[data-folder="${folderId}"]`);
            if (selectedContent) {
                selectedContent.style.display = 'grid';
                
                // Add a smooth fade-in effect
                selectedContent.style.opacity = '0';
                setTimeout(() => {
                    selectedContent.style.transition = 'opacity 0.3s ease';
                    selectedContent.style.opacity = '1';
                }, 10);
            }
            
            // Highlight the selected folder header
            const selectedHeader = document.querySelector(`.folder-header[data-folder="${folderId}"]`);
            if (selectedHeader) {
                selectedHeader.classList.add('active-folder');
                
                // Rotate the folder toggle icon
                const icon = selectedHeader.querySelector('.folder-toggle-icon');
                if (icon) {
                    icon.style.transition = 'transform 0.3s ease';
                    icon.style.transform = 'rotate(180deg)';
                }
                
                // Scroll to the folder if needed
                selectedHeader.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // Update URL with the selected folder
            const url = new URL(window.location);
            url.searchParams.set('folder', folderId);
            window.history.replaceState({}, '', url);
            
            // Apply current search filter to the newly opened folder
            applySearchFilter();
        }
    </script>
    <script>
        function toggleUrgentStatus(checkbox, documentId) {
            const isUrgent = checkbox.checked;
            const card = checkbox.closest('.document-card');

            fetch('../actions/set_document_urgency.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    document_id: documentId,
                    is_urgent: isUrgent,
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (isUrgent) {
                        card.classList.add('border-red-500', 'border-2');
                    } else {
                        card.classList.remove('border-red-500', 'border-2');
                    }
                } else {
                    alert('Error: ' + data.error);
                    checkbox.checked = !isUrgent; // Revert the checkbox
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected error occurred.');
                checkbox.checked = !isUrgent; // Revert the checkbox
            });
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="p-4 md:p-6 max-w-7xl mx-auto">
        <?php
        // Display success message if document was uploaded successfully
        if (isset($_GET['success']) && $_GET['success'] == 'upload') {
            echo '<div id="success-notification" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-500">';
            echo '<div class="flex items-center">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>';
            echo '</svg>';
            echo '<div>';
            echo '<h3 class="font-semibold">Document Uploaded Successfully!</h3>';
            echo '<p>Your document has been uploaded and is now available in the system.</p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            // Add script to auto-hide the message after a few seconds
            echo '<script>';
            echo 'document.addEventListener("DOMContentLoaded", function() {';
            echo '  setTimeout(function() {';
            echo '    const notification = document.getElementById("success-notification");';
            echo '    notification.style.opacity = "0";';
            echo '    setTimeout(function() {';
            echo '      notification.style.display = "none";';
            echo '    }, 500);';
            echo '  }, 5000);';
            echo '});';
            echo '</script>';
        }
        
        // Display error message if upload failed
        if (isset($_GET['error'])) {
            $error_message = htmlspecialchars($_GET['error']);
            echo '<div id="error-notification" class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-500">';
            echo '<div class="flex items-center">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>';
            echo '</svg>';
            echo '<div>';
            echo '<h3 class="font-semibold">Upload Failed</h3>';
            echo '<p>' . $error_message . '</p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            // Add script to auto-hide the message after a few seconds
            echo '<script>';
            echo 'document.addEventListener("DOMContentLoaded", function() {';
            echo '  setTimeout(function() {';
            echo '    const notification = document.getElementById("error-notification");';
            echo '    notification.style.opacity = "0";';
            echo '    setTimeout(function() {';
            echo '      notification.style.display = "none";';
            echo '    }, 500);';
            echo '  }, 5000);';
            echo '});';
            echo '</script>';
        }
        ?>
        
        <div class="mb-6 flex flex-col md:flex-row justify-between md:items-center gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Document Management System</h1>
                <div class="flex items-center text-sm text-gray-500 mb-4">
                    <a href="dashboard.php" class="hover:text-gray-700 hover:underline">Dashboard</a>
                    <span class="mx-2">/</span>
                    <span>Documents</span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <!-- New Folder button removed -->
            </div>
        </div>



        <!-- Search and Filter Section -->
        <div class="bg-white rounded-lg shadow-sm mb-6 overflow-hidden">
            <div class="p-4">
                <div class="mb-3 p-3 bg-blue-50 rounded-lg border border-blue-100 text-sm">
                    <div class="flex items-center mb-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium text-blue-800">AI Document Search</span>
                    </div>
                    <p class="text-blue-700 ml-7">Enter keywords or phrases e.g., “reports about quarterly updates” to search document content using AI.</p>
                </div>
                <div class="flex flex-wrap gap-4 items-center search-filter-container">
                    <div class="flex-1 min-w-[300px] search-container">
                        
                        <input type="text" id="searchInput" placeholder="Search documents using AI (e.g., 'find reports about quarterly updates')" 
                               class="w-full px-4 py-2 border rounded-lg search-input focus:ring-2 focus:ring-blue-500 focus:border-transparent"/>
                        <script>
                        (function(){
                            const input = document.getElementById('searchInput');
                            let aiResultsBox = null;
                            function ensureBox(){
                                if (!aiResultsBox){
                                    aiResultsBox = document.createElement('div');
                                    aiResultsBox.id = 'ai-search-results';
                                    aiResultsBox.className = 'mt-2 bg-white border rounded-lg shadow p-2 text-sm';
                                    input.parentElement.appendChild(aiResultsBox);
                                }
                            }
                            function setLoading(isLoading){
                                ensureBox();
                                if (isLoading){
                                    aiResultsBox.innerHTML = '<div class="flex items-center text-gray-500"><span class="animate-pulse inline-block w-2 h-2 bg-blue-600 rounded-full mr-2"></span> Searching...</div>';
                                }
                            }
                            function renderResults(items){
                                ensureBox();
                                if (!items || items.length===0){ aiResultsBox.innerHTML = '<div class="text-gray-500">No AI matches</div>'; return; }
                                aiResultsBox.innerHTML = items.map(r => `<div class="py-1 cursor-pointer hover:bg-gray-50 rounded px-2" data-id="${r.document_id}"><div class="font-medium">${r.title}</div><div class="text-gray-500">Score: ${r.score ?? ''} ${r.reason ? ('- ' + r.reason) : ''}</div></div>`).join('');
                                aiResultsBox.querySelectorAll('[data-id]').forEach(el => {
                                    el.addEventListener('click', () => {
                                        const id = el.getAttribute('data-id');
                                        const card = document.querySelector(`.document-card[data-document-id="${id}"]`);
                                        if (card){
                                            card.scrollIntoView({behavior:'smooth', block:'center'});
                                            card.classList.add('search-highlight');
                                            setTimeout(()=>card.classList.remove('search-highlight'), 2000);
                                        }
                                    });
                                });
                            }
                            let timer = null;
                            input.addEventListener('input', function(){
                                const q = input.value.trim();
                                if (timer) clearTimeout(timer);
                                if (q.length < 2){ 
                                    if (aiResultsBox) aiResultsBox.innerHTML=''; 
                                    return; 
                                }
                                timer = setTimeout(() => {
                                    setLoading(true);
                                    fetch('../actions/ai_search_documents.php', {
                                        method:'POST', 
                                        headers:{'Content-Type':'application/json'}, 
                                        body: JSON.stringify({query: q})
                                    })
                                    .then(r => {
                                        if (!r.ok) {
                                            throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                                        }
                                        return r.json();
                                    })
                                    .then(d => {
                                        console.log('AI Search Response:', d);
                                        if (d.success !== false && d.results && Array.isArray(d.results)) {
                                            if (d.results.length > 0) {
                                                renderResults(d.results);
                                            } else {
                                                aiResultsBox.innerHTML = '<div class="text-gray-500 py-2">No matching documents found. Try different keywords.</div>';
                                            }
                                        } else {
                                            aiResultsBox.innerHTML = '<div class="text-yellow-600 py-2">Search completed but no results. ' + (d.note || 'Try different keywords.') + '</div>';
                                        }
                                    })
                                    .catch(err => {
                                        console.error('AI Search Error:', err);
                                        aiResultsBox.innerHTML = '<div class="text-red-500 py-2">Error: ' + err.message + '. Using keyword fallback...</div>';
                                        // Fallback to basic keyword search
                                        setTimeout(() => {
                                            const allCards = document.querySelectorAll('.document-card');
                                            const searchLower = q.toLowerCase();
                                            let found = [];
                                            allCards.forEach(card => {
                                                const title = (card.querySelector('.document-title')?.textContent || '').toLowerCase();
                                                const content = (card.dataset.content || '').toLowerCase();
                                                if (title.includes(searchLower) || content.includes(searchLower)) {
                                                    const docId = card.dataset.documentId;
                                                    if (docId) {
                                                        found.push({
                                                            document_id: parseInt(docId),
                                                            title: card.querySelector('.document-title')?.textContent || 'Untitled',
                                                            score: 50,
                                                            reason: 'Keyword match'
                                                        });
                                                    }
                                                }
                                            });
                                            if (found.length > 0) {
                                                renderResults(found.slice(0, 10));
                                            } else {
                                                aiResultsBox.innerHTML = '<div class="text-gray-500 py-2">No documents found matching your search.</div>';
                                            }
                                        }, 500);
                                    });
                                }, 350);
                            });
                        })();
                        </script>
                    </div>
                    <div class="flex items-center gap-3">
                        <select id="categoryFilter" class="border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="all">All Types</option>
                        <option value="pdf">PDF Files</option>
                        <option value="docx">Word Documents</option>
                        <option value="txt">Text Files</option>
                            <option value="html">HTML Files</option>
                    </select>
                        
                    </div>
                </div>
            </div>
        </div>

        <!-- Revision notification banner removed -->
        
        <!-- AI Suggestions -->
        <div id="aiSuggestions" class="ai-suggestions mt-4 hidden">
            <div class="flex items-center mb-2">
                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-blue-800">AI Document Search</h3>
                <div class="ai-scanning ml-3 flex items-center">
                    <div class="animate-pulse inline-block w-2 h-2 bg-blue-600 rounded-full mr-1"></div>
                    <div class="animate-pulse inline-block w-2 h-2 bg-blue-600 rounded-full mr-1" style="animation-delay: 200ms;"></div>
                    <div class="animate-pulse inline-block w-2 h-2 bg-blue-600 rounded-full" style="animation-delay: 400ms;"></div>
                </div>
            </div>
            <div id="aiContent" class="pl-10">
                <!-- Content will be dynamically populated here -->
                Analyzing documents...
            </div>
        </div>

        <!-- Document Overview -->
        <div class="mb-6">
            <div class="bg-white rounded-lg shadow-sm p-4 overflow-hidden">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Documents Overview</h2>
                    <div class="text-sm text-gray-500">
                        <?php
                        // Get the document counts from status folders
                        error_log("=== FINAL DOCUMENT COUNTS ===");
                        foreach($status_folders as $folder_key => $folder) {
                            $count = isset($folder['files']) ? count($folder['files']) : 0;
                            error_log("Folder '$folder_key': $count documents");
                        }
                        
                        // Count total documents
                        $total_docs = 0;
                        if (!empty($status_folders)) {
                            foreach($status_folders as $folder) {
                                if (isset($folder['files'])) {
                                    $total_docs += count($folder['files']);
                                }
                            }
                        }
                        echo $total_docs . ' document' . ($total_docs != 1 ? 's' : '') . ' in ' . count($status_folders) . ' folder' . (count($status_folders) != 1 ? 's' : '');
                        ?>
                    </div>
                </div>
                
                <!-- New modern category design -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php
                    // Debug: Output total document count
                    $total_doc_count = 0;
                    foreach($status_folders as $folder_key => $folder) {
                        if (isset($folder['files'])) {
                            $total_doc_count += count($folder['files']);
                        }
                    }
                    
                    // Define category styles
                    $category_styles = [
                        'all' => [
                            'bg' => 'bg-gradient-to-br from-blue-50 to-blue-100',
                            'border' => 'border-blue-200',
                            'icon_bg' => 'bg-blue-100',
                            'icon_color' => 'text-blue-600',
                            'text_color' => 'text-blue-800',
                            'count_bg' => 'bg-blue-200',
                            'count_text' => 'text-blue-800'
                        ],
                        'approved' => [
                            'bg' => 'bg-gradient-to-br from-green-50 to-green-100',
                            'border' => 'border-green-200',
                            'icon_bg' => 'bg-green-100',
                            'icon_color' => 'text-green-600',
                            'text_color' => 'text-green-800',
                            'count_bg' => 'bg-green-200',
                            'count_text' => 'text-green-800'
                        ],
                        'pending' => [
                            'bg' => 'bg-gradient-to-br from-yellow-50 to-yellow-100',
                            'border' => 'border-yellow-200',
                            'icon_bg' => 'bg-yellow-100',
                            'icon_color' => 'text-yellow-600',
                            'text_color' => 'text-yellow-800',
                            'count_bg' => 'bg-yellow-200',
                            'count_text' => 'text-yellow-800'
                        ],
                        'rejected' => [
                            'bg' => 'bg-gradient-to-br from-red-50 to-red-100',
                            'border' => 'border-red-200',
                            'icon_bg' => 'bg-red-100',
                            'icon_color' => 'text-red-600',
                            'text_color' => 'text-red-800',
                            'count_bg' => 'bg-red-200',
                            'count_text' => 'text-red-800'
                        ]
                    ];
                    
                    // Create status folder cards with new design
                    foreach($status_folders as $folder_key => $folder) {
                        // Skip categories we've removed
                        if (in_array($folder_key, ['revision', 'draft', 'hold'])) {
                            continue;
                        }
                        
                        if (!isset($folder['files'])) {
                            $folder['files'] = [];
                        }
                        
                        $count = count($folder['files']);
                        $style = isset($category_styles[$folder_key]) ? $category_styles[$folder_key] : $category_styles['all'];
                        
                        // Icons for each category
                        $icons = [
                            'all' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>',
                            'approved' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                            'pending' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                            'rejected' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>'
                        ];
                        
                        $icon = isset($icons[$folder_key]) ? $icons[$folder_key] : $icons['all'];
                        
                        // Output the card with new modern design
                        echo "<div class=\"relative overflow-hidden rounded-xl border {$style['border']} {$style['bg']} p-6 shadow-sm hover:shadow-md transition-all duration-300 transform hover:-translate-y-1 cursor-pointer\" onclick=\"forceOpenFolder('{$folder_key}')\">";
                        
                        // Icon
                        echo "<div class=\"flex justify-between items-start mb-4\">";
                        echo "<div class=\"{$style['icon_bg']} p-3 rounded-lg\">";
                        echo "<svg xmlns=\"http://www.w3.org/2000/svg\" class=\"h-8 w-8 {$style['icon_color']}\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\">";
                        echo $icon;
                            echo "</svg>";
                        echo "</div>";
                        
                        // Count badge
                        echo "<span class=\"{$style['count_bg']} {$style['count_text']} text-sm font-medium px-3 py-1 rounded-full\">";
                        echo "{$count}";
                        echo "</span>";
                        echo "</div>";
                        
                        // Title
                        echo "<h3 class=\"text-lg font-semibold {$style['text_color']} mb-1\">{$folder['name']}</h3>";
                        
                        // Description
                        echo "<p class=\"text-sm opacity-75 {$style['text_color']}\">";
                        if ($count > 0) {
                            echo "Click to view {$count} document" . ($count != 1 ? "s" : "");
                        } else {
                            echo "No documents in this category";
                        }
                        echo "</p>";
                        
                        // Decorative elements
                        echo "<div class=\"absolute -bottom-4 -right-4 w-24 h-24 rounded-full bg-white opacity-10\"></div>";
                        echo "<div class=\"absolute -top-6 -left-6 w-16 h-16 rounded-full bg-white opacity-10\"></div>";
                        
                        echo "</div>";
                    }
                    
                    // Show empty state if no documents
                    if (empty($status_folders) || count($status_folders) === 0) {
                        echo "<div class=\"col-span-full bg-gray-50 p-8 rounded-lg text-center\">";
                        echo "<svg xmlns=\"http://www.w3.org/2000/svg\" class=\"h-12 w-12 mx-auto mb-4 text-gray-400\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\">";
                        echo "<path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z\"/>";
                        echo "</svg>";
                        echo "<p class=\"text-lg font-medium\">No documents found</p>";
                        echo "<p class=\"mt-1 text-gray-500\">Upload a document to get started</p>";
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Document Search and Filters -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text" id="document-search" placeholder="Search documents..." class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
                <div class="flex items-center space-x-2">
                    <select id="document-sort" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        
                    </select>
                    <button id="grid-view-btn" class="p-2 rounded-md bg-blue-50 text-blue-600 hover:bg-blue-100" title="Grid View">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                    </button>
                    <button id="list-view-btn" class="p-2 rounded-md hover:bg-gray-100 text-gray-500" title="List View">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <button id="test-search-sort" class="px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm" title="Test Search & Sort">
                        TEST
                    </button>
                </div>
            </div>
        </div>

        <!-- Documents by Status -->
        <div id="documents-container" class="bg-white rounded-lg shadow-md p-6" style="display: block !important;">
                <!-- All Documents -->
            <div class="folder mb-4">
                <div class="folder-header bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 px-5 py-4 rounded-t-xl flex items-center justify-between cursor-pointer" data-folder="all" onclick="forceOpenFolder('all')" style="cursor: pointer;">
                        <div class="flex items-center">
                        <div class="bg-blue-100 p-2 rounded-lg mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                        </div>
                        <h3 class="font-medium text-blue-800 text-lg">All Documents</h3>
                    </div>
                    <div class="flex items-center">
                        <span class="bg-blue-200 text-blue-800 text-sm font-medium px-3 py-1 rounded-full mr-2">
                            <?php echo isset($status_folders['all']['files']) ? count($status_folders['all']['files']) : 0; ?> documents
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 folder-toggle-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
                <div class="folder-content document-grid gap-4 p-5 rounded-b-xl border border-gray-200 border-t-0 bg-white" data-folder="all" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
                        <!-- Document cards will be inserted here by PHP -->
                    <?php
                    // Render document cards for All Documents folder
                    if (isset($status_folders['all']['files']) && !empty($status_folders['all']['files'])) {
                        foreach ($status_folders['all']['files'] as $file) {
                            $extension = strtolower($file['extension']);
                            $badge_class = 'file-badge-other';
                            if ($extension === 'pdf') $badge_class = 'file-badge-pdf';
                            else if ($extension === 'docx' || $extension === 'doc') $badge_class = 'file-badge-docx';
                            else if ($extension === 'txt') $badge_class = 'file-badge-txt';
                            else if ($extension === 'html' || $extension === 'htm') $badge_class = 'file-badge-html';
                            
                            $urgent_class = $file['is_urgent'] ? 'border-red-500 border-2' : '';
                            // Check if this is a memorandum
                            $isMemorandum = isset($file['is_memorandum']) && $file['is_memorandum'];
                            $memorandumClass = $isMemorandum ? 'memorandum-card border-blue-300' : '';
                            
                            echo '<div class="document-card shadow-sm hover:shadow-md transition-all duration-200 '.$urgent_class.' '.$memorandumClass.'" data-document-id="'.$file['document_id'].'" data-name="'.$file['name'].'" data-content="'.htmlspecialchars($file['content']).'" data-is-memorandum="'.($isMemorandum ? '1' : '0').'">';
                            echo '<div class="document-card-header p-3 border-b bg-gray-50 flex items-center">';
                            echo '<div class="flex-1">';
                            echo '<h3 class="document-title font-medium text-gray-900 break-words leading-tight" title="'.htmlspecialchars($file['display_filename']).'">'.htmlspecialchars($file['display_filename']).'</h3>';
                            echo '<div class="flex items-center mt-1">';
                            echo '<span class="document-type text-xs text-gray-500 uppercase">'.$file['type_name'].'</span>';
                            echo '<span class="document-size text-xs text-gray-500 ml-2">'.$file['size'].'</span>';
                            echo '</div>';
                            echo '</div>';
                            echo '<div class="document-icon flex items-center justify-center h-10 w-10 rounded-lg">';
                            echo '<span class="'.$badge_class.' text-xs">'.$extension.'</span>';
                            echo '</div>';
                            echo '</div>';
                            echo '<div class="document-card-body p-3 flex-1">';
                            echo '<div class="text-sm text-gray-600 break-words leading-tight document-description" title="'.htmlspecialchars($file['filename']).'">'.htmlspecialchars($file['filename']).'</div>';
                            echo '<div class="mt-2">';
                            echo '<div class="text-xs text-gray-500 flex items-center">';
                            echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />';
                            echo '</svg>';
                            echo '<span class="document-date">'.$file['upload_time'].'</span>';
                            echo '</div>';
                            echo '<div class="text-xs text-gray-500 flex items-center mt-1">';
                            echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />';
                            echo '</svg>';
                            echo '<span class="document-author">'.$file['creator_name'].'</span>';
                            echo '</div>';
                            echo '</div>';
                            echo '</div>';
                            
                            // Add memorandum tracking UI if this is a memorandum
                            if ($isMemorandum) {
                                echo '<div class="memorandum-tracking-section p-3 border-t border-blue-200 bg-blue-50">';
                                echo '<div class="flex items-center justify-between mb-2">';
                                echo '<h4 class="text-sm font-medium text-blue-900">Memorandum Distribution</h4>';
                                echo '<div class="flex items-center space-x-1">';
                                echo '<div class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>';
                                echo '<span class="text-xs text-blue-600">Live tracking</span>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="memorandum-progress-container mb-3">';
                                echo '<div class="flex justify-between items-center mb-1">';
                                echo '<span class="text-xs text-blue-700">Progress</span>';
                                echo '<span class="memorandum-progress-text text-xs text-blue-600">0%</span>';
                                echo '</div>';
                                echo '<div class="w-full bg-blue-200 rounded-full h-1.5">';
                                echo '<div class="memorandum-progress-bar bg-blue-600 h-1.5 rounded-full transition-all duration-300" style="width: 0%"></div>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="memorandum-stats grid grid-cols-3 gap-2 text-center">';
                                echo '<div class="bg-blue-100 p-2 rounded">';
                                echo '<div class="memorandum-total-offices text-lg font-bold text-blue-700">0</div>';
                                echo '<div class="text-xs text-blue-600">Total</div>';
                                echo '</div>';
                                echo '<div class="bg-green-100 p-2 rounded">';
                                echo '<div class="memorandum-read-offices text-lg font-bold text-green-700">0</div>';
                                echo '<div class="text-xs text-green-600">Read</div>';
                                echo '</div>';
                                echo '<div class="bg-purple-100 p-2 rounded">';
                                echo '<div class="memorandum-progress-percent text-lg font-bold text-purple-700">0%</div>';
                                echo '<div class="text-xs text-purple-600">Progress</div>';
                                echo '</div>';
                                echo '</div>';
                                echo '<button onclick="showMemorandumDetails('.$file['document_id'].')" class="w-full mt-2 text-xs text-blue-700 hover:text-blue-900 bg-blue-100 hover:bg-blue-200 px-2 py-1 rounded transition-colors">View Details</button>';
                                echo '</div>';
                            }
                            
                            echo '<div class="document-card-footer p-2 border-t bg-gray-50">';
                            echo '<div class="document-actions flex flex-wrap gap-2">';
                            echo '<a href="view_document.php?id='.$file['document_id'].'" class="document-view-action text-blue-600 hover:text-blue-800 text-xs bg-blue-50 px-2 py-1 rounded flex items-center">';
                            echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />';
                            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
                            echo '</svg>View</a>';
                            if (!empty($file['web_path'])) {
                                echo '<a href="'.$file['web_path'].'" download class="document-download-action text-green-600 hover:text-green-800 text-xs bg-green-50 px-2 py-1 rounded flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />';
                                echo '</svg>DL</a>';
                            }
                            // Add Summary button
                            echo '<button onclick="event.stopPropagation(); summarizeDocument('.$file['document_id'].', \''.addslashes($file['display_filename']).'\')" class="text-amber-600 hover:text-amber-800 text-xs bg-amber-50 px-2 py-1 rounded flex items-center">';
                            echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />';
                            echo '</svg>Summary</button>';
                            // Add Analyze button
                            echo '<button onclick="event.stopPropagation(); analyzeDocument('.$file['document_id'].', \''.addslashes($file['display_filename']).'\')" class="text-indigo-600 hover:text-indigo-800 text-xs bg-indigo-50 px-2 py-1 rounded flex items-center">';
                            echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />';
                            echo '</svg>Analyze</button>';
                            echo '</div>';
                            echo '<div class="mt-2 pl-1">';
                            echo '<label class="flex items-center text-xs text-gray-700">';
                            echo '<input type="checkbox" onchange="toggleUrgentStatus(this, '.$file['document_id'].')" class="form-checkbox h-4 w-4 text-red-600 border-gray-300 rounded focus:ring-red-500" '.($file['is_urgent'] ? 'checked' : '').'>';
                            echo '<span class="ml-2">Urgent</span>';
                            echo '</label>';
                            echo '</div>';
                            echo '</div>';
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="col-span-full p-4 text-center text-gray-500">No documents found in this folder</div>';
                    }
                    ?>
                    </div>
                </div>

                <!-- Approved Documents -->
            <div class="folder mb-4">
                <div class="folder-header bg-gradient-to-r from-green-50 to-green-100 border border-green-200 px-5 py-4 rounded-t-xl flex items-center justify-between cursor-pointer" data-folder="approved" onclick="forceOpenFolder('approved')" style="cursor: pointer;">
                        <div class="flex items-center">
                        <div class="bg-green-100 p-2 rounded-lg mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                            <h3 class="font-medium text-green-800 text-lg">Approved Documents</h3>
                        </div>
                        <div class="flex items-center">
                            <span class="bg-green-200 text-green-800 text-sm font-medium px-3 py-1 rounded-full mr-2">
                                <?php echo isset($status_folders['approved']['files']) ? count($status_folders['approved']['files']) : 0; ?> documents
                        </span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600 folder-toggle-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                    </div>
                    </div>
                    <div class="folder-content document-grid gap-4 p-5 rounded-b-xl border border-gray-200 border-t-0 bg-white" data-folder="approved" style="display: none; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
                        <!-- Approved document cards -->
                        <?php
                        // Render document cards for Approved Documents folder
                        if (isset($status_folders['approved']['files']) && !empty($status_folders['approved']['files'])) {
                            foreach ($status_folders['approved']['files'] as $file) {
                                // Same card rendering code as above
                                // ...
                                $extension = strtolower($file['extension']);
                                $badge_class = 'file-badge-other';
                                if ($extension === 'pdf') $badge_class = 'file-badge-pdf';
                                else if ($extension === 'docx' || $extension === 'doc') $badge_class = 'file-badge-docx';
                                else if ($extension === 'txt') $badge_class = 'file-badge-txt';
                                else if ($extension === 'html' || $extension === 'htm') $badge_class = 'file-badge-html';
                                
                                $urgent_class = $file['is_urgent'] ? 'border-red-500 border-2' : '';
                                echo '<div class="document-card shadow-sm hover:shadow-md transition-all duration-200 '.$urgent_class.'" data-document-id="'.$file['document_id'].'" data-name="'.$file['name'].'" data-content="'.htmlspecialchars($file['content']).'">';
                                // Rest of the card code
                                // ...
                                echo '<div class="document-card-header p-3 border-b bg-gray-50 flex items-center">';
                                echo '<div class="flex-1">';
                                echo '<h3 class="document-title font-medium text-gray-900 break-words leading-tight" title="'.htmlspecialchars($file['display_filename']).'">'.htmlspecialchars($file['display_filename']).'</h3>';
                                echo '<div class="flex items-center mt-1">';
                                echo '<span class="document-type text-xs text-gray-500 uppercase">'.$file['type_name'].'</span>';
                                echo '<span class="document-size text-xs text-gray-500 ml-2">'.$file['size'].'</span>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="document-icon flex items-center justify-center h-10 w-10 rounded-lg">';
                                echo '<span class="'.$badge_class.' text-xs">'.$extension.'</span>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="document-card-body p-3 flex-1">';
                                echo '<div class="text-sm text-gray-600 break-words leading-tight document-description" title="'.htmlspecialchars($file['filename']).'">'.htmlspecialchars($file['filename']).'</div>';
                                echo '<div class="mt-2">';
                                echo '<div class="text-xs text-gray-500 flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />';
                                echo '</svg>';
                                echo '<span class="document-date">'.$file['upload_time'].'</span>';
                                echo '</div>';
                                echo '<div class="text-xs text-gray-500 flex items-center mt-1">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />';
                                echo '</svg>';
                                echo '<span class="document-author">'.$file['creator_name'].'</span>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="document-card-footer p-2 border-t bg-gray-50">';
                                echo '<div class="document-actions flex flex-wrap gap-2">';
                                echo '<a href="view_document.php?id='.$file['document_id'].'" class="document-view-action text-blue-600 hover:text-blue-800 text-xs bg-blue-50 px-2 py-1 rounded flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
                                echo '</svg>View</a>';
                                if (!empty($file['web_path'])) {
                                    echo '<a href="'.$file['web_path'].'" download class="document-download-action text-green-600 hover:text-green-800 text-xs bg-green-50 px-2 py-1 rounded flex items-center">';
                                    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />';
                                    echo '</svg>DL</a>';
                                }
                                // Add Summary button
                                echo '<button onclick="event.stopPropagation(); summarizeDocument('.$file['document_id'].', \''.addslashes($file['display_filename']).'\')" class="text-amber-600 hover:text-amber-800 text-xs bg-amber-50 px-2 py-1 rounded flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />';
                                echo '</svg>Summary</button>';
                                // Add Analyze button
                                echo '<button onclick="event.stopPropagation(); analyzeDocument('.$file['document_id'].', \''.addslashes($file['display_filename']).'\')" class="text-indigo-600 hover:text-indigo-800 text-xs bg-indigo-50 px-2 py-1 rounded flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />';
                                echo '</svg>Analyze</button>';
                                echo '</div>';
                                echo '<div class="mt-2 pl-1">';
                                echo '<label class="flex items-center text-xs text-gray-700">';
                                echo '<input type="checkbox" onchange="toggleUrgentStatus(this, '.$file['document_id'].')" class="form-checkbox h-4 w-4 text-red-600 border-gray-300 rounded focus:ring-red-500" '.($file['is_urgent'] ? 'checked' : '').'>';
                                echo '<span class="ml-2">Urgent</span>';
                                echo '</label>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="col-span-full p-4 text-center text-gray-500">No approved documents found</div>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Rejected Documents -->
                <div class="folder mb-4">
                    <div class="folder-header bg-gradient-to-r from-red-50 to-red-100 border border-red-200 px-5 py-4 rounded-t-xl flex items-center justify-between cursor-pointer" data-folder="rejected" onclick="forceOpenFolder('rejected')" style="cursor: pointer;">
                        <div class="flex items-center">
                            <div class="bg-red-100 p-2 rounded-lg mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                                <h3 class="font-medium text-red-800 text-lg">Rejected Documents</h3>
                            </div>
                            <div class="flex items-center">
                                <span class="bg-red-200 text-red-800 text-sm font-medium px-3 py-1 rounded-full mr-2">
                                    <?php echo isset($status_folders['rejected']['files']) ? count($status_folders['rejected']['files']) : 0; ?> documents
                        </span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600 folder-toggle-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                    </div>
                    </div>
                    <div class="folder-content document-grid gap-4 p-5 rounded-b-xl border border-gray-200 border-t-0 bg-white" data-folder="rejected" style="display: none; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
                        <!-- Rejected document cards -->
                        <?php
                        if (isset($status_folders['rejected']['files']) && !empty($status_folders['rejected']['files'])) {
                            foreach ($status_folders['rejected']['files'] as $file) {
                                $extension = strtolower($file['extension']);
                                $badge_class = 'file-badge-other';
                                if ($extension === 'pdf') $badge_class = 'file-badge-pdf';
                                else if ($extension === 'docx' || $extension === 'doc') $badge_class = 'file-badge-docx';
                                else if ($extension === 'txt') $badge_class = 'file-badge-txt';
                                else if ($extension === 'html' || $extension === 'htm') $badge_class = 'file-badge-html';

                                $urgent_class = $file['is_urgent'] ? 'border-red-500 border-2' : '';
                                echo '<div class="document-card shadow-sm hover:shadow-md transition-all duration-200 '.$urgent_class.'" data-document-id="'.$file['document_id'].'" data-name="'.$file['name'].'" data-content="'.htmlspecialchars($file['content']).'">';
                                echo '<div class="document-card-header p-3 border-b bg-gray-50 flex items-center">';
                                echo '<div class="flex-1">';
                                echo '<h3 class="document-title font-medium text-gray-900 break-words leading-tight" title="'.htmlspecialchars($file['display_filename']).'">'.htmlspecialchars($file['display_filename']).'</h3>';
                                echo '<div class="flex items-center mt-1">';
                                echo '<span class="document-type text-xs text-gray-500 uppercase">'.$file['type_name'].'</span>';
                                echo '<span class="document-size text-xs text-gray-500 ml-2">'.$file['size'].'</span>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="document-icon flex items-center justify-center h-10 w-10 rounded-lg">';
                                echo '<span class="'.$badge_class.' text-xs">'.$extension.'</span>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="document-card-body p-3 flex-1">';
                                echo '<div class="text-sm text-gray-600 break-words leading-tight document-description" title="'.htmlspecialchars($file['filename']).'">'.htmlspecialchars($file['filename']).'</div>';
                                echo '<div class="mt-2">';
                                echo '<div class="text-xs text-gray-500 flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />';
                                echo '</svg>';
                                echo '<span class="document-date">'.$file['upload_time'].'</span>';
                                echo '</div>';
                                echo '<div class="text-xs text-gray-500 flex items-center mt-1">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />';
                                echo '</svg>';
                                echo '<span class="document-author">'.$file['creator_name'].'</span>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="document-card-footer p-2 border-t bg-gray-50">';
                                echo '<div class="document-actions flex flex-wrap gap-2">';
                                echo '<a href="view_document.php?id='.$file['document_id'].'" class="document-view-action text-blue-600 hover:text-blue-800 text-xs bg-blue-50 px-2 py-1 rounded flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
                                echo '</svg>View</a>';
                                if (!empty($file['web_path'])) {
                                    echo '<a href="'.$file['web_path'].'" download class="document-download-action text-green-600 hover:text-green-800 text-xs bg-green-50 px-2 py-1 rounded flex items-center">';
                                    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />';
                                    echo '</svg>DL</a>';
                                }
                                echo '<button onclick="event.stopPropagation(); summarizeDocument('.$file['document_id'].', \''.addslashes($file['display_filename']).'\')" class="text-amber-600 hover:text-amber-800 text-xs bg-amber-50 px-2 py-1 rounded flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />';
                                echo '</svg>Summary</button>';
                                echo '<button onclick="event.stopPropagation(); analyzeDocument('.$file['document_id'].', \''.addslashes($file['display_filename']).'\')" class="text-indigo-600 hover:text-indigo-800 text-xs bg-indigo-50 px-2 py-1 rounded flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />';
                                echo '</svg>Analyze</button>';
                                echo '</div>';
                                echo '<div class="mt-2 pl-1">';
                                echo '<label class="flex items-center text-xs text-gray-700">';
                                echo '<input type="checkbox" onchange="toggleUrgentStatus(this, '.$file['document_id'].')" class="form-checkbox h-4 w-4 text-red-600 border-gray-300 rounded focus:ring-red-500" '.($file['is_urgent'] ? 'checked' : '').'>';
                                echo '<span class="ml-2">Urgent</span>';
                                echo '</label>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="col-span-full p-4 text-center text-gray-500">No rejected documents found</div>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Pending Documents -->
                <div class="folder mb-4">
                    <div class="folder-header bg-gradient-to-r from-yellow-50 to-yellow-100 border border-yellow-200 px-5 py-4 rounded-t-xl flex items-center justify-between cursor-pointer" data-folder="pending" onclick="forceOpenFolder('pending')" style="cursor: pointer;">
                        <div class="flex items-center">
                            <div class="bg-yellow-100 p-2 rounded-lg mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                                <h3 class="font-medium text-yellow-800 text-lg">Pending Documents</h3>
                            </div>
                            <div class="flex items-center">
                                <span class="bg-yellow-200 text-yellow-800 text-sm font-medium px-3 py-1 rounded-full mr-2">
                                    <?php echo isset($status_folders['pending']['files']) ? count($status_folders['pending']['files']) : 0; ?> documents
                        </span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-600 folder-toggle-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                    </div>
                    </div>
                    <div class="folder-content document-grid gap-4 p-5 rounded-b-xl border border-gray-200 border-t-0 bg-white" data-folder="pending" style="display: none; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
                        <!-- Pending document cards -->
                        <?php
                        if (isset($status_folders['pending']['files']) && !empty($status_folders['pending']['files'])) {
                            foreach ($status_folders['pending']['files'] as $file) {
                                $extension = strtolower($file['extension']);
                                $badge_class = 'file-badge-other';
                                if ($extension === 'pdf') $badge_class = 'file-badge-pdf';
                                else if ($extension === 'docx' || $extension === 'doc') $badge_class = 'file-badge-docx';
                                else if ($extension === 'txt') $badge_class = 'file-badge-txt';
                                else if ($extension === 'html' || $extension === 'htm') $badge_class = 'file-badge-html';

                                $urgent_class = $file['is_urgent'] ? 'border-red-500 border-2' : '';
                                echo '<div class="document-card shadow-sm hover:shadow-md transition-all duration-200 '.$urgent_class.'" data-document-id="'.$file['document_id'].'" data-name="'.$file['name'].'" data-content="'.htmlspecialchars($file['content']).'">';
                                echo '<div class="document-card-header p-3 border-b bg-gray-50 flex items-center">';
                                echo '<div class="flex-1">';
                                echo '<h3 class="document-title font-medium text-gray-900 break-words leading-tight" title="'.htmlspecialchars($file['display_filename']).'">'.htmlspecialchars($file['display_filename']).'</h3>';
                                echo '<div class="flex items-center mt-1">';
                                echo '<span class="document-type text-xs text-gray-500 uppercase">'.$file['type_name'].'</span>';
                                echo '<span class="document-size text-xs text-gray-500 ml-2">'.$file['size'].'</span>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="document-icon flex items-center justify-center h-10 w-10 rounded-lg">';
                                echo '<span class="'.$badge_class.' text-xs">'.$extension.'</span>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="document-card-body p-3 flex-1">';
                                echo '<div class="text-sm text-gray-600 break-words leading-tight document-description" title="'.htmlspecialchars($file['filename']).'">'.htmlspecialchars($file['filename']).'</div>';
                                echo '<div class="mt-2">';
                                echo '<div class="text-xs text-gray-500 flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />';
                                echo '</svg>';
                                echo '<span class="document-date">'.$file['upload_time'].'</span>';
                                echo '</div>';
                                echo '<div class="text-xs text-gray-500 flex items-center mt-1">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />';
                                echo '</svg>';
                                echo '<span class="document-author">'.$file['creator_name'].'</span>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="document-card-footer p-2 border-t bg-gray-50">';
                                echo '<div class="document-actions flex flex-wrap gap-2">';
                                echo '<a href="view_document.php?id='.$file['document_id'].'" class="document-view-action text-blue-600 hover:text-blue-800 text-xs bg-blue-50 px-2 py-1 rounded flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
                                echo '</svg>View</a>';
                                if (!empty($file['web_path'])) {
                                    echo '<a href="'.$file['web_path'].'" download class="document-download-action text-green-600 hover:text-green-800 text-xs bg-green-50 px-2 py-1 rounded flex items-center">';
                                    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />';
                                    echo '</svg>DL</a>';
                                }
                                echo '<button onclick="event.stopPropagation(); summarizeDocument('.$file['document_id'].', \''.addslashes($file['display_filename']).'\')" class="text-amber-600 hover:text-amber-800 text-xs bg-amber-50 px-2 py-1 rounded flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />';
                                echo '</svg>Summary</button>';
                                echo '<button onclick="event.stopPropagation(); analyzeDocument('.$file['document_id'].', \''.addslashes($file['display_filename']).'\')" class="text-indigo-600 hover:text-indigo-800 text-xs bg-indigo-50 px-2 py-1 rounded flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />';
                                echo '</svg>Analyze</button>';
                                echo '</div>';
                                echo '<div class="mt-2 pl-1">';
                                echo '<label class="flex items-center text-xs text-gray-700">';
                                echo '<input type="checkbox" onchange="toggleUrgentStatus(this, '.$file['document_id'].')" class="form-checkbox h-4 w-4 text-red-600 border-gray-300 rounded focus:ring-red-500" '.($file['is_urgent'] ? 'checked' : '').'>';
                                echo '<span class="ml-2">Urgent</span>';
                                echo '</label>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="col-span-full p-4 text-center text-gray-500">No pending documents found</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents by Office -->
        <div class="mt-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4 pt-4 border-t"></h2>
            <?php foreach ($office_folders as $folder_key => $folder): ?>
                <?php if (!empty($folder['files']) || $folder['office_id'] == ($_SESSION['office_id'] ?? 0)): ?>
                <div class="folder mb-4">
                    <div class="folder-header bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 px-5 py-4 rounded-t-xl flex items-center justify-between cursor-pointer" data-folder="office_<?php echo $folder_key; ?>" onclick="forceOpenFolder('office_<?php echo $folder_key; ?>')" style="cursor: pointer;">
                        <div class="flex items-center">
                            <div class="bg-gray-200 p-2 rounded-lg mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m-1 4h1m-1 4h1m4-12h1m-1 4h1m-1 4h1m-1 4h1m4-12h1m-1 4h1m-1 4h1m-1 4h1" />
                                </svg>
                            </div>
                            <h3 class="font-medium text-gray-800 text-lg"><?php echo htmlspecialchars($folder['name']); ?></h3>
                        </div>
                        <div class="flex items-center">
                            <span class="bg-gray-200 text-gray-800 text-sm font-medium px-3 py-1 rounded-full mr-2">
                                <?php echo count($folder['files']); ?> documents
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 folder-toggle-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                    <div class="folder-content document-grid gap-4 p-5 rounded-b-xl border border-gray-200 border-t-0 bg-white" data-folder="office_<?php echo $folder_key; ?>" style="display: none; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
                        <?php if (!empty($folder['files'])): ?>
                            <?php foreach ($folder['files'] as $file): ?>
                                <?php
                                $extension = strtolower($file['extension']);
                                $badge_class = 'file-badge-other';
                                if ($extension === 'pdf') $badge_class = 'file-badge-pdf';
                                else if ($extension === 'docx' || $extension === 'doc') $badge_class = 'file-badge-docx';
                                else if ($extension === 'txt') $badge_class = 'file-badge-txt';
                                else if ($extension === 'html' || $extension === 'htm') $badge_class = 'file-badge-html';

                                $urgent_class = $file['is_urgent'] ? 'border-red-500 border-2' : '';
                                echo '<div class="document-card shadow-sm hover:shadow-md transition-all duration-200 '.$urgent_class.'" data-document-id="'.$file['document_id'].'" data-name="'.$file['name'].'" data-content="'.htmlspecialchars($file['content']).'">';
                                echo '<div class="document-card-header p-3 border-b bg-gray-50 flex items-center">';
                                echo '<div class="flex-1">';
                                echo '<h3 class="document-title font-medium text-gray-900 break-words leading-tight" title="'.htmlspecialchars($file['display_filename']).'">'.htmlspecialchars($file['display_filename']).'</h3>';
                                echo '<div class="flex items-center mt-1">';
                                echo '<span class="document-type text-xs text-gray-500 uppercase">'.$file['type_name'].'</span>';
                                echo '<span class="document-size text-xs text-gray-500 ml-2">'.$file['size'].'</span>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="document-icon flex items-center justify-center h-10 w-10 rounded-lg">';
                                echo '<span class="'.$badge_class.' text-xs">'.$extension.'</span>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="document-card-body p-3 flex-1">';
                                echo '<div class="text-sm text-gray-600 break-words leading-tight document-description" title="'.htmlspecialchars($file['filename']).'">'.htmlspecialchars($file['filename']).'</div>';
                                echo '<div class="mt-2">';
                                echo '<div class="text-xs text-gray-500 flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />';
                                echo '</svg>';
                                echo '<span class="document-date">'.$file['upload_time'].'</span>';
                                echo '</div>';
                                echo '<div class="text-xs text-gray-500 flex items-center mt-1">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />';
                                echo '</svg>';
                                echo '<span class="document-author">'.$file['creator_name'].'</span>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="document-card-footer p-2 border-t bg-gray-50">';
                                echo '<div class="document-actions flex flex-wrap gap-2">';
                                echo '<a href="view_document.php?id='.$file['document_id'].'" class="document-view-action text-blue-600 hover:text-blue-800 text-xs bg-blue-50 px-2 py-1 rounded flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
                                echo '</svg>View</a>';
                                if (!empty($file['web_path'])) {
                                    echo '<a href="'.$file['web_path'].'" download class="document-download-action text-green-600 hover:text-green-800 text-xs bg-green-50 px-2 py-1 rounded flex items-center">';
                                    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />';
                                    echo '</svg>DL</a>';
                                }
                                echo '<button onclick="event.stopPropagation(); summarizeDocument('.$file['document_id'].', \''.addslashes($file['display_filename']).'\')" class="text-amber-600 hover:text-amber-800 text-xs bg-amber-50 px-2 py-1 rounded flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />';
                                echo '</svg>Summary</button>';
                                echo '<button onclick="event.stopPropagation(); analyzeDocument('.$file['document_id'].', \''.addslashes($file['display_filename']).'\')" class="text-indigo-600 hover:text-indigo-800 text-xs bg-indigo-50 px-2 py-1 rounded flex items-center">';
                                echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
                                echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />';
                                echo '</svg>Analyze</button>';
                                echo '</div>';
                                echo '<div class="mt-2 pl-1">';
                                echo '<label class="flex items-center text-xs text-gray-700">';
                                echo '<input type="checkbox" onchange="toggleUrgentStatus(this, '.$file['document_id'].')" class="form-checkbox h-4 w-4 text-red-600 border-gray-300 rounded focus:ring-red-500" '.($file['is_urgent'] ? 'checked' : '').'>';
                                echo '<span class="ml-2">Urgent</span>';
                                echo '</label>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                                ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-span-full p-4 text-center text-gray-500">No documents found in this folder</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Create Folder Modal -->
    <div id="createFolderModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000] hidden">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Create New Folder</h3>
                <button id="closeFolderModal" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
                <form id="createFolderForm">
                    <div class="mb-4">
                        <label for="folderName" class="block text-sm font-medium text-gray-700 mb-1">Folder Name</label>
                    <input type="text" id="folderName" name="folderName" class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter folder name" required>
                    </div>
                    <div class="flex justify-end gap-3">
                    <button type="button" id="cancelFolderBtn" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Folder</button>
                    </div>
                </form>
            </div>
        </div>

    <!-- Transfer Document Modal -->
    <div id="transferDocumentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000] hidden">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Transfer Document</h3>
                <button id="closeTransferModal" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="transferDocumentForm">
                <input type="hidden" id="documentPath" name="documentPath">
                <div class="mb-4">
                    <label for="targetOffice" class="block text-sm font-medium text-gray-700 mb-1">Select Target Office</label>
                    <select id="targetOffice" name="targetOffice" class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <?php
                        // Display all offices except the current user's office
                        foreach($offices as $office_id => $office_name) {
                            if ($office_id != ($_SESSION['office_id'] ?? 0)) {
                                echo "<option value=\"{$office_id}\">{$office_name}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="transferReason" class="block text-sm font-medium text-gray-700 mb-1">Reason for Transfer (Optional)</label>
                    <textarea id="transferReason" name="transferReason" class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" rows="3" placeholder="Enter reason for transfer"></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" id="cancelTransferBtn" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Transfer Document</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notification -->
    <div id="notification" class="fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg transform translate-y-10 opacity-0 transition-all duration-300 z-[11000]">
        <span id="notificationText"></span>
    </div>

    <!-- Summary Modal -->
    <div id="summaryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999] hidden">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] flex flex-col">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="text-xl font-semibold text-gray-800" id="summaryTitle">Document Summary</h3>
                <button id="closeSummaryModal" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6 overflow-y-auto flex-grow">
                <!-- Loading spinner -->
                <div id="summaryLoading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-amber-500"></div>
                    <p class="mt-4 text-gray-600">Generating document summary...</p>
                    <p class="text-sm text-gray-500 mt-2">This may take a moment for larger documents</p>
                </div>
                
                <!-- Error message -->
                <div id="summaryError" class="hidden"></div>
                
                <!-- Summary content -->
                <div id="summaryContent" class="hidden">
                    <div class="mb-8 bg-blue-50 rounded-lg p-5 border border-blue-100">
                        <h4 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Key Points
                        </h4>
                        <ul id="keyPoints" class="list-none space-y-3 text-gray-700"></ul>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-5 border border-gray-200">
                        <h4 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                            </svg>
                            Summary
                        </h4>
                        <div id="summaryText" class="prose max-w-none text-gray-700 leading-relaxed"></div>
                    </div>
                </div>
                        </div>
            <div class="p-4 border-t flex justify-between items-center">
                <div>
                    <button id="copySummaryBtn" class="px-4 py-2 bg-amber-100 text-amber-700 rounded hover:bg-amber-200 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                    </svg>
                        Copy to Clipboard
                </button>
                </div>
                <button id="closeSummaryBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Close</button>
            </div>
        </div>
    </div>

    <!-- AI Analysis Modal -->
    <div id="aiAnalysisModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999] hidden">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] flex flex-col">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="text-xl font-semibold text-gray-800" id="aiAnalysisTitle">Document Analysis</h3>
                <button id="closeAiAnalysisModal" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6 overflow-y-auto flex-grow">
                <!-- Loading spinner -->
                <div id="aiAnalysisLoading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-indigo-500"></div>
                    <p class="mt-4 text-gray-600">Analyzing document content...</p>
                    <p class="text-sm text-gray-500 mt-2">This may take a moment for larger documents</p>
                </div>
                
                <!-- Error message -->
                <div id="aiAnalysisError" class="hidden">
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p id="aiAnalysisErrorText" class="text-red-700">Error analyzing document</p>
                        </div>
                        </div>
                    </div>
                    
                <!-- Analysis content -->
                <div id="aiAnalysisContent" class="hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Document Classification -->
                        <div class="bg-gradient-to-br from-indigo-50 to-white p-5 rounded-lg border border-indigo-100 shadow-sm">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                                Document Classification
                            </h4>
                            <div id="classificationResults" class="flex flex-wrap gap-2"></div>
                    </div>
                    
                        <!-- Named Entities -->
                        <div class="bg-gradient-to-br from-blue-50 to-white p-5 rounded-lg border border-blue-100 shadow-sm">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Named Entities
                            </h4>
                            <div id="entitiesResults" class="max-h-64 overflow-y-auto"></div>
                    </div>
                    
                        <!-- Keywords -->
                        <div class="bg-gradient-to-br from-green-50 to-white p-5 rounded-lg border border-green-100 shadow-sm">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                                </svg>
                                Keywords
                            </h4>
                            <div id="keywordsResults" class="flex flex-wrap gap-2"></div>
                        </div>
                        
                        <!-- Sentiment Analysis -->
                        <div class="bg-gradient-to-br from-purple-50 to-white p-5 rounded-lg border border-purple-100 shadow-sm">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                                Sentiment Analysis
                            </h4>
                            <div id="sentimentResults"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-4 border-t flex justify-between items-center">
                <div>
                    <button id="copyAiAnalysisBtn" class="px-4 py-2 bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                    </svg>
                        Copy to Clipboard
                </button>
                </div>
                <button id="closeAiAnalysisBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Close</button>
            </div>
        </div>
    </div>

    <!-- Delete Document Confirmation Modal -->
    <div id="deleteConfirmationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-[10000]">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-auto overflow-hidden">
            <div class="px-6 py-4 bg-red-50 border-b border-red-100">
                <h3 class="text-lg font-medium text-red-800">Confirm Delete</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-700 mb-4">Are you sure you want to delete this document? This action cannot be undone.</p>
                <div class="text-right space-x-3">
                    <button class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg" onclick="document.getElementById('deleteConfirmationModal').classList.add('hidden')">Cancel</button>
                    <button id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Transfer Modal -->
    <div id="transferDocumentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-[10000]">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-auto overflow-hidden">
            <div class="px-6 py-4 bg-blue-50 border-b border-blue-100">
                <h3 class="text-lg font-medium text-blue-800">Transfer Document</h3>
            </div>
            <div class="p-6">
                <form id="transferForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="transferOffice">Select Office</label>
                        <select id="transferOffice" name="office_id" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2">
                            <?php
                            // Get all offices
                            $offices_query = "SELECT * FROM offices ORDER BY office_name";
                            $offices_result = $conn->query($offices_query);
                            
                            if ($offices_result && $offices_result->num_rows > 0) {
                                while ($office = $offices_result->fetch_assoc()) {
                                    echo "<option value=\"{$office['office_id']}\">{$office['office_name']}</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="transferReason">Reason for Transfer (Optional)</label>
                        <textarea id="transferReason" name="reason" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2" rows="3"></textarea>
                    </div>
                    <div class="text-right space-x-3">
                        <button type="button" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg" onclick="document.getElementById('transferDocumentModal').classList.add('hidden')">Cancel</button>
                        <button type="submit" id="confirmTransferBtn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">Transfer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Document Card Template -->
    <template id="document-card-template">
        <div class="document-card bg-white rounded-lg shadow border border-gray-200 overflow-hidden" style="display: flex; flex-direction: column;">
            <div class="document-card-header p-3 border-b bg-gray-50 flex items-center">
                <div class="flex-1">
                    <h3 class="document-title font-medium text-gray-900 truncate"></h3>
                    <div class="flex items-center mt-1">
                        <span class="document-type text-xs text-gray-500 uppercase"></span>
                        <span class="document-size text-xs text-gray-500 ml-2"></span>
                    </div>
                </div>
                <div class="document-icon flex items-center justify-center h-10 w-10 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>
            <div class="document-card-body p-3 flex-1">
                <div class="text-sm text-gray-600 truncate document-description"></div>
                <div class="mt-2">
                    <div class="text-xs text-gray-500 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="document-date"></span>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center mt-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span class="document-author"></span>
                    </div>
                    <div class="document-status-container mt-2 flex items-center">
                        <span class="document-status px-2 py-0.5 text-xs rounded-full"></span>
                    </div>
                </div>
            </div>
            <div class="document-card-footer p-3 border-t bg-gray-50 flex justify-between items-center">
                <div class="document-actions flex space-x-2">
                    <a href="#" class="document-view-action text-blue-600 hover:text-blue-800 text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        View
                    </a>
                    <a href="#" class="document-download-action text-green-600 hover:text-green-800 text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Download
                    </a>
                </div>
                <div class="document-menu relative">
                    <button class="text-gray-600 hover:text-gray-900 focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <script>
        // Add debugging output to console
        console.log('Document management page loaded');

        // Document summarization function
        function documentSummarize(documentId, fileName) {
            // Show the modal
            const summaryModal = document.getElementById('summaryModal');
            const summaryTitle = document.getElementById('summaryTitle');
            const summaryLoading = document.getElementById('summaryLoading');
            const summaryContent = document.getElementById('summaryContent');
            const summaryError = document.getElementById('summaryError');
            const keyPoints = document.getElementById('keyPoints');
            const summaryText = document.getElementById('summaryText');
            
            console.log('Summarizing document ID:', documentId);
            
            summaryModal.classList.remove('hidden');
            summaryTitle.textContent = 'Summarizing: ' + fileName;
            summaryLoading.classList.remove('hidden');
            summaryContent.classList.add('hidden');
            summaryError.classList.add('hidden');
            
            // Make API call to summarize the document
            fetch('../actions/summarize_document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    documentId: documentId,
                    fileName: fileName
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(response => {
                // Check if response is already JSON or needs parsing
                let data = response;
                
                // If response is a string that looks like JSON, parse it
                if (typeof response === 'string' && (response.trim().startsWith('{') || response.trim().startsWith('['))) {
                    try {
                        data = JSON.parse(response);
                    } catch (e) {
                        console.error('Failed to parse JSON string:', e);
                    }
                }
                
                // If summary is a JSON string, parse it
                if (data.summary && typeof data.summary === 'string' && data.summary.trim().startsWith('{')) {
                    try {
                        const parsedSummary = JSON.parse(data.summary);
                        data.summary = parsedSummary.summary || parsedSummary;
                        if (parsedSummary.keyPoints && !data.keyPoints) {
                            data.keyPoints = parsedSummary.keyPoints;
                        }
                    } catch (e) {
                        // If parsing fails, it's probably just text
                        console.log('Summary is plain text, not JSON');
                    }
                }
                
                summaryLoading.classList.add('hidden');
                
                if (data.success !== false) {
                    // Display the summary
                    summaryContent.classList.remove('hidden');
                    
                    // Clear previous content
                    keyPoints.innerHTML = '';
                    
                    // Add key points with better formatting
                    let keyPointsData = data.keyPoints;
                    
                    // Handle different keyPoints formats
                    if (!keyPointsData && data.summary && typeof data.summary === 'object') {
                        keyPointsData = data.summary.keyPoints;
                    }
                    
                    if (keyPointsData && Array.isArray(keyPointsData) && keyPointsData.length > 0) {
                        // Limit to first 5 key points
                        const maxKeyPoints = 5;
                        keyPointsData.slice(0, maxKeyPoints).forEach(point => {
                            const li = document.createElement('li');
                            li.className = 'mb-3 text-gray-700 leading-relaxed pl-2';
                            let pointText = typeof point === 'string' ? point : (point.point || point.text || JSON.stringify(point));
                            // Limit each key point to 200 characters
                            if (pointText.length > 200) {
                                pointText = pointText.substring(0, 200).trim() + '...';
                            }
                            li.innerHTML = `<span class="font-semibold text-blue-600 mr-2">▸</span> <span class="text-gray-800">${pointText}</span>`;
                            keyPoints.appendChild(li);
                        });
                    } else if (keyPointsData && typeof keyPointsData === 'string') {
                        // If keyPoints is a string, try to parse as JSON first
                        let points = [];
                        try {
                            const parsed = JSON.parse(keyPointsData);
                            if (Array.isArray(parsed)) {
                                points = parsed;
                            }
                        } catch (e) {
                            // If not JSON, split by delimiters
                            points = keyPointsData.split(/\n|•|[-*]|\d+\./).filter(p => p.trim().length > 0);
                        }
                        
                        // Limit to first 5 key points
                        const maxKeyPoints = 5;
                        points.slice(0, maxKeyPoints).forEach(point => {
                            const li = document.createElement('li');
                            li.className = 'mb-3 text-gray-700 leading-relaxed pl-2';
                            let pointText = point.trim();
                            // Limit each key point to 200 characters
                            if (pointText.length > 200) {
                                pointText = pointText.substring(0, 200).trim() + '...';
                            }
                            li.innerHTML = `<span class="font-semibold text-blue-600 mr-2">▸</span> <span class="text-gray-800">${pointText}</span>`;
                            keyPoints.appendChild(li);
                        });
                    } else {
                        const li = document.createElement('li');
                        li.className = 'text-gray-500 italic';
                        li.textContent = 'No key points extracted';
                        keyPoints.appendChild(li);
                    }
                    
                    // Add summary text with better formatting
                    let summaryData = data.summary;
                    
                    // Extract summary from nested objects
                    if (summaryData && typeof summaryData === 'object' && summaryData.summary) {
                        summaryData = summaryData.summary;
                    } else if (summaryData && typeof summaryData === 'object') {
                        summaryData = JSON.stringify(summaryData);
                    }
                    
                    if (summaryData && typeof summaryData === 'string') {
                        // Remove JSON-like artifacts more aggressively
                        summaryData = summaryData
                            // Remove entire JSON wrapper if present
                            .replace(/^\s*\{[^{]*"summary"\s*:\s*"([^"]*)"[^}]*\}\s*$/s, '$1')
                            // Remove just the summary key wrapper
                            .replace(/^\s*"summary"\s*:\s*"/, '')
                            .replace(/"\s*,\s*"keyPoints"/, '')
                            .replace(/"\s*\}\s*$/, '')
                            // Remove escaped quotes and newlines
                            .replace(/\\"/g, '"')
                            .replace(/\\n/g, '\n')
                            // Remove surrounding quotes
                            .replace(/^["']+|["']+$/g, '')
                            .trim();
                        
                        // If it still looks like JSON, try to extract the actual summary text
                        if (summaryData.includes('{"summary"') || summaryData.includes('"summary":')) {
                            try {
                                const jsonMatch = summaryData.match(/"summary"\s*:\s*"([^"]*(?:\\.[^"]*)*)"/);
                                if (jsonMatch && jsonMatch[1]) {
                                    summaryData = jsonMatch[1].replace(/\\"/g, '"').replace(/\\n/g, '\n');
                                }
                            } catch (e) {
                                console.log('JSON extraction failed, using as-is');
                            }
                        }
                        
                        // Remove any remaining JSON artifacts
                        summaryData = summaryData
                            .replace(/^\{[^}]*\}/, '')
                            .replace(/^\[[^\]]*\]/, '')
                            .trim();
                        
                        // Format the summary with proper paragraphs
                        const paragraphs = summaryData
                            .split(/\n\n+/)
                            .filter(p => {
                                const trimmed = p.trim();
                                return trimmed.length > 0 && 
                                       !trimmed.match(/^[,\[\]\{\}"\s]*$/) &&
                                       !trimmed.startsWith('"keyPoints"') &&
                                       !trimmed.includes('","keyPoints"');
                            });
                        
                        if (paragraphs.length > 0) {
                            // Limit summary to first 2 paragraphs or 600 characters, whichever is shorter
                            const maxLength = 600;
                            const maxParagraphs = 2;
                            let truncatedParagraphs = paragraphs.slice(0, maxParagraphs);
                            let combinedText = truncatedParagraphs.join(' ').trim();
                            
                            if (combinedText.length > maxLength) {
                                combinedText = combinedText.substring(0, maxLength);
                                // Find last complete sentence or word
                                const lastPeriod = combinedText.lastIndexOf('.');
                                const lastSpace = combinedText.lastIndexOf(' ');
                                const cutPoint = lastPeriod > maxLength * 0.8 ? lastPeriod + 1 : (lastSpace > maxLength * 0.8 ? lastSpace : maxLength);
                                combinedText = combinedText.substring(0, cutPoint).trim() + '...';
                            }
                            
                            const formattedSummary = combinedText
                                .split(/\n\n+/)
                                .filter(p => p.trim().length > 0)
                                .map(p => {
                                    const cleanP = p.trim()
                                        .replace(/^["']+|["']+$/g, '')
                                        .replace(/\\"/g, '"')
                                        .replace(/\\n/g, '\n');
                                    return `<p class="mb-4 text-gray-700 leading-relaxed text-base">${cleanP.replace(/\n/g, '<br>')}</p>`;
                                })
                                .join('');
                            summaryText.innerHTML = formattedSummary;
                        } else if (summaryData.length > 20) {
                            // If no paragraphs but we have substantial content, limit to 600 characters
                            let truncatedSummary = summaryData;
                            if (truncatedSummary.length > 600) {
                                truncatedSummary = truncatedSummary.substring(0, 600);
                                const lastPeriod = truncatedSummary.lastIndexOf('.');
                                const lastSpace = truncatedSummary.lastIndexOf(' ');
                                const cutPoint = lastPeriod > 450 ? lastPeriod + 1 : (lastSpace > 450 ? lastSpace : 600);
                                truncatedSummary = truncatedSummary.substring(0, cutPoint).trim() + '...';
                            }
                            summaryText.innerHTML = '<p class="text-gray-700 leading-relaxed text-base">' + truncatedSummary.replace(/\n/g, '<br>') + '</p>';
                        } else {
                            summaryText.innerHTML = '<p class="text-gray-500 italic">No summary available. The document may be too short or contain no readable content.</p>';
                        }
                    } else {
                        summaryText.innerHTML = '<p class="text-gray-500 italic">No summary available. The document may be too short or contain no readable content.</p>';
                    }
                } else {
                    // Show error
                    summaryError.classList.remove('hidden');
                    console.error('Error:', data.message || 'Unknown error');
                    document.getElementById('summaryError').innerHTML = `
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-red-700">${data.message || 'Error generating summary. Please try again later.'}</p>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(error => {
                summaryLoading.classList.add('hidden');
                summaryError.classList.remove('hidden');
                console.error('Error:', error);
                document.getElementById('summaryError').innerHTML = `
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-red-700">Error: ${error.message || 'Failed to generate summary. Please try again later.'}</p>
                        </div>
                    </div>
                `;
            });
        }
        
        // Close summary modal
        document.getElementById('closeSummaryModal').addEventListener('click', function() {
            document.getElementById('summaryModal').classList.add('hidden');
        });
        
        document.getElementById('closeSummaryBtn').addEventListener('click', function() {
            document.getElementById('summaryModal').classList.add('hidden');
        });
        
        // Copy summary to clipboard
        document.getElementById('copySummaryBtn').addEventListener('click', function() {
            const keyPoints = document.getElementById('keyPoints').innerText;
            const summaryText = document.getElementById('summaryText').innerText;
            const fullSummary = `KEY POINTS:\n${keyPoints}\n\nSUMMARY:\n${summaryText}`;
            
            navigator.clipboard.writeText(fullSummary).then(function() {
                showNotification('Summary copied to clipboard!');
            }, function(err) {
                console.error('Could not copy text: ', err);
                showNotification('Failed to copy to clipboard', 'error');
            });
        });

        // Document AI analysis function
        function documentAnalyze(documentId, fileName) {
            // Show the modal
            const aiAnalysisModal = document.getElementById('aiAnalysisModal');
            const aiAnalysisTitle = document.getElementById('aiAnalysisTitle');
            const aiAnalysisLoading = document.getElementById('aiAnalysisLoading');
            const aiAnalysisContent = document.getElementById('aiAnalysisContent');
            const aiAnalysisError = document.getElementById('aiAnalysisError');
            
            console.log('Analyzing document ID:', documentId);
            
            aiAnalysisModal.classList.remove('hidden');
            aiAnalysisTitle.textContent = 'Analyzing: ' + fileName;
            aiAnalysisLoading.classList.remove('hidden');
            aiAnalysisContent.classList.add('hidden');
            aiAnalysisError.classList.add('hidden');
            
            // Call the API
            fetch('../actions/ai_document_processor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    documentId: documentId,
                    fileName: fileName,
                    operation: 'all'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(response => {
                // Handle potential double-encoded JSON
                let data = response;
                
                // If response is a string that looks like JSON, parse it
                if (typeof response === 'string' && (response.trim().startsWith('{') || response.trim().startsWith('['))) {
                    try {
                        data = JSON.parse(response);
                    } catch (e) {
                        console.error('Failed to parse JSON string:', e);
                    }
                }
                
                // If classification is a JSON string, parse it
                if (data.classification && typeof data.classification === 'string' && data.classification.trim().startsWith('[')) {
                    try {
                        data.classification = JSON.parse(data.classification);
                    } catch (e) {
                        console.log('Classification is not JSON string');
                    }
                }
                
                aiAnalysisLoading.classList.add('hidden');
                
                if (data.success !== false) {
                    // Display the analysis results
                    aiAnalysisContent.classList.remove('hidden');
                    
                    // Display classification results
                    const classificationResults = document.getElementById('classificationResults');
                    classificationResults.innerHTML = '';
                    
                    // Normalize bad API outputs like "[object Object]" into an empty array first
                    if (typeof data.classification === 'string' && /\[object Object\]/i.test(data.classification)) {
                        data.classification = [];
                    }
                    
                    // Handle classification data - could be array or object
                    let classifications = [];
                    if (data.classification) {
                        // If it's already an array
                        if (Array.isArray(data.classification)) {
                            classifications = data.classification.filter(c => c !== null && c !== undefined);
                        } 
                        // If it's an object (could be key-value pairs or nested structure)
                        else if (typeof data.classification === 'object') {
                            Object.keys(data.classification).forEach(key => {
                                const value = data.classification[key];
                                
                                // Skip null/undefined values
                                if (value === null || value === undefined) return;
                                
                                // If value is an object with classification properties
                                if (typeof value === 'object' && !Array.isArray(value)) {
                                    // Check if it has nested structure (e.g., {name: {...}, confidence: {...}})
                                    if (value.name && typeof value.name === 'object') {
                                        // Nested object, extract the actual values
                                        const actualName = value.name.name || value.name.category || value.name.label || value.name.text || key;
                                        const actualConf = value.confidence || value.score || value.percentage || '';
                                        classifications.push({ 
                                            name: typeof actualName === 'string' ? actualName : String(actualName),
                                            confidence: actualConf 
                                        });
                                    } else if (value.name || value.category || value.label) {
                                        // Has expected properties
                                        classifications.push(value);
                                    } else {
                                        // Convert object properties
                                        const conf = typeof value === 'number' ? value : (value.confidence || value.score || value.percentage || '');
                                        classifications.push({ 
                                            name: key, 
                                            confidence: conf 
                                        });
                                    }
                                } 
                                // If value is an array (nested arrays)
                                else if (Array.isArray(value)) {
                                    value.forEach((item, idx) => {
                                        if (typeof item === 'object' && item !== null) {
                                            classifications.push(item);
                                        } else if (typeof item === 'string') {
                                            classifications.push({ name: item, confidence: '' });
                                        }
                                    });
                                }
                                // If value is a number
                                else if (typeof value === 'number') {
                                    classifications.push({ name: key, confidence: value });
                                } 
                                // If value is a string
                                else if (typeof value === 'string') {
                                    classifications.push({ name: key, confidence: value });
                                }
                            });
                        } 
                        // If it's a JSON string
                        else if (typeof data.classification === 'string') {
                            try {
                                const parsed = JSON.parse(data.classification);
                                if (Array.isArray(parsed)) {
                                    classifications = parsed.filter(c => c !== null && c !== undefined);
                                } else if (typeof parsed === 'object') {
                                    Object.keys(parsed).forEach(key => {
                                        const val = parsed[key];
                                        if (typeof val === 'object' && val !== null) {
                                            classifications.push(val);
                                        } else {
                                            classifications.push({ name: key, confidence: val });
                                        }
                                    });
                                }
                            } catch (e) {
                                // Not JSON, treat as single category
                                classifications = [{ name: data.classification, confidence: '' }];
                            }
                        }
                        
                        // Final cleanup: ensure all classifications have valid structure
                        classifications = classifications.map(cat => {
                            if (typeof cat === 'string') {
                                return { name: cat, confidence: '' };
                            } else if (cat && typeof cat === 'object') {
                                return {
                                    name: cat.name || cat.category || cat.label || cat.type || cat.text || 'Unknown',
                                    confidence: cat.confidence || cat.score || cat.percentage || ''
                                };
                            }
                            return { name: 'Unknown', confidence: '' };
                        }).filter(cat => cat.name !== 'Unknown' || cat.confidence !== '');
                    }
                    
                    if (classifications.length > 0) {
                        const readableSummary = [];
                        classifications.forEach(category => {
                            const categoryBadge = document.createElement('div');
                            categoryBadge.className = 'px-3 py-1.5 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800 mb-2 inline-block mr-2';
                            
                            // Extract name - handle all possible formats
                            let name = 'Unknown';
                            if (typeof category === 'string') {
                                name = category;
                            } else if (category && typeof category === 'object') {
                                // Try multiple possible property names
                                name = category.name || category.category || category.label || category.type || category.text || category.title || 'Unknown';
                                
                                // If name is still an object, recursively extract
                                if (typeof name === 'object' && name !== null) {
                                    if (name.name) name = name.name;
                                    else if (name.category) name = name.category;
                                    else if (name.label) name = name.label;
                                    else if (name.text) name = name.text;
                                    else {
                                        // Last resort: try to stringify and take first reasonable value
                                        try {
                                            const str = JSON.stringify(name);
                                            const match = str.match(/"name":\s*"([^"]+)"/) || 
                                                         str.match(/"category":\s*"([^"]+)"/) ||
                                                         str.match(/"label":\s*"([^"]+)"/) ||
                                                         str.match(/"text":\s*"([^"]+)"/);
                                            name = match ? match[1] : str.substring(0, 50);
                                        } catch (e) {
                                            name = String(name).substring(0, 30);
                                        }
                                    }
                                }
                                
                                // Ensure name is a string
                                if (typeof name !== 'string') {
                                    name = String(name || 'Unknown');
                                }
                            }
                            
                            // Extract confidence
                            let confidence = '';
                            if (category && typeof category === 'object') {
                                confidence = category.confidence || category.score || category.percentage || category.percentage_value || '';
                                // Convert to number and format
                                if (confidence !== '') {
                                    const confNum = typeof confidence === 'number' ? confidence : parseFloat(confidence);
                                    if (!isNaN(confNum)) {
                                        confidence = Math.round(confNum);
                                    }
                                }
                            }
                            
                            categoryBadge.innerHTML = `<span class="font-semibold">${name}</span>${confidence !== '' ? ` <span class="text-xs text-indigo-600 ml-1 font-medium">${confidence}%</span>` : ''}`;
                            classificationResults.appendChild(categoryBadge);
                            readableSummary.push(`${name}${confidence !== '' ? ` (${confidence}%)` : ''}`);
                        });
                        // Also update the header subtitle (if any) by injecting a compact summary right above the badges
                        if (!document.getElementById('classificationSummary')) {
                            const summary = document.createElement('div');
                            summary.id = 'classificationSummary';
                            summary.className = 'text-sm text-gray-600 mb-2';
                            classificationResults.parentElement.insertBefore(summary, classificationResults);
                        }
                        const summaryEl = document.getElementById('classificationSummary');
                        if (summaryEl) {
                            summaryEl.textContent = readableSummary.join(', ');
                        }
                    } else {
                        classificationResults.innerHTML = '<p class="text-gray-500 text-sm">No classification categories found</p>';
                        const summaryEl = document.getElementById('classificationSummary');
                        if (summaryEl) summaryEl.textContent = '';
                    }
                    
                    // Display entities results
                    const entitiesResults = document.getElementById('entitiesResults');
                    entitiesResults.innerHTML = '';
                    
                    // Handle entities - could be array, string, or object grouped by type
                    let entities = [];
                    if (data.entities) {
                        if (Array.isArray(data.entities)) {
                            entities = data.entities;
                        } else if (typeof data.entities === 'string') {
                            // Try to parse JSON string, else split by commas
                            try {
                                const parsed = JSON.parse(data.entities);
                                if (Array.isArray(parsed)) {
                                    entities = parsed;
                                } else if (typeof parsed === 'object') {
                                    Object.keys(parsed).forEach(type => {
                                        const arr = Array.isArray(parsed[type]) ? parsed[type] : [parsed[type]];
                                        arr.forEach(val => entities.push({ type, text: val }));
                                    });
                                } else {
                                    entities = String(data.entities).split(',');
                                }
                            } catch (e) {
                                entities = String(data.entities).split(',');
                            }
                        } else if (typeof data.entities === 'object') {
                            // If it's an object, it might be grouped by type
                            Object.keys(data.entities).forEach(type => {
                                const typeEntities = Array.isArray(data.entities[type]) ? data.entities[type] : [data.entities[type]];
                                typeEntities.forEach(entity => {
                                    entities.push({
                                        type: type,
                                        text: typeof entity === 'string' ? entity : (entity.text || entity.name || entity.value || entity)
                                    });
                                });
                            });
                        }
                    }
                    
                    if (entities.length > 0) {
                        // Group entities by type
                        const entityTypes = {};
                        entities.forEach(entity => {
                            const type = entity.type || entity.category || 'General';
                            const text = typeof entity === 'string' ? entity : (entity.text || entity.name || entity.value || String(entity));
                            
                            if (!entityTypes[type]) {
                                entityTypes[type] = [];
                            }
                            entityTypes[type].push(text);
                        });
                        
                        // Create a section for each entity type
                        Object.keys(entityTypes).sort().forEach(type => {
                            const typeSection = document.createElement('div');
                            typeSection.className = 'mb-4 pb-3 border-b border-gray-100 last:border-b-0';
                            
                            const typeHeading = document.createElement('h5');
                            typeHeading.className = 'font-semibold text-gray-800 mb-2 text-sm uppercase tracking-wide';
                            typeHeading.textContent = type;
                            typeSection.appendChild(typeHeading);
                            
                            const entitiesList = document.createElement('div');
                            entitiesList.className = 'flex flex-wrap gap-2';
                            
                            // Remove duplicates
                            const uniqueEntities = [...new Set(entityTypes[type])];
                            uniqueEntities.forEach(text => {
                                const entityBadge = document.createElement('div');
                                entityBadge.className = 'px-2.5 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800';
                                entityBadge.textContent = text;
                                entitiesList.appendChild(entityBadge);
                            });
                            
                            typeSection.appendChild(entitiesList);
                            entitiesResults.appendChild(typeSection);
                        });
                    } else {
                        entitiesResults.innerHTML = '<p class="text-gray-500 text-sm">No entities found</p>';
                    }
                    
                    // Display keywords results
                    const keywordsResults = document.getElementById('keywordsResults');
                    keywordsResults.innerHTML = '';
                    
                    // Handle keywords - could be array, object, or CSV string
                    let keywords = [];
                    if (data.keywords) {
                        if (Array.isArray(data.keywords)) {
                            keywords = data.keywords;
                        } else if (typeof data.keywords === 'object') {
                            keywords = Object.keys(data.keywords);
                        } else if (typeof data.keywords === 'string') {
                            // Try JSON parse or fallback to CSV split
                            try {
                                const parsed = JSON.parse(data.keywords);
                                if (Array.isArray(parsed)) keywords = parsed;
                                else keywords = Object.keys(parsed || {});
                            } catch (e) {
                                keywords = data.keywords.split(/[;,\n]/).map(s => s.trim()).filter(Boolean);
                            }
                        }
                    }
                    
                    if (keywords.length > 0) {
                        keywords.forEach(keyword => {
                            const keywordBadge = document.createElement('div');
                            keywordBadge.className = 'px-3 py-1.5 rounded-full text-sm font-medium bg-green-100 text-green-800 mb-2 inline-block mr-2';
                            keywordBadge.textContent = typeof keyword === 'string' ? keyword : (keyword.text || keyword.keyword || keyword.name || 'Unknown');
                            keywordsResults.appendChild(keywordBadge);
                        });
                    } else {
                        keywordsResults.innerHTML = '<p class="text-gray-500 text-sm">No keywords found</p>';
                    }
                    
                    // Display sentiment results
                    const sentimentResults = document.getElementById('sentimentResults');
                    sentimentResults.innerHTML = '';
                    
                    if (data.sentiment) {
                        // Handle sentiment data - could be object or string
                        let sentimentObj = data.sentiment;
                        if (typeof sentimentObj === 'string') {
                            // If it's a string, try to parse it
                            try {
                                // Remove any surrounding quotes or JSON artifacts
                                let cleanStr = sentimentObj.trim().replace(/^["']|["']$/g, '');
                                if (cleanStr.startsWith('{') || cleanStr.startsWith('[')) {
                                    sentimentObj = JSON.parse(cleanStr);
                                } else {
                                    sentimentObj = { label: cleanStr };
                                }
                            } catch (e) {
                                // If parsing fails, check if it contains JSON-like structure
                                const jsonMatch = sentimentObj.match(/\{[^}]+\}/);
                                if (jsonMatch) {
                                    try {
                                        sentimentObj = JSON.parse(jsonMatch[0]);
                                    } catch (e2) {
                                        sentimentObj = { label: sentimentObj };
                                    }
                                } else {
                                    sentimentObj = { label: sentimentObj };
                                }
                            }
                        }
                        
                        // If sentimentObj is still an object with nested sentiment, extract it
                        if (sentimentObj && typeof sentimentObj === 'object' && sentimentObj.sentiment) {
                            sentimentObj = sentimentObj.sentiment;
                        }
                        
                        // Extract sentiment properties
                        const sentimentScore = sentimentObj.overall || sentimentObj.score || sentimentObj.value || 0;
                        let sentimentLabel = sentimentObj.sentiment_label || sentimentObj.label || sentimentObj.sentiment || 'neutral';
                        
                        // If label is an object, extract the actual label
                        if (typeof sentimentLabel === 'object') {
                            sentimentLabel = sentimentLabel.label || sentimentLabel.name || sentimentLabel.text || 'neutral';
                        }
                        
                        // Determine color based on sentiment
                        let sentimentColor = 'bg-gray-500'; // neutral
                        let sentimentTextColor = 'text-gray-700';
                        if (sentimentLabel.toLowerCase().includes('positive') || sentimentLabel.toLowerCase() === 'positive') {
                            sentimentColor = 'bg-green-500';
                            sentimentTextColor = 'text-green-700';
                        } else if (sentimentLabel.toLowerCase().includes('negative') || sentimentLabel.toLowerCase() === 'negative') {
                            sentimentColor = 'bg-red-500';
                            sentimentTextColor = 'text-red-700';
                        }
                        
                        // Create sentiment meter
                        const sentimentMeter = document.createElement('div');
                        sentimentMeter.className = 'mb-4';
                        const scorePercentage = typeof sentimentScore === 'number' 
                            ? Math.max(0, Math.min(100, Math.round((sentimentScore + 1) / 2 * 100)))
                            : 50;
                        sentimentMeter.innerHTML = `
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium ${sentimentTextColor}">Sentiment: <span class="capitalize font-semibold">${sentimentLabel}</span></span>
                                ${typeof sentimentScore === 'number' ? `<span class="text-sm font-medium text-gray-600">${sentimentScore.toFixed(2)}</span>` : ''}
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3 mb-3">
                                <div class="${sentimentColor} h-3 rounded-full transition-all duration-500" style="width: ${scorePercentage}%"></div>
                            </div>
                        `;
                        sentimentResults.appendChild(sentimentMeter);
                        
                        // Display emotional tones if available
                        const tones = sentimentObj.tones || sentimentObj.emotions || [];
                        if (Array.isArray(tones) && tones.length > 0) {
                            const tonesSection = document.createElement('div');
                            tonesSection.className = 'mt-4 pt-3 border-t border-gray-200';
                            
                            const tonesHeading = document.createElement('h5');
                            tonesHeading.className = 'font-medium text-gray-700 mb-2 text-sm';
                            tonesHeading.textContent = 'Document Tones';
                            tonesSection.appendChild(tonesHeading);
                            
                            const tonesList = document.createElement('div');
                            tonesList.className = 'flex flex-wrap gap-2';
                            
                            tones.forEach(tone => {
                                const toneBadge = document.createElement('div');
                                toneBadge.className = 'px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800';
                                const toneName = typeof tone === 'string' ? tone : (tone.tone || tone.name || 'Unknown');
                                const toneIntensity = tone.intensity || tone.score || tone.confidence || '';
                                toneBadge.innerHTML = `${toneName}${toneIntensity ? ` <span class="text-purple-600">${Math.round(toneIntensity * 100)}%</span>` : ''}`;
                                tonesList.appendChild(toneBadge);
                            });
                            
                            tonesSection.appendChild(tonesList);
                            sentimentResults.appendChild(tonesSection);
                        } else if (sentimentObj.description) {
                            const descDiv = document.createElement('div');
                            descDiv.className = 'text-sm text-gray-600 mt-2';
                            descDiv.textContent = sentimentObj.description;
                            sentimentResults.appendChild(descDiv);
                        }
                    } else {
                        sentimentResults.innerHTML = '<p class="text-gray-500 text-sm">No sentiment analysis available</p>';
                    }
                } else {
                    // Show error
                    aiAnalysisError.classList.remove('hidden');
                    document.getElementById('aiAnalysisErrorText').textContent = data.error || 'Error analyzing document. Please try again later.';
                }
            })
            .catch(error => {
                aiAnalysisLoading.classList.add('hidden');
                aiAnalysisError.classList.remove('hidden');
                document.getElementById('aiAnalysisErrorText').textContent = error.message || 'Failed to analyze document. Please try again later.';
            });
        }
        
        // Close AI analysis modal
        document.getElementById('closeAiAnalysisModal').addEventListener('click', function() {
            document.getElementById('aiAnalysisModal').classList.add('hidden');
        });
        
        document.getElementById('closeAiAnalysisBtn').addEventListener('click', function() {
            document.getElementById('aiAnalysisModal').classList.add('hidden');
        });
        
        // Copy AI analysis to clipboard
        document.getElementById('copyAiAnalysisBtn').addEventListener('click', function() {
            // Gather all analysis data
            const classification = Array.from(document.getElementById('classificationResults').querySelectorAll('div'))
                .map(el => el.textContent.trim())
                .join(', ');
                
            const entities = Array.from(document.getElementById('entitiesResults').querySelectorAll('h5'))
                .map(el => {
                    const type = el.textContent;
                    const values = Array.from(el.nextElementSibling.querySelectorAll('div'))
                        .map(div => div.textContent.trim())
                        .join(', ');
                    return `${type}: ${values}`;
                })
                .join('\n');
                
            const keywords = Array.from(document.getElementById('keywordsResults').querySelectorAll('div'))
                .map(el => el.textContent.trim())
                .join(', ');
                
            const sentiment = document.getElementById('sentimentResults').textContent.trim();
            
            const fullAnalysis = `DOCUMENT ANALYSIS\n\nCLASSIFICATION:\n${classification}\n\nENTITIES:\n${entities}\n\nKEYWORDS:\n${keywords}\n\nSENTIMENT:\n${sentiment}`;
            
            navigator.clipboard.writeText(fullAnalysis).then(function() {
                showNotification('Analysis copied to clipboard!');
            }, function(err) {
                console.error('Could not copy text: ', err);
                showNotification('Failed to copy to clipboard', 'error');
            });
        });
        
        // Show notification function
        function showNotification(message, type = 'success') {
            // Create notification element if it doesn't exist
            let notification = document.getElementById('notification');
            if (!notification) {
                notification = document.createElement('div');
                notification.id = 'notification';
                notification.className = 'fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 ease-in-out translate-y-10 opacity-0';
                document.body.appendChild(notification);
            }
            
            // Set notification style based on type
            if (type === 'error') {
                notification.className = notification.className.replace(/bg-[^\s]+/g, '');
                notification.classList.add('bg-red-600', 'text-white');
            } else if (type === 'success') {
                notification.className = notification.className.replace(/bg-[^\s]+/g, '');
                notification.classList.add('bg-green-600', 'text-white');
            } else {
                notification.className = notification.className.replace(/bg-[^\s]+/g, '');
                notification.classList.add('bg-blue-600', 'text-white');
            }
            
            // Set notification message
            notification.textContent = message;
            
            // Show notification
            notification.classList.remove('translate-y-10', 'opacity-0');
            
            // Hide notification after 3 seconds
            setTimeout(() => {
                notification.classList.add('translate-y-10', 'opacity-0');
            }, 3000);
        }

        // Transfer document form submission
        document.getElementById('transferDocumentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const documentId = document.getElementById('documentPath').value;
            const targetOffice = document.getElementById('targetOffice').value;
            const transferReason = document.getElementById('transferReason').value;
            
            if (!targetOffice) {
                showNotification('Please select a target office', 'error');
                return;
            }
            
            fetch('../actions/transfer_document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    id: documentId,
                    targetOffice: targetOffice,
                    reason: transferReason 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Document transferred successfully');
                    document.getElementById('transferDocumentModal').classList.add('hidden');
                    
                    // Reload the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification('Error: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while transferring the document', 'error');
            });
        });

        // Transfer document modal handlers
        document.getElementById('closeTransferModal').addEventListener('click', function() {
            document.getElementById('transferDocumentModal').classList.add('hidden');
        });
        
        document.getElementById('cancelTransferBtn').addEventListener('click', function() {
            document.getElementById('transferDocumentModal').classList.add('hidden');
        });

        // Initialize folder toggles
        const folderToggles = document.querySelectorAll('.folder-toggle');
        folderToggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                const folderId = this.getAttribute('data-folder');
                const folderContent = document.querySelector(`.folder-content[data-folder="${folderId}"]`);
                
                // Toggle folder open/closed
                folderContent.classList.toggle('open');
                this.classList.toggle('open');
                
                // Update folder visibility in URL
                updateUrlWithFolderState();
                
                // Show/hide documents container based on any open folders
                updateDocumentsContainerVisibility();
            });
        });
        
        // Function to handle document search and filtering
        let searchTimeout;
        
        // Show all folders when page loads with a search query
        // urlParams already declared above
        const searchQuery = urlParams.get('q');
        if (searchQuery && searchQuery.length >= 3) {
            document.getElementById('searchInput').value = searchQuery;
            
            // Open all folders to show search results
            document.querySelectorAll('.folder-content').forEach(content => {
                content.classList.add('open');
            });
            document.querySelectorAll('.folder-toggle').forEach(toggle => {
                toggle.classList.add('open');
            });
            
            // Show documents container
            const documentsContainer = document.getElementById('documents-container');
            documentsContainer.classList.add('visible');
            
            // Trigger search after page load
            setTimeout(() => {
                document.getElementById('searchInput').dispatchEvent(new Event('input'));
            }, 500);
        }
        
        document.getElementById('searchInput').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.toLowerCase();

            // Clear previous search highlights
            document.querySelectorAll('.search-highlight').forEach(el => {
                el.classList.remove('search-highlight');
            });
            
            // Hide search results summary if query is too short
            if (query.length < 3) {
                filterDocuments(query);
                document.getElementById('aiSuggestions').classList.add('hidden');
                searchResultsSummary.classList.add('hidden');
                return;
            }

            // Show loading state
            document.getElementById('aiSuggestions').classList.remove('hidden');
            document.getElementById('aiContent').innerHTML = 'Analyzing documents...';

            searchTimeout = setTimeout(() => {
                // Collect document data
                const documents = [];
                document.querySelectorAll('.document-card').forEach(item => {
                    // Check if this card has content
                    if (item.dataset.content || item.dataset.name) {
                        documents.push({
                            name: item.dataset.name || '',
                            content: item.dataset.content || '',
                            upload_date: item.querySelector('.text-gray-500') ? item.querySelector('.text-gray-500').textContent : '',
                            document_id: item.dataset.documentId || ''
                        });
                    }
                });

                console.log(`Collected ${documents.length} documents for search`);
                
                // If no documents are found, show a message
                if (documents.length === 0) {
                    document.getElementById('aiContent').innerHTML = 'No documents available to search.';
                    return;
                }

                // Call AI analysis
                fetch('../actions/analyze_documents.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        query: query,
                        documents: documents
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    console.log("AI search results:", data);

                    // Update AI suggestions
                    document.getElementById('aiContent').innerHTML = `
                        <p class="mb-2">${data.explanation}</p>
                        ${data.suggestedQueries ? `
                            <p class="font-medium mt-3">Try these searches:</p>
                            <ul class="list-disc list-inside">
                                ${data.suggestedQueries.map(q => `<li><a href="#" class="text-amber-800 hover:underline suggested-query">${q}</a></li>`).join('')}
                            </ul>
                        ` : ''}
                    `;
                    
                    // Add click event to suggested queries
                    document.querySelectorAll('.suggested-query').forEach(link => {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            const query = this.textContent;
                            document.getElementById('searchInput').value = query;
                            document.getElementById('searchInput').dispatchEvent(new Event('input'));
                        });
                    });
                    
                    // Remove animation class
                    document.querySelector('.ai-scanning').classList.remove('ai-scanning');

                    // Filter documents based on AI results
                    filterDocuments(query, data.relevantDocuments);
                })
                .catch(error => {
                    console.error('AI analysis error:', error);
                    document.getElementById('aiContent').innerHTML = `Error: ${error.message}. Using basic search instead.`;
                    filterDocuments(query);
                });
            }, 500);
        });

        // Direct folder opening function - more reliable than the regular function
        function forceOpenFolder(folderName) {
            console.log('forceOpenFolder: Opening folder:', folderName);
            
            // Hide all folder contents first
            const allContents = document.querySelectorAll('.folder-content');
            allContents.forEach(content => {
                content.style.display = 'none';
                console.log('forceOpenFolder: Hiding folder content', content.dataset.folder);
            });
            
            // Reset all folder headers to default state
            const allHeaders = document.querySelectorAll('.folder-header');
            allHeaders.forEach(header => {
                header.classList.remove('active-folder');
                header.style.display = 'flex'; // Crucially ensures headers reappear after search
                console.log('forceOpenFolder: Showing folder header', header.dataset.folder);
                
                // Reset the folder toggle icon
                const icon = header.querySelector('.folder-toggle-icon');
                if (icon) {
                    icon.style.transform = 'rotate(0deg)';
                }
            });
            
            // Show the selected folder content
            const selectedContent = document.querySelector(`.folder-content[data-folder="${folderName}"]`);
            if (selectedContent) {
                selectedContent.style.display = 'grid';
                console.log('forceOpenFolder: Showing selected folder content', folderName);
                
                // Add a smooth fade-in effect
                selectedContent.style.opacity = '0';
                setTimeout(() => {
                    selectedContent.style.transition = 'opacity 0.3s ease';
                    selectedContent.style.opacity = '1';
                }, 10);
            }
            
            // Highlight the selected folder header
            const selectedHeader = document.querySelector(`.folder-header[data-folder="${folderName}"]`);
            if (selectedHeader) {
                selectedHeader.classList.add('active-folder');
                
                // Rotate the folder toggle icon
                const icon = selectedHeader.querySelector('.folder-toggle-icon');
                if (icon) {
                    icon.style.transition = 'transform 0.3s ease';
                    icon.style.transform = 'rotate(180deg)';
                }
                
                // Scroll to the folder if needed
                selectedHeader.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // Update URL with the selected folder
            const url = new URL(window.location);
            url.searchParams.set('folder', folderName);
            window.history.replaceState({}, '', url);
            
            // Clear search when switching folders
            const searchInput = document.getElementById('document-search');
            if (searchInput) {
                searchInput.value = '';
            }
            
            // Apply current search filter to the newly opened folder
            applySearchFilter();
        }
        
        // Function to filter documents based on search term
        function applySearchFilter() {
            console.log("applySearchFilter: Applying search filter.");
            const searchTerm = document.getElementById('document-search').value.toLowerCase();
            
            // Find the currently visible folder by checking computed styles
            const allFolders = document.querySelectorAll('.folder-content');
            let currentFolder = null;
            
            for (let folder of allFolders) {
                const computedStyle = window.getComputedStyle(folder);
                if (computedStyle.display !== 'none') {
                    currentFolder = folder;
                    break;
                }
            }
            
            if (!currentFolder) {
                console.log("applySearchFilter: No visible folder found.");
                return;
            }
            
            console.log("applySearchFilter: Found current folder:", currentFolder.dataset.folder);
            
            const cards = currentFolder.querySelectorAll('.document-card');
            let hasVisibleCards = false;
            
            console.log("applySearchFilter: Found", cards.length, "cards in folder");
            
            cards.forEach(card => {
                const title = (card.querySelector('.document-title')?.textContent || '').toLowerCase();
                const description = (card.querySelector('.document-description')?.textContent || '').toLowerCase();
                const author = (card.querySelector('.document-author')?.textContent || '').toLowerCase();
                const type = (card.querySelector('.document-type')?.textContent || '').toLowerCase();
                
                if (searchTerm === '') {
                    // If search is empty, show all cards
                    card.style.display = 'flex';
                    hasVisibleCards = true;
                } else if (title.includes(searchTerm) || 
                    description.includes(searchTerm) || 
                    author.includes(searchTerm) || 
                    type.includes(searchTerm)) {
                    card.style.display = 'flex';
                    hasVisibleCards = true;
                    console.log("applySearchFilter: Showing card", card.dataset.documentId, "for search term:", searchTerm);
            } else {
                    card.style.display = 'none';
                    console.log("applySearchFilter: Hiding card", card.dataset.documentId, "for search term:", searchTerm);
                }
            });
            
            // Show or hide the "no results" message
            let noResultsMsg = currentFolder.querySelector('.no-results-message');
            
            if (!hasVisibleCards) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.className = 'no-results-message col-span-full p-8 text-center text-gray-500';
                    noResultsMsg.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <p class="text-lg font-medium">No matching documents found</p>
                        <p class="mt-1">Try adjusting your search term</p>
                    `;
                    currentFolder.appendChild(noResultsMsg);
                }
                noResultsMsg.style.display = 'block';
                console.log("applySearchFilter: Displaying no results message.");
            } else if (noResultsMsg) {
                noResultsMsg.style.display = 'none';
                console.log("applySearchFilter: Hiding no results message.");
            }
        }
        
        // Function to sort documents
        function sortDocuments(sortBy) {
            // Find the currently visible folder by checking computed styles
            const allFolders = document.querySelectorAll('.folder-content');
            let currentFolder = null;
            
            for (let folder of allFolders) {
                const computedStyle = window.getComputedStyle(folder);
                if (computedStyle.display !== 'none') {
                    currentFolder = folder;
                    break;
                }
            }
            
            if (!currentFolder) {
                console.log("sortDocuments: No visible folder found.");
                return;
            }
            
            console.log("sortDocuments: Found current folder:", currentFolder.dataset.folder);
            
            const cards = Array.from(currentFolder.querySelectorAll('.document-card'));
            console.log("sortDocuments: Found", cards.length, "cards to sort");
            
            cards.sort((a, b) => {
                switch (sortBy) {
                    case 'newest':
                        const dateA = a.querySelector('.document-date')?.textContent || '';
                        const dateB = b.querySelector('.document-date')?.textContent || '';
                        return new Date(dateB) - new Date(dateA);
                    case 'oldest':
                        const dateC = a.querySelector('.document-date')?.textContent || '';
                        const dateD = b.querySelector('.document-date')?.textContent || '';
                        return new Date(dateC) - new Date(dateD);
                    case 'name-asc':
                        const nameA = (a.querySelector('.document-title')?.textContent || '').toLowerCase();
                        const nameB = (b.querySelector('.document-title')?.textContent || '').toLowerCase();
                        return nameA.localeCompare(nameB);
                    case 'name-desc':
                        const nameC = (a.querySelector('.document-title')?.textContent || '').toLowerCase();
                        const nameD = (b.querySelector('.document-title')?.textContent || '').toLowerCase();
                        return nameD.localeCompare(nameC);
                    default:
                        return 0;
                }
            });
            
            // Remove all cards and re-append them in the sorted order
            console.log("sortDocuments: Reordering", cards.length, "cards");
            cards.forEach(card => card.remove());
            cards.forEach(card => currentFolder.appendChild(card));
            console.log("sortDocuments: Sort completed for", sortBy);
        }
        
        // Function to toggle between grid and list views
        function toggleView(viewType) {
            console.log("toggleView: Switching to", viewType, "view");
            const allFolderContents = document.querySelectorAll('.folder-content');
            const gridViewBtn = document.getElementById('grid-view-btn');
            const listViewBtn = document.getElementById('list-view-btn');
            
            if (viewType === 'grid') {
                console.log("toggleView: Setting grid view for", allFolderContents.length, "folders");
                allFolderContents.forEach(content => {
                    // Use responsive grid based on screen size
                    if (window.innerWidth <= 480) {
                        content.style.gridTemplateColumns = '1fr';
                        content.style.gap = '0.5rem';
                    } else if (window.innerWidth <= 768) {
                        content.style.gridTemplateColumns = 'repeat(auto-fill, minmax(280px, 1fr))';
                        content.style.gap = '0.75rem';
                    } else {
                        content.style.gridTemplateColumns = 'repeat(auto-fill, minmax(300px, 1fr))';
                        content.style.gap = '1rem';
                    }
                });
                
                // Update active state of buttons
                gridViewBtn.classList.add('bg-blue-50', 'text-blue-600');
                gridViewBtn.classList.remove('hover:bg-gray-100', 'text-gray-500');
                listViewBtn.classList.add('hover:bg-gray-100', 'text-gray-500');
                listViewBtn.classList.remove('bg-blue-50', 'text-blue-600');
                
                // Update card styles for grid view
                document.querySelectorAll('.document-card').forEach(card => {
                    card.classList.add('flex-col');
                    card.classList.remove('flex-row');
                    
                    // Ensure the document actions are visible and properly spaced
                    const actions = card.querySelector('.document-actions');
                    if (actions) {
                        actions.classList.remove('flex-col', 'space-y-2');
                        actions.classList.add('justify-center');
                    }
                });
            } else {
                console.log("toggleView: Setting list view for", allFolderContents.length, "folders");
                allFolderContents.forEach(content => {
                    content.style.gridTemplateColumns = '1fr';
                    content.style.gap = '0.5rem';
                });
                
                // Update active state of buttons
                listViewBtn.classList.add('bg-blue-50', 'text-blue-600');
                listViewBtn.classList.remove('hover:bg-gray-100', 'text-gray-500');
                gridViewBtn.classList.add('hover:bg-gray-100', 'text-gray-500');
                gridViewBtn.classList.remove('bg-blue-50', 'text-blue-600');
                
                // Update card styles for list view
                document.querySelectorAll('.document-card').forEach(card => {
                    card.classList.add('flex-row');
                    card.classList.remove('flex-col');
                    
                    // Ensure the document actions are visible and properly spaced
                    const actions = card.querySelector('.document-actions');
                    if (actions) {
                        actions.classList.add('justify-end');
                        actions.classList.remove('justify-center');
                    }
                });
            }
            
            // Save the view preference
            localStorage.setItem('documentViewPreference', viewType);
            console.log("toggleView: View preference saved as", viewType);
        }
        
        // Initialize document display
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOMContentLoaded: Initializing document display");
            // Add CSS for active folder styling
            const style = document.createElement('style');
            style.textContent = `
                .active-folder {
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                }
                .document-card {
                    border-radius: 0.5rem;
                    border: 1px solid #e5e7eb;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    height: 100%;
                    background-color: white;
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                }
                .document-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                }
                .file-badge-pdf {
                    background-color: #ef4444;
                    color: white;
                    padding: 2px 4px;
                    border-radius: 4px;
                }
                .file-badge-docx {
                    background-color: #2563eb;
                    color: white;
                    padding: 2px 4px;
                    border-radius: 4px;
                }
                .file-badge-txt {
                    background-color: #10b981;
                    color: white;
                    padding: 2px 4px;
                    border-radius: 4px;
                }
                .file-badge-html {
                    background-color: #f59e0b;
                    color: white;
                    padding: 2px 4px;
                    border-radius: 4px;
                }
                .file-badge-other {
                    background-color: #6b7280;
                    color: white;
                    padding: 2px 4px;
                    border-radius: 4px;
                }
                .folder-content {
                    min-height: 100px;
                }
                .document-card.flex-row .document-card-header {
                    width: 200px;
                    border-right: 1px solid #e5e7eb;
                    border-bottom: none;
                }
                .document-card.flex-row {
                    height: 100px;
                }
                .document-card.flex-row .document-card-body {
                    flex: 1;
                    border-right: 1px solid #e5e7eb;
                }
                .document-card.flex-row .document-card-footer {
                    width: 150px;
                    border-top: none;
                    align-items: center;
                    justify-content: center;
                }
                .document-card.flex-row .document-actions {
                    flex-direction: column;
                    align-items: flex-start;
                }
                .document-card.flex-row .document-actions > * {
                    margin-bottom: 8px;
                    margin-left: 0;
                }
                .document-actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                }
                .document-actions button, 
                .document-actions a {
                    white-space: nowrap;
                    display: inline-flex;
                    align-items: center;
                }
            `;
            document.head.appendChild(style);
            
            // Check URL parameters for folder selection
            const urlParams = new URLSearchParams(window.location.search);
            const folderParam = urlParams.get('folder');
            
                if (folderParam) {
                // Open the folder specified in URL
                    forceOpenFolder(folderParam);
                } else {
                // Default to opening the "all" folder
                    forceOpenFolder('all');
                }

            // Add click handlers to folder headers
            const folderHeaders = document.querySelectorAll('.folder-header');
            console.log("DOMContentLoaded: Found", folderHeaders.length, "folder headers");
            folderHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    const folderName = this.getAttribute('data-folder');
                    console.log("Folder header clicked:", folderName);
                    forceOpenFolder(folderName);
                });
            });
            
            // Add click handlers to document cards for viewing
            const documentCards = document.querySelectorAll('.document-card');
            documentCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    // Don't handle clicks on buttons, links, or their children (like SVG icons)
                    if (e.target.closest('button') || e.target.closest('a')) {
                        return;
                    }
                    
                    const documentId = this.getAttribute('data-document-id');
                    previewDocument(documentId);
                });
            });
            
            // Set up search functionality
            const searchInput = document.getElementById('document-search');
            if (searchInput) {
                console.log("Setting up search input event listener");
                searchInput.addEventListener('input', function() {
                    console.log("Search input changed:", this.value);
                    applySearchFilter();
                });
            } else {
                console.log("Search input element not found");
            }
            
            // Set up sorting functionality
            const sortSelect = document.getElementById('document-sort');
            if (sortSelect) {
                console.log("Setting up sort select event listener");
                sortSelect.addEventListener('change', function() {
                    console.log("Sort changed to:", this.value);
                    sortDocuments(this.value);
                });
            } else {
                console.log("Sort select element not found");
            }
            
            // Set up view toggle functionality
            const gridViewBtn = document.getElementById('grid-view-btn');
            const listViewBtn = document.getElementById('list-view-btn');
            
            if (gridViewBtn && listViewBtn) {
                console.log("Setting up view toggle event listeners");
                gridViewBtn.addEventListener('click', function() {
                    console.log("Grid view button clicked");
                    toggleView('grid');
                });
                
                listViewBtn.addEventListener('click', function() {
                    console.log("List view button clicked");
                    toggleView('list');
                });
                
                // Load saved view preference
                const savedViewPreference = localStorage.getItem('documentViewPreference') || 'grid';
                console.log("Loading saved view preference:", savedViewPreference);
                toggleView(savedViewPreference);
            } else {
                console.log("View toggle buttons not found");
            }
        });

        // Function to filter documents based on a search query
        function filterDocuments(query, relevantDocuments = null) {
            console.log("filterDocuments: Called with query", query, "and relevantDocuments", relevantDocuments ? relevantDocuments.length : 'none');

            const allCards = document.querySelectorAll('.document-card');
            const aiSuggestions = document.getElementById('aiSuggestions');
            const queryLowercase = query.toLowerCase();

            // If the query is empty, reset the view
            if (!query) {
                console.log("filterDocuments: Resetting view as query is empty.");
                allCards.forEach(card => {
                    card.style.display = '';
                    card.classList.remove('search-highlight');
                });
                // Ensure all folder headers are visible when search is reset
                document.querySelectorAll('.folder-header').forEach(h => h.style.display = 'flex');
                aiSuggestions.classList.add('hidden');
                forceOpenFolder('all'); // Re-open the 'All Documents' folder by default
                return;
            }
            
            // Ensure aiSuggestions is visible during filtering
            aiSuggestions.classList.remove('hidden');

            // Determine which cards to show
            if (relevantDocuments && Array.isArray(relevantDocuments) && relevantDocuments.length > 0) {
                const relevantIds = relevantDocuments.map(doc => String(doc.document_id)); // Ensure IDs are strings for consistent comparison
                console.log("filterDocuments: Filtering by AI relevant documents. IDs:", relevantIds);
                allCards.forEach(card => {
                    const docId = String(card.dataset.documentId); // Ensure card ID is also a string
                    if (relevantIds.includes(docId)) {
                        card.style.display = '';
                        card.classList.add('search-highlight');
                        console.log("filterDocuments: Showing and highlighting", docId);
                    } else {
                        card.style.display = 'none';
                        console.log("filterDocuments: Hiding", docId);
                    }
                });
            } else {
                // Basic text search (case-insensitive) if no AI relevant documents provided or they are empty
                console.log("filterDocuments: Performing basic text search for", queryLowercase, ". No AI relevant documents or list is empty.");
                allCards.forEach(card => {
                    const title = (card.querySelector('.document-title')?.textContent || '').toLowerCase();
                    const content = (card.dataset.content || '').toLowerCase();
                    const author = (card.querySelector('.document-author')?.textContent || '').toLowerCase();

                    if (title.includes(queryLowercase) || content.includes(queryLowercase) || author.includes(queryLowercase)) {
                        card.style.display = '';
                        console.log("filterDocuments: Basic search showing", card.dataset.documentId);
                    } else {
                        card.style.display = 'none';
                        console.log("filterDocuments: Basic search hiding", card.dataset.documentId);
                    }
                });
            }

            // Update folder visibility based on search results
            document.querySelectorAll('.folder-content').forEach(folderContent => {
                const visibleCards = folderContent.querySelectorAll('.document-card:not([style*="display: none"])');
                if (visibleCards.length > 0) {
                    folderContent.style.display = 'grid';
                    console.log("filterDocuments: Folder content", folderContent.dataset.folder, "set to grid");
                } else {
                    folderContent.style.display = 'none';
                    console.log("filterDocuments: Folder content", folderContent.dataset.folder, "set to none");
                }
            });
        }
        
        // Function to handle document search and filtering
        // searchTimeout already declared above
        
        // Show all folders when page loads with a search query
        // urlParams already declared above
        const searchQuery = urlParams.get('q');
        if (searchQuery && searchQuery.length >= 3) {
            document.getElementById('searchInput').value = searchQuery;
            
            // Open all folders to show search results
            document.querySelectorAll('.folder-content').forEach(content => {
                content.classList.add('open');
            });
            document.querySelectorAll('.folder-toggle').forEach(toggle => {
                toggle.classList.add('open');
            });
            
            // Show documents container
            const documentsContainer = document.getElementById('documents-container');
            documentsContainer.classList.add('visible');
            
            // Trigger search after page load
            setTimeout(() => {
                document.getElementById('searchInput').dispatchEvent(new Event('input'));
            }, 500);
        }
        
        // Main event listener for the AI search input
        document.getElementById('searchInput').addEventListener('input', function(e) {
            console.log("searchInput input event fired. Current query:", e.target.value);

            clearTimeout(searchTimeout);
            const query = e.target.value;

            // Clear previous search highlights
            document.querySelectorAll('.search-highlight').forEach(el => {
                el.classList.remove('search-highlight');
            });
            
            // If query is too short, reset the filter and hide AI suggestions
            if (query.length < 3) {
                console.log("searchInput: Query too short, resetting filter and hiding AI suggestions.");
                filterDocuments(''); // Pass empty string to reset
                document.getElementById('aiSuggestions').classList.add('hidden');

                const aiScanningDiv = document.getElementById('aiSuggestions').querySelector('.ai-scanning');
                if (aiScanningDiv) {
                    aiScanningDiv.classList.remove('ai-scanning');
                    console.log("searchInput: Removed ai-scanning class (query too short).");
                }
                return;
            }

            // Show loading state and animation
            document.getElementById('aiSuggestions').classList.remove('hidden');
            document.getElementById('aiContent').innerHTML = 'Analyzing documents...';
            
            // Get the specific div containing the pulsing dots and add the class
            const aiScanningDiv = document.getElementById('aiSuggestions').querySelector('.ai-scanning');
            if (aiScanningDiv) {
                aiScanningDiv.classList.add('ai-scanning');
                console.log("searchInput: Added ai-scanning class.");
            }

            searchTimeout = setTimeout(() => {
                console.log("searchInput: Debounce timeout finished. Starting mock search.");
                // Collect document data from all available cards (even hidden ones)
                const documents = [];
                document.querySelectorAll('.document-card').forEach(item => {
                    // Ensure document_id is always a string for consistent comparison in mockAISearch
                    const documentId = item.dataset.documentId ? String(item.dataset.documentId) : '';
                    
                    // Only add if we have a valid documentId and some content/name
                    if (documentId && (item.dataset.content || item.dataset.name)) {
                        documents.push({
                            name: item.dataset.name || '',
                            content: item.dataset.content || '',
                            upload_date: item.querySelector('.document-date') ? item.querySelector('.document-date').textContent : '',
                            document_id: documentId
                        });
                    }
                });

                console.log(`searchInput: Collected ${documents.length} documents for mock AI search`);
                
                // If no documents are found on the page, show a message
                if (documents.length === 0) {
                    document.getElementById('aiContent').innerHTML = 'No documents available to search on the page.';
                    const currentAiScanningDiv = document.getElementById('aiSuggestions').querySelector('.ai-scanning');
                    if (currentAiScanningDiv) {
                        currentAiScanningDiv.classList.remove('ai-scanning');
                        console.log("searchInput: Removed ai-scanning class (no documents on page).");
                    }
                    filterDocuments(''); // Reset filter completely
                    return;
                }

                // Call mock AI search
                mockAISearch(query, documents)
                    .then(data => {
                        console.log("searchInput: Mock AI search results received:", data);

                        // Update AI suggestions panel content
                        document.getElementById('aiContent').innerHTML = `
                            <p class="mb-2">${data.explanation}</p>
                            ${data.suggestedQueries && data.suggestedQueries.length > 0 ? `
                                <p class="font-medium mt-3">Try these searches:</p>
                                <ul class="list-disc list-inside">
                                    ${data.suggestedQueries.map(q => `<li><a href="#" class="text-amber-800 hover:underline suggested-query">${q}</a></li>`).join('')}
                                </ul>
                            ` : ''}
                        `;
                        
                        // Add click event to suggested queries
                        document.querySelectorAll('.suggested-query').forEach(link => {
                            link.addEventListener('click', function(e) {
                                e.preventDefault();
                                const queryText = this.textContent;
                                document.getElementById('searchInput').value = queryText;
                                document.getElementById('searchInput').dispatchEvent(new Event('input')); // Trigger search with new query
                            });
                        });
                        
                        // Remove animation class
                        const currentAiScanningDiv = document.getElementById('aiSuggestions').querySelector('.ai-scanning');
                        if (currentAiScanningDiv) {
                            currentAiScanningDiv.classList.remove('ai-scanning');
                            console.log("searchInput: Removed ai-scanning class (success).");
                        }

                        // Filter documents based on mock AI results
                        filterDocuments(query, data.relevantDocuments);
                    })
                    .catch(error => {
                        console.error('searchInput: Mock AI search error:', error);
                        document.getElementById('aiContent').innerHTML = `Error: ${error.message}. Using basic search instead.`;
                        const currentAiScanningDiv = document.getElementById('aiSuggestions').querySelector('.ai-scanning');
                        if (currentAiScanningDiv) {
                            currentAiScanningDiv.classList.remove('ai-scanning');
                            console.log("searchInput: Removed ai-scanning class (error).");
                        }
                        filterDocuments(query); // Fallback to basic search on error
                    });
            }, 500); // Debounce time
        });

        // Mock AI search function to simulate API response
        function mockAISearch(query, documents) {
            console.log("mockAISearch: Called with query", query, "and documents count", documents.length);
            
            const relevantDocs = [];
            // Simulate finding some relevant documents based on the query
            const queryLower = query.toLowerCase();
            documents.forEach(doc => {
                const docTitleLower = (doc.name || '').toLowerCase();
                const docContentLower = (doc.content || '').toLowerCase();
                
                // Using a simple check: if the query is in title or content, it's 'relevant'
                if (docTitleLower.includes(queryLower) || docContentLower.includes(queryLower)) {
                    relevantDocs.push({ document_id: doc.document_id, reason: `Mock match for \"${query}\"` });
                }
            });

            // If after initial check, no docs matched, just pick the first few for demonstration
            // This ensures a result is always shown for the mockup, if documents exist.
            if (relevantDocs.length === 0 && documents.length > 0) {
                const numToPick = Math.min(3, documents.length); // Pick up to 3 documents
                for (let i = 0; i < numToPick; i++) {
                    if (documents[i]) { // Ensure document exists at index
                        relevantDocs.push({ document_id: documents[i].document_id, reason: 'Random mock document (no specific match)' });
                    }
                }
            }

            const mockExplanation = `Mock AI analysis: Found ${relevantDocs.length} document(s) for \"${query}\".`;
            const mockSuggestedQueries = [
                "Find all financial reports",
                "Documents about legal compliance",
                "Recent memos on HR policies"
            ];
            
            return new Promise(resolve => {
                setTimeout(() => {
                    console.log("mockAISearch: Resolving with relevantDocs count", relevantDocs.length);
                    resolve({
                        success: true,
                        explanation: mockExplanation,
                        relevantDocuments: relevantDocs,
                        suggestedQueries: mockSuggestedQueries
                    });
                }, 800); // Simulate network delay
            });
        }
    </script>
  
  <!-- Layout Fix Script -->
  <script src="../assets/js/document-layout-fix.js?v=20250109"></script>
  
  <!-- HARD FIX: Search and Sort Functionality - INLINE -->
  <script>
  console.log('🔧 HARD FIX: Loading INLINE search and sort functionality...');
  
  // Global variables
  let currentSearchTerm = '';
  let currentSortBy = 'newest';
  let currentViewType = 'grid';
  
  // Function to get the currently visible folder
  function getCurrentVisibleFolder() {
      const allFolders = document.querySelectorAll('.folder-content');
      for (let folder of allFolders) {
          const computedStyle = window.getComputedStyle(folder);
          if (computedStyle.display !== 'none' && computedStyle.visibility !== 'hidden') {
              return folder;
          }
      }
      return null;
  }
  
  // Function to search documents
  function performSearch(searchTerm) {
      console.log('🔍 SEARCH: Searching for:', searchTerm);
      currentSearchTerm = searchTerm.toLowerCase();
      
      const currentFolder = getCurrentVisibleFolder();
      if (!currentFolder) {
          console.log('❌ SEARCH: No visible folder found');
          return;
      }
      
      const cards = currentFolder.querySelectorAll('.document-card');
      let visibleCount = 0;
      
      cards.forEach(card => {
          const title = (card.querySelector('.document-title')?.textContent || '').toLowerCase();
          const description = (card.querySelector('.document-description')?.textContent || '').toLowerCase();
          const author = (card.querySelector('.document-author')?.textContent || '').toLowerCase();
          const type = (card.querySelector('.document-type')?.textContent || '').toLowerCase();
          
          const matches = currentSearchTerm === '' || 
                         title.includes(currentSearchTerm) || 
                         description.includes(currentSearchTerm) || 
                         author.includes(currentSearchTerm) || 
                         type.includes(currentSearchTerm);
          
          if (matches) {
              card.style.display = 'flex';
              visibleCount++;
          } else {
              card.style.display = 'none';
          }
      });
      
      console.log(`✅ SEARCH: Found ${visibleCount} matching documents`);
      
      // Show/hide no results message
      let noResultsMsg = currentFolder.querySelector('.no-results-message');
      if (visibleCount === 0 && currentSearchTerm !== '') {
          if (!noResultsMsg) {
              noResultsMsg = document.createElement('div');
              noResultsMsg.className = 'no-results-message col-span-full p-8 text-center text-gray-500';
              noResultsMsg.innerHTML = `
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                  <p class="text-lg font-medium">No matching documents found</p>
                  <p class="mt-1">Try adjusting your search term</p>
              `;
              currentFolder.appendChild(noResultsMsg);
          }
          noResultsMsg.style.display = 'block';
      } else if (noResultsMsg) {
          noResultsMsg.style.display = 'none';
      }
  }
  
  // Function to sort documents
  function performSort(sortBy) {
      console.log('📊 SORT: Sorting by:', sortBy);
      currentSortBy = sortBy;
      
      const currentFolder = getCurrentVisibleFolder();
      if (!currentFolder) {
          console.log('❌ SORT: No visible folder found');
          return;
      }
      
      const cards = Array.from(currentFolder.querySelectorAll('.document-card'));
      console.log(`📊 SORT: Found ${cards.length} cards to sort`);
      
      cards.sort((a, b) => {
          switch (sortBy) {
              case 'newest':
                  const dateA = a.querySelector('.document-date')?.textContent || '';
                  const dateB = b.querySelector('.document-date')?.textContent || '';
                  return new Date(dateB) - new Date(dateA);
              case 'oldest':
                  const dateC = a.querySelector('.document-date')?.textContent || '';
                  const dateD = b.querySelector('.document-date')?.textContent || '';
                  return new Date(dateC) - new Date(dateD);
              case 'name-asc':
                  const nameA = (a.querySelector('.document-title')?.textContent || '').toLowerCase();
                  const nameB = (b.querySelector('.document-title')?.textContent || '').toLowerCase();
                  return nameA.localeCompare(nameB);
              case 'name-desc':
                  const nameC = (a.querySelector('.document-title')?.textContent || '').toLowerCase();
                  const nameD = (b.querySelector('.document-title')?.textContent || '').toLowerCase();
                  return nameD.localeCompare(nameC);
              default:
                  return 0;
          }
      });
      
      // Re-append cards in sorted order
      cards.forEach(card => card.remove());
      cards.forEach(card => currentFolder.appendChild(card));
      
      console.log('✅ SORT: Sort completed');
  }
  
  // Function to toggle view
  function performViewToggle(viewType) {
      console.log('👁️ VIEW: Switching to', viewType, 'view');
      currentViewType = viewType;
      
      const allFolders = document.querySelectorAll('.folder-content');
      const gridBtn = document.getElementById('grid-view-btn');
      const listBtn = document.getElementById('list-view-btn');
      
      allFolders.forEach(folder => {
          if (viewType === 'grid') {
              // Use responsive grid based on screen size
              if (window.innerWidth <= 480) {
                  folder.style.gridTemplateColumns = '1fr';
                  folder.style.gap = '0.5rem';
              } else if (window.innerWidth <= 768) {
                  folder.style.gridTemplateColumns = 'repeat(auto-fill, minmax(280px, 1fr))';
                  folder.style.gap = '0.75rem';
              } else {
                  folder.style.gridTemplateColumns = 'repeat(auto-fill, minmax(300px, 1fr))';
                  folder.style.gap = '1rem';
              }
          } else {
              folder.style.gridTemplateColumns = '1fr';
              folder.style.gap = '0.5rem';
          }
      });
      
      // Update button states
      if (gridBtn && listBtn) {
          if (viewType === 'grid') {
              gridBtn.classList.add('bg-blue-50', 'text-blue-600');
              gridBtn.classList.remove('hover:bg-gray-100', 'text-gray-500');
              listBtn.classList.add('hover:bg-gray-100', 'text-gray-500');
              listBtn.classList.remove('bg-blue-50', 'text-blue-600');
          } else {
              listBtn.classList.add('bg-blue-50', 'text-blue-600');
              listBtn.classList.remove('hover:bg-gray-100', 'text-gray-500');
              gridBtn.classList.add('hover:bg-gray-100', 'text-gray-500');
              gridBtn.classList.remove('bg-blue-50', 'text-blue-600');
          }
      }
      
      localStorage.setItem('documentViewPreference', viewType);
      console.log('✅ VIEW: View switched to', viewType);
  }
  
  // Function to setup all event listeners
  function setupEventListeners() {
      console.log('🔧 SETUP: Setting up event listeners...');
      
      // Search input
      const searchInput = document.getElementById('document-search');
      if (searchInput) {
          searchInput.addEventListener('input', function() {
              performSearch(this.value);
          });
          console.log('✅ SETUP: Search input listener added');
      } else {
          console.log('❌ SETUP: Search input not found');
      }
      
      // Sort select
      const sortSelect = document.getElementById('document-sort');
      if (sortSelect) {
          sortSelect.addEventListener('change', function() {
              performSort(this.value);
          });
          console.log('✅ SETUP: Sort select listener added');
      } else {
          console.log('❌ SETUP: Sort select not found');
      }
      
      // View toggle buttons
      const gridBtn = document.getElementById('grid-view-btn');
      const listBtn = document.getElementById('list-view-btn');
      
      if (gridBtn && listBtn) {
          gridBtn.addEventListener('click', function() {
              performViewToggle('grid');
          });
          listBtn.addEventListener('click', function() {
              performViewToggle('list');
          });
          
          // Add window resize listener for responsive behavior
          window.addEventListener('resize', function() {
              if (currentViewType === 'grid') {
                  performViewToggle('grid');
              }
          });
          console.log('✅ SETUP: View toggle listeners added');
      } else {
          console.log('❌ SETUP: View toggle buttons not found');
      }
      
      // Test button
      const testBtn = document.getElementById('test-search-sort');
      if (testBtn) {
          testBtn.addEventListener('click', function() {
              console.log('🧪 TEST: Running search and sort test...');
              
              // Test search
              const searchInput = document.getElementById('document-search');
              if (searchInput) {
                  searchInput.value = 'test';
                  performSearch('test');
                  setTimeout(() => {
                      searchInput.value = '';
                      performSearch('');
                  }, 2000);
              }
              
              // Test sort
              const sortSelect = document.getElementById('document-sort');
              if (sortSelect) {
                  sortSelect.value = 'name-asc';
                  performSort('name-asc');
                  setTimeout(() => {
                      sortSelect.value = 'newest';
                      performSort('newest');
                  }, 2000);
              }
              
              // Test view toggle
              performViewToggle('list');
              setTimeout(() => {
                  performViewToggle('grid');
              }, 2000);
              
              console.log('✅ TEST: Test completed - check console for results');
          });
          console.log('✅ SETUP: Test button listener added');
      }
  }
  
  // Function to initialize everything
  function initializeSearchSort() {
      console.log('🚀 INIT: Initializing search and sort functionality...');
      
      // Wait a bit for DOM to be fully ready
      setTimeout(() => {
          setupEventListeners();
          
          // Load saved preferences
          const savedView = localStorage.getItem('documentViewPreference') || 'grid';
          performViewToggle(savedView);
          
          console.log('✅ INIT: Search and sort functionality ready!');
          console.log('🎯 TEST: Try typing in the search box or changing the sort dropdown');
      }, 1000);
  }
  
  // Start the initialization
  if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initializeSearchSort);
  } else {
      initializeSearchSort();
  }
  
  // Also run after a delay to catch any late-loading content
  setTimeout(initializeSearchSort, 2000);
  </script>

  <!-- Document Preview Modal -->
  <div id="documentPreviewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000] hidden">
      <div class="bg-white rounded-lg shadow-lg w-full max-w-6xl max-h-[90vh] flex flex-col">
          <div class="p-4 border-b flex justify-between items-center">
              <h3 class="text-xl font-semibold text-gray-800" id="previewDocumentTitle">Document Preview</h3>
              <button id="closePreviewModal" class="text-gray-500 hover:text-gray-700">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
              </button>
          </div>
          <div class="p-6 overflow-y-auto flex-grow">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                  <!-- Document Metadata -->
                  <div class="md:col-span-1">
                      <div class="bg-white rounded-lg border p-5 mb-6">
                          <h2 class="text-lg font-semibold mb-4 pb-2 border-b">Document Details</h2>
                          
                          <!-- Status Badge -->
                          <div class="mb-4">
                              <p class="text-sm text-gray-500 mb-1">Status</p>
                              <span id="previewDocumentStatus" class="badge badge-pending">Pending</span>
                          </div>
                          
                          <div class="mb-4">
                              <p class="text-sm text-gray-500 mb-1">Document ID</p>
                              <p id="previewDocumentId" class="font-medium">DOC-000</p>
                          </div>
                          
                          <div class="mb-4">
                              <p class="text-sm text-gray-500 mb-1">Document Type</p>
                              <p id="previewDocumentType" class="font-medium">Unknown</p>
                          </div>
                          
                          <div class="mb-4">
                              <p class="text-sm text-gray-500 mb-1">Created By</p>
                              <p id="previewDocumentCreator" class="font-medium">Unknown</p>
                          </div>
                          
                          <div class="mb-4">
                              <p class="text-sm text-gray-500 mb-1">From Office</p>
                              <p id="previewDocumentOffice" class="font-medium">Unknown</p>
                          </div>
                          
                          <div class="mb-4">
                              <p class="text-sm text-gray-500 mb-1">Created On</p>
                              <p id="previewDocumentCreatedDate" class="font-medium">Unknown</p>
                          </div>
                          
                          <div class="mb-4">
                              <p class="text-sm text-gray-500 mb-1">Last Updated</p>
                              <p id="previewDocumentUpdatedDate" class="font-medium">Unknown</p>
                          </div>
                          
                          <!-- QR Code Section -->
                          <div id="previewQrCodeSection" class="mt-6 pt-4 border-t">
                              <h3 class="text-md font-semibold mb-3">QR Verification</h3>
                              <div class="flex flex-col items-center">
                                  <img id="previewQrCode" src="" alt="QR Code" class="w-32 h-32 mb-2">
                                  <p class="text-xs text-gray-500 text-center mb-1">Scan to verify document</p>
                                  <p id="previewVerificationCode" class="text-sm font-mono bg-gray-100 px-2 py-1 rounded"></p>
                              </div>
                          </div>
                      </div>
                  </div>
                  
                  <!-- Document Preview -->
                  <div class="md:col-span-2">
                      <div class="bg-white rounded-lg border p-5">
                          <h2 class="text-lg font-semibold mb-4 pb-2 border-b">Document Preview</h2>
                          <div id="previewDocumentContent" class="min-h-[400px]">
                              <div class="flex items-center justify-center h-64">
                                  <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
          <div class="p-4 bg-gray-50 rounded-b-lg flex justify-between">
              <a id="fullDocumentViewLink" href="#" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                  </svg>
                  View Full Page
              </a>
              <button id="printDocumentBtn" onclick="printDocument()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17H5a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2zm0 0l4 4m-4-4v4m0-4h4m-4 0a2 2 0 01-2-2v-4m-7 4h.01M7 13h.01M17 17v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4"/>
                  </svg>
                  Print Document
              </button>
              <button id="closePreviewBtn" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100">
                  Close
              </button>
          </div>
      </div>
  </div>

  <style>
      .badge {
          display: inline-block;
          padding: 0.25em 0.6em;
          font-size: 75%;
          font-weight: 700;
          line-height: 1;
          text-align: center;
          white-space: nowrap;
          vertical-align: baseline;
          border-radius: 0.375rem;
      }
      .badge-pending {
          background-color: #FEF3C7;
          color: #92400E;
      }
      .badge-approved {
          background-color: #D1FAE5;
          color: #065F46;
      }
      .badge-rejected {
          background-color: #FEE2E2;
          color: #B91C1C;
      }
      .badge-hold, .badge-on_hold {
          background-color: #E0F2FE;
          color: #0369A1;
      }
      .badge-revision, .badge-revision_requested {
          background-color: #F3E8FF;
          color: #7E22CE;
      }
  </style>

  <script>
      // Cache-busting: Updated 2025-01-09 to fix search/sort functionality
      console.log('Main documents script loaded - search/sort functionality available');
      
      // Document preview functionality
      function previewDocument(documentId) {
          console.log('Previewing document:', documentId);
          
          // Show the preview modal
          const previewModal = document.getElementById('documentPreviewModal');
          previewModal.classList.remove('hidden');
          
          // Set loading state
          document.getElementById('previewDocumentContent').innerHTML = `
              <div class="flex items-center justify-center h-64">
                  <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
              </div>
          `;
          
          // Update the full view link
          document.getElementById('fullDocumentViewLink').href = `dashboard.php?page=view_document&id=${documentId}`;
          
          // Fetch document details
          fetch(`../actions/get_document_details.php?id=${documentId}`)
              .then(response => {
                  if (!response.ok) {
                      throw new Error('Network response was not ok: ' + response.status);
                  }
                  return response.json();
              })
              .then(data => {
                  if (data.error) {
                      throw new Error(data.error);
                  }
                  
                  // Cache for printing later
                  window.currentPreviewDoc = data;
                  
                  // Update document details
                  document.getElementById('previewDocumentTitle').textContent = data.title || 'Document Preview';
                  document.getElementById('previewDocumentId').textContent = `DOC-${String(data.document_id).padStart(3, '0')}`;
                  document.getElementById('previewDocumentType').textContent = data.type_name || 'Unknown';
                  document.getElementById('previewDocumentCreator').textContent = data.creator_name || 'Unknown';
                  document.getElementById('previewDocumentOffice').textContent = data.office_name || 'Unknown';
                  document.getElementById('previewDocumentCreatedDate').textContent = formatDate(data.created_at) || 'Unknown';
                  document.getElementById('previewDocumentUpdatedDate').textContent = formatDate(data.updated_at) || 'Unknown';
                  
                  // Update status badge
                  const statusBadge = document.getElementById('previewDocumentStatus');
                  let statusClass = 'badge-pending';
                  let statusText = 'Pending';
                  
                  switch(data.status) {
                      case 'approved':
                          statusClass = 'badge-approved';
                          statusText = 'Approved';
                          break;
                      case 'rejected':
                          statusClass = 'badge-rejected';
                          statusText = 'Rejected';
                          break;
                      case 'revision':
                      case 'revision_requested':
                          statusClass = 'badge-revision';
                          statusText = 'Revision Requested';
                          break;
                      case 'on_hold':
                          statusClass = 'badge-hold';
                          statusText = 'On Hold';
                          break;
                  }
                  
                  statusBadge.className = `badge ${statusClass}`;
                  statusBadge.textContent = statusText;
                  
                  // Update QR code if available
                  if (data.verification_code) {
                      const verificationUrl = `http://${window.location.host}/SCCDMS2/document_with_qr.php?doc=${data.document_id}&code=${data.verification_code}`;
                      const qrImageUrl = `../qr_display.php?url=${encodeURIComponent(verificationUrl)}&size=150`;
                      
                      document.getElementById('previewQrCode').src = qrImageUrl;
                      document.getElementById('previewVerificationCode').textContent = data.verification_code;
                      document.getElementById('previewQrCodeSection').style.display = 'block';
                  } else {
                      document.getElementById('previewQrCodeSection').style.display = 'none';
                  }
                  
                  // Display document content based on file type
                  const filePath = data.file_path ? fixFilePath(data.file_path) : '';
                  const fileExtension = filePath ? filePath.split('.').pop().toLowerCase() : '';
                  const hasGoogleDoc = data.google_doc_id ? true : false;
                  const googleDocId = data.google_doc_id || '';
                  
                  if (!hasGoogleDoc && filePath) {
                      switch (fileExtension) {
                          case 'txt':
                              // Fetch text file content
                              fetch(filePath)
                                  .then(response => response.text())
                                  .then(content => {
                                      document.getElementById('previewDocumentContent').innerHTML = `
                                          <div class="p-4 bg-white rounded-lg border text-gray-800 whitespace-pre-wrap">${escapeHtml(content)}</div>
                                      `;
                                  })
                                  .catch(error => {
                                      document.getElementById('previewDocumentContent').innerHTML = `
                                          <div class="text-center p-6 bg-red-50 rounded-lg">
                                              <p class="text-red-600">Error loading text content: ${error.message}</p>
                                          </div>
                                      `;
                                  });
                              break;
                          case 'html':
                          case 'htm':
                              document.getElementById('previewDocumentContent').innerHTML = `
                                  <iframe src="${filePath}" width="100%" height="700px" class="border-0 rounded-lg"></iframe>
                              `;
                              break;
                          case 'pdf':
                              document.getElementById('previewDocumentContent').innerHTML = `
                                  <iframe src="${filePath}" width="100%" height="700px" class="border-0 rounded-lg"></iframe>
                              `;
                              break;
                          case 'docx':
                          case 'doc':
                              document.getElementById('previewDocumentContent').innerHTML = `
                                  <div class="text-center p-6 bg-blue-50 rounded-lg mb-4">
                                      <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-blue-600 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                      </svg>
                                      <h3 class="text-xl font-semibold mb-2">Microsoft Word Document</h3>
                                      <p class="text-gray-600 mb-4">Preview not available for this file type.</p>
                                      <a href="${filePath}" download class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                          </svg>
                                          Download Document
                                      </a>
                                  </div>
                              `;
                              break;
                          case 'jpg':
                          case 'jpeg':
                          case 'png':
                          case 'gif':
                              document.getElementById('previewDocumentContent').innerHTML = `
                                  <img src="${filePath}" alt="Document Preview" class="max-w-full h-auto mx-auto rounded-lg">
                              `;
                              break;
                          default:
                              document.getElementById('previewDocumentContent').innerHTML = `
                                  <div class="text-center p-6 bg-gray-50 rounded-lg mb-4">
                                      <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h7.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                      </svg>
                                      <h3 class="text-xl font-semibold mb-2">File Preview Unavailable</h3>
                                      <p class="text-gray-600 mb-4">This file type cannot be previewed directly in the browser.</p>
                                      <a href="${filePath}" download class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                          </svg>
                                          Download Document
                                      </a>
                                  </div>
                              `;
                      }
                  } else if (hasGoogleDoc) {
                      document.getElementById('previewDocumentContent').innerHTML = `
                          <iframe src="https://docs.google.com/document/d/${googleDocId}/preview" width="100%" height="700px" class="border-0 rounded-lg"></iframe>
                      `;
                  } else {
                      document.getElementById('previewDocumentContent').innerHTML = `
                          <p class="text-gray-500">No document content available.</p>
                      `;
                  }
              })
              .catch(error => {
                  console.error('Error fetching document details:', error);
                  document.getElementById('previewDocumentContent').innerHTML = `
                      <div class="text-center p-6 bg-red-50 rounded-lg">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                          </svg>
                          <h3 class="text-xl font-semibold mb-2">Error Loading Document</h3>
                          <p class="text-gray-600">${error.message || 'Could not load document preview.'}</p>
                      </div>
                  `;
              });
      }

      // Helper function to format date
      function formatDate(dateString) {
          if (!dateString) return '';
          const date = new Date(dateString);
          return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      }
      
      // Helper function to escape HTML
      function escapeHtml(text) {
          const div = document.createElement('div');
          div.textContent = text;
          return div.innerHTML.replace(/\n/g, '<br>');
      }
      
      // Helper function to fix file paths
      function fixFilePath(path) {
          if (!path) return '';
          path = path.replace(/\\/g, "/");
          if (path.startsWith('http://') || path.startsWith('https://')) return path;
          if (path.startsWith('storage/')) path = '../' + path;
          else if (!path.startsWith('../') && !path.startsWith('/')) path = '../' + path;
          return path;
      }
      
      // Close preview modal handlers
      document.getElementById('closePreviewModal').addEventListener('click', function() {
          document.getElementById('documentPreviewModal').classList.add('hidden');
      });
      
      document.getElementById('closePreviewBtn').addEventListener('click', function() {
          document.getElementById('documentPreviewModal').classList.add('hidden');
      });

          // Update all document view links to use the preview function
        document.addEventListener('DOMContentLoaded', function() {
        // Update existing document view links
        document.querySelectorAll('.document-view-action').forEach(link => {
            const href = link.getAttribute('href');
            if (href && href.includes('view_document.php?id=')) {
                const documentId = href.split('id=')[1];
                link.setAttribute('href', 'javascript:void(0);');
                link.setAttribute('onclick', `previewDocument(${documentId})`);
            }
        });
        
        // Make sure this runs immediately to convert all links
        setTimeout(() => {
            document.querySelectorAll('.document-view-action').forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.includes('view_document.php?id=')) {
                    const documentId = href.split('id=')[1];
                    link.setAttribute('href', 'javascript:void(0);');
                    link.setAttribute('onclick', `previewDocument(${documentId})`);
                }
            });
            console.log('All document view links updated to use preview function');
        }, 500);
        
        // Attach print function to the new print button
        document.getElementById('printDocumentBtn').addEventListener('click', printDocument);
        });
        
        // Print only the previewed document, not the whole dashboard
        function printDocument() {
            const container = document.getElementById('previewDocumentContent');
            if (!container) {
                window.print();
                return;
            }

            // If the preview contains an iframe (Google Doc / PDF)
            const iframe = container.querySelector('iframe');
            if (iframe) {
                // Always route printing via a same-origin proxy page to avoid cross-origin blocks
                const doc = window.currentPreviewDoc || {};
                const docId = doc.document_id || '';
                // Use dedicated same-origin print page that renders real content
                const proxyUrl = `print_document.php?id=${docId}`;
                const hidden = document.createElement('iframe');
                hidden.style.position = 'fixed';
                hidden.style.right = '0';
                hidden.style.bottom = '0';
                hidden.style.width = '0';
                hidden.style.height = '0';
                hidden.style.border = '0';
                hidden.src = proxyUrl;
                hidden.onload = function() {
                    try {
                        hidden.contentWindow.focus();
                        setTimeout(() => hidden.contentWindow.print(), 300);
                    } catch (e) {
                        // As a last resort, open proxy in a new tab
                        window.open(proxyUrl, '_blank');
                    }
                    // Cleanup
                    setTimeout(() => hidden.remove(), 2000);
                };
                document.body.appendChild(hidden);
                return;
            }

            // Otherwise, open a clean print window containing only the document preview HTML
            const printWindow = window.open('', '_blank', 'width=900,height=1100');
            if (!printWindow) {
                window.print();
                return;
            }

            const html = `<!DOCTYPE html>
            <html>
            <head>
              <meta charset="utf-8">
              <title>Print Document</title>
              <style>
                html, body { margin: 0; padding: 0; }
                body { font-family: Arial, sans-serif; color: #000; background: #fff; }
                .print-root { padding: 20px; }
                @page { size: A4; margin: 15mm; }
                @media print { .no-print { display: none !important; } }
              </style>
            </head>
            <body>
              <div class="print-root">${container.innerHTML}</div>
            </body>
            </html>`;

            printWindow.document.open();
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 500);
        }
        
        // Memorandum Tracking Functions
        function showMemorandumDetails(documentId) {
            // Create modal for memorandum details
            const modal = document.createElement('div');
            modal.id = 'memorandumDetailsModal';
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-hidden">
                    <div class="px-6 py-4 bg-blue-50 border-b border-blue-100 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-blue-800">Memorandum Distribution Details</h3>
                        <button onclick="closeMemorandumDetails()" class="text-blue-600 hover:text-blue-800">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                        <div id="memorandumDetailsContent">
                            <div class="flex items-center justify-center p-8">
                                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                <span class="ml-2 text-gray-600">Loading memorandum details...</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Load memorandum details
            loadMemorandumDetails(documentId);
        }
        
        function closeMemorandumDetails() {
            const modal = document.getElementById('memorandumDetailsModal');
            if (modal) {
                modal.remove();
            }
        }
        
        function loadMemorandumDetails(documentId) {
            fetch(`../api/get_memorandum_progress.php?document_id=${documentId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayMemorandumDetails(data.data);
                } else {
                    document.getElementById('memorandumDetailsContent').innerHTML = `
                        <div class="text-center p-6 bg-red-50 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <h3 class="text-xl font-semibold mb-2">Error Loading Details</h3>
                            <p class="text-gray-600">${data.error || 'Could not load memorandum details.'}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading memorandum details:', error);
                document.getElementById('memorandumDetailsContent').innerHTML = `
                    <div class="text-center p-6 bg-red-50 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="text-xl font-semibold mb-2">Network Error</h3>
                        <p class="text-gray-600">Could not connect to server.</p>
                    </div>
                `;
            });
        }
        
        function displayMemorandumDetails(data) {
            const { progress, total_offices, read_offices, offices } = data;
            
            const officeList = offices.map(office => {
                const statusIcon = office.is_read 
                    ? '<svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>'
                    : '<svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg>';
                
                const readTime = office.read_at 
                    ? `<span class="text-xs text-gray-500">Read at ${new Date(office.read_at).toLocaleString()}</span>`
                    : '<span class="text-xs text-gray-400">Not read yet</span>';
                
                return `
                    <div class="flex items-center justify-between p-3 border-b border-gray-200">
                        <div class="flex items-center space-x-3">
                            ${statusIcon}
                            <div>
                                <div class="font-medium text-gray-900">${office.office_name}</div>
                                ${readTime}
                            </div>
                        </div>
                        <div class="text-sm text-gray-500">
                            ${office.is_read ? 'Read' : 'Pending'}
                        </div>
                    </div>
                `;
            }).join('');
            
            document.getElementById('memorandumDetailsContent').innerHTML = `
                <div class="mb-6">
                    <div class="grid grid-cols-3 gap-4 text-center mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">${total_offices}</div>
                            <div class="text-sm text-blue-500">Total Offices</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">${read_offices}</div>
                            <div class="text-sm text-green-500">Read Offices</div>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600">${progress}%</div>
                            <div class="text-sm text-purple-500">Progress</div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700">Distribution Progress</span>
                            <span class="text-sm text-gray-500">${progress}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div class="bg-blue-600 h-3 rounded-full transition-all duration-300" style="width: ${progress}%"></div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-3">Office Status</h4>
                    <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
                        ${officeList}
                    </div>
                </div>
            `;
        }
        
        // Initialize memorandum tracking for all memorandum cards
        function initializeMemorandumTracking() {
            document.querySelectorAll('.memorandum-card').forEach(card => {
                const documentId = card.dataset.documentId;
                const progressBar = card.querySelector('.memorandum-progress-bar');
                const progressText = card.querySelector('.memorandum-progress-text');
                const totalOffices = card.querySelector('.memorandum-total-offices');
                const readOffices = card.querySelector('.memorandum-read-offices');
                const progressPercent = card.querySelector('.memorandum-progress-percent');
                
                // Update progress every 30 seconds
                setInterval(() => {
                    fetch('../api/track_memorandum_view.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            document_id: documentId,
                            action: 'viewed'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const { progress, total_offices, read_offices } = data.data;
                            
                            if (progressBar) progressBar.style.width = progress + '%';
                            if (progressText) progressText.textContent = progress + '%';
                            if (totalOffices) totalOffices.textContent = total_offices;
                            if (readOffices) readOffices.textContent = read_offices;
                            if (progressPercent) progressPercent.textContent = progress + '%';
                        }
                    })
                    .catch(error => {
                        console.error('Error updating memorandum progress:', error);
                    });
                }, 30000);
            });
        }
        
        // Initialize memorandum tracking when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeMemorandumTracking();
        });
    </script>
  
    <!-- Include AI features -->
    <script src="../assets/js/document-ai-features.js"></script>
    <script src="../assets/js/document-ai-override.js"></script>
    <!-- Include Memorandum Tracking -->
    <script src="../assets/js/memorandum-tracking.js"></script>
</body>
</html>