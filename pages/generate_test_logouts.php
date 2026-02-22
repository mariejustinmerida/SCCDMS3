<?php
// This is a one-time script to generate test logout records for existing login records
// Access this page once to generate the records, then delete it

// Check if a session is already active before starting one
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';

// Check if user is logged in and has President or Super Admin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['President','Super Admin'])) {
    echo "Access denied. Only the President or Super Admin can run this script.";
    exit;
}

// Check if force generation is requested
$force_generate = isset($_GET['force']) && $_GET['force'] === 'true';

// Get all login records
$login_sql = "SELECT * FROM user_logs WHERE action = 'login' ORDER BY timestamp ASC";
$login_result = $conn->query($login_sql);

$count = 0;
if ($login_result->num_rows > 0) {
    while ($login = $login_result->fetch_assoc()) {
        // For each login, create a logout record 10-60 minutes later
        $login_time = strtotime($login['timestamp']);
        $logout_time = date('Y-m-d H:i:s', $login_time + rand(10 * 60, 60 * 60)); // 10-60 minutes later
        
        // Check if a logout already exists for this login
        $check_sql = "SELECT COUNT(*) as count FROM user_logs 
                     WHERE user_id = ? AND action = 'logout' 
                     AND timestamp BETWEEN ? AND DATE_ADD(?, INTERVAL 2 HOUR)";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iss", $login['user_id'], $login['timestamp'], $login['timestamp']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $has_logout = $check_result->fetch_assoc()['count'] > 0;
        
        // Only create a logout if one doesn't already exist or if force generation is enabled
        if (!$has_logout || $force_generate) {
            $insert_sql = "INSERT INTO user_logs (user_id, action, timestamp) VALUES (?, 'logout', ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("is", $login['user_id'], $logout_time);
            
            if ($insert_stmt->execute()) {
                $count++;
            }
        }
    }
}

// Get the total count of login and logout records
$stats_sql = "SELECT 
                SUM(CASE WHEN action = 'login' THEN 1 ELSE 0 END) as total_logins,
                SUM(CASE WHEN action = 'logout' THEN 1 ELSE 0 END) as total_logouts
              FROM user_logs";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Output HTML with styling
echo '<!DOCTYPE html>
<html>
<head>
    <title>Test Logout Records Generated</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold text-green-600 mb-4">Test Logout Records Generated</h1>
        <div class="mb-6">
            <p class="text-lg mb-2">Successfully created <span class="font-bold text-green-600">' . $count . '</span> logout records.</p>
            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">Total Login Records:</span>
                    <span class="font-semibold">' . $stats['total_logins'] . '</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Logout Records:</span>
                    <span class="font-semibold">' . $stats['total_logouts'] . '</span>
                </div>
            </div>
        </div>
        
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        You should delete this script now for security reasons.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="flex space-x-4">
            <a href="?page=user_logs" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                Return to User Logs
            </a>
            <a href="?page=generate_test_logouts&force=true" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Force Generate More Records
            </a>
        </div>
    </div>
</body>
</html>';
?> 