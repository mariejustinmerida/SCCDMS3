<?php
// Test if error logging works
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/ai_errors.log');

echo "Testing error logging...\n";

// Test 1: Direct error_log
error_log("TEST: Direct error_log call at " . date('Y-m-d H:i:s'));

// Test 2: Create log file if it doesn't exist
$logFile = __DIR__ . '/logs/ai_errors.log';
if (!file_exists($logFile)) {
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    file_put_contents($logFile, "Log file created at " . date('Y-m-d H:i:s') . "\n");
    echo "Created log file: $logFile\n";
} else {
    echo "Log file exists: $logFile\n";
}

// Test 3: Write directly to log
file_put_contents($logFile, "Direct write test at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Test 4: Check if we can read the log
if (file_exists($logFile)) {
    echo "Log file contents:\n";
    echo file_get_contents($logFile);
} else {
    echo "Log file does not exist!\n";
}

echo "\nDone. Check the log file manually.\n";
?>
