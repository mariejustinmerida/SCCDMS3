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

// Check if document ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>Error!</strong>
            <span class='block sm:inline'>No document ID provided.</span>
          </div>";
    exit();
}

$document_id = (int)$_GET['id'];

// Fetch document details
$sql = "SELECT d.*, dt.type_name, u.full_name as creator_name, o.office_name as creator_office
        FROM documents d
        JOIN document_types dt ON d.type_id = dt.type_id
        JOIN users u ON d.creator_id = u.user_id
        JOIN offices o ON u.office_id = o.office_id
        WHERE d.document_id = $document_id";

$result = $conn->query($sql);

if (!$result) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>Error!</strong>
            <span class='block sm:inline'>Database error: " . $conn->error . "</span>
          </div>";
    exit();
}

if ($result->num_rows === 0) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>Error!</strong>
            <span class='block sm:inline'>Document not found.</span>
          </div>";
    exit();
}

$document = $result->fetch_assoc();

// Check if document_attachments table exists
$table_exists = false;
$check_table_sql = "SHOW TABLES LIKE 'document_attachments'";
$check_table_result = $conn->query($check_table_sql);
if ($check_table_result && $check_table_result->num_rows > 0) {
    $table_exists = true;
}

// Fetch document attachments if table exists
$attachments = [];
if ($table_exists) {
    $attachments_sql = "SELECT * FROM document_attachments WHERE document_id = $document_id";
    $attachments_result = $conn->query($attachments_sql);
    
    if ($attachments_result) {
        while ($attachment = $attachments_result->fetch_assoc()) {
            $attachments[] = $attachment;
        }
    }
}

// Check workflow_steps table structure
$workflow_steps = [];
$check_workflow_sql = "SHOW COLUMNS FROM workflow_steps LIKE 'document_id'";
$check_workflow_result = $conn->query($check_workflow_sql);

if ($check_workflow_result && $check_workflow_result->num_rows > 0) {
    // Column exists, fetch workflow steps
    $workflow_sql = "SELECT ws.*, o.office_name, 
                    IFNULL(r.role_name, 'Any Role') as role_name
                    FROM workflow_steps ws
                    JOIN offices o ON ws.office_id = o.office_id
                    LEFT JOIN roles r ON ws.role_id = r.role_id
                    WHERE ws.document_id = $document_id
                    ORDER BY ws.step_order ASC";
    $workflow_result = $conn->query($workflow_sql);
    
    if ($workflow_result) {
        while ($step = $workflow_result->fetch_assoc()) {
            $workflow_steps[] = $step;
        }
    }
}

// Get status badge class
$status_class = match($document['status']) {
    'approved' => 'badge-approved',
    'rejected' => 'badge-rejected',
    'pending' => 'badge-pending',
    'received' => 'badge-received',
    default => 'badge-pending'
};
?>

<!DOCTYPE html>
<html>
<head>
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Plus+Jakarta+Sans%3Awght%40400%3B500%3B700%3B800" />
  <title>View Document - SCC DMS</title>
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
    .badge-received {
      background-color: #E0F2FE;
      color: #0369A1;
    }
    .document-section {
      border-bottom: 1px solid #e5e7eb;
      padding-bottom: 1.5rem;
      margin-bottom: 1.5rem;
    }
    .document-section:last-child {
      border-bottom: none;
      padding-bottom: 0;
      margin-bottom: 0;
    }
  </style>
</head>
<body class="bg-gray-50">
  <div class="flex pt-[0px]">
    <main class="flex-1 ml-0 p-6">
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold">View Document</h1>
          <div class="flex items-center text-sm text-gray-500">
            <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
            <span class="mx-2">/</span>
            <a href="dashboard.php?page=received" class="hover:text-gray-700">Received</a>
            <span class="mx-2">/</span>
            <span>View Document</span>
          </div>
        </div>
        <div class="flex space-x-2">
          <a href="dashboard.php?page=track&id=<?php echo $document_id; ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
            <i class="fas fa-route mr-2"></i> Track
          </a>
          <a href="javascript:history.back()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Back
          </a>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <div class="document-section">
          <h2 class="text-xl font-semibold mb-4">Document Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <p class="text-gray-500 text-sm mb-1">Document Code</p>
              <p class="font-medium">DOC-<?php echo str_pad($document['document_id'], 3, '0', STR_PAD_LEFT); ?></p>
            </div>
            <div>
              <p class="text-gray-500 text-sm mb-1">Status</p>
              <p><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($document['status']); ?></span></p>
            </div>
            <div>
              <p class="text-gray-500 text-sm mb-1">Title</p>
              <p class="font-medium"><?php echo htmlspecialchars($document['title']); ?></p>
            </div>
            <div>
              <p class="text-gray-500 text-sm mb-1">Document Type</p>
              <p><?php echo htmlspecialchars($document['type_name']); ?></p>
            </div>
            <div>
              <p class="text-gray-500 text-sm mb-1">Created By</p>
              <p><?php echo htmlspecialchars($document['creator_name']); ?></p>
            </div>
            <div>
              <p class="text-gray-500 text-sm mb-1">From Office</p>
              <p><?php echo htmlspecialchars($document['creator_office']); ?></p>
            </div>
            <div>
              <p class="text-gray-500 text-sm mb-1">Date Created</p>
              <p><?php echo date('M j, Y', strtotime($document['created_at'])); ?></p>
            </div>
            <div>
              <p class="text-gray-500 text-sm mb-1">Last Updated</p>
              <p><?php echo isset($document['updated_at']) ? date('M j, Y', strtotime($document['updated_at'])) : 'Not updated'; ?></p>
            </div>
          </div>
        </div>

        <div class="document-section">
          <h2 class="text-xl font-semibold mb-4">Document Content</h2>
          <div class="bg-gray-50 p-4 rounded-lg">
            <p><?php echo isset($document['content']) ? nl2br(htmlspecialchars($document['content'])) : 'No content available'; ?></p>
          </div>
        </div>

        <?php if (!empty($attachments)): ?>
        <div class="document-section">
          <h2 class="text-xl font-semibold mb-4">Attachments</h2>
          <div class="grid grid-cols-1 gap-4">
            <?php foreach ($attachments as $attachment): ?>
              <div class="flex items-center p-3 border rounded-lg">
                <div class="bg-gray-100 p-2 rounded mr-3">
                  <i class="fas fa-file-alt text-gray-500"></i>
                </div>
                <div class="flex-1">
                  <p class="font-medium"><?php echo htmlspecialchars($attachment['file_name']); ?></p>
                  <p class="text-sm text-gray-500"><?php echo formatFileSize($attachment['file_size']); ?></p>
                </div>
                <a href="../uploads/<?php echo $attachment['file_path']; ?>" download class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 text-sm flex items-center">
                  <i class="fas fa-download mr-1"></i> Download
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($workflow_steps)): ?>
        <div class="document-section">
          <h2 class="text-xl font-semibold mb-4">Workflow</h2>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Step</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Office</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($workflow_steps as $step): ?>
                <tr>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $step['step_order']; ?></td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($step['office_name']); ?></td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($step['role_name']); ?></td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?php 
                    $step_status = $step['status'] ?? 'pending';
                    $step_status_class = match($step_status) {
                        'approved' => 'badge-approved',
                        'rejected' => 'badge-rejected',
                        'pending' => 'badge-pending',
                        'received' => 'badge-received',
                        default => 'badge-pending'
                    };
                    echo "<span class='badge $step_status_class'>" . ucfirst($step_status) . "</span>";
                    ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?php echo isset($step['updated_at']) ? date('M j, Y', strtotime($step['updated_at'])) : '-'; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <script>
    // Add any JavaScript functionality here
  </script>
</body>
</html>

<?php
// Helper function to format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
