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
    .badge-hold, .badge-on_hold {
      background-color: #E0F2FE;
      color: #0369A1;
    }
    .badge-revision, .badge-revision_requested {
      background-color: #F3E8FF;
      color: #7E22CE;
    }
  </style>
</head>
<body class="bg-gray-50">
  <div id="page-content" class="p-6">
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
          <div class="flex items-center mt-2 space-x-4 text-xs">
            <div class="flex items-center">
              <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
              <span class="text-gray-600">Current Documents (Available for action)</span>
            </div>
            <div class="flex items-center">
              <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
              <span class="text-gray-600">Upcoming Documents (Preview only)</span>
            </div>
            <div class="flex items-center">
              <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
              <span class="text-gray-600">Memorandums (View-only, sent to all offices)</span>
            </div>
          </div>
        </div>
      <div class="flex space-x-2">
        <button class="bg-green-700 text-white px-4 py-2 rounded-lg hover:bg-green-800 flex items-center">
          <i class="fas fa-sync-alt mr-2"></i> Refresh
        </button>
        <button id="exportBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
          <i class="fas fa-file-export mr-2"></i> Export
        </button>
        <button id="toggleUpcoming" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
          <i class="fas fa-eye mr-2"></i> <span id="toggleText">Show Upcoming</span>
        </button>
      </div>
    </div>

    <!-- Status Messages -->
    <?php if (isset($_GET['status']) && isset($_GET['message'])): ?>
      <div class="mb-6 p-4 rounded-lg <?php echo $_GET['status'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
        <?php echo htmlspecialchars($_GET['message']); ?>
      </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['resumed']) && $_GET['resumed'] == '1'): ?>
      <div class="mb-6 p-4 rounded-lg bg-green-100 text-green-800 border-l-4 border-green-500">
        <div class="flex items-center">
          <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
          </svg>
          <span class="font-medium">Document has been resumed successfully! It should now appear in your inbox below.</span>
        </div>
      </div>
    <?php endif; ?>

    <!-- Search Bar -->
    <div class="w-full mb-6">
      <form method="GET" action="" class="relative" id="searchForm">
        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search for documents by title, type, requisitioner or office" class="w-full pl-10 pr-12 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
            <option value="100" <?php echo $items_per_page == 100 ? 'selected' : ''; ?>>100</option>
          </select>
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
              <th class="p-4 font-medium">Sender</th>
              <th class="p-4 font-medium">Document Title</th>
              <th class="p-4 font-medium">Type</th>
              <th class="p-4 font-medium">From Office</th>
              <th class="p-4 font-medium">Date</th>
              <th class="p-4 font-medium">Status</th>
              <th class="p-4 font-medium text-center">Actions</th>
            </tr>
          </thead>
          <tbody id="documents-tbody">
            <?php
            // Get all documents for this office that are not on hold
            $sql = "SELECT 
                      d.document_id, 
                      d.title, 
                      dw.status as workflow_status, 
                      d.status as document_status, 
                      d.created_at,
                      dt.type_name, 
                      u.full_name as creator_name,
                      o.office_name as creator_office,
                      d.is_urgent
                    FROM document_workflow dw
                    JOIN documents d ON dw.document_id = d.document_id
                    LEFT JOIN document_types dt ON d.type_id = dt.type_id
                    LEFT JOIN users u ON d.creator_id = u.user_id
                    LEFT JOIN offices o ON u.office_id = o.office_id
                    LEFT JOIN (
                        SELECT document_id, MAX(created_at) as latest_hold
                        FROM document_logs
                        WHERE action = 'hold'
                        GROUP BY document_id
                    ) hold_logs ON d.document_id = hold_logs.document_id
                    LEFT JOIN (
                        SELECT document_id, MAX(created_at) as latest_resume
                        FROM document_logs
                        WHERE action = 'resume'
                        GROUP BY document_id
                    ) resume_logs ON d.document_id = resume_logs.document_id
                    WHERE dw.office_id = $office_id
                    AND UPPER(dw.status) = 'CURRENT'
                    AND (hold_logs.document_id IS NULL OR (resume_logs.document_id IS NOT NULL AND resume_logs.latest_resume > hold_logs.latest_hold))
                    $search_condition 
                    ORDER BY d.created_at DESC
                    LIMIT $offset, $items_per_page";
            
            // Count total records for pagination - excluding documents on hold
            $count_sql = "SELECT COUNT(*) as total 
                         FROM document_workflow dw 
                         JOIN documents d ON dw.document_id = d.document_id 
                         LEFT JOIN document_types dt ON d.type_id = dt.type_id
                         LEFT JOIN users u ON d.creator_id = u.user_id
                         LEFT JOIN offices o ON u.office_id = o.office_id
                         LEFT JOIN (
                             SELECT document_id, MAX(created_at) as latest_hold
                             FROM document_logs
                             WHERE action = 'hold'
                             GROUP BY document_id
                         ) hold_logs ON d.document_id = hold_logs.document_id
                         LEFT JOIN (
                             SELECT document_id, MAX(created_at) as latest_resume
                             FROM document_logs
                             WHERE action = 'resume'
                             GROUP BY document_id
                         ) resume_logs ON d.document_id = resume_logs.document_id
                         WHERE dw.office_id = $office_id
                         AND UPPER(dw.status) = 'CURRENT'
                         AND (hold_logs.document_id IS NULL OR (resume_logs.document_id IS NOT NULL AND resume_logs.latest_resume > hold_logs.latest_hold))
                         $search_condition";
            
            $count_result = $conn->query($count_sql);
            $total_records = ($count_result && $row = $count_result->fetch_assoc()) ? $row['total'] : 0;
            $total_pages = ceil($total_records / $items_per_page);
            
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $documentCode = 'DOC-' . str_pad($row['document_id'], 3, '0', STR_PAD_LEFT);
                    
                    // For inbox, we should show the workflow status for the current office, not the document's overall status
                    // Convert workflow status to lowercase for consistency
                    $workflow_status = strtolower($row['workflow_status']);
                    
                    $status_class = match($workflow_status) {
                        'completed' => 'badge-approved',
                        'rejected' => 'badge-rejected',
                        'hold' => 'badge-on_hold',
                        'on_hold' => 'badge-on_hold',
                        'revision' => 'badge-revision_requested',
                        'revision_requested' => 'badge-revision_requested',
                        default => 'badge-pending'
                    };
                    
                    $status_text = match($workflow_status) {
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                        'hold' => 'On Hold',
                        'on_hold' => 'On Hold',
                        'revision' => 'Revision Requested',
                        'revision_requested' => 'Revision Requested',
                        default => 'Pending'
                    };
                    
                    // Add visual indicator for current documents
                    $current_indicator = '<div class="flex items-center mt-1"><span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span><span class="text-xs text-green-600 font-medium">CURRENT</span></div>';
                    
                    // Add urgent styling to the row if document is urgent
                    $urgent_row_class = $row['is_urgent'] ? 'border-l-red-500 bg-red-50' : 'border-l-green-500';
                    echo "<tr class='hover:bg-gray-50 border-b border-l-4 $urgent_row_class'>";
                    echo "<td class='p-4 font-medium'>$documentCode</td>";
                    echo "<td class='p-4'>{$row['creator_name']}</td>";
                    echo "<td class='p-4'>{$row['title']}";
                    if ($row['is_urgent']) {
                        echo ' <span class="badge animate-pulse" style="background-color: #FEE2E2; color: #B91C1C; font-weight: bold;"><i class="fas fa-exclamation-triangle mr-1"></i>URGENT</span>';
                    }
                    
                    // Check for attachments
                    $attachment_query = "SELECT COUNT(*) as attachment_count FROM document_attachments WHERE document_id = ?";
                    $attachment_stmt = $conn->prepare($attachment_query);
                    if ($attachment_stmt) {
                        $attachment_stmt->bind_param("i", $row['document_id']);
                        $attachment_stmt->execute();
                        $attachment_result = $attachment_stmt->get_result();
                        if ($attachment_row = $attachment_result->fetch_assoc()) {
                            if ($attachment_row['attachment_count'] > 0) {
                                echo ' <span class="badge" style="background-color: #E0F2FE; color: #0369A1;"><i class="fas fa-paperclip mr-1"></i>' . $attachment_row['attachment_count'] . '</span>';
                            }
                        }
                    }
                    echo "</td>";
                    echo "<td class='p-4'>{$row['type_name']}</td>";
                    $office_name_escaped = htmlspecialchars($row['creator_office'], ENT_QUOTES, 'UTF-8');
                    $title_escaped_for_workflow = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8');
                    echo "<td class='p-4'><span class='cursor-pointer text-blue-600 hover:text-blue-800 hover:underline font-medium workflow-office-link' data-document-id='{$row['document_id']}' data-office-name='{$office_name_escaped}' data-document-title='{$title_escaped_for_workflow}' title='Click to view workflow route'>{$office_name_escaped}</span>$current_indicator</td>";
                    // Output UTC timestamp and let JS render in user's local timezone to avoid server/DB timezone drift
                    $created_iso = str_replace(' ', 'T', $row['created_at']);
                    echo "<td class='p-4'><span class=\"local-dt\" data-utc=\"{$created_iso}Z\"></span></td>";
                    echo "<td class='p-4'><span class='badge $status_class'>$status_text</span></td>";
                    echo "<td class='p-4'>";
                    $menuId = 'actions-' . $row['document_id'];
                    echo "<div class='relative inline-flex'>";
                    // Primary action
                    echo "<a href='dashboard.php?page=view_document&id={$row['document_id']}' class='bg-blue-600 text-white px-3 py-1 rounded-l hover:bg-blue-700 text-sm flex items-center'><i class='fas fa-eye mr-1'></i> View</a>";
                    // Menu toggle
                    echo "<button type='button' class='bg-blue-600 text-white px-2 rounded-r hover:bg-blue-700 text-sm' onclick=\"toggleActionMenu('{$menuId}')\" aria-haspopup='true' aria-expanded='false'><i class='fas fa-ellipsis-v'></i></button>";
                    // Dropdown menu (Approve, Reject, Request Revision, Put on Hold)
                    $doc_id = $row['document_id'];
                    $title_escaped = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8');
                    echo "<div id='{$menuId}' class='hidden absolute right-0 top-full mt-1 w-52 bg-white border border-gray-200 rounded shadow-lg z-20 max-h-64 overflow-y-auto'>";
                    echo "  <button type='button' class='w-full text-left block px-3 py-2 hover:bg-gray-50 text-sm flex items-center cursor-pointer document-action-btn' data-doc-id='{$doc_id}' data-action='approve' data-title='{$title_escaped}'><i class='fas fa-check mr-2 text-green-700'></i>Approve</button>";
                    echo "  <button type='button' class='w-full text-left block px-3 py-2 hover:bg-gray-50 text-sm flex items-center cursor-pointer document-action-btn' data-doc-id='{$doc_id}' data-action='reject' data-title='{$title_escaped}'><i class='fas fa-times mr-2 text-red-600'></i>Reject</button>";
                    echo "  <button type='button' class='w-full text-left block px-3 py-2 hover:bg-gray-50 text-sm flex items-center cursor-pointer document-action-btn' data-doc-id='{$doc_id}' data-action='request_revision' data-title='{$title_escaped}'><i class='fas fa-edit mr-2 text-amber-600'></i>Request Revision</button>";
                    echo "  <button type='button' class='w-full text-left block px-3 py-2 hover:bg-gray-50 text-sm flex items-center cursor-pointer document-action-btn' data-doc-id='{$doc_id}' data-action='hold' data-title='{$title_escaped}'><i class='fas fa-pause-circle mr-2 text-blue-600'></i>Put on Hold</button>";
                    echo "</div>";
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
        <div id="entries-info" class="text-sm text-gray-500">
          <?php if ($total_records > 0): ?>
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_records); ?> of <?php echo $total_records; ?> entries
          <?php else: ?>
            Showing 0 to 0 of 0 entries
          <?php endif; ?>
        </div>
        <div id="pagination-container" class="flex space-x-2">
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

    <!-- Upcoming Documents Section -->
    <div id="upcomingDocuments" class="bg-white rounded-lg shadow mt-6" style="display: none;">
      <div class="p-4 border-b bg-yellow-50 border-yellow-200">
        <h2 class="text-lg font-semibold text-yellow-800 flex items-center">
          <i class="fas fa-clock mr-2"></i>
          Upcoming Documents
          <span class="ml-2 text-sm bg-yellow-200 text-yellow-800 px-2 py-1 rounded-full" id="upcomingCount">0</span>
        </h2>
        <p class="text-sm text-yellow-700 mt-1">Documents that will be routed to your office. You can see their progress but cannot take action yet.</p>
      </div>
      
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="text-left border-b bg-yellow-50">
              <th class="p-4 font-medium">Code</th>
              <th class="p-4 font-medium">Title</th>
              <th class="p-4 font-medium">Type</th>
              <th class="p-4 font-medium">Creator</th>
              <th class="p-4 font-medium">Current Office</th>
              <th class="p-4 font-medium">Status</th>
              <th class="p-4 font-medium">Created</th>
              <th class="p-4 font-medium text-center">Actions</th>
            </tr>
          </thead>
          <tbody id="upcoming-tbody">
            <?php
            // Get upcoming documents (documents in workflow that will come to this office)
            // For memorandums, we only show them once since they're view-only documents
            $upcoming_sql = "SELECT DISTINCT
                              d.document_id, 
                              d.title, 
                              my_step.status as workflow_status, 
                              d.status as document_status, 
                              d.created_at,
                              dt.type_name, 
                              u.full_name as creator_name,
                              o.office_name as creator_office,
                              d.is_urgent,
                              d.is_memorandum,
                              d.memorandum_sent_to_all_offices,
                              current_office.office_name as current_office_name,
                              current_office.office_id as current_office_id,
                              COALESCE(curr.current_step_order, 0) as current_step_order,
                              my_step.step_order as my_step_order,
                              (my_step.step_order - COALESCE(curr.current_step_order, 0)) AS steps_away
                            FROM documents d
                            LEFT JOIN document_types dt ON d.type_id = dt.type_id
                            LEFT JOIN users u ON d.creator_id = u.user_id
                            LEFT JOIN offices o ON u.office_id = o.office_id
                            /* Determine current step order per document (CURRENT / ON_HOLD / HOLD) */
                            LEFT JOIN (
                                SELECT document_id, MAX(step_order) AS current_step_order
                                FROM document_workflow
                                WHERE UPPER(status) IN ('CURRENT','ON_HOLD','HOLD')
                                GROUP BY document_id
                            ) curr ON curr.document_id = d.document_id
                            /* For display of current office */
                            LEFT JOIN document_workflow cw ON cw.document_id = d.document_id AND cw.step_order = curr.current_step_order
                            LEFT JOIN offices current_office ON cw.office_id = current_office.office_id
                            /* The user's upcoming step */
                            INNER JOIN document_workflow my_step ON d.document_id = my_step.document_id AND my_step.office_id = $office_id
                            WHERE my_step.office_id = $office_id 
                            AND my_step.step_order > COALESCE(curr.current_step_order, 0)
                            /* Exclude finished/terminated documents */
                            AND UPPER(d.status) NOT IN ('COMPLETED','REJECTED','CANCELLED')
                            /* Memorandum visibility */
                            AND (d.is_memorandum = 0 OR (d.is_memorandum = 1 AND d.memorandum_sent_to_all_offices = 1))
                            /* Exclude steps already done at user's office */
                            AND UPPER(my_step.status) NOT IN ('CURRENT','COMPLETED','APPROVED','REJECTED','CANCELLED')
                            $search_condition 
                            /* Sort: nearest first, newest first; non-memos before memos */
                            ORDER BY steps_away ASC, d.created_at DESC, d.is_memorandum ASC, d.document_id DESC
                            LIMIT $offset, $items_per_page";
            
            $upcoming_result = $conn->query($upcoming_sql);
            $upcoming_count = 0;
            
            if ($upcoming_result && $upcoming_result->num_rows > 0) {
                while($upcoming_row = $upcoming_result->fetch_assoc()) {
                    $upcoming_count++;
                    $documentCode = 'DOC-' . str_pad($upcoming_row['document_id'], 3, '0', STR_PAD_LEFT);
                    
                    $workflow_status = strtolower($upcoming_row['workflow_status']);
                    
                    $status_class = match($workflow_status) {
                        'completed' => 'badge-approved',
                        'rejected' => 'badge-rejected',
                        'hold' => 'badge-on_hold',
                        'on_hold' => 'badge-on_hold',
                        'revision' => 'badge-revision_requested',
                        'revision_requested' => 'badge-revision_requested',
                        default => 'badge-pending'
                    };
                    
                    $status_text = match($workflow_status) {
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                        'hold' => 'On Hold',
                        'on_hold' => 'On Hold',
                        'revision' => 'Revision Requested',
                        'revision_requested' => 'Revision Requested',
                        default => 'Pending'
                    };
                    
                    // Check if this is a memorandum
                    $isMemorandum = isset($upcoming_row['is_memorandum']) && $upcoming_row['is_memorandum'];
                    
                    // Calculate position in workflow
                    $position = $upcoming_row['my_step_order'] - $upcoming_row['current_step_order'];
                    
                    if ($isMemorandum) {
                        // For memorandums, show distribution status instead of position
                        $position_text = "Sent to All Offices";
                        $memorandum_class = "border-l-4 border-l-blue-500";
                    } else {
                        $position_text = $position == 1 ? 'Next' : "$position steps away";
                        $memorandum_class = "";
                    }
                    
                    // Add urgent styling to upcoming row if document is urgent
                    $upcoming_urgent_class = $upcoming_row['is_urgent'] ? 'border-l-red-500 bg-red-50' : '';
                    echo "<tr class='hover:bg-yellow-50 border-b border-yellow-200 $memorandum_class $upcoming_urgent_class'>";
                    echo "<td class='p-4 font-medium'>$documentCode</td>";
                    echo "<td class='p-4'>" . htmlspecialchars($upcoming_row['title']);
                    if ($isMemorandum) {
                        echo ' <span class="badge" style="background-color: #DBEAFE; color: #1E40AF;">Memorandum</span>';
                    }
                    if ($upcoming_row['is_urgent']) {
                        echo ' <span class="badge animate-pulse" style="background-color: #FEE2E2; color: #B91C1C; font-weight: bold;"><i class="fas fa-exclamation-triangle mr-1"></i>URGENT</span>';
                    }
                    
                    // Check for attachments
                    $attachment_query = "SELECT COUNT(*) as attachment_count FROM document_attachments WHERE document_id = ?";
                    $attachment_stmt = $conn->prepare($attachment_query);
                    if ($attachment_stmt) {
                        $attachment_stmt->bind_param("i", $upcoming_row['document_id']);
                        $attachment_stmt->execute();
                        $attachment_result = $attachment_stmt->get_result();
                        if ($attachment_row = $attachment_result->fetch_assoc()) {
                            if ($attachment_row['attachment_count'] > 0) {
                                echo ' <span class="badge" style="background-color: #E0F2FE; color: #0369A1;"><i class="fas fa-paperclip mr-1"></i>' . $attachment_row['attachment_count'] . '</span>';
                            }
                        }
                    }
                    echo "</td>";
                    echo "<td class='p-4'>" . htmlspecialchars($upcoming_row['type_name']) . "</td>";
                    echo "<td class='p-4'>" . htmlspecialchars($upcoming_row['creator_name']) . "<br><span class='text-xs text-gray-500'>" . htmlspecialchars($upcoming_row['creator_office']) . "</span></td>";
                    echo "<td class='p-4'>" . htmlspecialchars($upcoming_row['current_office_name']) . "<br><span class='text-xs text-yellow-600 font-medium'>$position_text</span></td>";
                    echo "<td class='p-4'><span class='badge $status_class'>" . $status_text . "</span></td>";
                    echo "<td class='p-4'>" . date('M j, Y', strtotime($upcoming_row['created_at'])) . "</td>";
                    echo "<td class='p-4'>";
                    echo "<div class='flex justify-center space-x-2'>";
                    echo "<button onclick='showDocumentProgress(" . $upcoming_row['document_id'] . ")' class='bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 text-sm flex items-center'><i class='fas fa-route mr-1'></i> Track</button>";
                    echo "<button onclick='showDocumentPreview(" . $upcoming_row['document_id'] . ")' class='bg-gray-600 text-white px-3 py-1 rounded hover:bg-gray-700 text-sm flex items-center'><i class='fas fa-eye mr-1'></i> Preview</button>";
                    echo "</div>";
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8' class='text-center py-8 text-gray-500'>No upcoming documents</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
    
    <script>
        // Update the upcoming count and make toggle function globally accessible
        document.addEventListener('DOMContentLoaded', function() {
            const upcomingCountEl = document.getElementById('upcomingCount');
            if (upcomingCountEl) {
                upcomingCountEl.textContent = '<?php echo $upcoming_count; ?>';
            }
            
            // Function is already globally accessible via window.toggleUpcomingDocuments
        });
    </script>
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
            <p class="text-sm text-gray-600">From Office: <span class="font-medium" id="workflowFromOffice"></span></p>
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

  <!-- Document Action Modal -->
  <div id="actionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000] hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
      <div class="p-4 border-b flex justify-between items-center">
        <h3 class="text-xl font-semibold text-gray-800" id="actionModalTitle">Document Action</h3>
        <button id="closeActionModal" class="text-gray-500 hover:text-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div class="p-6">
        <div class="mb-4">
          <p class="text-sm text-gray-600 mb-2">Document: <span class="font-medium" id="actionDocumentTitle"></span></p>
        </div>
        <div class="mb-4">
          <label for="actionComments" class="block text-sm font-medium text-gray-700 mb-2">
            <span id="actionLabel">Reason/Comments:</span>
            <span id="actionRequired" class="text-red-500 ml-1">*</span>
          </label>
          <textarea id="actionComments" name="comments" rows="4" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Enter your reason or comments here..."></textarea>
          <p class="text-xs text-gray-500 mt-1" id="actionHelpText">This field is required for reject, hold, and revision actions.</p>
        </div>
        <div id="actionError" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm"></div>
        <div class="flex justify-end space-x-2">
          <button id="cancelActionBtn" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Cancel</button>
          <button id="submitActionBtn" class="px-4 py-2 rounded text-white font-medium" style="background-color: #16a34a;">
            <span id="submitActionText">Submit</span>
            <span id="submitActionSpinner" class="hidden"><i class="fas fa-spinner fa-spin"></i> Processing...</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Summary Modal -->
  <div id="summaryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[11000] hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl max-h-[90vh] flex flex-col">
      <div class="p-4 border-b flex justify-between items-center">
        <h3 class="text-xl font-semibold text-gray-800" id="summaryTitle">Document Summary</h3>
        <button id="closeSummaryModal" class="text-gray-500 hover:text-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div class="p-6 overflow-y-auto flex-grow">
        <!-- Loading spinner -->
        <div id="summaryLoading" class="text-center py-12">
          <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-amber-500"></div>
          <p class="mt-4 text-gray-600">Generating document summary...</p>
          <p class="text-sm text-gray-500 mt-2">This may take a moment for larger documents</p>
        </div>
        
        <!-- Error message -->
        <div id="summaryError" class="hidden"></div>
        
        <!-- Summary content -->
        <div id="summaryContent" class="hidden">
          <div class="mb-6">
            <h4 class="text-lg font-medium text-gray-800 mb-3">Key Points</h4>
            <ul id="keyPoints" class="list-disc pl-5 space-y-2 text-gray-700"></ul>
          </div>
          <div class="mb-6">
            <h4 class="text-lg font-medium text-gray-800 mb-3">Summary</h4>
            <div id="summaryText" class="text-gray-700 leading-relaxed"></div>
          </div>
          <div class="flex justify-end">
            <button id="copySummaryBtn" class="bg-amber-600 text-white px-4 py-2 rounded hover:bg-amber-700 flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
              </svg>
              Copy Summary
            </button>
          </div>
        </div>
      </div>
      <div class="p-4 border-t flex justify-end">
        <button id="closeSummaryBtn" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Close</button>
      </div>
    </div>
  </div>

  <!-- Analysis Modal -->
  <div id="aiAnalysisModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999] hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-6xl max-h-[90vh] flex flex-col">
      <div class="p-4 border-b flex justify-between items-center">
        <h3 class="text-xl font-semibold text-gray-800" id="aiAnalysisTitle">Document Analysis</h3>
        <button id="closeAnalysisModal" class="text-gray-500 hover:text-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div class="p-6 overflow-y-auto flex-grow">
        <!-- Loading spinner -->
        <div id="aiAnalysisLoading" class="text-center py-12">
          <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-indigo-500"></div>
          <p class="mt-4 text-gray-600">Analyzing document...</p>
          <p class="text-sm text-gray-500 mt-2">This may take a moment for larger documents</p>
        </div>
        
        <!-- Error message -->
        <div id="aiAnalysisError" class="hidden"></div>
        
        <!-- Analysis content -->
        <div id="aiAnalysisContent" class="hidden">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Classification -->
            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="text-lg font-medium text-gray-800 mb-3">Document Classification</h4>
              <div id="classificationResults" class="flex flex-wrap gap-2"></div>
            </div>
            
            <!-- Entities -->
            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="text-lg font-medium text-gray-800 mb-3">Key Entities</h4>
              <div id="entitiesResults"></div>
            </div>
            
            <!-- Sentiment -->
            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="text-lg font-medium text-gray-800 mb-3">Sentiment Analysis</h4>
              <div id="sentimentResults" class="text-gray-700"></div>
            </div>
            
            <!-- Keywords -->
            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="text-lg font-medium text-gray-800 mb-3">Keywords</h4>
              <div id="keywordsResults" class="flex flex-wrap gap-2"></div>
            </div>
          </div>
          
          <!-- Summary -->
          <div class="mt-6 bg-gray-50 p-4 rounded-lg">
            <h4 class="text-lg font-medium text-gray-800 mb-3">Document Summary</h4>
            <div id="analysisSummary" class="text-gray-700 leading-relaxed"></div>
          </div>
          
          <div class="flex justify-end mt-6">
            <button id="copyAnalysisBtn" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
              </svg>
              Copy Analysis
            </button>
          </div>
        </div>
      </div>
      <div class="p-4 border-t flex justify-end">
        <button id="closeAnalysisBtn" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Close</button>
      </div>
    </div>
  </div>

  <script>
  // Render timestamps in user's local timezone
  document.addEventListener('DOMContentLoaded', function(){
    const nodes = document.querySelectorAll('.local-dt');
    nodes.forEach(el => {
      const utc = el.getAttribute('data-utc');
      if(!utc) return;
      // Parse as UTC and format in local tz
      const d = new Date(utc);
      if (isNaN(d.getTime())) { el.textContent = utc; return; }
      const opts = { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };
      el.textContent = d.toLocaleString(undefined, opts);
      el.title = d.toLocaleString();
    });
  });
// Document summarization function
function documentSummarize(documentId, fileName) {
    // Show the modal
    const summaryModal = document.getElementById('summaryModal');
    const summaryTitle = document.getElementById('summaryTitle');
    const summaryLoading = document.getElementById('summaryLoading');
    const summaryContent = document.getElementById('summaryContent');
    const summaryError = document.getElementById('summaryError');
    const keyPoints = document.getElementById('keyPoints');
    const summaryText = document.getElementById('summaryText');
    
    console.log('Summarizing document ID:', documentId);
    
    summaryModal.classList.remove('hidden');
    summaryTitle.textContent = 'Summarizing: ' + fileName;
    summaryLoading.classList.remove('hidden');
    summaryContent.classList.add('hidden');
    summaryError.classList.add('hidden');
    
    // Make API call to summarize the document
    fetch('../actions/summarize_document.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            documentId: documentId,
            fileName: fileName
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Non-JSON response:', text);
                throw new Error('Server returned non-JSON response. Check console for details.');
            });
        }
        
        return response.json();
    })
    .then(data => {
        summaryLoading.classList.add('hidden');
        
        if (data.success) {
            // Display the summary
            summaryContent.classList.remove('hidden');
            
            // Clear previous content
            keyPoints.innerHTML = '';
            
            // Add key points
            if (data.keyPoints && Array.isArray(data.keyPoints)) {
                data.keyPoints.forEach(point => {
                    const li = document.createElement('li');
                    li.textContent = point;
                    keyPoints.appendChild(li);
                });
            } else {
                const li = document.createElement('li');
                li.textContent = 'No key points available';
                keyPoints.appendChild(li);
            }
            
            // Add summary text
            if (data.summary) {
                summaryText.innerHTML = data.summary;
            } else {
                summaryText.innerHTML = '<p>No summary available</p>';
            }
        } else {
            // Show error
            summaryError.classList.remove('hidden');
            console.error('Error:', data.message || 'Unknown error');
            document.getElementById('summaryError').innerHTML = `
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-red-700">${data.message || 'Error generating summary. Please try again later.'}</p>
                    </div>
                </div>
            `;
        }
    })
    .catch(error => {
        summaryLoading.classList.add('hidden');
        summaryError.classList.remove('hidden');
        console.error('Error:', error);
        document.getElementById('summaryError').innerHTML = `
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-red-700">Error: ${error.message || 'Failed to generate summary. Please try again later.'}</p>
                </div>
            </div>
        `;
    });
}

// Document AI analysis function
function documentAnalyze(documentId, fileName) {
    // Show the modal
    const aiAnalysisModal = document.getElementById('aiAnalysisModal');
    const aiAnalysisTitle = document.getElementById('aiAnalysisTitle');
    const aiAnalysisLoading = document.getElementById('aiAnalysisLoading');
    const aiAnalysisContent = document.getElementById('aiAnalysisContent');
    const aiAnalysisError = document.getElementById('aiAnalysisError');
    
    console.log('Analyzing document ID:', documentId);
    
    aiAnalysisModal.classList.remove('hidden');
    aiAnalysisTitle.textContent = 'Analyzing: ' + fileName;
    aiAnalysisLoading.classList.remove('hidden');
    aiAnalysisContent.classList.add('hidden');
    aiAnalysisError.classList.add('hidden');
    
    // Call the API
    fetch('../actions/analyze_document.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            documentId: documentId,
            fileName: fileName
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Non-JSON response:', text);
                throw new Error('Server returned non-JSON response. Check console for details.');
            });
        }
        
        return response.json();
    })
    .then(data => {
        aiAnalysisLoading.classList.add('hidden');
        
        if (data.success !== false) {
            // Display the analysis results
            aiAnalysisContent.classList.remove('hidden');
            
            // Display classification results
            const classificationResults = document.getElementById('classificationResults');
            classificationResults.innerHTML = '';
            
            if (data.classification && data.classification.length > 0) {
                data.classification.forEach(category => {
                    const categoryBadge = document.createElement('div');
                    categoryBadge.className = 'px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800';
                    categoryBadge.innerHTML = `${category.name} <span class="text-xs text-indigo-600">${category.confidence}%</span>`;
                    classificationResults.appendChild(categoryBadge);
                });
            } else {
                classificationResults.innerHTML = '<p class="text-gray-500">No categories found</p>';
            }
            
            // Display entities results
            const entitiesResults = document.getElementById('entitiesResults');
            entitiesResults.innerHTML = '';
            
            if (data.entities && data.entities.length > 0) {
                // Group entities by type
                const entityTypes = {};
                data.entities.forEach(entity => {
                    if (!entityTypes[entity.type]) {
                        entityTypes[entity.type] = [];
                    }
                    entityTypes[entity.type].push(entity);
                });
                
                // Create a section for each entity type
                Object.keys(entityTypes).forEach(type => {
                    const typeSection = document.createElement('div');
                    typeSection.className = 'mb-3';
                    
                    const typeHeading = document.createElement('h5');
                    typeHeading.className = 'font-medium text-gray-700 mb-1';
                    typeHeading.textContent = type;
                    typeSection.appendChild(typeHeading);
                    
                    const entitiesList = document.createElement('div');
                    entitiesList.className = 'flex flex-wrap gap-2';
                    
                    entityTypes[type].forEach(entity => {
                        const entityBadge = document.createElement('div');
                        entityBadge.className = 'px-2 py-1 rounded text-sm bg-gray-100 text-gray-800';
                        entityBadge.textContent = entity.text || entity.name;
                        entitiesList.appendChild(entityBadge);
                    });
                    
                    typeSection.appendChild(entitiesList);
                    entitiesResults.appendChild(typeSection);
                });
            } else {
                entitiesResults.innerHTML = '<p class="text-gray-500">No entities found</p>';
            }
            
            // Display sentiment results
            const sentimentResults = document.getElementById('sentimentResults');
            if (data.sentiment) {
                sentimentResults.innerHTML = `<p class="text-lg font-medium">${data.sentiment}</p>`;
            } else {
                sentimentResults.innerHTML = '<p class="text-gray-500">No sentiment analysis available</p>';
            }
            
            // Display keywords results
            const keywordsResults = document.getElementById('keywordsResults');
            keywordsResults.innerHTML = '';
            
            if (data.keywords && data.keywords.length > 0) {
                data.keywords.forEach(keyword => {
                    const keywordBadge = document.createElement('div');
                    keywordBadge.className = 'px-2 py-1 rounded text-sm bg-blue-100 text-blue-800';
                    keywordBadge.textContent = keyword;
                    keywordsResults.appendChild(keywordBadge);
                });
            } else {
                keywordsResults.innerHTML = '<p class="text-gray-500">No keywords found</p>';
            }
            
            // Display summary
            const analysisSummary = document.getElementById('analysisSummary');
            if (data.summary) {
                analysisSummary.innerHTML = data.summary;
            } else {
                analysisSummary.innerHTML = '<p class="text-gray-500">No summary available</p>';
            }
        } else {
            // Show error
            aiAnalysisError.classList.remove('hidden');
            console.error('Error:', data.message || 'Unknown error');
            document.getElementById('aiAnalysisError').innerHTML = `
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-red-700">${data.message || 'Error generating analysis. Please try again later.'}</p>
                    </div>
                </div>
            `;
        }
    })
    .catch(error => {
        aiAnalysisLoading.classList.add('hidden');
        aiAnalysisError.classList.remove('hidden');
        console.error('Error:', error);
        document.getElementById('aiAnalysisError').innerHTML = `
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                <div class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-red-700">Error: ${error.message || 'Failed to generate analysis. Please try again later.'}</p>
                </div>
            </div>
        `;
    });
}

// Close summary modal
document.getElementById('closeSummaryModal').addEventListener('click', function() {
    document.getElementById('summaryModal').classList.add('hidden');
});

document.getElementById('closeSummaryBtn').addEventListener('click', function() {
    document.getElementById('summaryModal').classList.add('hidden');
});

// Close analysis modal
document.getElementById('closeAnalysisModal').addEventListener('click', function() {
    document.getElementById('aiAnalysisModal').classList.add('hidden');
});

document.getElementById('closeAnalysisBtn').addEventListener('click', function() {
    document.getElementById('aiAnalysisModal').classList.add('hidden');
});

// Copy summary to clipboard
document.getElementById('copySummaryBtn').addEventListener('click', function() {
    const keyPoints = document.getElementById('keyPoints').innerText;
    const summaryText = document.getElementById('summaryText').innerText;
    const fullSummary = `KEY POINTS:\n${keyPoints}\n\nSUMMARY:\n${summaryText}`;
    
    navigator.clipboard.writeText(fullSummary).then(function() {
        showNotification('Summary copied to clipboard!');
    }, function(err) {
        console.error('Could not copy text: ', err);
        showNotification('Failed to copy to clipboard', 'error');
    });
});

// Copy analysis to clipboard
document.getElementById('copyAnalysisBtn').addEventListener('click', function() {
    const classification = document.getElementById('classificationResults').innerText;
    const entities = document.getElementById('entitiesResults').innerText;
    const sentiment = document.getElementById('sentimentResults').innerText;
    const keywords = document.getElementById('keywordsResults').innerText;
    const summary = document.getElementById('analysisSummary').innerText;
    const fullAnalysis = `CLASSIFICATION:\n${classification}\n\nENTITIES:\n${entities}\n\nSENTIMENT:\n${sentiment}\n\nKEYWORDS:\n${keywords}\n\nSUMMARY:\n${summary}`;
    
    navigator.clipboard.writeText(fullAnalysis).then(function() {
        showNotification('Analysis copied to clipboard!');
    }, function(err) {
        console.error('Could not copy text: ', err);
        showNotification('Failed to copy to clipboard', 'error');
    });
});

// Notification function
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `fixed bottom-4 right-4 px-4 py-2 rounded-lg shadow-lg transform translate-y-10 opacity-0 transition-all duration-300 z-50 ${
        type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
    }`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.remove('translate-y-10', 'opacity-0');
    }, 100);
    
    // Hide notification after 3 seconds
    setTimeout(() => {
        notification.classList.add('translate-y-10', 'opacity-0');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Upcoming documents functionality - make globally accessible
window.toggleUpcomingDocuments = function() {
    const upcomingSection = document.getElementById('upcomingDocuments');
    const toggleText = document.getElementById('toggleText');
    const toggleButton = document.getElementById('toggleUpcoming');
    
    if (!upcomingSection || !toggleText || !toggleButton) {
        console.error('Upcoming documents elements not found');
        return;
    }
    
    if (upcomingSection.style.display === 'none' || !upcomingSection.style.display) {
        upcomingSection.style.display = 'block';
        toggleText.textContent = 'Hide Upcoming';
        toggleButton.classList.remove('bg-blue-600', 'hover:bg-blue-700');
        toggleButton.classList.add('bg-gray-600', 'hover:bg-gray-700');
    } else {
        upcomingSection.style.display = 'none';
        toggleText.textContent = 'Show Upcoming';
        toggleButton.classList.remove('bg-gray-600', 'hover:bg-gray-700');
        toggleButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
    }
}

// Make globally accessible
window.showDocumentProgress = function(documentId) {
    // Redirect to the tracking page
    window.location.href = `dashboard.php?page=track&id=${documentId}`;
}

function trackMemorandumView(documentId) {
    // Check if this is a memorandum by looking at the document data
    // For now, we'll track all preview views as potential memorandum views
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
        } else {
            console.log('Not a memorandum or tracking failed:', data);
        }
    })
    .catch(error => {
        console.error('Error tracking memorandum view:', error);
    });
}

// Make globally accessible
window.showDocumentPreview = function(documentId) {
    // Track memorandum view if this is a memorandum
    trackMemorandumView(documentId);
    
    // Create modal for document preview
    const modal = document.createElement('div');
    modal.id = 'documentPreviewModal';
    modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-hidden">
            <div class="px-6 py-4 bg-yellow-50 border-b border-yellow-100 flex justify-between items-center">
                <h3 class="text-lg font-medium text-yellow-800">Document Preview</h3>
                <button onclick="closeDocumentPreview()" class="text-yellow-600 hover:text-yellow-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-0 overflow-hidden max-h-[calc(90vh-56px)]">
                <iframe src="../pages/print_document.php?id=${documentId}&mode=preview" width="100%" height="700" class="border-0 block" title="Document Preview"></iframe>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);

}

window.closeDocumentPreview = function() {
    const modal = document.getElementById('documentPreviewModal');
    if (modal) {
        modal.remove();
    }
}

function loadDocumentPreview(documentId) {
    fetch(`../api/get_document_preview.php?document_id=${documentId}`)
        .then(async (response) => {
            const contentType = response.headers.get('content-type') || '';
            if (!response.ok) {
                // Try to read JSON error
                if (contentType.includes('application/json')) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.error || `HTTP ${response.status}`);
                }
                const text = await response.text().catch(() => '');
                throw new Error(text || `HTTP ${response.status}`);
            }
            if (!contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error(text.substring(0, 300) || 'Server returned non-JSON');
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success) {
                displayDocumentPreview(data.data);
            } else {
                const msg = (data && (data.error || data.message)) || 'Preview not available for this document.';
                document.getElementById('documentPreviewContent').innerHTML = `
                    <div class="text-center p-6 bg-red-50 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="text-xl font-semibold mb-2">Preview Not Available</h3>
                        <p class="text-gray-600">${escapeHtml(msg)}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading document preview:', error);
            document.getElementById('documentPreviewContent').innerHTML = `
                <div class="text-center p-6 bg-red-50 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="text-xl font-semibold mb-2">Preview Error</h3>
                    <p class="text-gray-600">${escapeHtml(error.message || 'Failed to load document preview.')}</p>
                </div>
            `;
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function fixFilePath(filePath) {
    if (!filePath) return '';
    // Normalize path separators
    let path = filePath.replace(/\\/g, '/');
    // If path doesn't start with ../ or /, add ../
    if (!path.startsWith('../') && !path.startsWith('/')) {
        path = '../' + path;
    }
    return path;
}

function displayDocumentPreview(data) {
    const { document, workflow } = data;
    
    // Prepare document content display
    let documentContentHtml = '';
    const filePath = document.file_path ? fixFilePath(document.file_path) : '';
    const fileExtension = filePath ? filePath.split('.').pop().toLowerCase() : '';
    const hasGoogleDoc = document.google_doc_id ? true : false;
    const googleDocId = document.google_doc_id || '';
    
    // Try to display document content
    if (hasGoogleDoc && googleDocId) {
        documentContentHtml = `
            <div class="mb-4">
                <h4 class="text-lg font-medium text-gray-900 mb-2">Document Content</h4>
                <div class="border rounded-lg overflow-hidden">
                    <iframe src="https://docs.google.com/document/d/${googleDocId}/preview" width="100%" height="700px" class="border-0"></iframe>
                </div>
            </div>
        `;
    } else if (filePath) {
        // Handle different file types
        switch (fileExtension) {
            case 'txt':
                // Load text file content
                fetch(filePath)
                    .then(response => response.text())
                    .then(content => {
                        const contentDiv = document.getElementById('documentContentPreview');
                        if (contentDiv) {
                            contentDiv.innerHTML = `
                                <div class="mb-4">
                                    <h4 class="text-lg font-medium text-gray-900 mb-2">Document Content</h4>
                                    <div class="p-4 bg-white rounded-lg border text-gray-800 whitespace-pre-wrap max-h-96 overflow-y-auto">${escapeHtml(content)}</div>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        const contentDiv = document.getElementById('documentContentPreview');
                        if (contentDiv) {
                            contentDiv.innerHTML = `
                                <div class="mb-4">
                                    <h4 class="text-lg font-medium text-gray-900 mb-2">Document Content</h4>
                                    <div class="p-4 bg-red-50 rounded-lg border border-red-200 text-red-700">
                                        Error loading file: ${escapeHtml(error.message)}
                                    </div>
                                </div>
                            `;
                        }
                    });
                documentContentHtml = '<div id="documentContentPreview"><div class="p-4 text-center"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div><p class="mt-2 text-gray-600">Loading content...</p></div></div>';
                break;
            case 'html':
            case 'htm':
                documentContentHtml = `
                    <div class="mb-4">
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Document Content</h4>
                        <div class="border rounded-lg overflow-hidden">
                            <iframe src="${filePath}" width="100%" height="700px" class="border-0"></iframe>
                        </div>
                    </div>
                `;
                break;
            case 'pdf':
                documentContentHtml = `
                    <div class="mb-4">
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Document Content</h4>
                        <div class="border rounded-lg overflow-hidden">
                            <iframe src="${filePath}" width="100%" height="700px" class="border-0"></iframe>
                        </div>
                    </div>
                `;
                break;
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
                documentContentHtml = `
                    <div class="mb-4">
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Document Content</h4>
                        <div class="border rounded-lg p-4 bg-gray-50">
                            <img src="${filePath}" alt="Document Preview" class="max-w-full h-auto mx-auto rounded-lg shadow">
                        </div>
                    </div>
                `;
                break;
            default:
                documentContentHtml = `
                    <div class="mb-4">
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Document Content</h4>
                        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <p class="text-gray-600 mb-2">Preview not available for this file type.</p>
                            <a href="${filePath}" target="_blank" class="text-blue-600 hover:text-blue-800 underline">Download file to view</a>
                        </div>
                    </div>
                `;
        }
    } else if (document.content) {
        documentContentHtml = `
            <div class="mb-4">
                <h4 class="text-lg font-medium text-gray-900 mb-2">Document Content</h4>
                <div class="p-4 bg-white rounded-lg border text-gray-800 whitespace-pre-wrap">${escapeHtml(document.content)}</div>
            </div>
        `;
    } else if (document.description) {
        documentContentHtml = `
            <div class="mb-4">
                <h4 class="text-lg font-medium text-gray-900 mb-2">Document Description</h4>
                <div class="p-4 bg-white rounded-lg border text-gray-800 whitespace-pre-wrap">${escapeHtml(document.description)}</div>
            </div>
        `;
    } else {
        documentContentHtml = `
            <div class="mb-4">
                <h4 class="text-lg font-medium text-gray-900 mb-2">Document Content</h4>
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-gray-600">No document content available.</p>
                </div>
            </div>
        `;
    }
    
    document.getElementById('documentPreviewContent').innerHTML = `
        <div class="mb-6">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-yellow-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-yellow-800 font-medium">Preview Mode</span>
                </div>
                <p class="text-yellow-700 text-sm mt-1">This document is coming to your office. You can preview it but cannot take action yet.</p>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Document Code</label>
                    <p class="text-gray-900">${document.document_code}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <p class="text-gray-900">${escapeHtml(document.title)}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <p class="text-gray-900">${escapeHtml(document.type_name || 'N/A')}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Created By</label>
                    <p class="text-gray-900">${escapeHtml(document.creator_name || 'N/A')}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Created Date</label>
                    <p class="text-gray-900">${document.created_at || 'N/A'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Status</label>
                    <p class="text-gray-900">${escapeHtml(document.status || 'N/A')}</p>
                </div>
            </div>
        </div>
        
        ${documentContentHtml}
        
        <div>
            <h4 class="text-lg font-medium text-gray-900 mb-3">Workflow Progress</h4>
            <div class="space-y-2">
                ${workflow.map(step => `
                    <div class="flex items-center p-3 border rounded-lg ${step.is_current ? 'bg-blue-50 border-blue-200' : step.is_completed ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200'}">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${step.is_current ? 'bg-blue-500' : step.is_completed ? 'bg-green-500' : 'bg-gray-300'} text-white text-sm font-medium">
                            ${step.step_order || ''}
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-gray-900">${escapeHtml(step.office_name || 'Unknown Office')}</p>
                            <p class="text-xs text-gray-500">${escapeHtml(step.status || 'Pending')}</p>
                        </div>
                        <div class="flex-shrink-0">
                            ${step.is_current ? '<span class="text-blue-600 text-xs font-medium">Current</span>' : 
                              step.is_completed ? '<span class="text-green-600 text-xs font-medium">Completed</span>' : 
                              '<span class="text-gray-500 text-xs">Pending</span>'}
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

// Document Action Modal Functions
let currentActionData = { documentId: null, action: null, title: null };

// Make function globally accessible
window.openActionModal = function(documentId, action, title, e) {
    console.log('openActionModal called:', { documentId, action, title });
    
    // Prevent any default behavior if event is provided
    if (e) {
        if (e.preventDefault) e.preventDefault();
        if (e.stopPropagation) e.stopPropagation();
    }
    
    currentActionData = { documentId, action, title };
    const modal = document.getElementById('actionModal');
    
    if (!modal) {
        console.error('Action modal not found');
        alert('Error: Modal not found. Please refresh the page.');
        return false;
    }
    
    console.log('Modal found, showing modal...');
    
    const modalTitle = document.getElementById('actionModalTitle');
    const documentTitle = document.getElementById('actionDocumentTitle');
    const actionLabel = document.getElementById('actionLabel');
    const actionRequired = document.getElementById('actionRequired');
    const actionHelpText = document.getElementById('actionHelpText');
    const submitBtn = document.getElementById('submitActionBtn');
    const submitText = document.getElementById('submitActionText');
    const actionError = document.getElementById('actionError');
    const commentsField = document.getElementById('actionComments');
    
    if (!modalTitle || !documentTitle || !actionLabel || !submitBtn || !commentsField) {
        console.error('One or more modal elements not found');
        alert('Error: Modal elements not found. Please refresh the page.');
        return false;
    }
    
    // Reset form
    if (commentsField) commentsField.value = '';
    if (actionError) {
        actionError.classList.add('hidden');
        actionError.textContent = '';
    }
    
    // Set titles and labels based on action
    documentTitle.textContent = title;
    
    const actionLabels = {
        'approve': { title: 'Approve Document', label: 'Comments (Optional):', required: false, help: 'Optional: Add any comments or notes about this approval.', color: '#16a34a' },
        'reject': { title: 'Reject Document', label: 'Reason for Rejection:', required: true, help: 'Please provide a reason for rejecting this document.', color: '#dc2626' },
        'hold': { title: 'Put Document on Hold', label: 'Reason for Hold:', required: true, help: 'Please provide a reason for putting this document on hold.', color: '#2563eb' },
        'request_revision': { title: 'Request Revision', label: 'Revision Details:', required: true, help: 'Please provide details about what needs to be revised.', color: '#d97706' }
    };
    
    const actionConfig = actionLabels[action] || actionLabels.approve;
    modalTitle.textContent = actionConfig.title;
    actionLabel.textContent = actionConfig.label;
    actionRequired.style.display = actionConfig.required ? 'inline' : 'none';
    actionHelpText.textContent = actionConfig.help;
    submitBtn.style.backgroundColor = actionConfig.color;
    submitText.textContent = actionConfig.title.split(' ')[0]; // "Approve", "Reject", etc.
    
    // Close dropdown menu
    document.querySelectorAll('[id^="actions-"]').forEach(m => m.classList.add('hidden'));
    
    // Show modal
    modal.classList.remove('hidden');
    
    // Focus on textarea
    setTimeout(() => {
        if (commentsField) commentsField.focus();
    }, 100);
    
    return false;
}

function closeActionModal() {
    const modal = document.getElementById('actionModal');
    modal.classList.add('hidden');
    currentActionData = { documentId: null, action: null, title: null };
}

function submitDocumentAction() {
    const { documentId, action } = currentActionData;
    const comments = document.getElementById('actionComments').value.trim();
    const submitBtn = document.getElementById('submitActionBtn');
    const submitText = document.getElementById('submitActionText');
    const submitSpinner = document.getElementById('submitActionSpinner');
    const actionError = document.getElementById('actionError');
    const commentsField = document.getElementById('actionComments');
    
    // Validate required fields
    const requiredActions = ['reject', 'hold', 'request_revision'];
    if (requiredActions.includes(action) && comments === '') {
        actionError.classList.remove('hidden');
        actionError.textContent = 'Please provide a reason before submitting.';
        commentsField.focus();
        return;
    }
    
    // Disable submit button and show spinner
    submitBtn.disabled = true;
    submitText.classList.add('hidden');
    submitSpinner.classList.remove('hidden');
    actionError.classList.add('hidden');
    
    // Make AJAX request
    fetch('../actions/process_document_action_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            document_id: documentId,
            action: action,
            comments: comments
        })
    })
    .then(async response => {
        // Log response for debugging
        console.log('Response status:', response.status);
        const contentType = response.headers.get('content-type');
        
        // Clone response so we can read it multiple times if needed
        const responseClone = response.clone();
        
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text);
            // Try to extract error message from HTML if possible
            const errorMatch = text.match(/<b>(.*?)<\/b>/i);
            const errorMsg = errorMatch ? errorMatch[1] : 'Server error occurred';
            throw new Error('Server returned non-JSON response: ' + errorMsg);
        }
        
        try {
            return await response.json();
        } catch (jsonError) {
            // If JSON parsing fails, try to get the text response
            console.error('JSON parse error:', jsonError);
            const text = await responseClone.text();
            console.error('Response text:', text);
            throw new Error('Invalid JSON response from server. Please check the console for details.');
        }
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            // Show success message
            showNotification(data.message, 'success');
            
            // Close modal
            closeActionModal();
            
            // Reload the page to reflect changes
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } else {
            // Show error
            actionError.classList.remove('hidden');
            actionError.textContent = data.message || 'An error occurred. Please try again.';
            submitBtn.disabled = false;
            submitText.classList.remove('hidden');
            submitSpinner.classList.add('hidden');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        actionError.classList.remove('hidden');
        actionError.textContent = error.message || 'Network error. Please check your connection and try again.';
        submitBtn.disabled = false;
        submitText.classList.remove('hidden');
        submitSpinner.classList.add('hidden');
    });
}

// Event listeners for action modal
document.addEventListener('DOMContentLoaded', function() {
    const actionModal = document.getElementById('actionModal');
    const closeActionModalBtn = document.getElementById('closeActionModal');
    const cancelActionBtn = document.getElementById('cancelActionBtn');
    const submitActionBtn = document.getElementById('submitActionBtn');
    
    // Attach event listeners to document action buttons using event delegation
    document.addEventListener('click', function(e) {
        const actionBtn = e.target.closest('.document-action-btn');
        if (actionBtn) {
            e.preventDefault();
            e.stopPropagation();
            const docId = parseInt(actionBtn.getAttribute('data-doc-id'));
            const action = actionBtn.getAttribute('data-action');
            const title = actionBtn.getAttribute('data-title');
            console.log('Action button clicked:', { docId, action, title });
            if (docId && action && title) {
                openActionModal(docId, action, title, e);
            }
            // Close the dropdown menu
            document.querySelectorAll('[id^="actions-"]').forEach(m => m.classList.add('hidden'));
        }
    });
    
    // Close modal handlers
    if (closeActionModalBtn) {
        closeActionModalBtn.addEventListener('click', closeActionModal);
    }
    if (cancelActionBtn) {
        cancelActionBtn.addEventListener('click', closeActionModal);
    }
    if (submitActionBtn) {
        submitActionBtn.addEventListener('click', submitDocumentAction);
    }
    
    // Close modal on outside click
    if (actionModal) {
        actionModal.addEventListener('click', function(e) {
            if (e.target === actionModal) {
                closeActionModal();
            }
        });
    }
    
    // Submit on Enter key (Ctrl+Enter or Cmd+Enter)
    const commentsField = document.getElementById('actionComments');
    if (commentsField) {
        commentsField.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                submitDocumentAction();
            }
        });
    }
    
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
});

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
                // Document title is already set from function parameter, no need to override
                
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

    function updateTable(url, pushState = true) {
        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.getElementById('page-content');
                if (newContent) {
                    pageContent.innerHTML = newContent.innerHTML;
                    // Re-evaluate scripts in the new content if any. 
                    // For this use case, we are moving all JS out so it is not needed.
                }
                if (pushState) {
                    history.pushState({ path: url }, '', url);
                }
            })
            .catch(error => console.error('Error updating table:', error));
    }

    pageContent.addEventListener('input', function(e) {
        if (e.target && e.target.id === 'search') {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const searchTerm = e.target.value;
                const entriesPerPageSelect = document.getElementById('entriesPerPage');
                const entries = entriesPerPageSelect ? entriesPerPageSelect.value : 15;
                const url = `?search=${encodeURIComponent(searchTerm)}&show=${entries}&page=1`;
                updateTable(url);
            }, 300);
        }
    });

    pageContent.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'entriesPerPage') {
            const searchInput = document.getElementById('search');
            const searchTerm = searchInput ? searchInput.value : '';
            const entries = e.target.value;
            const url = `?page=1&show=${entries}&search=${encodeURIComponent(searchTerm)}`;
            updateTable(url);
        }
    });

    pageContent.addEventListener('click', function (e) {
        const paginationLink = e.target.closest('.flex.space-x-1 a');
        if (paginationLink && paginationLink.href) {
            e.preventDefault();
            updateTable(paginationLink.href);
        }
        
        const refreshButton = e.target.closest('button');
        if (refreshButton && refreshButton.textContent.includes('Refresh')) {
            e.preventDefault();
            window.location.reload();
        }
        
        const toggleButton = e.target.closest('#toggleUpcoming');
        if (toggleButton) {
            e.preventDefault();
            e.stopPropagation();
            toggleUpcomingDocuments();
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
                    if (col.cellIndex !== 7) { // Skip actions column (8th column, index 7)
                        rowData.push('"' + (col.textContent.trim().replace(/"/g, '""')) + '"');
                    }
                }
                csv.push(rowData.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', 'incoming_documents_' + new Date().toISOString().slice(0, 10) + '.csv');
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
            // When the user navigates back to the initial state
            updateTable(location.pathname + location.search, false);
        }
    });
});
  </script>
</body>
</html>
<script>
// Simple action menu toggler and outside click handler
function toggleActionMenu(id){
  const menu = document.getElementById(id);
  if(!menu) return;
  const isHidden = menu.classList.contains('hidden');
  document.querySelectorAll('[id^="actions-"]').forEach(m=>m.classList.add('hidden'));
  if(isHidden){ menu.classList.remove('hidden'); }
}
document.addEventListener('click', function(e){
  const toggleClicked = e.target.closest('button') && e.target.closest('button').getAttribute('onclick') && e.target.closest('button').getAttribute('onclick').includes('toggleActionMenu');
  const inMenu = e.target.closest('[id^="actions-"]');
  if(!toggleClicked && !inMenu){
    document.querySelectorAll('[id^="actions-"]').forEach(m=>m.classList.add('hidden'));
  }
});
</script>