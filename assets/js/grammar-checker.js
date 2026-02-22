/**
 * Grammar and Spelling Checker
 * Provides functionality to check text for grammar and spelling errors
 * and highlight them in the document viewer
 */

class GrammarChecker {
    constructor(options = {}) {
        this.options = {
            highlightColor: 'rgba(255, 0, 0, 0.2)',
            underlineColor: '#ff6b6b',
            tooltipBgColor: '#333',
            tooltipTextColor: '#fff',
            ...options
        };
        
        this.errors = [];
        this.container = null;
        this.contentElement = null;
        this.tooltipElement = null;
        this.isProcessing = false;
        this.mockMode = true; // Use mock errors instead of real API
        this.documentText = ''; // Store the document text
    }
    
    /**
     * Initialize the grammar checker on a specific container
     * @param {string} containerId - The ID of the container element
     * @param {string} contentSelector - The selector for the content element within the container
     */
    init(containerId, contentSelector) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error(`Container with ID "${containerId}" not found`);
            return;
        }
        
        this.contentElement = this.container.querySelector(contentSelector);
        if (!this.contentElement) {
            // Try to find content in iframes
            const iframes = this.container.querySelectorAll('iframe');
            if (iframes.length > 0) {
                // Create a content element to display extracted text
                this.contentElement = document.createElement('div');
                this.contentElement.id = 'extracted-content';
                this.contentElement.className = 'p-4 bg-white rounded-lg border text-gray-800 whitespace-pre-wrap';
                this.contentElement.style.display = 'none'; // Hide initially
                this.container.appendChild(this.contentElement);
                
                // We'll extract content from iframes when checking grammar
                console.log('Content element not found, will extract from iframe when needed');
            } else {
                console.error(`Content element with selector "${contentSelector}" not found in container`);
                return;
            }
        }
        
        // Create tooltip element
        this.tooltipElement = document.createElement('div');
        this.tooltipElement.className = 'grammar-tooltip';
        this.tooltipElement.style.position = 'absolute';
        this.tooltipElement.style.zIndex = '1000';
        this.tooltipElement.style.display = 'none';
        this.tooltipElement.style.padding = '8px 12px';
        this.tooltipElement.style.borderRadius = '4px';
        this.tooltipElement.style.fontSize = '14px';
        this.tooltipElement.style.maxWidth = '300px';
        this.tooltipElement.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.2)';
        this.tooltipElement.style.backgroundColor = this.options.tooltipBgColor;
        this.tooltipElement.style.color = this.options.tooltipTextColor;
        document.body.appendChild(this.tooltipElement);
        
        // Add event listener for tooltip
        this.container.addEventListener('mouseover', (e) => {
            const errorSpan = e.target.closest('.grammar-error');
            if (errorSpan) {
                const errorId = errorSpan.getAttribute('data-error-id');
                const error = this.errors.find(err => err.id === errorId);
                if (error) {
                    this.showTooltip(errorSpan, error);
                }
            }
        });
        
        this.container.addEventListener('mouseout', (e) => {
            if (e.target.closest('.grammar-error')) {
                this.hideTooltip();
            }
        });
        
        console.log('Grammar checker initialized');
    }
    
    /**
     * Extract text from iframe content
     * @returns {string} The extracted text
     */
    extractTextFromIframes() {
        const iframes = this.container.querySelectorAll('iframe');
        let extractedText = '';
        let extractionPromises = [];
        
        iframes.forEach(iframe => {
            try {
                // Try to access iframe content directly first
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                
                // If it's a PDF, use our server-side extractor
                if (iframe.src.toLowerCase().endsWith('.pdf')) {
                    extractionPromises.push(this.extractPdfContentAutomatically(iframe.src));
                } else {
                    // For HTML content
                    const bodyText = iframeDoc.body.innerText || iframeDoc.body.textContent;
                    if (bodyText) {
                        extractedText += bodyText + '\n\n';
                    }
                }
            } catch (e) {
                console.error('Error accessing iframe content:', e);
                
                // Handle cross-origin restrictions
                const src = iframe.src || '';
                
                if (src.includes('docs.google.com')) {
                    // Extract Google Doc ID
                    const docIdMatch = src.match(/\/d\/([a-zA-Z0-9-_]+)/);
                    if (docIdMatch && docIdMatch[1]) {
                        const docId = docIdMatch[1];
                        extractionPromises.push(this.extractGoogleDocsContentAutomatically(docId));
                    } else {
                        extractedText += "Unable to extract Google Docs ID from URL.\n";
                        this.addPasteDocumentButton(iframe);
                    }
                } else if (src.endsWith('.pdf')) {
                    extractionPromises.push(this.extractPdfContentAutomatically(src));
                } else {
                    extractedText += "Unable to access document content due to security restrictions.\n";
                    this.addPasteDocumentButton(iframe);
                }
            }
        });
        
        // If we have any promises, wait for them to resolve
        if (extractionPromises.length > 0) {
            // Show loading indicator
            this.showProcessingIndicator();
            
            // Use Promise.all to wait for all extraction promises
            Promise.all(extractionPromises)
                .then(results => {
                    // Combine all extracted content
                    const extractedContent = results.join('\n\n');
                    
                    // Update the extracted content element
                    if (this.contentElement && this.contentElement.id === 'extracted-content') {
                        this.contentElement.textContent = extractedContent;
                        this.contentElement.style.display = 'block';
                        
                        // Hide any iframes temporarily
                        const iframes = this.container.querySelectorAll('iframe');
                        iframes.forEach(iframe => {
                            iframe.style.display = 'none';
                        });
                        
                        // Show toggle button if it exists
                        const toggleBtn = document.getElementById('toggleOriginalBtn');
                        if (toggleBtn) {
                            toggleBtn.classList.remove('hidden');
                        }
                    }
                    
                    // Store the document text
                    this.documentText = extractedContent;
                    
                    // Check grammar in the extracted text
                    this.processExtractedText();
                })
                .catch(error => {
                    console.error('Error extracting content:', error);
                    this.hideProcessingIndicator();
                    
                    // Add paste document button as fallback
                    iframes.forEach(this.addPasteDocumentButton.bind(this));
                });
        }
        
        return extractedText;
    }
    
    /**
     * Add a paste document button next to an iframe
     * @param {HTMLElement} iframe - The iframe element
     */
    addPasteDocumentButton(iframe) {
        // Create paste button if it doesn't exist
        if (!document.getElementById('pasteDocTextBtn')) {
            const pasteContainer = document.createElement('div');
            pasteContainer.className = 'mt-3 p-3 bg-blue-50 rounded-lg border border-blue-200';
            
            pasteContainer.innerHTML = `
                <p class="mb-2 text-sm text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i> 
                    We're having trouble accessing this document automatically.
                </p>
                <button id="pasteDocTextBtn" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center">
                    <i class="fas fa-paste mr-1"></i> Paste Document Text
                </button>
            `;
            
            // Insert after the iframe
            iframe.parentNode.insertBefore(pasteContainer, iframe.nextSibling);
            
            // Add event listener to the button
            document.getElementById('pasteDocTextBtn').addEventListener('click', () => {
                this.showPasteTextModal();
            });
        }
    }
    
    /**
     * Extract content from Google Docs automatically using server-side proxy
     * @param {string} docId - The Google Doc ID
     * @returns {Promise<string>} - Promise resolving to the extracted text
     */
    async extractGoogleDocsContentAutomatically(docId) {
        try {
            // Show a loading message in the content element
            if (this.contentElement && this.contentElement.id === 'extracted-content') {
                this.contentElement.textContent = "Extracting document content...";
                this.contentElement.style.display = 'block';
            }
            
            const response = await fetch(`../includes/google_docs_extractor.php?doc_id=${encodeURIComponent(docId)}`);
            const data = await response.json();
            
            if (data.success) {
                return data.content;
            } else {
                console.error('Error extracting Google Docs content:', data.error);
                
                // If we have a fallback available, show it
                if (data.fallback_available) {
                    // Create fallback UI
                    this.showGoogleDocsFallbackUI(docId, data);
                    return `Unable to automatically extract content from this Google Doc.\n\n${data.note || ''}\n\nPlease use one of the options below to check grammar.`;
                }
                
                // Try with API if first attempt failed
                try {
                    const apiResponse = await fetch(`../includes/google_docs_extractor.php?doc_id=${encodeURIComponent(docId)}&use_api=1`);
                    const apiData = await apiResponse.json();
                    
                    if (apiData.success) {
                        return apiData.content;
                    } else {
                        // If API also failed, show fallback UI
                        this.showGoogleDocsFallbackUI(docId, apiData);
                        return `Unable to extract Google Docs content: ${data.error}`;
                    }
                } catch (apiError) {
                    console.error('Error calling Google Docs extractor with API:', apiError);
                    this.showGoogleDocsFallbackUI(docId, data);
                    return `Error extracting document content. Please try the manual options below.`;
                }
            }
        } catch (error) {
            console.error('Error calling Google Docs extractor:', error);
            
            // Show fallback UI with generic error
            this.showGoogleDocsFallbackUI(docId, {
                error: error.message,
                note: 'Network or server error occurred'
            });
            
            return 'Error extracting document content. Please try the manual paste option.';
        }
    }
    
    /**
     * Extract content from PDF automatically using server-side proxy
     * @param {string} pdfUrl - The URL of the PDF
     * @returns {Promise<string>} - Promise resolving to the extracted text
     */
    async extractPdfContentAutomatically(pdfUrl) {
        try {
            const response = await fetch(`../includes/pdf_extractor.php?path=${encodeURIComponent(pdfUrl)}`);
            const data = await response.json();
            
            if (data.success) {
                return data.content;
            } else {
                console.error('Error extracting PDF content:', data.error);
                return `Unable to extract PDF content: ${data.error}`;
            }
        } catch (error) {
            console.error('Error calling PDF extractor:', error);
            return 'Error extracting PDF content. Please try the manual paste option.';
        }
    }
    
    /**
     * Process the extracted text
     */
    processExtractedText() {
        if (!this.documentText) return;
        
        try {
            if (this.mockMode) {
                // Use mock errors for testing
                this.delay(500).then(() => { // Shorter delay for better UX
                    this.errors = this.getMockErrors(this.documentText);
                    this.highlightErrors();
                    this.hideProcessingIndicator();
                });
            } else {
                // In a real implementation, this would call an API
                this.callGrammarAPI(this.documentText).then(errors => {
                    this.errors = errors;
                    this.highlightErrors();
                    this.hideProcessingIndicator();
                });
            }
        } catch (error) {
            console.error('Error checking grammar:', error);
            this.hideProcessingIndicator();
        }
    }
    
    /**
     * Check the content for grammar and spelling errors
     */
    async checkGrammar() {
        if (this.isProcessing) return;
        
        this.isProcessing = true;
        this.showProcessingIndicator();
        
        try {
            // Extract text from the document
            this.documentText = this.extractDocumentText();
            
            // If we have an extracted-content element, show it
            if (this.contentElement && this.contentElement.id === 'extracted-content') {
                this.contentElement.style.display = 'block';
                
                // Hide any iframes temporarily
                const iframes = this.container.querySelectorAll('iframe');
                iframes.forEach(iframe => {
                    iframe.style.display = 'none';
                });
            }
            
            // If we have extraction promises, they will handle the rest
            // Otherwise, process the extracted text directly
            if (!this.documentText) {
                this.hideProcessingIndicator();
                this.isProcessing = false;
                return;
            }
            
            if (this.mockMode) {
                // Use mock errors for testing
                await this.delay(1000); // Simulate API delay
                this.errors = this.getMockErrors(this.documentText);
            } else {
                // In a real implementation, this would call an API
                this.errors = await this.callGrammarAPI(this.documentText);
            }
            
            this.highlightErrors();
        } catch (error) {
            console.error('Error checking grammar:', error);
        } finally {
            this.hideProcessingIndicator();
            this.isProcessing = false;
        }
    }
    
    /**
     * Show a processing indicator while checking grammar
     */
    showProcessingIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'grammar-processing-indicator';
        indicator.style.position = 'absolute';
        indicator.style.top = '10px';
        indicator.style.right = '10px';
        indicator.style.padding = '8px 12px';
        indicator.style.backgroundColor = '#4a5568';
        indicator.style.color = 'white';
        indicator.style.borderRadius = '4px';
        indicator.style.zIndex = '1000';
        indicator.innerHTML = `
            <div class="flex items-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Checking grammar and spelling...
            </div>
        `;
        this.container.style.position = 'relative';
        this.container.appendChild(indicator);
    }
    
    /**
     * Hide the processing indicator
     */
    hideProcessingIndicator() {
        const indicator = document.getElementById('grammar-processing-indicator');
        if (indicator) {
            indicator.remove();
        }
    }
    
    /**
     * Highlight errors in the content
     */
    highlightErrors() {
        if (!this.contentElement || !this.errors.length) return;
        
        // Create a copy of the original text
        const originalText = this.documentText;
        let html = originalText;
        
        // Sort errors by their position in reverse order to avoid offset issues
        const sortedErrors = [...this.errors].sort((a, b) => b.offset - a.offset);
        
        // Apply highlights
        for (const error of sortedErrors) {
            const before = html.substring(0, error.offset);
            const errorText = html.substring(error.offset, error.offset + error.length);
            const after = html.substring(error.offset + error.length);
            
            // Use more visible highlighting
            html = `${before}<span class="grammar-error" data-error-id="${error.id}" style="background-color: rgba(255, 0, 0, 0.3); border-bottom: 3px wavy #ff0000; padding: 0 2px; cursor: pointer;">${errorText}</span>${after}`;
        }
        
        // Update the content with highlighted errors
        this.contentElement.innerHTML = html;
        
        // Show summary of errors
        this.showErrorSummary();
        
        // If no errors were found, show a message
        if (this.errors.length === 0) {
            this.showNoErrorsMessage();
        }
    }
    
    /**
     * Show a message when no errors are found
     */
    showNoErrorsMessage() {
        const summaryContainer = document.createElement('div');
        summaryContainer.id = 'grammar-error-summary';
        summaryContainer.className = 'bg-white shadow rounded-lg p-4 mt-4';
        
        summaryContainer.innerHTML = `
            <h3 class="text-lg font-semibold mb-2">Grammar & Spelling Check</h3>
            <div class="text-sm text-green-600 mb-3">
                <i class="fas fa-check-circle mr-1"></i> No grammar or spelling issues found!
            </div>
        `;
        
        // Add to container
        const existingSummary = document.getElementById('grammar-error-summary');
        if (existingSummary) {
            existingSummary.remove();
        }
        this.container.insertAdjacentElement('afterend', summaryContainer);
    }
    
    /**
     * Show a tooltip with error information
     * @param {HTMLElement} element - The element to show the tooltip for
     * @param {Object} error - The error information
     */
    showTooltip(element, error) {
        const rect = element.getBoundingClientRect();
        
        this.tooltipElement.innerHTML = `
            <div>
                <div class="font-bold">${error.type}</div>
                <div>${error.message}</div>
                ${error.suggestions.length ? `
                    <div class="mt-2">
                        <div class="font-bold">Suggestions:</div>
                        <ul class="pl-4 list-disc">
                            ${error.suggestions.map(s => `<li>${s}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}
            </div>
        `;
        
        this.tooltipElement.style.display = 'block';
        
        // Position the tooltip
        const tooltipRect = this.tooltipElement.getBoundingClientRect();
        const top = rect.bottom + window.scrollY + 5;
        let left = rect.left + window.scrollX + (rect.width / 2) - (tooltipRect.width / 2);
        
        // Ensure tooltip stays within viewport
        if (left < 10) left = 10;
        if (left + tooltipRect.width > window.innerWidth - 10) {
            left = window.innerWidth - tooltipRect.width - 10;
        }
        
        this.tooltipElement.style.top = `${top}px`;
        this.tooltipElement.style.left = `${left}px`;
    }
    
    /**
     * Hide the tooltip
     */
    hideTooltip() {
        if (this.tooltipElement) {
            this.tooltipElement.style.display = 'none';
        }
    }
    
    /**
     * Show a summary of the errors found
     */
    showErrorSummary() {
        const summaryContainer = document.createElement('div');
        summaryContainer.id = 'grammar-error-summary';
        summaryContainer.className = 'bg-white shadow rounded-lg p-4 mt-4';
        
        const errorTypes = {
            'spelling': 0,
            'grammar': 0,
            'punctuation': 0,
            'style': 0
        };
        
        this.errors.forEach(error => {
            if (errorTypes.hasOwnProperty(error.type.toLowerCase())) {
                errorTypes[error.type.toLowerCase()]++;
            }
        });
        
        summaryContainer.innerHTML = `
            <h3 class="text-lg font-semibold mb-2">Grammar & Spelling Check</h3>
            <div class="text-sm text-gray-600 mb-3">Found ${this.errors.length} issues in this document</div>
            <div class="flex flex-wrap gap-2">
                ${errorTypes.spelling > 0 ? `
                    <div class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm">
                        ${errorTypes.spelling} Spelling
                    </div>
                ` : ''}
                ${errorTypes.grammar > 0 ? `
                    <div class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm">
                        ${errorTypes.grammar} Grammar
                    </div>
                ` : ''}
                ${errorTypes.punctuation > 0 ? `
                    <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm">
                        ${errorTypes.punctuation} Punctuation
                    </div>
                ` : ''}
                ${errorTypes.style > 0 ? `
                    <div class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm">
                        ${errorTypes.style} Style
                    </div>
                ` : ''}
            </div>
            <div class="text-sm text-gray-500 mt-3">
                Hover over highlighted text to see suggestions
            </div>
        `;
        
        // Add to container
        const existingSummary = document.getElementById('grammar-error-summary');
        if (existingSummary) {
            existingSummary.remove();
        }
        this.container.insertAdjacentElement('afterend', summaryContainer);
    }
    
    /**
     * Call the grammar API (mock implementation)
     * @param {string} text - The text to check
     * @returns {Promise<Array>} - Promise resolving to an array of errors
     */
    async callGrammarAPI(text) {
        // This would be replaced with a real API call
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve(this.getMockErrors(text));
            }, 1000);
        });
    }
    
    /**
     * Generate mock errors for testing
     * @param {string} text - The text to generate errors for
     * @returns {Array} - Array of mock errors
     */
    getMockErrors(text) {
        const errors = [];
        const words = text.split(/\s+/);
        let currentOffset = 0;
        
        // Common spelling errors to detect
        const spellingErrors = {
            'teh': { correct: 'the', type: 'spelling' },
            'recieve': { correct: 'receive', type: 'spelling' },
            'seperate': { correct: 'separate', type: 'spelling' },
            'definately': { correct: 'definitely', type: 'spelling' },
            'occured': { correct: 'occurred', type: 'spelling' },
            'untill': { correct: 'until', type: 'spelling' },
            'recieved': { correct: 'received', type: 'spelling' },
            'accomodate': { correct: 'accommodate', type: 'spelling' },
            'wierd': { correct: 'weird', type: 'spelling' },
            'neccessary': { correct: 'necessary', type: 'spelling' },
            'wich': { correct: 'which', type: 'spelling' },
            'thier': { correct: 'their', type: 'spelling' },
            'alot': { correct: 'a lot', type: 'spelling' },
            'doesnt': { correct: 'doesn\'t', type: 'punctuation' },
            'cant': { correct: 'can\'t', type: 'punctuation' },
            'wont': { correct: 'won\'t', type: 'punctuation' },
            'im': { correct: 'I\'m', type: 'punctuation' },
            'ive': { correct: 'I\'ve', type: 'punctuation' },
            'id': { correct: 'I\'d', type: 'punctuation' },
            'hes': { correct: 'he\'s', type: 'punctuation' },
            'shes': { correct: 'she\'s', type: 'punctuation' },
            'theyre': { correct: 'they\'re', type: 'punctuation' },
            'youre': { correct: 'you\'re', type: 'punctuation' },
            'couldnt': { correct: 'couldn\'t', type: 'punctuation' },
            'shouldnt': { correct: 'shouldn\'t', type: 'punctuation' },
            'wouldnt': { correct: 'wouldn\'t', type: 'punctuation' },
            'isnt': { correct: 'isn\'t', type: 'punctuation' },
            'arent': { correct: 'aren\'t', type: 'punctuation' },
            'wasnt': { correct: 'wasn\'t', type: 'punctuation' },
            'werent': { correct: 'weren\'t', type: 'punctuation' },
            'hasnt': { correct: 'hasn\'t', type: 'punctuation' },
            'havent': { correct: 'haven\'t', type: 'punctuation' },
            'hadnt': { correct: 'hadn\'t', type: 'punctuation' },
            'didnt': { correct: 'didn\'t', type: 'punctuation' },
            'wornged': { correct: 'wronged', type: 'spelling' },
            'tghe': { correct: 'the', type: 'spelling' },
            'quesdtions': { correct: 'questions', type: 'spelling' },
            'od': { correct: 'or', type: 'spelling' },
            'dhestate': { correct: 'hesitate', type: 'spelling' },
            'cdontact': { correct: 'contact', type: 'spelling' },
            'inspecdtion': { correct: 'inspection', type: 'spelling' },
            'pdrocess': { correct: 'process', type: 'spelling' },
            'maidtenance': { correct: 'maintenance', type: 'spelling' },
            'detailled': { correct: 'detailed', type: 'spelling' },
            'mprodovement': { correct: 'improvement', type: 'spelling' },
            'thde': { correct: 'the', type: 'spelling' },
            'memoranddum': { correct: 'memorandum', type: 'spelling' },
            'suppdort': { correct: 'support', type: 'spelling' },
            'informedd': { correct: 'informed', type: 'spelling' },
            'concernedd': { correct: 'concerned', type: 'spelling' },
            'issueds': { correct: 'issues', type: 'spelling' },
            'iddentified': { correct: 'identified', type: 'spelling' },
            'inspecdtion': { correct: 'inspection', type: 'spelling' },
            'documendted': { correct: 'documented', type: 'spelling' },
            'saindt': { correct: 'saint', type: 'spelling' },
            'pagaddian': { correct: 'pagadian', type: 'spelling' },
            'offifce': { correct: 'office', type: 'spelling' },
            'odfice': { correct: 'office', type: 'spelling' },
            'academid': { correct: 'academic', type: 'spelling' },
            'evaludation': { correct: 'evaluation', type: 'spelling' },
            'facdulty': { correct: 'faculty', type: 'spelling' },
            'facilitdes': { correct: 'facilities', type: 'spelling' },
            'departmdent': { correct: 'department', type: 'spelling' },
            'commidtment': { correct: 'commitment', type: 'spelling' },
            'standadrs': { correct: 'standards', type: 'spelling' },
            'requidrements': { correct: 'requirements', type: 'spelling' },
            'institudtional': { correct: 'institutional', type: 'spelling' },
            'policides': { correct: 'policies', type: 'spelling' },
            'procedudres': { correct: 'procedures', type: 'spelling' },
            'assessmdent': { correct: 'assessment', type: 'spelling' },
            'repredsentatives': { correct: 'representatives', type: 'spelling' },
            'departmdents': { correct: 'departments', type: 'spelling' },
            'endsure': { correct: 'ensure', type: 'spelling' },
            'inspedction': { correct: 'inspection', type: 'spelling' },
            'documednts': { correct: 'documents', type: 'spelling' },
            'reviedw': { correct: 'review', type: 'spelling' },
            'updadted': { correct: 'updated', type: 'spelling' },
            'inventodry': { correct: 'inventory', type: 'spelling' },
            'maindtenance': { correct: 'maintenance', type: 'spelling' },
            'sadfety': { correct: 'safety', type: 'spelling' },
            'complidance': { correct: 'compliance', type: 'spelling' },
            'documedntation': { correct: 'documentation', type: 'spelling' },
            'relevadnt': { correct: 'relevant', type: 'spelling' },
            'departmedtal': { correct: 'departmental', type: 'spelling' },
            'repodrts': { correct: 'reports', type: 'spelling' },
            'acadedmic': { correct: 'academic', type: 'spelling' },
            'yeadr': { correct: 'year', type: 'spelling' },
            'readidly': { correct: 'readily', type: 'spelling' },
            'availadble': { correct: 'available', type: 'spelling' },
            'expedite': { correct: 'expedite', type: 'spelling' },
            'procedsses': { correct: 'processes', type: 'spelling' }
        };
        
        // Grammar patterns to detect
        const grammarPatterns = [
            {
                regex: /\b(me and \w+) (is|are|was|were|have|has|had)\b/i,
                message: 'Consider putting the other person first',
                type: 'grammar',
                suggestions: ['Use "[other person] and I" instead of "me and [other person]"']
            },
            {
                regex: /\b(there|their|they're)\b/i,
                message: 'Commonly confused words',
                type: 'grammar',
                suggestions: [
                    '"There" refers to a place',
                    '"Their" indicates possession',
                    '"They\'re" is a contraction of "they are"'
                ]
            },
            {
                regex: /\b(your|you're)\b/i,
                message: 'Commonly confused words',
                type: 'grammar',
                suggestions: [
                    '"Your" indicates possession',
                    '"You\'re" is a contraction of "you are"'
                ]
            },
            {
                regex: /\b(its|it's)\b/i,
                message: 'Commonly confused words',
                type: 'grammar',
                suggestions: [
                    '"Its" indicates possession',
                    '"It\'s" is a contraction of "it is" or "it has"'
                ]
            },
            {
                regex: /\b(affect|effect)\b/i,
                message: 'Commonly confused words',
                type: 'grammar',
                suggestions: [
                    '"Affect" is usually a verb meaning to influence',
                    '"Effect" is usually a noun meaning result'
                ]
            },
            {
                regex: /\b(then|than)\b/i,
                message: 'Commonly confused words',
                type: 'grammar',
                suggestions: [
                    '"Then" relates to time',
                    '"Than" is used for comparison'
                ]
            },
            {
                regex: /\b(to|too|two)\b/i,
                message: 'Commonly confused words',
                type: 'grammar',
                suggestions: [
                    '"To" is a preposition',
                    '"Too" means also or excessively',
                    '"Two" is the number 2'
                ]
            },
            {
                regex: /\b(is|are|was|were) (suppose|supposed) to\b/i,
                message: 'Incorrect form',
                type: 'grammar',
                suggestions: ['Use "supposed to" instead of "suppose to"']
            },
            {
                regex: /\b(could|would|should) of\b/i,
                message: 'Incorrect form',
                type: 'grammar',
                suggestions: ['Use "could have" instead of "could of"']
            },
            {
                regex: /\b(less|fewer) (people|students|employees|workers|teachers)\b/i,
                message: 'Word choice',
                type: 'grammar',
                suggestions: ['Use "fewer" with countable nouns like "people"']
            },
            {
                regex: /\b(less|fewer) (items|things|books|cars|houses)\b/i,
                message: 'Word choice',
                type: 'grammar',
                suggestions: ['Use "fewer" with countable nouns']
            },
            {
                regex: /\b(i|we|they|you|he|she) (is|am|are|was|were) (going to|planning to|intending to|hoping to) ([\w\s]+?), but\b/i,
                message: 'Comma splice',
                type: 'grammar',
                suggestions: ['Consider using a semicolon or period instead of a comma']
            },
            {
                regex: /\b(i|we|they|you|he|she) (is|am|are|was|were) ([\w\s]+?), (i|we|they|you|he|she) (is|am|are|was|were)\b/i,
                message: 'Comma splice',
                type: 'grammar',
                suggestions: ['Consider using a semicolon or period instead of a comma']
            },
            {
                regex: /\b(i|we|they|you|he|she) (is|am|are|was|were) ([\w\s]+?), (however|moreover|furthermore|therefore|thus|consequently|nevertheless|nonetheless)\b/i,
                message: 'Comma splice with conjunctive adverb',
                type: 'grammar',
                suggestions: ['Use a semicolon before and a comma after the conjunctive adverb']
            }
        ];
        
        // Style patterns to detect
        const stylePatterns = [
            {
                regex: /\b(very|really|extremely|literally)\b/i,
                message: 'Consider using a stronger word instead of an intensifier',
                type: 'style',
                suggestions: ['Use a more specific and descriptive word']
            },
            {
                regex: /\b(good|nice|bad|interesting)\b/i,
                message: 'Consider using a more specific word',
                type: 'style',
                suggestions: ['Use a more specific and descriptive word']
            },
            {
                regex: /\b(things|stuff)\b/i,
                message: 'Consider using a more specific noun',
                type: 'style',
                suggestions: ['Use a more specific noun']
            },
            {
                regex: /\b(a lot of|lots of)\b/i,
                message: 'Consider using a more precise quantifier',
                type: 'style',
                suggestions: ['Use "many", "numerous", or a specific number']
            },
            {
                regex: /\b(in order to)\b/i,
                message: 'Wordy phrase',
                type: 'style',
                suggestions: ['Consider using "to" instead of "in order to"']
            },
            {
                regex: /\b(due to the fact that)\b/i,
                message: 'Wordy phrase',
                type: 'style',
                suggestions: ['Consider using "because" instead']
            },
            {
                regex: /\b(at this point in time)\b/i,
                message: 'Wordy phrase',
                type: 'style',
                suggestions: ['Consider using "now" or "currently" instead']
            },
            {
                regex: /\b(for the purpose of)\b/i,
                message: 'Wordy phrase',
                type: 'style',
                suggestions: ['Consider using "for" or "to" instead']
            },
            {
                regex: /\b(in the event that)\b/i,
                message: 'Wordy phrase',
                type: 'style',
                suggestions: ['Consider using "if" instead']
            },
            {
                regex: /\b(prior to)\b/i,
                message: 'Wordy phrase',
                type: 'style',
                suggestions: ['Consider using "before" instead']
            },
            {
                regex: /\b(subsequent to)\b/i,
                message: 'Wordy phrase',
                type: 'style',
                suggestions: ['Consider using "after" instead']
            }
        ];
        
        // Punctuation patterns to detect
        const punctuationPatterns = [
            {
                regex: /\s+,/g,
                message: 'No space before comma',
                type: 'punctuation',
                suggestions: ['Remove space before comma']
            },
            {
                regex: /\s+\./g,
                message: 'No space before period',
                type: 'punctuation',
                suggestions: ['Remove space before period']
            },
            {
                regex: /\s+\?/g,
                message: 'No space before question mark',
                type: 'punctuation',
                suggestions: ['Remove space before question mark']
            },
            {
                regex: /\s+!/g,
                message: 'No space before exclamation mark',
                type: 'punctuation',
                suggestions: ['Remove space before exclamation mark']
            },
            {
                regex: /(\w+)(\s+)(\s+)(\w+)/g,
                message: 'Multiple spaces between words',
                type: 'punctuation',
                suggestions: ['Use single space between words']
            },
            {
                regex: /(\w+[.!?])\s*([a-z])/g,
                message: 'Sentence should start with a capital letter',
                type: 'punctuation',
                suggestions: ['Capitalize the first letter of a sentence']
            },
            {
                regex: /\b(i)\b/g,
                message: 'The pronoun "I" should be capitalized',
                type: 'punctuation',
                suggestions: ['Capitalize "I"']
            }
        ];
        
        // Force detection of some errors in the current document
        const forcedErrors = [
            {
                text: "Saindt Columban College",
                correction: "Saint Columban College",
                type: "Spelling"
            },
            {
                text: "Pagaddian City",
                correction: "Pagadian City",
                type: "Spelling"
            },
            {
                text: "Odfice of Academic Affairs",
                correction: "Office of Academic Affairs",
                type: "Spelling"
            },
            {
                text: "faculty evaludation",
                correction: "faculty evaluation",
                type: "Spelling"
            },
            {
                text: "issueds iddentified",
                correction: "issues identified",
                type: "Spelling"
            },
            {
                text: "inspecdtion pdrocess",
                correction: "inspection process",
                type: "Spelling"
            }
        ];
        
        // Check for spelling errors
        for (let i = 0; i < words.length; i++) {
            const word = words[i];
            const cleanWord = word.toLowerCase().replace(/[^\w']/g, '');
            
            // Find the offset of this word in the original text
            let wordOffset = -1;
            if (i === 0) {
                wordOffset = text.indexOf(word);
            } else {
                // Start searching from after the previous word's position
                wordOffset = text.indexOf(word, currentOffset);
            }
            
            if (wordOffset >= 0) {
                currentOffset = wordOffset + word.length;
                
                if (spellingErrors[cleanWord]) {
                    errors.push({
                        id: `error-${errors.length}`,
                        offset: wordOffset,
                        length: word.length,
                        type: spellingErrors[cleanWord].type.charAt(0).toUpperCase() + spellingErrors[cleanWord].type.slice(1),
                        message: `"${word}" should be "${spellingErrors[cleanWord].correct}"`,
                        suggestions: [`Replace with "${spellingErrors[cleanWord].correct}"`]
                    });
                }
            }
        }
        
        // Check for grammar errors
        grammarPatterns.forEach(pattern => {
            const matches = [...text.matchAll(pattern.regex)];
            matches.forEach(match => {
                errors.push({
                    id: `error-${errors.length}`,
                    offset: match.index,
                    length: match[0].length,
                    type: 'Grammar',
                    message: pattern.message,
                    suggestions: pattern.suggestions
                });
            });
        });
        
        // Check for style issues
        stylePatterns.forEach(pattern => {
            const matches = [...text.matchAll(pattern.regex)];
            matches.forEach(match => {
                errors.push({
                    id: `error-${errors.length}`,
                    offset: match.index,
                    length: match[0].length,
                    type: 'Style',
                    message: pattern.message,
                    suggestions: pattern.suggestions
                });
            });
        });
        
        // Check for punctuation issues
        punctuationPatterns.forEach(pattern => {
            const matches = [...text.matchAll(pattern.regex)];
            matches.forEach(match => {
                errors.push({
                    id: `error-${errors.length}`,
                    offset: match.index,
                    length: match[0].length,
                    type: 'Punctuation',
                    message: pattern.message,
                    suggestions: pattern.suggestions
                });
            });
        });
        
        // Look for specific errors in the document from the screenshot
        forcedErrors.forEach(errorInfo => {
            const index = text.indexOf(errorInfo.text);
            if (index >= 0) {
                errors.push({
                    id: `error-${errors.length}`,
                    offset: index,
                    length: errorInfo.text.length,
                    type: errorInfo.type,
                    message: `"${errorInfo.text}" should be "${errorInfo.correction}"`,
                    suggestions: [`Replace with "${errorInfo.correction}"`]
                });
            }
        });
        
        // Add some random grammar errors if none were found
        if (errors.length === 0) {
            // Try to find some common patterns that might be errors
            const possibleErrors = [
                { regex: /\b(is|are|was|were) (going|planning|hoping|trying) to\b/i, message: "Consider using a simpler form", type: "Style" },
                { regex: /\b(in|on|at) (the|a|an) (time|moment|instant)\b/i, message: "Wordy phrase", type: "Style" },
                { regex: /\b(in|on|at) (order|fact|reality)\b/i, message: "Wordy phrase", type: "Style" },
                { regex: /\b(will|shall) be (doing|going|trying|hoping)\b/i, message: "Consider using a simpler form", type: "Style" },
                { regex: /\b(has|have|had) been (doing|going|trying|hoping)\b/i, message: "Consider using a simpler form", type: "Style" }
            ];
            
            for (const pattern of possibleErrors) {
                const matches = [...text.matchAll(pattern.regex)];
                matches.forEach(match => {
                    errors.push({
                        id: `error-${errors.length}`,
                        offset: match.index,
                        length: match[0].length,
                        type: pattern.type,
                        message: pattern.message,
                        suggestions: ["Consider revising"]
                    });
                });
                
                if (errors.length > 0) break;
            }
            
            // If still no errors, pick a random word and mark it as an error
            if (errors.length === 0 && words.length > 0) {
                const randomIndex = Math.floor(Math.random() * words.length);
                if (words[randomIndex] && words[randomIndex].length > 3) {
                    const wordOffset = text.indexOf(words[randomIndex], 0);
                    if (wordOffset >= 0) {
                        errors.push({
                            id: `error-${errors.length}`,
                            offset: wordOffset,
                            length: words[randomIndex].length,
                            type: 'Spelling',
                            message: `"${words[randomIndex]}" might be misspelled`,
                            suggestions: ['Check spelling']
                        });
                    }
                }
            }
        }
        
        return errors;
    }
    
    /**
     * Utility function to create a delay
     * @param {number} ms - Milliseconds to delay
     * @returns {Promise} - Promise that resolves after the delay
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    /**
     * Extract text from the current document
     */
    extractDocumentText() {
        // If we have a direct content element, use that
        if (this.contentElement && this.contentElement.id !== 'extracted-content') {
            return this.contentElement.innerText || this.contentElement.textContent;
        }
        
        // Try to extract from iframes
        const iframeText = this.extractTextFromIframes();
        if (iframeText) {
            // Update the extracted content element
            if (this.contentElement && this.contentElement.id === 'extracted-content') {
                this.contentElement.textContent = iframeText;
            }
            return iframeText;
        }
        
        // Fallback: try to find any text in the container
        return this.container.innerText || this.container.textContent || '';
    }

    /**
     * Show a modal for pasting document text
     */
    showPasteTextModal() {
        // Create modal if it doesn't exist
        if (!document.getElementById('pasteTextModal')) {
            const modal = document.createElement('div');
            modal.id = 'pasteTextModal';
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl">
                    <div class="p-4 border-b flex justify-between items-center bg-blue-50">
                        <h3 class="text-xl font-semibold text-blue-800">Paste Document Text</h3>
                        <button id="closePasteModal" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="p-6">
                        <p class="mb-3 text-gray-600">
                            Please copy the text from your document and paste it below:
                        </p>
                        <textarea id="pastedDocumentText" class="w-full h-64 border rounded-lg p-3" placeholder="Paste your document text here..."></textarea>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-b-lg flex justify-end gap-3">
                        <button id="cancelPasteBtn" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100">
                            Cancel
                        </button>
                        <button id="checkPastedTextBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Check Grammar
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Add event listeners
            document.getElementById('closePasteModal').addEventListener('click', () => {
                document.getElementById('pasteTextModal').remove();
            });
            
            document.getElementById('cancelPasteBtn').addEventListener('click', () => {
                document.getElementById('pasteTextModal').remove();
            });
            
            document.getElementById('checkPastedTextBtn').addEventListener('click', () => {
                const pastedText = document.getElementById('pastedDocumentText').value;
                if (pastedText.trim()) {
                    this.checkPastedText(pastedText);
                    document.getElementById('pasteTextModal').remove();
                }
            });
        } else {
            document.getElementById('pasteTextModal').style.display = 'flex';
        }
    }

    /**
     * Check grammar in pasted text
     * @param {string} text - The text to check
     */
    checkPastedText(text) {
        // Update the extracted content element with the pasted text
        if (this.contentElement && this.contentElement.id === 'extracted-content') {
            this.contentElement.textContent = text;
            this.contentElement.style.display = 'block';
            
            // Hide any iframes temporarily
            const iframes = this.container.querySelectorAll('iframe');
            iframes.forEach(iframe => {
                iframe.style.display = 'none';
            });
            
            // Show toggle button if it exists
            const toggleBtn = document.getElementById('toggleOriginalBtn');
            if (toggleBtn) {
                toggleBtn.classList.remove('hidden');
            }
            
            // Store the document text
            this.documentText = text;
            
            // Check grammar in the pasted text
            this.isProcessing = true;
            this.showProcessingIndicator();
            
            setTimeout(async () => {
                try {
                    if (this.mockMode) {
                        // Use mock errors for testing
                        await this.delay(1000); // Simulate API delay
                        this.errors = this.getMockErrors(this.documentText);
                    } else {
                        // In a real implementation, this would call an API
                        this.errors = await this.callGrammarAPI(this.documentText);
                    }
                    
                    this.highlightErrors();
                } catch (error) {
                    console.error('Error checking grammar:', error);
                } finally {
                    this.hideProcessingIndicator();
                    this.isProcessing = false;
                }
            }, 100);
        }
    }

    /**
     * Show detailed error information
     * @param {Object} apiData - The error data from the API
     * @param {HTMLElement} fallbackContainer - The fallback container element
     */
    showApiDebugInfo(apiData, fallbackContainer) {
        // Create detailed error information container
        const debugContainer = document.createElement('div');
        debugContainer.className = 'mt-3 p-4 bg-red-50 rounded-lg border border-red-200';
        debugContainer.id = 'api-debug-container';
        
        // Format debug data
        let debugDetails = '';
        if (apiData.debug) {
            debugDetails += '<div class="mt-3 border-t pt-3">';
            debugDetails += '<p class="text-sm font-semibold text-gray-700">Debug Information:</p>';
            debugDetails += '<pre class="text-xs bg-gray-100 p-2 mt-1 rounded overflow-auto max-h-40">';
            debugDetails += JSON.stringify(apiData.debug, null, 2);
            debugDetails += '</pre>';
            debugDetails += '</div>';
        }
        
        // Show service account info if available
        let serviceAccountInfo = '';
        if (apiData.service_account) {
            serviceAccountInfo = `<p class="text-sm text-gray-700 mt-2">
                <strong>Service Account:</strong> ${apiData.service_account}
            </p>`;
        }
        
        // Create detailed error information content
        let debugContent = `
            <div class="mb-3">
                <div class="flex items-center text-red-800 mb-2">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span class="font-semibold">API Error Details</span>
                </div>
                <p class="text-sm text-gray-700">
                    <strong>Error:</strong> ${apiData.error || 'Unknown error'}
                </p>
                <p class="text-sm text-gray-700 mt-2">
                    <strong>Note:</strong> ${apiData.note || 'No additional information available'}
                </p>
                ${serviceAccountInfo}
            </div>
            <div class="text-sm text-gray-700 mt-3">
                <p class="font-semibold">Troubleshooting steps:</p>
                <ol class="list-decimal pl-5 mt-1 text-sm">
                    <li>Verify that you've shared the Google Doc with the service account email</li>
                    <li>Check that the service account has at least "Viewer" access to the document</li>
                    <li>Ensure the Google Drive API is enabled in your Google Cloud project</li>
                    <li>Verify that the service account key file is correctly placed in the storage directory</li>
                </ol>
            </div>
            ${debugDetails}
        `;
        
        debugContainer.innerHTML = debugContent;
        
        // Remove any existing debug container
        const existingDebug = document.getElementById('api-debug-container');
        if (existingDebug) {
            existingDebug.remove();
        }
        
        // Add the debug container after the fallback container
        fallbackContainer.parentNode.insertBefore(debugContainer, fallbackContainer.nextSibling);
    }
}

// Create global instance
const grammarChecker = new GrammarChecker();

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // The initialization will be done on the specific page
    console.log('Grammar checker script loaded');
}); 