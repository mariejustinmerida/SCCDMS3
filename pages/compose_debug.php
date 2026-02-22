<?php
// Include the original compose.php content
include('compose.php');
?>

<script>
// Override the submit handler to use our debug endpoint
document.addEventListener('DOMContentLoaded', function() {
    const originalSubmitBtn = document.getElementById('submitBtn');
    if (originalSubmitBtn) {
        originalSubmitBtn.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent the original handler
            
            // Show that we're using the debug version
            const alertContainer = document.getElementById('alertContainer');
            if (alertContainer) {
                alertContainer.innerHTML = `
                    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4">
                        <strong class="font-bold">Debug Mode:</strong>
                        <span class="block sm:inline">Using enhanced error handling...</span>
                    </div>
                `;
            }
            
            // Validate form first
            if (typeof validateForm === 'function' && !validateForm()) {
                return;
            }
            
            // Make sure we have a Google Doc ID
            if (!currentDocumentId) {
                showErrorMessage('No active Google Doc. Please refresh the page and try again.');
                return;
            }
            
            // Show loading state
            originalSubmitBtn.disabled = true;
            originalSubmitBtn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Submitting...
            `;
            
            // Collect form data
            const formData = new FormData();
            formData.append('title', document.getElementById('docTitle').value.trim());
            formData.append('type_id', document.getElementById('documentType').value);
            formData.append('status', 'pending');
            formData.append('google_doc_id', currentDocumentId);
            
            // Add file attachment if present
            const fileInput = document.getElementById('docFile');
            if (fileInput && fileInput.files.length > 0) {
                formData.append('attachment', fileInput.files[0]);
            }
            
            // Add workflow data
            const workflow = collectWorkflowData();
            
            if (workflow.length === 0) {
                showErrorMessage('Please add at least one workflow step');
                originalSubmitBtn.disabled = false;
                originalSubmitBtn.innerHTML = 'Submit Document';
                return;
            }
            
            console.log('Workflow data:', workflow);
            
            // Add workflow offices and roles
            workflow.forEach((step, index) => {
                if (step.recipient.type === 'office') {
                    formData.append('workflow_offices[]', step.recipient.id);
                    formData.append('workflow_roles[]', '');
                    formData.append('recipient_types[]', 'office');
                } else if (step.recipient.type === 'person') {
                    // Get the office ID for this user
                    const userSelect = document.querySelector(`.workflow-step:nth-child(${index + 1}) .user-select`);
                    const officeId = userSelect.options[userSelect.selectedIndex].getAttribute('data-office');
                    
                    formData.append('workflow_offices[]', officeId);
                    formData.append('workflow_roles[]', step.recipient.id);
                    formData.append('recipient_types[]', 'person');
                }
            });
            
            // Debug: Log all form data
            for (const pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            // Use the debug endpoint instead of the original
            fetch('../api/submit_document_debug.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                // Get the raw text first for debugging
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    
                    try {
                        // Try to parse as JSON
                        const data = JSON.parse(text);
                        return data;
                    } catch (e) {
                        console.error('Error parsing JSON:', e);
                        throw new Error('Invalid JSON response: ' + text);
                    }
                });
            })
            .then(data => {
                // Reset button state
                originalSubmitBtn.disabled = false;
                originalSubmitBtn.innerHTML = 'Submit Document';
                
                if (data.success) {
                    showSuccessMessage('Document submitted successfully');
                    document.getElementById('documentForm').reset();
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                } else {
                    showErrorMessage('Error submitting document: ' + data.error);
                }
            })
            .catch(error => {
                // Reset button state
                originalSubmitBtn.disabled = false;
                originalSubmitBtn.innerHTML = 'Submit Document';
                
                showErrorMessage('Error: ' + error.message);
                console.error('Submission error:', error);
            });
        }, true); // Use capturing phase to override the original handler
    }
});
</script> 