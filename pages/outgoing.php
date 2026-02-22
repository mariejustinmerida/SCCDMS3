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
  <title>Outgoing - SCC DMS</title>
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
    .badge-revision_requested {
      background-color: #F3E8FF;
      color: #7E22CE;
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
    <main id="page-content" class="flex-1 ml-0 p-6">
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold">Outgoing</h1>
          <div class="flex items-center text-sm text-gray-500">
            <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
            <span class="mx-2">/</span>
            <span>Outgoing</span>
          </div>
        </div>
        <div class="flex space-x-2">
          <button id="refreshBtn" class="bg-green-700 text-white px-4 py-2 rounded-lg hover:bg-green-800 flex items-center">
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
            <select id="entriesPerPage" name="show" class="border rounded px-2 py-1 text-sm">
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
                <th class="p-4 font-medium">Date Sent</th>
                <th class="p-4 font-medium text-center">Actions</th>
              </tr>
            </thead>
            <tbody id="documents-tbody">
              <?php
              if (!isset($_SESSION['office_id'])) {
                  echo "<tr><td colspan='7' class='text-center py-8 text-gray-500'>Please log in first</td></tr>";
                  exit();
              }

              // Count total records for pagination - exclude approved and rejected documents
              $count_sql = "SELECT COUNT(DISTINCT d.document_id) as total FROM documents d
                           JOIN document_types dt ON d.type_id = dt.type_id 
                           JOIN users u ON d.creator_id = u.user_id
                           LEFT JOIN document_workflow dw ON d.document_id = dw.document_id AND (dw.status = 'current' OR dw.status = 'on_hold' OR dw.status = 'ON_HOLD')
                           LEFT JOIN offices o ON dw.office_id = o.office_id
                           WHERE u.office_id = {$_SESSION['office_id']} 
                           AND d.status != 'approved' 
                           AND d.status != 'rejected'$search_condition";
              
              $count_result = $conn->query($count_sql);
              if (!$count_result) {
                $total_records = 0;
              } else {
                $total_records = $count_result->fetch_assoc()['total'];
              }
              $total_pages = ceil($total_records / $items_per_page);

              $sql = "SELECT d.document_id, d.title, dt.type_name, 
                      COALESCE(o.office_name, 'None') as current_office, 
                      CASE 
                          WHEN d.status IS NULL OR d.status = '' THEN 'pending'
                          ELSE d.status
                      END as status,
                      d.created_at,
                      d.is_memorandum,
                      d.memorandum_sent_to_all_offices,
                      d.memorandum_total_offices,
                      d.memorandum_read_offices,
                      CASE 
                          WHEN d.is_memorandum = 1 AND d.memorandum_sent_to_all_offices = 1 THEN 'Sent to All Offices'
                          WHEN d.status = 'rejected' THEN 'Rejected'
                          WHEN d.status = 'on_hold' OR d.status = 'ON_HOLD' OR LOWER(d.status) = 'hold' THEN 
                              COALESCE(
                                (SELECT CONCAT('On Hold at ', 
                                    SUBSTRING_INDEX(
                                        SUBSTRING_INDEX(details, 'Document put on hold by ', -1), 
                                        ':', 1
                                    )
                                ) 
                                FROM document_logs 
                                WHERE document_id = d.document_id AND action = 'hold' 
                                ORDER BY created_at DESC LIMIT 1),
                                'On Hold'
                              )
                          WHEN d.status = 'revision_requested' THEN 'Revision Requested'
                          ELSE COALESCE(o.office_name, 'Pending Assignment')
                      END as display_office
                      FROM documents d
                      JOIN document_types dt ON d.type_id = dt.type_id 
                      JOIN users u ON d.creator_id = u.user_id
                      LEFT JOIN document_workflow dw ON d.document_id = dw.document_id AND (dw.status = 'current' OR dw.status = 'on_hold' OR dw.status = 'ON_HOLD')
                      LEFT JOIN offices o ON dw.office_id = o.office_id
                      WHERE u.office_id = {$_SESSION['office_id']} 
                      AND d.status != 'approved' 
                      AND d.status != 'rejected'$search_condition
                      GROUP BY d.document_id
                      ORDER BY d.created_at DESC
                      LIMIT $offset, $items_per_page";

              $result = $conn->query($sql);

              if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                  $status_class = match($row['status']) {
                    'approved' => 'badge-approved',
                    'rejected' => 'badge-rejected',
                    'pending' => 'badge-pending',
                    'on_hold' => 'badge-hold',
                    'ON_HOLD' => 'badge-hold',
                    'hold' => 'badge-hold',
                    'revision_requested' => 'badge-revision_requested',
                    default => 'badge-pending'
                  };
                  
                  // Ensure status text is properly displayed
                  $status_text = match($row['status']) {
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'pending' => 'Pending',
                    'on_hold' => 'On Hold',
                    'ON_HOLD' => 'On Hold',
                    'hold' => 'On Hold',
                    'revision_requested' => 'Revision Requested',
                    '' => 'Pending',
                    null => 'Pending',
                    default => 'Pending'
                  };
                  
                  // Check if this is a memorandum
                  $isMemorandum = isset($row['is_memorandum']) && $row['is_memorandum'];
                  $memorandumClass = $isMemorandum ? 'memorandum-row border-l-4 border-l-blue-500' : '';
                  
                  echo "<tr class='hover:bg-gray-50 border-b document-row $memorandumClass' data-document-id='" . $row['document_id'] . "' data-is-memorandum='" . ($isMemorandum ? '1' : '0') . "'>";
                  echo "<td class='p-4 font-medium'>DOC-" . str_pad($row['document_id'], 3, '0', STR_PAD_LEFT) . "</td>";
                  echo "<td class='p-4 document-title'>" . htmlspecialchars($row['title']) . "</td>";
                  echo "<td class='p-4'>" . htmlspecialchars($row['type_name']) . "</td>";
                  $office_name_escaped = htmlspecialchars($row['display_office'], ENT_QUOTES, 'UTF-8');
                  $title_escaped_for_workflow = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8');
                  echo "<td class='p-4'><span class='cursor-pointer text-blue-600 hover:text-blue-800 hover:underline font-medium workflow-office-link' data-document-id='{$row['document_id']}' data-office-name='{$office_name_escaped}' data-document-title='{$title_escaped_for_workflow}' title='Click to view workflow route'>{$office_name_escaped}</span></td>";
                  echo "<td class='p-4'><span class='badge $status_class'>" . $status_text . "</span></td>";
                  echo "<td class='p-4'>" . date('M j, Y', strtotime($row['created_at'])) . "</td>";
                  echo "<td class='p-4'>";
                  echo "<div class='flex justify-center space-x-2'>";
                  echo "<a href='dashboard.php?page=track&id=" . $row['document_id'] . "' class='bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 text-sm flex items-center'><i class='fas fa-route mr-1'></i> Track</a>";
                  
                  // Add memorandum progress button for memorandums
                  if ($isMemorandum) {
                      $progress = $row['memorandum_total_offices'] > 0 ? round(($row['memorandum_read_offices'] / $row['memorandum_total_offices']) * 100) : 0;
                      echo "<button onclick='showMemorandumProgress(" . $row['document_id'] . ")' class='bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 text-sm flex items-center'><i class='fas fa-chart-line mr-1'></i> $progress%</button>";
                  }
                  
                  echo "<div class='hover-actions'>";
                  echo "<a href='dashboard.php?page=edit&id=" . $row['document_id'] . "' class='bg-yellow-600 text-white px-3 py-1 rounded hover:bg-yellow-700 text-sm flex items-center'><i class='fas fa-edit mr-1'></i> Edit</a>";
                  echo "</div>";
                  echo "</div>";
                  echo "</td>";
                  echo "</tr>";
                  
                  // Add memorandum progress row if it's a memorandum
                  if ($isMemorandum) {
                      $progress = $row['memorandum_total_offices'] > 0 ? round(($row['memorandum_read_offices'] / $row['memorandum_total_offices']) * 100) : 0;
                      echo "<tr class='memorandum-progress-row bg-blue-50' id='progress-" . $row['document_id'] . "' style='display: none;'>";
                      echo "<td colspan='7' class='p-4'>";
                      echo "<div class='flex items-center justify-between mb-2'>";
                      echo "<h4 class='text-sm font-medium text-blue-900'>Memorandum Distribution Progress</h4>";
                      echo "<div class='flex items-center space-x-1'>";
                      echo "<div class='w-2 h-2 bg-blue-500 rounded-full animate-pulse'></div>";
                      echo "<span class='text-xs text-blue-600'>Live tracking</span>";
                      echo "</div>";
                      echo "</div>";
                      echo "<div class='mb-3'>";
                      echo "<div class='flex justify-between items-center mb-1'>";
                      echo "<span class='text-xs text-blue-700'>Progress</span>";
                      echo "<span class='memorandum-progress-text text-xs text-blue-600'>$progress%</span>";
                      echo "</div>";
                      echo "<div class='w-full bg-blue-200 rounded-full h-2'>";
                      echo "<div class='memorandum-progress-bar bg-blue-600 h-2 rounded-full transition-all duration-300' style='width: $progress%'></div>";
                      echo "</div>";
                      echo "</div>";
                      echo "<div class='grid grid-cols-3 gap-4 text-center'>";
                      echo "<div class='bg-blue-100 p-2 rounded'>";
                      echo "<div class='memorandum-total-offices text-lg font-bold text-blue-700'>" . $row['memorandum_total_offices'] . "</div>";
                      echo "<div class='text-xs text-blue-600'>Total Offices</div>";
                      echo "</div>";
                      echo "<div class='bg-green-100 p-2 rounded'>";
                      echo "<div class='memorandum-read-offices text-lg font-bold text-green-700'>" . $row['memorandum_read_offices'] . "</div>";
                      echo "<div class='text-xs text-green-600'>Read Offices</div>";
                      echo "</div>";
                      echo "<div class='bg-purple-100 p-2 rounded'>";
                      echo "<div class='memorandum-progress-percent text-lg font-bold text-purple-700'>$progress%</div>";
                      echo "<div class='text-xs text-purple-600'>Progress</div>";
                      echo "</div>";
                      echo "</div>";
                      echo "<button onclick='showMemorandumDetails(" . $row['document_id'] . ")' class='w-full mt-2 text-xs text-blue-700 hover:text-blue-900 bg-blue-100 hover:bg-blue-200 px-2 py-1 rounded transition-colors'>View Details</button>";
                      echo "</div>";
                      echo "</td>";
                      echo "</tr>";
                  }
                }
              } else {
                echo "<tr><td colspan='7' class='text-center py-8 text-gray-500'>No outgoing documents</td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="p-4 border-t flex items-center justify-between">
          <div id="entries-info" class="text-sm text-gray-500">
            <?php if ($total_records > 0): ?>
              Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_records); ?> of <?php echo $total_records; ?> entries
            <?php else: ?>
              No records found
            <?php endif; ?>
          </div>
          <div id="pagination-container">
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

  <!-- Workflow Route Modal -->
  <div id="workflowRouteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10001] hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-3xl mx-4 max-h-[90vh] flex flex-col">
      <div class="p-4 border-b flex justify-between items-center bg-blue-600 text-white rounded-t-lg">
        <h3 class="text-xl font-semibold" id="workflowModalTitle">Document Workflow Route</h3>
        <button id="closeWorkflowModal" class="text-white hover:text-gray-200">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div class="p-6 overflow-y-auto flex-grow">
        <!-- Loading spinner -->
        <div id="workflowLoading" class="text-center py-12">
          <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
          <p class="mt-4 text-gray-600">Loading workflow route...</p>
        </div>
        
        <!-- Error message -->
        <div id="workflowError" class="hidden p-4 bg-red-50 border border-red-200 rounded text-red-700"></div>
        
        <!-- Workflow content -->
        <div id="workflowContent" class="hidden">
          <div class="mb-4">
            <p class="text-sm text-gray-600 mb-2">Document: <span class="font-medium" id="workflowDocumentTitle"></span></p>
            <p class="text-sm text-gray-600">Current Office: <span class="font-medium" id="workflowFromOffice"></span></p>
          </div>
          
          <div class="mb-4">
            <h4 class="text-lg font-medium text-gray-800 mb-3">Workflow Steps</h4>
            <div id="workflowSteps" class="space-y-3">
              <!-- Workflow steps will be inserted here -->
            </div>
          </div>
        </div>
      </div>
      <div class="p-4 border-t flex justify-end bg-gray-50 rounded-b-lg">
        <button id="closeWorkflowBtn" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Close</button>
      </div>
    </div>
  </div>

  <script>
// Memorandum progress functions
function showMemorandumProgress(documentId) {
    const progressRow = document.getElementById('progress-' + documentId);
    if (progressRow) {
        if (progressRow.style.display === 'none') {
            progressRow.style.display = 'table-row';
        } else {
            progressRow.style.display = 'none';
        }
    }
}

function trackMemorandumView(documentId) {
    // Track memorandum view
    fetch('../api/track_memorandum_view.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            document_id: documentId,
            action: 'viewed'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Memorandum view tracked successfully:', data);
            // Update the progress display if available
            if (data.data) {
                updateMemorandumProgress(documentId, data.data);
            }
        } else {
            console.log('Not a memorandum or tracking failed:', data);
        }
    })
    .catch(error => {
        console.error('Error tracking memorandum view:', error);
    });
}

function updateMemorandumProgress(documentId, data) {
    // Update progress elements if they exist
    const progressPercent = document.querySelector(`[data-document-id="${documentId}"] .memorandum-progress-percent`);
    const readOffices = document.querySelector(`[data-document-id="${documentId}"] .memorandum-read-offices`);
    const totalOffices = document.querySelector(`[data-document-id="${documentId}"] .memorandum-total-offices`);
    
    if (progressPercent) progressPercent.textContent = data.progress + '%';
    if (readOffices) readOffices.textContent = data.read_offices;
    if (totalOffices) totalOffices.textContent = data.total_offices;
}

function showMemorandumDetails(documentId) {
    // Track memorandum view
    trackMemorandumView(documentId);
    
    // Create modal for memorandum details
    const modal = document.createElement('div');
    modal.id = 'memorandumDetailsModal';
    modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-hidden">
            <div class="px-6 py-4 bg-blue-50 border-b border-blue-100 flex justify-between items-center">
                <h3 class="text-lg font-medium text-blue-800">Memorandum Distribution Details</h3>
                <button onclick="closeMemorandumDetails()" class="text-blue-600 hover:text-blue-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                <div id="memorandumDetailsContent">
                    <div class="flex items-center justify-center p-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        <span class="ml-2 text-gray-600">Loading memorandum details...</span>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Load memorandum details
    loadMemorandumDetails(documentId);
}

function closeMemorandumDetails() {
    const modal = document.getElementById('memorandumDetailsModal');
    if (modal) {
        modal.remove();
    }
}

function loadMemorandumDetails(documentId) {
    fetch(`../api/get_memorandum_progress.php?document_id=${documentId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayMemorandumDetails(data.data);
        } else {
            document.getElementById('memorandumDetailsContent').innerHTML = `
                <div class="text-center p-6 bg-red-50 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="text-xl font-semibold mb-2">Error Loading Details</h3>
                    <p class="text-gray-600">${data.error || 'Could not load memorandum details.'}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error loading memorandum details:', error);
        document.getElementById('memorandumDetailsContent').innerHTML = `
            <div class="text-center p-6 bg-red-50 rounded-lg">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-xl font-semibold mb-2">Network Error</h3>
                <p class="text-gray-600">Could not connect to server.</p>
            </div>
        `;
    });
}

function displayMemorandumDetails(data) {
    const { progress, total_offices, read_offices, offices } = data;
    
    const officeList = offices.map(office => {
        const statusIcon = office.is_read 
            ? '<svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>'
            : '<svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg>';
        
        const readTime = office.read_at 
            ? `<span class="text-xs text-gray-500">Read at ${new Date(office.read_at).toLocaleString()}</span>`
            : '<span class="text-xs text-gray-400">Not read yet</span>';
        
        return `
            <div class="flex items-center justify-between p-3 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    ${statusIcon}
                    <div>
                        <div class="font-medium text-gray-900">${office.office_name}</div>
                        ${readTime}
                    </div>
                </div>
                <div class="text-sm text-gray-500">
                    ${office.is_read ? 'Read' : 'Pending'}
                </div>
            </div>
        `;
    }).join('');
    
    document.getElementById('memorandumDetailsContent').innerHTML = `
        <div class="mb-6">
            <div class="grid grid-cols-3 gap-4 text-center mb-6">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">${total_offices}</div>
                    <div class="text-sm text-blue-500">Total Offices</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">${read_offices}</div>
                    <div class="text-sm text-green-500">Read Offices</div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">${progress}%</div>
                    <div class="text-sm text-purple-500">Progress</div>
                </div>
            </div>
            
            <div class="mb-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Distribution Progress</span>
                    <span class="text-sm text-gray-500">${progress}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="bg-blue-600 h-3 rounded-full transition-all duration-300" style="width: ${progress}%"></div>
                </div>
            </div>
        </div>
        
        <div>
            <h4 class="text-lg font-medium text-gray-900 mb-3">Office Status</h4>
            <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
                ${officeList}
            </div>
        </div>
    `;
}

// Function to show workflow route (make globally accessible)
window.showWorkflowRoute = function(documentId, officeName, documentTitle) {
    const modal = document.getElementById('workflowRouteModal');
    const loading = document.getElementById('workflowLoading');
    const content = document.getElementById('workflowContent');
    const error = document.getElementById('workflowError');
    const documentTitleEl = document.getElementById('workflowDocumentTitle');
    const fromOffice = document.getElementById('workflowFromOffice');
    const workflowSteps = document.getElementById('workflowSteps');
    
    // Show modal and loading
    modal.classList.remove('hidden');
    loading.classList.remove('hidden');
    content.classList.add('hidden');
    error.classList.add('hidden');
    
    // Set office name and document title
    if (fromOffice) fromOffice.textContent = officeName;
    if (documentTitleEl && documentTitle) {
        documentTitleEl.textContent = documentTitle;
    } else if (documentTitleEl) {
        documentTitleEl.textContent = 'Document #' + documentId;
    }
    
    // Fetch workflow data
    fetch(`../api/get_document_workflow.php?document_id=${documentId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            loading.classList.add('hidden');
            
            if (data.success && data.steps && data.steps.length > 0) {
                // Display workflow steps
                workflowSteps.innerHTML = '';
                
                data.steps.forEach((step, index) => {
                    const status = (step.status || 'PENDING').toUpperCase();
                    const isCurrent = status === 'CURRENT';
                    const isCompleted = status === 'COMPLETED';
                    const isRejected = status === 'REJECTED';
                    const isOnHold = status === 'ON_HOLD' || status === 'HOLD';
                    const isRevision = status === 'REVISION_REQUESTED' || status === 'REVISION';
                    const isCancelled = status === 'CANCELLED';
                    
                    // Determine step styling
                    let stepClass = 'bg-gray-50 border-gray-200';
                    let badgeClass = 'bg-gray-100 text-gray-800';
                    let iconClass = 'text-gray-500';
                    
                    if (isCompleted) {
                        stepClass = 'bg-green-50 border-green-200';
                        badgeClass = 'bg-green-100 text-green-800';
                        iconClass = 'text-green-500';
                    } else if (isCurrent) {
                        stepClass = 'bg-blue-50 border-blue-200';
                        badgeClass = 'bg-blue-100 text-blue-800';
                        iconClass = 'text-blue-500';
                    } else if (isRejected) {
                        stepClass = 'bg-red-50 border-red-200';
                        badgeClass = 'bg-red-100 text-red-800';
                        iconClass = 'text-red-500';
                    } else if (isOnHold) {
                        stepClass = 'bg-yellow-50 border-yellow-200';
                        badgeClass = 'bg-yellow-100 text-yellow-800';
                        iconClass = 'text-yellow-500';
                    } else if (isRevision) {
                        stepClass = 'bg-purple-50 border-purple-200';
                        badgeClass = 'bg-purple-100 text-purple-800';
                        iconClass = 'text-purple-500';
                    } else if (isCancelled) {
                        stepClass = 'bg-gray-50 border-gray-200 opacity-50';
                        badgeClass = 'bg-gray-100 text-gray-600';
                        iconClass = 'text-gray-400';
                    }
                    
                    // Get status text
                    let statusText = status.replace(/_/g, ' ').toLowerCase();
                    statusText = statusText.charAt(0).toUpperCase() + statusText.slice(1);
                    
                    // Get timestamp
                    let timestamp = 'N/A';
                    if (step.completed_at) {
                        timestamp = new Date(step.completed_at).toLocaleString();
                    } else if (step.created_at) {
                        timestamp = new Date(step.created_at).toLocaleString();
                    }
                    
                    // Create step element
                    const stepElement = document.createElement('div');
                    stepElement.className = `flex items-center p-4 border-2 rounded-lg ${stepClass}`;
                    
                    // Step number circle
                    const stepNumber = document.createElement('div');
                    stepNumber.className = `flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center text-white text-sm font-bold ${isCompleted ? 'bg-green-500' : isCurrent ? 'bg-blue-500' : isRejected ? 'bg-red-500' : isOnHold ? 'bg-yellow-500' : isCancelled ? 'bg-gray-400' : 'bg-gray-300'}`;
                    stepNumber.textContent = step.step_order || (index + 1);
                    
                    // Step content
                    const stepContent = document.createElement('div');
                    stepContent.className = 'ml-4 flex-1';
                    
                    const officeNameDiv = document.createElement('div');
                    officeNameDiv.className = 'flex items-center mb-1';
                    
                    const officeNameText = document.createElement('span');
                    officeNameText.className = 'font-medium text-gray-900 mr-2';
                    officeNameText.textContent = step.office_name || 'Unknown Office';
                    
                    const statusBadge = document.createElement('span');
                    statusBadge.className = `px-2 py-1 rounded text-xs font-medium ${badgeClass}`;
                    statusBadge.textContent = statusText;
                    
                    officeNameDiv.appendChild(officeNameText);
                    officeNameDiv.appendChild(statusBadge);
                    
                    const timestampDiv = document.createElement('p');
                    timestampDiv.className = 'text-xs text-gray-500 mt-1';
                    timestampDiv.textContent = `Status: ${statusText} • ${timestamp}`;
                    
                    // Comments if available
                    if (step.comments) {
                        const commentsDiv = document.createElement('p');
                        commentsDiv.className = 'text-sm text-gray-600 mt-2 italic';
                        commentsDiv.textContent = '"' + step.comments + '"';
                        stepContent.appendChild(commentsDiv);
                    }
                    
                    stepContent.appendChild(officeNameDiv);
                    stepContent.appendChild(timestampDiv);
                    
                    stepElement.appendChild(stepNumber);
                    stepElement.appendChild(stepContent);
                    
                    workflowSteps.appendChild(stepElement);
                    
                    // Add arrow between steps (except for last one)
                    if (index < data.steps.length - 1) {
                        const arrow = document.createElement('div');
                        arrow.className = 'flex justify-center py-2';
                        arrow.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>';
                        workflowSteps.appendChild(arrow);
                    }
                });
                
                content.classList.remove('hidden');
            } else {
                error.classList.remove('hidden');
                error.textContent = data.error || 'No workflow information available for this document.';
            }
        })
        .catch(error => {
            console.error('Error loading workflow:', error);
            loading.classList.add('hidden');
            error.classList.remove('hidden');
            error.textContent = 'Failed to load workflow route. Please try again later.';
        });
}

document.addEventListener('DOMContentLoaded', function () {
    const pageContent = document.getElementById('page-content');
    let debounceTimeout;
    
    // Workflow route modal handlers
    const workflowModal = document.getElementById('workflowRouteModal');
    const closeWorkflowModalBtn = document.getElementById('closeWorkflowModal');
    const closeWorkflowBtn = document.getElementById('closeWorkflowBtn');
    
    function closeWorkflowModal() {
        if (workflowModal) {
            workflowModal.classList.add('hidden');
        }
    }
    
    if (closeWorkflowModalBtn) {
        closeWorkflowModalBtn.addEventListener('click', closeWorkflowModal);
    }
    if (closeWorkflowBtn) {
        closeWorkflowBtn.addEventListener('click', closeWorkflowModal);
    }
    
    // Close workflow modal on outside click
    if (workflowModal) {
        workflowModal.addEventListener('click', function(e) {
            if (e.target === workflowModal) {
                closeWorkflowModal();
            }
        });
    }
    
    // Handle workflow office link clicks
    document.addEventListener('click', function(e) {
        const workflowLink = e.target.closest('.workflow-office-link');
        if (workflowLink) {
            e.preventDefault();
            e.stopPropagation();
            const documentId = workflowLink.getAttribute('data-document-id');
            const officeName = workflowLink.getAttribute('data-office-name');
            const documentTitle = workflowLink.getAttribute('data-document-title');
            showWorkflowRoute(documentId, officeName, documentTitle);
        }
    });

    function updateTable(url, pushState = true) {
        // Show loading overlay
        const loadingOverlay = document.getElementById('loadingOverlay');
        if(loadingOverlay) loadingOverlay.classList.add('active');

        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.getElementById('page-content');
                if (newContent) {
                    pageContent.innerHTML = newContent.innerHTML;
                }
                if (pushState) {
                    history.pushState({ path: url }, '', url);
                }
            })
            .catch(error => console.error('Error updating table:', error))
            .finally(() => {
                // Hide loading overlay
                if(loadingOverlay) loadingOverlay.classList.remove('active');
            });
    }

    pageContent.addEventListener('input', function(e) {
        if (e.target && e.target.id === 'search') {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const searchTerm = e.target.value;
                const entriesPerPageSelect = document.getElementById('entriesPerPage');
                const entries = entriesPerPageSelect ? entriesPerPageSelect.value : 15;
                const url = `?page=outgoing&search=${encodeURIComponent(searchTerm)}&show=${entries}&page_num=1`;
                updateTable(window.location.pathname + url.replace('page=outgoing&', ''));
            }, 300);
        }
    });

    pageContent.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'entriesPerPage') {
            const searchInput = document.getElementById('search');
            const searchTerm = searchInput ? searchInput.value : '';
            const entries = e.target.value;
            const url = `?page=outgoing&show=${entries}&search=${encodeURIComponent(searchTerm)}&page_num=1`;
            updateTable(window.location.pathname + url.replace('page=outgoing&', ''));
        }
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

    pageContent.addEventListener('click', function (e) {
        const paginationLink = e.target.closest('.flex.items-center.gap-2 a');
        if (paginationLink && paginationLink.href) {
            e.preventDefault();
            updateTable(paginationLink.href);
        }

        const refreshButton = e.target.closest('#refreshBtn');
        if (refreshButton) {
            e.preventDefault();
            const loadingOverlay = document.getElementById('loadingOverlay');
            if(loadingOverlay) loadingOverlay.classList.add('active');
            setTimeout(() => {
                window.location.reload();
            }, 300);
        }
        
        const exportButton = e.target.closest('#exportBtn');
        if (exportButton) {
            e.preventDefault();
            const table = pageContent.querySelector('table');
            if (!table) return;
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (const row of rows) {
                const cols = row.querySelectorAll('th, td');
                const rowData = [];
                for (const col of cols) {
                    if (col.cellIndex < 6) { // Skip actions column
                        rowData.push('"' + (col.textContent.trim().replace(/"/g, '""')) + '"');
                    }
                }
                csv.push(rowData.join(','));
            }
            
            const csvContent = csv.join('\\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', 'outgoing_documents_' + new Date().toISOString().slice(0, 10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    });

    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.path) {
            updateTable(e.state.path, false);
        } else if (e.state === null) {
            updateTable(window.location.href, false);
        }
    });
});
  </script>
</body>
</html>
