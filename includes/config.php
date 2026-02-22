<?php
// Database configuration (local + DigitalOcean App Platform).
//
// DigitalOcean: either (a) link DB in App Platform and set DB_NAME=defaultdb, or (b) set:
//   DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME=defaultdb, DB_PORT=25060
//   (sslmode=REQUIRED is handled automatically for *.ondigitalocean.com hosts.)
// Local: DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME (or .env).
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_username = getenv('DB_USERNAME') ?: 'root';
$db_password = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_NAME') ?: 'scc_dms';
$db_port = 3306;

$database_url = getenv('DATABASE_URL');
if (!empty($database_url)) {
    $url = @parse_url($database_url);
    if (!empty($url['host'])) {
        $db_host = $url['host'];
        $db_username = isset($url['user']) ? rawurldecode($url['user']) : $db_username;
        $db_password = isset($url['pass']) ? rawurldecode($url['pass']) : $db_password;
        $path = isset($url['path']) ? ltrim($url['path'], '/') : '';
        if ($path !== '') {
            $db_name = (strpos($path, '?') !== false) ? strstr($path, '?', true) : $path;
        }
        if (!empty($url['port'])) {
            $db_port = (int) $url['port'];
        }
    }
    $db_name_override = getenv('DB_NAME');
    if ($db_name_override !== false && $db_name_override !== '') {
        $db_name = $db_name_override;
    }
} else {
    $port_env = getenv('DB_PORT');
    if ($port_env !== false && $port_env !== '') {
        $db_port = (int) $port_env;
    }
}

// Force TCP (avoid Unix socket "No such file or directory")
if ($db_host === 'localhost' || $db_host === '') {
    $db_host = '127.0.0.1';
}

$conn = null;
$is_do_host = (strpos($db_host, 'ondigitalocean.com') !== false);
$use_ssl_ca = getenv('DB_SSL_CA');
$use_ssl_ca = ($use_ssl_ca !== false && $use_ssl_ca !== '' && is_readable($use_ssl_ca));

try {
    // Optional: explicit CA cert path (e.g. DB_SSL_CA=/path/to/ca-certificate.crt)
    if ($use_ssl_ca) {
        $conn = mysqli_init();
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        $conn->ssl_set(null, null, getenv('DB_SSL_CA'), null, null);
        $ok = $conn->real_connect($db_host, $db_username, $db_password, $db_name, $db_port, null, MYSQLI_CLIENT_SSL);
        if (!$ok) {
            $conn = null;
        }
    }
    // DigitalOcean MySQL (sslmode=REQUIRED): use SSL without cert verification (no ssl_set to avoid "No such file or directory")
    if (!$conn && $is_do_host && defined('MYSQLI_CLIENT_SSL') && defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT')) {
        $conn = mysqli_init();
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        $ok = @$conn->real_connect($db_host, $db_username, $db_password, $db_name, $db_port, null, MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT);
        if (!$ok) {
            $conn = null;
        }
    }
    if (!$conn) {
        $conn = new mysqli($db_host, $db_username, $db_password, $db_name, $db_port);
    }
    // If plain connection failed (e.g. "SSL required"), retry with SSL
    if ($conn && $conn->connect_error && !$is_do_host && defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT')) {
        $conn->close();
        $conn = mysqli_init();
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        $ok = @$conn->real_connect($db_host, $db_username, $db_password, $db_name, $db_port, null, MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT);
        if (!$ok) {
            $conn = new mysqli($db_host, $db_username, $db_password, $db_name, $db_port);
        }
    }
    
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
