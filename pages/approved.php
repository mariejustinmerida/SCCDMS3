<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- DATA FETCHING LOGIC ---
$approved_documents = [];
$total_records = 0;
$total_pages = 0;
$error_message = null;

$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'] ?? 0;

// Pagination and search settings
$items_per_page = isset($_GET['show']) ? (int)$_GET['show'] : 15;
$current_page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($current_page - 1) * $items_per_page;

try {
    // Define the base parts of the SQL query
    $sql_from = " FROM documents d
                  JOIN users u ON d.creator_id = u.user_id
                    JOIN offices o ON u.office_id = o.office_id 
                  JOIN document_types dt ON d.type_id = dt.type_id";
    $sql_where = " WHERE d.status = 'approved'";
    
    $params = [];
    $param_types = '';

    if (!empty($search_query)) {
        $search_term = "%" . $search_query . "%";
        $sql_where .= " AND (d.title LIKE ? OR u.full_name LIKE ? OR o.office_name LIKE ?)";
        array_push($params, $search_term, $search_term, $search_term);
        $param_types .= 'sss';
    }

    $count_sql = "SELECT COUNT(d.document_id) as total" . $sql_from . $sql_where;
    $count_stmt = $conn->prepare($count_sql);
    if($count_stmt) {
        if(!empty($params)) {
            $count_stmt->bind_param($param_types, ...$params);
        }
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_records = $count_result->fetch_assoc()['total'] ?? 0;
        $total_pages = ceil($total_records / $items_per_page);
        $count_stmt->close();
    } else {
        throw new Exception("Database error (count): " . $conn->error);
    }

    $sql_select = "SELECT d.document_id, d.title, dt.type_name, u.full_name as creator, o.office_name, d.created_at, d.updated_at";
    $sql_order_limit = " ORDER BY d.updated_at DESC LIMIT ?, ?";
    $sql = $sql_select . $sql_from . $sql_where . $sql_order_limit;
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $final_params = $params;
        $final_param_types = $param_types;
        $final_params[] = $offset;
        $final_params[] = $items_per_page;
        $final_param_types .= 'ii';
        
        $stmt->bind_param($final_param_types, ...$final_params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $approved_documents[] = $row;
        }
        $stmt->close();
    } else {
        throw new Exception("Database error (fetch): " . $conn->error);
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
    error_log("Error in pages/approved.php: " . $error_message);
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Plus+Jakarta+Sans%3Awght%40400%3B500%3B700%3B800" />
  <title>Approved Documents - SCC DMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    body { font-family: "Plus Jakarta Sans", "Noto Sans", sans-serif; }
    .sidebar { background: rgb(22, 59, 32); }
    .badge { display: inline-block; padding: 0.25em 0.6em; font-size: 75%; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.375rem; }
    .badge-approved { background-color: #D1FAE5; color: #065F46; }
    </style>
</head>
<body class="bg-gray-50">
  <div class="p-6">
    <div class="mb-6 flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold">Approved Documents</h1>
            <div class="flex items-center text-sm text-gray-500">
                <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
                <span class="mx-2">/</span>
                <a href="dashboard.php?page=documents" class="hover:text-gray-700">Documents</a>
                <span class="mx-2">/</span>
          <span>Approved</span>
        </div>
      </div>
       <div class="flex space-x-2">
        <button onclick="window.location.reload()" class="bg-green-700 text-white px-4 py-2 rounded-lg hover:bg-green-800 flex items-center">
          <i class="fas fa-sync-alt mr-2"></i> Refresh
        </button>
        <button id="exportBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
          <i class="fas fa-file-export mr-2"></i> Export
        </button>
            </div>
        </div>
        
    <?php if ($error_message): ?>
        <div class="mb-6 p-4 rounded-lg bg-red-100 text-red-800">
            <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
    <div class="w-full mb-6">
      <form method="GET" action="" class="relative" id="searchForm">
        <input type="hidden" name="page" value="approved">
        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search for documents by title, creator, or office..." class="w-full pl-10 pr-12 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 absolute left-3 top-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <button type="submit" class="absolute right-3 top-2 text-blue-600 hover:text-blue-800">
          <i class="fas fa-search"></i>
        </button>
        <?php if (!empty($search_query)): ?>
        <button type="button" onclick="clearSearch()" class="absolute right-10 top-2 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times"></i>
        </button>
        <?php endif; ?>
      </form>
    </div>

    <div class="bg-white rounded-lg shadow">
      <div class="p-4 border-b flex justify-between items-center">
        <div class="flex items-center gap-2">
          <span class="text-sm">Show</span>
          <form>
            <input type="hidden" name="page" value="approved">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
            <select name="show" class="border rounded px-2 py-1 text-sm" onchange="this.form.submit()">
                <option value="15" <?php echo $items_per_page == 15 ? 'selected' : ''; ?>>15</option>
                <option value="25" <?php echo $items_per_page == 25 ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo $items_per_page == 50 ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo $items_per_page == 100 ? 'selected' : ''; ?>>100</option>
            </select>
          </form>
          <span class="text-sm">entries</span>
                </div>
                <div>
          <span id="documentCount" class="text-sm text-gray-500"><?php echo $total_records; ?> document<?php echo $total_records != 1 ? 's' : ''; ?> found</span>
                </div>
            </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="text-left border-b bg-gray-50">
              <th class="p-4 font-medium">Code</th>
              <th class="p-4 font-medium">Creator</th>
              <th class="p-4 font-medium">Document Title</th>
              <th class="p-4 font-medium">Type</th>
              <th class="p-4 font-medium">From Office</th>
              <th class="p-4 font-medium">Approved On</th>
              <th class="p-4 font-medium">Status</th>
              <th class="p-4 font-medium text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($approved_documents)): ?>
                <?php foreach ($approved_documents as $doc): ?>
                    <tr class='hover:bg-gray-50 border-b'>
                        <td class='p-4 font-medium'>DOC-<?php echo str_pad($doc['document_id'], 3, '0', STR_PAD_LEFT); ?></td>
                        <td class='p-4'><?php echo htmlspecialchars($doc['creator']); ?></td>
                        <td class='p-4'><?php echo htmlspecialchars($doc['title']); ?></td>
                        <td class='p-4'><?php echo htmlspecialchars($doc['type_name']); ?></td>
                        <td class='p-4'><?php echo htmlspecialchars($doc['office_name']); ?></td>
                        <td class='p-4'><?php echo date('M j, Y, g:i a', strtotime($doc['updated_at'])); ?></td>
                        <td class='p-4'><span class='badge badge-approved'>Approved</span></td>
                        <td class='p-4'>
                            <div class='flex justify-center space-x-2'>
                                <a href='dashboard.php?page=view_document&id=<?php echo $doc['document_id']; ?>' class='bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 text-sm flex items-center'><i class='fas fa-eye mr-1'></i> View</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan='8' class='p-4 text-center text-gray-500'>No approved documents found.</td></tr>
            <?php endif; ?>
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
                <a href="?page=approved&p=1&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_query); ?>" class="px-3 py-1 border rounded hover:bg-gray-100">&laquo;</a>
                <a href="?page=approved&p=<?php echo $current_page - 1; ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_query); ?>" class="px-3 py-1 border rounded hover:bg-gray-100">&lsaquo;</a>
              <?php endif; ?>
              
              <?php
              $start_page = max(1, $current_page - 2);
              $end_page = min($start_page + 4, $total_pages);
              
              for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page=approved&p=<?php echo $i; ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_query); ?>" class="px-3 py-1 border rounded <?php echo $i == $current_page ? 'bg-blue-600 text-white' : 'hover:bg-gray-100'; ?>"><?php echo $i; ?></a>
              <?php endfor; ?>
              
              <?php if ($current_page < $total_pages): ?>
                <a href="?page=approved&p=<?php echo $current_page + 1; ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_query); ?>" class="px-3 py-1 border rounded hover:bg-gray-100">&rsaquo;</a>
                <a href="?page=approved&p=<?php echo $total_pages; ?>&show=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search_query); ?>" class="px-3 py-1 border rounded hover:bg-gray-100">&raquo;</a>
              <?php endif; ?>
                </div>
          <?php endif; ?>
        </div>
                </div>
        </div>
    </div>

    <script>
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
                    newRow.innerHTML = '<td colspan="8" class="p-4 text-center text-gray-500">No documents found matching your search.</td>';
                    table.appendChild(newRow);
                }
            } else if (noResultsRow) {
                noResultsRow.remove();
            }
        }

        // Export button functionality
        const exportBtn = document.getElementById('exportBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
            // Create a CSV from the table data
            const table = document.querySelector('table');
            if (!table) return;
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (const row of rows) {
                const cols = row.querySelectorAll('th, td');
                const rowData = [];
                
                for (const col of cols) {
                    // Skip the action column (8th column)
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
            link.setAttribute('download', 'approved_documents_' + new Date().toISOString().slice(0, 10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            });
        }

        // Auto-submit search on Enter key
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('searchForm').submit();
                    }
                });

                // Live search as user types
                searchInput.addEventListener('input', function(e) {
                    filterTable(e.target.value);
                });
            }
        });
    </script>
</body>
</html>
