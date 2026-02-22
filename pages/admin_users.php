<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

if (!isset($_SESSION['user_id'])) {
	header('Location: ../auth/login.php');
	exit;
}

if (($_SESSION['role'] ?? '') !== 'Super Admin') {
	echo "<div class='p-4 bg-red-50 text-red-700 border border-red-200 rounded'>Access denied</div>";
	exit;
}
?>
<?php if (!defined('INCLUDED_IN_DASHBOARD')): ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Manage Users</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <div class="max-w-6xl mx-auto p-6">
<?php else: ?>
  <div class="bg-white rounded-lg p-4">
<?php endif; ?>
    <h1 class="text-2xl font-bold mb-4">Manage Users (Super Admin)</h1>
    <div id="alert" class="hidden mb-4"></div>
    <div class="bg-white shadow rounded-lg overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-100 text-left">
            <th class="p-3">ID</th>
            <th class="p-3">Name</th>
            <th class="p-3">Email</th>
            <th class="p-3">Role</th>
            <th class="p-3">Office</th>
            <th class="p-3 text-center">Actions</th>
          </tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
    <!-- Edit User Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-lg w-full max-w-lg mx-4">
        <div class="p-4 border-b flex justify-between items-center">
          <h3 class="text-xl font-semibold">Edit User</h3>
          <button id="closeEditModal" class="text-gray-500 hover:text-gray-700">&times;</button>
        </div>
        <div class="p-4 space-y-4">
          <input type="hidden" id="edit_user_id">
          <div>
            <label class="block text-sm text-gray-700 mb-1">Full name</label>
            <input id="edit_full_name" type="text" class="w-full border rounded px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm text-gray-700 mb-1">Email</label>
            <input id="edit_email" type="email" class="w-full border rounded px-3 py-2" />
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-sm text-gray-700 mb-1">Role</label>
              <select id="edit_role_id" class="w-full border rounded px-3 py-2"></select>
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">Office</label>
              <select id="edit_office_id" class="w-full border rounded px-3 py-2"></select>
            </div>
          </div>
          <div>
            <label class="block text-sm text-gray-700 mb-1">New password (leave blank to keep)</label>
            <input id="edit_password" type="password" class="w-full border rounded px-3 py-2" />
          </div>
        </div>
        <div class="p-4 border-t flex justify-end space-x-2">
          <button id="cancelEditBtn" class="px-4 py-2 rounded bg-gray-500 text-white hover:bg-gray-600">Cancel</button>
          <button id="saveEditBtn" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700">Save</button>
        </div>
      </div>
    </div>
  </div>
  <script>
  function showAlert(msg, type='success'){
    const a = document.getElementById('alert');
    a.className = `mb-4 p-3 rounded ${type==='success'?'bg-green-50 text-green-700 border border-green-200':'bg-red-50 text-red-700 border border-red-200'}`;
    a.textContent = msg; a.classList.remove('hidden');
    setTimeout(()=>a.classList.add('hidden'), 3000);
  }
  function loadUsers(){
    fetch('../api/list_all_users.php', {credentials:'same-origin'})
      .then(async r => {
        const ct = r.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
          const text = await r.text();
          throw new Error(text || `HTTP ${r.status}`);
        }
        return r.json();
      })
      .then(d=>{
        if(!d.success){ showAlert(d.error||'Failed to load users','error'); return; }
        const tb = document.getElementById('tbody'); tb.innerHTML='';
        d.users.forEach(u=>{
          const tr = document.createElement('tr');
          tr.className='border-b';
          tr.innerHTML = `
            <td class=\"p-3\">${u.user_id}</td>
            <td class=\"p-3\">${u.full_name} <div class=\"text-xs text-gray-500\">${u.username}</div></td>
            <td class=\"p-3\">${u.email||''}</td>
            <td class=\"p-3\">${u.role_name||''}</td>
            <td class=\"p-3\">${u.office_name||''}</td>
            <td class=\"p-3 text-center space-x-2\">
              <button class=\"px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700\">Edit</button>
              <button class=\"px-3 py-1 rounded bg-red-600 text-white hover:bg-red-700\">Delete</button>
            </td>`;
          const [editBtn, delBtn] = tr.querySelectorAll('button');
          editBtn.addEventListener('click', ()=> openEdit(u));
          delBtn.addEventListener('click', ()=> deleteUser(u.user_id, (u.full_name||'').replace(/'/g,"&#39;")));
          tb.appendChild(tr);
        });
      })
      .catch(err=>showAlert(err.message||'Network error','error'))
  }
  function deleteUser(id, name){
    if(!confirm(`Are you sure you want to delete user "${name}"?`)) return;
    fetch('../api/delete_user.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify({user_id:id})
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
        const msg = d.message || 'User deleted successfully';
        showAlert(msg); 
        loadUsers(); 
      }
      else { showAlert(d.error||'Delete failed','error'); }
    })
    .catch(err=>showAlert(err.message||'Network error','error'))
  }
  async function loadRolesOffices(){
    const [rolesRes, officesRes] = await Promise.all([
      fetch('../api/list_roles.php', {credentials:'same-origin'}),
      fetch('../api/list_offices.php', {credentials:'same-origin'})
    ]);
    const rolesJson = await rolesRes.json().catch(()=>({roles:[]}));
    const officesJson = await officesRes.json().catch(()=>({offices:[]}));
    const roles = rolesJson.roles||[];
    const offices = officesJson.offices||[];
    const roleSel = document.getElementById('edit_role_id');
    const officeSel = document.getElementById('edit_office_id');
    if(roleSel) roleSel.innerHTML = roles.map(r=>`<option value="${r.role_id}">${r.role_name}</option>`).join('');
    if(officeSel) officeSel.innerHTML = offices.map(o=>`<option value="${o.office_id}">${o.office_name}</option>`).join('');
  }
  function openEdit(u){
    const modal = document.getElementById('editModal');
    document.getElementById('edit_user_id').value = u.user_id;
    document.getElementById('edit_full_name').value = u.full_name||'';
    document.getElementById('edit_email').value = u.email||'';
    loadRolesOffices().then(()=>{
      const roleSel = document.getElementById('edit_role_id');
      const officeSel = document.getElementById('edit_office_id');
      if (u.role_id) roleSel.value = u.role_id;
      if (u.office_id) officeSel.value = u.office_id;
    }).finally(()=>{
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    });
  }
  function closeEdit(){
    const modal = document.getElementById('editModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    const pw = document.getElementById('edit_password'); if (pw) pw.value='';
  }
  document.getElementById('closeEditModal')?.addEventListener('click', closeEdit);
  document.getElementById('cancelEditBtn')?.addEventListener('click', closeEdit);
  document.getElementById('saveEditBtn')?.addEventListener('click', async ()=>{
    const payload = {
      user_id: parseInt(document.getElementById('edit_user_id').value,10),
      full_name: document.getElementById('edit_full_name').value.trim(),
      email: document.getElementById('edit_email').value.trim(),
      role_id: parseInt(document.getElementById('edit_role_id').value,10),
      office_id: parseInt(document.getElementById('edit_office_id').value,10),
      password: document.getElementById('edit_password').value
    };
    const res = await fetch('../api/update_user.php',{
      method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin', body: JSON.stringify(payload)
    });
    const d = await res.json().catch(()=>({success:false,error:'Invalid response'}));
    if(d.success){ showAlert(d.message||'User updated'); closeEdit(); loadUsers(); }
    else{ showAlert(d.error||'Failed to update user','error'); }
  });
  document.addEventListener('DOMContentLoaded', loadUsers);
  </script>
<?php if (!defined('INCLUDED_IN_DASHBOARD')): ?>
</body>
</html>
<?php endif; ?>

