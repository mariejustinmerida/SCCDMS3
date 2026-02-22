<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php' && basename($_SERVER['PHP_SELF']) !== 'register.php' && basename($_SERVER['PHP_SELF']) !== 'verify.php') {
    header("Location: ../auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Plus+Jakarta+Sans%3Awght%40400%3B500%3B700%3B800" />
  <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>SCC DMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      font-family: "Plus Jakarta Sans", "Noto Sans", sans-serif;
      background-color: #f9fafb;
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
    .badge-hold {
      background-color: #E0E7FF;
      color: #4338CA;
    }
    .badge-revision {
      background-color: #F5D0FE;
      color: #9333EA;
    }
  </style>
</head>
<body>
<?php if (isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'dashboard.php'): ?>
  <!-- Simple header for non-dashboard pages -->
  <header class="bg-white shadow-sm py-4 px-6 mb-6">
    <div class="container mx-auto flex justify-between items-center">
      <div class="flex items-center gap-3">
        <img src="../assets/images/logo.png" alt="SCC Logo" class="w-10 h-10">
        <h2 class="text-gray-800 text-lg font-bold">SCC Document Management System</h2>
      </div>
      <?php if (isset($_SESSION['user_id'])): ?>
      <div class="flex items-center gap-4">
        <!-- Dynamic Clock -->
        <div id="dynamicClock" class="flex items-center gap-2 px-4 py-2 bg-gray-50 rounded-lg border border-gray-200">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="flex flex-col">
            <span id="clockTime" class="text-sm font-semibold text-gray-800">--:--:--</span>
            <span id="clockDate" class="text-xs text-gray-500">-- -- ----</span>
          </div>
        </div>
        <span class="text-gray-600"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'President'): ?>
        <a href="../auth/register.php" class="text-green-600 hover:text-green-800">Add Users</a>
        <?php endif; ?>
        <a href="../auth/logout.php" class="text-red-600 hover:text-red-800">Logout</a>
      </div>
      <?php endif; ?>
    </div>
  </header>
  <script>
    // Dynamic Clock Functionality
    function updateClock() {
      const now = new Date();
      
      // Format time (HH:MM:SS)
      const hours = String(now.getHours()).padStart(2, '0');
      const minutes = String(now.getMinutes()).padStart(2, '0');
      const seconds = String(now.getSeconds()).padStart(2, '0');
      const timeString = `${hours}:${minutes}:${seconds}`;
      
      // Format date (Day, Month DD, YYYY)
      const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      const dayName = days[now.getDay()];
      const monthName = months[now.getMonth()];
      const day = String(now.getDate()).padStart(2, '0');
      const year = now.getFullYear();
      const dateString = `${dayName}, ${monthName} ${day}, ${year}`;
      
      // Update clock elements
      const clockTime = document.getElementById('clockTime');
      const clockDate = document.getElementById('clockDate');
      
      if (clockTime) {
        clockTime.textContent = timeString;
      }
      if (clockDate) {
        clockDate.textContent = dateString;
      }
    }
    
    // Initialize clock and update every second
    document.addEventListener('DOMContentLoaded', function() {
      // Update clock immediately
      updateClock();
      
      // Update clock every second
      setInterval(updateClock, 1000);
    });
  </script>
<?php endif; ?>
