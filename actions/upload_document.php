<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Create absolute path for upload directory
        $base_dir = realpath(dirname(dirname(__FILE__))); // Get the absolute path to the project root
        $upload_dir = $base_dir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        $allowed_types = array('pdf', 'doc', 'docx');
        $response = array('success' => false, 'message' => '');
        
        // Debug information
        error_log("Base directory: " . $base_dir);
        error_log("Upload directory: " . $upload_dir);
        
        // Create storage directory if it doesn't exist
        $storage_dir = $base_dir . DIRECTORY_SEPARATOR . 'storage';
        if (!file_exists($storage_dir)) {
            if (!mkdir($storage_dir, 0777)) {
                throw new Exception("Failed to create storage directory: " . $storage_dir);
            }
            chmod($storage_dir, 0777);
            error_log("Created storage directory: " . $storage_dir);
        }
        
        // Create uploads directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777)) {
                throw new Exception("Failed to create uploads directory: " . $upload_dir);
            }
            chmod($upload_dir, 0777);
            error_log("Created uploads directory: " . $upload_dir);
        }
        
        $file = $_FILES['document'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_ext, $allowed_types)) {
            // Generate unique filename
            $unique_id = uniqid();
            $original_filename = pathinfo($file['name'], PATHINFO_FILENAME);
            // Sanitize the original filename to remove special characters
            $original_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $original_filename);
            $new_filename = $unique_id . '_' . $original_filename . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                throw new Exception("Failed to move uploaded file to: " . $upload_path);
            }
            error_log("Uploaded file to: " . $upload_path);
            
            $response['success'] = true;
            $response['message'] = "Document uploaded successfully!";
            $response['filename'] = $new_filename;
            
            // If this is a form submission from compose.php, save to database
            if (isset($_POST['title']) && isset($_POST['type_id'])) {
                // This is from compose.php - save to database
                $title = $_POST['title'];
                $type_id = $_POST['type_id'];
                $description = isset($_POST['description']) ? $_POST['description'] : '';
                $creator_id = $_SESSION['user_id'];
                $status = 'pending';
                
                // Save document info to database with relative path
                $sql = "INSERT INTO documents (title, type_id, creator_id, file_path, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $file_path = 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $new_filename;
                $stmt->bind_param("siiss", $title, $type_id, $creator_id, $file_path, $status);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error saving document to database: " . $conn->error);
                }
                
                $document_id = $conn->insert_id;
                $response['document_id'] = $document_id;
                
                // Auto classify with AI (non-blocking best-effort)
                try {
                    require_once __DIR__ . '/../includes/config.php';
                    // Extract plain text content for quick classification
                    $plain = '';
                    $ext = strtolower(pathinfo($new_filename, PATHINFO_EXTENSION));
                    $abs_path = realpath(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $new_filename;
                    if ($ext === 'txt') {
                        $plain = @file_get_contents($abs_path) ?: '';
                    } elseif ($ext === 'pdf') {
                        if (function_exists('extractPdfContent')) { $plain = extractPdfContent($abs_path); }
                    } elseif ($ext === 'docx' || $ext === 'doc') {
                        if (function_exists('extractDocxContent')) { $plain = extractDocxContent($abs_path); }
                    } elseif ($ext === 'html' || $ext === 'htm') {
                        $plain = strip_tags(@file_get_contents($abs_path) ?: '');
                    }

                    if (!empty($plain)) {
                        // Call Gemini for classification (compact prompt)
                        $apiKey = getenv('GEMINI_API_KEY');
                        if (empty($apiKey)) {
                            $stmtKey = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name='gemini_api_key' LIMIT 1");
                            if ($stmtKey && $stmtKey->execute()) { $resKey = $stmtKey->get_result(); if ($rowKey = $resKey->fetch_assoc()) { $apiKey = $rowKey['setting_value']; } }
                        }

                        if (!empty($apiKey)) {
                            $model = 'gemini-1.5-flash';
                            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);
                            $payload = [
                                'systemInstruction' => [ 'parts' => [[ 'text' => 'Classify the document into one short category from this set: HR memo, financial request, student communication, administrative memo, academic letter, procurement, facilities request, announcement, other. Return JSON: {"classification":"<one>"} only.' ]] ],
                                'contents' => [[ 'role' => 'user', 'parts' => [[ 'text' => substr($plain, 0, 6000) ]] ]],
                                'generationConfig' => [ 'temperature' => 0.1, 'maxOutputTokens' => 128, 'response_mime_type' => 'application/json' ]
                            ];
                            $ch = curl_init($url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                            $resp = curl_exec($ch);
                            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            if ($http === 200 && $resp) {
                                $decoded = json_decode($resp, true);
                                $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
                                if ($text) {
                                    // Strip fences if any
                                    $text = preg_replace('/^```[a-zA-Z]*\n|\n```$/', '', trim($text));
                                    $j = json_decode($text, true);
                                    $classification = $j['classification'] ?? null;
                                    if ($classification) {
                                        // Save to logs
                                        $log = $conn->prepare("INSERT INTO document_logs (document_id, user_id, action, comment) VALUES (?, ?, 'ai_classified', ?)");
                                        if ($log) { $cmt = 'AI category: ' . $classification; $log->bind_param('iis', $document_id, $creator_id, $cmt); $log->execute(); $log->close(); }
                                        // Optional: map to document_types by name
                                        $q = $conn->prepare("SELECT type_id FROM document_types WHERE LOWER(type_name) LIKE CONCAT('%', ?, '%') LIMIT 1");
                                        if ($q) { $lc = strtolower($classification); $q->bind_param('s', $lc); $q->execute(); $res = $q->get_result(); if ($row = $res->fetch_assoc()) { $tid = (int)$row['type_id']; $up = $conn->prepare("UPDATE documents SET type_id=? WHERE document_id=?"); if ($up){ $up->bind_param('ii',$tid,$document_id); $up->execute(); $up->close(); } } $q->close(); }
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('Auto classification skipped: ' . $e->getMessage());
                }

                // Process workflow steps if present
                if (isset($_POST['workflow']) && is_array($_POST['workflow'])) {
                    $workflow = $_POST['workflow'];
                    $recipient_types = isset($_POST['recipient_types']) ? $_POST['recipient_types'] : [];
                    $user_workflow = isset($_POST['user_workflow']) ? $_POST['user_workflow'] : [];
                    
                    // Save workflow steps to document_workflow table
                    foreach ($workflow as $index => $office_id) {
                        $step_order = $index + 1;
                        $recipient_type = isset($recipient_types[$index]) ? $recipient_types[$index] : 'office';
                        $user_id = ($recipient_type == 'person' && isset($user_workflow[$index])) ? $user_workflow[$index] : null;
                        
                        // Set status: first step is 'CURRENT', others are 'PENDING'
                        $status = ($index === 0) ? 'CURRENT' : 'PENDING';
                        
                        $workflow_sql = "INSERT INTO document_workflow (document_id, office_id, user_id, recipient_type, step_order, status) 
                                        VALUES (?, ?, ?, ?, ?, ?)";
                        $workflow_stmt = $conn->prepare($workflow_sql);
                        $workflow_stmt->bind_param("iiisis", $document_id, $office_id, $user_id, $recipient_type, $step_order, $status);
                        
                        if (!$workflow_stmt->execute()) {
                            throw new Exception("Error saving workflow step: " . $conn->error);
                        }
                    }
                    
                    // Get the first workflow step's office_id to set as current_step in documents table
                    $current_step_office_id = $workflow[0];
                    
                    // Update document with current step office_id
                    $update_sql = "UPDATE documents SET current_step = ? WHERE document_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ii", $current_step_office_id, $document_id);
                    
                    if (!$update_stmt->execute()) {
                        throw new Exception("Error updating document workflow: " . $conn->error);
                    }
                }
                
                // Handle redirect URL if provided
                if (isset($_POST['redirect_url'])) {
                    $redirect_url = $_POST['redirect_url'];
                    // Append folder parameter if not already present
                    if (strpos($redirect_url, 'folder=') === false) {
                        $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . 'folder=all';
                    }
                    $response['redirect'] = $redirect_url;
                    error_log("Redirect URL set to: " . $redirect_url);
                } else {
                    // Default redirect to documents page
                    $response['redirect'] = '../pages/dashboard.php?page=documents&folder=all';
                    error_log("Using default redirect URL");
                }
            }
        } else {
            throw new Exception("Invalid file type. Only PDF, DOC, and DOCX files are allowed.");
        }
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
        error_log("Error in upload_document.php: " . $e->getMessage());
    }
    
    // Check if we should redirect or return JSON
    if (isset($response['redirect']) && !isset($_GET['ajax'])) {
        // Perform direct redirect for traditional form submission
        error_log("Redirecting to: " . $response['redirect']);
        
        // Always redirect to a known working location
        if ($response['success']) {
            // Add debug redirect path logging
            error_log("Success redirect to documents page");
            
            // Use absolute URL path for consistent redirection
            $redirect_url = '/SCCDMS2/pages/dashboard.php?page=documents&folder=all&success=upload';
            header('Location: ' . $redirect_url);
            error_log("Header set to: " . $redirect_url);
        } else {
            // Add debug error path logging
            error_log("Error redirect to documents page: " . $response['message']);
            
            // Use absolute URL path for consistent redirection
            $redirect_url = '/SCCDMS2/pages/dashboard.php?page=documents&folder=all&error=' . urlencode($response['message']);
            header('Location: ' . $redirect_url);
            error_log("Header set to: " . $redirect_url);
        }
        exit();
    } else {
        // Return JSON response for AJAX requests
        echo json_encode($response);
        exit();
    }
}
?>
