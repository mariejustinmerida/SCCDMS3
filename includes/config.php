<?php
// Production-ready database configuration
// Use environment variables for security, fallback to live server credentials

// Load .env file if it exists (for easier configuration)
if (file_exists(__DIR__ . '/../.env')) {
    $envFile = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envFile as $line) {
        // Skip comments and empty lines
        if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
            continue;
        }
        // Parse KEY=VALUE format
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            $value = trim($value, '"\'');
            // Set environment variable if not already set
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_username = getenv('DB_USERNAME') ?: 'root';
$db_password = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_NAME') ?: 'scc_dms';

// Application timezone (defaults to Asia/Manila but can be overridden via APP_TIMEZONE)
$app_timezone_name = getenv('APP_TIMEZONE') ?: 'Asia/Manila';
try {
    $app_timezone = new DateTimeZone($app_timezone_name);
} catch (Exception $e) {
    error_log("Invalid APP_TIMEZONE '{$app_timezone_name}', falling back to UTC.");
    $app_timezone = new DateTimeZone('UTC');
}
date_default_timezone_set($app_timezone->getName());

// Security settings
$conn = null;

try {
    // Create connection with proper charset and options
    $conn = new mysqli($db_host, $db_username, $db_password, $db_name);
    
    // Ensure MySQL session uses the same timezone as PHP
    $conn->query("SET time_zone = '" . (new DateTime('now', $app_timezone))->format('P') . "'");
    
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
