/**
 * Memorandum Tracking JavaScript
 * 
 * Handles memorandum progress tracking and office read status
 */

class MemorandumTracker {
    constructor() {
        this.trackingInterval = null;
        this.currentDocumentId = null;
    }

    /**
     * Initialize memorandum tracking for a document
     */
    initTracking(documentId) {
        this.currentDocumentId = documentId;
        
        // Track initial view
        this.trackView(documentId);
        
        // Set up periodic progress updates
        this.startProgressUpdates();
        
        // Track when user leaves the page
        window.addEventListener('beforeunload', () => {
            this.trackView(documentId, 'viewed');
        });
    }

    /**
     * Track a memorandum view
     */
    trackView(documentId, action = 'viewed') {
        fetch('../api/track_memorandum_view.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                document_id: documentId,
                action: action
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateProgressDisplay(data.data);
            }
        })
        .catch(error => {
            console.error('Error tracking memorandum view:', error);
        });
    }

    /**
     * Start periodic progress updates
     */
    startProgressUpdates() {
        if (this.trackingInterval) {
            clearInterval(this.trackingInterval);
        }
        
        // Update progress every 30 seconds
        this.trackingInterval = setInterval(() => {
            this.updateProgress();
        }, 30000);
    }

    /**
     * Update progress display
     */
    updateProgress() {
        if (!this.currentDocumentId) return;
        
        fetch('../api/track_memorandum_view.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                document_id: this.currentDocumentId,
                action: 'viewed'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateProgressDisplay(data.data);
            }
        })
        .catch(error => {
            console.error('Error updating progress:', error);
        });
    }

    /**
     * Update the progress display with new data
     */
    updateProgressDisplay(data) {
        const { progress, total_offices, read_offices, offices } = data;
        
        // Update progress bar
        this.updateProgressBar(progress);
        
        // Update office status list
        this.updateOfficeStatus(offices);
        
        // Update statistics
        this.updateStatistics(total_offices, read_offices, progress);
    }

    /**
     * Update the progress bar
     */
    updateProgressBar(progress) {
        const progressBar = document.getElementById('memorandum-progress-bar');
        const progressText = document.getElementById('memorandum-progress-text');
        
        if (progressBar) {
            progressBar.style.width = progress + '%';
            progressBar.setAttribute('aria-valuenow', progress);
        }
        
        if (progressText) {
            progressText.textContent = progress + '%';
        }
    }

    /**
     * Update office status display
     */
    updateOfficeStatus(offices) {
        const officeList = document.getElementById('memorandum-office-list');
        if (!officeList) return;
        
        officeList.innerHTML = '';
        
        offices.forEach(office => {
            const officeItem = document.createElement('div');
            officeItem.className = 'flex items-center justify-between p-3 border-b border-gray-200';
            
            const statusIcon = office.is_read 
                ? '<svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>'
                : '<svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg>';
            
            const readTime = office.read_at 
                ? `<span class="text-xs text-gray-500">Read at ${new Date(office.read_at).toLocaleString()}</span>`
                : '<span class="text-xs text-gray-400">Not read yet</span>';
            
            officeItem.innerHTML = `
                <div class="flex items-center space-x-3">
                    ${statusIcon}
                    <div>
                        <div class="font-medium text-gray-900">${office.office_name}</div>
                        ${readTime}
                    </div>
                </div>
                <div class="text-sm text-gray-500">
                    ${office.is_read ? 'Read' : 'Pending'}
                </div>
            `;
            
            officeList.appendChild(officeItem);
        });
    }

    /**
     * Update statistics display
     */
    updateStatistics(totalOffices, readOffices, progress) {
        const statsContainer = document.getElementById('memorandum-stats');
        if (!statsContainer) return;
        
        statsContainer.innerHTML = `
            <div class="grid grid-cols-3 gap-4 text-center">
                <div class="bg-blue-50 p-3 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">${totalOffices}</div>
                    <div class="text-sm text-blue-500">Total Offices</div>
                </div>
                <div class="bg-green-50 p-3 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">${readOffices}</div>
                    <div class="text-sm text-green-500">Read Offices</div>
                </div>
                <div class="bg-purple-50 p-3 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">${progress}%</div>
                    <div class="text-sm text-purple-500">Progress</div>
                </div>
            </div>
        `;
    }

    /**
     * Create memorandum tracking UI
     */
    createTrackingUI(documentId) {
        const trackingContainer = document.createElement('div');
        trackingContainer.id = 'memorandum-tracking-container';
        trackingContainer.className = 'bg-white rounded-lg shadow-sm p-6 mb-6';
        
        trackingContainer.innerHTML = `
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Memorandum Distribution Tracking</h3>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-sm text-gray-500">Live tracking</span>
                </div>
            </div>
            
            <div id="memorandum-stats" class="mb-6">
                <!-- Statistics will be populated here -->
            </div>
            
            <div class="mb-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Distribution Progress</span>
                    <span id="memorandum-progress-text" class="text-sm text-gray-500">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div id="memorandum-progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
            
            <div>
                <h4 class="text-md font-medium text-gray-900 mb-3">Office Status</h4>
                <div id="memorandum-office-list" class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg">
                    <!-- Office list will be populated here -->
                </div>
            </div>
        `;
        
        return trackingContainer;
    }

    /**
     * Stop tracking
     */
    stopTracking() {
        if (this.trackingInterval) {
            clearInterval(this.trackingInterval);
            this.trackingInterval = null;
        }
        this.currentDocumentId = null;
    }
}

// Initialize memorandum tracker
const memorandumTracker = new MemorandumTracker();

// Export for use in other files
window.memorandumTracker = memorandumTracker; 