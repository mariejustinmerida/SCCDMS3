<?php
// File Path Debugging Tool
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get document ID from query string
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle file path directly if provided
$direct_path = isset($_GET['path']) ? $_GET['path'] : '';

require_once 'includes/config.php';

echo "<h1>File Path Debugging Tool</h1>";

if ($document_id > 0) {
    // Get file path from database
    $query = "SELECT document_id, title, file_path FROM documents WHERE document_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $document = $result->fetch_assoc();
        
        echo "<h2>Document Information</h2>";
        echo "<p><strong>Document ID:</strong> " . $document['document_id'] . "</p>";
        echo "<p><strong>Title:</strong> " . htmlspecialchars($document['title']) . "</p>";
        echo "<p><strong>File Path in DB:</strong> " . htmlspecialchars($document['file_path']) . "</p>";
        
        $file_path = $document['file_path'];
    } else {
        echo "<p style='color:red'>Document not found in database</p>";
        $file_path = '';
    }
} elseif (!empty($direct_path)) {
    $file_path = $direct_path;
    echo "<h2>Checking Direct Path</h2>";
    echo "<p><strong>File Path:</strong> " . htmlspecialchars($file_path) . "</p>";
} else {
    echo "<p style='color:red'>No document ID or file path provided</p>";
    echo "<p>Usage: file_debug.php?id=123 OR file_debug.php?path=storage/file.pdf</p>";
    exit;
}

if (empty($file_path)) {
    echo "<p style='color:red'>No file path available to check</p>";
    exit;
}

// Normalize path separators
$file_path = str_replace('\\', '/', $file_path);

// List of possible paths to check
$possible_paths = [
    // Original path
    $file_path,
    
    // Absolute paths
    $_SERVER['DOCUMENT_ROOT'] . '/SCCDMS2/' . $file_path,
    $_SERVER['DOCUMENT_ROOT'] . '/SCCDMS2/storage/' . basename($file_path),
    $_SERVER['DOCUMENT_ROOT'] . '/storage/' . basename($file_path),
    
    // Relative paths from script location
    dirname(__FILE__) . '/' . $file_path,
    dirname(__FILE__) . '/storage/' . basename($file_path),
    
    // Common relative paths
    '../' . $file_path,
    '../storage/' . basename($file_path),
    'storage/' . basename($file_path),
    '../../storage/' . basename($file_path),
    
    // Additional fallbacks
    '../uploads/' . basename($file_path),
    'uploads/' . basename($file_path),
];

echo "<h2>Path Existence Checks</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Path</th><th>Exists?</th><th>Is Readable?</th><th>Size</th><th>Last Modified</th></tr>";

foreach ($possible_paths as $path) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($path) . "</td>";
    
    $exists = file_exists($path);
    echo "<td>" . ($exists ? "<span style='color:green'>Yes</span>" : "<span style='color:red'>No</span>") . "</td>";
    
    $readable = is_readable($path);
    echo "<td>" . ($readable ? "<span style='color:green'>Yes</span>" : "<span style='color:red'>No</span>") . "</td>";
    
    if ($exists) {
        $size = filesize($path);
        $size_formatted = $size > 1024 ? round($size / 1024, 2) . ' KB' : $size . ' bytes';
        echo "<td>" . $size_formatted . "</td>";
        
        $last_modified = date('Y-m-d H:i:s', filemtime($path));
        echo "<td>" . $last_modified . "</td>";
    } else {
        echo "<td>-</td><td>-</td>";
    }
    
    echo "</tr>";
}

echo "</table>";

// Display server information
echo "<h2>Server Information</h2>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Script Filename:</strong> " . $_SERVER['SCRIPT_FILENAME'] . "</p>";
echo "<p><strong>Current Directory:</strong> " . getcwd() . "</p>";

// Display content of storage directory if it exists
$storage_dir = 'storage';
echo "<h2>Storage Directory Contents</h2>";
if (is_dir($storage_dir)) {
    $files = scandir($storage_dir);
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>" . htmlspecialchars($file) . "</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p>Storage directory not found at: " . getcwd() . '/' . $storage_dir . "</p>";
    
    // Check if storage exists in parent directory
    $parent_storage = '../storage';
    if (is_dir($parent_storage)) {
        echo "<p>Found storage directory in parent folder:</p>";
        $files = scandir($parent_storage);
        echo "<ul>";
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                echo "<li>" . htmlspecialchars($file) . "</li>";
            }
        }
        echo "</ul>";
    }
}

// Link to view/download file if found
$found_path = '';
foreach ($possible_paths as $path) {
    if (file_exists($path) && is_readable($path)) {
        $found_path = $path;
        break;
    }
}

if (!empty($found_path)) {
    echo "<h2>File Actions</h2>";
    echo "<p><a href='" . $found_path . "' target='_blank'>View/Download File</a></p>";
    
    // Display file preview for images
    $ext = strtolower(pathinfo($found_path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        echo "<h2>Image Preview</h2>";
        echo "<img src='" . $found_path . "' style='max-width: 500px; max-height: 500px;' alt='File Preview'>";
    }
} 