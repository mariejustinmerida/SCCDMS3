<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';
$document_id = isset($_POST['document_id']) ? $_POST['document_id'] : '';

// Validate document_id
if (empty($document_id)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Document ID is required']);
    exit;
}

// Check if user has permission to access this document
$permission = getUserDocumentPermission($conn, $user_id, $document_id);
if (!$permission) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'You do not have permission to access this document']);
    exit;
}

switch ($action) {
    case 'save_version':
        saveVersion($conn, $user_id, $document_id);
        break;
    case 'get_versions':
        getVersions($conn, $document_id);
        break;
    case 'get_version_content':
        $version_id = isset($_POST['version_id']) ? $_POST['version_id'] : '';
        getVersionContent($conn, $document_id, $version_id, $user_id);
        break;
    case 'restore_version':
        $version_id = isset($_POST['version_id']) ? $_POST['version_id'] : '';
        restoreVersion($conn, $document_id, $version_id, $user_id);
        break;
    case 'add_collaborator':
        $collaborator_email = isset($_POST['email']) ? $_POST['email'] : '';
        $permission_level = isset($_POST['permission']) ? $_POST['permission'] : 'view';
        addCollaborator($conn, $document_id, $collaborator_email, $permission_level, $user_id);
        break;
    case 'remove_collaborator':
        $collaborator_id = isset($_POST['collaborator_id']) ? $_POST['collaborator_id'] : '';
        removeCollaborator($conn, $document_id, $collaborator_id, $user_id);
        break;
    case 'get_collaborators':
        getCollaborators($conn, $document_id, $user_id);
        break;
    case 'add_comment':
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        $position_start = isset($_POST['position_start']) ? $_POST['position_start'] : null;
        $position_end = isset($_POST['position_end']) ? $_POST['position_end'] : null;
        $parent_id = isset($_POST['parent_id']) ? $_POST['parent_id'] : null;
        addComment($conn, $document_id, $user_id, $content, $position_start, $position_end, $parent_id);
        break;
    case 'get_comments':
        getComments($conn, $document_id);
        break;
    case 'resolve_comment':
        $comment_id = isset($_POST['comment_id']) ? $_POST['comment_id'] : '';
        resolveComment($conn, $comment_id, $user_id);
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        exit;
}

// Function to get user's permission level for a document
function getUserDocumentPermission($conn, $user_id, $document_id) {
    // First check if user is the owner of the document
    $stmt = $conn->prepare("SELECT user_id FROM documents WHERE id = ?");
    $stmt->bind_param("s", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['user_id'] == $user_id) {
            return 'admin'; // Document owner has admin rights
        }
    }
    
    // Check collaborator permissions
    $stmt = $conn->prepare("SELECT permission FROM document_collaborators WHERE document_id = ? AND user_id = ?");
    $stmt->bind_param("si", $document_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['permission'];
    }
    
    return false; // No permission
}

// Function to save a new version of a document
function saveVersion($conn, $user_id, $document_id) {
    // Check if user has edit permission
    $permission = getUserDocumentPermission($conn, $user_id, $document_id);
    if ($permission != 'edit' && $permission != 'admin') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'You do not have permission to edit this document']);
        exit;
    }
    
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    $comment = isset($_POST['comment']) ? $_POST['comment'] : '';
    
    // Get the latest version number
    $stmt = $conn->prepare("SELECT MAX(version_number) as max_version FROM document_versions WHERE document_id = ?");
    $stmt->bind_param("s", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $version_number = ($row['max_version'] === null) ? 1 : $row['max_version'] + 1;
    
    // Insert new version
    $stmt = $conn->prepare("INSERT INTO document_versions (document_id, version_number, user_id, content, comment) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sisss", $document_id, $version_number, $user_id, $content, $comment);
    
    if ($stmt->execute()) {
        // Update the document's content in the documents table
        $stmt = $conn->prepare("UPDATE documents SET content = ? WHERE id = ?");
        $stmt->bind_param("ss", $content, $document_id);
        $stmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'message' => 'Version saved successfully',
            'version_number' => $version_number
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to save version: ' . $conn->error]);
    }
}

// Function to get all versions of a document
function getVersions($conn, $document_id) {
    $stmt = $conn->prepare("
        SELECT v.version_id, v.version_number, v.user_id, u.username, v.created_at, v.comment
        FROM document_versions v
        JOIN users u ON v.user_id = u.id
        WHERE v.document_id = ?
        ORDER BY v.version_number DESC
    ");
    $stmt->bind_param("s", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $versions = [];
    while ($row = $result->fetch_assoc()) {
        $versions[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'versions' => $versions]);
}

// Function to get content of a specific version
function getVersionContent($conn, $document_id, $version_id, $user_id) {
    if (empty($version_id)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Version ID is required']);
        exit;
    }
    
    $stmt = $conn->prepare("
        SELECT v.content, v.version_number, v.created_at, u.username
        FROM document_versions v
        JOIN users u ON v.user_id = u.id
        WHERE v.document_id = ? AND v.version_id = ?
    ");
    $stmt->bind_param("si", $document_id, $version_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'content' => $row['content'],
            'version_number' => $row['version_number'],
            'created_at' => $row['created_at'],
            'username' => $row['username']
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Version not found']);
    }
}

// Function to restore a previous version
function restoreVersion($conn, $document_id, $version_id, $user_id) {
    // Check if user has edit permission
    $permission = getUserDocumentPermission($conn, $user_id, $document_id);
    if ($permission != 'edit' && $permission != 'admin') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'You do not have permission to edit this document']);
        exit;
    }
    
    if (empty($version_id)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Version ID is required']);
        exit;
    }
    
    // Get the content of the version to restore
    $stmt = $conn->prepare("SELECT content FROM document_versions WHERE document_id = ? AND version_id = ?");
    $stmt->bind_param("si", $document_id, $version_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $content = $row['content'];
        
        // Create a new version with the restored content
        $comment = "Restored from version ID: " . $version_id;
        
        // Get the latest version number
        $stmt = $conn->prepare("SELECT MAX(version_number) as max_version FROM document_versions WHERE document_id = ?");
        $stmt->bind_param("s", $document_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $version_number = $row['max_version'] + 1;
        
        // Insert new version
        $stmt = $conn->prepare("INSERT INTO document_versions (document_id, version_number, user_id, content, comment) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sisss", $document_id, $version_number, $user_id, $content, $comment);
        
        if ($stmt->execute()) {
            // Update the document's content in the documents table
            $stmt = $conn->prepare("UPDATE documents SET content = ? WHERE id = ?");
            $stmt->bind_param("ss", $content, $document_id);
            $stmt->execute();
            
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success', 
                'message' => 'Version restored successfully',
                'version_number' => $version_number
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Failed to restore version: ' . $conn->error]);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Version not found']);
    }
}

// Function to add a collaborator to a document
function addCollaborator($conn, $document_id, $email, $permission_level, $user_id) {
    // Check if user has admin permission
    $user_permission = getUserDocumentPermission($conn, $user_id, $document_id);
    if ($user_permission != 'admin') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'You do not have permission to add collaborators']);
        exit;
    }
    
    if (empty($email)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Email is required']);
        exit;
    }
    
    // Validate permission level
    if (!in_array($permission_level, ['view', 'edit', 'admin'])) {
        $permission_level = 'view'; // Default to view if invalid
    }
    
    // Find user by email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    $collaborator_id = $row['id'];
    
    // Check if collaborator already exists
    $stmt = $conn->prepare("SELECT id FROM document_collaborators WHERE document_id = ? AND user_id = ?");
    $stmt->bind_param("si", $document_id, $collaborator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing collaborator
        $stmt = $conn->prepare("UPDATE document_collaborators SET permission = ? WHERE document_id = ? AND user_id = ?");
        $stmt->bind_param("ssi", $permission_level, $document_id, $collaborator_id);
    } else {
        // Add new collaborator
        $stmt = $conn->prepare("INSERT INTO document_collaborators (document_id, user_id, permission, added_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sisi", $document_id, $collaborator_id, $permission_level, $user_id);
    }
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Collaborator added successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to add collaborator: ' . $conn->error]);
    }
}

// Function to remove a collaborator
function removeCollaborator($conn, $document_id, $collaborator_id, $user_id) {
    // Check if user has admin permission
    $user_permission = getUserDocumentPermission($conn, $user_id, $document_id);
    if ($user_permission != 'admin') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'You do not have permission to remove collaborators']);
        exit;
    }
    
    if (empty($collaborator_id)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Collaborator ID is required']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM document_collaborators WHERE document_id = ? AND user_id = ?");
    $stmt->bind_param("si", $document_id, $collaborator_id);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Collaborator removed successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to remove collaborator: ' . $conn->error]);
    }
}

// Function to get all collaborators for a document
function getCollaborators($conn, $document_id, $user_id) {
    $stmt = $conn->prepare("
        SELECT c.id, c.user_id, u.username, u.email, c.permission, c.added_at,
               a.username as added_by_username
        FROM document_collaborators c
        JOIN users u ON c.user_id = u.id
        JOIN users a ON c.added_by = a.id
        WHERE c.document_id = ?
        ORDER BY c.added_at DESC
    ");
    $stmt->bind_param("s", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $collaborators = [];
    while ($row = $result->fetch_assoc()) {
        $collaborators[] = $row;
    }
    
    // Also get the document owner
    $stmt = $conn->prepare("
        SELECT d.user_id, u.username, u.email
        FROM documents d
        JOIN users u ON d.user_id = u.id
        WHERE d.id = ?
    ");
    $stmt->bind_param("s", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $owner = $result->fetch_assoc();
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success', 
        'collaborators' => $collaborators,
        'owner' => $owner
    ]);
}

// Function to add a comment to a document
function addComment($conn, $document_id, $user_id, $content, $position_start, $position_end, $parent_id) {
    if (empty($content)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Comment content is required']);
        exit;
    }
    
    // Prepare the SQL statement
    if ($parent_id) {
        $stmt = $conn->prepare("INSERT INTO document_comments (document_id, user_id, parent_id, content, position_start, position_end) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siisii", $document_id, $user_id, $parent_id, $content, $position_start, $position_end);
    } else {
        $stmt = $conn->prepare("INSERT INTO document_comments (document_id, user_id, content, position_start, position_end) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sisii", $document_id, $user_id, $content, $position_start, $position_end);
    }
    
    if ($stmt->execute()) {
        $comment_id = $conn->insert_id;
        
        // Get the comment with user information
        $stmt = $conn->prepare("
            SELECT c.comment_id, c.user_id, u.username, c.content, c.position_start, c.position_end,
                   c.created_at, c.updated_at, c.resolved, c.parent_id
            FROM document_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.comment_id = ?
        ");
        $stmt->bind_param("i", $comment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $comment = $result->fetch_assoc();
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'message' => 'Comment added successfully',
            'comment' => $comment
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to add comment: ' . $conn->error]);
    }
}

// Function to get all comments for a document
function getComments($conn, $document_id) {
    $stmt = $conn->prepare("
        SELECT c.comment_id, c.user_id, u.username, c.content, c.position_start, c.position_end,
               c.created_at, c.updated_at, c.resolved, c.parent_id
        FROM document_comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.document_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->bind_param("s", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    
    // Organize comments into threads
    $threads = [];
    $replies = [];
    
    foreach ($comments as $comment) {
        if ($comment['parent_id'] === null) {
            $threads[] = $comment;
        } else {
            if (!isset($replies[$comment['parent_id']])) {
                $replies[$comment['parent_id']] = [];
            }
            $replies[$comment['parent_id']][] = $comment;
        }
    }
    
    // Add replies to their parent comments
    foreach ($threads as &$thread) {
        $thread['replies'] = isset($replies[$thread['comment_id']]) ? $replies[$thread['comment_id']] : [];
    }
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'comments' => $threads]);
}

// Function to resolve a comment
function resolveComment($conn, $comment_id, $user_id) {
    if (empty($comment_id)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Comment ID is required']);
        exit;
    }
    
    // Check if user is the comment author or has admin permission on the document
    $stmt = $conn->prepare("
        SELECT c.user_id, c.document_id
        FROM document_comments c
        WHERE c.comment_id = ?
    ");
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Comment not found']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    $comment_user_id = $row['user_id'];
    $document_id = $row['document_id'];
    
    $permission = getUserDocumentPermission($conn, $user_id, $document_id);
    
    if ($user_id != $comment_user_id && $permission != 'admin') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'You do not have permission to resolve this comment']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE document_comments SET resolved = TRUE WHERE comment_id = ?");
    $stmt->bind_param("i", $comment_id);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Comment resolved successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to resolve comment: ' . $conn->error]);
    }
}
?> 