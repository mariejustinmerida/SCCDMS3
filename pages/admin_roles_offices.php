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
  <title>Manage Roles & Offices</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
  <div class="max-w-6xl mx-auto p-6">
<?php else: ?>
  <div class="bg-white rounded-lg p-4">
<?php endif; ?>
    <h1 class="text-2xl font-bold mb-4">Manage Roles & Offices (Super Admin)</h1>
    <div id="alert" class="hidden mb-4"></div>
    
    <!-- Tabs -->
    <div class="mb-4 border-b border-gray-200">
      <nav class="-mb-px flex space-x-8">
        <button id="rolesTab" class="tab-button border-b-2 border-blue-500 py-4 px-1 text-sm font-medium text-blue-600">
          Roles
        </button>
        <button id="officesTab" class="tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
          Offices
        </button>
      </nav>
    </div>

    <!-- Roles Section -->
    <div id="rolesSection" class="tab-content">
      <div class="mb-4 flex justify-between items-center">
        <h2 class="text-xl font-semibold">Roles</h2>
        <button id="addRoleBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
          <i class="fas fa-plus mr-2"></i>Add Role
        </button>
      </div>
      <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="bg-gray-100 text-left">
              <th class="p-3">ID</th>
              <th class="p-3">Role Name</th>
              <th class="p-3 text-center">Actions</th>
            </tr>
          </thead>
          <tbody id="rolesTableBody"></tbody>
        </table>
      </div>
    </div>

    <!-- Offices Section -->
    <div id="officesSection" class="tab-content hidden">
      <div class="mb-4 flex justify-between items-center">
        <h2 class="text-xl font-semibold">Offices</h2>
        <button id="addOfficeBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
          <i class="fas fa-plus mr-2"></i>Add Office
        </button>
      </div>
      <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="bg-gray-100 text-left">
              <th class="p-3">ID</th>
              <th class="p-3">Office Name</th>
              <th class="p-3 text-center">Actions</th>
            </tr>
          </thead>
          <tbody id="officesTableBody"></tbody>
        </table>
      </div>
    </div>

    <!-- Add/Edit Role Modal -->
    <div id="roleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
        <div class="p-4 border-b flex justify-between items-center">
          <h3 id="roleModalTitle" class="text-xl font-semibold">Add Role</h3>
          <button id="closeRoleModal" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        <div class="p-4 space-y-4">
          <input type="hidden" id="edit_role_id">
          <div>
            <label class="block text-sm text-gray-700 mb-1">Role Name</label>
            <input id="role_name_input" type="text" class="w-full border rounded px-3 py-2" placeholder="Enter role name" />
          </div>
        </div>
        <div class="p-4 border-t flex justify-end space-x-2">
          <button id="cancelRoleBtn" class="px-4 py-2 rounded bg-gray-500 text-white hover:bg-gray-600">Cancel</button>
          <button id="saveRoleBtn" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700">Save</button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Office Modal -->
    <div id="officeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
        <div class="p-4 border-b flex justify-between items-center">
          <h3 id="officeModalTitle" class="text-xl font-semibold">Add Office</h3>
          <button id="closeOfficeModal" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        <div class="p-4 space-y-4">
          <input type="hidden" id="edit_office_id">
          <div>
            <label class="block text-sm text-gray-700 mb-1">Office Name</label>
            <input id="office_name_input" type="text" class="w-full border rounded px-3 py-2" placeholder="Enter office name" />
          </div>
        </div>
        <div class="p-4 border-t flex justify-end space-x-2">
          <button id="cancelOfficeBtn" class="px-4 py-2 rounded bg-gray-500 text-white hover:bg-gray-600">Cancel</button>
          <button id="saveOfficeBtn" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700">Save</button>
        </div>
      </div>
    </div>
  </div>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script>
    function showAlert(msg, type='success'){
      const a = document.getElementById('alert');
      a.className = `mb-4 p-3 rounded ${type==='success'?'bg-green-50 text-green-700 border border-green-200':'bg-red-50 text-red-700 border border-red-200'}`;
      a.textContent = msg; 
      a.classList.remove('hidden');
      setTimeout(()=>a.classList.add('hidden'), 3000);
    }

    // Tab switching
    document.getElementById('rolesTab').addEventListener('click', function() {
      document.getElementById('rolesSection').classList.remove('hidden');
      document.getElementById('officesSection').classList.add('hidden');
      this.classList.add('border-blue-500', 'text-blue-600');
      this.classList.remove('border-transparent', 'text-gray-500');
      document.getElementById('officesTab').classList.remove('border-blue-500', 'text-blue-600');
      document.getElementById('officesTab').classList.add('border-transparent', 'text-gray-500');
    });

    document.getElementById('officesTab').addEventListener('click', function() {
      document.getElementById('officesSection').classList.remove('hidden');
      document.getElementById('rolesSection').classList.add('hidden');
      this.classList.add('border-blue-500', 'text-blue-600');
      this.classList.remove('border-transparent', 'text-gray-500');
      document.getElementById('rolesTab').classList.remove('border-blue-500', 'text-blue-600');
      document.getElementById('rolesTab').classList.add('border-transparent', 'text-gray-500');
    });

    // Load Roles
    function loadRoles(){
      fetch('../api/manage_roles.php', {credentials:'same-origin'})
        .then(r => r.json())
        .then(data => {
          if(data.success){
            const tbody = document.getElementById('rolesTableBody');
            tbody.innerHTML = '';
            if(data.roles.length === 0){
              tbody.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-gray-500">No roles found</td></tr>';
              return;
            }
            data.roles.forEach(role => {
              const tr = document.createElement('tr');
              tr.className = 'border-b';
              tr.innerHTML = `
                <td class="p-3">${role.role_id}</td>
                <td class="p-3 font-medium">${role.role_name}</td>
                <td class="p-3 text-center space-x-2">
                  <button class="px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700" onclick="editRole(${role.role_id}, '${role.role_name.replace(/'/g,"&#39;")}')">Edit</button>
                  <button class="px-3 py-1 rounded bg-red-600 text-white hover:bg-red-700" onclick="deleteRole(${role.role_id}, '${role.role_name.replace(/'/g,"&#39;")}')">Delete</button>
                </td>
              `;
              tbody.appendChild(tr);
            });
          } else {
            showAlert(data.error || 'Failed to load roles', 'error');
          }
        })
        .catch(e => showAlert('Error loading roles: ' + e.message, 'error'));
    }

    // Load Offices
    function loadOffices(){
      fetch('../api/manage_offices.php', {credentials:'same-origin'})
        .then(r => r.json())
        .then(data => {
          if(data.success){
            const tbody = document.getElementById('officesTableBody');
            tbody.innerHTML = '';
            if(data.offices.length === 0){
              tbody.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-gray-500">No offices found</td></tr>';
              return;
            }
            data.offices.forEach(office => {
              const tr = document.createElement('tr');
              tr.className = 'border-b';
              tr.innerHTML = `
                <td class="p-3">${office.office_id}</td>
                <td class="p-3 font-medium">${office.office_name}</td>
                <td class="p-3 text-center space-x-2">
                  <button class="px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700" onclick="editOffice(${office.office_id}, '${office.office_name.replace(/'/g,"&#39;")}')">Edit</button>
                  <button class="px-3 py-1 rounded bg-red-600 text-white hover:bg-red-700" onclick="deleteOffice(${office.office_id}, '${office.office_name.replace(/'/g,"&#39;")}')">Delete</button>
                </td>
              `;
              tbody.appendChild(tr);
            });
          } else {
            showAlert(data.error || 'Failed to load offices', 'error');
          }
        })
        .catch(e => showAlert('Error loading offices: ' + e.message, 'error'));
    }

    // Role functions
    function editRole(id, name){
      document.getElementById('edit_role_id').value = id;
      document.getElementById('role_name_input').value = name;
      document.getElementById('roleModalTitle').textContent = 'Edit Role';
      document.getElementById('roleModal').classList.remove('hidden');
      document.getElementById('roleModal').classList.add('flex');
    }

    function deleteRole(id, name){
      if(!confirm(`Are you sure you want to delete the role "${name}"?`)) return;
      fetch('../api/manage_roles.php', {
        method: 'DELETE',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({role_id: id})
      })
      .then(r => r.json())
      .then(data => {
        if(data.success){
          showAlert('Role deleted successfully');
          loadRoles();
        } else {
          showAlert(data.error || 'Failed to delete role', 'error');
        }
      })
      .catch(e => showAlert('Error: ' + e.message, 'error'));
    }

    // Office functions
    function editOffice(id, name){
      document.getElementById('edit_office_id').value = id;
      document.getElementById('office_name_input').value = name;
      document.getElementById('officeModalTitle').textContent = 'Edit Office';
      document.getElementById('officeModal').classList.remove('hidden');
      document.getElementById('officeModal').classList.add('flex');
    }

    function deleteOffice(id, name){
      if(!confirm(`Are you sure you want to delete the office "${name}"?`)) return;
      fetch('../api/manage_offices.php', {
        method: 'DELETE',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({office_id: id})
      })
      .then(r => r.json())
      .then(data => {
        if(data.success){
          showAlert('Office deleted successfully');
          loadOffices();
        } else {
          showAlert(data.error || 'Failed to delete office', 'error');
        }
      })
      .catch(e => showAlert('Error: ' + e.message, 'error'));
    }

    // Role modal handlers
    document.getElementById('addRoleBtn').addEventListener('click', function(){
      document.getElementById('edit_role_id').value = '';
      document.getElementById('role_name_input').value = '';
      document.getElementById('roleModalTitle').textContent = 'Add Role';
      document.getElementById('roleModal').classList.remove('hidden');
      document.getElementById('roleModal').classList.add('flex');
    });

    function closeRoleModal(){
      document.getElementById('roleModal').classList.add('hidden');
      document.getElementById('roleModal').classList.remove('flex');
    }

    document.getElementById('closeRoleModal').addEventListener('click', closeRoleModal);
    document.getElementById('cancelRoleBtn').addEventListener('click', closeRoleModal);
    
    // Close modal when clicking outside
    document.getElementById('roleModal').addEventListener('click', function(e){
      if(e.target === this){
        closeRoleModal();
      }
    });

    document.getElementById('saveRoleBtn').addEventListener('click', function(){
      const roleId = document.getElementById('edit_role_id').value;
      const roleName = document.getElementById('role_name_input').value.trim();
      
      if(!roleName){
        showAlert('Role name is required', 'error');
        return;
      }

      const method = roleId ? 'PUT' : 'POST';
      const body = roleId ? {role_id: parseInt(roleId), role_name: roleName} : {role_name: roleName};

      fetch('../api/manage_roles.php', {
        method: method,
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify(body)
      })
      .then(r => r.json())
      .then(data => {
        if(data.success){
          showAlert(roleId ? 'Role updated successfully' : 'Role created successfully');
          closeRoleModal();
          loadRoles();
        } else {
          showAlert(data.error || 'Failed to save role', 'error');
        }
      })
      .catch(e => showAlert('Error: ' + e.message, 'error'));
    });

    // Office modal handlers
    document.getElementById('addOfficeBtn').addEventListener('click', function(){
      document.getElementById('edit_office_id').value = '';
      document.getElementById('office_name_input').value = '';
      document.getElementById('officeModalTitle').textContent = 'Add Office';
      document.getElementById('officeModal').classList.remove('hidden');
      document.getElementById('officeModal').classList.add('flex');
    });

    function closeOfficeModal(){
      document.getElementById('officeModal').classList.add('hidden');
      document.getElementById('officeModal').classList.remove('flex');
    }

    document.getElementById('closeOfficeModal').addEventListener('click', closeOfficeModal);
    document.getElementById('cancelOfficeBtn').addEventListener('click', closeOfficeModal);
    
    // Close modal when clicking outside
    document.getElementById('officeModal').addEventListener('click', function(e){
      if(e.target === this){
        closeOfficeModal();
      }
    });

    document.getElementById('saveOfficeBtn').addEventListener('click', function(){
      const officeId = document.getElementById('edit_office_id').value;
      const officeName = document.getElementById('office_name_input').value.trim();
      
      if(!officeName){
        showAlert('Office name is required', 'error');
        return;
      }

      const method = officeId ? 'PUT' : 'POST';
      const body = officeId ? {office_id: parseInt(officeId), office_name: officeName} : {office_name: officeName};

      fetch('../api/manage_offices.php', {
        method: method,
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify(body)
      })
      .then(r => r.json())
      .then(data => {
        if(data.success){
          showAlert(officeId ? 'Office updated successfully' : 'Office created successfully');
          closeOfficeModal();
          loadOffices();
        } else {
          showAlert(data.error || 'Failed to save office', 'error');
        }
      })
      .catch(e => showAlert('Error: ' + e.message, 'error'));
    });

    // Load on page load
    loadRoles();
    loadOffices();
  </script>
</body>
</html>

