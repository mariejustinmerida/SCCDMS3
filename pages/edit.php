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
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'];

// Check if the document belongs to the user's office
$check_sql = "SELECT d.* FROM documents d
              JOIN users u ON d.creator_id = u.user_id
              WHERE d.document_id = $document_id AND u.office_id = $office_id";
$check_result = $conn->query($check_sql);

if (!$check_result) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>Error!</strong>
            <span class='block sm:inline'>Database error: " . $conn->error . "</span>
          </div>";
    exit();
}

if ($check_result->num_rows === 0) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>Error!</strong>
            <span class='block sm:inline'>You don't have permission to edit this document.</span>
          </div>";
    exit();
}

// Fetch document details
$sql = "SELECT d.*, dt.type_name 
        FROM documents d
        JOIN document_types dt ON d.type_id = dt.type_id
        WHERE d.document_id = $document_id";
$result = $conn->query($sql);

if (!$result) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>Error!</strong>
            <span class='block sm:inline'>Database error: " . $conn->error . "</span>
          </div>";
    exit();
}

$document = $result->fetch_assoc();

// Check if document can be edited (only pending or rejected documents can be edited)
if ($document['status'] === 'approved') {
    echo "<div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>Warning!</strong>
            <span class='block sm:inline'>Approved documents cannot be edited.</span>
          </div>";
    exit();
}

// Check if document_attachments table exists
$attachments = [];
$table_exists = false;
$check_table_sql = "SHOW TABLES LIKE 'document_attachments'";
$check_table_result = $conn->query($check_table_sql);
if ($check_table_result && $check_table_result->num_rows > 0) {
    $table_exists = true;
    
    // Fetch document attachments if table exists
    if ($table_exists) {
        $attachments_sql = "SELECT * FROM document_attachments WHERE document_id = $document_id";
        $attachments_result = $conn->query($attachments_sql);
        
        if ($attachments_result) {
            while ($attachment = $attachments_result->fetch_assoc()) {
                $attachments[] = $attachment;
            }
        }
    }
}

// Fetch all document types for dropdown
$types_sql = "SELECT * FROM document_types ORDER BY type_name ASC";
$types_result = $conn->query($types_sql);
$document_types = [];
if ($types_result) {
    while ($type = $types_result->fetch_assoc()) {
        $document_types[] = $type;
    }
}

// Fetch all offices for workflow
$offices_sql = "SELECT * FROM offices ORDER BY office_name ASC";
$offices_result = $conn->query($offices_sql);
$offices = [];
if ($offices_result) {
    while ($office = $offices_result->fetch_assoc()) {
        $offices[] = $office;
    }
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $title = trim($_POST['title']);
    $type_id = (int)$_POST['type_id'];
    
    if (empty($title)) {
        $error_message = "Document title is required.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update document
            $title = $conn->real_escape_string($title);
            $update_sql = "UPDATE documents SET 
                          title = '$title', 
                          type_id = $type_id, 
                          updated_at = NOW() 
                          WHERE document_id = $document_id";
            $update_result = $conn->query($update_sql);
            
            if (!$update_result) {
                throw new Exception("Error updating document: " . $conn->error);
            }
            
            // Handle file uploads if attachments table exists
            if ($table_exists && isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                $upload_dir = "../uploads/";
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Process each file
                for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                    if ($_FILES['attachments']['error'][$i] === 0) {
                        $file_name = $_FILES['attachments']['name'][$i];
                        $file_size = $_FILES['attachments']['size'][$i];
                        $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                        $file_type = $_FILES['attachments']['type'][$i];
                        
                        // Generate unique filename
                        $file_path = uniqid() . '_' . $file_name;
                        
                        // Move file to uploads directory
                        if (move_uploaded_file($file_tmp, $upload_dir . $file_path)) {
                            // Insert attachment record
                            $file_name = $conn->real_escape_string($file_name);
                            $file_path = $conn->real_escape_string($file_path);
                            $file_type = $conn->real_escape_string($file_type);
                            $attach_sql = "INSERT INTO document_attachments (document_id, file_name, file_path, file_size, file_type, uploaded_at) 
                                          VALUES ($document_id, '$file_name', '$file_path', $file_size, '$file_type', NOW())";
                            $attach_result = $conn->query($attach_sql);
                            
                            if (!$attach_result) {
                                throw new Exception("Error adding attachment: " . $conn->error);
                            }
                        }
                    }
                }
            }
            
            // Handle attachment deletions if table exists
            if ($table_exists && isset($_POST['delete_attachments']) && is_array($_POST['delete_attachments'])) {
                foreach ($_POST['delete_attachments'] as $attachment_id) {
                    $attachment_id = (int)$attachment_id;
                    // Get file path
                    $file_sql = "SELECT file_path FROM document_attachments WHERE attachment_id = $attachment_id AND document_id = $document_id";
                    $file_result = $conn->query($file_sql);
                    
                    if (!$file_result) {
                        throw new Exception("Error getting file path: " . $conn->error);
                    }
                    
                    if ($file_result->num_rows > 0) {
                        $file_path = $file_result->fetch_assoc()['file_path'];
                        
                        // Delete file from filesystem
                        $full_path = "../uploads/" . $file_path;
                        if (file_exists($full_path)) {
                            unlink($full_path);
                        }
                        
                        // Delete record from database
                        $delete_sql = "DELETE FROM document_attachments WHERE attachment_id = $attachment_id AND document_id = $document_id";
                        $delete_result = $conn->query($delete_sql);
                        
                        if (!$delete_result) {
                            throw new Exception("Error deleting attachment: " . $conn->error);
                        }
                    }
                }
            }
            
            // Check if workflow_steps table has document_id column
            $check_workflow_sql = "SHOW COLUMNS FROM workflow_steps LIKE 'document_id'";
            $has_workflow = false;
            $check_workflow_result = $conn->query($check_workflow_sql);
            if ($check_workflow_result && $check_workflow_result->num_rows > 0) {
                $has_workflow = true;
            }
            
            // Handle workflow updates if the document is in pending status and workflow table exists
            if ($has_workflow && $document['status'] === 'pending' && isset($_POST['workflow_offices']) && is_array($_POST['workflow_offices'])) {
                // First, delete existing workflow steps that haven't been processed yet
                $delete_workflow_sql = "DELETE FROM workflow_steps 
                                       WHERE document_id = $document_id 
                                       AND status = 'pending'";
                $delete_workflow_result = $conn->query($delete_workflow_sql);
                
                if (!$delete_workflow_result) {
                    throw new Exception("Error deleting workflow steps: " . $conn->error);
                }
                
                // Get the highest step order
                $max_order_sql = "SELECT MAX(step_order) as max_order FROM workflow_steps WHERE document_id = $document_id";
                $max_order_result = $conn->query($max_order_sql);
                
                if (!$max_order_result) {
                    throw new Exception("Error getting max step order: " . $conn->error);
                }
                
                $max_order = $max_order_result->fetch_assoc()['max_order'] ?? 0;
                
                // Add new workflow steps
                foreach ($_POST['workflow_offices'] as $index => $office_id) {
                    if (!empty($office_id)) {
                        $step_order = $max_order + $index + 1;
                        $role_id = isset($_POST['workflow_roles'][$index]) ? (int)$_POST['workflow_roles'][$index] : 'NULL';
                        
                        $workflow_sql = "INSERT INTO workflow_steps 
                                        (document_id, office_id, role_id, step_order, status, created_at, updated_at) 
                                        VALUES ($document_id, $office_id, $role_id, $step_order, 'pending', NOW(), NOW())";
                        $workflow_result = $conn->query($workflow_sql);
                        
                        if (!$workflow_result) {
                            throw new Exception("Error adding workflow step: " . $conn->error);
                        }
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            $success_message = "Document updated successfully.";
            
            // Refresh document data
            $result = $conn->query($sql);
            $document = $result->fetch_assoc();
            
            // Refresh attachments if table exists
            if ($table_exists) {
                $attachments = [];
                $attachments_result = $conn->query($attachments_sql);
                if ($attachments_result) {
                    while ($attachment = $attachments_result->fetch_assoc()) {
                        $attachments[] = $attachment;
                    }
                }
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error updating document: " . $e->getMessage();
        }
    }
}

// Check if workflow_steps table has document_id column
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

// Check if roles table has office_id column
$roles_by_office = [];
$check_roles_sql = "SHOW COLUMNS FROM roles LIKE 'office_id'";
$check_roles_result = $conn->query($check_roles_sql);

if ($check_roles_result && $check_roles_result->num_rows > 0) {
    // Column exists, fetch roles
    $roles_sql = "SELECT r.*, o.office_id 
                FROM roles r
                JOIN offices o ON r.office_id = o.office_id
                ORDER BY r.role_name ASC";
    $roles_result = $conn->query($roles_sql);
    
    if ($roles_result) {
        while ($role = $roles_result->fetch_assoc()) {
            if (!isset($roles_by_office[$role['office_id']])) {
                $roles_by_office[$role['office_id']] = [];
            }
            $roles_by_office[$role['office_id']][] = $role;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Plus+Jakarta+Sans%3Awght%40400%3B500%3B700%3B800" />
  <title>Edit Document - SCC DMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      font-family: "Plus Jakarta Sans", "Noto Sans", sans-serif;
    }
    .sidebar {
      background: rgb(22, 59, 32);
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
          <h1 class="text-2xl font-bold">Edit Document</h1>
          <div class="flex items-center text-sm text-gray-500">
            <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
            <span class="mx-2">/</span>
            <a href="dashboard.php?page=outgoing" class="hover:text-gray-700">Outgoing</a>
            <span class="mx-2">/</span>
            <span>Edit Document</span>
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

      <?php if (!empty($success_message)): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline"><?php echo $success_message; ?></span>
      </div>
      <?php endif; ?>

      <?php if (!empty($error_message)): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class='font-bold'>Error!</strong>
        <span class='block sm:inline'><?php echo $error_message; ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6">
        <div class="document-section">
          <h2 class="text-xl font-semibold mb-4">Document Information</h2>
          
          <div class="mb-4">
            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Document Title</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($document['title']); ?>" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
          </div>
          
          <div class="mb-4">
            <label for="type_id" class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
            <select id="type_id" name="type_id" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
              <?php foreach ($document_types as $type): ?>
                <option value="<?php echo $type['type_id']; ?>" <?php echo $type['type_id'] == $document['type_id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($type['type_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="mb-4">
            <label for="content" class="block text-sm font-medium text-gray-700 mb-1">Document Content</label>
            <input type="hidden" id="content" name="content" value="<?php echo htmlspecialchars($document['google_doc_id'] ?? ''); ?>">
            
            <!-- Google Docs Integration -->
            <div class="border rounded-lg overflow-hidden" style="height: 600px;">
              <?php if (!empty($document['google_doc_id'])): ?>
                <iframe src="https://docs.google.com/document/d/<?php echo htmlspecialchars($document['google_doc_id']); ?>/edit?rm=minimal&embedded=true" 
                        class="google-docs-iframe w-full h-full border-0"></iframe>
              <?php else: ?>
                <div id="google-docs-placeholder" class="w-full h-full flex items-center justify-center bg-gray-100">
                  <div class="text-center">
                    <p class="text-gray-500 mb-2">No Google Doc associated with this document.</p>
                    <button type="button" id="createGoogleDoc" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                      Create Google Doc
                    </button>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="document-section">
          <h2 class="text-xl font-semibold mb-4">Attachments</h2>
          
          <?php if (!empty($attachments)): ?>
          <div class="mb-4">
            <h3 class="text-md font-medium mb-2">Current Attachments</h3>
            <div class="space-y-2">
              <?php foreach ($attachments as $attachment): ?>
                <div class="flex items-center p-3 border rounded-lg">
                  <div class="bg-gray-100 p-2 rounded mr-3">
                    <i class="fas fa-file-alt text-gray-500"></i>
                  </div>
                  <div class="flex-1">
                    <p class="font-medium"><?php echo htmlspecialchars($attachment['file_name']); ?></p>
                    <p class="text-sm text-gray-500"><?php echo formatFileSize($attachment['file_size']); ?></p>
                  </div>
                  <div class="flex items-center space-x-2">
                    <a href="../uploads/<?php echo $attachment['file_path']; ?>" download class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 text-sm flex items-center">
                      <i class="fas fa-download mr-1"></i> Download
                    </a>
                    <div class="flex items-center">
                      <input type="checkbox" id="delete_<?php echo $attachment['attachment_id']; ?>" name="delete_attachments[]" value="<?php echo $attachment['attachment_id']; ?>" class="mr-2">
                      <label for="delete_<?php echo $attachment['attachment_id']; ?>" class="text-sm text-red-600">Delete</label>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          
          <div>
            <h3 class="text-md font-medium mb-2">Add New Attachments</h3>
            <div class="flex items-center justify-center w-full">
              <label for="attachments" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                  <i class="fas fa-cloud-upload-alt text-gray-500 text-3xl mb-2"></i>
                  <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                  <p class="text-xs text-gray-500">PDF, DOCX, XLSX, JPG, PNG (MAX. 10MB)</p>
                </div>
                <input id="attachments" name="attachments[]" type="file" multiple class="hidden" />
              </label>
            </div>
            <div id="file-list" class="mt-2 space-y-1"></div>
          </div>
        </div>

        <?php if ($document['status'] === 'pending'): ?>
        <div class="document-section">
          <h2 class="text-xl font-semibold mb-4">Workflow</h2>
          
          <div class="mb-4">
            <h3 class="text-md font-medium mb-2">Current Workflow Steps</h3>
            <?php if (!empty($workflow_steps)): ?>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Step</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Office</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($workflow_steps as $step): ?>
                    <tr>
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $step['step_order']; ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($step['office_name']); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($step['role_name'] ?? 'Any Role'); ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php 
                        $step_status = $step['status'] ?? 'pending';
                        echo ucfirst($step_status);
                        ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-gray-500">No workflow steps defined yet.</p>
            <?php endif; ?>
          </div>
          
          <div>
            <h3 class="text-md font-medium mb-2">Add New Workflow Steps</h3>
            <p class="text-sm text-gray-500 mb-2">You can add new workflow steps for offices that will need to review this document.</p>
            
            <div id="workflow-steps" class="space-y-2">
              <div class="workflow-step flex items-center space-x-2">
                <select name="workflow_offices[]" class="office-select px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600 flex-1">
                  <option value="">Select Office</option>
                  <?php foreach ($offices as $office): ?>
                    <option value="<?php echo $office['office_id']; ?>"><?php echo htmlspecialchars($office['office_name']); ?></option>
                  <?php endforeach; ?>
                </select>
                
                <select name="workflow_roles[]" class="role-select px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600 flex-1">
                  <option value="">Any Role</option>
                </select>
                
                <button type="button" class="remove-step bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>
            
            <button type="button" id="add-step" class="mt-2 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
              <i class="fas fa-plus mr-1"></i> Add Step
            </button>
          </div>
        </div>
        <?php endif; ?>

        <div class="flex justify-end mt-6">
          <a href="javascript:history.back()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 mr-2">Cancel</a>
          <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">Save Changes</button>
        </div>
      </form>
    </main>
  </div>

  <script>
    // Google Docs Integration
    const documentId = <?php echo $document_id; ?>;
    let googleDocId = '<?php echo $document['google_doc_id'] ?? ''; ?>';
    
    // Handle creating a new Google Doc for this document
    document.getElementById('createGoogleDoc')?.addEventListener('click', function() {
      this.disabled = true;
      this.textContent = 'Creating...';
      
      // Get the document title
      const title = document.getElementById('title').value || 'Document';
      
      // Call the API to create a new Google Doc
      fetch('../api/google_docs_api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          'action': 'create_document',
          'title': title,
          'document_id': documentId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Update the hidden input with the Google Doc ID
          document.getElementById('content').value = data.document.id;
          googleDocId = data.document.id;
          
          // Replace the placeholder with the iframe
          const placeholder = document.getElementById('google-docs-placeholder');
          placeholder.innerHTML = `<iframe src="https://docs.google.com/document/d/${data.document.id}/edit?rm=minimal&embedded=true" class="google-docs-iframe w-full h-full border-0"></iframe>`;
          
          // Update the database with the Google Doc ID
          updateDocumentWithGoogleDocId(documentId, data.document.id);
        } else {
          alert('Error creating Google Doc: ' + data.error);
          this.disabled = false;
          this.textContent = 'Create Google Doc';
        }
      })
      .catch(error => {
        alert('Error creating Google Doc: ' + error.message);
        this.disabled = false;
        this.textContent = 'Create Google Doc';
      });
    });
    
    // Update the document record with the Google Doc ID
    function updateDocumentWithGoogleDocId(documentId, googleDocId) {
      fetch('../api/update_document.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          'document_id': documentId,
          'google_doc_id': googleDocId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (!data.success) {
          console.error('Error updating document with Google Doc ID:', data.error);
        }
      })
      .catch(error => {
        console.error('Error updating document with Google Doc ID:', error);
      });
    }
    
    // File upload preview
    const fileInput = document.getElementById('attachments');
    const fileList = document.getElementById('file-list');
    
    fileInput.addEventListener('change', function() {
      fileList.innerHTML = '';
      
      for (const file of this.files) {
        const fileItem = document.createElement('div');
        fileItem.className = 'text-sm text-gray-600';
        fileItem.innerHTML = `<i class="fas fa-file mr-1"></i> ${file.name} (${formatFileSize(file.size)})`;
        fileList.appendChild(fileItem);
      }
    });
    
    // Format file size
    function formatFileSize(bytes) {
      const units = ['B', 'KB', 'MB', 'GB'];
      let size = bytes;
      let unitIndex = 0;
      
      while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
      }
      
      return `${size.toFixed(2)} ${units[unitIndex]}`;
    }
    
    <?php if ($document['status'] === 'pending'): ?>
    // Workflow steps
    const workflowSteps = document.getElementById('workflow-steps');
    const addStepBtn = document.getElementById('add-step');
    const rolesData = <?php echo json_encode($roles_by_office); ?>;
    
    // Add new workflow step
    addStepBtn.addEventListener('click', function() {
      const stepTemplate = workflowSteps.querySelector('.workflow-step').cloneNode(true);
      stepTemplate.querySelector('.office-select').value = '';
      stepTemplate.querySelector('.role-select').innerHTML = '<option value="">Any Role</option>';
      
      workflowSteps.appendChild(stepTemplate);
      
      // Re-attach event listeners
      attachOfficeChangeListeners();
      attachRemoveStepListeners();
    });
    
    // Handle office selection change
    function attachOfficeChangeListeners() {
      const officeSelects = document.querySelectorAll('.office-select');
      
      officeSelects.forEach(select => {
        select.addEventListener('change', function() {
          const roleSelect = this.parentElement.querySelector('.role-select');
          const officeId = this.value;
          
          // Clear current options
          roleSelect.innerHTML = '<option value="">Any Role</option>';
          
          // Add roles for selected office
          if (officeId && rolesData[officeId]) {
            rolesData[officeId].forEach(role => {
              const option = document.createElement('option');
              option.value = role.role_id;
              option.textContent = role.role_name;
              roleSelect.appendChild(option);
            });
          }
        });
      });
    }
    
    // Handle remove step button
    function attachRemoveStepListeners() {
      const removeButtons = document.querySelectorAll('.remove-step');
      
      removeButtons.forEach(button => {
        button.addEventListener('click', function() {
          // Don't remove if it's the only step
          if (workflowSteps.querySelectorAll('.workflow-step').length > 1) {
            this.parentElement.remove();
          }
        });
      });
    }
    
    // Initialize event listeners
    attachOfficeChangeListeners();
    attachRemoveStepListeners();
    <?php endif; ?>
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
