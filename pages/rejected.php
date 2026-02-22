<?php
require_once '../includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'] ?? 0;

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
                           CONCAT('DOC-', LPAD(d.document_id, 3, '0')) LIKE '$search_term_escaped')"; 
}

// Get rejected documents
$count_sql = "SELECT COUNT(DISTINCT d.document_id) as total 
             FROM documents d
             WHERE d.status = 'rejected' 
             AND d.creator_id = ?$search_condition";

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();

if (!$count_result) {
  $total_records = 0;
} else {
  $total_records = $count_result->fetch_assoc()['total'];
}
$total_pages = ceil($total_records / $items_per_page);

// Get rejected documents with details
$sql = "SELECT d.document_id, d.title, dt.type_name, d.created_at, d.status, d.google_doc_id,
        (SELECT o.office_name FROM document_workflow dw JOIN offices o ON dw.office_id = o.office_id 
         WHERE dw.document_id = d.document_id AND dw.status = 'REJECTED' 
         ORDER BY dw.created_at DESC LIMIT 1) as rejecting_office,
        (SELECT dw.comments FROM document_workflow dw 
         WHERE dw.document_id = d.document_id AND dw.status = 'REJECTED'
         ORDER BY dw.created_at DESC LIMIT 1) as rejection_reason,
        (SELECT COUNT(*) FROM document_workflow dw 
         WHERE dw.document_id = d.document_id AND dw.status = 'REJECTED' AND dw.comments IS NOT NULL AND dw.comments != '') as has_comments
        FROM documents d
        JOIN document_types dt ON d.type_id = dt.type_id 
        WHERE d.status = 'rejected' AND d.creator_id = ?$search_condition
        ORDER BY d.created_at DESC
        LIMIT $offset, $items_per_page";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Plus+Jakarta+Sans%3Awght%40400%3B500%3B700%3B800" />
  <title>Rejected Documents - SCC DMS</title>
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
    .badge-rejected {
      background-color: #FEE2E2;
      color: #B91C1C;
    }
    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(255, 255, 255, 0.8);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      visibility: hidden;
      opacity: 0;
      transition: visibility 0s, opacity 0.3s;
    }
    .loading-overlay.active {
      visibility: visible;
      opacity: 1;
    }
  </style>
</head>
<body class="bg-gray-50">
  <!-- Loading Overlay -->
  <div id="loadingOverlay" class="loading-overlay">
    <div class="bg-white p-5 rounded-lg shadow-lg flex flex-col items-center">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-red-700 mb-3"></div>
      <p class="text-gray-700">Loading...</p>
    </div>
  </div>

  <div class="flex pt-[0px]">
    <main class="flex-1 ml-0 p-6">
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold">Rejected Documents</h1>
          <div class="flex items-center text-sm text-gray-500">
            <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
            <span class="mx-2">/</span>
            <span>Rejected Documents</span>
          </div>
        </div>
        <div class="flex space-x-2">
          <button id="refreshBtn" class="bg-red-700 text-white px-4 py-2 rounded-lg hover:bg-red-800 flex items-center">
            <i class="fas fa-sync-alt mr-2"></i> Refresh
          </button>
          <button id="exportBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
            <i class="fas fa-file-export mr-2"></i> Export
          </button>
          <a href="dashboard.php?page=compose" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 flex items-center">
            <i class="fas fa-plus mr-2"></i> New Document
          </a>
        </div>
      </div>

      <!-- Search Bar -->
      <div class="w-full mb-6">
        <form method="GET" action="" class="relative" id="searchForm">
          <input type="hidden" name="page" value="rejected">
          <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search for documents by title, type, or code" class="w-full pl-10 pr-12 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 absolute left-3 top-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <button type="submit" class="absolute right-3 top-2 text-blue-600 hover:text-blue-800">
            <i class="fas fa-search"></i>
          </button>
          <?php if (!empty($search_term)): ?>
          <button type="button" onclick="clearSearch()" class="absolute right-10 top-2 text-gray-400 hover:text-gray-600">
            <i class="fas fa-times"></i>
          </button>
          <?php endif; ?>
        </form>
      </div>

      <!-- Explanation Banner -->
      <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 text-red-700">
        <p class="font-medium">Rejected Documents</p>
        <p>These documents have been rejected by their respective offices. Review the rejection reasons and consider creating a new document if needed.</p>
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
          <div id="documentCount" class="text-sm text-gray-600 font-medium"></div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="text-left border-b bg-gray-50">
                <th class="p-4 font-medium">Code</th>
                <th class="p-4 font-medium">Title</th>
                <th class="p-4 font-medium">Type</th>
                <th class="p-4 font-medium">Rejected By</th>
                <th class="p-4 font-medium">Rejection Reason</th>
                <th class="p-4 font-medium">Date</th>
                <th class="p-4 font-medium text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): 
                  $has_comments = !empty($row['rejection_reason']) || $row['has_comments'] > 0;
                ?>
                  <tr class="hover:bg-gray-50 border-b <?php echo $has_comments ? 'bg-red-50' : ''; ?>">
                    <td class="p-4 font-medium">DOC-<?php echo str_pad($row['document_id'], 3, '0', STR_PAD_LEFT); ?></td>
                    <td class="p-4"><?php echo htmlspecialchars($row['title']); ?></td>
                    <td class="p-4"><?php echo htmlspecialchars($row['type_name']); ?></td>
                    <td class="p-4"><?php echo htmlspecialchars($row['rejecting_office'] ?? 'Unknown'); ?></td>
                    <td class="p-4">
                      <?php if (!empty($row['rejection_reason'])): ?>
                        <div class="max-h-20 overflow-y-auto text-sm">
                          <?php echo nl2br(htmlspecialchars(substr($row['rejection_reason'], 0, 100) . (strlen($row['rejection_reason']) > 100 ? '...' : ''))); ?>
                        </div>
                      <?php elseif($row['has_comments'] > 0): ?>
                        <span class="text-red-600 text-sm">Has comments (view in tracking)</span>
                      <?php else: ?>
                        <span class="text-gray-400 italic">No reason provided</span>
                      <?php endif; ?>
                    </td>
                    <td class="p-4"><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                    <td class="p-4">
                      <div class="flex justify-center space-x-2">
                        <a href="dashboard.php?page=compose&clone=<?php echo $row['document_id']; ?>" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 text-sm flex items-center">
                          <i class="fas fa-clone mr-1"></i> Clone
                        </a>
                        <a href="dashboard.php?page=track&id=<?php echo $row['document_id']; ?>" class="bg-gray-600 text-white px-3 py-1 rounded hover:bg-gray-700 text-sm flex items-center">
                          <i class="fas fa-route mr-1"></i> Track
                        </a>
                        <?php if (!empty($row['google_doc_id'])): ?>
                        <a href="https://docs.google.com/document/d/<?php echo htmlspecialchars($row['google_doc_id']); ?>/edit" target="_blank" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 text-sm flex items-center">
                          <i class="fas fa-external-link-alt mr-1"></i> View
                        </a>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="7" class="text-center py-8 text-gray-500">No rejected documents found</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="p-4 border-t flex items-center justify-between">
          <div class="text-sm text-gray-500">
            <?php if ($total_records > 0): ?>
              Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_records); ?> of <?php echo $total_records; ?> entries
            <?php else: ?>
              No records found
            <?php endif; ?>
          </div>
          <?php if ($total_pages > 1): ?>
          <div class="flex items-center gap-2">
            <a href="?page=rejected&p=<?php echo max(1, $current_page - 1); ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" 
               class="px-2 py-1 border rounded <?php echo $current_page == 1 ? 'text-gray-400' : 'hover:bg-gray-100'; ?>"
               <?php echo $current_page == 1 ? 'aria-disabled="true"' : ''; ?>>&lt;</a>
            
            <?php
            $start_page = max(1, min($current_page - 2, $total_pages - 4));
            $end_page = min($total_pages, max($current_page + 2, 5));
            
            for ($i = $start_page; $i <= $end_page; $i++):
            ?>
              <a href="?page=rejected&p=<?php echo $i; ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" 
                 class="px-2 py-1 border rounded <?php echo $i == $current_page ? 'bg-red-700 text-white' : 'hover:bg-gray-100'; ?>">
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>
            
            <a href="?page=rejected&p=<?php echo min($total_pages, $current_page + 1); ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" 
               class="px-2 py-1 border rounded <?php echo $current_page == $total_pages ? 'text-gray-400' : 'hover:bg-gray-100'; ?>"
               <?php echo $current_page == $total_pages ? 'aria-disabled="true"' : ''; ?>>&gt;</a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <script>
    // Update document count
    document.getElementById('documentCount').textContent = '<?php echo $total_records; ?> document<?php echo $total_records != 1 ? "s" : ""; ?> found';
    
    // Function to change entries per page
    function changeEntriesPerPage(value) {
      const url = new URL(window.location.href);
      url.searchParams.set('show', value);
      url.searchParams.set('p', 1); // Reset to first page
      window.location.href = url.toString();
    }
    
    // Refresh button functionality
    document.getElementById('refreshBtn').addEventListener('click', () => {
      const loadingOverlay = document.getElementById('loadingOverlay');
      loadingOverlay.classList.add('active');
      
      setTimeout(() => {
        window.location.reload();
      }, 500);
    });
    
    // Export button functionality
    document.getElementById('exportBtn').addEventListener('click', () => {
      // Create a CSV from the table data
      const table = document.querySelector('table');
      let csv = [];
      const rows = table.querySelectorAll('tr');
      
      for (const row of rows) {
        const cols = row.querySelectorAll('th, td');
        const rowData = [];
        
        for (const col of cols) {
          // Skip the action column
          if (col.cellIndex !== 6) {
            rowData.push('"' + (col.textContent.trim().replace(/"/g, '""')) + '"');
          }
        }
        
        csv.push(rowData.join(','));
      }
      
      // Create and download the CSV file
      const csvContent = csv.join('\n');
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', 'rejected_documents_' + new Date().toISOString().slice(0, 10) + '.csv');
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    });

    // Clear search function
    function clearSearch() {
        document.getElementById('search').value = '';
        filterTable('');
    }

    // Live search functionality
    function filterTable(searchTerm) {
        const table = document.querySelector('table tbody');
        if (!table) return;
        
        const rows = table.querySelectorAll('tr');
        const term = searchTerm.toLowerCase();
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            let matches = false;
            
            // Check each cell for the search term
            cells.forEach(cell => {
                if (cell.textContent.toLowerCase().includes(term)) {
                    matches = true;
                }
            });
            
            // Show/hide row based on match
            row.style.display = matches ? '' : 'none';
        });
        
        // Update "No results" message if needed
        const visibleRows = table.querySelectorAll('tr[style=""], tr:not([style])');
        const noResultsRow = table.querySelector('.no-results-row');
        
        if (visibleRows.length === 0 && term.length > 0) {
            if (!noResultsRow) {
                const newRow = document.createElement('tr');
                newRow.className = 'no-results-row';
                newRow.innerHTML = '<td colspan="7" class="p-4 text-center text-gray-500">No documents found matching your search.</td>';
                table.appendChild(newRow);
            }
        } else if (noResultsRow) {
            noResultsRow.remove();
        }
    }

    // Auto-submit search on Enter key
    document.getElementById('search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('searchForm').submit();
        }
    });

    // Live search as user types
    document.getElementById('search').addEventListener('input', function(e) {
        filterTable(e.target.value);
    });
  </script>
</body>
</html> 