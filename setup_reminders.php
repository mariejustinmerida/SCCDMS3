<?php
// Script to add reminders table to the database
require_once 'includes/config.php';

// SQL to create reminders table
$sql = "CREATE TABLE IF NOT EXISTS reminders (
    reminder_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    reminder_date DATE NOT NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE INDEX idx_reminders_user ON reminders(user_id);
CREATE INDEX idx_reminders_date ON reminders(reminder_date);";

// Execute the SQL commands
$success = true;
$error_message = '';

if ($conn->multi_query($sql)) {
    do {
        // Store result
        if ($result = $conn->store_result()) {
            $result->free();
        }
        // Check for errors
        if ($conn->error) {
            $success = false;
            $error_message .= $conn->error . '<br>';
        }
    } while ($conn->next_result());
} else {
    $success = false;
    $error_message = $conn->error;
}

// Close the connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Reminders Table</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-lg mx-auto bg-white rounded-xl shadow-md p-6">
        <h1 class="text-2xl font-bold mb-4">Reminders Table Setup</h1>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <p>Reminders table created successfully!</p>
            </div>
            <p class="mb-4">The reminders feature is now ready to use in your dashboard.</p>
            <a href="dashboard.php" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                Go to Dashboard
            </a>
        <?php else: ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <p><strong>Error:</strong> Could not create reminders table.</p>
                <p class="text-sm"><?php echo $error_message; ?></p>
            </div>
            <p class="mb-4">Please check your database configuration and try again.</p>
            <button onclick="window.location.reload()" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                Try Again
            </button>
        <?php endif; ?>
    </div>
</body>
</html>
