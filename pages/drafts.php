<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
	header('Location: ../auth/login.php');
	exit;
}

$userId = (int)$_SESSION['user_id'];

// Ensure table exists (safe)
$conn->query("CREATE TABLE IF NOT EXISTS document_drafts (
  draft_id INT(11) NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  title VARCHAR(255),
  type_id INT(11),
  content LONGTEXT,
  workflow TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (draft_id)
)");

// Fetch drafts
$stmt = $conn->prepare("SELECT d.draft_id, d.title, d.type_id, d.updated_at, dt.type_name
  FROM document_drafts d
  LEFT JOIN document_types dt ON d.type_id = dt.type_id
  WHERE d.user_id = ?
  ORDER BY d.updated_at DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

?>
<?php if (!defined('INCLUDED_IN_DASHBOARD')): ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Drafts</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <div class="max-w-6xl mx-auto p-6">
<?php else: ?>
  <div class="bg-white rounded-lg p-4">
<?php endif; ?>
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Drafts</h1>
      <a href="dashboard.php?page=compose" class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700">New Document</a>
    </div>
    <div class="bg-white shadow rounded-lg overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-100 text-left">
            <th class="p-3">Title</th>
            <th class="p-3">Type</th>
            <th class="p-3">Last Saved</th>
            <th class="p-3 text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr class="border-b">
              <td class="p-3"><?php echo htmlspecialchars($row['title'] ?: 'Untitled Draft'); ?></td>
              <td class="p-3"><?php echo htmlspecialchars($row['type_name'] ?: '—'); ?></td>
              <td class="p-3"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($row['updated_at']))); ?></td>
              <td class="p-3 text-center">
                <a class="px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700" href="dashboard.php?page=compose&draft_id=<?php echo (int)$row['draft_id']; ?>">Edit</a>
                <button class="px-3 py-1 rounded bg-red-600 text-white hover:bg-red-700" onclick="deleteDraft(<?php echo (int)$row['draft_id']; ?>)">Delete</button>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td class="p-6 text-center text-gray-500" colspan="4">No drafts yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <script>
    function deleteDraft(id){
      if(!confirm('Delete this draft permanently?')) return;
      fetch('../actions/save_draft.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        credentials:'same-origin',
        body: new URLSearchParams({ action:'delete_draft', draft_id:String(id) })
      })
      .then(r=>r.json())
      .then(d=>{ if(d.success) location.reload(); else alert(d.message||'Failed to delete'); })
      .catch(e=>alert(e.message||'Network error'))
    }
  </script>
<?php if (!defined('INCLUDED_IN_DASHBOARD')): ?>
</body>
</html>
<?php endif; ?>

