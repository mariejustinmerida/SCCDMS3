<?php
/**
 * Update Document API
 * 
 * This file handles API requests to update document information.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'User not logged in'
    ]);
    exit();
}

$userId = $_SESSION['user_id'];
$officeId = $_SESSION['office_id'];
$roleName = $_SESSION['role'] ?? '';

// Get document ID from request
$documentId = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;

if ($documentId <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid document ID'
    ]);
    exit();
}

// Super Admin can update any document; others must own-by-office
if ($roleName !== 'Super Admin') {
    $check_sql = "SELECT d.* FROM documents d
                  JOIN users u ON d.creator_id = u.user_id
                  WHERE d.document_id = $documentId AND u.office_id = $officeId";
    $check_result = $conn->query($check_sql);
    if (!$check_result || $check_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'You don\'t have permission to update this document'
        ]);
        exit();
    }
}

// Get the Google Doc ID from request
$googleDocId = isset($_POST['google_doc_id']) ? $conn->real_escape_string($_POST['google_doc_id']) : '';

// Check if the google_doc_id column exists in the documents table
$check_column_sql = "SHOW COLUMNS FROM documents LIKE 'google_doc_id'";
$check_column_result = $conn->query($check_column_sql);

if ($check_column_result->num_rows === 0) {
    // Add the google_doc_id column to the documents table
    $add_column_sql = "ALTER TABLE documents ADD COLUMN google_doc_id VARCHAR(255) DEFAULT NULL";
    if (!$conn->query($add_column_sql)) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to add google_doc_id column: ' . $conn->error
        ]);
        exit();
    }
}

// Update the document with the Google Doc ID
$update_sql = "UPDATE documents SET google_doc_id = '$googleDocId', updated_at = NOW() WHERE document_id = $documentId";
$update_result = $conn->query($update_sql);

if ($update_result) {
    echo json_encode([
        'success' => true,
        'message' => 'Document updated successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update document: ' . $conn->error
    ]);
}
