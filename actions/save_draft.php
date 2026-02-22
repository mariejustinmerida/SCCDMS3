<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

// Create drafts table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS document_drafts (
    draft_id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    title VARCHAR(255),
    type_id INT(11),
    content LONGTEXT,
    workflow TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (draft_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)");

switch ($action) {
    case 'save_draft':
        $title = isset($_POST['title']) ? $_POST['title'] : '';
        $type_id = isset($_POST['type_id']) ? $_POST['type_id'] : null;
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        $workflow = isset($_POST['workflow']) ? json_encode($_POST['workflow']) : '[]';
        $draft_id = isset($_POST['draft_id']) ? $_POST['draft_id'] : null;
        
        if ($draft_id) {
            // Update existing draft
            $stmt = $conn->prepare("UPDATE document_drafts SET title = ?, type_id = ?, content = ?, workflow = ? WHERE draft_id = ? AND user_id = ?");
            $stmt->bind_param("sissii", $title, $type_id, $content, $workflow, $draft_id, $user_id);
            $result = $stmt->execute();
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Draft updated successfully', 'draft_id' => $draft_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating draft: ' . $conn->error]);
            }
        } else {
            // Create new draft
            $stmt = $conn->prepare("INSERT INTO document_drafts (user_id, title, type_id, content, workflow) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isiss", $user_id, $title, $type_id, $content, $workflow);
            $result = $stmt->execute();
            
            if ($result) {
                $new_draft_id = $conn->insert_id;
                echo json_encode(['success' => true, 'message' => 'Draft saved successfully', 'draft_id' => $new_draft_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error saving draft: ' . $conn->error]);
            }
        }
        break;
    
    case 'get_drafts':
        $stmt = $conn->prepare("SELECT draft_id, title, type_id, DATE_FORMAT(updated_at, '%b %d, %Y %h:%i %p') as saved_at FROM document_drafts WHERE user_id = ? ORDER BY updated_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $drafts = [];
        while ($row = $result->fetch_assoc()) {
            $drafts[] = $row;
        }
        
        echo json_encode(['success' => true, 'drafts' => $drafts]);
        break;
    
    case 'get_draft':
        $draft_id = isset($_GET['draft_id']) ? $_GET['draft_id'] : 0;
        
        $stmt = $conn->prepare("SELECT * FROM document_drafts WHERE draft_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $draft_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($draft = $result->fetch_assoc()) {
            // Parse workflow JSON back to array
            $draft['workflow'] = json_decode($draft['workflow'], true);
            echo json_encode(['success' => true, 'draft' => $draft]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Draft not found']);
        }
        break;
    
    case 'delete_draft':
        $draft_id = isset($_POST['draft_id']) ? $_POST['draft_id'] : 0;
        
        $stmt = $conn->prepare("DELETE FROM document_drafts WHERE draft_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $draft_id, $user_id);
        $result = $stmt->execute();
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Draft deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting draft: ' . $conn->error]);
        }
        break;
    
    case 'count_drafts':
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM document_drafts WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        echo json_encode(['success' => true, 'count' => $row['count']]);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
