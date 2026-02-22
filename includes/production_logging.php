<?php
/**
 * Production Logging System
 * Handles comprehensive logging for production environment
 */

class ProductionLogger {
    private $logPath;
    private $maxLogSize = 10 * 1024 * 1024; // 10MB
    private $maxLogFiles = 5;
    
    public function __construct($logPath = null) {
        $this->logPath = $logPath ?: realpath(dirname(__FILE__) . '/../logs');
        
        if (!file_exists($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }
    
    /**
     * Log system events
     */
    public function logSystem($level, $message, $context = []) {
        $this->writeLog('system', $level, $message, $context);
    }
    
    /**
     * Log user actions
     */
    public function logUserAction($userId, $action, $details = '', $documentId = null, $officeId = null) {
        $context = [
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'document_id' => $documentId,
            'office_id' => $officeId,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
        
        $this->writeLog('user_actions', 'info', "User Action: $action", $context);
    }
    
    /**
     * Log security events
     */
    public function logSecurity($event, $details = '', $severity = 'warning') {
        $context = [
            'event' => $event,
            'details' => $details,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
        ];
        
        $this->writeLog('security', $severity, "Security Event: $event", $context);
    }
    
    /**
     * Log database errors
     */
    public function logDatabase($query, $error, $params = []) {
        $context = [
            'query' => $query,
            'error' => $error,
            'params' => $params,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];
        
        $this->writeLog('database', 'error', 'Database Error', $context);
    }
    
    /**
     * Log API calls
     */
    public function logAPI($endpoint, $method, $status, $responseTime, $requestData = null) {
        $context = [
            'endpoint' => $endpoint,
            'method' => $method,
            'status' => $status,
            'response_time' => $responseTime,
            'request_data' => $requestData,
            'ip_address' => $this->getClientIP()
        ];
        
        $this->writeLog('api', 'info', "API Call: $method $endpoint", $context);
    }
    
    /**
     * Write log entry
     */
    private function writeLog($type, $level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context
        ];
        
        $logFile = $this->logPath . DIRECTORY_SEPARATOR . $type . '_' . date('Y-m-d') . '.log';
        
        // Rotate log if too large
        if (file_exists($logFile) && filesize($logFile) > $this->maxLogSize) {
            $this->rotateLog($logFile);
        }
        
        $logLine = json_encode($logEntry) . "\n";
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Rotate log files
     */
    private function rotateLog($logFile) {
        $baseName = pathinfo($logFile, PATHINFO_FILENAME);
        $extension = pathinfo($logFile, PATHINFO_EXTENSION);
        $directory = dirname($logFile);
        
        // Move existing files
        for ($i = $this->maxLogFiles - 1; $i > 0; $i--) {
            $oldFile = $directory . DIRECTORY_SEPARATOR . $baseName . '.' . $i . '.' . $extension;
            $newFile = $directory . DIRECTORY_SEPARATOR . $baseName . '.' . ($i + 1) . '.' . $extension;
            
            if (file_exists($oldFile)) {
                if ($i === $this->maxLogFiles - 1) {
                    unlink($oldFile); // Delete oldest
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }
        
        // Move current log
        $newFile = $directory . DIRECTORY_SEPARATOR . $baseName . '.1.' . $extension;
        rename($logFile, $newFile);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    /**
     * Get system statistics
     */
    public function getSystemStats() {
        $stats = [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'load_average' => sys_getloadavg(),
            'disk_usage' => disk_free_space($this->logPath),
            'log_files' => count(glob($this->logPath . '/*.log'))
        ];
        
        return $stats;
    }
}

// Global logger instance
$productionLogger = new ProductionLogger();
?>
