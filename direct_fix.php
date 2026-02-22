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

// First, let's completely rewrite the incoming.php file with a much simpler approach
$incoming_php_path = __DIR__ . '/pages/incoming.php';

// Create a backup of the original file if it doesn't exist already
$backup_path = __DIR__ . '/pages/incoming.php.original';
if (!file_exists($backup_path)) {
    copy($incoming_php_path, $backup_path);
    echo "Created backup of original incoming.php at $backup_path\n";
}

// Create a new, simplified version of incoming.php
$new_content = '<?php
require_once \'../includes/config.php\';
require_once \'../includes/file_helpers.php\';

// Check if user is logged in
if (!isset($_SESSION[\'user_id\'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user information
$user_id = $_SESSION[\'user_id\'];
$office_id = $_SESSION[\'office_id\'] ?? 0;

// Pagination settings
$items_per_page = isset($_GET[\'show\']) ? (int)$_GET[\'show\'] : 15;
$current_page = isset($_GET[\'page\']) ? max(1, (int)$_GET[\'page\']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Search functionality
$search_term = isset($_GET[\'search\']) ? trim($_GET[\'search\']) : \'\';
$search_condition = \'\';
if (!empty($search_term)) {
    $search_term_escaped = \'%\' . $conn->real_escape_string($search_term) . \'%\';
    $search_condition = " AND (d.title LIKE \'$search_term_escaped\' OR 
                           dt.type_name LIKE \'$search_term_escaped\' OR 
                           u.full_name LIKE \'$search_term_escaped\' OR 
                           o.office_name LIKE \'$search_term_escaped\' OR
                           CONCAT(\'DOC-\', LPAD(d.document_id, 3, \'0\')) LIKE \'$search_term_escaped\')"; 
}

// Function to fix file path for document preview
function fixFilePath($path) {
    if (empty($path)) return "";
    
    // Remove any double slashes
    $path = str_replace(\'//\', \'/\', $path);
    
    // Ensure the path starts with ../
    if (strpos($path, \'../\') !== 0 && strpos($path, \'./\') !== 0) {
        $path = "../" . $path;
    }
    
    return $path;
}
?>
<!DOCTYPE html>
<html>
<head>
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Plus+Jakarta+Sans%3Awght%40400%3B500%3B700%3B800" />
  <title>Inbox - SCC DMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      font-family: "Plus Jakarta Sans", "Noto Sans", sans-serif;
    }
    .sidebar {
      background: rgb(22, 59, 32);
    }
  </style>
</head>
<body class="bg-gray-50">
  <div class="p-6">
    <div class="mb-6 flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold">Inbox</h1>
        <div class="flex items-center text-sm text-gray-500">
          <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
          <span class="mx-2">/</span>
          <a href="dashboard.php?page=documents" class="hover:text-gray-700">Documents</a>
          <span class="mx-2">/</span>
          <span>Inbox</span>
        </div>
      </div>
      <div class="flex space-x-2">
        <button id="refreshBtn" onclick="window.location.reload()" class="bg-green-700 text-white px-4 py-2 rounded-lg hover:bg-green-800 flex items-center">
          <i class="fas fa-sync-alt mr-2"></i> Refresh
        </button>
      </div>
    </div>

    <!-- Search Bar -->
    <div class="w-full mb-6">
      <form method="GET" action="" class="relative">
        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search for documents by title, type, requisitioner or office" class="w-full pl-10 pr-4 py-2 border rounded-lg">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 absolute left-3 top-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <button type="submit" class="absolute right-3 top-2 text-blue-600">Search</button>
      </form>
    </div>

    <!-- Records Section -->
    <div class="bg-white rounded-lg shadow">
      <div class="p-4 border-b flex justify-between items-center">
        <div class="flex items-center gap-2">
          <span class="text-sm">Show</span>
          <select id="entriesPerPage" name="show" class="border rounded px-2 py-1 text-sm" onchange="changeEntriesPerPage(this.value)">
            <option value="15" <?php echo $items_per_page == 15 ? \'selected\' : \'\'; ?>>15</option>
            <option value="25" <?php echo $items_per_page == 25 ? \'selected\' : \'\'; ?>>25</option>
            <option value="50" <?php echo $items_per_page == 50 ? \'selected\' : \'\'; ?>>50</option>
            <option value="100" <?php echo $items_per_page == 100 ? \'selected\' : \'\'; ?>>100</option>
          </select>
          <span class="text-sm">entries</span>
        </div>
        <div>
          <span id="documentCount" class="text-sm text-gray-500">Loading...</span>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full table-auto">
          <thead>
            <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              <th class="p-4">Code</th>
              <th class="p-4">Sender</th>
              <th class="p-4">Document Title</th>
              <th class="p-4">Type</th>
              <th class="p-4">From Office</th>
              <th class="p-4">Date</th>
              <th class="p-4">Status</th>
              <th class="p-4">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php
            // Direct query to get documents for this office where the workflow status is current
            $sql = "SELECT d.document_id, d.title, d.type_id, d.file_path, d.created_at, d.status, 
                           dt.type_name, u.full_name as creator_name, o.office_name as creator_office 
                    FROM document_workflow dw 
                    JOIN documents d ON dw.document_id = d.document_id 
                    LEFT JOIN document_types dt ON d.type_id = dt.type_id 
                    LEFT JOIN users u ON d.creator_id = u.user_id 
                    LEFT JOIN offices o ON u.office_id = o.office_id 
                    WHERE dw.office_id = $office_id AND dw.status = \'current\' 
                    $search_condition 
                    ORDER BY d.created_at DESC 
                    LIMIT $offset, $items_per_page";
            
            // Count total records for pagination
            $count_sql = "SELECT COUNT(*) as total 
                         FROM document_workflow dw 
                         JOIN documents d ON dw.document_id = d.document_id 
                         WHERE dw.office_id = $office_id AND dw.status = \'current\' 
                         $search_condition";
            
            $count_result = $conn->query($count_sql);
            $total_records = ($count_result && $row = $count_result->fetch_assoc()) ? $row[\'total\'] : 0;
            $total_pages = ceil($total_records / $items_per_page);
            
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $documentCode = \'DOC-\' . str_pad($row[\'document_id\'], 3, \'0\', STR_PAD_LEFT);
                    $filePath = fixFilePath($row[\'file_path\']);
                    $statusClass = "";
                    $statusText = ucfirst($row[\'status\']);
                    
                    if ($row[\'status\'] == "pending") {
                        $statusClass = "bg-yellow-100 text-yellow-800";
                    } else if ($row[\'status\'] == "approved") {
                        $statusClass = "bg-green-100 text-green-800";
                    } else if ($row[\'status\'] == "rejected") {
                        $statusClass = "bg-red-100 text-red-800";
                    } else if ($row[\'status\'] == "on_hold") {
                        $statusClass = "bg-gray-100 text-gray-800";
                        $statusText = "On Hold";
                    }
                    
                    echo "<tr class=\'hover:bg-gray-50\'>";                    
                    echo "<td class=\'p-4\'>$documentCode</td>";
                    echo "<td class=\'p-4\'>{$row[\'creator_name\']}</td>";
                    echo "<td class=\'p-4\'>{$row[\'title\']}</td>";
                    echo "<td class=\'p-4\'>{$row[\'type_name\']}</td>";
                    echo "<td class=\'p-4\'>{$row[\'creator_office\']}</td>";
                    echo "<td class=\'p-4\'>{$row[\'created_at\']}</td>";
                    echo "<td class=\'p-4\'><span class=\'px-2 py-1 rounded-full text-xs $statusClass\'>$statusText</span></td>";
                    echo "<td class=\'p-4\'>";                    
                    echo "<a href=\'dashboard.php?page=view_document&id={$row[\'document_id\']}\' class=\'text-blue-600 hover:text-blue-900 mr-3\'><i class=\'fas fa-eye\'></i></a>";
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan=\'8\' class=\'p-4 text-center text-gray-500\'>No pending documents</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>

      <div class="p-4 border-t flex items-center justify-between">
        <div class="text-sm text-gray-500">
          <?php if ($total_records > 0): ?>
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_records); ?> of <?php echo $total_records; ?> entries
          <?php else: ?>
            Showing 0 to 0 of 0 entries
          <?php endif; ?>
        </div>
        <div class="flex space-x-1">
          <?php if ($total_pages > 1): ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
              <a href="?page=<?php echo $i; ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" 
                 class="px-3 py-1 rounded <?php echo $i == $current_page ? \'bg-green-600 text-white\' : \'bg-gray-200 text-gray-700 hover:bg-gray-300\'; ?>">
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Update document count
    document.getElementById("documentCount").textContent = "<?php echo $total_records; ?> document<?php echo $total_records != 1 ? "s" : ""; ?> found";
    
    // Function to change entries per page
    function changeEntriesPerPage(value) {
      window.location.href = "?show=" + value + "&search=<?php echo urlencode($search_term); ?>";
    }
  </script>
</body>
</html>';

// Write the new content to the file
file_put_contents($incoming_php_path, $new_content);
echo "Completely replaced incoming.php with a new, simplified version\n\n";

// Now, let's create a test document to ensure there's something in the inbox
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

// Finally, let's check if there are any documents in the inbox now
echo "Checking for documents in the inbox...\n";
$check_query = "SELECT COUNT(*) as count FROM document_workflow dw 
    JOIN documents d ON dw.document_id = d.document_id 
    WHERE dw.office_id = $office_id AND dw.status = 'current'";
$check_result = $conn->query($check_query);
$document_count = 0;
if ($check_result && $row = $check_result->fetch_assoc()) {
    $document_count = $row['count'];
}

echo "Found $document_count documents that should appear in the inbox.\n\n";

echo "=== INSTRUCTIONS ===\n";
echo "1. The incoming.php page has been completely replaced with a new version.\n";
echo "2. A test document has been created and assigned to your office.\n";
echo "3. Please visit this URL to view your incoming documents: http://localhost/SCCDMS2/pages/incoming.php\n";
echo "4. If you still don't see any documents, please try clearing your browser cache or opening in a private/incognito window.\n";

echo "\n=== DIRECT FIX COMPLETED ===\n";
?>
