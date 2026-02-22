<?php
/**
 * Activity Logger Implementation
 * 
 * This file contains functions to implement logging throughout the SCCDMS system
 * It should be included in key pages to track user actions
 */

// Make sure logging.php is included
require_once 'logging.php';

/**
 * Add logging to various system actions
 */
function implement_system_logging() {
    global $conn;
    
    // Only proceed if user is logged in
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    $office_id = $_SESSION['office_id'] ?? null;
    
    // Check if this is a document action
    if (isset($_GET['page']) && isset($_GET['id'])) {
        $document_id = (int)$_GET['id'];
        $page = $_GET['page'];
        
        // Log document views
        if ($page === 'view') {
            // Get document title for details
            $doc_sql = "SELECT title FROM documents WHERE document_id = ?";
            $stmt = $conn->prepare($doc_sql);
            $stmt->bind_param("i", $document_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $doc_title = ($result->num_rows > 0) ? $result->fetch_assoc()['title'] : "Unknown document";
            
            log_user_action(
                $user_id, 
                'view_document', 
                "Viewed document: $doc_title", 
                $document_id, 
                null, 
                $office_id
            );
        }
        
        // Log document tracking
        if ($page === 'track') {
            // Get document title for details
            $doc_sql = "SELECT title FROM documents WHERE document_id = ?";
            $stmt = $conn->prepare($doc_sql);
            $stmt->bind_param("i", $document_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $doc_title = ($result->num_rows > 0) ? $result->fetch_assoc()['title'] : "Unknown document";
            
            log_user_action(
                $user_id, 
                'track_document', 
                "Tracked document: $doc_title", 
                $document_id, 
                null, 
                $office_id
            );
        }
        
        // Log document editing
        if ($page === 'edit') {
            // Only log when first accessing the edit page, not on form submission
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                // Get document title for details
                $doc_sql = "SELECT title FROM documents WHERE document_id = ?";
                $stmt = $conn->prepare($doc_sql);
                $stmt->bind_param("i", $document_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $doc_title = ($result->num_rows > 0) ? $result->fetch_assoc()['title'] : "Unknown document";
                
                log_user_action(
                    $user_id, 
                    'access_edit_document', 
                    "Accessed edit page for document: $doc_title", 
                    $document_id, 
                    null, 
                    $office_id
                );
            }
        }
    }
    
    // Log document creation (compose page)
    if (isset($_GET['page']) && $_GET['page'] === 'compose') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            log_user_action(
                $user_id, 
                'access_compose', 
                "Accessed document creation page", 
                null, 
                null, 
                $office_id
            );
        }
    }
    
    // Log form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Document creation
        if (isset($_GET['page']) && $_GET['page'] === 'compose' && isset($_POST['title'])) {
            $title = $_POST['title'];
            log_user_action(
                $user_id, 
                'create_document', 
                "Created new document: $title", 
                null, // Document ID not available yet
                null, 
                $office_id
            );
        }
        
        // Document editing
        if (isset($_GET['page']) && $_GET['page'] === 'edit' && isset($_POST['title']) && isset($_GET['id'])) {
            $title = $_POST['title'];
            $document_id = (int)$_GET['id'];
            log_user_action(
                $user_id, 
                'edit_document', 
                "Updated document: $title", 
                $document_id, 
                null, 
                $office_id
            );
        }
        
        // Document approval/rejection
        if (isset($_POST['action']) && in_array($_POST['action'], ['approve', 'reject']) && isset($_POST['document_id'])) {
            $action = $_POST['action'];
            $document_id = (int)$_POST['document_id'];
            
            // Get document title for details
            $doc_sql = "SELECT title FROM documents WHERE document_id = ?";
            $stmt = $conn->prepare($doc_sql);
            $stmt->bind_param("i", $document_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $doc_title = ($result->num_rows > 0) ? $result->fetch_assoc()['title'] : "Unknown document";
            
            log_user_action(
                $user_id, 
                $action . '_document', 
                ucfirst($action) . "d document: $doc_title", 
                $document_id, 
                null, 
                $office_id
            );
        }
    }
}

// Automatically implement logging when this file is included
implement_system_logging();

/**
 * Function to log document workflow changes
 * Call this function when a document's workflow is updated
 */
function log_workflow_change($document_id, $action, $details) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $office_id = $_SESSION['office_id'] ?? null;
    
    return log_user_action(
        $user_id,
        $action,
        $details,
        $document_id,
        null,
        $office_id
    );
}

/**
 * Function to log user management actions
 * Call this function when user accounts are created, modified, or deleted
 */
function log_user_management($action, $details, $affected_user_id) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $office_id = $_SESSION['office_id'] ?? null;
    
    return log_user_action(
        $user_id,
        $action,
        $details,
        null,
        $affected_user_id,
        $office_id
    );
}

/**
 * Function to log office/department management actions
 * Call this function when offices are created, modified, or deleted
 */
function log_office_management($action, $details, $affected_office_id) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $office_id = $_SESSION['office_id'] ?? null;
    
    return log_user_action(
        $user_id,
        $action,
        $details,
        null,
        null,
        $affected_office_id
    );
}
?>
