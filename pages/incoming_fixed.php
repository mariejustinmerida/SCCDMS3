<?php
require_once '../includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'] ?? 0;

// Check if status column exists in document_workflow table
$check_column = "SHOW COLUMNS FROM document_workflow LIKE 'status'";
$column_result = $conn->query($check_column);
$has_status_column = ($column_result && $column_result->num_rows > 0);

// Pagination settings
$items_per_page = isset($_GET['show']) ? (int)$_GET['show'] : 15;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Search functionality
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
if (!empty($search_term)) {
    $search_term_escaped = '%' . $conn->real_escape_string($search_term) . '%';
    $search_condition = " AND (d.title LIKE '$search_term_escaped' OR 
                           dt.type_name LIKE '$search_term_escaped' OR 
                           u.full_name LIKE '$search_term_escaped' OR 
                           o.office_name LIKE '$search_term_escaped' OR
                           CONCAT('DOC-', LPAD(d.document_id, 3, '0')) LIKE '$search_term_escaped')"; 
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
    .badge {
      display: inline-block;
      padding: 0.25em 0.6em;
      font-size: 75%;
      font-weight: 700;
      line-height: 1;
      text-align: center;
      white-space: nowrap;
      vertical-align: baseline;
      border-radius: 0.375rem;
    }
    .badge-pending {
      background-color: #FEF3C7;
      color: #92400E;
    }
    .badge-approved {
      background-color: #D1FAE5;
      color: #065F46;
    }
    .badge-rejected {
      background-color: #FEE2E2;
      color: #B91C1C;
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
        <button onclick="window.location.reload()" class="bg-green-700 text-white px-4 py-2 rounded-lg hover:bg-green-800 flex items-center">
          <i class="fas fa-sync-alt mr-2"></i> Refresh
        </button>
      </div>
    </div>

    <!-- Status Messages -->
    <?php if (isset($_GET['status']) && isset($_GET['message'])): ?>
      <div class="mb-6 p-4 rounded-lg <?php echo $_GET['status'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
        <?php echo htmlspecialchars($_GET['message']); ?>
      </div>
    <?php endif; ?>

    <!-- Search Bar -->
    <div class="w-full mb-6">
      <form method="GET" action="" class="relative">
        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search for documents by title, type, requisitioner or office" class="w-full pl-10 pr-4 py-2 border rounded-lg">
        <svg xmlns="[http://www.w3.org/2000/svg"](http://www.w3.org/2000/svg") class="h-5 w-5 absolute left-3 top-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
            <option value="15" <?php echo $items_per_page == 15 ? 'selected' : ''; ?>>15</option>
            <option value="25" <?php echo $items_per_page == 25 ? 'selected' : ''; ?>>25</option>
            <option value="50" <?php echo $items_per_page == 50 ? 'selected' : ''; ?>>50</option>
            <option value="100" <?php echo $items_per_page == 100 ? 'selected' : ''; ?>>100</option>
          </select>
          <span class="text-sm">entries</span>
        </div>
        <div>
          <span id="documentCount" class="text-sm text-gray-500">Loading...</span>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="text-left border-b bg-gray-50">
              <th class="p-4 font-medium">Code</th>
              <th class="p-4 font-medium">Sender</th>
              <th class="p-4 font-medium">Document Title</th>
              <th class="p-4 font-medium">Type</th>
              <th class="p-4 font-medium">From Office</th>
              <th class="p-4 font-medium">Date</th>
              <th class="p-4 font-medium">Status</th>
              <th class="p-4 font-medium text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // Determine join statement based on status column existence
            if ($has_status_column) {
                // Dynamic query when status column exists
                $sql = "SELECT 
                          d.document_id, 
                          d.title, 
                          d.status, 
                          d.created_at,
                          dt.type_name, 
                          u.full_name as creator_name,
                          o.office_name as creator_office
                        FROM document_workflow dw
                        JOIN documents d ON dw.document_id = d.document_id
                        LEFT JOIN document_types dt ON d.type_id = dt.type_id
                        LEFT JOIN users u ON d.creator_id = u.user_id
                        LEFT JOIN offices o ON u.office_id = o.office_id
                        WHERE dw.office_id = $office_id
                        AND dw.status = 'CURRENT' 
                        $search_condition 
                        ORDER BY d.created_at DESC
                        LIMIT $offset, $items_per_page";
                
                // Count total records for pagination
                $count_sql = "SELECT COUNT(*) as total 
                             FROM document_workflow dw 
                             JOIN documents d ON dw.document_id = d.document_id 
                             WHERE dw.office_id = $office_id
                             AND dw.status = 'CURRENT'
                             $search_condition";
            } else {
                // Alternative query when status column doesn't exist
                // For simplicity, we will show any documents assigned to this office
                $sql = "SELECT 
                          d.document_id, 
                          d.title, 
                          d.status, 
                          d.created_at,
                          dt.type_name, 
                          u.full_name as creator_name,
                          o.office_name as creator_office
                        FROM document_workflow dw
                        JOIN documents d ON dw.document_id = d.document_id
                        LEFT JOIN document_types dt ON d.type_id = dt.type_id
                        LEFT JOIN users u ON d.creator_id = u.user_id
                        LEFT JOIN offices o ON u.office_id = o.office_id
                        WHERE dw.office_id = $office_id
                        $search_condition 
                        ORDER BY d.created_at DESC
                        LIMIT $offset, $items_per_page";
                
                // Count total records for pagination
                $count_sql = "SELECT COUNT(*) as total 
                             FROM document_workflow dw 
                             JOIN documents d ON dw.document_id = d.document_id 
                             WHERE dw.office_id = $office_id
                             $search_condition";
            }
            
            $count_result = $conn->query($count_sql);
            $total_records = ($count_result && $row = $count_result->fetch_assoc()) ? $row['total'] : 0;
            $total_pages = ceil($total_records / $items_per_page);
            
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $documentCode = 'DOC-' . str_pad($row['document_id'], 3, '0', STR_PAD_LEFT);
                    
                    // Determine badge class based on status
                    $status_class = match($row['status']) {
                        'approved' => 'badge-approved',
                        'rejected' => 'badge-rejected',
                        'on_hold' => 'badge-on_hold',
                        'hold' => 'badge-on_hold',
                        'revision' => 'badge-revision_requested',
                        'revision_requested' => 'badge-revision_requested',
                        default => 'badge-pending'
                    };
                    
                    $status_text = match($row['status']) {
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'on_hold' => 'On Hold',
                        'hold' => 'On Hold',
                        'revision' => 'Revision Requested',
                        'revision_requested' => 'Revision Requested',
                        default => 'Pending'
                    };
                    
                    echo "<tr class='hover:bg-gray-50 border-b'>";
                    echo "<td class='p-4 font-medium'>$documentCode</td>";
                    echo "<td class='p-4'>{$row['creator_name']}</td>";
                    echo "<td class='p-4'>{$row['title']}</td>";
                    echo "<td class='p-4'>{$row['type_name']}</td>";
                    echo "<td class='p-4'>{$row['creator_office']}</td>";
                    echo "<td class='p-4'>" . date('M j, Y', strtotime($row['created_at'])) . "</td>";
                    echo "<td class='p-4'><span class='badge $status_class'>$status_text</span></td>";
                    echo "<td class='p-4'>";
                    echo "<div class='flex justify-center space-x-2'>";
                    echo "<a href='dashboard.php?page=view_document&id={$row['document_id']}' class='bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 text-sm flex items-center'><i class='fas fa-eye mr-1'></i> View</a>";
                    echo "</div>";
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8' class='p-4 text-center text-gray-500'>No documents found</td></tr>";
                if ($conn->error) {
                    echo "<tr><td colspan='8' class='p-4 text-center text-red-500'>Error: {$conn->error}</td></tr>";
                }
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
        <div class="flex space-x-2">
          <?php if ($total_pages > 1): ?>
            <div class="flex space-x-1">
              <?php if ($current_page > 1): ?>
                <a href="?page=1&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" class="px-3 py-1 border rounded hover:bg-gray-100">&laquo;</a>
                <a href="?page=<?php echo $current_page - 1; ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" class="px-3 py-1 border rounded hover:bg-gray-100">&lsaquo;</a>
              <?php endif; ?>
              
              <?php
              $start_page = max(1, $current_page - 2);
              $end_page = min($start_page + 4, $total_pages);
              
              for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page=<?php echo $i; ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" class="px-3 py-1 border rounded <?php echo $i == $current_page ? 'bg-blue-600 text-white' : 'hover:bg-gray-100'; ?>"><?php echo $i; ?></a>
              <?php endfor; ?>
              
              <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?php echo $current_page + 1; ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" class="px-3 py-1 border rounded hover:bg-gray-100">&rsaquo;</a>
                <a href="?page=<?php echo $total_pages; ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" class="px-3 py-1 border rounded hover:bg-gray-100">&raquo;</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Update document count
    document.getElementById("documentCount").textContent = "<?php echo $total_records; ?> document<?php echo $total_records != 1 ? 's' : ''; ?> found";
    
    // Function to change entries per page
    function changeEntriesPerPage(value) {
      window.location.href = "?page=1&show=" + value + "&search=<?php echo urlencode($search_term); ?>";
    }
  </script>
</body>
</html> 