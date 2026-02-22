<?php
// Include the logging functions
require_once '../includes/logging.php';

// Check if user is logged in and has President or Super Admin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['President','Super Admin'])) {
    // Redirect to dashboard if not president
    header('Location: ?page=dashboard_content');
    exit;
}

// Get user logs from database
$logs_per_page = isset($_GET['show']) ? (int)$_GET['show'] : 20;
$current_page = isset($_GET['log_page']) ? max(1, (int)$_GET['log_page']) : 1;
$offset = ($current_page - 1) * $logs_per_page;

// Search functionality
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [];
$param_types = '';

if (!empty($search_term)) {
    $search_condition = " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ? OR ul.action LIKE ? OR ul.details LIKE ?)";
    $search_params = ["%$search_term%", "%$search_term%", "%$search_term%", "%$search_term%", "%$search_term%"];
    $param_types = 'sssss';
}

// Date filter
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$date_condition = '';

if (!empty($date_filter)) {
    switch ($date_filter) {
        case 'today':
            $date_condition = " AND DATE(ul.timestamp) = CURDATE()";
            break;
        case 'yesterday':
            $date_condition = " AND DATE(ul.timestamp) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $date_condition = " AND WEEK(ul.timestamp) = WEEK(CURDATE()) AND YEAR(ul.timestamp) = YEAR(CURDATE())";
            break;
        case 'this_month':
            $date_condition = " AND MONTH(ul.timestamp) = MONTH(CURDATE()) AND YEAR(ul.timestamp) = YEAR(CURDATE())";
            break;
        case 'custom':
            if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
                $start_date = $_GET['start_date'];
                $end_date = $_GET['end_date'];
                $date_condition = " AND DATE(ul.timestamp) BETWEEN ? AND ?";
                $search_params[] = $start_date;
                $search_params[] = $end_date;
                $param_types .= 'ss';
            }
            break;
    }
}

// Action filter
$action_filter = isset($_GET['action_filter']) ? $_GET['action_filter'] : '';
$action_condition = '';

if (!empty($action_filter) && $action_filter !== 'all') {
    $action_condition = " AND ul.action = ?";
    $search_params[] = $action_filter;
    $param_types .= 's';
}

// Office filter
$office_filter = isset($_GET['office_filter']) ? (int)$_GET['office_filter'] : 0;
$office_condition = '';

if ($office_filter > 0) {
    $office_condition = " AND ul.office_id = ?";
    $search_params[] = $office_filter;
    $param_types .= 'i';
}

// Document filter
$document_filter = isset($_GET['document_filter']) ? (int)$_GET['document_filter'] : 0;
$document_condition = '';

if ($document_filter > 0) {
    $document_condition = " AND ul.affected_document_id = ?";
    $search_params[] = $document_filter;
    $param_types .= 'i';
}

// Get activity statistics
$stats_sql = "SELECT 
                COUNT(*) as total_activities,
                SUM(CASE WHEN action = 'login' THEN 1 ELSE 0 END) as total_logins,
                SUM(CASE WHEN action = 'logout' THEN 1 ELSE 0 END) as total_logouts,
                COUNT(DISTINCT user_id) as unique_users,
                MAX(timestamp) as latest_activity
              FROM user_logs";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get most active users (top 5)
$active_users_sql = "SELECT 
                        u.user_id, u.username, u.full_name, r.role_name, o.office_name,
                        COUNT(*) as activity_count 
                     FROM user_logs ul
                     JOIN users u ON ul.user_id = u.user_id
                     JOIN roles r ON u.role_id = r.role_id
                     JOIN offices o ON u.office_id = o.office_id
                     GROUP BY u.user_id
                     ORDER BY activity_count DESC
                     LIMIT 5";
$active_users_result = $conn->query($active_users_sql);
$active_users = [];
while ($user = $active_users_result->fetch_assoc()) {
    $active_users[] = $user;
}

// Get recent activities (last 10)
try {
    // First check if the details column exists in user_logs table
    $check_column_sql = "SHOW COLUMNS FROM user_logs LIKE 'details'";
    $check_column_result = $conn->query($check_column_sql);
    $has_details_column = ($check_column_result && $check_column_result->num_rows > 0);
    
    // Construct query based on available columns
    if ($has_details_column) {
        $recent_activities_sql = "SELECT 
                                ul.action, ul.timestamp, ul.details,
                                u.username, u.full_name, r.role_name, o.office_name
                            FROM user_logs ul
                            JOIN users u ON ul.user_id = u.user_id
                            JOIN roles r ON u.role_id = r.role_id
                            JOIN offices o ON u.office_id = o.office_id
                            ORDER BY ul.timestamp DESC
                            LIMIT 10";
    } else {
        $recent_activities_sql = "SELECT 
                                ul.action, ul.timestamp,
                                u.username, u.full_name, r.role_name, o.office_name
                            FROM user_logs ul
                            JOIN users u ON ul.user_id = u.user_id
                            JOIN roles r ON u.role_id = r.role_id
                            JOIN offices o ON u.office_id = o.office_id
                            ORDER BY ul.timestamp DESC
                            LIMIT 10";
    }
    
    $recent_activities_result = $conn->query($recent_activities_sql);
    $recent_activities = [];
    
    if ($recent_activities_result) {
        while ($activity = $recent_activities_result->fetch_assoc()) {
            $recent_activities[] = $activity;
        }
    } else {
        // Fallback to simpler query if the first one fails
        $simple_activities_sql = "SELECT action, timestamp FROM user_logs ORDER BY timestamp DESC LIMIT 10";
        $simple_result = $conn->query($simple_activities_sql);
        
        if ($simple_result) {
            while ($activity = $simple_result->fetch_assoc()) {
                // Add placeholder values for missing fields
                $activity['username'] = 'Unknown';
                $activity['full_name'] = 'Unknown';
                $activity['role_name'] = 'Unknown';
                $activity['office_name'] = 'Unknown';
                if ($has_details_column) {
                    $activity['details'] = '';
                }
                $recent_activities[] = $activity;
            }
        }
    }
} catch (Exception $e) {
    // In case of any error, just create an empty array
    $recent_activities = [];
}

// Get all available actions for filter
try {
    $actions_sql = "SELECT DISTINCT action FROM user_logs ORDER BY action";
    $actions_result = $conn->query($actions_sql);
    $available_actions = [];
    
    if ($actions_result) {
        while ($action = $actions_result->fetch_assoc()) {
            $available_actions[] = $action['action'];
        }
    }
} catch (Exception $e) {
    $available_actions = ['login', 'logout']; // Default actions if query fails
}

// Get all offices for filter
try {
    $offices_sql = "SELECT office_id, office_name FROM offices ORDER BY office_name";
    $offices_result = $conn->query($offices_sql);
    $available_offices = [];
    
    if ($offices_result) {
        while ($office = $offices_result->fetch_assoc()) {
            $available_offices[] = $office;
        }
    }
} catch (Exception $e) {
    $available_offices = []; // Empty array if query fails
}

// Count total logs for pagination
try {
    // Check if the necessary columns exist
    $check_details_sql = "SHOW COLUMNS FROM user_logs LIKE 'details'";
    $check_details_result = $conn->query($check_details_sql);
    $has_details = ($check_details_result && $check_details_result->num_rows > 0);
    
    $check_affected_doc_sql = "SHOW COLUMNS FROM user_logs LIKE 'affected_document_id'";
    $check_affected_doc_result = $conn->query($check_affected_doc_sql);
    $has_affected_doc = ($check_affected_doc_result && $check_affected_doc_result->num_rows > 0);
    
    $check_affected_user_sql = "SHOW COLUMNS FROM user_logs LIKE 'affected_user_id'";
    $check_affected_user_result = $conn->query($check_affected_user_sql);
    $has_affected_user = ($check_affected_user_result && $check_affected_user_result->num_rows > 0);
    
    $check_office_id_sql = "SHOW COLUMNS FROM user_logs LIKE 'office_id'";
    $check_office_id_result = $conn->query($check_office_id_sql);
    $has_office_id = ($check_office_id_result && $check_office_id_result->num_rows > 0);
    
    $check_ip_sql = "SHOW COLUMNS FROM user_logs LIKE 'ip_address'";
    $check_ip_result = $conn->query($check_ip_sql);
    $has_ip = ($check_ip_result && $check_ip_result->num_rows > 0);
    
    // Adjust search condition based on available columns
    if ($has_details) {
        $search_condition = " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ? OR ul.action LIKE ? OR ul.details LIKE ?)";
        $search_params = ["%$search_term%", "%$search_term%", "%$search_term%", "%$search_term%", "%$search_term%"];
        $param_types = 'sssss';
    } else {
        $search_condition = " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ? OR ul.action LIKE ?)";
        $search_params = ["%$search_term%", "%$search_term%", "%$search_term%", "%$search_term%"];
        $param_types = 'ssss';
    }
    
    // Adjust action condition based on available columns
    $action_condition = '';
    if (!empty($action_filter) && $action_filter !== 'all') {
        $action_condition = " AND ul.action = ?";
        $search_params[] = $action_filter;
        $param_types .= 's';
    }
    
    // Adjust office condition based on available columns
    $office_condition = '';
    if ($office_filter > 0 && $has_office_id) {
        $office_condition = " AND ul.office_id = ?";
        $search_params[] = $office_filter;
        $param_types .= 'i';
    }
    
    // Adjust document condition based on available columns
    $document_condition = '';
    if ($document_filter > 0 && $has_affected_doc) {
        $document_condition = " AND ul.affected_document_id = ?";
        $search_params[] = $document_filter;
        $param_types .= 'i';
    }
    
    $count_sql = "SELECT COUNT(*) as total FROM user_logs ul 
                JOIN users u ON ul.user_id = u.user_id 
                WHERE 1=1" . $search_condition . $date_condition . $action_condition . $office_condition . $document_condition;
    
    $count_stmt = $conn->prepare($count_sql);
    if (!empty($search_params)) {
        $count_stmt->bind_param($param_types, ...$search_params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_logs = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_logs / $logs_per_page);
    
    // Build the main logs query based on available columns
    $select_fields = "ul.log_id, ul.user_id, ul.action, ul.timestamp";
    $join_clauses = "FROM user_logs ul 
                    JOIN users u ON ul.user_id = u.user_id 
                    JOIN roles r ON u.role_id = r.role_id 
                    JOIN offices o ON u.office_id = o.office_id";
    
    if ($has_details) {
        $select_fields .= ", ul.details";
    }
    
    if ($has_ip) {
        $select_fields .= ", ul.ip_address";
    }
    
    if ($has_affected_doc) {
        $select_fields .= ", ul.affected_document_id";
        $join_clauses .= " LEFT JOIN documents d ON ul.affected_document_id = d.document_id";
    }
    
    if ($has_affected_user) {
        $select_fields .= ", ul.affected_user_id";
        $join_clauses .= " LEFT JOIN users au ON ul.affected_user_id = au.user_id";
    }
    
    if ($has_office_id) {
        $select_fields .= ", ul.office_id";
    }
    
    $select_fields .= ", u.username, u.email, u.full_name, r.role_name, o.office_name";
    
    if ($has_affected_doc) {
        $select_fields .= ", d.title as document_title";
    }
    
    if ($has_affected_user) {
        $select_fields .= ", au.username as affected_username, au.full_name as affected_full_name";
    }
    
    if ($has_office_id) {
        $select_fields .= ", o.office_name as affected_office_name";
    }
    
    $logs_sql = "SELECT $select_fields 
                $join_clauses
                WHERE 1=1" . $search_condition . $date_condition . $action_condition . $office_condition . $document_condition . " 
                ORDER BY ul.timestamp DESC 
                LIMIT ?, ?";
    
    $logs_stmt = $conn->prepare($logs_sql);
    $search_params[] = $offset;
    $search_params[] = $logs_per_page;
    $param_types .= 'ii';
    $logs_stmt->bind_param($param_types, ...$search_params);
    $logs_stmt->execute();
    $logs_result = $logs_stmt->get_result();
    $logs = [];
    while ($log = $logs_result->fetch_assoc()) {
        $logs[] = $log;
    }
} catch (Exception $e) {
    // If any error occurs, set default values
    $total_logs = 0;
    $total_pages = 1;
    $logs = [];
}
?>

<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">User Activity Logs</h1>
        <div class="flex justify-between items-center">
            <p class="text-gray-600">View login and logout activities of all users</p>
            <a href="?page=generate_test_logouts" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Generate Test Logout Records
            </a>
        </div>
    </div>

    <!-- Office Approvals Section -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-4 border-b bg-green-50">
            <h2 class="text-lg font-semibold text-green-800">Document Approvals by Office</h2>
            <p class="text-sm text-gray-600">View which offices have approved documents and their verification codes</p>
        </div>
        
        <div class="p-4">
            <form action="" method="GET" class="mb-4">
                <input type="hidden" name="page" value="user_logs">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Office Filter for Approvals -->
                    <div>
                        <label for="approval_office_filter" class="block text-sm font-medium text-gray-700 mb-1">Office</label>
                        <select id="approval_office_filter" name="approval_office_filter" 
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="0">All Offices</option>
                            <?php foreach ($available_offices as $office): ?>
                                <option value="<?php echo $office['office_id']; ?>" <?php echo (isset($_GET['approval_office_filter']) && $_GET['approval_office_filter'] == $office['office_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($office['office_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Time Period -->
                    <div>
                        <label for="approval_time_filter" class="block text-sm font-medium text-gray-700 mb-1">Time Period</label>
                        <select id="approval_time_filter" name="approval_time_filter" 
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="all" <?php echo (!isset($_GET['approval_time_filter']) || $_GET['approval_time_filter'] == 'all') ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo (isset($_GET['approval_time_filter']) && $_GET['approval_time_filter'] == 'today') ? 'selected' : ''; ?>>Today</option>
                            <option value="this_week" <?php echo (isset($_GET['approval_time_filter']) && $_GET['approval_time_filter'] == 'this_week') ? 'selected' : ''; ?>>This Week</option>
                            <option value="this_month" <?php echo (isset($_GET['approval_time_filter']) && $_GET['approval_time_filter'] == 'this_month') ? 'selected' : ''; ?>>This Month</option>
                            <option value="last_month" <?php echo (isset($_GET['approval_time_filter']) && $_GET['approval_time_filter'] == 'last_month') ? 'selected' : ''; ?>>Last Month</option>
                        </select>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="flex items-end">
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                            <i class="fas fa-filter mr-1"></i> Filter Approvals
                        </button>
                    </div>
                </div>
            </form>

            <?php
            // Get approvals based on filters
            $approval_office_filter = isset($_GET['approval_office_filter']) ? (int)$_GET['approval_office_filter'] : 0;
            $approval_time_filter = isset($_GET['approval_time_filter']) ? $_GET['approval_time_filter'] : 'all';
            
            // Build time condition
            $time_condition = '';
            switch ($approval_time_filter) {
                case 'today':
                    $time_condition = " AND DATE(dw.completed_at) = CURDATE()";
                    $sv_time_condition = " AND DATE(sv.created_at) = CURDATE()";
                    break;
                case 'this_week':
                    $time_condition = " AND WEEK(dw.completed_at) = WEEK(CURDATE()) AND YEAR(dw.completed_at) = YEAR(CURDATE())";
                    $sv_time_condition = " AND WEEK(sv.created_at) = WEEK(CURDATE()) AND YEAR(sv.created_at) = YEAR(CURDATE())";
                    break;
                case 'this_month':
                    $time_condition = " AND MONTH(dw.completed_at) = MONTH(CURDATE()) AND YEAR(dw.completed_at) = YEAR(CURDATE())";
                    $sv_time_condition = " AND MONTH(sv.created_at) = MONTH(CURDATE()) AND YEAR(sv.created_at) = YEAR(CURDATE())";
                    break;
                case 'last_month':
                    $time_condition = " AND MONTH(dw.completed_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(dw.completed_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
                    $sv_time_condition = " AND MONTH(sv.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(sv.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
                    break;
                default:
                    $sv_time_condition = '';
                    break;
            }
            
            // Build office condition
            $office_condition = '';
            $sv_office_condition = '';
            if ($approval_office_filter > 0) {
                $office_condition = " AND dw.office_id = " . $approval_office_filter;
                $sv_office_condition = " AND sv.office_id = " . $approval_office_filter;
            }
            
            // Define a combined SQL query for both workflow and simple approvals
            $approvals_query = "";
            
            // First check if the document_logs table exists and has necessary approval data
            $logs_check = $conn->query("SHOW TABLES LIKE 'document_logs'");
            $has_logs_table = ($logs_check && $logs_check->num_rows > 0);
            
            if ($has_logs_table) {
                // Check for qr_signed and approve actions in document_logs
                $logs_query = "SELECT dl.document_id, d.title, d.verification_code, 
                                d.file_path, d.status, 
                               dl.created_at as completed_at, dl.user_id as completed_by, dl.details as remarks, 0 as step_order,
                               o.office_name, u.username, u.full_name, 
                               CASE 
                                 WHEN dl.action = 'qr_signed' THEN 'QR Signature'
                                 WHEN dl.action = 'approve_document' THEN 'Approval'
                                 ELSE dl.action
                               END as approval_type
                            FROM document_logs dl
                            JOIN documents d ON dl.document_id = d.document_id
                            JOIN users u ON dl.user_id = u.user_id
                            JOIN offices o ON u.office_id = o.office_id
                            WHERE (dl.action = 'qr_signed' OR dl.action = 'approve_document')";
                            
                if ($approval_office_filter > 0) {
                    $logs_query .= " AND o.office_id = " . $approval_office_filter;
                }
                
                if (!empty($time_condition)) {
                    // Convert the time condition to work with document_logs.created_at
                    $logs_time_condition = str_replace('dw.completed_at', 'dl.created_at', $time_condition);
                    $logs_query .= $logs_time_condition;
                }
            }
            
            // First check if document_workflow has a verification_code column
            $check_workflow_verification = $conn->query("SHOW COLUMNS FROM document_workflow LIKE 'verification_code'");
            $has_workflow_verification = ($check_workflow_verification && $check_workflow_verification->num_rows > 0);
            
            // Check if documents table has verification_code column
            $doc_verification_check = $conn->query("SHOW COLUMNS FROM documents LIKE 'verification_code'");
            $has_doc_verification = ($doc_verification_check && $doc_verification_check->num_rows > 0);
            
            // First part of the query - workflow approvals
            $workflow_query = "SELECT d.document_id, d.title, ";
            
            if ($has_workflow_verification && $has_doc_verification) {
                $workflow_query .= "COALESCE(d.verification_code, dw.verification_code, 'N/A') as verification_code, ";
            } elseif ($has_doc_verification) {
                $workflow_query .= "COALESCE(d.verification_code, 'N/A') as verification_code, ";
            } elseif ($has_workflow_verification) {
                $workflow_query .= "COALESCE(dw.verification_code, 'N/A') as verification_code, ";
            } else {
                $workflow_query .= "'N/A' as verification_code, ";
            }
                              
            $workflow_query .= "d.file_path, d.status";
                              
            // Check if comments column exists in document_workflow (old tables might have 'remarks' instead)
            $check_comments_column = $conn->query("SHOW COLUMNS FROM document_workflow LIKE 'comments'");
            $has_comments_column = ($check_comments_column && $check_comments_column->num_rows > 0);
            
            $check_remarks_column = $conn->query("SHOW COLUMNS FROM document_workflow LIKE 'remarks'");
            $has_remarks_column = ($check_remarks_column && $check_remarks_column->num_rows > 0);
            
            if ($has_comments_column) {
                $remarks_column = "dw.comments";
            } elseif ($has_remarks_column) {
                $remarks_column = "dw.remarks";
            } else {
                $remarks_column = "''";
            }
            
            $workflow_query .= ", dw.completed_at, u.user_id as completed_by, $remarks_column AS remarks, dw.step_order,
                              o.office_name, u.username, u.full_name, 'Workflow' as approval_type
                           FROM document_workflow dw
                           JOIN documents d ON dw.document_id = d.document_id
                           JOIN offices o ON dw.office_id = o.office_id
                           LEFT JOIN users u ON dw.user_id = u.user_id
                           WHERE dw.status = 'COMPLETED'" . $office_condition . $time_condition;
            
            // Check if simple_verifications table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'simple_verifications'");
            $has_simple_verifications = ($table_check && $table_check->num_rows > 0);
            
            // Combine the appropriate queries
            if ($has_logs_table && $has_simple_verifications) {
                // Include both document_logs and simple_verifications
                $simple_query = "SELECT d.document_id, d.title, sv.verification_code, 
                                d.file_path, d.status,
                                sv.created_at as completed_at, sv.user_id as completed_by, '' as remarks, 0 as step_order,
                               o.office_name, u.username, u.full_name, 'Simple Verification' as approval_type
                         FROM simple_verifications sv
                         JOIN documents d ON sv.document_id = d.document_id
                            JOIN offices o ON CAST(sv.office_id AS UNSIGNED) = o.office_id
                         LEFT JOIN users u ON sv.user_id = u.user_id
                         WHERE 1=1" . $sv_office_condition . $sv_time_condition;
                
                // Instead of using UNION ALL, let's use a subquery approach to prioritize records with verification codes
                $approvals_query = "
                    SELECT t.* FROM (
                        $workflow_query
                        UNION ALL
                        $simple_query
                        UNION ALL
                        $logs_query
                    ) t
                    JOIN (
                        SELECT document_id, office_name, 
                               MAX(CASE WHEN verification_code != 'N/A' THEN 1 ELSE 0 END) as has_code,
                               MAX(CASE WHEN username IS NOT NULL THEN 1 ELSE 0 END) as has_user
                        FROM (
                            $workflow_query
                            UNION ALL
                            $simple_query
                            UNION ALL
                            $logs_query
                        ) x
                        GROUP BY document_id, office_name
                    ) b ON t.document_id = b.document_id AND t.office_name = b.office_name
                    WHERE 
                        (t.verification_code != 'N/A' AND b.has_code = 1) 
                        OR (b.has_code = 0 AND t.username IS NOT NULL AND b.has_user = 1)
                        OR (b.has_code = 0 AND b.has_user = 0)
                    ORDER BY t.completed_at DESC
                ";
            } elseif ($has_simple_verifications) {
                // Include workflow and simple_verifications
                $simple_query = "SELECT d.document_id, d.title, sv.verification_code, 
                               d.file_path, d.status,
                               sv.created_at as completed_at, sv.user_id as completed_by, '' as remarks, 0 as step_order,
                               o.office_name, u.username, u.full_name, 'Simple Verification' as approval_type
                            FROM simple_verifications sv
                            JOIN documents d ON sv.document_id = d.document_id
                            JOIN offices o ON CAST(sv.office_id AS UNSIGNED) = o.office_id
                            LEFT JOIN users u ON sv.user_id = u.user_id
                            WHERE 1=1" . $sv_office_condition . $sv_time_condition;
                
                $approvals_query = "
                    SELECT t.* FROM (
                        $workflow_query
                        UNION ALL
                        $simple_query
                    ) t
                    JOIN (
                        SELECT document_id, office_name, 
                               MAX(CASE WHEN verification_code != 'N/A' THEN 1 ELSE 0 END) as has_code,
                               MAX(CASE WHEN username IS NOT NULL THEN 1 ELSE 0 END) as has_user
                        FROM (
                            $workflow_query
                            UNION ALL
                            $simple_query
                        ) x
                        GROUP BY document_id, office_name
                    ) b ON t.document_id = b.document_id AND t.office_name = b.office_name
                    WHERE 
                        (t.verification_code != 'N/A' AND b.has_code = 1) 
                        OR (b.has_code = 0 AND t.username IS NOT NULL AND b.has_user = 1)
                        OR (b.has_code = 0 AND b.has_user = 0)
                    ORDER BY t.completed_at DESC
                ";
            } elseif ($has_logs_table) {
                // Include workflow and document_logs
                $approvals_query = "
                    SELECT t.* FROM (
                        $workflow_query
                        UNION ALL
                        $logs_query
                    ) t
                    JOIN (
                        SELECT document_id, office_name, 
                               MAX(CASE WHEN verification_code != 'N/A' THEN 1 ELSE 0 END) as has_code,
                               MAX(CASE WHEN username IS NOT NULL THEN 1 ELSE 0 END) as has_user
                        FROM (
                            $workflow_query
                            UNION ALL
                            $logs_query
                        ) x
                        GROUP BY document_id, office_name
                    ) b ON t.document_id = b.document_id AND t.office_name = b.office_name
                    WHERE 
                        (t.verification_code != 'N/A' AND b.has_code = 1) 
                        OR (b.has_code = 0 AND t.username IS NOT NULL AND b.has_user = 1)
                        OR (b.has_code = 0 AND b.has_user = 0)
                    ORDER BY t.completed_at DESC
                ";
            } else {
                // Only use workflow approvals if other tables don't exist
                $approvals_query = $workflow_query . " ORDER BY completed_at DESC";
            }
            
            // Add LIMIT to the final query
            $approvals_query .= " LIMIT 30";
            
            // Execute the query
            try {
                // Debug the query for admins
                $is_debugging = true; // Set to false in production
                
                if ($is_debugging && (isset($_SESSION['role']) && $_SESSION['role'] === 'President')) {
                    echo '<div class="bg-gray-100 p-4 mb-4 rounded text-xs overflow-auto" style="max-height: 200px;">';
                    echo '<p class="font-bold mb-2">Debug Information (Only visible to President)</p>';
                    echo '<p><strong>Query:</strong> ' . nl2br(htmlspecialchars($approvals_query)) . '</p>';
                    
                    // Check which tables we're using
                    echo '<p><strong>Tables Available:</strong> ';
                    echo 'document_workflow, ';
                    echo $has_simple_verifications ? 'simple_verifications, ' : 'NO simple_verifications, ';
                    echo $has_logs_table ? 'document_logs' : 'NO document_logs';
                    echo '</p>';
                    
                    // Check if documents table has verification_code column
                    $doc_verification_check = $conn->query("SHOW COLUMNS FROM documents LIKE 'verification_code'");
                    $has_doc_verification = ($doc_verification_check && $doc_verification_check->num_rows > 0);
                    echo '<p><strong>Documents Table:</strong> ' . ($has_doc_verification ? 'Has verification_code column' : 'NO verification_code column') . '</p>';
                    
                    echo '</div>';
                }
                
                $approvals_result = $conn->query($approvals_query);
                
                if ($approvals_result && $approvals_result->num_rows > 0) {
            ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Office</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved By</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Verification Code</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($approval = $approvals_result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 rounded-md 
                                                <?php
                                                $ext = pathinfo($approval['file_path'] ?? '', PATHINFO_EXTENSION);
                                                switch(strtolower($ext)) {
                                                    case 'pdf': echo 'bg-red-100 text-red-600'; break;
                                                    case 'doc':
                                                    case 'docx': echo 'bg-blue-100 text-blue-600'; break;
                                                    case 'xls':
                                                    case 'xlsx': echo 'bg-green-100 text-green-600'; break;
                                                    case 'jpg':
                                                    case 'jpeg':
                                                    case 'png': echo 'bg-purple-100 text-purple-600'; break;
                                                    default: echo 'bg-gray-100 text-gray-600';
                                                }
                                                ?>
                                                flex items-center justify-center">
                                                <span class="text-xs font-bold"><?php echo strtoupper($ext ?: 'DOC'); ?></span>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($approval['title']); ?></p>
                                                <p class="text-xs text-gray-500">DOC-<?php echo str_pad($approval['document_id'], 3, '0', STR_PAD_LEFT); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($approval['office_name']); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                        if (!empty($approval['full_name'])) {
                                            echo htmlspecialchars($approval['full_name']);
                                        } elseif (!empty($approval['username'])) {
                                            echo htmlspecialchars($approval['username']);
                                        } else {
                                            echo 'Unknown';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y g:i A', strtotime($approval['completed_at'])); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php
                                            switch($approval['status']) {
                                                case 'approved': echo 'bg-green-100 text-green-800'; break;
                                                case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'revision': 
                                                case 'revision_requested': echo 'bg-blue-100 text-blue-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($approval['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if (!empty($approval['verification_code'])): ?>
                                            <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded"><?php echo $approval['verification_code']; ?></span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 rounded-full text-xs
                                            <?php echo $approval['approval_type'] === 'workflow' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'; ?>">
                                            <?php echo ucfirst($approval['approval_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex space-x-3">
                                            <a href="dashboard.php?page=view_document&id=<?php echo $approval['document_id']; ?>" 
                                               class="text-indigo-600 hover:text-indigo-900"
                                               title="View Document">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (!empty($approval['verification_code'])): ?>
                                            <a href="dashboard.php?page=admin_verify&verification_code=<?php echo $approval['verification_code']; ?>&verify=1" 
                                               class="text-green-600 hover:text-green-900"
                                               title="Verify Document">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                            <?php endif; ?>
                                            <?php if (!empty($approval['remarks'])): ?>
                                            <span class="text-yellow-600 hover:text-yellow-900 cursor-pointer"
                                                 title="<?php echo htmlspecialchars($approval['remarks']); ?>">
                                                <i class="fas fa-comment"></i>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php } else { ?>
                <div class="p-8 text-center text-gray-500">
                    <p>No document approvals found matching your criteria.</p>
                    <?php if ($conn->error): ?>
                    <p class="text-xs text-red-500 mt-2">Error: <?php echo htmlspecialchars($conn->error); ?></p>
                    <?php endif; ?>
                </div>
            <?php }
            } catch (Exception $e) {
                echo '<div class="p-8 text-center text-red-500">';
                echo '<p>Error retrieving document approvals: ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <!-- Activity Dashboard -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Statistics Cards -->
        <div class="bg-white rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-semibold mb-4">Activity Statistics</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Activities:</span>
                    <span class="font-semibold"><?php echo number_format($stats['total_activities']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Logins:</span>
                    <span class="font-semibold text-green-600"><?php echo number_format($stats['total_logins']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Logouts:</span>
                    <span class="font-semibold text-red-600"><?php echo number_format($stats['total_logouts']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Unique Users:</span>
                    <span class="font-semibold"><?php echo number_format($stats['unique_users']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Latest Activity:</span>
                    <span class="font-semibold"><?php echo date('M d, Y g:i A', strtotime($stats['latest_activity'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Most Active Users -->
        <div class="bg-white rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-semibold mb-4">Most Active Users</h3>
            <?php if (empty($active_users)): ?>
                <p class="text-gray-500 text-center">No user activity data available.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($active_users as $user): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 mr-3">
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="font-medium"><?php echo htmlspecialchars($user['username']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($user['role_name']); ?> - <?php echo htmlspecialchars($user['office_name']); ?></div>
                                </div>
                            </div>
                            <div class="bg-gray-100 px-2 py-1 rounded-full text-xs font-medium">
                                <?php echo $user['activity_count']; ?> activities
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activities -->
        <div class="bg-white rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-semibold mb-4">Recent Activities</h3>
            <?php if (empty($recent_activities)): ?>
                <p class="text-gray-500 text-center">No recent activities.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="flex items-start">
                            <div class="mt-1 mr-3">
                                <div class="w-2 h-2 rounded-full <?php echo $activity['action'] === 'login' ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                            </div>
                            <div>
                                <div class="text-sm">
                                    <span class="font-medium"><?php echo isset($activity['username']) ? htmlspecialchars($activity['username']) : 'Unknown user'; ?></span>
                                    <span class="text-gray-600"><?php echo isset($activity['action']) && $activity['action'] === 'login' ? 'logged in' : 'logged out'; ?></span>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo date('M d, Y g:i A', strtotime($activity['timestamp'])); ?>
                                    (<?php echo human_time_diff(strtotime($activity['timestamp'])); ?>)
                                </div>
                                <?php if (isset($activity['details']) && !empty($activity['details'])): ?>
                                <div class="text-xs text-gray-600 mt-1">
                                    <?php echo htmlspecialchars($activity['details']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <form action="" method="GET" class="space-y-4">
            <input type="hidden" name="page" value="user_logs">
            
            <div class="flex flex-wrap gap-4">
                <!-- Search Box -->
                <div class="flex-1 min-w-[200px]">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search User</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" 
                           placeholder="Search by username, email or name" 
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                
                <!-- Date Filter -->
                <div class="w-48">
                    <label for="date_filter" class="block text-sm font-medium text-gray-700 mb-1">Date Filter</label>
                    <select id="date_filter" name="date_filter" 
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="" <?php echo $date_filter === '' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo $date_filter === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="this_month" <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="custom" <?php echo $date_filter === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                
                <!-- Custom Date Range (initially hidden) -->
                <div id="custom_date_range" class="<?php echo $date_filter === 'custom' ? 'flex' : 'hidden'; ?> gap-2 w-full md:w-auto">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" id="start_date" name="start_date" 
                               value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>" 
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" id="end_date" name="end_date" 
                               value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>" 
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                </div>
                
                <!-- Action Filter -->
                <div class="w-48">
                    <label for="action_filter" class="block text-sm font-medium text-gray-700 mb-1">Action Filter</label>
                    <select id="action_filter" name="action_filter" 
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="all" <?php echo $action_filter === 'all' ? 'selected' : ''; ?>>All Actions</option>
                        <?php foreach ($available_actions as $action): ?>
                            <option value="<?php echo $action; ?>" <?php echo $action_filter === $action ? 'selected' : ''; ?>><?php echo ucfirst($action); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Office Filter -->
                <div class="w-48">
                    <label for="office_filter" class="block text-sm font-medium text-gray-700 mb-1">Office Filter</label>
                    <select id="office_filter" name="office_filter" 
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="0" <?php echo $office_filter === 0 ? 'selected' : ''; ?>>All Offices</option>
                        <?php foreach ($available_offices as $office): ?>
                            <option value="<?php echo $office['office_id']; ?>" <?php echo $office_filter === $office['office_id'] ? 'selected' : ''; ?>><?php echo $office['office_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Document Filter -->
                <div class="w-48">
                    <label for="document_filter" class="block text-sm font-medium text-gray-700 mb-1">Document Filter</label>
                    <select id="document_filter" name="document_filter" 
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="0" <?php echo $document_filter === 0 ? 'selected' : ''; ?>>All Documents</option>
                        <?php 
                        $documents_sql = "SELECT document_id, title FROM documents ORDER BY title";
                        $documents_result = $conn->query($documents_sql);
                        while ($document = $documents_result->fetch_assoc()): ?>
                            <option value="<?php echo $document['document_id']; ?>" <?php echo $document_filter === $document['document_id'] ? 'selected' : ''; ?>><?php echo $document['title']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <!-- Results Per Page -->
                <div class="w-32">
                    <label for="show" class="block text-sm font-medium text-gray-700 mb-1">Show</label>
                    <select id="show" name="show" 
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="10" <?php echo $logs_per_page === 10 ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo $logs_per_page === 20 ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?php echo $logs_per_page === 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $logs_per_page === 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
                
                <!-- Submit Button -->
                <div class="flex items-end">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                        Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="p-4 border-b">
            <h2 class="text-lg font-semibold">User Activity Logs</h2>
            <p class="text-sm text-gray-500">Showing <?php echo count($logs); ?> of <?php echo $total_logs; ?> logs</p>
        </div>
        
        <?php if (empty($logs)): ?>
            <div class="p-8 text-center text-gray-500">
                <p>No logs found matching your criteria.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Office</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Affected User</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Affected Document</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-600">
                                            <?php echo strtoupper(substr($log['username'], 0, 1)); ?>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($log['username']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($log['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        switch($log['role_name']) {
                                            case 'President':
                                                echo 'bg-purple-100 text-purple-800';
                                                break;
                                            case 'Admin':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            default:
                                                echo 'bg-green-100 text-green-800';
                                        }
                                        ?>">
                                        <?php echo htmlspecialchars($log['role_name']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($log['office_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $log['action'] === 'login' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($log['action'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                    $timestamp = strtotime($log['timestamp']);
                                    echo date('M d, Y g:i A', $timestamp); 
                                    echo ' (' . human_time_diff($timestamp) . ')';
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo isset($log['details']) ? htmlspecialchars($log['details']) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if (isset($log['affected_username']) && !empty($log['affected_username'])): ?>
                                        <?php echo htmlspecialchars($log['affected_username']); ?> 
                                        <?php if (isset($log['affected_full_name']) && !empty($log['affected_full_name'])): ?>
                                            (<?php echo htmlspecialchars($log['affected_full_name']); ?>)
                                        <?php endif; ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if (isset($log['document_title']) && !empty($log['document_title'])): ?>
                                        <?php echo htmlspecialchars($log['document_title']); ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 border-t flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                            <span class="font-medium"><?php echo min($offset + $logs_per_page, $total_logs); ?></span> of 
                            <span class="font-medium"><?php echo $total_logs; ?></span> results
                        </p>
                    </div>
                    <div class="flex space-x-2">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=user_logs&log_page=<?php echo $current_page - 1; ?>&show=<?php echo $logs_per_page; ?>&search=<?php echo urlencode($search_term); ?>&date_filter=<?php echo urlencode($date_filter); ?><?php echo $date_filter === 'custom' ? '&start_date=' . urlencode($_GET['start_date'] ?? '') . '&end_date=' . urlencode($_GET['end_date'] ?? '') : ''; ?>&action_filter=<?php echo urlencode($action_filter); ?>&office_filter=<?php echo urlencode($office_filter); ?>&document_filter=<?php echo urlencode($document_filter); ?>" 
                               class="px-3 py-1 border rounded-md hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php 
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <a href="?page=user_logs&log_page=<?php echo $i; ?>&show=<?php echo $logs_per_page; ?>&search=<?php echo urlencode($search_term); ?>&date_filter=<?php echo urlencode($date_filter); ?><?php echo $date_filter === 'custom' ? '&start_date=' . urlencode($_GET['start_date'] ?? '') . '&end_date=' . urlencode($_GET['end_date'] ?? '') : ''; ?>&action_filter=<?php echo urlencode($action_filter); ?>&office_filter=<?php echo urlencode($office_filter); ?>&document_filter=<?php echo urlencode($document_filter); ?>" 
                               class="px-3 py-1 border rounded-md <?php echo $i === $current_page ? 'bg-green-600 text-white' : 'hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=user_logs&log_page=<?php echo $current_page + 1; ?>&show=<?php echo $logs_per_page; ?>&search=<?php echo urlencode($search_term); ?>&date_filter=<?php echo urlencode($date_filter); ?><?php echo $date_filter === 'custom' ? '&start_date=' . urlencode($_GET['start_date'] ?? '') . '&end_date=' . urlencode($_GET['end_date'] ?? '') : ''; ?>&action_filter=<?php echo urlencode($action_filter); ?>&office_filter=<?php echo urlencode($office_filter); ?>&document_filter=<?php echo urlencode($document_filter); ?>" 
                               class="px-3 py-1 border rounded-md hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Hidden form for admin_verify page -->
    <form id="admin_verify_form" action="dashboard.php" method="GET" class="hidden">
        <input type="hidden" name="page" value="admin_verify">
        <input type="hidden" id="verification_code" name="verification_code" value="">
        <input type="hidden" name="verify" value="1">
    </form>
</div>

<script>
    // Show/hide custom date range based on date filter selection
    document.getElementById('date_filter').addEventListener('change', function() {
        const customDateRange = document.getElementById('custom_date_range');
        if (this.value === 'custom') {
            customDateRange.classList.remove('hidden');
            customDateRange.classList.add('flex');
        } else {
            customDateRange.classList.add('hidden');
            customDateRange.classList.remove('flex');
        }
    });
</script>