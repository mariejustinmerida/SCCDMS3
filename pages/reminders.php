<?php
require_once '../includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Check if reminders table exists
$table_check = $conn->query("SHOW TABLES LIKE 'reminders'");
$reminders = [];

if ($table_check && $table_check->num_rows > 0) {
    // Get reminders for today
    $sql = "SELECT reminder_id, title, description, reminder_date, 
                   TIME(reminder_date) as reminder_time, is_completed
           FROM reminders 
           WHERE user_id = ? 
           AND DATE(reminder_date) = ? 
           ORDER BY reminder_date ASC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $reminders[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Plus+Jakarta+Sans%3Awght%40400%3B500%3B700%3B800" />
  <title>Reminders - SCC DMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      font-family: "Plus Jakarta Sans", "Noto Sans", sans-serif;
    }
    .sidebar {
      background: rgb(22, 59, 32);
    }
  </style>
</head>
<body class="bg-gray-50">
  <div id="page-content" class="p-6">
    <div class="mb-6 flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold">Today's Reminders</h1>
        <div class="flex items-center text-sm text-gray-500">
          <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
          <span class="mx-2">/</span>
          <span>Reminders</span>
        </div>
      </div>
      <div class="flex space-x-2">
        <button id="refreshBtn" class="bg-green-700 text-white px-4 py-2 rounded-lg hover:bg-green-800 flex items-center">
          <i class="fas fa-sync-alt mr-2"></i> Refresh
        </button>
        <a href="dashboard.php?page=dashboard_content" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
          <i class="fas fa-calendar mr-2"></i> View Calendar
        </a>
      </div>
    </div>

    <!-- Status Messages -->
    <div id="statusMessage" class="mb-6 hidden"></div>

    <!-- Reminders List -->
    <div class="bg-white rounded-lg shadow">
      <?php if (empty($reminders)): ?>
        <div class="p-12 text-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <h3 class="text-lg font-medium text-gray-900 mb-2">No reminders for today</h3>
          <p class="text-gray-500 mb-4">You don't have any reminders scheduled for today.</p>
          <a href="dashboard.php?page=dashboard_content" class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
            <i class="fas fa-plus mr-2"></i> Add Reminder
          </a>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php foreach ($reminders as $reminder): 
                // Parse time
                $timeDisplay = '';
                $isPast = false;
                $isDue = false;
                
                if ($reminder['reminder_time']) {
                  $parts = explode(':', $reminder['reminder_time']);
                  if (count($parts) >= 2) {
                    $hours = (int)$parts[0];
                    $minutes = (int)$parts[1];
                    $hour12 = ($hours % 12) ?: 12;
                    $ampm = $hours >= 12 ? 'PM' : 'AM';
                    $timeDisplay = sprintf('%d:%02d %s', $hour12, $minutes, $ampm);
                    
                    // Check if time has passed
                    $now = new DateTime();
                    $reminderTime = new DateTime($reminder['reminder_date']);
                    if ($reminderTime <= $now && $reminderTime->format('Y-m-d') === $now->format('Y-m-d')) {
                      $isPast = true;
                    }
                    // Check if due within 2 minutes
                    $diff = $now->diff($reminderTime);
                    $minutesDiff = $diff->days * 24 * 60 + $diff->h * 60 + $diff->i;
                    if ($minutesDiff >= -2 && $minutesDiff <= 2 && !$isPast) {
                      $isDue = true;
                    }
                  }
                } else if ($reminder['reminder_date']) {
                  $reminderDate = new DateTime($reminder['reminder_date']);
                  $timeDisplay = $reminderDate->format('g:i A');
                }
                
                $completedClass = $reminder['is_completed'] == 1 ? 'line-through text-gray-500' : '';
                $rowClass = $isDue ? 'bg-yellow-50' : ($isPast ? 'bg-gray-50' : '');
              ?>
                <tr class="reminder-row <?php echo $rowClass; ?>" data-reminder-id="<?php echo $reminder['reminder_id']; ?>">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="text-sm font-medium <?php echo $isDue ? 'text-red-600 font-bold' : ($isPast ? 'text-gray-500' : 'text-blue-600'); ?>">
                      <?php echo $timeDisplay ?: 'No time set'; ?>
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <div class="text-sm font-medium text-gray-900 <?php echo $completedClass; ?>">
                      <?php echo htmlspecialchars($reminder['title']); ?>
                      <?php if ($isDue): ?>
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                          <i class="fas fa-bell mr-1"></i> Due Now
                        </span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="text-sm text-gray-500 <?php echo $completedClass; ?>">
                      <?php echo htmlspecialchars($reminder['description'] ?: 'No description'); ?>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <?php if ($reminder['is_completed'] == 1): ?>
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <i class="fas fa-check-circle mr-1"></i> Completed
                      </span>
                    <?php elseif ($isPast): ?>
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                        <i class="fas fa-clock mr-1"></i> Past
                      </span>
                    <?php else: ?>
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        <i class="fas fa-calendar-check mr-1"></i> Upcoming
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex justify-end space-x-2">
                      <button onclick="toggleComplete(<?php echo $reminder['reminder_id']; ?>, <?php echo $reminder['is_completed'] ? 'false' : 'true'; ?>)" 
                              class="text-blue-600 hover:text-blue-900 <?php echo $reminder['is_completed'] == 1 ? 'hidden' : ''; ?> complete-btn">
                        <i class="fas fa-check mr-1"></i> Mark Complete
                      </button>
                      <button onclick="toggleComplete(<?php echo $reminder['reminder_id']; ?>, false)" 
                              class="text-yellow-600 hover:text-yellow-900 <?php echo $reminder['is_completed'] == 1 ? '' : 'hidden'; ?> incomplete-btn">
                        <i class="fas fa-undo mr-1"></i> Mark Incomplete
                      </button>
                      <button onclick="deleteReminder(<?php echo $reminder['reminder_id']; ?>)" 
                              class="text-red-600 hover:text-red-900">
                        <i class="fas fa-trash mr-1"></i> Delete
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    function showMessage(message, type = 'success') {
      const statusDiv = document.getElementById('statusMessage');
      statusDiv.className = `mb-6 p-4 rounded-lg ${type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`;
      statusDiv.textContent = message;
      statusDiv.classList.remove('hidden');
      setTimeout(() => {
        statusDiv.classList.add('hidden');
      }, 3000);
    }

    function toggleComplete(reminderId, isCompleted) {
      fetch('../api/reminders.php', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          reminder_id: reminderId,
          is_completed: isCompleted
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showMessage(isCompleted ? 'Reminder marked as complete' : 'Reminder marked as incomplete', 'success');
          // Reload page after short delay
          setTimeout(() => {
            location.reload();
          }, 500);
        } else {
          showMessage('Error: ' + (data.error || 'Failed to update reminder'), 'error');
        }
      })
      .catch(error => {
        showMessage('Network error: ' + error.message, 'error');
      });
    }

    function deleteReminder(reminderId) {
      if (!confirm('Are you sure you want to delete this reminder?')) {
        return;
      }

      fetch('../api/reminders.php', {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          reminder_id: reminderId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showMessage('Reminder deleted successfully', 'success');
          // Remove row from table
          const row = document.querySelector(`[data-reminder-id="${reminderId}"]`);
          if (row) {
            row.remove();
            // Reload if no more reminders
            setTimeout(() => {
              if (document.querySelectorAll('.reminder-row').length === 0) {
                location.reload();
              }
            }, 500);
          }
        } else {
          showMessage('Error: ' + (data.error || 'Failed to delete reminder'), 'error');
        }
      })
      .catch(error => {
        showMessage('Network error: ' + error.message, 'error');
      });
    }

    // Refresh button
    document.getElementById('refreshBtn').addEventListener('click', function() {
      location.reload();
    });
  </script>
</body>
</html>

