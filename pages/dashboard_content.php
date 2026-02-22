<?php
require_once '../includes/config.php';

// Get document counts
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'] ?? 0;
$counts = [
  'incoming' => 0,
  'outgoing' => 0,
  'pending' => 0,
  'approved' => 0,
  'revision' => 0,
  'reminders' => 0
];

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
  $counts['incoming'] = 0;
} else {
  $incoming_stmt->bind_param("i", $office_id);
  $incoming_stmt->execute();
  $incoming_result = $incoming_stmt->get_result();
  if($incoming_result && $incoming_row = $incoming_result->fetch_assoc()) {
    $counts['incoming'] = $incoming_row['count'];
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
  $counts['outgoing'] = 0;
} else {
  $outgoing_stmt->bind_param("i", $office_id);
  $outgoing_stmt->execute();
  $outgoing_result = $outgoing_stmt->get_result();
  if($outgoing_result && $outgoing_row = $outgoing_result->fetch_assoc()) {
    $counts['outgoing'] = $outgoing_row['count'];
  }
}

// Get pending/hold count - match hold.php query exactly
$pending_query = "SELECT COUNT(DISTINCT d.document_id) as count 
                FROM documents d
                JOIN document_types dt ON d.type_id = dt.type_id
                JOIN document_workflow dw ON d.document_id = dw.document_id
                JOIN offices o ON dw.office_id = o.office_id
                WHERE dw.office_id = ? 
                AND UPPER(dw.status) IN ('ON_HOLD','HOLD')";
$pending_stmt = $conn->prepare($pending_query);
if ($pending_stmt === false) {
  $pending_count = 0;
} else {
  $pending_stmt->bind_param("i", $office_id);
  $pending_stmt->execute();
  $pending_result = $pending_stmt->get_result();
  $pending_count = ($pending_result && $pending_result->num_rows > 0) ? $pending_result->fetch_assoc()['count'] : 0;
}

// Get revision count - match documents_needing_revision.php query exactly
$revision_query = "SELECT COUNT(DISTINCT d.document_id) as count 
                  FROM documents d
                  WHERE d.status = 'revision' 
                  AND d.creator_id = ?";
$revision_stmt = $conn->prepare($revision_query);
if ($revision_stmt === false) {
  $counts['revision'] = 0;
} else {
  $revision_stmt->bind_param("i", $user_id);
  $revision_stmt->execute();
  $revision_result = $revision_stmt->get_result();
  if($revision_result && $revision_row = $revision_result->fetch_assoc()) {
    $counts['revision'] = $revision_row['count'];
  }
}

// Get approved count - match approved.php query exactly (no office filter)
$approved_query = "SELECT COUNT(d.document_id) as count 
                   FROM documents d
                   WHERE d.status = 'approved'";
$approved_stmt = $conn->prepare($approved_query);
if ($approved_stmt === false) {
  $counts['approved'] = 0;
} else {
  $approved_stmt->execute();
  $approved_result = $approved_stmt->get_result();
  if($approved_result && $approved_row = $approved_result->fetch_assoc()) {
    $counts['approved'] = $approved_row['count'];
  }
}

// Get reminders count - active reminders for this user
$today = date('Y-m-d');
// Check if reminders table exists before querying
$table_check = $conn->query("SHOW TABLES LIKE 'reminders'");
$reminders_table_exists = ($table_check && $table_check->num_rows > 0);

if ($reminders_table_exists) {
  // Get count of reminders for the stats section at the top
  $reminders_count_query = "SELECT COUNT(*) as count FROM reminders WHERE user_id = ? AND reminder_date >= ? AND is_completed = 0";
  $reminders_count_stmt = $conn->prepare($reminders_count_query);
  if ($reminders_count_stmt === false) {
    $counts['reminders'] = 0;
  } else {
    $reminders_count_stmt->bind_param("is", $user_id, $today);
    $reminders_count_stmt->execute();
    $reminders_count_result = $reminders_count_stmt->get_result();
    if($reminders_count_result && $reminders_count_row = $reminders_count_result->fetch_assoc()) {
      $counts['reminders'] = $reminders_count_row['count'];
    } else {
      $counts['reminders'] = 0;
    }
  }
} else {
  $counts['reminders'] = 0;
}

// Get document categories
$categories_query = "SELECT dt.type_name, COUNT(*) as count 
                    FROM documents d 
                    JOIN document_types dt ON d.type_id = dt.type_id 
                    GROUP BY d.type_id 
                    ORDER BY count DESC 
                    LIMIT 4";
$categories_result = $conn->query($categories_query);
$categories = [];
$total_docs = 0;
while($category = $categories_result->fetch_assoc()) {
  $categories[$category['type_name']] = $category['count'];
  $total_docs += $category['count'];
}

// Get recent documents - using the new structure
$recents_query = "SELECT 
                   d.document_id,
                   d.title, 
                   d.status, 
                   d.created_at, 
                   dt.type_name
                 FROM documents d 
                 JOIN document_types dt ON d.type_id = dt.type_id 
                 LEFT JOIN (
                   SELECT document_id, office_id, status
                   FROM document_workflow
                   WHERE UPPER(status) = 'CURRENT'
                 ) dwc ON d.document_id = dwc.document_id
                 WHERE d.creator_id = ? 
                    OR (dwc.office_id = (SELECT office_id FROM users WHERE user_id = ?) )
                 GROUP BY d.document_id
                 ORDER BY d.created_at DESC 
                 LIMIT 4";
$recents_stmt = $conn->prepare($recents_query);
if ($recents_stmt === false) {
  // Handle error - likely because we're in the middle of database structure change
  $recents = [];
} else {
  $recents_stmt->bind_param("ii", $user_id, $user_id);
  $recents_stmt->execute();
  $recents_result = $recents_stmt->get_result();
  $recents = [];
  if ($recents_result) {
    while($recent = $recents_result->fetch_assoc()) {
      $recents[] = $recent;
    }
  }
}

// Calendar variables for later use
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// Environmental impact calculations
// Get total number of documents in the system
$total_system_docs_query = "SELECT COUNT(*) as count FROM documents";
$total_system_docs_result = $conn->query($total_system_docs_query);
if (!$total_system_docs_result) {
  // Handle error - likely because we're in the middle of database structure change
  $total_system_docs = 0;
} else {
  $total_system_docs_row = $total_system_docs_result->fetch_assoc();
  $total_system_docs = $total_system_docs_row['count'];
}

// Calculate environmental impact metrics
// Assumptions:
// - Average document is 5 pages
// - Each page of paper weighs about 5 grams
// - Producing 1kg of paper requires about 10 liters of water
// - Producing 1kg of paper emits about 3kg of CO2
// - It takes about 17 trees to produce 1 ton of paper

$avg_pages_per_doc = 5;
$total_pages_saved = $total_system_docs * $avg_pages_per_doc;
$total_paper_saved_kg = $total_pages_saved * 0.005; // 5g per page
$water_saved_liters = $total_paper_saved_kg * 10;
$co2_avoided_kg = $total_paper_saved_kg * 3;
$trees_saved = ($total_paper_saved_kg / 1000) * 17;

// Environmental Impact - Change from monthly to yearly view
$current_year = date('Y');
$selected_year = isset($_GET['year']) ? $_GET['year'] : $current_year;

// For calendar view - keep monthly selection
$current_month = date('Y-m');
$current_month_name = date('F Y');
$selected_month = isset($_GET['month']) ? $_GET['month'] : $current_month;
$selected_month_name = date('F Y', strtotime($selected_month . '-01'));

// Generate past 5 years for the dropdown
$past_years = [];
for ($i = 0; $i < 5; $i++) {
  $year = date('Y', strtotime("-$i years"));
  $past_years[$year] = $year;
}

// Generate past 12 months for the calendar dropdown
$past_months = [];
for ($i = 0; $i < 12; $i++) {
  $month = date('Y-m', strtotime("-$i months"));
  $month_name = date('F Y', strtotime("-$i months"));
  $past_months[$month] = $month_name;
}

// Calculate date range for selected year (for environmental impact)
$year_start = $selected_year . '-01-01';
$year_end = $selected_year . '-12-31';

// Calculate date range for selected month (for calendar)
$month_start = date('Y-m-01', strtotime($selected_month . '-01'));
$month_end = date('Y-m-t', strtotime($selected_month . '-01'));

// Environmental impact calculations for the selected year
// Get total number of documents in the system for the selected year
$total_system_docs_query = "SELECT COUNT(*) as count FROM documents WHERE created_at BETWEEN ? AND ?";
$total_system_docs_stmt = $conn->prepare($total_system_docs_query);
$total_system_docs_stmt->bind_param("ss", $year_start, $year_end);
$total_system_docs_stmt->execute();
$total_system_docs_result = $total_system_docs_stmt->get_result();
$total_system_docs_row = $total_system_docs_result->fetch_assoc();
$total_system_docs = $total_system_docs_row['count'];

// Calculate environmental impact metrics for the selected year
$avg_pages_per_doc = 5;
$total_pages_saved = $total_system_docs * $avg_pages_per_doc;
$total_paper_saved_kg = $total_pages_saved * 0.005; // 5g per page
$water_saved_liters = $total_paper_saved_kg * 10;
$co2_avoided_kg = $total_paper_saved_kg * 3;
$trees_saved = ($total_paper_saved_kg / 1000) * 17;
?>

<?php if (!defined('INCLUDED_IN_DASHBOARD')): ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Base styles */
    .grid {
      display: grid;
      gap: 1rem;
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
    }
    
    /* Fixed notification panel */
    #documentModal, .fixed-notification {
      z-index: 1050 !important;
    }
    
    /* Notification popup styles */
    .notification-popup {
      position: fixed;
      top: 4rem;
      right: 1rem;
      background-color: white;
      border: 1px solid #e5e7eb;
      border-radius: 0.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      z-index: 1100;
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

    /* Responsive adjustments */
    @media screen and (max-width: 1024px) {
      .grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
      }

      .grid-cols-3 {
        grid-template-columns: 1fr;
      }

      .col-span-2 {
        grid-column: span 1;
      }
    }

    @media screen and (max-width: 768px) {
      .grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
      }

      .grid-cols-3 {
        grid-template-columns: 1fr;
      }

      .col-span-2 {
        grid-column: span 1;
      }

      .content {
        width: calc(100% - 60px);
        left: 200px;
      }
    }

    @media screen and (max-width: 576px) {
      .grid-cols-4 {
        grid-template-columns: 1fr;
      }

      .grid-cols-3 {
        grid-template-columns: 1fr;
      }

      .content nav form .form-input input {
        display: none;
      }

      .content nav form .form-input button {
        width: auto;
        height: auto;
        background: transparent;
        color: var(--dark);
      }

      .content nav form.show .form-input input {
        display: block;
        width: 100%;
      }

      .content nav form.show .form-input button {
        width: 36px;
        height: 100%;
        color: var(--light);
        background: var(--danger);
        border-radius: 0 36px 36px 0;
      }

      .content nav form.show~.notif, .content nav form.show~.profile {
        display: none;
      }

      .content main .insights {
        grid-template-columns: 1fr;
      }

      .content main .bottom-data .header,
      .content main .bottom-data .orders table {
        min-width: 340px;
      }
    }
    
    /* Environmental Impact Section Styles */
    .eco-stat {
      position: relative;
      overflow: hidden;
      transition: transform 0.3s ease;
    }
    
    .eco-stat:hover {
      transform: translateY(-5px);
    }
    
    .eco-stat::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, #10b981, #3b82f6);
    }
    
    .eco-icon {
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
    }
    
    .progress-ring {
      transform: rotate(-90deg);
    }
    
    .progress-ring-circle {
      stroke-dasharray: 283;
      transition: stroke-dashoffset 0.5s ease;
    }
    
    #calendarViewBtn.active, #listViewBtn.active {
      background-color: rgb(22, 59, 32);
      color: white;
    }

    /* Toggle Switch Styles */
    .toggle-checkbox:checked {
      right: 0;
      border-color: #4f46e5;
    }
    .toggle-checkbox:checked + .toggle-label {
      background-color: #4f46e5;
    }
    .toggle-checkbox {
      right: 0;
      transition: all 0.3s;
    }
    .toggle-label {
      transition: background-color 0.3s;
    }
    
    /* Notification Styles */
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
    }
  </style>
</head>
  <body class="p-6 bg-gray-100">
<?php endif; ?>

  <!-- Stats Cards -->
  <div class="grid grid-cols-6 gap-4 mb-6">
    <div class="bg-white shadow rounded-xl p-4 transition-all duration-300 hover:shadow-md hover:translate-y-[-2px]">
      <a href="?page=incoming" class="block">
        <div class="flex items-center">
          <div class="w-10 h-10 mr-3 bg-yellow-100 rounded-full flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-600" viewBox="0 0 20 20" fill="currentColor">
              <path d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8z" />
              <path d="M12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z" />
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-gray-700"><?php echo $counts['incoming']; ?></h3>
            <p class="text-sm text-gray-500">Incoming</p>
          </div>
        </div>
      </a>
    </div>
    <div class="bg-white shadow rounded-xl p-4 transition-all duration-300 hover:shadow-md hover:translate-y-[-2px]">
      <a href="?page=outgoing" class="block">
        <div class="flex items-center">
          <div class="w-10 h-10 mr-3 bg-blue-100 rounded-full flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" viewBox="0 0 20 20" fill="currentColor">
              <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-gray-700"><?php echo $counts['outgoing']; ?></h3>
            <p class="text-sm text-gray-500">Outgoing</p>
          </div>
        </div>
      </a>
    </div>
    <div class="bg-white shadow rounded-xl p-4 transition-all duration-300 hover:shadow-md hover:translate-y-[-2px]">
      <a href="?page=pending" class="block">
        <div class="flex items-center">
          <div class="w-10 h-10 mr-3 bg-orange-100 rounded-full flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-orange-600" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-gray-700"><?php echo $pending_count; ?></h3>
            <p class="text-sm text-gray-500">Pending</p>
          </div>
        </div>
      </a>
    </div>
    <div class="bg-white shadow rounded-xl p-4 transition-all duration-300 hover:shadow-md hover:translate-y-[-2px] <?php echo $counts['revision'] > 0 ? 'bg-purple-50 border border-purple-100' : ''; ?>">
      <a href="?page=documents_needing_revision" class="block">
        <div class="flex items-center">
          <div class="w-10 h-10 mr-3 bg-purple-100 rounded-full flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600" viewBox="0 0 20 20" fill="currentColor">
              <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-gray-700"><?php echo $counts['revision']; ?></h3>
            <p class="text-sm text-gray-500">Revision</p>
          </div>
        </div>
      </a>
    </div>
    <div class="bg-white shadow rounded-xl p-4 transition-all duration-300 hover:shadow-md hover:translate-y-[-2px]">
      <a href="?page=received" class="block">
        <div class="flex items-center">
          <div class="w-10 h-10 mr-3 bg-green-100 rounded-full flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-600" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-gray-700"><?php echo $counts['approved']; ?></h3>
            <p class="text-sm text-gray-500">Approved</p>
          </div>
        </div>
      </a>
    </div>
    <div class="bg-white shadow rounded-xl p-4 transition-all duration-300 hover:shadow-md hover:translate-y-[-2px] bg-blue-50 border border-blue-100">
      <a href="?page=reminders" class="block">
        <div class="flex items-center">
          <div class="w-10 h-10 mr-3 bg-blue-100 rounded-full flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-blue-700"><?php echo $counts['reminders']; ?></h3>
            <p class="text-sm text-blue-600">Reminders</p>
          </div>
        </div>
      </a>
    </div>
  </div>

  <!-- Environmental Impact -->
  <div class="bg-white shadow rounded-xl p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-bold flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
        </svg>
        Environmental Impact
      </h2>
      <div class="flex items-center space-x-3">
        <span class="text-sm text-green-600 font-medium bg-green-100 px-3 py-1 rounded-full flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Making a Difference
        </span>
        <form method="get" class="flex items-center">
          <input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : 'dashboard_content'; ?>">
          <select name="year" id="year-selector" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" onchange="this.form.submit()">
            <?php foreach($past_years as $year_val => $year_name): ?>
              <option value="<?php echo $year_val; ?>" <?php echo $selected_year == $year_val ? 'selected' : ''; ?>>
                <?php echo $year_name; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>
    
    <p class="text-gray-600 mb-6 border-l-4 border-green-200 pl-3 py-1 italic">
      By using our digital document management system, you're helping reduce paper waste and contributing to a more sustainable future.
      <strong>Viewing impact for: <?php echo $selected_year; ?></strong>
    </p>
    
    <div class="grid grid-cols-2 md:grid-cols-4 gap-5 mb-6">
      <div class="eco-stat bg-white border border-gray-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
        <div class="absolute w-1 h-full bg-green-500 left-0 top-0"></div>
        <div class="flex items-center mb-3">
          <div class="eco-icon bg-green-100 text-green-600 mr-3 p-2 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div>
            <h3 class="text-xl font-bold text-gray-800"><?php echo number_format($total_pages_saved); ?></h3>
            <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Pages Saved</p>
          </div>
        </div>
        <div class="text-xs text-gray-600 bg-gray-50 p-2 rounded-lg">
          That's approximately <?php echo number_format($total_paper_saved_kg, 1); ?> kg of paper!
        </div>
      </div>
      
      <div class="eco-stat bg-white border border-gray-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
        <div class="absolute w-1 h-full bg-blue-500 left-0 top-0"></div>
        <div class="flex items-center mb-3">
          <div class="eco-icon bg-blue-100 text-blue-600 mr-3 p-2 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
          </div>
          <div>
            <h3 class="text-xl font-bold text-gray-800"><?php echo number_format($water_saved_liters); ?></h3>
            <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Liters of Water</p>
          </div>
        </div>
        <div class="text-xs text-gray-600 bg-gray-50 p-2 rounded-lg">
          Enough to fill <?php echo number_format($water_saved_liters / 1000, 1); ?> cubic meters of water
        </div>
      </div>
      
      <div class="eco-stat bg-white border border-gray-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
        <div class="absolute w-1 h-full bg-amber-500 left-0 top-0"></div>
        <div class="flex items-center mb-3">
          <div class="eco-icon bg-amber-100 text-amber-600 mr-3 p-2 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <h3 class="text-xl font-bold text-gray-800"><?php echo number_format($co2_avoided_kg, 1); ?></h3>
            <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">kg CO₂ Avoided</p>
          </div>
        </div>
        <div class="text-xs text-gray-600 bg-gray-50 p-2 rounded-lg">
          Equivalent to <?php echo number_format($co2_avoided_kg / 200, 1); ?> trees absorbing CO₂ for a year
        </div>
      </div>
      
      <div class="eco-stat bg-white border border-gray-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
        <div class="absolute w-1 h-full bg-emerald-500 left-0 top-0"></div>
        <div class="flex items-center mb-3">
          <div class="eco-icon bg-emerald-100 text-emerald-600 mr-3 p-2 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <div>
            <h3 class="text-xl font-bold text-gray-800"><?php echo number_format($trees_saved, 1); ?></h3>
            <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Trees Preserved</p>
          </div>
        </div>
        <div class="text-xs text-gray-600 bg-gray-50 p-2 rounded-lg">
          That's a small forest worth of trees saved!
        </div>
      </div>
    </div>
    
    <!-- Progress Visualization -->
    <div class="flex flex-col md:flex-row items-center justify-between bg-gray-50 rounded-xl p-6 border border-gray-200">
      <div class="mb-4 md:mb-0 md:mr-6 flex-1">
        <h3 class="text-lg font-bold mb-2 flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          Your Sustainability Journey
        </h3>
        <p class="text-sm text-gray-600 mb-4">
          Every digital document you process helps reduce our collective carbon footprint. 
          Here's how your contribution compares to traditional paper processes.
        </p>
        <div class="flex items-center">
          <div class="w-full bg-gray-200 rounded-full h-2.5 mr-2 overflow-hidden">
            <div class="bg-gradient-to-r from-green-400 to-green-600 h-2.5 rounded-full" style="width: 85%"></div>
          </div>
          <span class="text-sm font-medium bg-green-100 text-green-800 px-2 py-0.5 rounded">85% Reduction</span>
        </div>
      </div>
      
      <div class="relative flex items-center justify-center w-32 h-32">
        <svg class="progress-ring w-full h-full" viewBox="0 0 120 120">
          <circle cx="60" cy="60" r="54" fill="none" stroke="#e5e7eb" stroke-width="12"/>
          <circle cx="60" cy="60" r="54" fill="none" stroke="url(#greenGradient)" stroke-width="12" stroke-linecap="round" stroke-dasharray="339.292" stroke-dashoffset="<?php echo 339.292 - (339.292 * min(1, $total_system_docs / 1000)); ?>">
            <animate attributeName="stroke-dashoffset" from="339.292" to="<?php echo 339.292 - (339.292 * min(1, $total_system_docs / 1000)); ?>" dur="1.5s" begin="0s" fill="freeze" />
          </circle>
          <defs>
            <linearGradient id="greenGradient" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stop-color="#10b981"/>
              <stop offset="100%" stop-color="#34d399"/>
            </linearGradient>
          </defs>
        </svg>
        <div class="absolute inset-0 flex items-center justify-center">
          <span class="text-2xl font-bold"><?php echo number_format(min(100, $total_system_docs / 10)); ?>%</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Chart & Recents Section -->
  <div class="grid grid-cols-3 gap-4 mb-6">
    <!-- Documents by Category (Takes 2 columns) -->
    <div class="bg-white shadow rounded-xl overflow-hidden col-span-2">
      <div class="p-4 bg-gray-50 border-b border-gray-200">
        <h2 class="text-lg font-bold flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
          </svg>
          Documents by Category
        </h2>
      </div>
      <div class="p-6 flex flex-wrap">
        <div class="flex-1 min-w-[250px] flex items-center justify-center">
          <div class="w-full h-full max-w-[200px] aspect-square relative">
            <div class="absolute inset-0 flex items-center justify-center">
              <div class="text-center">
                <div class="text-3xl font-bold text-gray-800"><?php echo $total_docs; ?></div>
                <div class="text-sm text-gray-500">Total Documents</div>
              </div>
            </div>
            
            <!-- Simplified chart representation -->
            <svg class="w-full h-full" viewBox="0 0 100 100">
              <circle cx="50" cy="50" r="40" fill="none" stroke="#e5e7eb" stroke-width="15" />
              
              <?php 
              $cumulative = 0;
              $colors = ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b'];
              $i = 0;
              
              foreach($categories as $name => $count) {
                $percentage = ($count / max(1, $total_docs)) * 100;
                $dasharray = 251.2; // 2 * PI * 40 (circumference)
                $dashoffset = $dasharray * (1 - $percentage / 100);
                $start_angle = $cumulative;
                $cumulative += $percentage;
                $color = $colors[$i % count($colors)];
                
                echo '<circle cx="50" cy="50" r="40" fill="none" stroke="' . $color . '" 
                      stroke-width="15" stroke-dasharray="' . $dasharray . '" 
                      stroke-dashoffset="' . $dashoffset . '" 
                      transform="rotate(' . ($start_angle * 3.6 - 90) . ' 50 50)" />';
                
                $i++;
              }
              ?>
            </svg>
          </div>
        </div>
        
        <!-- Side Information -->
        <div class="flex-1 pl-6 flex flex-col justify-center min-w-[250px]">
          <h3 class="text-md font-semibold mb-3 pb-2 border-b border-gray-200">Category Breakdown</h3>
          <ul class="text-sm text-gray-700 space-y-3">
            <?php 
              if(empty($categories)) {
                echo "<li class='p-2 bg-gray-50 rounded'>No documents found</li>";
              } else {
                $colors = ['blue', 'green', 'purple', 'orange'];
                $i = 0;
                
                foreach($categories as $name => $count) {
                  $color = $colors[$i % count($colors)];
                  $percentage = round(($count / max(1, $total_docs)) * 100);
                  
                  echo "<li class='flex items-center'>";
                  echo "<div class='w-3 h-3 rounded-full bg-{$color}-500 mr-2'></div>";
                  echo "<div class='flex-1'>";
                  echo "<div class='flex justify-between mb-1'>";
                  echo "<span class='font-medium'>{$name}</span>";
                  echo "<span class='text-gray-500'>{$count} docs</span>";
                  echo "</div>";
                  echo "<div class='w-full bg-gray-200 rounded-full h-1.5'>";
                  echo "<div class='bg-{$color}-500 h-1.5 rounded-full' style='width: {$percentage}%'></div>";
                  echo "</div>";
                  echo "</div>";
                  echo "</li>";
                  
                  $i++;
                }
              }
            ?>
          </ul>
        </div>
      </div>
    </div>

    <!-- Recents (Takes 1 column) -->
    <div class="bg-white shadow rounded-xl overflow-hidden">
      <div class="p-4 bg-gray-50 border-b border-gray-200">
        <h2 class="text-lg font-bold flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Recent Documents
        </h2>
      </div>
      <div class="p-4">
        <?php 
          if(empty($recents)) {
            echo "<div class='p-4 bg-gray-50 rounded text-center text-gray-500'>No recent documents</div>";
          } else {
            echo "<div class='space-y-3'>";
            foreach($recents as $doc) {
              $status_badge = '';
              $status_color = '';
              
              switch($doc['status']) {
                case 'approved': 
                  $status_badge = '<span class="bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded">Approved</span>'; 
                  $status_color = 'border-green-200';
                  break;
                case 'pending': 
                  $status_badge = '<span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-0.5 rounded">Pending</span>';
                  $status_color = 'border-yellow-200';
                  break;
                case 'hold': 
                case 'on_hold': 
                  $status_badge = '<span class="bg-orange-100 text-orange-800 text-xs px-2 py-0.5 rounded">Hold</span>';
                  $status_color = 'border-orange-200';
                  break;
                case 'rejected': 
                  $status_badge = '<span class="bg-red-100 text-red-800 text-xs px-2 py-0.5 rounded">Rejected</span>';
                  $status_color = 'border-red-200';
                  break;
                case 'revision': 
                case 'revision_requested': 
                  $status_badge = '<span class="bg-purple-100 text-purple-800 text-xs px-2 py-0.5 rounded">Revision</span>';
                  $status_color = 'border-purple-200';
                  break;
                default: 
                  $status_badge = '<span class="bg-gray-100 text-gray-800 text-xs px-2 py-0.5 rounded">' . ucfirst($doc['status']) . '</span>';
                  $status_color = 'border-gray-200';
              }
              
              $date = date('M j, g:i A', strtotime($doc['created_at']));
              echo "<div class='p-3 border-l-4 {$status_color} bg-gray-50 rounded-r-md hover:bg-gray-100 transition-colors'>";
              echo "<div class='flex justify-between items-start'>";
              echo "<div class='flex-1'>";
              echo "<h4 class='font-medium text-gray-800 truncate' title='{$doc['title']}'>{$doc['title']}</h4>";
              echo "<div class='flex items-center mt-1'>";
              echo "<span class='text-xs text-gray-500 bg-gray-200 px-1.5 py-0.5 rounded mr-2'>{$doc['type_name']}</span>";
              echo $status_badge;
              echo "</div>";
              echo "</div>";
              echo "</div>";
              echo "<p class='text-xs text-gray-500 mt-2'>{$date}</p>";
              echo "</div>";
            }
            echo "</div>";
          }
        ?>
      </div>
    </div>
  </div>

  <!-- Security Anomalies (President only) -->
  <?php
    // Treat Super Admin like President for privileged widgets
    $office_check_stmt = $conn->prepare("SELECT office_id FROM users WHERE user_id = ?");
    $office_check_stmt->bind_param("i", $user_id);
    $office_check_stmt->execute();
    $office_check_res = $office_check_stmt->get_result();
    $user_office_row = $office_check_res ? $office_check_res->fetch_assoc() : null;
    $is_president = ($user_office_row && (int)$user_office_row['office_id'] === 1) || ((isset($_SESSION['role']) ? $_SESSION['role'] : '') === 'Super Admin');
  ?>
  <?php if ($is_president): ?>
  <div class="bg-white shadow rounded-xl p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-bold flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-red-500" viewBox="0 0 24 24" fill="currentColor">
          <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4m0 4h.01" />
        </svg>
        Security Anomalies
      </h2>
      <button id="refreshAnomalies" class="px-3 py-1 text-sm rounded-md bg-red-50 text-red-700 hover:bg-red-100">Refresh</button>
    </div>
    <div id="anomaliesContainer" class="space-y-3">
      <div class="text-gray-500 text-sm">Loading...</div>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const container = document.getElementById('anomaliesContainer');
      const refreshBtn = document.getElementById('refreshAnomalies');
      let lastAnomalySignature = '';

      function renderAnomalies(items) {
        if (!items || items.length === 0) {
          container.innerHTML = '<div class="text-sm text-gray-500">No anomalies detected.</div>';
          return;
        }
        container.innerHTML = '';
        items.forEach(a => {
          const color = a.severity === 'high' ? 'red' : (a.severity === 'medium' ? 'orange' : 'yellow');
          const wrapper = document.createElement('div');
          wrapper.className = 'border border-' + color + '-200 bg-' + color + '-50 rounded-lg p-3';
          const ip = a.data && a.data.ip_address ? a.data.ip_address : 'N/A';
          const who = a.data && a.data.user_identifier ? a.data.user_identifier : 'Unknown';
          const attempts = a.data && a.data.attempts ? a.data.attempts : undefined;
          const extra = attempts ? `Attempts: ${attempts} · IP: ${ip} · User: ${who}` 
                                 : (a.data && a.data.recent_count ? `Recent: ${a.data.recent_count}, Baseline/hr: ${a.data.baseline_avg_per_hour?.toFixed ? a.data.baseline_avg_per_hour.toFixed(2) : a.data.baseline_avg_per_hour}` : '');
          wrapper.innerHTML = `
            <div class="flex items-start justify-between">
              <div>
                <div class="font-semibold text-${color}-800">${a.message}</div>
                <div class="text-xs text-${color}-700 mt-1">Type: ${a.type.replace('_', ' ')}</div>
                ${extra ? `<div class="text-xs text-${color}-700 mt-1">${extra}</div>` : ''}
              </div>
              <span class="text-xs px-2 py-0.5 rounded bg-${color}-100 text-${color}-800 uppercase">${a.severity}</span>
            </div>
          `;
          container.appendChild(wrapper);
        });
      }

      function loadAnomalies() {
        container.innerHTML = '<div class="text-gray-500 text-sm">Loading...</div>';
        fetch('../api/security_anomalies.php', { credentials: 'same-origin' })
          .then(r => r.json())
          .then(data => {
            if (!data.success) throw new Error(data.error || 'Failed to load');
            renderAnomalies(data.anomalies || []);
          })
          .catch(err => {
            container.innerHTML = `<div class="text-sm text-red-600">Error loading anomalies: ${err.message}</div>`;
          });
      }

      // Lightweight poller to raise real-time toast alerts for new anomalies
      function pollAnomaliesForAlert() {
        fetch('../api/security_anomalies.php', { credentials: 'same-origin' })
          .then(r => r.json())
          .then(data => {
            if (!data.success) return;
            const anomalies = data.anomalies || [];
            const signature = JSON.stringify(anomalies);
            if (anomalies.length > 0 && signature !== lastAnomalySignature) {
              lastAnomalySignature = signature;
              showSecurityToast(anomalies);
            }
          })
          .catch(() => {});
      }

      function showSecurityToast(anomalies) {
        const highCount = anomalies.filter(a => a.severity === 'high').length;
        const msg = highCount > 0
          ? `${highCount} high-severity security event${highCount>1?'s':''} detected`
          : `${anomalies.length} security anomal${anomalies.length>1?'ies':'y'} detected`;

        const toast = document.createElement('div');
        toast.className = 'fixed top-4 right-4 bg-white border border-red-200 text-red-800 px-4 py-3 rounded shadow-lg z-50 max-w-sm w-[360px]';
        toast.innerHTML = `
          <div class="flex items-start justify-between">
            <div class="flex items-start">
              <div class="bg-red-500 text-white rounded-full p-2 mr-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.72-1.36 3.485 0l6.518 11.596c.75 1.335-.213 2.995-1.742 2.995H3.48c-1.53 0-2.492-1.66-1.743-2.995L8.257 3.1zM11 13a1 1 0 10-2 0 1 1 0 002 0zm-1-2a1 1 0 01-1-1V7a1 1 0 112 0v3a1 1 0 01-1 1z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <div class="font-semibold">Security Alert</div>
                <div class="text-sm mt-1">${msg}</div>
                <ul class="mt-2 text-xs list-disc list-inside max-h-40 overflow-y-auto">
                  ${anomalies.slice(0,4).map(a => `<li>${a.message}${a.data && a.data.ip_address ? ` (IP: ${a.data.ip_address||a.data.user_identifier||''})` : ''}</li>`).join('')}
                </ul>
                ${anomalies.length>4?`<div class="text-xs mt-1 text-gray-600">and ${anomalies.length-4} more…</div>`:''}
                <button id="openAnomaliesPanel" class="mt-3 text-xs text-red-700 underline">Open anomalies</button>
              </div>
            </div>
            <button aria-label="Close" class="ml-3 text-red-700 hover:text-red-900">✕</button>
          </div>`;

        document.body.appendChild(toast);
        const closeBtn = toast.querySelector('button[aria-label="Close"]');
        const openBtn = toast.querySelector('#openAnomaliesPanel');
        closeBtn.addEventListener('click', () => toast.remove());
        openBtn.addEventListener('click', () => {
          toast.remove();
          // Scroll to the anomalies widget
          document.getElementById('anomaliesContainer')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
          // Refresh the panel view
          loadAnomalies();
        });

        // Auto-hide after 12 seconds
        setTimeout(() => { if (toast && toast.parentNode) toast.remove(); }, 12000);
      }

      refreshBtn.addEventListener('click', loadAnomalies);
      loadAnomalies();
      setInterval(loadAnomalies, 60000); // refresh every minute
      setInterval(pollAnomaliesForAlert, 15000); // alert every 15s if changed
    });
  </script>
  <?php endif; ?>

  <div class="bg-white shadow rounded-xl overflow-hidden">
    <div class="p-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
      <h2 class="text-lg font-bold flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        Reminders & Calendar
      </h2>
      <div class="flex space-x-2">
        <select id="calendarMonthSelector" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50 mr-2">
          <?php foreach($past_months as $month_val => $month_name): ?>
            <option value="<?php echo $month_val; ?>" <?php echo $selected_month === $month_val ? 'selected' : ''; ?>>
              <?php echo $month_name; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button id="calendarViewBtn" class="px-3 py-1 text-sm rounded-md flex items-center focus:outline-none bg-green-600 text-white active">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          Calendar
        </button>
       
      </div>
    </div>
    
    <!-- Calendar View -->
    <div id="calendarView" class="block p-4">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-md font-semibold text-gray-700"><?php echo $selected_month_name; ?></h3>
        <div class="flex space-x-1">
          <button class="p-1 rounded-full hover:bg-gray-100" id="prevMonth">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <button class="p-1 rounded-full hover:bg-gray-100" id="nextMonth">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>
      </div>
      
      <div class="grid grid-cols-7 gap-1">
        <div class="text-xs font-medium text-center text-gray-500 p-1">Sun</div>
        <div class="text-xs font-medium text-center text-gray-500 p-1">Mon</div>
        <div class="text-xs font-medium text-center text-gray-500 p-1">Tue</div>
        <div class="text-xs font-medium text-center text-gray-500 p-1">Wed</div>
        <div class="text-xs font-medium text-center text-gray-500 p-1">Thu</div>
        <div class="text-xs font-medium text-center text-gray-500 p-1">Fri</div>
        <div class="text-xs font-medium text-center text-gray-500 p-1">Sat</div>
        
        <?php
        // Get documents created this month for the calendar
        $cal_query = "SELECT DATE(d.created_at) as doc_date, COUNT(*) as doc_count 
                     FROM documents d 
                     LEFT JOIN document_workflow dw ON d.document_id = dw.document_id
                     WHERE d.created_at BETWEEN ? AND ?
                     AND (d.creator_id = ? OR dw.office_id = (
                         SELECT office_id FROM users WHERE user_id = ?))
                     GROUP BY DATE(d.created_at)";
        $cal_stmt = $conn->prepare($cal_query);
        $cal_stmt->bind_param("ssii", $month_start, $month_end, $user_id, $user_id);
        $cal_stmt->execute();
        $cal_result = $cal_stmt->get_result();
        
        // Create associative array of dates => document counts
        $doc_dates = [];
        while($date_row = $cal_result->fetch_assoc()) {
          $doc_dates[date('j', strtotime($date_row['doc_date']))] = [
            'docs' => $date_row['doc_count'],
            'reminders' => 0
          ];
        }
        
        // Check if reminders table exists before querying
        $table_check = $conn->query("SHOW TABLES LIKE 'reminders'");
        $reminders_table_exists = ($table_check && $table_check->num_rows > 0);
        
        if ($reminders_table_exists) {
          // Get reminders for this month
          $reminder_query = "SELECT DATE(reminder_date) as reminder_date, COUNT(*) as reminder_count 
                           FROM reminders 
                           WHERE user_id = ? 
                           AND reminder_date BETWEEN ? AND ? 
                           GROUP BY DATE(reminder_date)";
          $reminder_stmt = $conn->prepare($reminder_query);
          if ($reminder_stmt) {
            $reminder_stmt->bind_param("iss", $user_id, $month_start, $month_end);
            $reminder_stmt->execute();
            $reminder_result = $reminder_stmt->get_result();
            
            // Add reminders to the dates array
            while($reminder_row = $reminder_result->fetch_assoc()) {
              if (isset($reminder_row['reminder_date'])) {
                $day = date('j', strtotime($reminder_row['reminder_date']));
                $reminder_count = isset($reminder_row['reminder_count']) ? $reminder_row['reminder_count'] : 0;
                
                if (!isset($doc_dates[$day])) {
                  $doc_dates[$day] = [
                    'docs' => 0,
                    'reminders' => $reminder_count
                  ];
                } else {
                  $doc_dates[$day]['reminders'] = $reminder_count;
                }
              }
            }
          }
        } else {
          // Initialize all dates with zero reminders
          foreach ($doc_dates as $day => $counts) {
            if (!isset($doc_dates[$day]['reminders'])) {
              $doc_dates[$day]['reminders'] = 0;
            }
          }
        }
        
        // Generate calendar
        $today = new DateTime();
        $firstDay = new DateTime($selected_month . '-01');
        $lastDay = new DateTime($selected_month . '-' . date('t', strtotime($selected_month . '-01')));
        $startingDay = (int)$firstDay->format('w'); // 0 (Sun) through 6 (Sat)
        $isCurrentMonth = ($selected_month === date('Y-m'));
        
        // Add empty cells for days before the first of the month
        for ($i = 0; $i < $startingDay; $i++) {
          echo '<div class="p-1 text-center text-gray-300"></div>';
        }
        
        // Add cells for each day of the month
        for ($i = 1; $i <= (int)$lastDay->format('d'); $i++) {
          $isToday = $isCurrentMonth && $i === (int)$today->format('d');
          $hasContent = isset($doc_dates[$i]);
          $hasDocuments = $hasContent && $doc_dates[$i]['docs'] > 0;
          $hasReminders = $hasContent && $doc_dates[$i]['reminders'] > 0;
          
          $cellClass = $isToday 
            ? 'border border-blue-400 bg-blue-50' 
            : 'border border-gray-200 hover:bg-gray-50';
          
          $formattedDateForJS = $selected_month . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
          echo '<div class="p-1 h-10 text-center relative cursor-pointer ' . $cellClass . '" data-date="' . $i . '" data-month="' . $selected_month . '" onclick="openCalendarModal(\'' . $formattedDateForJS . '\', ' . $i . ', \'' . $selected_month . '\')" style="pointer-events: auto !important; z-index: 10;">';
          echo '<span class="' . ($isToday ? 'font-bold text-blue-700' : 'text-gray-700') . '" style="pointer-events: none;">' . $i . '</span>';
          
          // Show indicators at the bottom of the cell
          echo '<div class="absolute bottom-1 left-0 right-0 flex justify-center space-x-1" style="pointer-events: none;">';
          
          // Document indicator (green dot)
          if ($hasDocuments) {
            echo '<div class="w-1.5 h-1.5 rounded-full bg-green-500" title="' . $doc_dates[$i]['docs'] . ' Documents"></div>';
          }
          
          // Reminder indicator (blue dot)
          if ($hasReminders) {
            echo '<div class="w-1.5 h-1.5 rounded-full bg-blue-500" title="' . $doc_dates[$i]['reminders'] . ' Reminders"></div>';
          }
          
          echo '</div>';
          echo '</div>';
        }
        ?>
      </div>
      
      <div class="mt-3 flex items-center justify-center text-xs text-gray-500 space-x-4">
        <div class="flex items-center">
          <div class="w-2 h-2 rounded-full bg-green-500 mr-1"></div>
          <span>Documents</span>
        </div>
        <div class="flex items-center">
          <div class="w-2 h-2 rounded-full bg-blue-500 mr-1"></div>
          <span>Reminders</span>
        </div>
      </div>
    </div>
    
    <!-- List View -->
   
    
    <!-- Document info modal -->
    <div id="documentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="if(event.target===this) closeDocumentModal();">
      <div class="bg-white rounded-lg max-w-lg w-full shadow-xl relative" onclick="event.stopPropagation();">
        <div class="p-4 border-b flex justify-between items-center bg-gray-50 relative">
          <h3 class="text-lg font-medium" id="modalDate">Documents for Date</h3>
          <button id="closeModal" class="text-gray-500 hover:text-gray-700 hover:bg-gray-200 rounded p-1" onclick="closeDocumentModal(); event.stopPropagation();" style="cursor: pointer; position: relative; z-index: 1000; pointer-events: auto;">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="p-4 max-h-96 overflow-y-auto">
          <div id="documentsList" class="space-y-3">
            <!-- Documents will be loaded here -->
          </div>
        </div>
      </div>
    </div>
    
  </div>

  <!-- Notifications Container (mirrors dashboard.php) -->
  <div id="notificationsContainer" class="notification-popup hidden fixed top-16 right-4 bg-white border border-gray-200 rounded-lg shadow-lg z-[12000] w-80">
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
      <button id="markAllRead" class="text-sm text-blue-600 hover:text-blue-800">Mark all as read</button>
    </div>
  </div>

  <!-- Calendar Section -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Global function to open calendar modal - MUST be defined before DOMContentLoaded
  // so onclick handlers can call it immediately
  window.openCalendarModal = function(formattedDate, date, month) {
    console.log('openCalendarModal called with:', formattedDate, date, month);
    
    // Get modal elements
    const documentModal = document.getElementById('documentModal');
    const modalDate = document.getElementById('modalDate');
    const documentsList = document.getElementById('documentsList');
    
    if (!documentModal || !modalDate || !documentsList) {
      console.error('Modal elements not found');
      return;
    }
        
        modalDate.textContent = `Content for ${new Date(formattedDate).toLocaleDateString()}`;
        documentsList.innerHTML = '<p class="text-center">Loading...</p>';
        
        // Show modal
        documentModal.classList.remove('hidden');
        
        // Create tabs for documents and reminders
        documentsList.innerHTML = `
          <div class="flex border-b mb-4">
            <button id="docsTab" class="px-4 py-2 border-b-2 border-blue-500 text-blue-500">Documents</button>
            <button id="remindersTab" class="px-4 py-2">Reminders</button>
          </div>
          <div id="docsContent" class="space-y-3"></div>
          <div id="remindersContent" class="space-y-3 hidden"></div>
        `;
        
        const docsTab = document.getElementById('docsTab');
        const remindersTab = document.getElementById('remindersTab');
        const docsContent = document.getElementById('docsContent');
        const remindersContent = document.getElementById('remindersContent');
        
        // Tab switching functionality
        docsTab.addEventListener('click', function() {
          docsTab.classList.add('border-b-2', 'border-blue-500', 'text-blue-500');
          remindersTab.classList.remove('border-b-2', 'border-blue-500', 'text-blue-500');
          docsContent.classList.remove('hidden');
          remindersContent.classList.add('hidden');
        });
        
        remindersTab.addEventListener('click', function() {
          remindersTab.classList.add('border-b-2', 'border-blue-500', 'text-blue-500');
          docsTab.classList.remove('border-b-2', 'border-blue-500', 'text-blue-500');
          remindersContent.classList.remove('hidden');
          docsContent.classList.add('hidden');
        });
        
        // Fetch documents for this date
        docsContent.innerHTML = '<p class="text-center">Loading documents...</p>';
        fetch(`../api/get_documents_by_date.php?date=${formattedDate}`)
          .then(response => {
            if (!response.ok) {
              throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
          })
          .then(data => {
            if (data.error) {
              docsContent.innerHTML = `<p class="text-center text-red-500">Error: ${data.error}</p>`;
              console.error('API Error:', data);
              return;
            }
            
            const documents = data.documents || data;
            
            if (documents.length > 0) {
          docsContent.innerHTML = '';
              documents.forEach(doc => {
                let viewLink = '';
                let statusClass = '';
                
                switch(doc.status) {
              case 'approved': statusClass = 'bg-green-100 text-green-800'; break;
              case 'pending': statusClass = 'bg-yellow-100 text-yellow-800'; break;
              case 'hold': case 'on_hold': statusClass = 'bg-orange-100 text-orange-800'; break;
              case 'rejected': statusClass = 'bg-red-100 text-red-800'; break;
              case 'revision': case 'revision_requested': statusClass = 'bg-purple-100 text-purple-800'; break;
              default: statusClass = 'bg-gray-100 text-gray-800';
            }
            
            viewLink = doc.document_type === 'inbox' 
              ? `dashboard.php?page=approved&id=${doc.document_id}`
              : `dashboard.php?page=documents&id=${doc.document_id}`;
                
                const docItem = document.createElement('div');
                docItem.className = 'border rounded p-3';
                docItem.innerHTML = `
                  <h4 class="font-medium">${doc.title}</h4>
                  <p class="text-sm text-gray-600">${doc.type_name}</p>
                  <div class="flex justify-between mt-2">
                    <span class="text-xs ${statusClass} px-2 py-0.5 rounded">${doc.status.charAt(0).toUpperCase() + doc.status.slice(1)}</span>
                    <a href="${viewLink}" class="text-blue-600 text-sm">View</a>
                  </div>
                `;
                docsContent.appendChild(docItem);
              });
            } else {
              docsContent.innerHTML = '<p class="text-center text-gray-500">No documents found for this date</p>';
            }
          })
          .catch(error => {
            console.error('Error fetching documents:', error);
            docsContent.innerHTML = `<p class="text-center text-red-500">Error loading documents: ${error.message}</p>`;
          });
          
        // Fetch reminders for this date
        remindersContent.innerHTML = '<p class="text-center">Loading reminders...</p>';
        fetch(`../api/get_reminders_by_date.php?date=${formattedDate}`)
          .then(response => {
            if (!response.ok) {
              if (response.status === 404 || response.status === 500) {
                remindersContent.innerHTML = `
                  <div class="text-center p-4">
                    <p class="text-gray-600 mb-3">Reminders feature is not fully set up yet.</p>
                    <a href="../setup_reminders.php" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                      Setup Reminders
                    </a>
                  </div>
                `;
                return null;
              }
              throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
          })
          .then(data => {
            if (!data) return;
            if (!data.success) {
              remindersContent.innerHTML = `<p class="text-center text-red-500">Error: ${data.error || 'Unknown error'}</p>`;
              return;
            }
            
            const reminders = data.reminders;
              const selectedDate = new Date(formattedDate);
              const today = new Date();
              today.setHours(0, 0, 0, 0);
              selectedDate.setHours(0, 0, 0, 0);
              const isPastDate = selectedDate < today;
              
        if (reminders.length > 0) {
          remindersContent.innerHTML = '';
              if (!isPastDate) {
                const addReminderBtn = document.createElement('div');
                addReminderBtn.className = 'mb-4';
                addReminderBtn.innerHTML = `
                  <button id="addReminderBtn" class="w-full py-2 bg-blue-500 text-white rounded hover:bg-blue-600 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Add New Reminder
                  </button>
                `;
                remindersContent.appendChild(addReminderBtn);
              document.getElementById('addReminderBtn').addEventListener('click', function() {
              if (typeof window.showAddReminderForm === 'function') {
                window.showAddReminderForm(formattedDate);
              } else {
                console.error('showAddReminderForm function not found');
              }
            });
          }
          
              reminders.forEach(reminder => {
                const reminderItem = document.createElement('div');
                reminderItem.className = 'border rounded p-3';
                reminderItem.setAttribute('data-reminder-id', reminder.reminder_id);
                const completedClass = reminder.is_completed == 1 ? 'line-through text-gray-500' : '';
                
                // Format reminder time for display
            // Prioritize reminder_time from API (extracted via TIME() function)
            // Then fallback to parsing reminder_date if it contains time
                let reminderTimeDisplay = '';
            
            // Debug: log what we receive
            console.log('Reminder data:', {
              id: reminder.reminder_id,
              reminder_time: reminder.reminder_time,
              reminder_date: reminder.reminder_date,
              title: reminder.title
            });
            
            if (reminder.reminder_time && reminder.reminder_time !== '00:00:00' && reminder.reminder_time !== null && reminder.reminder_time !== '') {
              // Use the time extracted from the database
              try {
                const parts = reminder.reminder_time.split(':');
                if (parts.length >= 2) {
                  let hours = parseInt(parts[0], 10);
                  let minutes = parseInt(parts[1], 10);
                  if (!isNaN(hours) && !isNaN(minutes) && hours >= 0 && hours <= 23 && minutes >= 0 && minutes <= 59) {
                  const hour12 = (hours % 12) || 12;
                  const ampm = hours >= 12 ? 'PM' : 'AM';
                  const minutesFormatted = String(minutes).padStart(2, '0');
                  reminderTimeDisplay = `${hour12}:${minutesFormatted} ${ampm}`;
                  }
                }
              } catch (e) {
                console.error('Error parsing reminder_time:', e, reminder.reminder_time);
              }
            } else if (reminder.reminder_date) {
              // Fallback: parse reminder_date if it contains time component
                  try {
                    let reminderDateStr = reminder.reminder_date.toString().trim();
                console.log('Parsing reminder_date (fallback):', reminderDateStr); // Debug log
                    
                    // Check if it contains time (has a space and colon)
                    if (reminderDateStr.includes(' ') && reminderDateStr.includes(':')) {
                      // Extract just the time part (after the space)
                      const parts = reminderDateStr.split(' ');
                      if (parts.length >= 2) {
                        const timePart = parts[1]; // e.g., "14:30:00" or "14:30"
                        const timeComponents = timePart.split(':');
                        
                        if (timeComponents.length >= 2) {
                          let hours = parseInt(timeComponents[0], 10);
                          let minutes = parseInt(timeComponents[1], 10);
                          
                          // Validate hours and minutes
                          if (!isNaN(hours) && !isNaN(minutes) && hours >= 0 && hours <= 23 && minutes >= 0 && minutes <= 59) {
                            // Format as HH:MM AM/PM
                              const hour12 = hours % 12 || 12;
                              const ampm = hours >= 12 ? 'PM' : 'AM';
                              const minutesFormatted = String(minutes).padStart(2, '0');
                              reminderTimeDisplay = `${hour12}:${minutesFormatted} ${ampm}`;
                            }
                          }
                        }
                      }
                // Don't use Date object fallback as it can cause timezone issues
                  } catch (error) {
                console.error('Error parsing reminder_date:', error, reminder.reminder_date);
                  }
                }
                
                reminderItem.innerHTML = `
                  <div class="flex justify-between items-start mb-2">
                    <div class="flex-1">
                      <h4 class="font-medium ${completedClass}">${reminder.title}</h4>
                      <p class="text-sm text-gray-600 ${completedClass}">${reminder.description || 'No description'}</p>
                    </div>
                ${reminderTimeDisplay ? `<span class="text-xs text-blue-600 font-medium ml-2 whitespace-nowrap">${reminderTimeDisplay}</span>` : ''}
                  </div>
                  <div class="flex justify-between mt-2">
                    <div class="flex items-center">
                      <input type="checkbox" id="complete-${reminder.reminder_id}" class="reminder-checkbox mr-2" ${reminder.is_completed == 1 ? 'checked' : ''}>
                      <label for="complete-${reminder.reminder_id}" class="text-sm">Mark as completed</label>
                    </div>
                    <button class="delete-reminder text-red-500 text-sm" data-id="${reminder.reminder_id}">Delete</button>
                  </div>
                `;
                remindersContent.appendChild(reminderItem);
              });
              
              document.querySelectorAll('.reminder-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                  const reminderId = this.id.split('-')[1];
                  fetch('../api/reminders.php', {
                    method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reminder_id: reminderId, is_completed: this.checked })
              }).then(r => r.json()).then(data => {
                    if (data.success) {
                  const item = document.querySelector(`[data-reminder-id="${reminderId}"]`);
                  const title = item.querySelector('h4');
                  const desc = item.querySelector('p');
                  if (this.checked) {
                        title.classList.add('line-through', 'text-gray-500');
                    desc.classList.add('line-through', 'text-gray-500');
                      } else {
                        title.classList.remove('line-through', 'text-gray-500');
                    desc.classList.remove('line-through', 'text-gray-500');
                      }
                    }
                  });
                });
              });
              
              document.querySelectorAll('.delete-reminder').forEach(button => {
                button.addEventListener('click', function() {
                  if (confirm('Are you sure you want to delete this reminder?')) {
                    fetch('../api/reminders.php', {
                      method: 'DELETE',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ reminder_id: this.getAttribute('data-id') })
                }).then(r => r.json()).then(data => {
                      if (data.success) {
                    document.querySelector(`[data-reminder-id="${this.getAttribute('data-id')}"]`).remove();
                      }
                    });
                  }
                });
              });
            } else {
              remindersContent.innerHTML = `
                <p class="text-center text-gray-500 mb-4">No reminders for this date</p>
            ${isPastDate ? '' : `<button id="addReminderBtn" class="w-full py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Add New Reminder</button>`}
          `;
          const addBtn = document.getElementById('addReminderBtn');
          if (addBtn) addBtn.addEventListener('click', () => {
            if (typeof window.showAddReminderForm === 'function') {
              window.showAddReminderForm(formattedDate);
            } else {
              console.error('showAddReminderForm function not found');
            }
          });
            }
          })
          .catch(error => {
            console.error('Error fetching reminders:', error);
        remindersContent.innerHTML = `<p class="text-center text-red-500">Error: ${error.message}</p>`;
      });
  };

  // Global function to close the document modal
  window.closeDocumentModal = function() {
    const documentModal = document.getElementById('documentModal');
    if (documentModal) {
        documentModal.classList.add('hidden');
      }
  };

  // Global function to show add reminder form - MUST be defined before DOMContentLoaded
  window.showAddReminderForm = function(date) {
    console.log('showAddReminderForm called with:', date);
    
      // Check if the date is in the past
      const selectedDate = new Date(date);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      selectedDate.setHours(0, 0, 0, 0);
      
      if (selectedDate < today) {
        alert('Cannot add reminders for past dates. Please select today or a future date.');
        return;
      }
      
      // Replace reminders content with form
      const remindersContent = document.getElementById('remindersContent');
    if (!remindersContent) {
      console.error('remindersContent element not found');
      return;
    }
      
      // Set default time to current time + 1 hour, or 09:00 if date is in the future
      let defaultTime = '';
      if (selectedDate.getTime() === today.getTime()) {
        // For today, set default to 1 hour from now
        const now = new Date();
        const oneHourLater = new Date(now.getTime() + 60 * 60 * 1000);
        defaultTime = String(oneHourLater.getHours()).padStart(2, '0') + ':' + String(oneHourLater.getMinutes()).padStart(2, '0');
      } else {
        // For future dates, default to 9:00 AM
        defaultTime = '09:00';
      }
      
      remindersContent.innerHTML = `
        <form id="addReminderForm" class="space-y-4">
          <div>
            <label for="reminderTitle" class="block text-sm font-medium text-gray-700">Title</label>
            <input type="text" id="reminderTitle" name="title" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
          </div>
          <div>
            <label for="reminderDescription" class="block text-sm font-medium text-gray-700">Description (optional)</label>
            <textarea id="reminderDescription" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
          </div>
          <div>
            <label for="reminderTime" class="block text-sm font-medium text-gray-700">Reminder Time</label>
            <input type="time" id="reminderTime" name="reminderTime" value="${defaultTime}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
            <p class="mt-1 text-xs text-gray-500">Select the time you want to be reminded</p>
          </div>
          <div class="flex space-x-2">
            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
              Save Reminder
            </button>
            <button type="button" id="cancelAddReminder" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
              Cancel
            </button>
          </div>
        </form>
      `;
      
      // Add event listeners for form submission and cancel
    const form = document.getElementById('addReminderForm');
    if (form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const title = document.getElementById('reminderTitle').value;
        const description = document.getElementById('reminderDescription').value;
        const reminderTime = document.getElementById('reminderTime').value;
        
        // Validate that we're not setting a reminder in the past
        const selectedDate = new Date(date);
        const timeParts = reminderTime.split(':');
        selectedDate.setHours(parseInt(timeParts[0]), parseInt(timeParts[1]), 0, 0);
        
        const now = new Date();
        if (selectedDate < now) {
          alert('Cannot set a reminder for a time that has already passed. Please select a future time.');
          return;
        }
        
        // Combine date and time into datetime string
        const reminderDateTime = date + ' ' + reminderTime + ':00';
        
        // Create reminder via API
        fetch('../api/reminders.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            title: title,
            description: description,
            reminder_date: reminderDateTime
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Show success notification (moved down to avoid overlapping with other notifications)
            const notification = document.createElement('div');
            notification.className = 'fixed top-32 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded shadow-lg z-50 flex justify-between items-center';
            notification.innerHTML = `
              <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <span><strong>Success!</strong> Reminder has been saved.</span>
              </div>
              <button class="text-green-700 hover:text-green-900" onclick="this.parentNode.remove();">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
              </button>
            `;
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
              if (notification.parentNode) {
                notification.remove();
              }
            }, 5000);
            
            // Refresh reminder cache so new reminder is included in real-time checking
            // Try multiple approaches to ensure it works
            console.log('[Reminder] Attempting to refresh cache after creating reminder...');
            
            const refreshCache = () => {
              if (typeof window.refreshReminderCache === 'function') {
                console.log('[Reminder] Using window.refreshReminderCache()');
                window.refreshReminderCache();
                return true;
              } else if (window.reminderSystem && typeof window.reminderSystem.forceRefresh === 'function') {
                console.log('[Reminder] Using window.reminderSystem.forceRefresh()');
                window.reminderSystem.forceRefresh().then(() => {
                  if (typeof window.checkCachedReminders === 'function') {
                    window.checkCachedReminders();
                  }
                });
                return true;
              } else if (typeof window.fetchAndCacheReminders === 'function') {
                console.log('[Reminder] Using window.fetchAndCacheReminders()');
                // Reset last fetch time
                if (window.reminderSystem) {
                  window.reminderSystem.lastReminderFetch = 0;
                }
                window.fetchAndCacheReminders().then(() => {
                  if (typeof window.checkCachedReminders === 'function') {
                    window.checkCachedReminders();
                  }
                });
                return true;
              }
              return false;
            };
            
            // Try immediately
            if (!refreshCache()) {
              // If not available, try after a short delay
              setTimeout(() => {
                if (!refreshCache()) {
                  console.warn('[Reminder] ⚠️ Cache refresh functions not yet available. Will refresh on next automatic check (within 1 minute).');
                  console.warn('[Reminder] 💡 You can manually refresh by running: window.forceReminderRefresh()');
                }
              }, 1000);
            }
            
            // Refresh reminders tab - reload the reminders for this date
            const formattedDate = date.split(' ')[0]; // Get just the date part
            fetch(`../api/get_reminders_by_date.php?date=${formattedDate}`)
              .then(response => response.json())
              .then(reminderData => {
                if (reminderData && reminderData.success) {
                  const reminders = reminderData.reminders || [];
                  const remindersContent = document.getElementById('remindersContent');
                  const selectedDate = new Date(formattedDate);
                  const today = new Date();
                  today.setHours(0, 0, 0, 0);
                  selectedDate.setHours(0, 0, 0, 0);
                  const isPastDate = selectedDate < today;
                  
                  if (reminders.length > 0) {
                    remindersContent.innerHTML = '';
                    if (!isPastDate) {
                      const addReminderBtn = document.createElement('div');
                      addReminderBtn.className = 'mb-4';
                      addReminderBtn.innerHTML = `
                        <button id="addReminderBtn" class="w-full py-2 bg-blue-500 text-white rounded hover:bg-blue-600 flex items-center justify-center">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                          </svg>
                          Add New Reminder
                        </button>
                      `;
                      remindersContent.appendChild(addReminderBtn);
                      document.getElementById('addReminderBtn').addEventListener('click', function() {
                        window.showAddReminderForm(formattedDate);
                      });
                    }
                    
                    reminders.forEach(reminder => {
                      const reminderItem = document.createElement('div');
                      reminderItem.className = 'border rounded p-3';
                      reminderItem.setAttribute('data-reminder-id', reminder.reminder_id);
                      const completedClass = reminder.is_completed == 1 ? 'line-through text-gray-500' : '';
                      
                      // Format reminder time for display
                      let reminderTimeDisplay = '';
                      if (reminder.reminder_date) {
                        try {
                          let reminderDateStr = reminder.reminder_date.toString().trim();
                          console.log('Parsing reminder_date (refresh):', reminderDateStr); // Debug log
                          
                          if (reminderDateStr.includes(' ') && reminderDateStr.includes(':')) {
                            const parts = reminderDateStr.split(' ');
                            if (parts.length >= 2) {
                              const timePart = parts[1];
                              const timeComponents = timePart.split(':');
                              if (timeComponents.length >= 2) {
                                let hours = parseInt(timeComponents[0], 10);
                                let minutes = parseInt(timeComponents[1], 10);
                                if (!isNaN(hours) && !isNaN(minutes) && hours >= 0 && hours <= 23 && minutes >= 0 && minutes <= 59) {
                                  const hour12 = hours % 12 || 12;
                                  const ampm = hours >= 12 ? 'PM' : 'AM';
                                  const minutesFormatted = String(minutes).padStart(2, '0');
                                  reminderTimeDisplay = `${hour12}:${minutesFormatted} ${ampm}`;
                                }
                              }
                            }
                          } else {
                            const reminderDate = new Date(reminderDateStr);
                            if (!isNaN(reminderDate.getTime())) {
                              const hours = reminderDate.getHours();
                              const minutes = reminderDate.getMinutes();
                              const hour12 = hours % 12 || 12;
                              const ampm = hours >= 12 ? 'PM' : 'AM';
                              const minutesFormatted = String(minutes).padStart(2, '0');
                              reminderTimeDisplay = `${hour12}:${minutesFormatted} ${ampm}`;
                            }
                          }
                        } catch (error) {
                          console.error('Error parsing reminder_date:', error, reminder.reminder_date);
                          try {
                            const reminderDate = new Date(reminder.reminder_date);
                            if (!isNaN(reminderDate.getTime())) {
                              const hours = reminderDate.getHours();
                              const minutes = reminderDate.getMinutes();
                              const hour12 = hours % 12 || 12;
                              const ampm = hours >= 12 ? 'PM' : 'AM';
                              const minutesFormatted = String(minutes).padStart(2, '0');
                              reminderTimeDisplay = `${hour12}:${minutesFormatted} ${ampm}`;
                            }
                          } catch (e) {
                            console.error('Fallback date parsing also failed:', e);
                          }
                        }
                      } else if (reminder.reminder_time) {
                        try {
                          const parts = reminder.reminder_time.split(':');
                          if (parts.length >= 2) {
                            let hours = parseInt(parts[0], 10);
                            let minutes = parseInt(parts[1], 10);
                            const hour12 = (hours % 12) || 12;
                            const ampm = hours >= 12 ? 'PM' : 'AM';
                            const minutesFormatted = String(minutes).padStart(2, '0');
                            reminderTimeDisplay = `${hour12}:${minutesFormatted} ${ampm}`;
                          }
                        } catch (e) {
                          console.error('Error parsing reminder_time:', e);
                        }
                      }
                      
                      reminderItem.innerHTML = `
                        <div class="flex justify-between items-start mb-2">
                          <div class="flex-1">
                            <h4 class="font-medium ${completedClass}">${reminder.title}</h4>
                            <p class="text-sm text-gray-600 ${completedClass}">${reminder.description || 'No description'}</p>
                          </div>
                          ${reminderTimeDisplay ? `<span class="text-xs text-blue-600 font-medium ml-2 whitespace-nowrap">${reminderTimeDisplay}</span>` : ''}
                        </div>
                        <div class="flex justify-between mt-2">
                          <div class="flex items-center">
                            <input type="checkbox" id="complete-${reminder.reminder_id}" class="reminder-checkbox mr-2" ${reminder.is_completed == 1 ? 'checked' : ''}>
                            <label for="complete-${reminder.reminder_id}" class="text-sm">Mark as completed</label>
                          </div>
                          <button class="delete-reminder text-red-500 text-sm" data-id="${reminder.reminder_id}">Delete</button>
                        </div>
                      `;
                      remindersContent.appendChild(reminderItem);
                    });
                    
                    // Re-attach event listeners for checkboxes and delete buttons
                    document.querySelectorAll('.reminder-checkbox').forEach(checkbox => {
                      checkbox.addEventListener('change', function() {
                        const reminderId = this.id.split('-')[1];
                        fetch('../api/reminders.php', {
                          method: 'PUT',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify({ reminder_id: reminderId, is_completed: this.checked })
                        }).then(r => r.json()).then(data => {
                          if (data.success) {
                            const item = document.querySelector(`[data-reminder-id="${reminderId}"]`);
                            const title = item.querySelector('h4');
                            const desc = item.querySelector('p');
                            if (this.checked) {
                              title.classList.add('line-through', 'text-gray-500');
                              desc.classList.add('line-through', 'text-gray-500');
                            } else {
                              title.classList.remove('line-through', 'text-gray-500');
                              desc.classList.remove('line-through', 'text-gray-500');
                            }
                          }
                        });
                      });
                    });
                    
                    document.querySelectorAll('.delete-reminder').forEach(button => {
                      button.addEventListener('click', function() {
                        if (confirm('Are you sure you want to delete this reminder?')) {
                          fetch('../api/reminders.php', {
                            method: 'DELETE',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ reminder_id: this.getAttribute('data-id') })
                          }).then(r => r.json()).then(data => {
                            if (data.success) {
                              document.querySelector(`[data-reminder-id="${this.getAttribute('data-id')}"]`).remove();
                            }
                          });
                        }
                      });
                    });
                  } else {
                    remindersContent.innerHTML = `
                      <p class="text-center text-gray-500 mb-4">No reminders for this date</p>
                      ${isPastDate ? '' : `<button id="addReminderBtn" class="w-full py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Add New Reminder</button>`}
                    `;
                    const addBtn = document.getElementById('addReminderBtn');
                    if (addBtn) addBtn.addEventListener('click', () => window.showAddReminderForm(formattedDate));
                  }
                }
              });
          } else {
            alert('Error creating reminder: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error creating reminder:', error);
          alert('Error creating reminder: ' + error.message);
        });
      });
    }
    
    // Cancel button handler - wait for element to exist
    setTimeout(() => {
      const cancelBtn = document.getElementById('cancelAddReminder');
      if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
          // Reload reminders for this date to go back to the list view
          const formattedDate = date.split(' ')[0];
          fetch(`../api/get_reminders_by_date.php?date=${formattedDate}`)
            .then(response => response.json())
            .then(data => {
              if (data && data.success) {
                const reminders = data.reminders || [];
                const remindersContent = document.getElementById('remindersContent');
                if (!remindersContent) return;
                
                const selectedDate = new Date(formattedDate);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                selectedDate.setHours(0, 0, 0, 0);
                const isPastDate = selectedDate < today;
                
                if (reminders.length > 0) {
                  remindersContent.innerHTML = '';
                  if (!isPastDate) {
                    const addReminderBtn = document.createElement('div');
                    addReminderBtn.className = 'mb-4';
                    addReminderBtn.innerHTML = `<button id="addReminderBtn" class="w-full py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Add New Reminder</button>`;
                    remindersContent.appendChild(addReminderBtn);
                    const addBtn = document.getElementById('addReminderBtn');
                    if (addBtn) addBtn.addEventListener('click', () => window.showAddReminderForm(formattedDate));
                  }
                  
                  // Display reminders with time
                  reminders.forEach(reminder => {
                    const reminderItem = document.createElement('div');
                    reminderItem.className = 'border rounded p-3';
                    reminderItem.setAttribute('data-reminder-id', reminder.reminder_id);
                    const completedClass = reminder.is_completed == 1 ? 'line-through text-gray-500' : '';
                    
                    // Format time - prefer reminder_time from API, fallback to parsing reminder_date
                    let reminderTimeDisplay = '';
                    if (reminder.reminder_time) {
                      try {
                        const parts = reminder.reminder_time.split(':');
                        if (parts.length >= 2) {
                          let hours = parseInt(parts[0], 10);
                          let minutes = parseInt(parts[1], 10);
                          const hour12 = (hours % 12) || 12;
                          const ampm = hours >= 12 ? 'PM' : 'AM';
                          const minutesFormatted = String(minutes).padStart(2, '0');
                          reminderTimeDisplay = `${hour12}:${minutesFormatted} ${ampm}`;
                        }
                      } catch (e) {
                        console.error('Error parsing reminder_time:', e);
                      }
                    } else if (reminder.reminder_date) {
                      try {
                        let reminderDateStr = reminder.reminder_date.toString().trim();
                        if (reminderDateStr.includes(' ') && reminderDateStr.includes(':')) {
                          const parts = reminderDateStr.split(' ');
                          if (parts.length >= 2) {
                            const timePart = parts[1];
                            const timeComponents = timePart.split(':');
                            if (timeComponents.length >= 2) {
                              let hours = parseInt(timeComponents[0], 10);
                              let minutes = parseInt(timeComponents[1], 10);
                              if (!isNaN(hours) && !isNaN(minutes)) {
                                const hour12 = hours % 12 || 12;
                                const ampm = hours >= 12 ? 'PM' : 'AM';
                                const minutesFormatted = String(minutes).padStart(2, '0');
                                reminderTimeDisplay = `${hour12}:${minutesFormatted} ${ampm}`;
                              }
                            }
                          }
                        }
                      } catch (error) {
                        console.error('Error parsing reminder_date:', error);
                      }
                    }
                    
                    reminderItem.innerHTML = `
                      <div class="flex justify-between items-start mb-2">
                        <div class="flex-1">
                          <h4 class="font-medium ${completedClass}">${reminder.title}</h4>
                          <p class="text-sm text-gray-600 ${completedClass}">${reminder.description || 'No description'}</p>
                        </div>
                        ${reminderTimeDisplay ? `<span class="text-xs text-blue-600 font-medium ml-2 whitespace-nowrap">${reminderTimeDisplay}</span>` : ''}
                      </div>
                      <div class="flex justify-between mt-2">
                        <div class="flex items-center">
                          <input type="checkbox" id="complete-${reminder.reminder_id}" class="reminder-checkbox mr-2" ${reminder.is_completed == 1 ? 'checked' : ''}>
                          <label for="complete-${reminder.reminder_id}" class="text-sm">Mark as completed</label>
                        </div>
                        <button class="delete-reminder text-red-500 text-sm" data-id="${reminder.reminder_id}">Delete</button>
                      </div>
                    `;
                    remindersContent.appendChild(reminderItem);
                  });
                  
                  // Re-attach event listeners
                  document.querySelectorAll('.reminder-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                      const reminderId = this.id.split('-')[1];
                      fetch('../api/reminders.php', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ reminder_id: reminderId, is_completed: this.checked })
                      }).then(r => r.json()).then(data => {
                        if (data.success) {
                          const item = document.querySelector(`[data-reminder-id="${reminderId}"]`);
                          const title = item.querySelector('h4');
                          const desc = item.querySelector('p');
                          if (this.checked) {
                            title.classList.add('line-through', 'text-gray-500');
                            desc.classList.add('line-through', 'text-gray-500');
                          } else {
                            title.classList.remove('line-through', 'text-gray-500');
                            desc.classList.remove('line-through', 'text-gray-500');
                          }
                        }
                      });
                    });
                  });
                  
                  document.querySelectorAll('.delete-reminder').forEach(button => {
                    button.addEventListener('click', function() {
                      if (confirm('Are you sure you want to delete this reminder?')) {
                        fetch('../api/reminders.php', {
                          method: 'DELETE',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify({ reminder_id: this.getAttribute('data-id') })
                        }).then(r => r.json()).then(data => {
                          if (data.success) {
                            document.querySelector(`[data-reminder-id="${this.getAttribute('data-id')}"]`).remove();
                          }
                        });
                      }
                    });
                  });
                } else {
                  remindersContent.innerHTML = `
                    <p class="text-center text-gray-500 mb-4">No reminders for this date</p>
                    ${isPastDate ? '' : `<button id="addReminderBtn" class="w-full py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Add New Reminder</button>`}
                  `;
                  const addBtn = document.getElementById('addReminderBtn');
                  if (addBtn) addBtn.addEventListener('click', () => window.showAddReminderForm(formattedDate));
                }
              }
            });
        });
      }
    }, 100);
  };

  document.addEventListener('DOMContentLoaded', function() {
    console.log('[Dashboard] DOMContentLoaded - Starting dashboard initialization...');
    
    // Calendar month selector (keep this for the calendar view)
    const calendarMonthSelector = document.getElementById('calendarMonthSelector');
    calendarMonthSelector.addEventListener('change', function() {
      const selectedMonth = this.value;
      window.location.href = `?page=dashboard_content&month=${selectedMonth}&year=${<?php echo $selected_year; ?>}`;
    });
    
    // Previous month button
    document.getElementById('prevMonth').addEventListener('click', function() {
      const currentMonth = '<?php echo $selected_month; ?>';
      const [year, month] = currentMonth.split('-');
      const prevMonth = new Date(year, month - 2, 1);
      const prevMonthStr = prevMonth.getFullYear() + '-' + String(prevMonth.getMonth() + 1).padStart(2, '0');
      window.location.href = `?page=dashboard_content&month=${prevMonthStr}&year=${<?php echo $selected_year; ?>}`;
    });
    
    // Next month button
    document.getElementById('nextMonth').addEventListener('click', function() {
      const currentMonth = '<?php echo $selected_month; ?>';
      const [year, month] = currentMonth.split('-');
      const nextMonth = new Date(year, month, 1);
      const nextMonthStr = nextMonth.getFullYear() + '-' + String(nextMonth.getMonth() + 1).padStart(2, '0');
      
      // Don't allow navigating to future months
      const today = new Date();
      const currentMonthStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0');
      
      if (nextMonthStr <= currentMonthStr) {
        window.location.href = `?page=dashboard_content&month=${nextMonthStr}&year=${<?php echo $selected_year; ?>}`;
      }
    });
    
    const documentModal = document.getElementById('documentModal');
    const closeModal = document.getElementById('closeModal');
    const modalDate = document.getElementById('modalDate');
    const documentsList = document.getElementById('documentsList');
    
    // View toggle functionality
    const calendarViewBtn = document.getElementById('calendarViewBtn');
    const listViewBtn = document.getElementById('listViewBtn');
    const calendarView = document.getElementById('calendarView');
    const listView = document.getElementById('listView');
    
    calendarViewBtn.addEventListener('click', function() {
      calendarView.classList.remove('hidden');
      calendarView.classList.add('block');
      listView.classList.add('hidden');
      listView.classList.remove('block');
      
      calendarViewBtn.classList.add('active', 'bg-green-600', 'text-white');
      calendarViewBtn.classList.remove('bg-gray-200', 'text-gray-700');
      
      listViewBtn.classList.remove('active', 'bg-green-600', 'text-white');
      listViewBtn.classList.add('bg-gray-200', 'text-gray-700');
    });
    
    listViewBtn.addEventListener('click', function() {
      listView.classList.remove('hidden');
      listView.classList.add('block');
      calendarView.classList.add('hidden');
      calendarView.classList.remove('block');
      
      listViewBtn.classList.add('active', 'bg-green-600', 'text-white');
      listViewBtn.classList.remove('bg-gray-200', 'text-gray-700');
      
      calendarViewBtn.classList.remove('active', 'bg-green-600', 'text-white');
      calendarViewBtn.classList.add('bg-gray-200', 'text-gray-700');
    });
    
    // Close modal when clicking close button (also use onclick as backup)
    if (closeModal) {
      closeModal.addEventListener('click', function(e) {
        e.stopPropagation();
        window.closeDocumentModal();
      });
    }
    
    // Close modal when clicking outside
    if (documentModal) {
      documentModal.addEventListener('click', function(e) {
        if (e.target === documentModal) {
          documentModal.classList.add('hidden');
        }
      });
    }
    
    // Request notification permission
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission();
    }
    
    // Track which reminders have already been notified
    const notifiedReminders = new Set();
    
    // Request notification permission on page load
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission().then(permission => {
        console.log('[Reminder Notification] Permission:', permission);
      });
    }
    
    // Function to show reminder modal (prominent popup)
    function showReminderModal(reminder, reminderTimeStr) {
      // Remove any existing reminder modal
      const existingModal = document.getElementById('reminderModal');
      if (existingModal) {
        existingModal.remove();
      }
      
      // Create modal overlay
      const modalOverlay = document.createElement('div');
      modalOverlay.id = 'reminderModal';
      modalOverlay.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center';
      modalOverlay.style.zIndex = '99999';
      modalOverlay.style.position = 'fixed';
      modalOverlay.style.top = '0';
      modalOverlay.style.left = '0';
      modalOverlay.style.right = '0';
      modalOverlay.style.bottom = '0';
      modalOverlay.style.display = 'flex';
      modalOverlay.style.alignItems = 'center';
      modalOverlay.style.justifyContent = 'center';
      modalOverlay.style.animation = 'fadeIn 0.3s ease-in';
      
      // Create modal content
      modalOverlay.innerHTML = `
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all animate-scaleIn" style="animation: scaleIn 0.3s ease-out;">
          <div class="bg-gradient-to-r from-red-500 to-orange-500 text-white p-6 rounded-t-2xl">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="bg-white bg-opacity-20 rounded-full p-3">
                  <svg class="w-8 h-8 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <div>
                  <h2 class="text-2xl font-bold">⏰ Reminder!</h2>
                  <p class="text-sm opacity-90">Time: ${reminderTimeStr}</p>
                </div>
              </div>
            </div>
          </div>
          
          <div class="p-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-3">${reminder.title}</h3>
            ${reminder.description ? `<p class="text-gray-600 mb-4">${reminder.description}</p>` : ''}
            
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
              </svg>
              <span>Scheduled for: ${reminderTimeStr}</span>
            </div>
            
            <div class="flex gap-3">
              <button id="reminderModalDismiss" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 px-4 rounded-lg transition-colors">
                Dismiss
              </button>
              <button id="reminderModalSnooze" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 px-4 rounded-lg transition-colors">
                Snooze 5 min
              </button>
            </div>
          </div>
        </div>
      `;
      
      // Add CSS animations if not already added
      if (!document.getElementById('reminderModalStyles')) {
        const style = document.createElement('style');
        style.id = 'reminderModalStyles';
        style.textContent = `
          @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
          }
          @keyframes scaleIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
          }
          #reminderModal {
            backdrop-filter: blur(4px);
          }
        `;
        document.head.appendChild(style);
      }
      
      // Add to page - ensure it's at the end of body for highest z-index
      document.body.appendChild(modalOverlay);
      
      // Force modal to be visible
      modalOverlay.style.display = 'flex';
      modalOverlay.style.visibility = 'visible';
      modalOverlay.style.opacity = '1';
      
      // Make modal focusable and focus it
      modalOverlay.setAttribute('tabindex', '-1');
      modalOverlay.focus();
      
      // Verify modal was added
      const addedModal = document.getElementById('reminderModal');
      if (!addedModal) {
        console.error('[Reminder Modal] ERROR: Modal was not added to DOM!');
        return;
      }
      console.log('[Reminder Modal] Modal successfully added to DOM');
      
      // Play sound if possible
      try {
        const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBjGH0fPTgjMGHm7A7+OZUhAMT6Tj8LZjHAY4kdfyzHksBSR3x/DdkEAKFF606euoVRQKRp/g8r5sIQYxh9Hz04IzBh5uwO/jmVIQDE+k4/C2YxwGOJHX8sx5LAUkd8fw3ZBAC');
        audio.volume = 0.5;
        audio.play().catch(e => console.log('Could not play sound:', e));
      } catch (e) {
        console.log('Audio not available');
      }
      
      // Event listeners
      document.getElementById('reminderModalDismiss').addEventListener('click', function() {
        closeReminderModal();
      });
      
      document.getElementById('reminderModalSnooze').addEventListener('click', function() {
        // Snooze for 5 minutes - remove from notified set so it will trigger again
        notifiedReminders.delete(reminder.reminder_id);
        closeReminderModal();
        
        // Show confirmation
        const snoozeMsg = document.createElement('div');
        snoozeMsg.className = 'fixed top-4 right-4 bg-blue-500 text-white px-4 py-2 rounded-lg shadow-lg z-[100000]';
        snoozeMsg.textContent = 'Reminder snoozed for 5 minutes';
        document.body.appendChild(snoozeMsg);
        setTimeout(() => snoozeMsg.remove(), 3000);
      });
      
      // Close on overlay click
      modalOverlay.addEventListener('click', function(e) {
        if (e.target === modalOverlay) {
          closeReminderModal();
        }
      });
      
      // Close on Escape key
      const escapeHandler = function(e) {
        if (e.key === 'Escape') {
          closeReminderModal();
          document.removeEventListener('keydown', escapeHandler);
        }
      };
      document.addEventListener('keydown', escapeHandler);
      
      // Auto-close after 2 minutes (user should have seen it by then)
      setTimeout(() => {
        if (document.getElementById('reminderModal')) {
          closeReminderModal();
        }
      }, 120000);
    }
    
    function closeReminderModal() {
      const modal = document.getElementById('reminderModal');
      if (modal) {
        modal.style.animation = 'fadeIn 0.3s ease-in reverse';
        setTimeout(() => modal.remove(), 300);
      }
    }
    
    function showBrowserNotification(reminder) {
      // Show browser notification if permission is granted
      if ('Notification' in window && Notification.permission === 'granted') {
        const reminderTime = reminder.reminder_time || 'now';
        const options = {
          body: `${reminder.description || 'No description'}\nTime: ${reminderTime}`,
          icon: '../assets/images/logo.png',
          badge: '../assets/images/logo.png',
          tag: `reminder-${reminder.reminder_id}`,
          requireInteraction: true,
          vibrate: [200, 100, 200], // Vibrate pattern for mobile devices
          sound: true
        };
        try {
          const notification = new Notification(`⏰ Reminder: ${reminder.title}`, options);
          // Auto-close after 10 seconds
          setTimeout(() => notification.close(), 10000);
        } catch (e) {
          console.error('Error showing browser notification:', e);
        }
      } else if ('Notification' in window && Notification.permission === 'default') {
        // Request permission if not yet requested
        Notification.requestPermission();
      }
    }
    
    // Function to check and show reminders
    function checkAndShowReminders() {
      console.log('[Reminder Check] Checking reminders at', new Date().toLocaleTimeString());
    fetch('../api/get_todays_reminders.php')
      .then(response => {
        if (!response.ok) {
            console.log('Today\'s reminders API returned error:', response.status);
          return null;
        }
        return response.json();
      })
      .then(data => {
          if (!data || !data.success) {
            console.log('[Reminder Check] No reminders data');
            return;
          }
          
          if (data.reminders && data.reminders.length > 0) {
            console.log('[Reminder Check] Found', data.reminders.length, 'reminders');
            // Separate reminders by status
            const now = new Date();
            const upcomingReminders = [];
            const pastReminders = [];
            const dueReminders = []; // Reminders that are due right now (±5 minutes)
            
            data.reminders.forEach(reminder => {
              let reminderDateTime = null;
              let reminderTimeOnly = null;
              
              // Parse reminder date and time - ensure we use local time (same as clock)
              if (reminder.reminder_time) {
                try {
                  const parts = reminder.reminder_time.split(':');
                  if (parts.length >= 2) {
                    reminderTimeOnly = {
                      hours: parseInt(parts[0], 10),
                      minutes: parseInt(parts[1], 10),
                      seconds: parts.length >= 3 ? parseInt(parts[2], 10) : 0
                    };
                  }
                } catch (e) {
                  console.error('Error parsing reminder_time:', e);
                }
              }
              
              if (reminder.reminder_date) {
                try {
                  let reminderDateStr = reminder.reminder_date.toString().trim();
                  
                  // Extract date part (YYYY-MM-DD)
                  let datePart = reminderDateStr.split(' ')[0];
                  
                  // If we have time from reminder_time, use it (more accurate)
                  if (reminderTimeOnly) {
                    // Create date in local timezone explicitly
                    reminderDateTime = new Date();
                    const dateParts = datePart.split('-');
                    reminderDateTime.setFullYear(parseInt(dateParts[0], 10));
                    reminderDateTime.setMonth(parseInt(dateParts[1], 10) - 1); // Month is 0-indexed
                    reminderDateTime.setDate(parseInt(dateParts[2], 10));
                    reminderDateTime.setHours(reminderTimeOnly.hours, reminderTimeOnly.minutes, reminderTimeOnly.seconds || 0, 0);
                  } else if (reminderDateStr.includes(' ') && reminderDateStr.includes(':')) {
                    // Parse datetime string - ensure local time interpretation
                    const datetimeParts = reminderDateStr.split(' ');
                    datePart = datetimeParts[0];
                    const timePart = datetimeParts[1];
                    const timeParts = timePart.split(':');
                    
                    reminderDateTime = new Date();
                    const dateParts = datePart.split('-');
                    reminderDateTime.setFullYear(parseInt(dateParts[0], 10));
                    reminderDateTime.setMonth(parseInt(dateParts[1], 10) - 1);
                    reminderDateTime.setDate(parseInt(dateParts[2], 10));
                    reminderDateTime.setHours(
                      parseInt(timeParts[0], 10),
                      parseInt(timeParts[1], 10),
                      timeParts.length >= 3 ? parseInt(timeParts[2], 10) : 0,
                      0
                    );
                  } else {
                    // Only date, set to midnight local time
                    reminderDateTime = new Date();
                    const dateParts = datePart.split('-');
                    reminderDateTime.setFullYear(parseInt(dateParts[0], 10));
                    reminderDateTime.setMonth(parseInt(dateParts[1], 10) - 1);
                    reminderDateTime.setDate(parseInt(dateParts[2], 10));
                    reminderDateTime.setHours(0, 0, 0, 0);
                  }
                } catch (e) {
                  console.error('Error parsing reminder date:', e);
                }
              }
              
              if (reminderDateTime) {
                // Use the same time as the clock (local time)
                const now = new Date();
                const timeDiff = reminderDateTime.getTime() - now.getTime();
                const minutesDiff = timeDiff / (1000 * 60);
                const secondsDiff = timeDiff / 1000;
                
                // Check if reminder is due - same logic as real-time check
                const isExactMinute = reminderDateTime.getHours() === now.getHours() && 
                                     reminderDateTime.getMinutes() === now.getMinutes();
                const isWithin30Seconds = Math.abs(secondsDiff) <= 30;
                const isWithin5Minutes = Math.abs(minutesDiff) <= 5;
                const shouldNotify = (isExactMinute || isWithin30Seconds || isWithin5Minutes) && 
                                    !notifiedReminders.has(reminder.reminder_id);
                
                if (shouldNotify) {
                  dueReminders.push(reminder);
                  notifiedReminders.add(reminder.reminder_id);
                  const reminderTimeStr = String(reminderDateTime.getHours()).padStart(2, '0') + ':' + 
                                         String(reminderDateTime.getMinutes()).padStart(2, '0') + ':' + 
                                         String(reminderDateTime.getSeconds()).padStart(2, '0');
                  const currentTimeStr = String(now.getHours()).padStart(2, '0') + ':' + 
                                        String(now.getMinutes()).padStart(2, '0') + ':' + 
                                        String(now.getSeconds()).padStart(2, '0');
                  console.log('[Reminder Check] REMINDER DUE NOW:', reminder.title, 'at', reminderTimeStr, 
                             '(Current time:', currentTimeStr + ', Diff:', secondsDiff.toFixed(0), 'seconds)');
                  showReminderModal(reminder, reminderTimeStr);
                  showBrowserNotification(reminder);
                } else if (reminderDateTime <= now) {
                  pastReminders.push(reminder);
                } else {
                  upcomingReminders.push(reminder);
                }
              }
            });
            
            // Show notification if there are due reminders, upcoming reminders, or past reminders
            const remindersToShow = dueReminders.length > 0 ? dueReminders : 
                                   (upcomingReminders.length > 0 ? upcomingReminders : pastReminders);
            
            if (remindersToShow.length > 0) {
              // Check if notification already exists
              const existingNotification = document.getElementById('remindersNotification');
              if (existingNotification) {
                existingNotification.remove();
              }
              
          // Create notification element
          const notification = document.createElement('div');
              notification.id = 'remindersNotification';
              // Make it more prominent if reminders are due
              const notificationClass = dueReminders.length > 0 
                ? 'fixed top-24 right-4 bg-white shadow-2xl rounded-lg p-4 max-w-md z-[10002] border-2 border-red-500 animate-bounce'
                : 'fixed top-24 right-4 bg-white shadow-lg rounded-lg p-4 max-w-md z-[10002]';
              notification.className = notificationClass;
              
              let notificationTitle = '';
              let notificationSubtitle = '';
              let notificationIconBg = 'bg-blue-500';
              
              if (dueReminders.length > 0) {
                notificationTitle = `⏰ Reminder Due Now! (${dueReminders.length})`;
                notificationSubtitle = 'These reminders are due right now';
                notificationIconBg = 'bg-red-500';
              } else if (upcomingReminders.length > 0) {
                notificationTitle = `Reminders for Today (${upcomingReminders.length} upcoming)`;
                notificationSubtitle = 'Upcoming reminders';
              } else {
                notificationTitle = `Reminders for Today (${pastReminders.length} past)`;
                notificationSubtitle = 'Past reminders from today';
              }
              
          notification.innerHTML = `
            <div class="flex justify-between items-start">
              <div class="flex items-center">
                    <div class="${notificationIconBg} rounded-full p-2 mr-3 animate-pulse">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                      <h3 class="font-bold text-gray-900">${notificationTitle}</h3>
                      <p class="text-sm text-gray-600">${notificationSubtitle}</p>
                </div>
              </div>
              <button id="closeNotification" class="text-gray-400 hover:text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
            <div class="mt-3 space-y-2 max-h-60 overflow-y-auto">
                  ${remindersToShow.map(reminder => {
                    // Format time for display
                    let reminderTimeDisplay = '';
                    let isPast = false;
                    
                    if (reminder.reminder_time) {
                      try {
                        const parts = reminder.reminder_time.split(':');
                        if (parts.length >= 2) {
                          let hours = parseInt(parts[0], 10);
                          let minutes = parseInt(parts[1], 10);
                          if (!isNaN(hours) && !isNaN(minutes)) {
                            // Use 24-hour format to match the clock (HH:MM:SS)
                            const seconds = parts.length >= 3 ? parseInt(parts[2], 10) : 0;
                            const hoursFormatted = String(hours).padStart(2, '0');
                            const minutesFormatted = String(minutes).padStart(2, '0');
                            const secondsFormatted = String(seconds).padStart(2, '0');
                            reminderTimeDisplay = `${hoursFormatted}:${minutesFormatted}:${secondsFormatted}`;
                            
                            // Check if time has passed using local time (same as clock)
                            const now = new Date();
                            const reminderTime = new Date();
                            reminderTime.setHours(hours, minutes, seconds || 0, 0);
                            const today = new Date();
                            today.setHours(0, 0, 0, 0);
                            
                            // Parse reminder date in local time
                            let reminderDate = new Date();
                            if (reminder.reminder_date) {
                              const dateStr = reminder.reminder_date.toString().trim();
                              const datePart = dateStr.split(' ')[0];
                              const dateParts = datePart.split('-');
                              reminderDate.setFullYear(parseInt(dateParts[0], 10));
                              reminderDate.setMonth(parseInt(dateParts[1], 10) - 1);
                              reminderDate.setDate(parseInt(dateParts[2], 10));
                              reminderDate.setHours(0, 0, 0, 0);
                            }
                            
                            if (reminderDate.getTime() === today.getTime() && reminderTime < now) {
                              isPast = true;
                            }
                          }
                        }
                      } catch (e) {
                        console.error('Error parsing reminder_time:', e);
                      }
                    } else if (reminder.reminder_date) {
                      try {
                        let reminderDateStr = reminder.reminder_date.toString().trim();
                        if (reminderDateStr.includes(' ') && reminderDateStr.includes(':')) {
                          const parts = reminderDateStr.split(' ');
                          if (parts.length >= 2) {
                            const timePart = parts[1];
                            const timeComponents = timePart.split(':');
                            if (timeComponents.length >= 2) {
                              let hours = parseInt(timeComponents[0], 10);
                              let minutes = parseInt(timeComponents[1], 10);
                              if (!isNaN(hours) && !isNaN(minutes)) {
                                const hour12 = hours % 12 || 12;
                                const ampm = hours >= 12 ? 'PM' : 'AM';
                                const minutesFormatted = String(minutes).padStart(2, '0');
                                reminderTimeDisplay = `${hour12}:${minutesFormatted} ${ampm}`;
                                
                                // Check if time has passed
                                const now = new Date();
                                const reminderTime = new Date();
                                reminderTime.setHours(hours, minutes, 0, 0);
                                if (reminderTime < now) {
                                  isPast = true;
                                }
                              }
                            }
                          }
                        }
                      } catch (e) {
                        console.error('Error parsing time in notification:', e);
                      }
                    }
                    
                    const bgColor = isPast ? 'bg-gray-100' : 'bg-blue-50';
                    const textColor = isPast ? 'text-gray-500' : 'text-gray-900';
                    
                    return `
                    <div class="${bgColor} p-2 rounded">
                      <div class="flex justify-between items-start">
                        <div class="flex-1">
                          <h4 class="font-medium text-sm ${textColor}">${reminder.title} ${isPast ? '<span class="text-xs text-gray-400">(Past)</span>' : ''}</h4>
                  ${reminder.description ? `<p class="text-xs text-gray-600">${reminder.description}</p>` : ''}
                </div>
                        ${reminderTimeDisplay ? `<span class="text-xs ${isPast ? 'text-gray-500' : 'text-blue-600'} font-medium ml-2 whitespace-nowrap">${reminderTimeDisplay}</span>` : ''}
                      </div>
                    </div>
                  `;
                  }).join('')}
            </div>
            <div class="mt-3 flex justify-end">
              <button id="viewAllReminders" class="text-sm text-blue-600 hover:text-blue-800">View All</button>
            </div>
          `;
          
          // Add notification to the page
          document.body.appendChild(notification);
          
          // Add event listeners
          document.getElementById('closeNotification').addEventListener('click', function() {
            notification.remove();
          });
          
          document.getElementById('viewAllReminders').addEventListener('click', function() {
            const today = new Date().getDate();
            const todayCell = document.querySelector(`[data-date="${today}"]`);
            if (todayCell) {
              todayCell.click();
              setTimeout(() => {
                    const remindersTab = document.getElementById('remindersTab');
                    if (remindersTab) remindersTab.click();
              }, 100);
            }
            notification.remove();
          });
          
              // Auto-hide notification after longer time if due, shorter if not
              const autoHideTime = dueReminders.length > 0 ? 60000 : 30000; // 60s for due, 30s for others
          setTimeout(() => {
            if (document.body.contains(notification)) {
              notification.remove();
            }
              }, autoHideTime);
            }
        }
      })
      .catch(error => {
        console.error('Error checking today\'s reminders:', error);
        });
    }
    
    // Real-time reminder checking system
    let cachedReminders = [];
    let lastReminderFetch = 0;
    const REMINDER_FETCH_INTERVAL = 60000; // Fetch fresh reminders every minute
    const REMINDER_CHECK_INTERVAL = 1000; // Check every second for real-time notifications
    
    // Expose variables to window for access from other scopes
    window.reminderSystem = {
      cachedReminders: cachedReminders,
      lastReminderFetch: lastReminderFetch,
      forceRefresh: function() {
        lastReminderFetch = 0;
        if (typeof fetchAndCacheReminders === 'function') {
          return fetchAndCacheReminders();
        } else if (typeof window.fetchAndCacheReminders === 'function') {
          return window.fetchAndCacheReminders();
        }
        return Promise.resolve();
      }
    };
    
    // Lightweight function to check cached reminders against current time
    function checkCachedReminders() {
      const now = new Date();
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      
      const currentTimeStr = String(now.getHours()).padStart(2, '0') + ':' + 
                            String(now.getMinutes()).padStart(2, '0') + ':' + 
                            String(now.getSeconds()).padStart(2, '0');
      
      if (cachedReminders.length === 0) {
        // Log every 30 seconds if no reminders (to show it's running but empty)
        const shouldLog = Math.floor(now.getSeconds() / 30) % 2 === 0 && now.getSeconds() % 30 < 2;
        if (shouldLog) {
          console.log('[Reminder Check] No cached reminders to check at', currentTimeStr);
        }
        return; // No reminders to check
      }
      
      // Log every 10 seconds to show it's running
      const logCheck = now.getSeconds() % 10 === 0;
      if (logCheck) {
        console.log('[Reminder Check] ✓ Checking', cachedReminders.length, 'cached reminders at', currentTimeStr);
        cachedReminders.forEach(r => {
          console.log('  - Reminder:', r.title, '| Time:', r.reminder_time, '| Date:', r.reminder_date);
        });
      }
      
      cachedReminders.forEach(reminder => {
        // Skip completed reminders
        if (reminder.is_completed) {
          return;
        }
        
        let reminderDateTime = null;
        let reminderTimeOnly = null;
        
        // Parse reminder date and time - ensure we use local time (same as clock)
        if (reminder.reminder_time && reminder.reminder_time !== '00:00:00' && reminder.reminder_time !== null) {
          try {
            const parts = reminder.reminder_time.split(':');
            if (parts.length >= 2) {
              const hours = parseInt(parts[0], 10);
              const minutes = parseInt(parts[1], 10);
              // Only use if it's a valid time (not midnight unless explicitly set)
              if (!isNaN(hours) && !isNaN(minutes)) {
                reminderTimeOnly = {
                  hours: hours,
                  minutes: minutes,
                  seconds: parts.length >= 3 ? parseInt(parts[2], 10) : 0
                };
              }
            }
          } catch (e) {
            console.error('[Reminder Check] Error parsing reminder_time:', e, reminder);
          }
        }
        
        if (reminder.reminder_date) {
          try {
            let reminderDateStr = reminder.reminder_date.toString().trim();
            let datePart = reminderDateStr.split(' ')[0];
            
            // Check if reminder is for today
            const reminderDateOnly = new Date();
            const dateParts = datePart.split('-');
            reminderDateOnly.setFullYear(parseInt(dateParts[0], 10));
            reminderDateOnly.setMonth(parseInt(dateParts[1], 10) - 1);
            reminderDateOnly.setDate(parseInt(dateParts[2], 10));
            reminderDateOnly.setHours(0, 0, 0, 0);
            
            // Only check reminders for today
            if (reminderDateOnly.getTime() !== today.getTime()) {
              return; // Skip reminders not for today
            }
            
            // Always try to extract time from reminder_date if it contains time
            if (reminderDateStr.includes(' ') && reminderDateStr.includes(':')) {
              // Extract time from reminder_date string
              const datetimeParts = reminderDateStr.split(' ');
              datePart = datetimeParts[0];
              const timePart = datetimeParts[1];
              const timeParts = timePart.split(':');
              
              if (timeParts.length >= 2) {
                const hours = parseInt(timeParts[0], 10);
                const minutes = parseInt(timeParts[1], 10);
                const seconds = timeParts.length >= 3 ? parseInt(timeParts[2], 10) : 0;
                
                if (!isNaN(hours) && !isNaN(minutes)) {
                  reminderDateTime = new Date();
                  reminderDateTime.setFullYear(parseInt(dateParts[0], 10));
                  reminderDateTime.setMonth(parseInt(dateParts[1], 10) - 1);
                  reminderDateTime.setDate(parseInt(dateParts[2], 10));
                  reminderDateTime.setHours(hours, minutes, seconds, 0);
                }
              }
            } else if (reminderTimeOnly) {
              // Use reminder_time if available and reminder_date doesn't have time
              reminderDateTime = new Date();
              reminderDateTime.setFullYear(parseInt(dateParts[0], 10));
              reminderDateTime.setMonth(parseInt(dateParts[1], 10) - 1);
              reminderDateTime.setDate(parseInt(dateParts[2], 10));
              reminderDateTime.setHours(reminderTimeOnly.hours, reminderTimeOnly.minutes, reminderTimeOnly.seconds || 0, 0);
            } else {
              // No time specified, skip this reminder (all-day reminders without specific time)
              return;
            }
          } catch (e) {
            console.error('[Reminder Check] Error parsing reminder date:', e, reminder);
          }
        }
        
        if (reminderDateTime) {
          const timeDiff = reminderDateTime.getTime() - now.getTime();
          const minutesDiff = timeDiff / (1000 * 60);
          const secondsDiff = timeDiff / 1000;
          
          // Check if reminder is due - multiple conditions for accuracy:
          // 1. Exact minute match (same hour and minute) - most important
          // 2. Within 30 seconds of scheduled time (for precise timing)
          // 3. Within ±5 minutes window (for flexibility)
          const isExactMinute = reminderDateTime.getHours() === now.getHours() && 
                               reminderDateTime.getMinutes() === now.getMinutes();
          const isWithin30Seconds = Math.abs(secondsDiff) <= 30;
          const isWithin5Minutes = Math.abs(minutesDiff) <= 5;
          
          // Always log when we're close to reminder time (for debugging)
          if (isExactMinute || isWithin30Seconds || isWithin5Minutes || Math.abs(secondsDiff) < 60) {
            console.log('[Reminder Check] 🔍 Evaluating reminder:', {
              title: reminder.title,
              reminderTime: String(reminderDateTime.getHours()).padStart(2, '0') + ':' + 
                           String(reminderDateTime.getMinutes()).padStart(2, '0') + ':' + 
                           String(reminderDateTime.getSeconds()).padStart(2, '0'),
              currentTime: currentTimeStr,
              secondsDiff: secondsDiff.toFixed(0),
              minutesDiff: minutesDiff.toFixed(2),
              isExactMinute,
              isWithin30Seconds,
              isWithin5Minutes,
              alreadyNotified: notifiedReminders.has(reminder.reminder_id),
              reminder_id: reminder.reminder_id
            });
          }
          
          // Trigger if: (exact minute) OR (within 30 seconds) OR (within 5 minutes window)
          const isTimeMatch = (isExactMinute || isWithin30Seconds || isWithin5Minutes);
          const alreadyNotified = notifiedReminders.has(reminder.reminder_id);
          const shouldNotify = isTimeMatch && !alreadyNotified;
          
          // Log if time matches but already notified (for debugging)
          if (isTimeMatch && alreadyNotified) {
            console.log('[Reminder Check] ⚠️ Reminder time matched but already notified:', {
              title: reminder.title,
              reminder_id: reminder.reminder_id,
              reminderTime: String(reminderDateTime.getHours()).padStart(2, '0') + ':' + 
                           String(reminderDateTime.getMinutes()).padStart(2, '0') + ':' + 
                           String(reminderDateTime.getSeconds()).padStart(2, '0'),
              currentTime: currentTimeStr,
              secondsDiff: secondsDiff.toFixed(0)
            });
            console.log('[Reminder Check] 💡 Tip: Use window.resetNotifiedReminders() to reset and allow this reminder to trigger again');
          }
          
          if (shouldNotify) {
            notifiedReminders.add(reminder.reminder_id);
            const reminderTimeStr = String(reminderDateTime.getHours()).padStart(2, '0') + ':' + 
                                   String(reminderDateTime.getMinutes()).padStart(2, '0') + ':' + 
                                   String(reminderDateTime.getSeconds()).padStart(2, '0');
            console.log('[Real-time Reminder] ⏰ REMINDER DUE NOW - SHOWING MODAL:', reminder.title, 'at', reminderTimeStr, 
                       '(Current time:', currentTimeStr + ', Diff:', secondsDiff.toFixed(0), 'seconds,', 
                       minutesDiff.toFixed(2), 'minutes)');
            console.log('[Real-time Reminder] Calling showReminderModal with:', reminder);
            
            // Show modal immediately
            try {
              showReminderModal(reminder, reminderTimeStr);
              console.log('[Real-time Reminder] Modal function called successfully');
            } catch (error) {
              console.error('[Real-time Reminder] Error showing modal:', error);
            }
            
            showBrowserNotification(reminder);
            // Trigger full check to update UI
            checkAndShowReminders();
          }
        }
      });
    }
    
    // Fetch reminders from API and cache them
    function fetchAndCacheReminders() {
      const now = Date.now();
      // Only fetch if it's been more than REMINDER_FETCH_INTERVAL since last fetch
      if (now - lastReminderFetch < REMINDER_FETCH_INTERVAL && cachedReminders.length > 0) {
        console.log('[Reminder Cache] Using cached reminders (last fetch:', Math.floor((now - lastReminderFetch) / 1000), 'seconds ago)');
        return Promise.resolve();
      }
      
      lastReminderFetch = now;
      console.log('[Reminder Cache] 🔄 Fetching fresh reminders from API...');
      return fetch('../api/get_todays_reminders.php')
        .then(response => {
          if (!response.ok) {
            console.error('[Reminder Cache] ❌ API returned error:', response.status);
            return null;
          }
          return response.json();
        })
        .then(data => {
          if (data && data.success && data.reminders) {
            const beforeCount = cachedReminders.length;
            cachedReminders = data.reminders.filter(r => !r.is_completed); // Only cache incomplete reminders
            console.log('[Reminder Cache] ✅ Cached', cachedReminders.length, 'incomplete reminders (was', beforeCount, ')');
            
            // Log reminder details for debugging
            if (cachedReminders.length > 0) {
              console.log('[Reminder Cache] 📋 Reminder list:');
              cachedReminders.forEach(reminder => {
                console.log('  - ID:', reminder.reminder_id, '| Title:', reminder.title, '| Date:', reminder.reminder_date, '| Time:', reminder.reminder_time);
              });
            } else {
              console.log('[Reminder Cache] ⚠️ No incomplete reminders found for today');
            }
          } else {
            console.log('[Reminder Cache] ⚠️ No reminders found or API error. Response:', data);
            cachedReminders = [];
          }
        })
        .catch(error => {
          console.error('[Reminder Cache] ❌ Error fetching reminders:', error);
          cachedReminders = [];
        });
    }
    
    // Expose functions to window for global access (after they're defined)
    window.fetchAndCacheReminders = fetchAndCacheReminders;
    window.checkCachedReminders = checkCachedReminders;
    
    // Update window.reminderSystem with actual references
    if (window.reminderSystem) {
      window.reminderSystem.forceRefresh = function() {
        lastReminderFetch = 0;
        return fetchAndCacheReminders();
      };
      // Create getter/setter for cachedReminders
      Object.defineProperty(window.reminderSystem, 'cachedReminders', {
        get: function() { return cachedReminders; },
        set: function(val) { cachedReminders = val; }
      });
    }
    
    // Initialize: fetch reminders and start real-time checking
    console.log('[Reminder System] 🚀 Starting initialization...');
    console.log('[Reminder System] Check interval:', REMINDER_CHECK_INTERVAL, 'ms (every second)');
    console.log('[Reminder System] Fetch interval:', REMINDER_FETCH_INTERVAL, 'ms (every minute)');
    
    try {
      // Force initial fetch (ignore cache)
      lastReminderFetch = 0;
      
      // Start checking immediately (even before fetch completes)
      console.log('[Reminder System] ⚡ Starting real-time check interval...');
      const checkInterval = setInterval(() => {
        try {
          checkCachedReminders();
        } catch (error) {
          console.error('[Reminder System] Error in check interval:', error);
        }
      }, REMINDER_CHECK_INTERVAL);
      
      // Refresh cached reminders every minute
      const fetchInterval = setInterval(() => {
        try {
          console.log('[Reminder System] 🔄 Periodic refresh triggered');
          fetchAndCacheReminders();
        } catch (error) {
          console.error('[Reminder System] Error in fetch interval:', error);
        }
      }, REMINDER_FETCH_INTERVAL);
      
      // Store intervals for potential cleanup
      window.reminderCheckInterval = checkInterval;
      window.reminderFetchInterval = fetchInterval;
      
      console.log('[Reminder System] ✅ Real-time checking intervals are now active!');
      
      // Now fetch initial reminders
      fetchAndCacheReminders().then(() => {
        console.log('[Reminder System] ✅ Initial cache loaded with', cachedReminders.length, 'reminders');
        
        // Check immediately (don't wait)
        console.log('[Reminder System] 🔍 Running immediate reminder check...');
        checkCachedReminders();
        
        // Also check after a short delay to catch any timing issues
        setTimeout(() => {
          console.log('[Reminder System] 🔍 Running follow-up reminder check after 2 seconds...');
          checkCachedReminders();
        }, 2000);
        
        // One more check after 5 seconds to catch reminders that were just created
        setTimeout(() => {
          console.log('[Reminder System] 🔍 Running final reminder check after 5 seconds...');
          checkCachedReminders();
        }, 5000);
      }).catch(error => {
        console.error('[Reminder System] ❌ Failed to fetch initial reminders:', error);
        console.error('[Reminder System] Error details:', error);
        // Continue anyway - intervals are already running
      });
      
      // Verify intervals are running (debug helper)
      setTimeout(() => {
        if (window.reminderCheckInterval) {
          console.log('[Reminder System] ✅ Verification: Check interval is running');
        } else {
          console.error('[Reminder System] ❌ ERROR: Check interval is NOT running!');
        }
        if (window.reminderFetchInterval) {
          console.log('[Reminder System] ✅ Verification: Fetch interval is running');
        } else {
          console.error('[Reminder System] ❌ ERROR: Fetch interval is NOT running!');
        }
      }, 5000);
      
    } catch (error) {
      console.error('[Reminder System] ❌ CRITICAL ERROR during initialization:', error);
      console.error('[Reminder System] Stack trace:', error.stack);
    }
    
    // Expose test functions to window for debugging
    window.testReminderModal = function() {
      const now = new Date();
      const testReminder = {
        reminder_id: 999,
        title: 'Test Reminder',
        description: 'This is a test reminder modal to verify it works',
        reminder_time: String(now.getHours()).padStart(2, '0') + ':' + 
                      String(now.getMinutes()).padStart(2, '0') + ':00',
        reminder_date: now.toISOString().split('T')[0] + ' ' + 
                      String(now.getHours()).padStart(2, '0') + ':' + 
                      String(now.getMinutes()).padStart(2, '0') + ':00',
        is_completed: 0
      };
      const timeStr = String(now.getHours()).padStart(2, '0') + ':' + 
                     String(now.getMinutes()).padStart(2, '0') + ':' + 
                     String(now.getSeconds()).padStart(2, '0');
      console.log('[Test] Showing test reminder modal at', timeStr);
      showReminderModal(testReminder, timeStr);
    };
    
    window.testReminderCheck = function() {
      console.log('[Test] Manually triggering reminder check');
      console.log('[Test] Cached reminders:', cachedReminders);
      checkCachedReminders();
    };
    
    window.debugReminders = function() {
      console.log('[Debug] Reminder System Status:');
      console.log('  - Cached reminders:', cachedReminders.length);
      console.log('  - Cached reminder details:', cachedReminders);
      console.log('  - Notified reminders:', Array.from(notifiedReminders));
      console.log('  - Current time:', new Date().toLocaleString());
      console.log('  - Check interval:', REMINDER_CHECK_INTERVAL, 'ms');
      console.log('  - Fetch interval:', REMINDER_FETCH_INTERVAL, 'ms');
      if (cachedReminders.length > 0) {
        console.log('  - Reminder list:');
        cachedReminders.forEach(r => {
          console.log('    *', r.title, '| ID:', r.reminder_id, '| Date:', r.reminder_date, '| Time:', r.reminder_time);
        });
      }
    };
    
    // Function to reset notified reminders (for testing)
    window.resetNotifiedReminders = function() {
      const beforeCount = notifiedReminders.size;
      notifiedReminders.clear();
      console.log('[Debug] ✅ Cleared', beforeCount, 'notified reminders. All reminders can now trigger again.');
    };
    
    // Function to manually trigger a reminder check
    window.manualReminderCheck = function() {
      console.log('[Debug] 🔍 Manually triggering reminder check...');
      checkCachedReminders();
    };
    
    // Function to force refresh and check
    window.forceReminderRefresh = function() {
      console.log('[Debug] 🔄 Force refreshing reminders...');
      lastReminderFetch = 0;
      fetchAndCacheReminders().then(() => {
        console.log('[Debug] ✅ Refresh complete. Running check...');
        checkCachedReminders();
      });
    };
    
    window.refreshReminderCache = function() {
      console.log('[Reminder Cache] Manually refreshing cache...');
      if (typeof fetchAndCacheReminders === 'function') {
        lastReminderFetch = 0; // Force refresh
        fetchAndCacheReminders().then(() => {
          console.log('[Reminder Cache] Cache refreshed. Now checking reminders...');
          if (typeof checkCachedReminders === 'function') {
            checkCachedReminders();
          }
        });
      } else if (typeof window.fetchAndCacheReminders === 'function') {
        lastReminderFetch = 0;
        window.fetchAndCacheReminders().then(() => {
          console.log('[Reminder Cache] Cache refreshed. Now checking reminders...');
          if (typeof window.checkCachedReminders === 'function') {
            window.checkCachedReminders();
          }
        });
      } else {
        console.error('[Reminder Cache] fetchAndCacheReminders function not available');
      }
    };
    
    // Check reminders on page load (full check with UI update)
    checkAndShowReminders();
    
    // Also do full check periodically to update UI (less frequent)
    setInterval(checkAndShowReminders, 30000); // Full check every 30 seconds for UI updates
    
    // Also check when page becomes visible (if user switches tabs)
    document.addEventListener('visibilitychange', function() {
      if (!document.hidden) {
        console.log('[Reminder Check] Page became visible, refreshing reminders');
        fetchAndCacheReminders();
        checkAndShowReminders();
      }
    });
  });
</script>

<!-- Include document AI features -->
<script src="../assets/js/document-ai-features.js"></script>
<!-- Include reminder notification system -->
<script src="../assets/js/reminder-notifications.js"></script>

<script>
  // Notification functionality
  document.addEventListener('DOMContentLoaded', function() {
    let notificationsContainer = document.getElementById('notificationsContainer');
    const closeNotifications = document.getElementById('closeNotifications');
    const markAllRead = document.getElementById('markAllRead');
    
    function ensureNotificationsContainer() {
      let el = document.getElementById('notificationsContainer');
      if (!el) {
        el = document.createElement('div');
        el.id = 'notificationsContainer';
        el.className = 'notification-popup hidden fixed top-16 right-4 bg-white border border-gray-200 rounded-lg shadow-lg z-[12000] w-80';
        el.innerHTML = `
          <div class="border-b border-gray-200 px-4 py-3 flex justify-between items-center bg-gray-50 rounded-t-lg">
            <h3 class="font-medium text-gray-700">Notifications</h3>
            <button id="closeNotifications" class="text-gray-400 hover:text-gray-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="max-h-96 overflow-y-auto">
            <div id="notificationsList" class="divide-y divide-gray-100"></div>
            <div class="p-4 text-center text-sm text-gray-500" id="noNotifications">No new notifications</div>
          </div>
          <div class="border-t border-gray-200 p-3 text-center bg-gray-50 rounded-b-lg">
            <button id="markAllRead" class="text-sm text-blue-600 hover:text-blue-800">Mark all as read</button>
          </div>`;
        document.body.appendChild(el);
      }
      return el;
    }
    
    // Delegated click handler to work even if the bell is injected later or outside this chunk
    document.addEventListener('click', function(e) {
      const bell = e.target.closest('.notification-bell');
      if (!bell) return;
        e.preventDefault();
        e.stopPropagation();
      notificationsContainer = ensureNotificationsContainer();
        notificationsContainer.classList.toggle('hidden');
        
      // Hide badge when opening
      const badge = bell.querySelector('.notification-badge');
        if (badge && !notificationsContainer.classList.contains('hidden')) {
          badge.classList.add('hidden');
        fetch('../api/mark_notifications_read.php', { method: 'POST', headers: { 'Content-Type': 'application/json' } });
      }
    });
    
    // Close notifications when clicking outside
    document.addEventListener('click', function(e) {
      const bell = e.target.closest('.notification-bell');
      // Don't close if clicking on calendar cells or modal
      const calendarCell = e.target.closest('[data-date]');
      const modal = e.target.closest('#documentModal');
      if (!notificationsContainer || notificationsContainer.classList.contains('hidden')) return;
      if (notificationsContainer.contains(e.target) || bell || calendarCell || modal) return;
        notificationsContainer.classList.add('hidden');
    });
    
    // Close button functionality
    if (closeNotifications) {
      closeNotifications.addEventListener('click', function() {
        const el = document.getElementById('notificationsContainer');
        if (el) el.classList.add('hidden');
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
        
        // Hide the badge
        const badge = document.querySelector('.notification-badge');
        if (badge) {
          badge.classList.add('hidden');
        }
        
        // Mark as read in the backend
        fetch('../api/mark_notifications_read.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          }
        });
      });
    }
    
    // Load notifications
    function loadNotifications() {
      const notificationsList = document.getElementById('notificationsList');
      const noNotifications = document.getElementById('noNotifications');
      
      if (!notificationsList) return;
      
      fetch('../api/get_notifications.php')
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .then(data => {
          if (data.success && data.notifications && data.notifications.length > 0) {
            notificationsList.innerHTML = '';
            noNotifications.classList.add('hidden');
            
            data.notifications.forEach(notification => {
              // Format date
              const date = new Date(notification.created_at);
              const formattedDate = date.toLocaleString();

              // Always deep-link to in-dashboard document view when a document_id exists
              const dest = notification.document_id
                ? `dashboard.php?page=view_document&id=${notification.document_id}`
                : '';

              const wrapper = document.createElement('div');
              wrapper.className = `notification-item ${notification.is_read ? '' : 'unread'}`;

              // Build inner content; wrap title in anchor when dest is available
              const titleHtml = dest
                ? `<a href="${dest}" class="text-sm font-medium text-blue-700 hover:underline">${notification.title}</a>`
                : `<span class="text-sm font-medium text-gray-900">${notification.title}</span>`;

              wrapper.innerHTML = `
                <div class="flex justify-between items-start">
                  <div>
                    ${titleHtml}
                    <p class="text-xs text-gray-500">${notification.message}</p>
                  </div>
                  <span class="text-xs text-gray-400">${formattedDate}</span>
                </div>
              `;

              // If entire row should be clickable too, mirror anchor behavior
              if (dest) {
                wrapper.style.cursor = 'pointer';
                wrapper.addEventListener('click', (e) => {
                  // Avoid double navigation if anchor clicked
                  if (e.target && e.target.tagName && e.target.tagName.toLowerCase() === 'a') return;
                  window.location.href = dest;
                });
              }

              notificationsList.appendChild(wrapper);
            });
            
            // Update badge count
            const unreadCount = data.notifications.filter(n => !n.is_read).length;
            updateNotificationBadge(unreadCount);
          } else {
            notificationsList.innerHTML = '';
            noNotifications.classList.remove('hidden');
            updateNotificationBadge(0);
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
        } else {
          badge.classList.add('hidden');
        }
      }
    }
    
    // Load notifications on page load
    loadNotifications();
    
    // Refresh notifications every minute
    setInterval(loadNotifications, 60000);
  });
</script>

<?php if (!defined('INCLUDED_IN_DASHBOARD')): ?>
</body>
</html>
<?php endif; ?>
