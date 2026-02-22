<?php
/**
 * Comprehensive logging system for SCCDMS
 * Records all user actions for auditing and monitoring purposes
 */

/**
 * Log a user action in the system
 * 
 * @param int $user_id The ID of the user performing the action
 * @param string $action The action being performed (e.g., 'create_document', 'edit_document', 'approve_document')
 * @param string $details Additional details about the action
 * @param int|null $affected_document_id The ID of the document being affected (if applicable)
 * @param int|null $affected_user_id The ID of another user being affected (if applicable)
 * @param int|null $office_id The ID of the office related to this action
 * @return bool Whether the logging was successful
 */
function log_user_action($user_id, $action, $details = '', $affected_document_id = null, $affected_user_id = null, $office_id = null) {
    global $conn;
    
    // Get the user's IP address
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // If office_id is not provided but user_id is, get the user's office
    if ($office_id === null && $user_id !== null) {
        $office_query = "SELECT office_id FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($office_query);
        
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $office_id = $row['office_id'];
            }
            
            $stmt->close();
        }
    }
    
    // Check which columns exist in the user_logs table
    $columns = [];
    $values = [];
    $types = "";
    $params = [];
    
    // Basic columns that should always exist
    $columns[] = "user_id";
    $values[] = "?";
    $types .= "i";
    $params[] = $user_id;
    
    $columns[] = "action";
    $values[] = "?";
    $types .= "s";
    $params[] = $action;
    
    $columns[] = "timestamp";
    $values[] = "NOW()";
    
    // Check for additional columns
    $check_columns = [
        'details' => ['value' => $details, 'type' => 's'],
        'ip_address' => ['value' => $ip_address, 'type' => 's'],
        'affected_document_id' => ['value' => $affected_document_id, 'type' => 'i'],
        'affected_user_id' => ['value' => $affected_user_id, 'type' => 'i'],
        'office_id' => ['value' => $office_id, 'type' => 'i']
    ];
    
    foreach ($check_columns as $column => $data) {
        $check_sql = "SHOW COLUMNS FROM user_logs LIKE '$column'";
        $check_result = $conn->query($check_sql);
        
        if ($check_result && $check_result->num_rows > 0) {
            // Column exists, add it to the query
            if ($data['value'] !== null) {
                $columns[] = $column;
                $values[] = "?";
                $types .= $data['type'];
                $params[] = $data['value'];
            }
        }
    }
    
    // Build the SQL statement
    $sql = "INSERT INTO user_logs (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ")";
    
    $stmt = $conn->prepare($sql);
    
    // Check if prepare was successful
    if (!$stmt) {
        error_log("Error preparing log statement: " . $conn->error);
        
        // Fallback to basic logging if the enhanced logging fails
        try {
            $basic_sql = "INSERT INTO user_logs (user_id, action, timestamp) VALUES (?, ?, NOW())";
            $basic_stmt = $conn->prepare($basic_sql);
            if ($basic_stmt) {
                $basic_stmt->bind_param("is", $user_id, $action);
                $basic_stmt->execute();
                $basic_stmt->close();
                return true;
            }
        } catch (Exception $e) {
            error_log("Error with fallback logging: " . $e->getMessage());
        }
        
        return false;
    }
    
    // Bind parameters if there are any
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    // Execute the statement
    $success = $stmt->execute();
    
    if (!$success) {
        error_log("Error logging user action: " . $stmt->error);
        
        // Fallback to basic logging if the enhanced logging fails
        try {
            $basic_sql = "INSERT INTO user_logs (user_id, action, timestamp) VALUES (?, ?, NOW())";
            $basic_stmt = $conn->prepare($basic_sql);
            if ($basic_stmt) {
                $basic_stmt->bind_param("is", $user_id, $action);
                $basic_stmt->execute();
                $basic_stmt->close();
                return true;
            }
        } catch (Exception $e) {
            error_log("Error with fallback logging: " . $e->getMessage());
        }
    }
    
    $stmt->close();
    
    return $success;
}

/**
 * Get a human-readable description of an action
 * 
 * @param string $action The action code
 * @return string Human-readable description
 */
function get_action_description($action) {
    $descriptions = [
        'login' => 'Logged in',
        'logout' => 'Logged out',
        'create_document' => 'Created a document',
        'edit_document' => 'Edited a document',
        'delete_document' => 'Deleted a document',
        'view_document' => 'Viewed a document',
        'approve_document' => 'Approved a document',
        'reject_document' => 'Rejected a document',
        'add_attachment' => 'Added an attachment',
        'remove_attachment' => 'Removed an attachment',
        'assign_workflow' => 'Assigned a workflow',
        'modify_workflow' => 'Modified a workflow',
        'add_comment' => 'Added a comment',
        'create_user' => 'Created a user account',
        'edit_user' => 'Modified a user account',
        'delete_user' => 'Deleted a user account',
        'change_password' => 'Changed password',
        'reset_password' => 'Reset password',
        'create_office' => 'Created an office',
        'edit_office' => 'Modified an office',
        'delete_office' => 'Deleted an office',
        'create_role' => 'Created a role',
        'edit_role' => 'Modified a role',
        'delete_role' => 'Deleted a role',
        'export_data' => 'Exported data',
        'import_data' => 'Imported data',
        'system_setting' => 'Changed system settings'
    ];
    
    return isset($descriptions[$action]) ? $descriptions[$action] : ucfirst(str_replace('_', ' ', $action));
}
