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
if (!$permission || $permission == 'view') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'You do not have permission to edit this document']);
    exit;
}

switch ($action) {
    case 'start_session':
        startEditSession($conn, $user_id, $document_id);
        break;
    case 'end_session':
        $session_id = isset($_POST['session_id']) ? $_POST['session_id'] : '';
        endEditSession($conn, $session_id, $user_id);
        break;
    case 'get_active_sessions':
        getActiveSessions($conn, $document_id);
        break;
    case 'record_change':
        $session_id = isset($_POST['session_id']) ? $_POST['session_id'] : '';
        $change_type = isset($_POST['change_type']) ? $_POST['change_type'] : '';
        $position = isset($_POST['position']) ? $_POST['position'] : 0;
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        $length = isset($_POST['length']) ? $_POST['length'] : 0;
        recordChange($conn, $document_id, $session_id, $user_id, $change_type, $position, $content, $length);
        break;
    case 'get_changes':
        $last_change_id = isset($_POST['last_change_id']) ? $_POST['last_change_id'] : 0;
        getChanges($conn, $document_id, $last_change_id);
        break;
    case 'acquire_lock':
        $section_id = isset($_POST['section_id']) ? $_POST['section_id'] : null;
        acquireLock($conn, $document_id, $section_id, $user_id);
        break;
    case 'release_lock':
        $section_id = isset($_POST['section_id']) ? $_POST['section_id'] : null;
        releaseLock($conn, $document_id, $section_id, $user_id);
        break;
    case 'get_locks':
        getLocks($conn, $document_id);
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        exit;
}

// Function to get user's permission level for a document (reused from version_control.php)
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

// Function to start a new edit session
function startEditSession($conn, $user_id, $document_id) {
    // Generate a unique session ID
    $session_id = uniqid('session_', true);
    
    // Insert new session
    $stmt = $conn->prepare("INSERT INTO document_edit_sessions (session_id, document_id, user_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $session_id, $document_id, $user_id);
    
    if ($stmt->execute()) {
        // Get user information
        $stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'message' => 'Edit session started',
            'session_id' => $session_id,
            'user' => $user
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to start edit session: ' . $conn->error]);
    }
}

// Function to end an edit session
function endEditSession($conn, $session_id, $user_id) {
    if (empty($session_id)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Session ID is required']);
        exit;
    }
    
    // Verify that the session belongs to the user
    $stmt = $conn->prepare("SELECT user_id FROM document_edit_sessions WHERE session_id = ?");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Session not found']);
        exit;
    }
    
    $row = $result->fetch_assoc();
    if ($row['user_id'] != $user_id) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'You do not have permission to end this session']);
        exit;
    }
    
    // Update session status to closed
    $stmt = $conn->prepare("UPDATE document_edit_sessions SET status = 'closed' WHERE session_id = ?");
    $stmt->bind_param("s", $session_id);
    
    if ($stmt->execute()) {
        // Release any locks held by this user
        $stmt = $conn->prepare("DELETE FROM document_locks WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Edit session ended']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to end edit session: ' . $conn->error]);
    }
}

// Function to get all active edit sessions for a document
function getActiveSessions($conn, $document_id) {
    $stmt = $conn->prepare("
        SELECT s.session_id, s.user_id, u.username, u.email, s.started_at, s.last_activity
        FROM document_edit_sessions s
        JOIN users u ON s.user_id = u.id
        WHERE s.document_id = ? AND s.status = 'active'
        AND s.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY s.last_activity DESC
    ");
    $stmt->bind_param("s", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'sessions' => $sessions]);
}

// Function to record a document change
function recordChange($conn, $document_id, $session_id, $user_id, $change_type, $position, $content, $length) {
    if (empty($session_id)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Session ID is required']);
        exit;
    }
    
    if (empty($change_type) || !in_array($change_type, ['insert', 'delete', 'replace'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Valid change type is required']);
        exit;
    }
    
    // Verify that the session is active and belongs to the user
    $stmt = $conn->prepare("
        SELECT session_id 
        FROM document_edit_sessions 
        WHERE session_id = ? AND user_id = ? AND status = 'active'
    ");
    $stmt->bind_param("si", $session_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid or inactive session']);
        exit;
    }
    
    // Update session last activity
    $stmt = $conn->prepare("UPDATE document_edit_sessions SET last_activity = NOW() WHERE session_id = ?");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    
    // Record the change
    $stmt = $conn->prepare("
        INSERT INTO document_changes 
        (document_id, session_id, user_id, change_type, position, content, length) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssisssi", $document_id, $session_id, $user_id, $change_type, $position, $content, $length);
    
    if ($stmt->execute()) {
        $change_id = $conn->insert_id;
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'message' => 'Change recorded',
            'change_id' => $change_id
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to record change: ' . $conn->error]);
    }
}

// Function to get changes since a specific change ID
function getChanges($conn, $document_id, $last_change_id) {
    $stmt = $conn->prepare("
        SELECT c.change_id, c.session_id, c.user_id, u.username, c.change_type, 
               c.position, c.content, c.length, c.timestamp
        FROM document_changes c
        JOIN users u ON c.user_id = u.id
        WHERE c.document_id = ? AND c.change_id > ?
        ORDER BY c.change_id ASC
    ");
    $stmt->bind_param("si", $document_id, $last_change_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $changes = [];
    while ($row = $result->fetch_assoc()) {
        $changes[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'changes' => $changes]);
}

// Function to acquire a lock on a document section
function acquireLock($conn, $document_id, $section_id, $user_id) {
    // Check if the section is already locked
    $stmt = $conn->prepare("
        SELECT l.lock_id, l.user_id, u.username
        FROM document_locks l
        JOIN users u ON l.user_id = u.id
        WHERE l.document_id = ? AND (l.section_id = ? OR l.section_id IS NULL)
        AND l.expires_at > NOW()
    ");
    $stmt->bind_param("ss", $document_id, $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['user_id'] != $user_id) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error', 
                'message' => 'Section is locked by another user',
                'locked_by' => [
                    'user_id' => $row['user_id'],
                    'username' => $row['username']
                ]
            ]);
            exit;
        }
        
        // User already has the lock, extend it
        $stmt = $conn->prepare("UPDATE document_locks SET expires_at = DATE_ADD(NOW(), INTERVAL 2 MINUTE) WHERE lock_id = ?");
        $stmt->bind_param("i", $row['lock_id']);
        $stmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'message' => 'Lock extended',
            'lock_id' => $row['lock_id']
        ]);
        exit;
    }
    
    // Acquire a new lock
    $expires_at = date('Y-m-d H:i:s', strtotime('+2 minutes'));
    
    if ($section_id) {
        $stmt = $conn->prepare("
            INSERT INTO document_locks (document_id, section_id, user_id, expires_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssis", $document_id, $section_id, $user_id, $expires_at);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO document_locks (document_id, user_id, expires_at)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("sis", $document_id, $user_id, $expires_at);
    }
    
    if ($stmt->execute()) {
        $lock_id = $conn->insert_id;
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'message' => 'Lock acquired',
            'lock_id' => $lock_id,
            'expires_at' => $expires_at
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to acquire lock: ' . $conn->error]);
    }
}

// Function to release a lock
function releaseLock($conn, $document_id, $section_id, $user_id) {
    if ($section_id) {
        $stmt = $conn->prepare("DELETE FROM document_locks WHERE document_id = ? AND section_id = ? AND user_id = ?");
        $stmt->bind_param("ssi", $document_id, $section_id, $user_id);
    } else {
        $stmt = $conn->prepare("DELETE FROM document_locks WHERE document_id = ? AND section_id IS NULL AND user_id = ?");
        $stmt->bind_param("si", $document_id, $user_id);
    }
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Lock released']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to release lock: ' . $conn->error]);
    }
}

// Function to get all active locks for a document
function getLocks($conn, $document_id) {
    $stmt = $conn->prepare("
        SELECT l.lock_id, l.document_id, l.section_id, l.user_id, u.username, l.acquired_at, l.expires_at
        FROM document_locks l
        JOIN users u ON l.user_id = u.id
        WHERE l.document_id = ? AND l.expires_at > NOW()
    ");
    $stmt->bind_param("s", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $locks = [];
    while ($row = $result->fetch_assoc()) {
        $locks[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'locks' => $locks]);
}
?> 