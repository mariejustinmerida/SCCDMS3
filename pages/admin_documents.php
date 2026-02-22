<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
	header('Location: ../auth/login.php');
	exit;
}

if (($_SESSION['role'] ?? '') !== 'Super Admin') {
	echo "<div class='p-4 bg-red-50 text-red-700 border border-red-200 rounded'>Access denied</div>";
	exit;
}

// Basic list of all documents
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = '';
if ($search !== '') {
	$esc = '%' . $conn->real_escape_string($search) . '%';
	$where = "WHERE d.title LIKE '$esc' OR dt.type_name LIKE '$esc' OR u.full_name LIKE '$esc'";
}

$sql = "SELECT d.document_id, d.title, d.status, d.created_at, dt.type_name, u.full_name AS creator_name
FROM documents d
LEFT JOIN document_types dt ON d.type_id = dt.type_id
LEFT JOIN users u ON d.creator_id = u.user_id
$where
ORDER BY d.created_at DESC
LIMIT 200";
$res = $conn->query($sql);
?>
<?php if (!defined('INCLUDED_IN_DASHBOARD')): ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>All Documents</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <div class="max-w-6xl mx-auto p-6">
<?php else: ?>
  <div class="bg-white rounded-lg p-4">
<?php endif; ?>
    <h1 class="text-2xl font-bold mb-4">All Documents (Super Admin)</h1>
    <form method="get" class="mb-4">
      <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search title, type, creator" class="border rounded px-3 py-2 w-80">
      <button class="ml-2 px-3 py-2 bg-blue-600 text-white rounded">Search</button>
    </form>
    <div id="alert" class="hidden mb-4"></div>
    <div class="bg-white shadow rounded-lg overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-100 text-left">
            <th class="p-3">ID</th>
            <th class="p-3">Title</th>
            <th class="p-3">Type</th>
            <th class="p-3">Creator</th>
            <th class="p-3">Status</th>
            <th class="p-3">Created</th>
            <th class="p-3 text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($res && $res->num_rows > 0): ?>
            <?php while($row = $res->fetch_assoc()): ?>
              <tr class="border-b">
                <td class="p-3">DOC-<?php echo str_pad($row['document_id'], 3, '0', STR_PAD_LEFT); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($row['title']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($row['type_name'] ?? ''); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($row['creator_name'] ?? ''); ?></td>
                <td class="p-3"><span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-sm"><?php echo htmlspecialchars($row['status']); ?></span></td>
                <td class="p-3"><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td class="p-3 text-center">
                  <a class="px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700" href="dashboard.php?page=view_document&id=<?php echo (int)$row['document_id']; ?>">View</a>
                  <button class="px-3 py-1 rounded bg-red-600 text-white hover:bg-red-700" onclick="deleteDocument(<?php echo (int)$row['document_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['title'])); ?>')">Delete</button>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td class="p-4" colspan="7">No documents found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <script>
    function showAlert(msg, type='success'){
      const a = document.getElementById('alert');
      a.className = `mb-4 p-3 rounded ${type==='success'?'bg-green-50 text-green-700 border border-green-200':'bg-red-50 text-red-700 border border-red-200'}`;
      a.textContent = msg; a.classList.remove('hidden');
      setTimeout(()=>a.classList.add('hidden'), 3000);
    }
    function deleteDocument(id, title){
      if(!confirm(`Are you sure you want to delete document "${title}"?`)) return;
      fetch('../actions/delete_document.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        credentials:'same-origin',
        body: JSON.stringify({id:id})
      })
      .then(async r => {
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
          const text = await r.text();
          throw new Error(text || `HTTP ${r.status}`);
        }
        return r.json();
      })
      .then(d=>{
        if(d.success){ 
          showAlert('Document deleted successfully'); 
          setTimeout(() => location.reload(), 1000);
        }
        else { showAlert(d.error||'Delete failed','error'); }
      })
      .catch(err=>showAlert(err.message||'Network error','error'))
    }
  </script>
<?php if (!defined('INCLUDED_IN_DASHBOARD')): ?>
</body>
</html>
<?php endif; ?>

