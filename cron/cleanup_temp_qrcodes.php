<?php
/**
 * Cleanup Temporary QR Codes
 * 
 * This script removes temporary QR code files that are older than 30 days
 * It should be run as a scheduled task (cron job)
 */

// Define the directory containing temporary QR codes
$qr_directory = __DIR__ . '/../temp_qrcodes/';

// Log file for cleanup operations
$log_file = __DIR__ . '/../logs/qr_cleanup.log';

// Ensure the logs directory exists
if (!file_exists(__DIR__ . '/../logs/')) {
    mkdir(__DIR__ . '/../logs/', 0777, true);
}

// Log function
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Start cleanup process
log_message('Starting QR code cleanup process');

// Check if the QR directory exists
if (!file_exists($qr_directory)) {
    log_message("Error: QR directory not found at {$qr_directory}");
    exit;
}

// Get all PNG files in the directory
$files = glob($qr_directory . '*.png');
$total_files = count($files);
log_message("Found {$total_files} QR code files");

// Set the cutoff time (30 days ago)
$cutoff_time = time() - (30 * 24 * 60 * 60);
$deleted_count = 0;

// Process each file
foreach ($files as $file) {
    // Get the file's last modification time
    $file_time = filemtime($file);
    
    // If the file is older than the cutoff time, delete it
    if ($file_time < $cutoff_time) {
        if (unlink($file)) {
            $deleted_count++;
            log_message("Deleted: " . basename($file));
        } else {
            log_message("Failed to delete: " . basename($file));
        }
    }
}

log_message("Cleanup completed. Deleted {$deleted_count} files.");
