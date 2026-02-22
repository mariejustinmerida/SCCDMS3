<?php
/**
 * Google Docs Handler
 * 
 * This file handles Google Docs operations like creating, editing, and retrieving documents.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/google_api_config.php';
require_once __DIR__ . '/google_auth_handler.php';

class GoogleDocsHandler {
    private $client;
    private $driveService;
    private $docsService;
    
    /**
     * Constructor - initializes the Google services
     * 
     * @param Google_Client $client The authenticated Google client
     */
    public function __construct($client) {
        $this->client = $client;
        $this->driveService = new Google_Service_Drive($client);
        $this->docsService = new Google_Service_Docs($client);
    }
    
    /**
     * Create a new Google Doc
     * 
     * @param string $title The document title
     * @param string $content Optional HTML content to populate the document with
     * @return array Document info including ID and URL
     */
    public function createDocument($title, $content = '') {
        // Create a new Google Doc
        $doc = new Google_Service_Drive_DriveFile([
            'name' => $title,
            'mimeType' => 'application/vnd.google-apps.document'
        ]);
        
        $file = $this->driveService->files->create($doc, [
            'fields' => 'id,webViewLink,webContentLink'
        ]);
        
        $documentId = $file->getId();
        
        // If content is provided, update the document with it
        if (!empty($content)) {
            $this->updateDocumentContent($documentId, $content);
        }
        
        return [
            'id' => $documentId,
            'url' => $file->getWebViewLink(),
            'edit_url' => 'https://docs.google.com/document/d/' . $documentId . '/edit',
            'title' => $title
        ];
    }
    
    /**
     * Update a Google Doc's content
     * 
     * @param string $documentId The Google Doc ID
     * @param string $content HTML content to update the document with
     * @return bool Whether the update was successful
     */
    public function updateDocumentContent($documentId, $content) {
        try {
            // Convert HTML to Google Docs format
            // This is a simplified approach - for complex documents, you might need more sophisticated conversion
            
            // First, clear the document
            $requests = [
                new Google_Service_Docs_Request([
                    'deleteContentRange' => [
                        'range' => [
                            'startIndex' => 1,
                            'endIndex' => 1000000 // A large number to ensure all content is deleted
                        ]
                    ]
                ])
            ];
            
            $batchUpdateRequest = new Google_Service_Docs_BatchUpdateDocumentRequest([
                'requests' => $requests
            ]);
            
            $this->docsService->documents->batchUpdate($documentId, $batchUpdateRequest);
            
            // Now insert the new content
            // For simple text, we can use the Drive API to update the file directly
            $this->driveService->files->update(
                $documentId,
                new Google_Service_Drive_DriveFile(),
                [
                    'uploadType' => 'media',
                    'mimeType' => 'text/html',
                    'data' => $content
                ]
            );
            
            return true;
        } catch (Exception $e) {
            error_log('Error updating Google Doc content: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get a Google Doc's content
     * 
     * @param string $documentId The Google Doc ID
     * @return string The document content as HTML
     */
    public function getDocumentContent($documentId) {
        try {
            // Export the document as HTML
            $response = $this->driveService->files->export($documentId, 'text/html', [
                'alt' => 'media'
            ]);
            
            return $response->getBody()->getContents();
        } catch (Exception $e) {
            error_log('Error getting Google Doc content: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Get a Google Doc's metadata
     * 
     * @param string $documentId The Google Doc ID
     * @return array|null The document metadata or null if not found
     */
    public function getDocumentMetadata($documentId) {
        try {
            $file = $this->driveService->files->get($documentId, [
                'fields' => 'id,name,webViewLink,modifiedTime'
            ]);
            
            return [
                'id' => $file->getId(),
                'title' => $file->getName(),
                'url' => $file->getWebViewLink(),
                'edit_url' => 'https://docs.google.com/document/d/' . $documentId . '/edit',
                'modified_time' => $file->getModifiedTime()
            ];
        } catch (Exception $e) {
            error_log('Error getting Google Doc metadata: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Delete a Google Doc
     * 
     * @param string $documentId The Google Doc ID
     * @return bool Whether the deletion was successful
     */
    public function deleteDocument($documentId) {
        try {
            $this->driveService->files->delete($documentId);
            return true;
        } catch (Exception $e) {
            error_log('Error deleting Google Doc: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate an embed URL for a Google Doc
     * 
     * @param string $documentId The Google Doc ID
     * @param bool $editable Whether the embedded document should be editable
     * @return string The embed URL
     */
    public function getEmbedUrl($documentId, $editable = true) {
        $mode = $editable ? 'edit' : 'preview';
        return "https://docs.google.com/document/d/$documentId/e/$mode?embedded=true";
    }
    
    /**
     * Map a document from SCCDMS database to Google Docs
     * 
     * @param int $documentId The SCCDMS document ID
     * @param string $googleDocId The Google Doc ID
     * @param int $userId The user ID who owns the document
     * @return bool Whether the mapping was successful
     */
    public function mapDocumentToGoogleDoc($documentId, $googleDocId, $userId) {
        global $conn;
        
        // Check if google_docs_mapping table exists, create if not
        $checkTable = "SHOW TABLES LIKE 'google_docs_mapping'";
        $tableExists = $conn->query($checkTable);
        
        if ($tableExists->num_rows == 0) {
            // Create the table
            $createTable = "CREATE TABLE google_docs_mapping (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                document_id INT(11) NOT NULL,
                google_doc_id VARCHAR(255) NOT NULL,
                user_id INT(11) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY document_id (document_id)
            )";
            
            if (!$conn->query($createTable)) {
                error_log("Error creating google_docs_mapping table: " . $conn->error);
                return false;
            }
        }
        
        // Prepare data
        $documentId = (int)$documentId;
        $googleDocId = $conn->real_escape_string($googleDocId);
        $userId = (int)$userId;
        
        // Check if mapping already exists
        $checkMapping = "SELECT id FROM google_docs_mapping WHERE document_id = $documentId";
        $result = $conn->query($checkMapping);
        
        if ($result->num_rows > 0) {
            // Update existing mapping
            $updateMapping = "UPDATE google_docs_mapping SET 
                google_doc_id = '$googleDocId',
                user_id = $userId
                WHERE document_id = $documentId";
            
            return $conn->query($updateMapping);
        } else {
            // Insert new mapping
            $insertMapping = "INSERT INTO google_docs_mapping (document_id, google_doc_id, user_id)
                VALUES ($documentId, '$googleDocId', $userId)";
            
            return $conn->query($insertMapping);
        }
    }
    
    /**
     * Get Google Doc ID for a SCCDMS document
     * 
     * @param int $documentId The SCCDMS document ID
     * @return string|null The Google Doc ID or null if not found
     */
    public function getGoogleDocIdForDocument($documentId) {
        global $conn;
        
        $documentId = (int)$documentId;
        $query = "SELECT google_doc_id FROM google_docs_mapping WHERE document_id = $documentId";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['google_doc_id'];
        }
        
        return null;
    }
}
