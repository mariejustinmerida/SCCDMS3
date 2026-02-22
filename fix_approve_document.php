<?php
require_once 'includes/config.php';

// This script will fix the document_workflow table structure if needed

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px;'>
            <h3>Access Denied</h3>
            <p>You must be logged in as an administrator to run this script.</p>
            <p><a href='auth/login.php'>Login</a> | <a href='pages/dashboard.php'>Dashboard</a></p>
          </div>";
    exit;
}

// Check if the workflow_id column exists in document_workflow table
$check_column = $conn->query("SHOW COLUMNS FROM document_workflow LIKE 'workflow_id'");
$workflow_id_exists = $check_column->num_rows > 0;

if (!$workflow_id_exists) {
    // Add workflow_id column if it doesn't exist
    $conn->query("ALTER TABLE document_workflow ADD COLUMN workflow_id INT AUTO_INCREMENT PRIMARY KEY FIRST");
    echo "<div style='background-color: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 4px; margin: 20px;'>
            <h3>Success</h3>
            <p>Added workflow_id column to document_workflow table.</p>
            <p><a href='pages/dashboard.php'>Return to Dashboard</a></p>
          </div>";
} else {
    echo "<div style='background-color: #d1ecf1; color: #0c5460; padding: 10px; border: 1px solid #bee5eb; border-radius: 4px; margin: 20px;'>
            <h3>Information</h3>
            <p>The workflow_id column already exists in the document_workflow table.</p>
            <p><a href='pages/dashboard.php'>Return to Dashboard</a></p>
          </div>";
}

// Now let's update the approve_document.php file to use document_id and office_id as a fallback
$approve_file = 'pages/approve_document.php';
$content = file_get_contents($approve_file);

// Create a backup of the original file
file_put_contents($approve_file . '.bak', $content);

// Replace the problematic query
$old_query = "UPDATE document_workflow \n                                                 SET status = 'completed', completed_at = NOW(), completed_by = ? \n                                                 WHERE workflow_id = ?";
$new_query = "UPDATE document_workflow \n                                                 SET status = 'completed', completed_at = NOW(), completed_by = ? \n                                                 WHERE document_id = ? AND office_id = ? AND status = 'current'";

// Replace the bind_param call
$old_bind = "\$update_stmt->bind_param(\"ii\", \$user_id, \$workflow['workflow_id'])";
$new_bind = "\$update_stmt->bind_param(\"iii\", \$user_id, \$document_id, \$office_id)";

// Make the replacements
$content = str_replace($old_query, $new_query, $content);
$content = str_replace($old_bind, $new_bind, $content);

// Save the updated file
file_put_contents($approve_file, $content);

echo "<div style='background-color: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 4px; margin: 20px;'>
        <h3>Success</h3>
        <p>Updated approve_document.php to use document_id and office_id for workflow updates.</p>
        <p><a href='pages/dashboard.php?page=incoming'>Return to Inbox</a></p>
      </div>";
?>
