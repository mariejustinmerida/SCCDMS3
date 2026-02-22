<?php
require_once '../includes/config.php';

// Get document counts
$user_id = $_SESSION['user_id'];
$counts = [
  'incoming' => 0,
  'outgoing' => 0,
  'pending' => 0,
  'approved' => 0,
  'revision' => 0,
  'reminders' => 0
];

// Get incoming count - using the new document_workflow table structure
$incoming_query = "SELECT COUNT(*) as count FROM document_workflow dw
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
                  WHERE dw.status = 'current' 
                  AND dw.office_id = (SELECT office_id FROM users WHERE user_id = ?)
                  AND (hold_logs.document_id IS NULL OR 
                       (resume_logs.document_id IS NOT NULL AND resume_logs.latest_resume > hold_logs.latest_hold))";
$incoming_stmt = $conn->prepare($incoming_query);
if ($incoming_stmt === false) {
  // Handle error - likely because we're in the middle of database structure change
  $counts['incoming'] = 0;
} else {
  $incoming_stmt->bind_param("i", $user_id);
  $incoming_stmt->execute();
  $incoming_result = $incoming_stmt->get_result();
  if($incoming_result && $incoming_row = $incoming_result->fetch_assoc()) {
    $counts['incoming'] = $incoming_row['count'];
  }
}

// Get outgoing count - using the new structure
$outgoing_query = "SELECT COUNT(*) as count FROM documents WHERE creator_id = ? AND (status = 'pending' OR status IS NULL OR status = '')";
$outgoing_stmt = $conn->prepare($outgoing_query);
if ($outgoing_stmt === false) {
  // Handle error - likely because we're in the middle of database structure change
  $counts['outgoing'] = 0;
} else {
  $outgoing_stmt->bind_param("i", $user_id);
  $outgoing_stmt->execute();
  $outgoing_result = $outgoing_stmt->get_result();
  if($outgoing_result && $outgoing_row = $outgoing_result->fetch_assoc()) {
    $counts['outgoing'] = $outgoing_row['count'];
  }
}

// Get pending/hold count - using the new structure
$pending_query = "SELECT COUNT(DISTINCT d.document_id) as count FROM documents d 
  JOIN document_types dt ON d.type_id = dt.type_id 
  JOIN users u ON d.creator_id = u.user_id
  LEFT JOIN document_workflow dw ON d.document_id = dw.document_id
  LEFT JOIN offices o ON dw.office_id = o.office_id
  JOIN document_logs dl ON d.document_id = dl.document_id AND dl.action = 'hold'
  LEFT JOIN (
      SELECT document_id, MAX(created_at) as latest_resume
      FROM document_logs
      WHERE action = 'resume'
      GROUP BY document_id
  ) resume_logs ON d.document_id = resume_logs.document_id
  WHERE (d.status = 'on_hold' OR d.status = 'ON_HOLD' OR LOWER(d.status) = 'hold' 
        OR EXISTS (SELECT 1 FROM document_workflow WHERE document_id = d.document_id AND (status = 'on_hold' OR status = 'ON_HOLD')))
  AND (resume_logs.document_id IS NULL OR dl.created_at > resume_logs.latest_resume)
  AND d.status != 'approved'";
$pending_result = $conn->query($pending_query);
$pending_count = ($pending_result && $pending_result->num_rows > 0) ? $pending_result->fetch_assoc()['count'] : 0;

// Get revision count - documents that need revision by this user
$revision_query = "SELECT COUNT(*) as count FROM documents WHERE status = 'revision_requested' AND creator_id = ?";
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

// Get approved count - using the new structure
$approved_query = "SELECT COUNT(*) as count 
                   FROM documents d
                   JOIN document_workflow dw ON d.document_id = dw.document_id
                   WHERE dw.office_id = (SELECT office_id FROM users WHERE user_id = ?)
                   AND d.status = 'approved'";
$approved_stmt = $conn->prepare($approved_query);
if ($approved_stmt === false) {
  // Handle error - likely because we're in the middle of database structure change
  $counts['approved'] = 0;
} else {
  $approved_stmt->bind_param("i", $user_id);
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
$recents_query = "SELECT d.title, d.status, d.created_at, dt.type_name 
                 FROM documents d 
                 JOIN document_types dt ON d.type_id = dt.type_id 
                 LEFT JOIN document_workflow dw ON d.document_id = dw.document_id
                 WHERE d.creator_id = ? OR dw.office_id = (
                   SELECT office_id FROM users WHERE user_id = ?)
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
        <button id="listViewBtn" class="px-3 py-1 text-sm rounded-md flex items-center focus:outline-none bg-gray-200 text-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
          </svg>
          List
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
          
          echo '<div class="p-1 h-10 text-center relative cursor-pointer ' . $cellClass . '" data-date="' . $i . '" data-month="' . $selected_month . '">';
          echo '<span class="' . ($isToday ? 'font-bold text-blue-700' : 'text-gray-700') . '">' . $i . '</span>';
          
          // Show indicators at the bottom of the cell
          echo '<div class="absolute bottom-1 left-0 right-0 flex justify-center space-x-1">';
          
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
    <div id="listView" class="hidden p-4">
      <div class="space-y-4">
        <?php
        // Get documents with dates for list view
        $list_query = "SELECT d.document_id, d.title, d.status, d.created_at, dt.type_name 
                      FROM documents d 
                      JOIN document_types dt ON d.type_id = dt.type_id 
                      LEFT JOIN document_workflow dw ON d.document_id = dw.document_id
                      WHERE d.created_at BETWEEN ? AND ?
                      AND (d.creator_id = ? OR dw.office_id = (
                          SELECT office_id FROM users WHERE user_id = ?))
                      GROUP BY d.document_id
                      ORDER BY d.created_at DESC
                      LIMIT 10";
        $list_stmt = $conn->prepare($list_query);
        $list_stmt->bind_param("ssii", $month_start, $month_end, $user_id, $user_id);
        $list_stmt->execute();
        $list_result = $list_stmt->get_result();
        
        // Group documents by date
        $grouped_docs = [];
        while($doc = $list_result->fetch_assoc()) {
          $date = date('Y-m-d', strtotime($doc['created_at']));
          if(!isset($grouped_docs[$date])) {
            $grouped_docs[$date] = [];
          }
          $grouped_docs[$date][] = $doc;
        }
        
        if(empty($grouped_docs)) {
          echo '<div class="text-center text-gray-500 py-4 bg-gray-50 rounded-lg">No documents found for ' . $selected_month . '</div>';
        } else {
          foreach($grouped_docs as $date => $docs) {
            $formatted_date = date('F j, Y', strtotime($date));
            echo '<div class="border rounded-lg overflow-hidden shadow-sm">';
            echo '<div class="bg-gray-100 px-4 py-2 font-medium flex items-center justify-between">';
            echo '<span>' . $formatted_date . '</span>';
            echo '<span class="bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-full">' . count($docs) . ' docs</span>';
            echo '</div>';
            echo '<div class="divide-y">';
            
            foreach($docs as $doc) {
              $status_class = '';
              switch($doc['status']) {
                case 'approved': $status_class = 'bg-green-100 text-green-800'; break;
                case 'pending': $status_class = 'bg-yellow-100 text-yellow-800'; break;
                case 'hold':
                case 'on_hold': $status_class = 'bg-orange-100 text-orange-800'; break;
                case 'rejected': $status_class = 'bg-red-100 text-red-800'; break;
                case 'revision':
                case 'revision_requested': $status_class = 'bg-purple-100 text-purple-800'; break;
                default: $status_class = 'bg-gray-100 text-gray-800';
              }
              
              echo '<div class="p-3 flex justify-between items-center hover:bg-gray-50">';
              echo '<div>';
              echo '<h4 class="font-medium">' . htmlspecialchars($doc['title']) . '</h4>';
              echo '<div class="flex items-center mt-1">';
              echo '<span class="text-xs text-gray-500 bg-gray-200 px-1.5 py-0.5 rounded mr-2">' . htmlspecialchars($doc['type_name']) . '</span>';
              echo '<span class="inline-block px-2 py-0.5 rounded text-xs ' . $status_class . '">' . ucfirst($doc['status']) . '</span>';
              echo '</div>';
              echo '</div>';
              echo '<div class="document-actions flex flex-wrap gap-2">';
              echo '<a href="view_document.php?id='.$doc['document_id'].'" class="document-view-action text-blue-600 hover:text-blue-800 text-xs bg-blue-50 px-2 py-1 rounded flex items-center">';
              echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
              echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />';
              echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
              echo '</svg>View</a>';
              
              // Add Summary button
              echo '<button onclick="event.stopPropagation(); summarizeDocument('.$doc['document_id'].', \''.addslashes($doc['title']).'\')" class="summary-btn text-amber-600 hover:text-amber-800 text-xs bg-amber-50 px-2 py-1 rounded flex items-center" data-document-id="'.$doc['document_id'].'" data-file-name="'.addslashes($doc['title']).'">';
              echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
              echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />';
              echo '</svg>Summary</button>';
              
              // Add Analyze button
              echo '<button onclick="event.stopPropagation(); analyzeDocument('.$doc['document_id'].', \''.addslashes($doc['title']).'\')" class="analyze-btn text-indigo-600 hover:text-indigo-800 text-xs bg-indigo-50 px-2 py-1 rounded flex items-center" data-document-id="'.$doc['document_id'].'" data-file-name="'.addslashes($doc['title']).'">';
              echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
              echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />';
              echo '</svg>Analyze</button>';
              
              echo '</div>';
              echo '</div>';
              echo '</div>';
            }
            
            echo '</div>';
            echo '</div>';
          }
        }
        ?>
        
        <div class="text-center mt-4">
          <a href="dashboard.php?page=documents" class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800">
            View All Documents
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </a>
        </div>
      </div>
    </div>
    
    <!-- Document info modal -->
    <div id="documentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg max-w-lg w-full shadow-xl">
        <div class="p-4 border-b flex justify-between items-center bg-gray-50">
          <h3 class="text-lg font-medium" id="modalDate">Documents for Date</h3>
          <button id="closeModal" class="text-gray-500 hover:text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
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

  <!-- Notifications Container -->
  <div id="notificationsContainer" class="notification-popup hidden">
    <div class="notification-header">
      <h3>Notifications</h3>
      <button id="closeNotifications" class="text-gray-500 hover:text-gray-700">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>
    <div class="max-h-96 overflow-y-auto">
      <div id="notificationsList" class="divide-y divide-gray-200">
        <!-- Notifications will be loaded here -->
      </div>
      <div class="p-3 text-center text-sm text-gray-500" id="noNotifications">
        No new notifications
      </div>
    </div>
    <div class="p-3 border-t text-center">
      <button id="markAllRead" class="text-sm text-blue-600 hover:text-blue-800">Mark all as read</button>
    </div>
  </div>

  <!-- Calendar Section -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
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
    
    const calendarCells = document.querySelectorAll('[data-date]');
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
    
    calendarCells.forEach(cell => {
      cell.addEventListener('click', function() {
        const date = this.getAttribute('data-date');
        const month = this.getAttribute('data-month');
        
        // Format date for display
        const formattedDate = `${month}-${date.toString().padStart(2, '0')}`;
        
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
            // Check if there's an error in the response
            if (data.error) {
              docsContent.innerHTML = `<p class="text-center text-red-500">Error: ${data.error}</p>`;
              console.error('API Error:', data);
              return;
            }
            
            // Get documents from the response
            const documents = data.documents || data;
            
            if (documents.length > 0) {
              docsContent.innerHTML = ''; // Clear loading message
              documents.forEach(doc => {
                // Determine the link based on document type
                let viewLink = '';
                let statusClass = '';
                
                // Set status class
                switch(doc.status) {
                  case 'approved': 
                    statusClass = 'bg-green-100 text-green-800'; 
                    break;
                  case 'pending': 
                    statusClass = 'bg-yellow-100 text-yellow-800'; 
                    break;
                  case 'hold': 
                  case 'on_hold': 
                    statusClass = 'bg-orange-100 text-orange-800'; 
                    break;
                  case 'rejected': 
                    statusClass = 'bg-red-100 text-red-800'; 
                    break;
                  case 'revision': 
                  case 'revision_requested': 
                    statusClass = 'bg-purple-100 text-purple-800'; 
                    break;
                  default: 
                    statusClass = 'bg-gray-100 text-gray-800';
                }
                
                // Set link based on document type
                if (doc.document_type === 'inbox') {
                  viewLink = `dashboard.php?page=approved&id=${doc.document_id}`;
                } else {
                  viewLink = `dashboard.php?page=documents&id=${doc.document_id}`;
                }
                
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
              // If the API returns an error (like table not found), show setup message
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
            // If data is null (from the previous then block), just return
            if (!data) return;
            
            if (!data.success) {
              remindersContent.innerHTML = `<p class="text-center text-red-500">Error: ${data.error || 'Unknown error'}</p>`;
              return;
            }
            
            const reminders = data.reminders;
            
            if (reminders.length > 0) {
              remindersContent.innerHTML = ''; // Clear loading message
              
              // Add a button to create a new reminder
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
              
              // Display each reminder
              reminders.forEach(reminder => {
                const reminderItem = document.createElement('div');
                reminderItem.className = 'border rounded p-3';
                reminderItem.setAttribute('data-reminder-id', reminder.reminder_id);
                
                const completedClass = reminder.is_completed == 1 ? 'line-through text-gray-500' : '';
                
                reminderItem.innerHTML = `
                  <h4 class="font-medium ${completedClass}">${reminder.title}</h4>
                  <p class="text-sm text-gray-600 ${completedClass}">${reminder.description || 'No description'}</p>
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
              
              // Add event listeners for reminder actions
              document.querySelectorAll('.reminder-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                  const reminderId = this.id.split('-')[1];
                  const isCompleted = this.checked;
                  
                  // Update reminder status
                  fetch('../api/reminders.php', {
                    method: 'PUT',
                    headers: {
                      'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                      reminder_id: reminderId,
                      is_completed: isCompleted
                    })
                  })
                  .then(response => response.json())
                  .then(data => {
                    if (data.success) {
                      // Update UI
                      const reminderItem = document.querySelector(`[data-reminder-id="${reminderId}"]`);
                      const title = reminderItem.querySelector('h4');
                      const description = reminderItem.querySelector('p');
                      
                      if (isCompleted) {
                        title.classList.add('line-through', 'text-gray-500');
                        description.classList.add('line-through', 'text-gray-500');
                      } else {
                        title.classList.remove('line-through', 'text-gray-500');
                        description.classList.remove('line-through', 'text-gray-500');
                      }
                    }
                  });
                });
              });
              
              document.querySelectorAll('.delete-reminder').forEach(button => {
                button.addEventListener('click', function() {
                  const reminderId = this.getAttribute('data-id');
                  
                  // Confirm deletion
                  if (confirm('Are you sure you want to delete this reminder?')) {
                    // Delete reminder
                    fetch('../api/reminders.php', {
                      method: 'DELETE',
                      headers: {
                        'Content-Type': 'application/json'
                      },
                      body: JSON.stringify({
                        reminder_id: reminderId
                      })
                    })
                    .then(response => response.json())
                    .then(data => {
                      if (data.success) {
                        // Remove from UI
                        const reminderItem = document.querySelector(`[data-reminder-id="${reminderId}"]`);
                        reminderItem.remove();
                        
                        // If no reminders left, show message
                        if (document.querySelectorAll('[data-reminder-id]').length === 0) {
                          remindersContent.innerHTML = '<p class="text-center text-gray-500">No reminders for this date</p>';
                        }
                      }
                    });
                  }
                });
              });
              
              // Add new reminder button functionality
              document.getElementById('addReminderBtn').addEventListener('click', function() {
                showAddReminderForm(formattedDate);
              });
              
            } else {
              // No reminders, show add button
              remindersContent.innerHTML = `
                <p class="text-center text-gray-500 mb-4">No reminders for this date</p>
                <button id="addReminderBtn" class="w-full py-2 bg-blue-500 text-white rounded hover:bg-blue-600 flex items-center justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                  </svg>
                  Add New Reminder
                </button>
              `;
              
              // Add new reminder button functionality
              document.getElementById('addReminderBtn').addEventListener('click', function() {
                showAddReminderForm(formattedDate);
              });
            }
          })
          .catch(error => {
            console.error('Error fetching reminders:', error);
            remindersContent.innerHTML = `<p class="text-center text-red-500">Error loading reminders: ${error.message}</p>`;
          });
      });
    });
    
    // Close modal when clicking close button
    closeModal.addEventListener('click', function() {
      documentModal.classList.add('hidden');
    });
    
    // Close modal when clicking outside
    documentModal.addEventListener('click', function(e) {
      if (e.target === documentModal) {
        documentModal.classList.add('hidden');
      }
    });
    
    // Function to show add reminder form
    function showAddReminderForm(date) {
      // Replace reminders content with form
      const remindersContent = document.getElementById('remindersContent');
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
      document.getElementById('addReminderForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const title = document.getElementById('reminderTitle').value;
        const description = document.getElementById('reminderDescription').value;
        
        // Create reminder via API
        fetch('../api/reminders.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            title: title,
            description: description,
            reminder_date: date
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Show success notification
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded shadow-lg z-50 flex justify-between items-center';
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
            
            // Refresh reminders tab
            fetch(`../api/get_reminders_by_date.php?date=${date}`)
              .then(response => response.json())
              .then(reminderData => {
                // Simulate clicking the reminders tab to refresh the view
                document.getElementById('remindersTab').click();
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
      
      document.getElementById('cancelAddReminder').addEventListener('click', function() {
        // Simulate clicking the reminders tab to go back to the list view
        document.getElementById('remindersTab').click();
      });
    }
    
    // Check for today's reminders and show notification if any
    fetch('../api/get_todays_reminders.php')
      .then(response => {
        // Check if response is ok before trying to parse JSON
        if (!response.ok) {
          // If the API returns an error (like table not found), just silently fail
          // This will happen before the reminders table is created
          return null;
        }
        return response.json();
      })
      .then(data => {
        // Skip if data is null (API error) or no success
        if (!data || !data.success) return;
        
        if (data.reminders.length > 0) {
          // Create notification element
          const notification = document.createElement('div');
          notification.className = 'fixed top-4 right-4 bg-white shadow-lg rounded-lg p-4 max-w-md z-50';
          notification.innerHTML = `
            <div class="flex justify-between items-start">
              <div class="flex items-center">
                <div class="bg-blue-500 rounded-full p-2 mr-3">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                  <h3 class="font-bold text-gray-900">Reminders for Today</h3>
                  <p class="text-sm text-gray-600">You have ${data.reminders.length} reminder(s) for today</p>
                </div>
              </div>
              <button id="closeNotification" class="text-gray-400 hover:text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
            <div class="mt-3 space-y-2 max-h-60 overflow-y-auto">
              ${data.reminders.map(reminder => `
                <div class="bg-gray-50 p-2 rounded">
                  <h4 class="font-medium text-sm">${reminder.title}</h4>
                  ${reminder.description ? `<p class="text-xs text-gray-600">${reminder.description}</p>` : ''}
                </div>
              `).join('')}
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
            // Find today's date in the calendar and click it
            const today = new Date().getDate();
            const todayCell = document.querySelector(`[data-date="${today}"]`);
            if (todayCell) {
              todayCell.click();
              // Switch to reminders tab
              setTimeout(() => {
                document.getElementById('remindersTab').click();
              }, 100);
            }
            
            // Remove notification
            notification.remove();
          });
          
          // Auto-hide notification after 10 seconds
          setTimeout(() => {
            if (document.body.contains(notification)) {
              notification.remove();
            }
          }, 10000);
        }
      })
      .catch(error => {
        console.error('Error checking today\'s reminders:', error);
      });
  });
</script>

<!-- Include document AI features -->
<script src="../assets/js/document-ai-features.js"></script>

<script>
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
        
        // If there's a badge, remove it when opening notifications
        const badge = notificationBell.querySelector('.notification-badge');
        if (badge && !notificationsContainer.classList.contains('hidden')) {
          badge.classList.add('hidden');
          
          // Mark as read in the backend
          fetch('../api/mark_notifications_read.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            }
          });
        }
      });
    }
    
    // For dashboard_content.php, we need to check if parent window has notification bell
    // This is needed because dashboard_content.php is loaded in an iframe or as content in dashboard.php
    if (!notificationBell) {
      // Create a local notification bell
      const localBell = document.createElement('div');
      localBell.className = 'notification-bell cursor-pointer relative';
      localBell.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600 hover:text-gray-800" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
      `;
      
      // Add to the page in a fixed position
      localBell.style.position = 'fixed';
      localBell.style.top = '1rem';
      localBell.style.right = '1rem';
      localBell.style.zIndex = '2000';
      localBell.style.backgroundColor = 'white';
      localBell.style.padding = '0.5rem';
      localBell.style.borderRadius = '0.5rem';
      localBell.style.boxShadow = '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)';
      document.body.appendChild(localBell);
      
      // Create a local notifications container if it doesn't exist
      if (!notificationsContainer) {
        const localNotificationsContainer = document.createElement('div');
        localNotificationsContainer.id = 'notificationsContainer';
        localNotificationsContainer.className = 'notification-popup hidden';
        localNotificationsContainer.style.zIndex = '2000';
        localNotificationsContainer.style.backgroundColor = 'white';
        
        // Add notification content
        localNotificationsContainer.innerHTML = `
          <div class="notification-header">
            <h3>Notifications</h3>
            <button id="closeLocalNotifications" class="text-gray-500 hover:text-gray-700">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="max-h-96 overflow-y-auto">
            <div id="localNotificationsList" class="divide-y divide-gray-200">
              <!-- Notifications will be loaded here -->
            </div>
            <div class="p-3 text-center text-sm text-gray-500" id="localNoNotifications">
              No new notifications
            </div>
          </div>
          <div class="p-3 border-t text-center">
            <button id="localMarkAllRead" class="text-sm text-blue-600 hover:text-blue-800">Mark all as read</button>
          </div>
        `;
        
        document.body.appendChild(localNotificationsContainer);
        
        // Add event listeners for the local notifications
        document.getElementById('closeLocalNotifications').addEventListener('click', function() {
          localNotificationsContainer.classList.add('hidden');
        });
        
        document.getElementById('localMarkAllRead').addEventListener('click', function() {
          // Mark all notifications as read in the UI
          const unreadItems = document.querySelectorAll('#localNotificationsList .notification-item.unread');
          unreadItems.forEach(item => {
            item.classList.remove('unread');
          });
          
          // Hide the badge
          const badge = localBell.querySelector('.notification-badge');
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
        
        // Add click event to toggle local notifications
        localBell.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          // Toggle local notifications
          localNotificationsContainer.classList.toggle('hidden');
          
          // If there's a badge, remove it when opening notifications
          const badge = localBell.querySelector('.notification-badge');
          if (badge && !localNotificationsContainer.classList.contains('hidden')) {
            badge.classList.add('hidden');
            
            // Mark as read in the backend
            fetch('../api/mark_notifications_read.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              }
            });
          }
          
          // Load notifications if container is visible
          if (!localNotificationsContainer.classList.contains('hidden')) {
            loadNotificationsToContainer('localNotificationsList', 'localNoNotifications', localBell);
          }
        });
        
        // Close notifications when clicking outside
        document.addEventListener('click', function(e) {
          if (!localNotificationsContainer.classList.contains('hidden') && 
              !localNotificationsContainer.contains(e.target) && 
              !localBell.contains(e.target)) {
            localNotificationsContainer.classList.add('hidden');
          }
        });
      }
      
      // Check for unread notifications and update badge
      loadNotificationsToContainer('localNotificationsList', 'localNoNotifications', localBell);
    }
    
    // Check for unread notifications and update badge
    loadNotificationsToContainer('localNotificationsList', 'localNoNotifications', localBell);
    
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
    
    // Function to load notifications into a specific container
    function loadNotificationsToContainer(listId, noNotificationsId, bellElement) {
      const notificationsList = document.getElementById(listId);
      const noNotifications = document.getElementById(noNotificationsId);
      
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
              const notificationItem = document.createElement('div');
              notificationItem.className = `notification-item ${notification.is_read ? '' : 'unread'}`;
              
              // Format date
              const date = new Date(notification.created_at);
              const formattedDate = date.toLocaleString();
              
              // Determine if we should show a "View Document" link
              let viewDocumentLink = '';
              if (notification.document_id) {
                viewDocumentLink = `
                  <a href="../pages/view_document.php?id=${notification.document_id}" 
                     class="text-blue-600 hover:text-blue-800 text-xs mt-1 inline-block">
                    View Document
                  </a>`;
              }
              
              // Determine status badge if present
              let statusBadge = '';
              if (notification.status) {
                let statusClass = '';
                switch(notification.status.toLowerCase()) {
                  case 'approved': 
                    statusClass = 'bg-green-100 text-green-800'; 
                    break;
                  case 'pending': 
                    statusClass = 'bg-yellow-100 text-yellow-800'; 
                    break;
                  case 'hold': 
                  case 'on_hold': 
                    statusClass = 'bg-orange-100 text-orange-800'; 
                    break;
                  case 'rejected': 
                    statusClass = 'bg-red-100 text-red-800'; 
                    break;
                  case 'revision': 
                  case 'revision_requested': 
                    statusClass = 'bg-purple-100 text-purple-800'; 
                    break;
                  default: 
                    statusClass = 'bg-gray-100 text-gray-800';
                }
                
                statusBadge = `
                  <span class="inline-block px-2 py-0.5 rounded text-xs ${statusClass} mt-1 mr-2">
                    ${notification.status.charAt(0).toUpperCase() + notification.status.slice(1)}
                  </span>`;
              }
              
              notificationItem.innerHTML = `
                <div class="flex justify-between items-start p-3">
                  <div>
                    <p class="text-sm font-medium text-gray-900">${notification.title || 'Notification'}</p>
                    <p class="text-xs text-gray-500">${notification.message || ''}</p>
                    <div class="mt-1">
                      ${statusBadge}
                      ${viewDocumentLink}
                    </div>
                  </div>
                  <span class="text-xs text-gray-400">${formattedDate}</span>
                </div>
              `;
              
              notificationsList.appendChild(notificationItem);
            });
            
            // Update badge count
            const unreadCount = data.notifications.filter(n => !n.is_read).length;
            updateNotificationBadge(unreadCount, bellElement);
          } else {
            notificationsList.innerHTML = '';
            noNotifications.classList.remove('hidden');
            updateNotificationBadge(0, bellElement);
          }
        })
        .catch(error => {
          console.error('Error fetching notifications:', error);
          notificationsList.innerHTML = `<div class="p-3 text-center text-sm text-red-500">Error loading notifications</div>`;
          noNotifications.classList.add('hidden');
        });
    }
    
    // Update notification badge
    function updateNotificationBadge(count, bellElement = null) {
      // If bell element is provided, use it, otherwise use the global one
      const bell = bellElement || document.querySelector('.notification-bell');
      if (!bell) return;
      
      // Find existing badge or create a new one
      let badge = bell.querySelector('.notification-badge');
      
      if (!badge && count > 0) {
        // Create badge if it doesn't exist
        badge = document.createElement('span');
        badge.className = 'notification-badge';
        bell.appendChild(badge);
      }
      
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
    if (notificationsContainer && document.getElementById('notificationsList')) {
      loadNotificationsToContainer('notificationsList', 'noNotifications', notificationBell);
      
      // Refresh notifications every minute
      setInterval(() => {
        loadNotificationsToContainer('notificationsList', 'noNotifications', notificationBell);
      }, 60000);
    }
  });
</script>

</body>
</html>
