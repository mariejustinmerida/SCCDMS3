<?php
require_once 'includes/config.php';

// Set content type to HTML for better display
header('Content-Type: text/html');

// Start session to get user information
session_start();
$user_id = $_SESSION['user_id'] ?? null;
$office_id = $_SESSION['office_id'] ?? null;

echo "<html><head><title>Workflow Debug</title>
<style>
  body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
  h1, h2, h3 { color: #2c3e50; }
  pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow: auto; }
  .success { color: green; }
  .error { color: red; }
  .warning { color: orange; }
  .code { font-family: monospace; background: #f0f0f0; padding: 2px 4px; }
  table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
  th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
  th { background-color: #f2f2f2; }
  .button { display: inline-block; background: #3498db; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; margin-top: 10px; }
</style>
</head><body>
<h1>Workflow Debug Tool</h1>

";

if (!$user_id || !$office_id) {
    echo "<div class='error'>Error: User not logged in or office ID not set. Please <a href='auth/login.php'>log in</a> first.</div>";
    echo "</body></html>";
    exit;
}

echo "<p>User ID: $user_id</p>";
echo "<p>Office ID: $office_id</p>";

// First, let's check the incoming.php query directly
echo "<h2>1. Testing Incoming Documents Query</h2>";

$test_query = "SELECT d.document_id, d.title, d.type_id, d.file_path, d.created_at, d.status, 
               dt.type_name, u.full_name as creator_name, o.office_name as creator_office,
               CASE 
                   WHEN d.status = 'approved' THEN 'Approved'
                   WHEN d.status = 'rejected' THEN 'Rejected'
                   WHEN d.status = 'on_hold' THEN 'On Hold'
                   ELSE 'Pending'
               END as display_status
        FROM document_workflow dw 
        JOIN documents d ON dw.document_id = d.document_id 
        LEFT JOIN document_types dt ON d.type_id = dt.type_id 
        LEFT JOIN users u ON d.creator_id = u.user_id 
        LEFT JOIN offices o ON u.office_id = o.office_id 
        WHERE dw.office_id = $office_id AND dw.status = 'current'";

echo "<p>Running query:</p>";
echo "<pre>$test_query</pre>";

$test_result = $conn->query($test_query);

if ($test_result && $test_result->num_rows > 0) {
    echo "<p class='success'>Query returned {$test_result->num_rows} results</p>";
    
    echo "<table>";
    echo "<tr><th>Document ID</th><th>Title</th><th>Status</th><th>Workflow Status</th></tr>";
    
    while ($row = $test_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['document_id']}</td>";
        echo "<td>{$row['title']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['display_status']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p class='error'>Query returned no results or error: " . $conn->error . "</p>";
}

// Let's check the document_workflow table directly
echo "<h2>2. Document Workflow Table Analysis</h2>";

// Check if there are any workflow entries for this office
$workflow_query = "SELECT dw.*, d.title 
                  FROM document_workflow dw 
                  JOIN documents d ON dw.document_id = d.document_id 
                  WHERE dw.office_id = $office_id";

echo "<p>Running query:</p>";
echo "<pre>$workflow_query</pre>";

$workflow_result = $conn->query($workflow_query);

if ($workflow_result && $workflow_result->num_rows > 0) {
    echo "<p class='success'>Found {$workflow_result->num_rows} workflow entries for your office</p>";
    
    echo "<table>";
    echo "<tr><th>Workflow ID</th><th>Document ID</th><th>Title</th><th>Status</th><th>Step Order</th><th>Assigned At</th></tr>";
    
    while ($row = $workflow_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['workflow_id']}</td>";
        echo "<td>{$row['document_id']}</td>";
        echo "<td>{$row['title']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['step_order']}</td>";
        echo "<td>{$row['assigned_at']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p class='error'>No workflow entries found for your office: " . $conn->error . "</p>";
}

// Check for case sensitivity issues
echo "<h2>3. Case Sensitivity Check</h2>";

$case_query = "SELECT DISTINCT status FROM document_workflow WHERE office_id = $office_id";
echo "<p>Running query:</p>";
echo "<pre>$case_query</pre>";

$case_result = $conn->query($case_query);

if ($case_result && $case_result->num_rows > 0) {
    echo "<p>Found the following distinct status values:</p>";
    echo "<ul>";
    
    while ($row = $case_result->fetch_assoc()) {
        echo "<li>'{$row['status']}'</li>";
    }
    
    echo "</ul>";
} else {
    echo "<p class='error'>No status values found: " . $conn->error . "</p>";
}

// Check for any documents with current status (case insensitive)
echo "<h2>4. Case Insensitive Search</h2>";

$insensitive_query = "SELECT dw.*, d.title 
                     FROM document_workflow dw 
                     JOIN documents d ON dw.document_id = d.document_id 
                     WHERE dw.office_id = $office_id AND LOWER(dw.status) = 'current'";

echo "<p>Running query:</p>";
echo "<pre>$insensitive_query</pre>";

$insensitive_result = $conn->query($insensitive_query);

if ($insensitive_result && $insensitive_result->num_rows > 0) {
    echo "<p class='success'>Found {$insensitive_result->num_rows} workflow entries with case-insensitive 'current' status</p>";
    
    echo "<table>";
    echo "<tr><th>Workflow ID</th><th>Document ID</th><th>Title</th><th>Status</th><th>Step Order</th></tr>";
    
    while ($row = $insensitive_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['workflow_id']}</td>";
        echo "<td>{$row['document_id']}</td>";
        echo "<td>{$row['title']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['step_order']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p class='error'>No case-insensitive 'current' status entries found: " . $conn->error . "</p>";
}

// Let's try a direct fix
echo "<h2>5. Direct Fix</h2>";

// Create a direct link to the incoming page with debug info
echo "<p>Let's try a direct approach to fix the issue:</p>";

// Create a modified version of the incoming page
echo "<a href='direct_incoming.php' target='_blank' class='button'>Open Direct Incoming Page</a>";

// Create the direct_incoming.php file
$direct_incoming_content = "<?php\n";
$direct_incoming_content .= "require_once 'includes/config.php';\n";
$direct_incoming_content .= "require_once 'includes/file_helpers.php';\n\n";
$direct_incoming_content .= "// Start session\nsession_start();\n\n";
$direct_incoming_content .= "// Check if user is logged in\nif (!isset($_SESSION['user_id'])) {\n";
$direct_incoming_content .= "    header(\"Location: auth/login.php\");\n";
$direct_incoming_content .= "    exit();\n";
$direct_incoming_content .= "}\n\n";
$direct_incoming_content .= "// Get user information\n$user_id = $_SESSION['user_id'];\n";
$direct_incoming_content .= "$office_id = $_SESSION['office_id'] ?? 0;\n\n";
$direct_incoming_content .= "?><!DOCTYPE html>\n";
$direct_incoming_content .= "<html>\n";
$direct_incoming_content .= "<head>\n";
$direct_incoming_content .= "  <title>Direct Incoming Documents</title>\n";
$direct_incoming_content .= "  <script src=\"https://cdn.tailwindcss.com\"></script>\n";
$direct_incoming_content .= "  <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css\">\n";
$direct_incoming_content .= "</head>\n";
$direct_incoming_content .= "<body class=\"bg-gray-100 p-6\">\n";
$direct_incoming_content .= "  <div class=\"max-w-7xl mx-auto\">\n";
$direct_incoming_content .= "    <h1 class=\"text-2xl font-bold mb-6\">Direct Incoming Documents</h1>\n\n";
$direct_incoming_content .= "    <div class=\"bg-white shadow rounded-lg p-6 mb-6\">\n";
$direct_incoming_content .= "      <h2 class=\"text-xl font-semibold mb-4\">Debug Information</h2>\n";
$direct_incoming_content .= "      <p>User ID: <?php echo $user_id; ?></p>\n";
$direct_incoming_content .= "      <p>Office ID: <?php echo $office_id; ?></p>\n";
$direct_incoming_content .= "    </div>\n\n";
$direct_incoming_content .= "    <div class=\"bg-white shadow rounded-lg p-6\">\n";
$direct_incoming_content .= "      <h2 class=\"text-xl font-semibold mb-4\">Incoming Documents</h2>\n\n";
$direct_incoming_content .= "      <table class=\"w-full border-collapse\">\n";
$direct_incoming_content .= "        <thead>\n";
$direct_incoming_content .= "          <tr class=\"bg-gray-100\">\n";
$direct_incoming_content .= "            <th class=\"border p-2 text-left\">Document ID</th>\n";
$direct_incoming_content .= "            <th class=\"border p-2 text-left\">Title</th>\n";
$direct_incoming_content .= "            <th class=\"border p-2 text-left\">Type</th>\n";
$direct_incoming_content .= "            <th class=\"border p-2 text-left\">Creator</th>\n";
$direct_incoming_content .= "            <th class=\"border p-2 text-left\">Status</th>\n";
$direct_incoming_content .= "            <th class=\"border p-2 text-left\">Actions</th>\n";
$direct_incoming_content .= "          </tr>\n";
$direct_incoming_content .= "        </thead>\n";
$direct_incoming_content .= "        <tbody>\n";
$direct_incoming_content .= "          <?php\n";
$direct_incoming_content .= "          // SIMPLIFIED QUERY - just get the basics\n";
$direct_incoming_content .= "          $sql = \"SELECT d.document_id, d.title, d.status, dt.type_name, u.full_name as creator_name\n";
$direct_incoming_content .= "                 FROM document_workflow dw\n";
$direct_incoming_content .= "                 JOIN documents d ON dw.document_id = d.document_id\n";
$direct_incoming_content .= "                 LEFT JOIN document_types dt ON d.type_id = dt.type_id\n";
$direct_incoming_content .= "                 LEFT JOIN users u ON d.creator_id = u.user_id\n";
$direct_incoming_content .= "                 WHERE dw.office_id = $office_id AND LOWER(dw.status) = 'current'\n";
$direct_incoming_content .= "                 ORDER BY d.created_at DESC\";\n\n";
$direct_incoming_content .= "          $result = $conn->query($sql);\n\n";
$direct_incoming_content .= "          if ($result && $result->num_rows > 0) {\n";
$direct_incoming_content .= "              while($row = $result->fetch_assoc()) {\n";
$direct_incoming_content .= "                  echo \"<tr class='border-b hover:bg-gray-50'>\\n\";\n";
$direct_incoming_content .= "                  echo \"<td class='border p-2'>\".htmlspecialchars($row['document_id']).\"</td>\\n\";\n";
$direct_incoming_content .= "                  echo \"<td class='border p-2'>\".htmlspecialchars($row['title']).\"</td>\\n\";\n";
$direct_incoming_content .= "                  echo \"<td class='border p-2'>\".htmlspecialchars($row['type_name']).\"</td>\\n\";\n";
$direct_incoming_content .= "                  echo \"<td class='border p-2'>\".htmlspecialchars($row['creator_name']).\"</td>\\n\";\n";
$direct_incoming_content .= "                  echo \"<td class='border p-2'>\".htmlspecialchars($row['status']).\"</td>\\n\";\n";
$direct_incoming_content .= "                  echo \"<td class='border p-2'><a href='pages/dashboard.php?page=view_document&id={$row['document_id']}' class='bg-blue-500 text-white px-3 py-1 rounded'>View</a></td>\\n\";\n";
$direct_incoming_content .= "                  echo \"</tr>\\n\";\n";
$direct_incoming_content .= "              }\n";
$direct_incoming_content .= "          } else {\n";
$direct_incoming_content .= "              echo \"<tr><td colspan='6' class='border p-4 text-center'>No documents found. SQL Error: \" . $conn->error . \"</td></tr>\";\n";
$direct_incoming_content .= "          }\n";
$direct_incoming_content .= "          ?>\n";
$direct_incoming_content .= "        </tbody>\n";
$direct_incoming_content .= "      </table>\n";
$direct_incoming_content .= "    </div>\n\n";
$direct_incoming_content .= "    <div class=\"mt-6\">\n";
$direct_incoming_content .= "      <a href=\"pages/incoming.php\" class=\"bg-green-500 text-white px-4 py-2 rounded\">Go to Regular Inbox</a>\n";
$direct_incoming_content .= "    </div>\n";
$direct_incoming_content .= "  </div>\n";
$direct_incoming_content .= "</body>\n";
$direct_incoming_content .= "</html>\n";

$file_path = 'direct_incoming.php';
file_put_contents($file_path, $direct_incoming_content);

echo "<p class='success'>Created direct_incoming.php with a simplified approach</p>";

echo "</body></html>";
?>
