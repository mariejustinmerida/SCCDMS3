<?php
require_once 'includes/config.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'] ?? 0;

// First, let's fix any case sensitivity issues
$fix_query = "UPDATE document_workflow SET status = 'current' WHERE LOWER(status) = 'current' AND status != 'current'";
$conn->query($fix_query);
$fixed_count = $conn->affected_rows;

// Now let's check if there are any documents assigned to this office
$check_query = "SELECT COUNT(*) as count FROM document_workflow WHERE office_id = $office_id";
$check_result = $conn->query($check_query);
$assigned_count = ($check_result && $row = $check_result->fetch_assoc()) ? $row['count'] : 0;

// Check if there are any documents with 'current' status
$current_query = "SELECT COUNT(*) as count FROM document_workflow WHERE office_id = $office_id AND status = 'current'";
$current_result = $conn->query($current_query);
$current_count = ($current_result && $row = $current_result->fetch_assoc()) ? $row['count'] : 0;

// Get all documents for this office regardless of status
$all_docs_query = "SELECT d.document_id, d.title, d.status as doc_status, dw.status as workflow_status, dw.workflow_id
                  FROM document_workflow dw
                  JOIN documents d ON dw.document_id = d.document_id
                  WHERE dw.office_id = $office_id";
$all_docs_result = $conn->query($all_docs_query);

// Force update all workflow statuses to 'current' for testing
if (isset($_GET['force_update'])) {
    $force_query = "UPDATE document_workflow SET status = 'current' WHERE office_id = $office_id";
    $conn->query($force_query);
    $forced_count = $conn->affected_rows;
    header("Location: simple_fix.php?updated=$forced_count");
    exit;
}

// Create a direct link to the incoming page
$incoming_url = "pages/incoming.php";
$new_incoming_url = "pages/new_incoming.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Fix for Incoming Documents</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        h1, h2 { color: #333; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 10px 15px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px; }
        .btn-blue { background: #2196F3; }
        .btn-red { background: #f44336; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Simple Fix for Incoming Documents</h1>
        
        <?php if (isset($_GET['updated'])): ?>
        <div class="card">
            <p class="success">Successfully updated <?php echo $_GET['updated']; ?> document(s) to 'current' status!</p>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Diagnostic Information</h2>
            <p>User ID: <?php echo $user_id; ?></p>
            <p>Office ID: <?php echo $office_id; ?></p>
            <p>Fixed <?php echo $fixed_count; ?> records with case sensitivity issues.</p>
            <p>Total documents assigned to your office: <?php echo $assigned_count; ?></p>
            <p>Documents with 'current' status: <?php echo $current_count; ?></p>
        </div>
        
        <div class="card">
            <h2>All Documents for Your Office</h2>
            <?php if ($all_docs_result && $all_docs_result->num_rows > 0): ?>
                <table>
                    <tr>
                        <th>Document ID</th>
                        <th>Title</th>
                        <th>Document Status</th>
                        <th>Workflow Status</th>
                        <th>Workflow ID</th>
                    </tr>
                    <?php while ($row = $all_docs_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['document_id']; ?></td>
                            <td><?php echo $row['title']; ?></td>
                            <td><?php echo $row['doc_status']; ?></td>
                            <td><?php echo $row['workflow_status']; ?></td>
                            <td><?php echo $row['workflow_id']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            <?php else: ?>
                <p class="warning">No documents found for your office.</p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>Actions</h2>
            <p><a href="?force_update=1" class="btn">Force Update All to 'current'</a></p>
            <p><a href="<?php echo $incoming_url; ?>" class="btn btn-blue">Go to Original Inbox</a></p>
            <p><a href="<?php echo $new_incoming_url; ?>" class="btn btn-blue">Go to New Inbox</a></p>
        </div>
        
        <div class="card">
            <h2>Manual SQL Fix</h2>
            <p>If you have access to the database, run this SQL query:</p>
            <pre>UPDATE document_workflow SET status = 'current' WHERE office_id = <?php echo $office_id; ?>;</pre>
        </div>
    </div>
</body>
</html>
