<?php
/**
 * Database Optimization and Connection Pooling
 * Handles database performance for multiple users
 */

class DatabaseOptimizer {
    private $conn;
    private $queryCache = [];
    private $maxConnections = 20;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->optimizeConnection();
    }
    
    /**
     * Optimize database connection for production
     */
    private function optimizeConnection() {
        // Set connection limits
        $this->conn->query("SET SESSION max_connections = {$this->maxConnections}");
        
        // Optimize query cache
        $this->conn->query("SET SESSION query_cache_size = 268435456"); // 256MB
        
        // Set timeouts
        $this->conn->query("SET SESSION wait_timeout = 28800"); // 8 hours
        $this->conn->query("SET SESSION interactive_timeout = 28800");
        
        // Enable query logging for monitoring
        $this->conn->query("SET SESSION general_log = 'ON'");
    }
    
    /**
     * Execute prepared statement with error handling
     */
    public function executePrepared($query, $params = [], $types = '') {
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            error_log("Prepare failed: " . $this->conn->error . " - Query: " . $query);
            throw new Exception("Database prepare error");
        }
        
        if (!empty($params)) {
            if (empty($types)) {
                $types = str_repeat('s', count($params)); // Default to string
            }
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error . " - Query: " . $query);
            $stmt->close();
            throw new Exception("Database execute error");
        }
        
        return $stmt;
    }
    
    /**
     * Get results with pagination
     */
    public function getPaginatedResults($query, $params = [], $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        // Add LIMIT and OFFSET to query
        $paginatedQuery = $query . " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->executePrepared($paginatedQuery, $params, 's' . str_repeat('s', count($params) - 2) . 'ii');
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM (" . $query . ") as count_table";
        $countStmt = $this->executePrepared($countQuery, array_slice($params, 0, -2));
        $countResult = $countStmt->get_result();
        $total = $countResult->fetch_assoc()['total'];
        $countStmt->close();
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Cache frequently used queries
     */
    public function getCachedResult($key, $query, $params = [], $ttl = 300) {
        if (isset($this->queryCache[$key])) {
            $cached = $this->queryCache[$key];
            if (time() - $cached['timestamp'] < $ttl) {
                return $cached['data'];
            }
        }
        
        $stmt = $this->executePrepared($query, $params);
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        
        $this->queryCache[$key] = [
            'data' => $data,
            'timestamp' => time()
        ];
        
        return $data;
    }
    
    /**
     * Clear query cache
     */
    public function clearCache($key = null) {
        if ($key) {
            unset($this->queryCache[$key]);
        } else {
            $this->queryCache = [];
        }
    }
    
    /**
     * Monitor database performance
     */
    public function getPerformanceStats() {
        $stats = [];
        
        // Get connection info
        $result = $this->conn->query("SHOW STATUS LIKE 'Connections'");
        if ($row = $result->fetch_assoc()) {
            $stats['connections'] = $row['Value'];
        }
        
        // Get query cache stats
        $result = $this->conn->query("SHOW STATUS LIKE 'Qcache%'");
        while ($row = $result->fetch_assoc()) {
            $stats['query_cache'][$row['Variable_name']] = $row['Value'];
        }
        
        // Get slow queries
        $result = $this->conn->query("SHOW STATUS LIKE 'Slow_queries'");
        if ($row = $result->fetch_assoc()) {
            $stats['slow_queries'] = $row['Value'];
        }
        
        return $stats;
    }
    
    /**
     * Create database indexes for better performance
     */
    public function createIndexes() {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_documents_status ON documents(status)",
            "CREATE INDEX IF NOT EXISTS idx_documents_creator ON documents(creator_id)",
            "CREATE INDEX IF NOT EXISTS idx_documents_created_at ON documents(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_document_workflow_doc_office ON document_workflow(document_id, office_id)",
            "CREATE INDEX IF NOT EXISTS idx_document_workflow_status ON document_workflow(status)",
            "CREATE INDEX IF NOT EXISTS idx_document_logs_doc_id ON document_logs(document_id)",
            "CREATE INDEX IF NOT EXISTS idx_document_logs_user_id ON document_logs(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_users_office_id ON users(office_id)",
            "CREATE INDEX IF NOT EXISTS idx_users_role_id ON users(role_id)"
        ];
        
        foreach ($indexes as $index) {
            try {
                $this->conn->query($index);
            } catch (Exception $e) {
                error_log("Index creation failed: " . $e->getMessage());
            }
        }
    }
}
?>
