<!DOCTYPE html>
<html>
<head>
  <title>Track Documents - SCC DMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900&amp;family=Plus+Jakarta+Sans%3Awght%40400%3B500%3B700%3B800" />
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
    body.dark .text-gray-900 {
      color: #ffffff;
    }
    body.dark .text-gray-600 {
      color: #999999;
    }
    body.dark .border-gray-200 {
      border-color: #333333;
    }
    body.dark .bg-gray-50 {
      background-color: #2a2a2a;
    }
    .spinner {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      border: 3px solid #eee;
      border-top-color: #16A34A;
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  </style>
</head>
<body class="min-h-screen bg-gray-50">
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Track Documents</h1>
      <div class="flex items-center text-sm text-gray-500">
        <a href="dashboard.php" class="text-green-600 hover:text-green-800">Dashboard</a>
        <span class="mx-2">/</span>
        <span>Track Documents</span>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">Enter Document Code</h2>
      <div class="relative">
        <input type="text" id="documentCode" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="Enter document ID or tracking code">
        <button id="trackButton" onclick="trackDocument()" class="absolute right-3 top-2 p-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
            Track
          </button>
      </div>
      <div id="errorMessage" class="text-red-500 mt-2 hidden"></div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
      <h2 class="text-lg font-semibold mb-4">Document Information</h2>
      <div id="documentInfo" class="text-gray-600">
        <p>Enter a document code above to track its status.</p>
      </div>

      <div class="mt-8">
        <h3 class="text-md font-semibold mb-4">Workflow Timeline</h3>
        <div id="timelineEvents" class="space-y-4">
          <!-- Timeline events will be inserted here -->
        </div>
      </div>
    </div>
  </div>

  <script>
    // Get document ID from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const docId = urlParams.get('id');
    
    // Auto-fill document code if present in URL
    if (docId) {
      document.getElementById('documentCode').value = docId;
      // Wait for DOM to fully load before tracking
      document.addEventListener('DOMContentLoaded', function() {
        trackDocument();
      });
    }
    
    function trackDocument() {
      hideError();
      
      const documentCode = document.getElementById('documentCode').value.trim();
      if (!documentCode) {
        showError('Please enter a document code');
        return;
      }
      
      // Clear previous results
      document.getElementById('documentInfo').innerHTML = '';
      document.getElementById('timelineEvents').innerHTML = '';
      
      // Show loading spinner
      document.getElementById('documentInfo').innerHTML = `
        <div class="flex justify-center py-6">
          <div class="spinner"></div>
        </div>
      `;
      
      // Fetch document info and workflow
      fetch(`../api/get_workflow.php?document_id=${documentCode}`)
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
          }
          return response.json();
        })
        .then(workflowData => {
          if (!workflowData.success) {
            throw new Error(workflowData.error || 'Failed to retrieve document information');
          }
          
          // Make sure document data exists
          if (!workflowData.document) {
            throw new Error('No document information found');
          }
          
          // Display document info
          const documentInfo = document.getElementById('documentInfo');
          const documentData = workflowData.document;
          
          // Check if all workflow steps are completed
          let allCompleted = true;
          let hasWorkflowSteps = false;
          
          if (documentData.workflow_history && documentData.workflow_history.length > 0) {
            hasWorkflowSteps = true;
            documentData.workflow_history.forEach(step => {
              if (step.status !== 'COMPLETED') {
                allCompleted = false;
              }
            });
          }
          
          // If all steps are completed but status is still pending, display as Approved
          let displayStatus = documentData.status;
          if (hasWorkflowSteps && allCompleted && displayStatus && displayStatus.toLowerCase() === 'pending') {
            displayStatus = 'Approved';
            
            // Make an API call to update the status in the database
            fetch(`../api/update_document_status.php?document_id=${documentCode}&status=approved`, {
              method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
              console.log('Status updated:', data);
            })
            .catch(error => {
              console.error('Error updating status:', error);
            });
          }
          
          // Ensure displayStatus is never empty
          if (!displayStatus) {
            displayStatus = 'Pending';
          }
          
          // Determine badge class based on status
          const badgeClass = displayStatus === 'Approved' ? 'bg-green-100 text-green-800' : 
                            displayStatus === 'Rejected' ? 'bg-red-100 text-red-800' : 
                            displayStatus === 'On Hold' ? 'bg-blue-100 text-blue-800' :
                            displayStatus === 'Revision Requested' ? 'bg-purple-100 text-purple-800' :
                            'bg-yellow-100 text-yellow-800';
          
          documentInfo.innerHTML = `
            <div class="bg-white shadow-md rounded-lg p-6 mb-6">
              <h2 class="text-xl font-semibold text-gray-800 mb-4">Document Information</h2>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <p class="text-sm text-gray-600">Document Code</p>
                  <p class="font-medium">${documentCode}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Status</p>
                  <p class="font-medium">
                    <span class="px-2 py-1 rounded text-xs font-semibold ${badgeClass}">
                      ${displayStatus}
                    </span>
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Title</p>
                  <p class="font-medium">${documentData.title || 'N/A'}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Type</p>
                  <p class="font-medium">${documentData.type_name || 'N/A'}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Created By</p>
                  <p class="font-medium">${documentData.creator_name || 'N/A'}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Created At</p>
                  <p class="font-medium">${documentData.created_at ? new Date(documentData.created_at).toLocaleString() : 'N/A'}</p>
                </div>
              </div>
            </div>
          `;
          
          // Get workflow details to display steps
          fetch(`../api/get_document_workflow.php?document_id=${documentCode}`)
            .then(response => {
              if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
              }
              return response.json();
            })
            .then(workflowData => {
              if (!workflowData.success) {
                throw new Error(workflowData.error || 'Failed to retrieve workflow information');
              }
              
              // Process workflow steps
              const events = workflowData.steps.map(step => {
                // Determine status
                let status = step.status || 'PENDING';
                status = status.toUpperCase();
                
                // Determine if this is the current step
                const current = status === 'CURRENT';
                
                // If a step is ON_HOLD, update the main document status display
                if (status === 'ON_HOLD') {
                  documentData.status = 'On Hold';
                  const statusElement = document.querySelector('.badge-status');
                  if(statusElement) {
                    statusElement.textContent = 'On Hold';
                    statusElement.className = 'px-2 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-800 badge-status';
                  }
                }

                // Handle timestamps
                let timestamp = 'N/A';
                if (step.completed_at) {
                  timestamp = new Date(step.completed_at).toLocaleString();
                } else if (step.created_at) {
                  timestamp = new Date(step.created_at).toLocaleString();
                }
                
                // Get comments if available
                const comments = step.comments || '';
                
                // Return processed step
                return {
                  office_name: step.office_name,
                  status: status,
                  current: current,
                  timestamp: timestamp,
                  description: step.description || 'Workflow step',
                  step_order: step.step_order,
                  comments
                };
              });
              
              // Display workflow steps
              const timelineEvents = document.getElementById('timelineEvents');
              
              if (!workflowData.steps || workflowData.steps.length === 0) {
                timelineEvents.innerHTML = `
                  <div class="text-center py-4">
                    <p class="text-gray-600">No workflow steps found for this document.</p>
                  </div>
                `;
                return;
              }
              
              // Sort events by step_order if available, otherwise use the original order
              if (events[0].step_order) {
                events.sort((a, b) => a.step_order - b.step_order);
              }
              
              // Build the timeline
              events.forEach((event, index) => {
                let iconClass = 'bg-gray-200 text-gray-500';
                let borderStyle = '';
                let bgColor = 'bg-white';
                
                // Set colors based on event type/status
                if (event.type === 'success' || event.status === 'APPROVED' || event.status.includes('APPROVE')) {
                  iconClass = 'bg-green-600 text-white';
                  borderStyle = 'border-l-4 border-green-600';
                  bgColor = 'bg-green-50';
                } else if (event.type === 'error' || event.status === 'REJECTED') {
                  iconClass = 'bg-red-500 text-white';
                  borderStyle = 'border-l-4 border-red-500';
                  bgColor = 'bg-red-50';
                } else if (event.type === 'current' || event.status === 'CURRENT' || event.current) {
                  iconClass = 'bg-yellow-500 text-white';
                  borderStyle = 'border-l-4 border-yellow-500';
                  bgColor = 'bg-yellow-50';
                } else if (event.status === 'PENDING') {
                  iconClass = 'bg-orange-500 text-white';
                  borderStyle = 'border-l-4 border-orange-500';
                  bgColor = 'bg-orange-50';
                } else if (event.status === 'ON_HOLD') {
                  iconClass = 'bg-blue-500 text-white';
                  borderStyle = 'border-l-4 border-blue-500';
                  bgColor = 'bg-blue-50';
                }
                
                // Create the timeline event
                const eventElement = document.createElement('div');
                eventElement.className = 'flex items-start mb-6';
                
                // Standardize status display - ensure "APPROVE" is shown as "APPROVED"
                let displayStatus = event.status;
                if (event.status && event.status.includes('APPROVE') && !event.status.endsWith('D')) {
                  displayStatus = 'APPROVED';
                }
                
                eventElement.innerHTML = `
                  <div class="flex items-center justify-center">
                    <div class="flex-shrink-0 z-10">
                      <div class="flex items-center justify-center h-10 w-10 rounded-full ${iconClass}">
                        ${index + 1}
                      </div>
                    </div>
                  </div>
                  <div class="flex-1 ml-4 ${bgColor} rounded-lg p-4 ${borderStyle}">
                    <div class="font-medium ${event.type === 'current' || event.current ? 'text-yellow-800 text-lg' : 'text-gray-900'}">Step ${index + 1}: ${event.office_name || ''} - ${displayStatus}</div>
                    <div class="text-sm ${event.type === 'upcoming' ? 'text-gray-400' : (event.type === 'current' ? 'text-yellow-700' : 'text-gray-600')}">${event.description}</div>
                    <div class="text-xs text-gray-500 mt-1">${event.timestamp}</div>
                    ${event.comments ? `<div class="mt-2 p-2 bg-gray-50 rounded text-sm text-gray-700 border-l-2 border-gray-300"><strong>Comments:</strong> ${event.comments}</div>` : ''}
                  </div>
                `;
                
                timelineEvents.appendChild(eventElement);
              });
            })
            .catch(error => {
              console.error('Error fetching workflow:', error);
              
              // Show error in the timeline container
              const timelineEvents = document.getElementById('timelineEvents');
              timelineEvents.innerHTML = `
                <div class="bg-red-50 border-l-4 border-red-500 p-4">
                  <div class="flex">
                    <div class="flex-shrink-0">
                      <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                      </svg>
                    </div>
                    <div class="ml-3">
                      <p class="text-sm text-red-700">
                        ${error.message || 'An error occurred while retrieving workflow information.'}
                      </p>
                    </div>
                  </div>
                </div>
              `;
            });
        })
        .catch(error => {
          console.error('Error:', error);
          
          // Log the error to console for debugging
          console.error('Full error object:', error);
          
          // Show user-friendly error message
          const documentInfo = document.getElementById('documentInfo');
          documentInfo.innerHTML = `
            <div class="bg-red-50 border-l-4 border-red-500 p-4">
              <div class="flex">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3">
                  <p class="text-sm text-red-700">
                    ${error.message || 'An error occurred while tracking the document. Please try again.'}
                  </p>
                </div>
              </div>
            </div>
          `;
          
          // Clear timeline
          document.getElementById('timelineEvents').innerHTML = '';
          
          // Show error in the error message area too
          showError(error.message || 'An error occurred while tracking the document');
        });
    }

    document.getElementById('documentCode').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        trackDocument();
      }
    });
    
    function showError(message) {
      const errorElement = document.getElementById('errorMessage');
      errorElement.textContent = message;
      errorElement.classList.remove('hidden');
    }
    
    function hideError() {
      const errorElement = document.getElementById('errorMessage');
      errorElement.textContent = '';
      errorElement.classList.add('hidden');
    }

    function displayTimeline(events, container) {
      // Clear previous content
      container.innerHTML = '';
      
      // No events to display
      if (!events || events.length === 0) {
        container.innerHTML = `
          <div class="text-center py-8">
            <p class="text-gray-600">No workflow steps available.</p>
          </div>
        `;
        return;
      }
      
      // Sort events by step_order if available, otherwise use the original order
      if (events[0].step_order) {
        events.sort((a, b) => a.step_order - b.step_order);
      }
      
      // Build the timeline
      events.forEach((event, index) => {
        let iconClass = 'bg-gray-200 text-gray-500';
        let borderStyle = '';
        let bgColor = 'bg-white';
        
        // Set colors based on event type/status
        if (event.type === 'success' || event.status === 'APPROVED' || event.status.includes('APPROVE')) {
          iconClass = 'bg-green-600 text-white';
          borderStyle = 'border-l-4 border-green-600';
          bgColor = 'bg-green-50';
        } else if (event.type === 'error' || event.status === 'REJECTED') {
          iconClass = 'bg-red-500 text-white';
          borderStyle = 'border-l-4 border-red-500';
          bgColor = 'bg-red-50';
        } else if (event.type === 'current' || event.status === 'CURRENT' || event.current) {
          iconClass = 'bg-yellow-500 text-white';
          borderStyle = 'border-l-4 border-yellow-500';
          bgColor = 'bg-yellow-50';
        } else if (event.status === 'PENDING') {
          iconClass = 'bg-orange-500 text-white';
          borderStyle = 'border-l-4 border-orange-500';
          bgColor = 'bg-orange-50';
        } else if (event.status === 'ON_HOLD') {
          iconClass = 'bg-blue-500 text-white';
          borderStyle = 'border-l-4 border-blue-500';
          bgColor = 'bg-blue-50';
        }
        
        // Create the timeline event
        const eventElement = document.createElement('div');
        eventElement.className = 'flex items-start mb-6';
        
        // Standardize status display - ensure "APPROVE" is shown as "APPROVED"
        let displayStatus = event.status;
        if (event.status && event.status.includes('APPROVE') && !event.status.endsWith('D')) {
          displayStatus = 'APPROVED';
        }
        
        eventElement.innerHTML = `
          <div class="flex items-center justify-center">
            <div class="flex-shrink-0 z-10">
              <div class="flex items-center justify-center h-10 w-10 rounded-full ${iconClass}">
                ${index + 1}
              </div>
            </div>
          </div>
          <div class="flex-1 ml-4 ${bgColor} rounded-lg p-4 ${borderStyle}">
            <div class="font-medium ${event.type === 'current' || event.current ? 'text-yellow-800 text-lg' : 'text-gray-900'}">Step ${index + 1}: ${event.office_name || ''} - ${displayStatus}</div>
            <div class="text-sm ${event.type === 'upcoming' ? 'text-gray-400' : (event.type === 'current' ? 'text-yellow-700' : 'text-gray-600')}">${event.description}</div>
            <div class="text-xs text-gray-500 mt-1">${event.timestamp}</div>
            ${event.comments ? `<div class="mt-2 p-2 bg-gray-50 rounded text-sm text-gray-700 border-l-2 border-gray-300"><strong>Comments:</strong> ${event.comments}</div>` : ''}
          </div>
        `;
        
        container.appendChild(eventElement);
      });
    }
  </script>
</body>
</html>