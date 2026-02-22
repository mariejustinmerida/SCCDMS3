<?php
// Add error suppression to prevent warnings from showing on the page
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering at the very beginning of the file
ob_start();

$valid_pages = ['dashboard_content', 'compose', 'documents', 'incoming', 'outgoing', 'received', 'hold', 'approved', 'track', 'user_logs', 'generate_test_logouts', 'view', 'edit', 'view_document', 'approve_document', 'resume', 'request_revision', 'revise_document', 'documents_needing_revision', 'document_qr_approval', 'simple_approval', 'admin_verify', 'document_with_qr_wrapper', 'rejected', 'ai_settings', 'test_ai_features', 'settings', 'admin_users', 'admin_documents', 'admin_roles_offices', 'drafts', 'reminders'];
$page = isset($_GET['page']) && in_array($_GET['page'], $valid_pages) ? $_GET['page'] : 'dashboard_content';
$page_file = $page . '.php';
session_start(); // Start session to access session variables
require_once '../includes/config.php';
require_once '../includes/activity_logger.php'; // Include activity logger

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Get requested page or default to dashboard
$folder = isset($_GET['folder']) ? $_GET['folder'] : 'all';

// Function to get human-readable time difference
function human_time_diff($timestamp) {
    $difference = time() - $timestamp;

    if ($difference < 60) {
        return "just now";
    } elseif ($difference < 3600) {
        $minutes = floor($difference / 60);
        return $minutes . " " . ($minutes == 1 ? "minute" : "minutes") . " ago";
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . " " . ($hours == 1 ? "hour" : "hours") . " ago";
    } elseif ($difference < 604800) {
        $days = floor($difference / 86400);
        return $days . " " . ($days == 1 ? "day" : "days") . " ago";
    } else {
        return date("M j", $timestamp);
    }
}
?>
<!DOCTYPE html>
<html>

<head>
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
  <link rel="stylesheet" as="style" onload="this.rel='stylesheet'"
    href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Plus+Jakarta+Sans%3Awght%40400%3B500%3B700%3B800" />
  <title>Dashboard - SCC DMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" as="style" onload="this.rel='stylesheet'"
    href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Plus+Jakarta+Sans%3Awght%40400%3B500%3B700%3B800" />
  <style>
    body {
      font-family: "Plus Jakarta Sans", "Noto Sans", sans-serif;
    }

    .sidebar {
      background: rgb(22, 59, 32);
    }

    body.dark {
      background-color: #1E1E1E;
      color: #ffffff;
    }

    body.dark .bg-white {
      background-color: #1a1a1a;
    }

    body.dark .text-[#1C160C] {
      color: #ffffff;
    }

    body.dark .border-[#F4EFE6] {
      border-color: #333333;
    }

    body.dark .hover\:bg-[#E9DFCE]:hover {
      background-color: #333333;
    }

    .stats-card {
      background: rgba(255, 255, 255, 0.1);
    }

    .calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 8px;
    }

    .calendar-cell {
      aspect-ratio: 1;
      background: white;
      border: 1px solid #e5e7eb;
      padding: 8px;
      border-radius: 4px;
      color: #1C160C;
    }

    body.dark .calendar-cell {
      background: #2d2d2d;
      border-color: #404040;
      color: #ffffff;
    }

    .calendar-cell.today {
      background: rgb(22, 59, 32);
      color: white;
    }

    body.dark .calendar-cell.today {
      background: rgb(22, 59, 32);
      color: white;
    }

    .calendar-cell:hover {
      background: #f3f4f6;
    }

    body.dark .calendar-cell:hover {
      background: #404040;
    }

    .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    background: rgb(22, 59, 32);
    width: 230px;
    height: 100vh;
    z-index: 10000;
    overflow-x: hidden;
    overflow-y: auto;
    scrollbar-width: none;
    transition: all 0.3s ease;
    padding: 1rem;
  }
  
  .sidebar.close {
    width: 80px;
    padding: 1rem 0.5rem;
  }
  
  .sidebar.close ~ nav {
    left: 80px;
  }

  .sidebar::-webkit-scrollbar {
    display: none;
  }
  
  /* Collapsed sidebar - menu items styling */
  .sidebar.close a {
    justify-content: center !important;
    padding: 0.75rem 0 !important;
    width: 100% !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
  }
  
  .sidebar.close a svg {
    margin: 0 !important;
    width: 24px !important;
    height: 24px !important;
  }
  
  .sidebar.close a span {
    display: none !important;
  }
  
  /* Hide badges in collapsed mode */
  .sidebar.close .ml-auto {
    display: none !important;
  }
  
  /* Hide submenu container in collapsed mode */
  .sidebar.close .ml-8 {
    display: none !important;
  }
  
  /* Ensure proper alignment for collapsed sidebar items */
  .sidebar.close .flex.flex-col {
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .sidebar .side-menu {
    margin-top: 48px;
  }

  .sidebar .side-menu li {
    height: 48px;
    background: transparent;
    margin-left: 6px;
    border-radius: 48px 0 0 48px;
    padding: 4px;
  }

  .sidebar .side-menu li a {
    display: flex;
    align-items: center;
    border-radius: 48px;
    font-size: 16px;
    color: white;
    white-space: nowrap;
    overflow-x: hidden;
    transition: all 0.3s ease;
    padding: 0 12px;
  }

  .sidebar.close .side-menu li a {
    width: 44px;
  }

  .content {
    position: relative;
    width: calc(100% - 230px);
    left: 230px;
    transition: all 0.3s ease;
  }

  .sidebar.close ~ .content {
    width: calc(100% - 80px);
    left: 80px;
  }

  /* Logo sizing and behavior */
  .sidebar .logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.3s ease;
    padding: 0.5rem 0;
  }
  .sidebar .logo img {
    width: 40px;
    height: 40px;
    object-fit: contain;
    flex-shrink: 0;
  }
  .sidebar .logo h2 {
    transition: all 0.3s ease;
    white-space: nowrap;
  }
  .sidebar.close .logo {
    justify-content: center !important;
    margin: 0 auto;
    width: 100%;
    padding: 0.5rem 0;
  }
  .sidebar.close .logo h2 {
    display: none !important;
  }
  .sidebar.close .logo img {
    width: 45px;
    height: 45px;
    margin: 0 auto;
  }
  
  /* Collapsed sidebar - logo container */
  .sidebar.close .flex.items-center.justify-between {
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
  }
  
  .sidebar.close .flex.items-center.justify-between.mb-6 {
    margin-bottom: 1.5rem;
  }

  /* Sidebar toggle button styling */
  #sidebarToggle {
    min-width: 36px;
    min-height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    transition: all 0.3s ease;
  }
  
  .sidebar.close #sidebarToggle {
    margin: 0.5rem auto 0;
    width: 100%;
  }
  
  .sidebar.close #sidebarToggle svg {
    transform: rotate(180deg);
  }
  
  #sidebarToggle:hover {
    background-color: rgba(255, 255, 255, 0.15);
  }
  
  /* Collapsed sidebar - ensure proper spacing */
  .sidebar.close .space-y-4 > * {
    margin-bottom: 0.5rem;
  }
  
  /* Collapsed sidebar - hide nested menu indentation */
  .sidebar.close .ml-8 {
    display: none;
  }
  
  /* Expanded sidebar - show nested menus */
  .sidebar:not(.close) .ml-8 {
    display: flex;
  }

  @media screen and (max-width: 768px) {
    .sidebar {
      width: 200px;
    }

    .content {
      width: calc(100% - 200px);
      left: 200px;
    }
  }

  @media screen and (max-width: 576px) {
    .sidebar {
      width: 100%;
      height: auto;
      position: relative;
    }

    .content {
      width: 100%;
      left: 0;
    }
  }

  /* Notification styles */
  .notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
    background-color: #ef4444;
    z-index: 50;
    min-width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  /* Fixed notification panel */
  #notificationsContainer {
    z-index: 2000 !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    background-color: white;
  }
  
  /* Notification popup styles */
  .notification-popup {
    background-color: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    max-width: 24rem;
    width: 100%;
  }
  
  .notification-header {
    background-color: #f9fafb;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    font-weight: 600;
    border-top-left-radius: 0.5rem;
    border-top-right-radius: 0.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .notification-item {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    background-color: white;
  }
  
  .notification-item:hover {
    background-color: #f9fafb;
  }
  
  .notification-item.unread {
    border-left: 3px solid #3b82f6;
  }
  
  .ignore-notification-btn {
    padding: 4px 8px;
    border-radius: 4px;
    border: 1px solid #e5e7eb;
    background-color: #f9fafb;
    transition: all 0.2s ease;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
  }
  
  .ignore-notification-btn:hover {
    background-color: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
  }
  
  .ignore-notification-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  </style>
</head>

<body class="min-h-screen bg-[#F4F4F4]">
  <?php
  // Get notification counts
  $user_id = $_SESSION['user_id'];
  $office_id = $_SESSION['office_id'] ?? 0;

  // Get incoming count - match incoming.php query exactly
  $incoming_query = "SELECT COUNT(*) as count 
                    FROM document_workflow dw 
                    JOIN documents d ON dw.document_id = d.document_id 
                    LEFT JOIN (
                        SELECT document_id, MAX(created_at) as latest_hold
                        FROM document_logs
                        WHERE action = 'hold'
                        GROUP BY document_id
                    ) hold_logs ON d.document_id = hold_logs.document_id
                    LEFT JOIN (
                        SELECT document_id, MAX(created_at) as latest_resume
                        FROM document_logs
                        WHERE action = 'resume'
                        GROUP BY document_id
                    ) resume_logs ON d.document_id = resume_logs.document_id
                    WHERE dw.office_id = ?
                    AND UPPER(dw.status) = 'CURRENT'
                    AND (hold_logs.document_id IS NULL OR (resume_logs.document_id IS NOT NULL AND resume_logs.latest_resume > hold_logs.latest_hold))";
  $incoming_stmt = $conn->prepare($incoming_query);
  if ($incoming_stmt === false) {
    $incoming_count = 0;
  } else {
    $incoming_stmt->bind_param("i", $office_id);
    $incoming_stmt->execute();
    $incoming_result = $incoming_stmt->get_result();
    $incoming_count = 0;
    if($incoming_row = $incoming_result->fetch_assoc()) {
      $incoming_count = $incoming_row['count'];
    }
  }

  // Get outgoing count - match outgoing.php query exactly
  $outgoing_query = "SELECT COUNT(DISTINCT d.document_id) as count 
                    FROM documents d
                    JOIN document_types dt ON d.type_id = dt.type_id 
                    JOIN users u ON d.creator_id = u.user_id
                    WHERE u.office_id = ? 
                    AND d.status != 'approved' 
                    AND d.status != 'rejected'";
  $outgoing_stmt = $conn->prepare($outgoing_query);
  if ($outgoing_stmt === false) {
    $outgoing_count = 0;
  } else {
    $outgoing_stmt->bind_param("i", $office_id);
    $outgoing_stmt->execute();
    $outgoing_result = $outgoing_stmt->get_result();
    $outgoing_count = 0;
    if($outgoing_row = $outgoing_result->fetch_assoc()) {
      $outgoing_count = $outgoing_row['count'];
    }
  }

  // Get hold count - match hold.php query exactly
  $hold_query = "SELECT COUNT(DISTINCT d.document_id) as count 
                FROM documents d
                JOIN document_types dt ON d.type_id = dt.type_id
                JOIN document_workflow dw ON d.document_id = dw.document_id
                JOIN offices o ON dw.office_id = o.office_id
                WHERE dw.office_id = ? 
                AND UPPER(dw.status) IN ('ON_HOLD','HOLD')";
  $hold_stmt = $conn->prepare($hold_query);
  if ($hold_stmt === false) {
    $hold_count = 0;
  } else {
    $hold_stmt->bind_param("i", $office_id);
    $hold_stmt->execute();
    $hold_result = $hold_stmt->get_result();
    $hold_count = 0;
    if($hold_row = $hold_result->fetch_assoc()) {
      $hold_count = $hold_row['count'];
    }
  }

    // Get revision count - match documents_needing_revision.php query exactly
    $revision_query = "SELECT COUNT(DISTINCT d.document_id) as count 
                      FROM documents d
                      WHERE d.status = 'revision' 
                      AND d.creator_id = ?";
    $revision_stmt = $conn->prepare($revision_query);
    if ($revision_stmt === false) {
      $revision_count = 0;
    } else {
      $revision_stmt->bind_param("i", $user_id);
      $revision_stmt->execute();
      $revision_result = $revision_stmt->get_result();
      $revision_count = 0;
      if($revision_row = $revision_result->fetch_assoc()) {
        $revision_count = $revision_row['count'];
      }
    }
    
    // Get rejected count - match rejected.php query exactly
    $rejected_query = "SELECT COUNT(DISTINCT d.document_id) as count 
                      FROM documents d
                      WHERE d.status = 'rejected' 
                      AND d.creator_id = ?";
    $rejected_stmt = $conn->prepare($rejected_query);
    if ($rejected_stmt === false) {
      $rejected_count = 0;
    } else {
      $rejected_stmt->bind_param("i", $user_id);
      $rejected_stmt->execute();
      $rejected_result = $rejected_stmt->get_result();
      $rejected_count = 0;
      if($rejected_row = $rejected_result->fetch_assoc()) {
        $rejected_count = $rejected_row['count'];
      }
    }

  // Get approved count - match approved.php query exactly (no office filter)
  $approved_query = "SELECT COUNT(d.document_id) as count 
                     FROM documents d
                     WHERE d.status = 'approved'";
  $approved_stmt = $conn->prepare($approved_query);
  if ($approved_stmt === false) {
    $approved_count = 0;
  } else {
    $approved_stmt->execute();
    $approved_result = $approved_stmt->get_result();
    $approved_count = 0;
    if($approved_row = $approved_result->fetch_assoc()) {
      $approved_count = $approved_row['count'];
    }
  }

  // Get draft count for current user
  $draft_count = 0;
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
  $dc_stmt = $conn->prepare("SELECT COUNT(*) as c FROM document_drafts WHERE user_id = ?");
  if ($dc_stmt) {
    $dc_stmt->bind_param('i', $user_id);
    $dc_stmt->execute();
    $dc_res = $dc_stmt->get_result();
    if ($dc_res && $row = $dc_res->fetch_assoc()) { $draft_count = (int)$row['c']; }
  }

  // Get notifications (document status changes) - using the new structure
  $notif_query = "SELECT n.notification_id, n.message, n.is_read, n.created_at, d.document_id, d.title, d.status,
                  (SELECT o.office_name FROM offices o 
                   JOIN document_workflow dw ON o.office_id = dw.office_id
                   WHERE dw.document_id = d.document_id AND dw.status = 'current'
                   LIMIT 1) as office_name
                FROM notifications n
                JOIN documents d ON n.document_id = d.document_id
                WHERE n.user_id = ? AND n.is_read = 0
                ORDER BY n.created_at DESC
                LIMIT 5";
  $notif_stmt = $conn->prepare($notif_query);
  if ($notif_stmt === false) {
    $notifications = [];
    $notification_count = 0;
  } else {
    $notif_stmt->bind_param("i", $user_id);
    $notif_stmt->execute();
    $notif_result = $notif_stmt->get_result();
    $notifications = [];
    while($notif = $notif_result->fetch_assoc()) {
      $notifications[] = $notif;
    }
    $notification_count = count($notifications);
  }
  ?>

  <!-- Top Navigation -->
  <nav class="fixed top-0 left-[230px] right-0 z-[9998] bg-white border-b border-gray-200 px-6 py-3 shadow-sm transition-all duration-300 ease-in-out">
    <div class="flex justify-end items-center gap-4">
      <?php if(isset($_SESSION['welcome_message'])): ?>
        <div id="welcome-message" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-[10002]">
          Welcome, <?php echo $_SESSION['username']; ?>!
        </div>
        <script>
          setTimeout(() => {
            document.getElementById('welcome-message').style.display = 'none';
            <?php unset($_SESSION['welcome_message']); ?>
          }, 5000);
        </script>
      <?php endif; ?>
      
      <?php if(isset($_SESSION['success'])): ?>
        <div id="success-message" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-[10002]">
          <?php echo $_SESSION['success']; ?>
        </div>
        <script>
          setTimeout(() => {
            document.getElementById('success-message').style.display = 'none';
            <?php unset($_SESSION['success']); ?>
          }, 5000);
        </script>
      <?php endif; ?>

      <!-- Notification Bell -->
      <div class="relative">
        <button class="notification-bell relative p-2 hover:bg-gray-100 rounded-full">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
          <span class="notification-badge">0</span>
        </button>
        
        <!-- Notifications Container -->
        <div id="notificationsContainer" class="notification-popup hidden fixed top-16 right-4 bg-white border border-gray-200 rounded-lg shadow-xl z-[10001] w-80">
          <div class="border-b border-gray-200 px-4 py-3 flex justify-between items-center bg-gray-50 rounded-t-lg">
            <h3 class="font-medium text-gray-700">Notifications</h3>
            <button id="closeNotifications" class="text-gray-400 hover:text-gray-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="max-h-96 overflow-y-auto">
            <div id="notificationsList" class="divide-y divide-gray-100">
              <!-- Notifications will be loaded here -->
            </div>
            <div class="p-4 text-center text-sm text-gray-500" id="noNotifications">
              No new notifications
            </div>
          </div>
          <div class="border-t border-gray-200 p-3 text-center bg-gray-50 rounded-b-lg">
            <button id="markAllRead" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Mark all as read</button>
            <a href="../generate_notifications.php" class="text-sm text-purple-600 hover:text-purple-800 font-medium ml-4">Generate Test Notifications</a>
          </div>
        </div>
      </div>

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

      <!-- User Profile Dropdown -->
      <div class="relative">
        <button id="profileBtn" class="flex items-center focus:outline-none">
          <?php if(isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])): ?>
            <img src="<?php echo '../' . $_SESSION['profile_image']; ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover cursor-pointer hover:opacity-80">
          <?php else: ?>
            <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
              </svg>
            </div>
          <?php endif; ?>
        </button>

        <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-[10001]">
          <div class="p-3 border-b">
            <h3 class="text-sm font-semibold"><?php echo $_SESSION['username']; ?></h3>
            <?php if(isset($_SESSION['email'])): ?>
            <p class="text-xs text-gray-500"><?php echo $_SESSION['email']; ?></p>
            <?php endif; ?>
          </div>
          <div class="py-1">
            <a href="../actions/update_profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
              <i class="fas fa-user-edit mr-2"></i> Update Profile
            </a>
          </div>
          <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['President','Super Admin'])): ?>
          <div class="py-1">
            <a href="../auth/register.php" class="block px-4 py-2 text-sm text-green-700 hover:bg-gray-100">
              <i class="fas fa-user-plus mr-2"></i> Add Users
            </a>
          </div>
          <?php endif; ?>
          <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin'): ?>
          <div class="py-1">
            <a href="?page=admin_users" class="block px-4 py-2 text-sm text-blue-700 hover:bg-gray-100">
              <i class="fas fa-users-cog mr-2"></i> Manage Users
            </a>
          </div>
          <div class="py-1">
            <a href="?page=admin_documents" class="block px-4 py-2 text-sm text-blue-700 hover:bg-gray-100">
              <i class="fas fa-folder-open mr-2"></i> All Documents
            </a>
          </div>
          <?php endif; ?>
          <div class="py-1">
            <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
              <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </a>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <div class="flex pt-[60px]">
    <!-- Sidebar -->
    <aside class="sidebar text-white">
      <div class="flex flex-col h-full">
        <div class="space-y-4">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3 logo">
              <img src="../assets/images/logo.png" alt="SCC Logo" class="logo-img w-10 h-10 object-contain">
              <h2 class="text-white text-lg font-bold">SCC DMS</h2>
            </div>
            <button id="sidebarToggle" class="text-white hover:bg-white/10 p-1.5 rounded-lg transition-all duration-300 flex items-center justify-center" title="Collapse sidebar">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
              </svg>
            </button>
          </div>
          <a href="?page=compose" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            <span>Compose</span>
          </a>
          <a href="?page=dashboard_content" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span>Dashboard</span>
          </a>
          <div class="flex flex-col">
            <a href="?page=documents" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span>Documents</span>
            </a>
            <div class="ml-8 flex flex-col">
              <a href="?page=incoming" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <span>Incoming</span>
                <span class="ml-auto bg-yellow-500 text-xs px-2 py-1 rounded-full"><?php echo $incoming_count; ?></span>
              </a>
              <a href="?page=outgoing" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                <span>Outgoing</span>
                <span class="ml-auto bg-blue-500 text-xs px-2 py-1 rounded-full"><?php echo $outgoing_count; ?></span>
              </a>
              <a href="?page=approved" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Approved</span>
                <span class="ml-auto bg-green-500 text-xs px-2 py-1 rounded-full"><?php echo $approved_count; ?></span>
              </a>
              <a href="?page=hold" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Hold</span>
                <span class="ml-auto bg-orange-500 text-xs px-2 py-1 rounded-full"><?php echo $hold_count; ?></span>
              </a>
              <a href="?page=documents_needing_revision" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                <span>Need Revision</span>
                <span class="ml-auto bg-purple-500 text-xs px-2 py-1 rounded-full"><?php echo $revision_count; ?></span>
              </a>
              <a href="?page=drafts" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v8m-4-4h8M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
                <span>Drafts</span>
                <span class="ml-auto bg-gray-500 text-xs px-2 py-1 rounded-full"><?php echo $draft_count; ?></span>
              </a>
              <a href="?page=rejected" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span>Rejected</span>
                <span class="ml-auto bg-red-500 text-xs px-2 py-1 rounded-full"><?php echo $rejected_count; ?></span>
              </a>
              </div>
          </div>
          <!-- <a href="?page=approved" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Approved</span>
          </a> -->
          <a href="?page=track" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
            </svg>
            <span>Track</span>
          </a>

          <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin'): ?>
          <div class="mt-2">
            <a href="?page=admin_users" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M9 20h6M3 20h5v-2a3 3 0 00-5.356-1.857M16 11V7a4 4 0 10-8 0v4M5 11h14a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2z" />
              </svg>
              <span>Manage Users</span>
            </a>
            <a href="?page=admin_documents" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h6a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM13 7h6a2 2 0 012 2v8a2 2 0 01-2 2h-6" />
              </svg>
              <span>All Documents</span>
            </a>
            <a href="?page=admin_roles_offices" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
              </svg>
              <span>Roles & Offices</span>
            </a>
          </div>
          <?php endif; ?>
          
          <!-- AI Settings link visible to all users -->
          <a href="?page=ai_settings" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>
            <span>AI Settings</span>
          </a>
          
          <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['President','Super Admin'])): ?>
          <a href="?page=user_logs" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>User Logs</span>
          </a>
          <?php endif; ?>
          
          <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
          <!-- This is the original AI settings link that was only visible to admins -->
          <!-- We're keeping it commented out since we now have a link visible to all users above -->
          <!-- <a href="?page=ai_settings" class="flex items-center gap-3 px-30 py-2 rounded-lg hover:bg-white/10">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>
            <span>AI Settings</span>
          </a> -->
          <?php endif; ?>
        </div>


        <div class="mt-auto space-y-2">
          <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['President','Super Admin'])): ?>
          <!-- Admin Document Verification Link - Only for President -->
          <a href="admin_verify.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <span>Document Verification</span>
          </a>
          
          <a href="?page=settings" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>Settings</span>
          </a>
          <?php endif; ?>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-6" style="margin-left: 230px; transition: margin-left 0.3s ease;">
      <?php 
      // Define a constant to indicate files are being included in dashboard
      define('INCLUDED_IN_DASHBOARD', true);
      
      // Include the requested page
      if (file_exists($page_file)) {
        // Debug output
        if ($page === 'ai_settings') {
          echo "<div class='bg-yellow-100 p-2 mb-4'>Debug: Loading AI Settings page. File path: $page_file, Exists: " . (file_exists($page_file) ? 'Yes' : 'No') . "</div>";
        }
        include($page_file);
      } else {
        echo "<div class='bg-white rounded-lg shadow-md p-6'>
                <h2 class='text-xl font-semibold mb-4'>Page Not Found</h2>
                <p class='text-gray-600'>The requested page could not be found.</p>
                <p class='text-gray-600'>Looking for: $page_file</p>
              </div>";
      }
      ?>
    </main>
  </div>

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

    // Notification functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Notification toggle
      const notificationBell = document.querySelector('.notification-bell');
      const notificationsContainer = document.getElementById('notificationsContainer');
      const closeNotifications = document.getElementById('closeNotifications');
      const markAllRead = document.getElementById('markAllRead');
      
      // If notification bell exists, add event listener
      if (notificationBell) {
        notificationBell.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          notificationsContainer.classList.toggle('hidden');
          
          // Load notifications when opening
          if (!notificationsContainer.classList.contains('hidden')) {
            loadNotifications();
          }
        });
      }
      
      // Close notifications when clicking outside
      document.addEventListener('click', function(e) {
        if (notificationsContainer && 
            !notificationsContainer.classList.contains('hidden') && 
            !notificationsContainer.contains(e.target) && 
            !notificationBell.contains(e.target)) {
          notificationsContainer.classList.add('hidden');
        }
      });
      
      // Close button functionality
      if (closeNotifications) {
        closeNotifications.addEventListener('click', function() {
          notificationsContainer.classList.add('hidden');
        });
      }
      
      // Mark all as read functionality
      if (markAllRead) {
        markAllRead.addEventListener('click', function() {
          // Mark all notifications as read in the UI
          const unreadItems = document.querySelectorAll('.notification-item.unread');
          unreadItems.forEach(item => {
            item.classList.remove('unread');
          });
          
          // Mark as read in the backend
          fetch('../api/mark_all_notifications_read.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            }
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Update badge to show 0 unread notifications
              updateNotificationBadge(0);
              console.log('All notifications marked as read');
            }
          })
          .catch(error => {
            console.error('Error marking notifications as read:', error);
          });
        });
      }
      
      // Load notifications
      function loadNotifications() {
        const notificationsList = document.getElementById('notificationsList');
        const noNotifications = document.getElementById('noNotifications');
        
        if (!notificationsList) return;
        
        console.log('Loading notifications...');
        
        fetch('../api/get_notifications.php')
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            return response.json();
          })
          .then(data => {
            console.log('Notifications data:', data);
            
            if (data.success && data.notifications && data.notifications.length > 0) {
              notificationsList.innerHTML = '';
              noNotifications.classList.add('hidden');
              
              data.notifications.forEach(notification => {
                const notificationItem = document.createElement('div');
                notificationItem.className = `notification-item p-3 hover:bg-gray-50 ${notification.is_read ? '' : 'unread border-l-4 border-blue-500'}`;
                
                // Format date
                const date = new Date(notification.created_at);
                const formattedDate = date.toLocaleString();
                
                // Determine status badge color based on document status or notification status
                let statusBadge = '';
                const status = notification.document_status || notification.status || '';
                
                if (status) {
                  let badgeClass = '';
                  let statusText = status;
                  
                  switch (status.toLowerCase()) {
                    case 'approved':
                      badgeClass = 'bg-green-100 text-green-800';
                      statusText = 'Approved';
                      break;
                    case 'rejected':
                      badgeClass = 'bg-red-100 text-red-800';
                      statusText = 'Rejected';
                      break;
                    case 'revision_requested':
                      badgeClass = 'bg-purple-100 text-purple-800';
                      statusText = 'Revision';
                      break;
                    case 'on_hold':
                      badgeClass = 'bg-orange-100 text-orange-800';
                      statusText = 'On Hold';
                      break;
                    case 'pending':
                      badgeClass = 'bg-yellow-100 text-yellow-800';
                      statusText = 'Pending';
                      break;
                    default:
                      badgeClass = 'bg-gray-100 text-gray-800';
                  }
                  
                  statusBadge = `<span class="inline-block px-2 py-0.5 rounded text-xs ${badgeClass} mt-1">${statusText}</span>`;
                }
                
                // Create action link if document_id exists - deep link to in-dashboard document view
                let actionLink = '';
                if (notification.document_id) {
                  actionLink = `<a href="?page=view_document&id=${notification.document_id}" class="text-blue-600 text-xs hover:underline mt-1 block">View Document</a>`;
                }
                
                const notificationHTML = `
                  <div class="flex justify-between items-start w-full">
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-gray-900">${notification.title || 'Notification'}</p>
                      <p class="text-xs text-gray-500">${notification.message || ''}</p>
                      ${statusBadge}
                      ${actionLink}
                    </div>
                    <div class="flex flex-col items-end space-y-1 ml-3 flex-shrink-0">
                      <span class="text-xs text-gray-400 whitespace-nowrap">${formattedDate}</span>
                      <button class="ignore-notification-btn text-xs text-gray-400 hover:text-red-500 transition-colors duration-200 whitespace-nowrap" 
                              data-notification-id="${notification.notification_id}" 
                              title="Ignore this notification">
                        <i class="fas fa-times"></i> Ignore
                      </button>
                    </div>
                  </div>
                `;
                
                // console.log('Generated notification HTML for ID', notification.notification_id, ':', notificationHTML);
                notificationItem.innerHTML = notificationHTML;
                
                // Make entire item clickable when we have a destination
                if (notification.document_id) {
                  notificationItem.style.cursor = 'pointer';
                  notificationItem.addEventListener('click', (e) => {
                    // Avoid interfering with the Ignore button or the anchor itself
                    if (e.target.closest('.ignore-notification-btn')) return;
                    if (e.target && e.target.tagName && e.target.tagName.toLowerCase() === 'a') return;
                    window.location.href = `?page=view_document&id=${notification.document_id}`;
                  });
                }
                notificationsList.appendChild(notificationItem);
              });
              
              // Add event listeners for ignore buttons
              addIgnoreButtonListeners();
              
              // Update badge count
              const unreadCount = data.notifications.filter(n => !n.is_read).length;
              updateNotificationBadge(unreadCount);
              
              console.log(`Loaded ${data.notifications.length} notifications, ${unreadCount} unread`);
            } else {
              notificationsList.innerHTML = '';
              noNotifications.classList.remove('hidden');
              updateNotificationBadge(0);
              console.log('No notifications found');
            }
          })
          .catch(error => {
            console.error('Error fetching notifications:', error);
            notificationsList.innerHTML = `<div class="p-3 text-center text-sm text-red-500">Error loading notifications</div>`;
            noNotifications.classList.add('hidden');
          });
      }
      
      // Update notification badge
      function updateNotificationBadge(count) {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
          if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.remove('hidden');
            badge.style.display = 'flex'; // Ensure it's displayed as flex
            console.log(`Updated badge: ${count} notifications`);
          } else {
            badge.textContent = '0';
            badge.classList.add('hidden');
            console.log('No unread notifications, hiding badge');
          }
        } else {
          console.error('Notification badge element not found');
        }
      }
      
      // Add event listeners for ignore buttons
      function addIgnoreButtonListeners() {
        const ignoreButtons = document.querySelectorAll('.ignore-notification-btn');
        // console.log('Found ignore buttons:', ignoreButtons.length);
        
        ignoreButtons.forEach((button, index) => {
          // console.log(`Adding listener to button ${index}:`, button);
          
          button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const notificationId = this.getAttribute('data-notification-id');
            // console.log('Ignore button clicked for notification:', notificationId);
            if (notificationId) {
              ignoreNotification(notificationId, this);
            }
          });
        });
      }
      
      // Ignore notification function
      function ignoreNotification(notificationId, buttonElement) {
        // Show confirmation
        if (!confirm('Are you sure you want to ignore this notification? This action cannot be undone.')) {
          return;
        }
        
        // Disable button to prevent multiple clicks
        buttonElement.disabled = true;
        buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ignoring...';
        
        fetch('../api/ignore_notification.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            notification_id: notificationId
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Remove the notification item from the UI
            const notificationItem = buttonElement.closest('.notification-item');
            if (notificationItem) {
              notificationItem.style.opacity = '0.5';
              notificationItem.style.transition = 'opacity 0.3s ease';
              
              setTimeout(() => {
                notificationItem.remove();
                
                // Check if there are any notifications left
                const remainingNotifications = document.querySelectorAll('.notification-item');
                if (remainingNotifications.length === 0) {
                  // Show "No notifications" message
                  const noNotifications = document.getElementById('noNotifications');
                  if (noNotifications) {
                    noNotifications.classList.remove('hidden');
                  }
                  updateNotificationBadge(0);
                } else {
                  // Update badge count
                  const unreadCount = document.querySelectorAll('.notification-item.unread').length;
                  updateNotificationBadge(unreadCount);
                }
              }, 300);
            }
            
            console.log('Notification ignored successfully');
          } else {
            // Re-enable button and show error
            buttonElement.disabled = false;
            buttonElement.innerHTML = '<i class="fas fa-times"></i> Ignore';
            alert('Failed to ignore notification: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error ignoring notification:', error);
          // Re-enable button and show error
          buttonElement.disabled = false;
          buttonElement.innerHTML = '<i class="fas fa-times"></i> Ignore';
          alert('Failed to ignore notification. Please try again.');
        });
      }
      
      // Load notifications on page load
      loadNotifications();
      
      // Refresh notifications every minute
      setInterval(loadNotifications, 60000);
    });

    // Profile dropdown functionality
    const profileBtn = document.getElementById('profileBtn');
    const profileDropdown = document.getElementById('profileDropdown');

    if (profileBtn && profileDropdown) {
      profileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        profileDropdown.classList.toggle('hidden');
      });

      // Close dropdown when clicking elsewhere
      document.addEventListener('click', (e) => {
        if (!profileDropdown.contains(e.target) && e.target !== profileBtn) {
          profileDropdown.classList.add('hidden');
        }
      });
    }

    // Sidebar collapse functionality
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('main');
    
    if (sidebarToggle && sidebar) {
      sidebarToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Toggle the 'close' class
        sidebar.classList.toggle('close');
        const isCollapsed = sidebar.classList.contains('close');

        // Adjust main content margin
        if (mainContent) {
          mainContent.style.marginLeft = isCollapsed ? '80px' : '230px';
        }
        
        // Adjust navigation bar position
        const navBar = document.querySelector('nav');
        if (navBar) {
          navBar.style.left = isCollapsed ? '80px' : '230px';
        }

        // Hide/show all text spans (including badges)
        const allSpans = sidebar.querySelectorAll('a span');
        allSpans.forEach(el => {
          el.style.display = isCollapsed ? 'none' : '';
        });

        // Hide submenu items when collapsed
        const subMenus = sidebar.querySelectorAll('.ml-8');
        subMenus.forEach(el => {
          el.style.display = isCollapsed ? 'none' : 'flex';
        });

        // Hide logo text but keep logo image visible
        const logoText = sidebar.querySelector('.logo h2');
        const logoContainer = sidebar.querySelector('.logo');
        if (logoText) {
          logoText.style.display = isCollapsed ? 'none' : 'block';
        }
        if (logoContainer) {
          logoContainer.style.justifyContent = isCollapsed ? 'center' : 'flex-start';
        }
        
        // Center all menu items when collapsed
        const menuLinks = sidebar.querySelectorAll('a');
        menuLinks.forEach(link => {
          if (isCollapsed) {
            link.style.justifyContent = 'center';
            link.style.padding = '0.75rem 0';
          } else {
            link.style.justifyContent = '';
            link.style.padding = '';
          }
        });

        // Rotate toggle button icon - handled by CSS now
      });
    }
  </script>
  
  <!-- Enhanced Notification System - REMOVED: Using direct implementation -->
  <!-- <script src="../assets/js/enhanced-notifications.js"></script> -->
  <!-- Include reminder notification system -->
  <script src="../assets/js/reminder-notifications.js"></script>
</body>

</html>