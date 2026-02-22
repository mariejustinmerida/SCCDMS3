/**
 * Document Content Extractor
 * Handles extraction of document content from various sources
 */

class DocumentContentExtractor {
    constructor(options = {}) {
        this.options = {
            apiEndpoint: options.apiEndpoint || '../includes/document_extractor.php',
            debug: options.debug || false,
            ...options
        };
        
        this.documentId = null;
        this.documentType = null;
    }
    
    /**
     * Extract content from a document
     * @param {string} documentId - The document ID
     * @param {string} documentType - The document type (pdf, docx, etc.)
     * @returns {Promise} - Promise that resolves with the document content
     */
    async extractContent(documentId, documentType) {
        this.documentId = documentId;
        this.documentType = documentType;
        
        try {
            // Try different extraction methods based on document type
            if (documentType === 'google_docs') {
                return await this.extractGoogleDocsContent(documentId);
            } else if (documentType === 'pdf') {
                return await this.extractPdfContent(documentId);
            } else if (documentType === 'docx') {
                return await this.extractDocxContent(documentId);
            } else {
                // Default extraction method
                return await this.extractGenericContent(documentId);
            }
        } catch (error) {
            console.error('Error extracting document content:', error);
            throw error;
        }
    }
    
    /**
     * Extract content from Google Docs
     * @param {string} documentId - The Google Docs document ID
     * @returns {Promise} - Promise that resolves with the document content
     */
    async extractGoogleDocsContent(documentId) {
        try {
            const response = await fetch(`${this.options.apiEndpoint}?type=google_docs&id=${encodeURIComponent(documentId)}`);
            const data = await response.json();
            
            if (data.success) {
                return data.content;
            } else {
                throw new Error(data.error || 'Failed to extract Google Docs content');
            }
        } catch (error) {
            console.error('Error extracting Google Docs content:', error);
            throw error;
        }
    }
    
    /**
     * Extract content from PDF
     * @param {string} documentId - The PDF document ID
     * @returns {Promise} - Promise that resolves with the document content
     */
    async extractPdfContent(documentId) {
        try {
            const response = await fetch(`${this.options.apiEndpoint}?type=pdf&id=${encodeURIComponent(documentId)}`);
            const data = await response.json();
            
            if (data.success) {
                return data.content;
            } else {
                throw new Error(data.error || 'Failed to extract PDF content');
            }
        } catch (error) {
            console.error('Error extracting PDF content:', error);
            throw error;
        }
    }
    
    /**
     * Extract content from DOCX
     * @param {string} documentId - The DOCX document ID
     * @returns {Promise} - Promise that resolves with the document content
     */
    async extractDocxContent(documentId) {
        try {
            const response = await fetch(`${this.options.apiEndpoint}?type=docx&id=${encodeURIComponent(documentId)}`);
            const data = await response.json();
            
            if (data.success) {
                return data.content;
            } else {
                throw new Error(data.error || 'Failed to extract DOCX content');
            }
        } catch (error) {
            console.error('Error extracting DOCX content:', error);
            throw error;
        }
    }
    
    /**
     * Extract content from any document type
     * @param {string} documentId - The document ID
     * @returns {Promise} - Promise that resolves with the document content
     */
    async extractGenericContent(documentId) {
        try {
            const response = await fetch(`${this.options.apiEndpoint}?id=${encodeURIComponent(documentId)}`);
            const data = await response.json();
            
            if (data.success) {
                return data.content;
            } else {
                throw new Error(data.error || 'Failed to extract document content');
            }
        } catch (error) {
            console.error('Error extracting document content:', error);
            throw error;
        }
    }
    
    /**
     * Show paste text modal for manual content entry
     * @param {Function} callback - Callback function to handle pasted text
     */
    showPasteTextModal(callback) {
        // Create modal container
        const modalContainer = document.createElement('div');
        modalContainer.className = 'fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50';
        modalContainer.id = 'paste-text-modal';
        
        // Create modal content
        modalContainer.innerHTML = `
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl mx-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-gray-900">Paste Document Text</h3>
                    <button id="close-modal-btn" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2">Paste the document text below:</p>
                    <textarea id="pasted-text" class="w-full h-64 p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Paste document text here..."></textarea>
                </div>
                <div class="flex justify-end">
                    <button id="cancel-paste-btn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 mr-2">Cancel</button>
                    <button id="submit-paste-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Submit</button>
                </div>
            </div>
        `;
        
        // Add modal to document
        document.body.appendChild(modalContainer);
        
        // Add event listeners
        document.getElementById('close-modal-btn').addEventListener('click', () => {
            modalContainer.remove();
        });
        
        document.getElementById('cancel-paste-btn').addEventListener('click', () => {
            modalContainer.remove();
        });
        
        document.getElementById('submit-paste-btn').addEventListener('click', () => {
            const pastedText = document.getElementById('pasted-text').value;
            if (pastedText.trim()) {
                if (typeof callback === 'function') {
                    callback(pastedText);
                }
            }
            modalContainer.remove();
        });
    }
    
    /**
     * Get document type from file extension
     * @param {string} filename - The filename
     * @returns {string} - The document type
     */
    static getDocumentTypeFromFilename(filename) {
        if (!filename) return 'unknown';
        
        const extension = filename.split('.').pop().toLowerCase();
        
        switch (extension) {
            case 'pdf':
                return 'pdf';
            case 'docx':
            case 'doc':
                return 'docx';
            case 'txt':
                return 'text';
            case 'html':
            case 'htm':
                return 'html';
            default:
                return 'unknown';
        }
    }
}

// Make available globally
window.DocumentContentExtractor = DocumentContentExtractor; 