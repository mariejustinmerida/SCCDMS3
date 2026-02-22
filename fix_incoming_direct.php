<?php
require_once 'includes/config.php';

// Set content type to plain text for easier reading
header('Content-Type: text/plain');

// Start session to get user information
session_start();
$user_id = $_SESSION['user_id'] ?? null;
$office_id = $_SESSION['office_id'] ?? null;

echo "=== DIRECT FIX FOR INCOMING DOCUMENTS ===\n\n";

if (!$user_id || !$office_id) {
    echo "Error: User not logged in or office ID not set.\n";
    echo "Please log in first.\n";
    exit;
}

echo "User ID: $user_id\n";
echo "Office ID: $office_id\n\n";

// STEP 1: Examine the database structure
echo "STEP 1: Examining database structure...\n";

// Check document_workflow table structure
$structure_query = "DESCRIBE document_workflow";
$structure_result = $conn->query($structure_query);
if ($structure_result) {
    echo "document_workflow table columns:\n";
    while ($row = $structure_result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error querying table structure: " . $conn->error . "\n";
}

echo "\n";

// STEP 2: Check existing data
echo "STEP 2: Checking existing data...\n";

// Check what documents should be in the inbox according to the notification counter query
$notification_query = "SELECT d.document_id, d.title, d.status, dw.status as workflow_status 
    FROM documents d 
    JOIN document_workflow dw ON d.document_id = dw.document_id 
    WHERE dw.office_id = $office_id AND dw.status = 'current'";

echo "Notification counter query: $notification_query\n\n";

$notification_result = $conn->query($notification_query);
if ($notification_result) {
    if ($notification_result->num_rows > 0) {
        echo "Documents that should be in your inbox:\n";
        while ($row = $notification_result->fetch_assoc()) {
            echo "- Document ID: {$row['document_id']}, Title: {$row['title']}, Status: {$row['status']}, Workflow Status: {$row['workflow_status']}\n";
        }
    } else {
        echo "No documents found that should be in your inbox.\n";
    }
} else {
    echo "Error querying documents: " . $conn->error . "\n";
}

echo "\n";

// STEP 3: Create a test document with guaranteed correct data
echo "STEP 3: Creating a test document...\n";

// Start a transaction to ensure all operations succeed or fail together
$conn->begin_transaction();

try {
    // Insert a new document
    $title = "TEST DOCUMENT - WILL APPEAR IN INBOX - " . date('Y-m-d H:i:s');
    $insert_doc_query = "INSERT INTO documents (title, type_id, creator_id, status, created_at) 
        VALUES (?, 1, ?, 'pending', NOW())";
    $insert_doc_stmt = $conn->prepare($insert_doc_query);
    $insert_doc_stmt->bind_param("si", $title, $user_id);
    $insert_doc_stmt->execute();
    
    $document_id = $conn->insert_id;
    echo "Created document ID: $document_id\n";
    
    // Insert workflow step for the current office with status = 'current'
    $insert_workflow_query = "INSERT INTO document_workflow (document_id, office_id, step_order, status, assigned_at) 
        VALUES (?, ?, 1, 'current', NOW())";
    $insert_workflow_stmt = $conn->prepare($insert_workflow_query);
    $insert_workflow_stmt->bind_param("ii", $document_id, $office_id);
    $insert_workflow_stmt->execute();
    
    echo "Created workflow step for office ID: $office_id with status 'current'\n";
    
    // Commit the transaction
    $conn->commit();
    echo "Transaction committed successfully\n";
} catch (Exception $e) {
    // Roll back the transaction if something failed
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n";

// STEP 4: Verify the test document was created correctly
echo "STEP 4: Verifying test document...\n";

$verify_query = "SELECT d.document_id, d.title, d.status, dw.status as workflow_status 
    FROM documents d 
    JOIN document_workflow dw ON d.document_id = dw.document_id 
    WHERE d.document_id = $document_id";

$verify_result = $conn->query($verify_query);
if ($verify_result && $verify_result->num_rows > 0) {
    $verify_data = $verify_result->fetch_assoc();
    echo "Test document verified:\n";
    echo "- Document ID: {$verify_data['document_id']}\n";
    echo "- Title: {$verify_data['title']}\n";
    echo "- Document Status: {$verify_data['status']}\n";
    echo "- Workflow Status: {$verify_data['workflow_status']}\n";
} else {
    echo "Error verifying test document: " . $conn->error . "\n";
}

echo "\n";

// STEP 5: Create a direct link to the test document in the incoming page
echo "STEP 5: Creating direct link to test document...\n";

// Create a simple HTML file that will display the test document
$html_content = "<!DOCTYPE html>\n<html>\n<head>\n  <title>Test Document</title>\n  <style>\n    body { font-family: Arial, sans-serif; margin: 20px; }\n    .document { border: 1px solid #ccc; padding: 20px; margin-bottom: 20px; }\n    h1 { color: #2c3e50; }\n    .label { font-weight: bold; }\n    .action { display: inline-block; background: #3498db; color: white; padding: 10px; text-decoration: none; margin-top: 20px; }\n  </style>\n</head>\n<body>\n  <h1>Test Document</h1>\n  <div class='document'>\n    <p><span class='label'>Document ID:</span> $document_id</p>\n    <p><span class='label'>Title:</span> $title</p>\n    <p><span class='label'>Status:</span> pending</p>\n    <p><span class='label'>Workflow Status:</span> current</p>\n    <p><span class='label'>Office ID:</span> $office_id</p>\n  </div>\n  <a href='pages/incoming.php' class='action'>Go to Inbox</a>\n</body>\n</html>";

$html_file = __DIR__ . '/test_document.html';
file_put_contents($html_file, $html_content);

echo "Created test document HTML file at $html_file\n";
echo "You can view it at: http://localhost/SCCDMS2/test_document.html\n\n";

// STEP 6: Modify the incoming.php file to directly output the test document
echo "STEP 6: Creating a direct test page for the inbox...\n";

$test_page_content = "<?php\nrequire_once '../includes/config.php';\n\n// Set content type to plain text for easier reading\nheader('Content-Type: text/plain');\n\n// Start session to get user information\nsession_start();\n\n// Get user and office info\n\$user_id = \$_SESSION['user_id'] ?? 'Not set';\n\$office_id = \$_SESSION['office_id'] ?? 'Not set';\n\necho \"=== INBOX DIAGNOSTIC INFORMATION ===\\n\";\necho \"User ID: \$user_id\\n\";\necho \"Office ID: \$office_id\\n\\n\";\n\n// Run the exact query used in the notification counter\n\$notification_query = \"SELECT d.document_id, d.title, d.status, dw.status as workflow_status \\n    FROM documents d \\n    JOIN document_workflow dw ON d.document_id = dw.document_id \\n    WHERE dw.office_id = \$office_id AND dw.status = 'current'\";\n\necho \"Notification counter query: \$notification_query\\n\\n\";\n\n\$notification_result = \$conn->query(\$notification_query);\nif (\$notification_result) {\n    echo \"Number of documents found: \" . \$notification_result->num_rows . \"\\n\\n\";\n    \n    if (\$notification_result->num_rows > 0) {\n        echo \"Documents that should be in your inbox:\\n\";\n        while (\$row = \$notification_result->fetch_assoc()) {\n            echo \"- Document ID: {\$row['document_id']}, Title: {\$row['title']}, Status: {\$row['status']}, Workflow Status: {\$row['workflow_status']}\\n\";\n        }\n    } else {\n        echo \"No documents found that should be in your inbox.\\n\";\n    }\n} else {\n    echo \"Error querying documents: \" . \$conn->error . \"\\n\";\n}\n\necho \"\\n=== END OF DIAGNOSTIC INFORMATION ===\\n\";\n?>";\n\n$test_page_file = __DIR__ . '/pages/inbox_test.php';
file_put_contents($test_page_file, $test_page_content);

echo "Created inbox test page at $test_page_file\n";
echo "You can view it at: http://localhost/SCCDMS2/pages/inbox_test.php\n\n";

// STEP 7: Final instructions
echo "=== FINAL INSTRUCTIONS ===\n";
echo "1. A test document has been created with ID: $document_id\n";
echo "2. The document has been assigned to your office (ID: $office_id) with status 'current'\n";
echo "3. Please try these steps in order:\n";
echo "   a. First, visit http://localhost/SCCDMS2/test_document.html to see the test document\n";
echo "   b. Then, visit http://localhost/SCCDMS2/pages/inbox_test.php to see if the document appears in the diagnostic query\n";
echo "   c. Finally, visit http://localhost/SCCDMS2/pages/incoming.php to see if the document appears in your inbox\n";
echo "4. If the document appears in steps a and b but not c, there is an issue with the incoming.php page\n";
echo "   In that case, try clearing your browser cache or opening in a private/incognito window\n";

echo "\n=== DIRECT FIX COMPLETED ===\n";
?>
