<?php
// Simple test script to check if the reminders API is working
require_once 'includes/config.php';

// Check if reminders table exists
$table_check = $conn->query("SHOW TABLES LIKE 'reminders'");
$reminders_table_exists = ($table_check && $table_check->num_rows > 0);

// Close the connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Reminders API</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-md p-6">
        <h1 class="text-2xl font-bold mb-4">Reminders API Debug Test</h1>
        
        <div class="mb-4">
            <h2 class="text-xl font-semibold mb-2">Reminders Table Status</h2>
            <p class="mb-2">
                <?php if ($reminders_table_exists): ?>
                    <span class="text-green-600 font-bold">✓</span> Reminders table exists
                <?php else: ?>
                    <span class="text-red-600 font-bold">✗</span> Reminders table does not exist
                    <a href="setup_reminders.php" class="ml-2 text-blue-600 underline">Run Setup Script</a>
                <?php endif; ?>
            </p>
        </div>
        
        <div class="mb-4">
            <h2 class="text-xl font-semibold mb-2">Debug API Test</h2>
            <div class="space-y-4">
                <div>
                    <h3 class="font-medium">Test Add Reminder (Debug Mode)</h3>
                    <div class="space-y-2 mt-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Title</label>
                            <input type="text" id="testTitle" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea id="testDescription" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Date</label>
                            <input type="date" id="testReminderDate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <button id="testAdd" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Add Test Reminder (Debug)</button>
                    </div>
                    <div id="addResult" class="mt-2 p-3 bg-gray-100 rounded-md overflow-auto max-h-80 hidden"></div>
                </div>
                
                <div class="mt-4">
                    <h3 class="font-medium">Check Error Log</h3>
                    <button id="checkLog" class="mt-2 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">View Error Log</button>
                    <div id="logResult" class="mt-2 p-3 bg-gray-100 rounded-md overflow-auto max-h-80 hidden"></div>
                </div>
            </div>
        </div>
        
        <div class="mt-6">
            <a href="test_reminders_api.php" class="text-blue-600 hover:underline">Back to Regular Test Page</a>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set today's date as default for date inputs
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('testReminderDate').value = today;
            
            // Test Add Reminder with Debug API
            document.getElementById('testAdd').addEventListener('click', function() {
                const title = document.getElementById('testTitle').value;
                const description = document.getElementById('testDescription').value;
                const reminderDate = document.getElementById('testReminderDate').value;
                const resultDiv = document.getElementById('addResult');
                
                if (!title || !reminderDate) {
                    resultDiv.innerHTML = '<p class="text-red-600">Title and date are required</p>';
                    resultDiv.classList.remove('hidden');
                    return;
                }
                
                resultDiv.innerHTML = 'Adding reminder...';
                resultDiv.classList.remove('hidden');
                
                fetch('api/reminders_debug.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        title: title,
                        description: description,
                        reminder_date: reminderDate
                    })
                })
                .then(response => {
                    const status = response.status;
                    return response.text().then(text => ({ status, text }));
                })
                .then(({ status, text }) => {
                    let output = `<p>Status Code: ${status}</p><p>Raw Response:</p><pre>${text}</pre>`;
                    
                    try {
                        // Try to parse the text as JSON
                        const data = JSON.parse(text);
                        output += `<p>Parsed JSON:</p><pre>${JSON.stringify(data, null, 2)}</pre>`;
                        
                        if (data.success) {
                            // Clear form fields on success
                            document.getElementById('testTitle').value = '';
                            document.getElementById('testDescription').value = '';
                        }
                    } catch (e) {
                        // If parsing fails, show parsing error
                        output += `<p class="text-red-600">Error parsing JSON: ${e.message}</p>`;
                    }
                    
                    resultDiv.innerHTML = output;
                })
                .catch(error => {
                    resultDiv.innerHTML = `<p class="text-red-600">Error: ${error.message}</p>`;
                });
            });
            
            // Check error log
            document.getElementById('checkLog').addEventListener('click', function() {
                const resultDiv = document.getElementById('logResult');
                resultDiv.innerHTML = 'Loading error log...';
                resultDiv.classList.remove('hidden');
                
                fetch('check_error_log.php')
                    .then(response => response.text())
                    .then(text => {
                        resultDiv.innerHTML = `<pre>${text}</pre>`;
                    })
                    .catch(error => {
                        resultDiv.innerHTML = `<p class="text-red-600">Error: ${error.message}</p>`;
                    });
            });
        });
    </script>
</body>
</html>
