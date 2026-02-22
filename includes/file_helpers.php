<?php
/**
 * Helper functions for file operations
 */

/**
 * Extract the original filename from a stored filename
 * Format: uniqueid_originalfilename.ext
 * 
 * @param string $filename The stored filename
 * @return string The original filename or the stored filename if no original name is found
 */
function getOriginalFilename($filename) {
    // Check if the filename follows our format (uniqueid_originalname.ext)
    if (preg_match('/^[a-f0-9]+_(.+)$/', $filename, $matches)) {
        return $matches[1];
    }
    
    // If it doesn't match our format, return the original filename
    return $filename;
}

/**
 * Fix file path for proper display/access
 * 
 * @param string $path The file path to fix
 * @return string The fixed file path
 */

/**
 * Get a display-friendly filename from a path
 * Extracts the original filename if available
 * 
 * @param string $path The file path
 * @return string The display-friendly filename
 */
function getDisplayFilename($path) {
    $filename = basename($path);
    return getOriginalFilename($filename);
} 