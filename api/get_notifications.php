<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/enhanced_notification_system.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if notifications table exists
$table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($table_check->num_rows == 0) {
    // Create notifications table if it doesn't exist
    $create_table_sql = "CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        document_id INT,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        status VARCHAR(50),
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE
    )";
    
    if (!$conn->query($create_table_sql)) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create notifications table: ' . $conn->error
        ]);
        exit;
    }
    
    // Add sample notifications for testing
    $sample_notifications = [
        [
            'title' => 'Welcome to the Dashboard',
            'message' => 'This is your notification center where you will receive important updates.',
            'status' => 'info'
        ],
        [
            'title' => 'New Document Uploaded',
            'message' => 'A new document has been uploaded and is waiting for your review.',
            'status' => 'pending'
        ]
    ];
    
    $insert_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, status) VALUES (?, ?, ?, ?)");
    foreach ($sample_notifications as $notification) {
        $insert_stmt->bind_param("isss", $user_id, $notification['title'], $notification['message'], $notification['status']);
        $insert_stmt->execute();
    }
}

// Check if we need to update the table structure
$column_check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'document_id'");
if ($column_check->num_rows == 0) {
    // Add document_id column if it doesn't exist
    $alter_table_sql = "ALTER TABLE notifications 
                        ADD COLUMN document_id INT,
                        ADD COLUMN status VARCHAR(50),
                        ADD FOREIGN KEY (document_id) REFERENCES documents(document_id) ON DELETE CASCADE";
    $conn->query($alter_table_sql);
}

// Insert a test notification if none exist for this user
$check_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$check_row = $check_result->fetch_assoc();

if ($check_row['count'] == 0) {
    // Insert sample notifications
    $sample_notifications = [
        [
            'title' => 'Welcome to the Dashboard',
            'message' => 'This is your notification center where you will receive important updates.',
            'status' => 'info'
        ],
        [
            'title' => 'Document Update',
            'message' => 'A document has been updated and requires your attention.',
            'status' => 'pending'
        ],
        [
            'title' => 'System Notification',
            'message' => 'The document management system has been updated with new features.',
            'status' => 'info'
        ]
    ];
    
    $insert_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, status, created_at) VALUES (?, ?, ?, ?, NOW())");
    foreach ($sample_notifications as $notification) {
        $insert_stmt->bind_param("isss", $user_id, $notification['title'], $notification['message'], $notification['status']);
        $insert_stmt->execute();
    }
}

// Return only the latest notification per (document_id, event_type) for this user
// Fallback when event_type is NULL: group by (document_id, status)
$query = "
  SELECT n.*, 
         d.title AS document_title,
         d.status AS document_status,
         d.is_urgent,
         d.created_at AS document_created_at
  FROM notifications n
  LEFT JOIN documents d ON n.document_id = d.document_id
  INNER JOIN (
    SELECT user_id,
           document_id,
           COALESCE(event_type, status) AS grp,
           MAX(created_at) AS max_created
    FROM notifications
    WHERE user_id = ?
    GROUP BY user_id, document_id, COALESCE(event_type, status)
  ) latest
  ON latest.user_id = n.user_id
     AND latest.document_id <=> n.document_id
     AND latest.grp = COALESCE(n.event_type, n.status)
     AND latest.max_created = n.created_at
  WHERE n.user_id = ?
  ORDER BY 
    CASE n.priority 
      WHEN 'critical' THEN 1 
      WHEN 'high' THEN 2 
      WHEN 'normal' THEN 3 
      WHEN 'low' THEN 4 
    END,
    n.created_at DESC
  LIMIT 20";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    // Format the notification title and message based on document status
    if (!empty($row['document_id']) && !empty($row['document_title'])) {
        // This is a document-related notification
        $document_title = $row['document_title'];
        $document_status = $row['document_status'] ?? $row['status'] ?? 'pending';
        
        // If title is not set, generate one based on document status
        if (empty($row['title']) || $row['title'] == 'undefined') {
            switch ($document_status) {
                case 'approved':
                    $row['title'] = 'Document Approved';
                    break;
                case 'rejected':
                    $row['title'] = 'Document Rejected';
                    break;
                case 'revision_requested':
                    $row['title'] = 'Revision Requested';
                    break;
                case 'on_hold':
                    $row['title'] = 'Document On Hold';
                    break;
                default:
                    $row['title'] = 'Document Update';
            }
        }
        
        // If message is not set, generate one based on document status
        if (empty($row['message']) || $row['message'] == 'undefined') {
            $row['message'] = "Document \"$document_title\" requires your attention";
        }
    }
    
    $notifications[] = $row;
}

// Add debug information
$debug = [
    'user_id' => $user_id,
    'notification_count' => count($notifications),
    'query' => $query,
    'table_exists' => ($table_check->num_rows > 0) ? 'yes' : 'no',
    'column_exists' => ($column_check->num_rows > 0) ? 'yes' : 'no'
];

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'debug' => $debug
]); 