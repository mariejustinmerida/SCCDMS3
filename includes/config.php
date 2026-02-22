<?php
// Production-ready database configuration
// Use environment variables for security, fallback to live server credentials.
// Supports DATABASE_URL (e.g. from DigitalOcean App Platform) or separate DB_* vars.
$db_host = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'scc_dms';

$db_port = 3306;

$database_url = getenv('DATABASE_URL');
if (!empty($database_url)) {
    // Parse DATABASE_URL (e.g. mysql://user:pass@host:port/dbname)
    $url = parse_url($database_url);
    if (!empty($url['host'])) {
        $db_host = $url['host'];
        $db_username = isset($url['user']) ? $url['user'] : $db_username;
        $db_password = isset($url['pass']) ? $url['pass'] : $db_password;
        $db_name = isset($url['path']) ? ltrim($url['path'], '/') : $db_name;
        if (($q = strpos($db_name, '?')) !== false) {
            $db_name = substr($db_name, 0, $q);
        }
        if (!empty($url['port'])) {
            $db_port = (int) $url['port'];
        }
    }
} else {
    $db_host = getenv('DB_HOST') ?: $db_host;
    $db_username = getenv('DB_USERNAME') ?: $db_username;
    $db_password = getenv('DB_PASSWORD') ?: $db_password;
    $db_name = getenv('DB_NAME') ?: $db_name;
    $port_env = getenv('DB_PORT');
    if ($port_env !== false && $port_env !== '') {
        $db_port = (int) $port_env;
    }
}

// Security settings
$conn = null;

try {
    // Create connection with proper charset and options
    $conn = new mysqli($db_host, $db_username, $db_password, $db_name, $db_port);
    
    // Set charset to prevent SQL injection
    $conn->set_charset("utf8mb4");
    
    // Set SQL mode for better data integrity
    $conn->query("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
    
    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        
        // If this is an API request, return JSON error
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database connection failed']);
            exit;
        } else {
            // For regular pages, show generic error
            die("System temporarily unavailable. Please try again later.");
        }
    }
    
    // Set connection timeout
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    
    // If this is an API request, return JSON error
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error occurred']);
        exit;
    } else {
        // For regular pages, show generic error
        die("System temporarily unavailable. Please try again later.");
    }
}

// Security headers for production
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    // Allow same-origin iframes (needed for print proxy). Still blocks third-party framing.
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Session security
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    session_start();
}
?>
