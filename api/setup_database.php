<?php
/**
 * Database Setup Script
 * 
 * This script checks and creates all necessary tables and columns for the SCCDMS system.
 */

// Include database configuration
require_once __DIR__ . '/../includes/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Track changes made
$changes = [];

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Check if documents table exists
    $check_documents_table = "SHOW TABLES LIKE 'documents'";
    $documents_table_exists = $conn->query($check_documents_table)->num_rows > 0;
    
    if (!$documents_table_exists) {
        // Create documents table
        $create_documents_table = "CREATE TABLE documents (
            id INT(11) NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            type_id INT(11) NOT NULL,
            creator_id INT(11) NOT NULL,
            status VARCHAR(50) DEFAULT 'draft',
            content LONGTEXT,
            google_doc_id VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY creator_id (creator_id),
            KEY type_id (type_id)
        )";
        
        if ($conn->query($create_documents_table)) {
            $changes[] = "Created documents table";
        } else {
            throw new Exception('Failed to create documents table: ' . $conn->error);
        }
    } else {
        // Check if google_doc_id column exists in documents table
        $check_google_doc_id = "SHOW COLUMNS FROM documents LIKE 'google_doc_id'";
        if ($conn->query($check_google_doc_id)->num_rows === 0) {
            // Add google_doc_id column
            $add_google_doc_id = "ALTER TABLE documents ADD COLUMN google_doc_id VARCHAR(255) DEFAULT NULL";
            if ($conn->query($add_google_doc_id)) {
                $changes[] = "Added google_doc_id column to documents table";
            } else {
                throw new Exception('Failed to add google_doc_id column: ' . $conn->error);
            }
        }
        
        // Check if updated_at column exists in documents table
        $check_updated_at = "SHOW COLUMNS FROM documents LIKE 'updated_at'";
        if ($conn->query($check_updated_at)->num_rows === 0) {
            // Add updated_at column
            $add_updated_at = "ALTER TABLE documents ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
            if ($conn->query($add_updated_at)) {
                $changes[] = "Added updated_at column to documents table";
            } else {
                throw new Exception('Failed to add updated_at column: ' . $conn->error);
            }
        }
        
        // Check if current_step column exists in documents table
        $check_current_step = "SHOW COLUMNS FROM documents LIKE 'current_step'";
        if ($conn->query($check_current_step)->num_rows === 0) {
            // Add current_step column
            $add_current_step = "ALTER TABLE documents ADD COLUMN current_step INT(11) DEFAULT NULL";
            if ($conn->query($add_current_step)) {
                $changes[] = "Added current_step column to documents table";
            } else {
                throw new Exception('Failed to add current_step column: ' . $conn->error);
            }
        }
    }
    
    // Check if workflow_steps table exists
    $check_workflow_table = "SHOW TABLES LIKE 'workflow_steps'";
    $workflow_table_exists = $conn->query($check_workflow_table)->num_rows > 0;
    
    if (!$workflow_table_exists) {
        // Create workflow_steps table
        $create_workflow_table = "CREATE TABLE workflow_steps (
            id INT(11) NOT NULL AUTO_INCREMENT,
            document_id INT(11) NOT NULL,
            office_id INT(11) NOT NULL,
            role_id INT(11) DEFAULT NULL,
            step_order INT(11) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY document_id (document_id),
            KEY office_id (office_id),
            KEY role_id (role_id)
        )";
        
        if ($conn->query($create_workflow_table)) {
            $changes[] = "Created workflow_steps table";
        } else {
            throw new Exception('Failed to create workflow_steps table: ' . $conn->error);
        }
    } else {
        // Check column names in workflow_steps table
        $workflow_columns = $conn->query("SHOW COLUMNS FROM workflow_steps");
        $column_names = [];
        
        while ($column = $workflow_columns->fetch_assoc()) {
            $column_names[] = $column['Field'];
        }
        
        // Check if id column exists (instead of step_id)
        if (!in_array('id', $column_names) && in_array('step_id', $column_names)) {
            // Rename step_id to id
            $rename_step_id = "ALTER TABLE workflow_steps CHANGE step_id id INT(11) NOT NULL AUTO_INCREMENT";
            if ($conn->query($rename_step_id)) {
                $changes[] = "Renamed step_id to id in workflow_steps table";
            } else {
                throw new Exception('Failed to rename step_id column: ' . $conn->error);
            }
        }
        
        // Check if document_id column exists
        if (!in_array('document_id', $column_names)) {
            // Add document_id column
            $add_document_id = "ALTER TABLE workflow_steps ADD COLUMN document_id INT(11) NOT NULL AFTER id";
            if ($conn->query($add_document_id)) {
                $changes[] = "Added document_id column to workflow_steps table";
            } else {
                throw new Exception('Failed to add document_id column: ' . $conn->error);
            }
        }
        
        // Check if role_id column exists
        if (!in_array('role_id', $column_names)) {
            // Add role_id column
            $add_role_id = "ALTER TABLE workflow_steps ADD COLUMN role_id INT(11) DEFAULT NULL AFTER office_id";
            if ($conn->query($add_role_id)) {
                $changes[] = "Added role_id column to workflow_steps table";
            } else {
                throw new Exception('Failed to add role_id column: ' . $conn->error);
            }
        }
    }
    
    // Check if document_attachments table exists
    $check_attachments_table = "SHOW TABLES LIKE 'document_attachments'";
    $attachments_table_exists = $conn->query($check_attachments_table)->num_rows > 0;
    
    if (!$attachments_table_exists) {
        // Create document_attachments table
        $create_attachments_table = "CREATE TABLE document_attachments (
            id INT(11) NOT NULL AUTO_INCREMENT,
            document_id INT(11) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            file_size INT(11) NOT NULL,
            uploaded_by INT(11) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY document_id (document_id),
            KEY uploaded_by (uploaded_by)
        )";
        
        if ($conn->query($create_attachments_table)) {
            $changes[] = "Created document_attachments table";
        } else {
            throw new Exception('Failed to create document_attachments table: ' . $conn->error);
        }
    } else {
        // Check column names in document_attachments table
        $attachment_columns = $conn->query("SHOW COLUMNS FROM document_attachments");
        $column_names = [];
        
        while ($column = $attachment_columns->fetch_assoc()) {
            $column_names[] = $column['Field'];
        }
        
        // Check if id column exists (instead of attachment_id)
        if (!in_array('id', $column_names) && in_array('attachment_id', $column_names)) {
            // Rename attachment_id to id
            $rename_attachment_id = "ALTER TABLE document_attachments CHANGE attachment_id id INT(11) NOT NULL AUTO_INCREMENT";
            if ($conn->query($rename_attachment_id)) {
                $changes[] = "Renamed attachment_id to id in document_attachments table";
            } else {
                throw new Exception('Failed to rename attachment_id column: ' . $conn->error);
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Database setup completed successfully',
        'changes' => $changes
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
