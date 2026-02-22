<?php
require_once '../includes/config.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'] ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Plus+Jakarta+Sans%3Awght%40400%3B500%3B700%3B800" />
  <title>New Inbox - SCC DMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      font-family: "Plus Jakarta Sans", "Noto Sans", sans-serif;
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
    .badge-on_hold {
      background-color: #FEF2FF;
      color: #6D28D9;
    }
    .badge-revision_requested {
      background-color: #FFF7ED;
      color: #A16207;
    }
  </style>
</head>
<body class="bg-gray-50">
  <div class="p-6">
    <div class="mb-6 flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold">New Inbox</h1>
        <div class="flex items-center text-sm text-gray-500">
          <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
          <span class="mx-2">/</span>
          <a href="dashboard.php?page=documents" class="hover:text-gray-700">Documents</a>
          <span class="mx-2">/</span>
          <span>New Inbox</span>
        </div>
      </div>
      <div class="flex space-x-2">
        <button onclick="window.location.reload()" class="bg-green-700 text-white px-4 py-2 rounded-lg hover:bg-green-800 flex items-center">
          <i class="fas fa-sync-alt mr-2"></i> Refresh
        </button>
      </div>
    </div>

    <!-- Records Section -->
    <div class="bg-white rounded-lg shadow">
      <div class="p-4 border-b flex justify-between items-center">
        <div>
          <h2 class="text-lg font-semibold">Incoming Documents</h2>
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
              <th class="p-4 font-medium">Title</th>
              <th class="p-4 font-medium">Type</th>
              <th class="p-4 font-medium">Sender</th>
              <th class="p-4 font-medium">From Office</th>
              <th class="p-4 font-medium">Date</th>
              <th class="p-4 font-medium">Status</th>
              <th class="p-4 font-medium text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // ULTRA SIMPLE QUERY - just get the documents
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
                    ORDER BY d.created_at DESC";

            $result = $conn->query($sql);
            $total_records = ($result) ? $result->num_rows : 0;
            
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
                    echo "<td class='p-4'>{$row['title']}</td>";
                    echo "<td class='p-4'>{$row['type_name']}</td>";
                    echo "<td class='p-4'>{$row['creator_name']}</td>";
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
            Showing <?php echo $total_records; ?> document<?php echo $total_records != 1 ? "s" : ""; ?>
          <?php else: ?>
            No documents found
          <?php endif; ?>
        </div>
        <div>
          <a href="incoming.php" class="text-blue-600 hover:underline">Go to original inbox</a>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Update document count
    document.getElementById("documentCount").textContent = "<?php echo $total_records; ?> document<?php echo $total_records != 1 ? 's' : ''; ?> found";
  </script>
</body>
</html>
