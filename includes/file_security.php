<?php
/**
 * File Security Handler
 * Handles secure file uploads and storage for production environment
 */

class FileSecurityHandler {
    private $allowedTypes = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'txt' => ['text/plain'],
        'rtf' => ['application/rtf', 'text/rtf']
    ];
    
    private $maxFileSize = 10 * 1024 * 1024; // 10MB
    private $uploadPath;
    private $tempPath;
    
    public function __construct($uploadPath = null) {
        $this->uploadPath = $uploadPath ?: realpath(dirname(__FILE__) . '/../storage/uploads');
        $this->tempPath = realpath(dirname(__FILE__) . '/../storage/temp');
        
        // Create directories if they don't exist
        $this->createDirectories();
    }
    
    private function createDirectories() {
        $directories = [$this->uploadPath, $this->tempPath];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new Exception("Failed to create directory: $dir");
                }
            }
        }
    }
    
    /**
     * Validate uploaded file
     */
    public function validateFile($file) {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload failed";
            return $errors;
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            $errors[] = "File size exceeds maximum allowed size of " . ($this->maxFileSize / 1024 / 1024) . "MB";
        }
        
        // Get file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check if extension is allowed
        if (!array_key_exists($extension, $this->allowedTypes)) {
            $errors[] = "File type not allowed. Allowed types: " . implode(', ', array_keys($this->allowedTypes));
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes[$extension])) {
            $errors[] = "File MIME type does not match extension";
        }
        
        // Check for malicious content
        if ($this->containsMaliciousContent($file['tmp_name'])) {
            $errors[] = "File contains potentially malicious content";
        }
        
        return $errors;
    }
    
    /**
     * Check for malicious content in file
     */
    private function containsMaliciousContent($filePath) {
        $content = file_get_contents($filePath, false, null, 0, 1024); // Read first 1KB
        
        // Check for common malicious patterns
        $maliciousPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/eval\(/i',
            '/exec\(/i',
            '/system\(/i'
        ];
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate secure filename
     */
    public function generateSecureFilename($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize basename
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        $basename = substr($basename, 0, 50); // Limit length
        
        // Generate unique filename
        $uniqueId = uniqid() . '_' . bin2hex(random_bytes(8));
        
        return $uniqueId . '_' . $basename . '.' . $extension;
    }
    
    /**
     * Move uploaded file to secure location
     */
    public function moveUploadedFile($file, $filename = null) {
        if (!$filename) {
            $filename = $this->generateSecureFilename($file['name']);
        }
        
        $destination = $this->uploadPath . DIRECTORY_SEPARATOR . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception("Failed to move uploaded file");
        }
        
        // Set secure permissions
        chmod($destination, 0644);
        
        return [
            'filename' => $filename,
            'path' => $destination,
            'relative_path' => 'storage/uploads/' . $filename,
            'size' => filesize($destination)
        ];
    }
    
    /**
     * Clean up old temporary files
     */
    public function cleanupTempFiles($maxAge = 3600) { // 1 hour
        $files = glob($this->tempPath . '/*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get file info safely
     */
    public function getFileInfo($filePath) {
        if (!file_exists($filePath)) {
            return null;
        }
        
        return [
            'size' => filesize($filePath),
            'modified' => filemtime($filePath),
            'mime_type' => mime_content_type($filePath),
            'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION))
        ];
    }
}
?>
