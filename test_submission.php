<?php
/**
 * Test Document Submission
 * 
 * This file tests the document submission functionality with Google Docs integration.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simulate a logged-in user
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Use a test user ID
    $_SESSION['office_id'] = 1; // Use a test office ID
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Document Submission</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .notification {
            position: fixed;
            right: 20px;
            top: 20px;
            max-width: 350px;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            transform: translateX(400px);
            transition: transform 0.3s ease-in-out;
            z-index: 1000;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Test Document Submission</h1>
        
        <div class="bg-white p-6 rounded shadow-md">
            <h2 class="text-xl font-semibold mb-4">Submit a Test Document</h2>
            
            <form id="testForm" class="space-y-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Document Title</label>
                    <input type="text" id="title" name="title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value="Test Document">
                </div>
                
                <div>
                    <label for="type_id" class="block text-sm font-medium text-gray-700">Document Type</label>
                    <select id="type_id" name="type_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="1">Memo</option>
                        <option value="2">Letter</option>
                        <option value="3">Report</option>
                    </select>
                </div>
                
                <div>
                    <label for="google_doc_id" class="block text-sm font-medium text-gray-700">Google Doc ID</label>
                    <input type="text" id="google_doc_id" name="google_doc_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Enter Google Doc ID">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Workflow</label>
                    <div class="mt-1 p-3 border border-gray-300 rounded-md">
                        <div class="flex items-center space-x-2 mb-2">
                            <span class="text-sm font-medium">Office 1</span>
                            <input type="hidden" name="workflow_offices[]" value="1">
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm font-medium">Office 2</span>
                            <input type="hidden" name="workflow_offices[]" value="2">
                        </div>
                    </div>
                </div>
                
                <div class="flex space-x-4">
                    <button type="button" id="submitBtn" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Submit Document
                    </button>
                    <button type="button" id="draftBtn" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Save as Draft
                    </button>
                </div>
            </form>
        </div>
        
        <div class="mt-6 bg-white p-6 rounded shadow-md">
            <h2 class="text-xl font-semibold mb-4">Response</h2>
            <pre id="response" class="bg-gray-100 p-4 rounded overflow-auto max-h-96"></pre>
        </div>
    </div>
    
    <!-- Notification container -->
    <div id="notification" class="notification"></div>
    
    <script>
        // Show notification
        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = 'notification ' + type;
            
            // Add show class to trigger animation
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            // Remove notification after 5 seconds
            setTimeout(() => {
                notification.classList.remove('show');
            }, 5000);
        }
        
        // Show success notification
        function showSuccessMessage(message) {
            showNotification(message, 'success');
        }
        
        // Show error notification
        function showErrorMessage(message) {
            showNotification(message, 'error');
        }
        
        // Submit document
        document.getElementById('submitBtn').addEventListener('click', function() {
            const title = document.getElementById('title').value;
            const typeId = document.getElementById('type_id').value;
            const googleDocId = document.getElementById('google_doc_id').value;
            
            if (!title) {
                showErrorMessage('Please enter a document title');
                return;
            }
            
            if (!googleDocId) {
                showErrorMessage('Please enter a Google Doc ID');
                return;
            }
            
            // Show loading state
            this.disabled = true;
            this.innerHTML = 'Submitting...';
            
            // Collect form data
            const formData = new FormData(document.getElementById('testForm'));
            formData.append('status', 'pending');
            
            // Submit the form
            fetch('api/submit_document.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if the response is JSON
                const contentType = response.headers.get('content-type');
                document.getElementById('response').textContent += "Response content type: " + contentType + "\n\n";
                
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON, get the text and log it for debugging
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        document.getElementById('response').textContent += "Raw response: " + text + "\n\n";
                        throw new Error('Unexpected response format from server');
                    });
                }
            })
            .then(data => {
                // Reset button state
                this.disabled = false;
                this.innerHTML = 'Submit Document';
                
                // Display response
                document.getElementById('response').textContent += JSON.stringify(data, null, 2);
                
                if (data.success) {
                    showSuccessMessage('Document submitted successfully');
                } else {
                    showErrorMessage('Error submitting document: ' + data.error);
                }
            })
            .catch(error => {
                // Reset button state
                this.disabled = false;
                this.innerHTML = 'Submit Document';
                
                console.error('Submission error:', error);
                showErrorMessage('Error submitting document: ' + error.message);
            });
        });
        
        // Save as draft
        document.getElementById('draftBtn').addEventListener('click', function() {
            const title = document.getElementById('title').value;
            const typeId = document.getElementById('type_id').value;
            const googleDocId = document.getElementById('google_doc_id').value;
            
            if (!title) {
                showErrorMessage('Please enter a document title');
                return;
            }
            
            if (!googleDocId) {
                showErrorMessage('Please enter a Google Doc ID');
                return;
            }
            
            // Show loading state
            this.disabled = true;
            this.innerHTML = 'Saving...';
            
            // Collect form data
            const formData = new FormData(document.getElementById('testForm'));
            formData.append('status', 'draft');
            
            // Submit the form
            fetch('api/submit_document.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if the response is JSON
                const contentType = response.headers.get('content-type');
                document.getElementById('response').textContent += "Response content type: " + contentType + "\n\n";
                
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON, get the text and log it for debugging
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        document.getElementById('response').textContent += "Raw response: " + text + "\n\n";
                        throw new Error('Unexpected response format from server');
                    });
                }
            })
            .then(data => {
                // Reset button state
                this.disabled = false;
                this.innerHTML = 'Save as Draft';
                
                // Display response
                document.getElementById('response').textContent += JSON.stringify(data, null, 2);
                
                if (data.success) {
                    showSuccessMessage('Draft saved successfully');
                } else {
                    showErrorMessage('Error saving draft: ' + data.error);
                }
            })
            .catch(error => {
                // Reset button state
                this.disabled = false;
                this.innerHTML = 'Save as Draft';
                
                console.error('Save draft error:', error);
                showErrorMessage('Error saving draft: ' + error.message);
            });
        });
    </script>
</body>
</html>
