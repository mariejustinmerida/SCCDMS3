<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

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
                           o.office_name LIKE '$search_term_escaped' OR
                           CONCAT('DOC-', LPAD(d.document_id, 3, '0')) LIKE '$search_term_escaped')"; 
}
?>
<!DOCTYPE html>
<html>
<head>
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Plus+Jakarta+Sans%3Awght%40400%3B500%3B700%3B800" />
  <title>On Hold - SCC DMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      font-family: "Plus Jakarta Sans", "Noto Sans", sans-serif;
    }
    .sidebar {
      background: rgb(22, 59, 32);
    }
    body.dark {
      background-color: #1E1E1E;
      color: #ffffff;
    }
    body.dark .bg-white {
      background-color: #1a1a1a;
    }
    .table-header {
      background-color: #f8f9fa;
    }
    body.dark .table-header {
      background-color: #2d2d2d;
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
    .badge-hold {
      background-color: #E0E7FF;
      color: #4338CA;
    }
    .hover-actions {
      visibility: hidden;
    }
    tr:hover .hover-actions {
      visibility: visible;
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
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-700 mb-3"></div>
      <p class="text-gray-700">Loading...</p>
    </div>
  </div>

  <div class="flex pt-[0px]">
    <main class="flex-1 ml-0 p-6">
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold">Documents On Hold</h1>
          <div class="flex items-center text-sm text-gray-500">
            <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
            <span class="mx-2">/</span>
            <span>On Hold</span>
          </div>
        </div>
        <div class="flex space-x-2">
          <button id="refreshBtn" class="bg-green-700 text-white px-4 py-2 rounded-lg hover:bg-green-800 flex items-center">
            <i class="fas fa-sync-alt mr-2"></i> Refresh
          </button>
          <button id="exportBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
            <i class="fas fa-file-export mr-2"></i> Export
          </button>
        </div>
      </div>

      <!-- Status Messages -->
      <?php if (isset($_GET['status']) && isset($_GET['message'])): ?>
        <div class="mb-6 <?php echo $_GET['status'] === 'success' ? 'bg-green-50 border-l-4 border-green-500 text-green-700' : 'bg-red-50 border-l-4 border-red-500 text-red-700'; ?> p-4 rounded-r-md shadow-sm">
          <div class="flex">
            <div class="flex-shrink-0">
              <?php if ($_GET['status'] === 'success'): ?>
                <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              <?php else: ?>
                <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
              <?php endif; ?>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium"><?php echo htmlspecialchars(urldecode($_GET['message'])); ?></p>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Search Bar -->
      <div class="w-full mb-6">
        <form method="GET" action="" class="relative" id="searchForm">
          <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search for documents by title, type, or office" class="w-full pl-10 pr-12 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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

      <!-- Records Section -->
      <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b flex justify-between items-center">
          <div class="flex items-center gap-2">
            <span class="text-sm">Show</span>
            <select id="entriesPerPage" name="show" class="border rounded px-2 py-1 text-sm" onchange="changeEntriesPerPage(this.value)">
              <option value="15" <?php echo $items_per_page == 15 ? 'selected' : ''; ?>>15</option>
              <option value="25" <?php echo $items_per_page == 25 ? 'selected' : ''; ?>>25</option>
              <option value="50" <?php echo $items_per_page == 50 ? 'selected' : ''; ?>>50</option>
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
                <th class="p-4 font-medium">Current Office</th>
                <th class="p-4 font-medium">Status</th>
                <th class="p-4 font-medium">Hold Date</th>
                <th class="p-4 font-medium">Hold Reason</th>
                <th class="p-4 font-medium text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              if (!isset($_SESSION['office_id'])) {
                  echo "<tr><td colspan='8' class='text-center py-8 text-gray-500'>Please log in first</td></tr>";
                  exit();
              }

              // Count total records for pagination
              $current_office_id = (int)$_SESSION['office_id'];
              $count_sql = "SELECT COUNT(DISTINCT d.document_id) as total 
                           FROM documents d
                           JOIN document_types dt ON d.type_id = dt.type_id
                           JOIN document_workflow dw ON d.document_id = dw.document_id
                           JOIN offices o ON dw.office_id = o.office_id
                           WHERE dw.office_id = $current_office_id 
                           AND UPPER(dw.status) IN ('ON_HOLD','HOLD')
                           $search_condition";
              
              $count_result = $conn->query($count_sql);
              $total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
              $total_pages = ceil($total_records / $items_per_page);

              // Main query to fetch on-hold documents for the current user's office
              // Documents are on hold when workflow status is ON_HOLD
              $sql = "SELECT d.document_id, d.title, dt.type_name, d.status,
                             o.office_name as current_office,
                             h.hold_date,
                             h.hold_reason
                      FROM documents d
                      JOIN document_types dt ON d.type_id = dt.type_id
                      JOIN document_workflow dw ON d.document_id = dw.document_id
                      JOIN offices o ON dw.office_id = o.office_id
                      LEFT JOIN (
                          SELECT x.document_id,
                                 x.hold_date,
                                 TRIM(
                                   CASE 
                                     WHEN x.details IS NULL OR x.details = '' THEN ''
                                     WHEN LOCATE('Reason:', x.details) > 0 THEN SUBSTRING(x.details, LOCATE('Reason:', x.details) + 8)
                                     ELSE x.details
                                   END
                                 ) AS hold_reason
                          FROM (
                               SELECT dl1.document_id,
                                      MAX(dl1.created_at) AS hold_date,
                                      SUBSTRING_INDEX(MAX(CONCAT(dl1.created_at, '|||', COALESCE(dl1.details, ''))), '|||', -1) AS details
                               FROM document_logs dl1
                               WHERE dl1.action = 'hold'
                               GROUP BY dl1.document_id
                          ) x
                      ) h ON d.document_id = h.document_id
                      WHERE dw.office_id = $current_office_id 
                      AND UPPER(dw.status) IN ('ON_HOLD','HOLD')
                      $search_condition
                      ORDER BY COALESCE(h.hold_date, d.updated_at, d.created_at) DESC
                      LIMIT $offset, $items_per_page";

              $result = $conn->query($sql);
              if (!$result) {
                error_log("Hold page main query error: " . $conn->error);
              }

              if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                  echo "<tr class='hover:bg-gray-50 border-b document-row'>";
                  echo "<td class='p-4 font-medium'>DOC-" . str_pad($row['document_id'], 3, '0', STR_PAD_LEFT) . "</td>";
                  echo "<td class='p-4 document-title'>" . htmlspecialchars($row['title']) . "</td>";
                  echo "<td class='p-4'>" . htmlspecialchars($row['type_name']) . "</td>";
                  echo "<td class='p-4'>" . htmlspecialchars($row['current_office'] ?? 'N/A') . "</td>";
                  echo "<td class='p-4'><span class='badge badge-hold'>On Hold</span></td>";
                  echo "<td class='p-4'>" . (!empty($row['hold_date']) ? date('M j, Y', strtotime($row['hold_date'])) : 'N/A') . "</td>";
                  echo "<td class='p-4'>" . htmlspecialchars(!empty($row['hold_reason']) ? $row['hold_reason'] : 'No reason provided') . "</td>";
                  echo "<td class='p-4'>";
                  echo "<div class='flex justify-center space-x-2'>";
                  echo "<a href='dashboard.php?page=track&id=" . $row['document_id'] . "' class='bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 text-sm flex items-center'><i class='fas fa-route mr-1'></i> Track</a>";
                  // Simple form-based resume action
                  echo "<form method='POST' action='resume_document.php' class='hover-actions'>";
                  echo "<input type='hidden' name='document_id' value='" . $row['document_id'] . "'>";
                  echo "<button type='submit' name='resume' class='bg-purple-600 text-white px-3 py-1 rounded hover:bg-purple-700 text-sm flex items-center'><i class='fas fa-play mr-1'></i> Resume</button>";
                  echo "</form>";
                  echo "</div>";
                  echo "</td>";
                  echo "</tr>";
                }
              } else {
                echo "<tr><td colspan='8' class='text-center py-8 text-gray-500'>No documents on hold</td></tr>";
              }
              ?>
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
            <a href="?page=<?php echo max(1, $current_page - 1); ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" 
               class="px-2 py-1 border rounded <?php echo $current_page == 1 ? 'text-gray-400' : 'hover:bg-gray-100'; ?>"
               <?php echo $current_page == 1 ? 'aria-disabled="true"' : ''; ?>>&lt;</a>
            
            <?php
            $start_page = max(1, min($current_page - 2, $total_pages - 4));
            $end_page = min($total_pages, max($current_page + 2, 5));
            
            for ($i = $start_page; $i <= $end_page; $i++):
            ?>
              <a href="?page=<?php echo $i; ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" 
                 class="px-2 py-1 border rounded <?php echo $i == $current_page ? 'bg-green-700 text-white' : 'hover:bg-gray-100'; ?>">
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>
            
            <a href="?page=<?php echo min($total_pages, $current_page + 1); ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_term); ?>" 
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
      url.searchParams.set('page', 1); // Reset to first page
      window.location.href = url.toString();
    }
    
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

    // Search functionality
    const searchForm = document.querySelector('form');
    searchForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const searchTerm = document.getElementById('search').value.trim();
      const url = new URL(window.location.href);
      
      if (searchTerm) {
        url.searchParams.set('search', searchTerm);
      } else {
        url.searchParams.delete('search');
      }
      
      url.searchParams.set('page', 1); // Reset to first page
      window.location.href = url.toString();
    });
    
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
          if (col.cellIndex !== 7) {
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
      link.setAttribute('download', 'hold_documents_' + new Date().toISOString().slice(0, 10) + '.csv');
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    });
  </script>
</body>
</html>