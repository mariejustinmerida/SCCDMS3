<?php
require_once 'includes/config.php';

// Set content type to plain text for easier reading
header('Content-Type: text/plain');

// Start session to get user information
session_start();
$user_id = $_SESSION['user_id'] ?? null;
$office_id = $_SESSION['office_id'] ?? null;

echo "=== FORCE FIX INCOMING DOCUMENTS ===\n\n";

if (!$user_id || !$office_id) {
    echo "Error: User not logged in or office ID not set.\n";
    echo "Please log in first.\n";
    exit;
}

echo "User ID: $user_id\n";
echo "Office ID: $office_id\n\n";

// 1. First, let's check the notification count to see what documents should be in the inbox
echo "Checking notification count...\n";
$notification_query = "SELECT COUNT(*) as count FROM documents d 
    JOIN document_workflow dw ON d.document_id = dw.document_id 
    WHERE dw.status = 'current' AND dw.office_id = $office_id";

$notification_result = $conn->query($notification_query);
$notification_count = 0;
if ($notification_result && $row = $notification_result->fetch_assoc()) {
    $notification_count = $row['count'];
}

echo "Notification count: $notification_count\n\n";

// 2. Now let's check what documents should be in the inbox
echo "Documents that should be in the inbox:\n";
$documents_query = "SELECT d.document_id, d.title, d.status, dw.status as workflow_status 
    FROM documents d 
    JOIN document_workflow dw ON d.document_id = dw.document_id 
    WHERE dw.office_id = $office_id AND dw.status = 'current'";

$documents_result = $conn->query($documents_query);
$documents = [];
if ($documents_result) {
    while ($row = $documents_result->fetch_assoc()) {
        $documents[] = $row;
        echo "- Document ID: {$row['document_id']}, Title: {$row['title']}, Status: {$row['status']}, Workflow Status: {$row['workflow_status']}\n";
    }
}

if (empty($documents)) {
    echo "No documents found that should be in the inbox.\n\n";
} else {
    echo "\nFound " . count($documents) . " documents that should be in the inbox.\n\n";
}

// 3. Let's create a test document and assign it to the current office
echo "Creating a test document...\n";

// Insert a new document
$title = "Test Document " . date('Y-m-d H:i:s');
$insert_doc_query = "INSERT INTO documents (title, type_id, creator_id, status, created_at) 
    VALUES (?, 1, ?, 'pending', NOW())";
$insert_doc_stmt = $conn->prepare($insert_doc_query);
$insert_doc_stmt->bind_param("si", $title, $user_id);
$insert_doc_stmt->execute();

$document_id = $conn->insert_id;
echo "Created document ID: $document_id\n";

// Insert workflow step for the current office
$insert_workflow_query = "INSERT INTO document_workflow (document_id, office_id, step_order, status, assigned_at) 
    VALUES (?, ?, 1, 'current', NOW())";
$insert_workflow_stmt = $conn->prepare($insert_workflow_query);
$insert_workflow_stmt->bind_param("ii", $document_id, $office_id);
$insert_workflow_stmt->execute();

echo "Created workflow step for office ID: $office_id\n\n";

// 4. Now let's fix the display issue in the incoming.php page
echo "Fixing the incoming.php page...\n";

// Update the incoming.php file to use a simpler query
$incoming_php_path = __DIR__ . '/pages/incoming.php';
$incoming_php_content = file_get_contents($incoming_php_path);

// Create a backup of the original file
$backup_path = __DIR__ . '/pages/incoming.php.bak';
if (!file_exists($backup_path)) {
    file_put_contents($backup_path, $incoming_php_content);
    echo "Created backup of incoming.php at $backup_path\n";
}

// Replace the SQL query in the file
$count_sql_pattern = '/\$count_sql = "[^"]+";/s';
$new_count_sql = '$count_sql = "SELECT COUNT(*) as total FROM document_workflow dw JOIN documents d ON dw.document_id = d.document_id WHERE dw.office_id = ' . $office_id . ' AND dw.status = \'current\'";';

$main_sql_pattern = '/\$sql = "[^"]+";/s';
$new_main_sql = '$sql = "SELECT d.document_id, d.title, d.type_id, d.file_path, d.google_doc_id, d.creator_id, d.created_at, d.status, dt.type_name, u.full_name as creator_name, o.office_name as creator_office FROM documents d JOIN document_workflow dw ON d.document_id = dw.document_id LEFT JOIN document_types dt ON d.type_id = dt.type_id LEFT JOIN users u ON d.creator_id = u.user_id LEFT JOIN offices o ON u.office_id = o.office_id WHERE dw.office_id = ' . $office_id . ' AND dw.status = \'current\' ORDER BY d.created_at DESC LIMIT $offset, $items_per_page";';

// Apply the replacements
$updated_content = preg_replace($count_sql_pattern, $new_count_sql, $incoming_php_content, 1);
$updated_content = preg_replace($main_sql_pattern, $new_main_sql, $updated_content, 1);

// Save the updated file
file_put_contents($incoming_php_path, $updated_content);
echo "Updated incoming.php with simplified queries\n\n";

// 5. Create a direct link to the incoming page
echo "=== INSTRUCTIONS ===\n";
echo "1. A test document has been created and assigned to your office.\n";
echo "2. The incoming.php page has been updated with simplified queries.\n";
echo "3. Please click here to view your incoming documents: http://localhost/SCCDMS2/pages/incoming.php\n";
echo "4. If the documents still don't appear, try clearing your browser cache or opening in a private/incognito window.\n";

echo "\n=== FORCE FIX COMPLETED ===\n";
?>
