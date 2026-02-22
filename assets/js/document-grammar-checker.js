/**
 * Document Grammar Checker Integration
 * Initializes and handles the grammar checker functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize grammar checker when the page loads
    initGrammarChecker();
    
    // Add event listeners for grammar check buttons
    const checkGrammarBtn = document.getElementById('check-grammar-btn');
    const showGrammarCheckBtn = document.getElementById('show-grammar-check-btn');
    
    if (checkGrammarBtn) {
        checkGrammarBtn.addEventListener('click', performGrammarCheck);
    }
    
    if (showGrammarCheckBtn) {
        showGrammarCheckBtn.addEventListener('click', showGrammarCheckResults);
    }
    
    // Add event listener for the paste document text button
    const pasteDocumentTextBtn = document.getElementById('paste-document-text-btn');
    if (pasteDocumentTextBtn) {
        pasteDocumentTextBtn.addEventListener('click', function() {
            if (window.documentExtractor) {
                window.documentExtractor.showPasteTextModal(function(text) {
                    // Update document preview with pasted text
                    const documentPreview = document.getElementById('document-preview');
                    if (documentPreview) {
                        documentPreview.innerHTML = formatDocumentText(text);
                        // Run grammar check on the pasted text
                        performGrammarCheck();
                    }
                });
            }
        });
    }
});

/**
 * Initialize the grammar checker
 */
function initGrammarChecker() {
    // Check if document preview container exists
    const documentPreview = document.getElementById('document-preview');
    if (!documentPreview) return;
    
    // Create loading indicator
    const loadingIndicator = document.createElement('div');
    loadingIndicator.id = 'grammar-check-loading';
    loadingIndicator.className = 'grammar-check-loading hidden';
    loadingIndicator.innerHTML = `
        <div class="flex items-center bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg">
            <div class="spinner mr-2"></div>
            <span>Checking grammar and spelling...</span>
        </div>
    `;
    document.body.appendChild(loadingIndicator);
    
    // Add CSS for the loading indicator
    const style = document.createElement('style');
    style.textContent = `
        .grammar-check-loading {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            transition: opacity 0.3s ease;
        }
        .grammar-check-loading.hidden {
            opacity: 0;
            pointer-events: none;
        }
        .spinner {
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 2px solid #fff;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .grammar-error {
            background-color: rgba(255, 0, 0, 0.3);
            border-bottom: 3px wavy #ff0000;
            padding: 0 2px;
            cursor: pointer;
        }
        .grammar-error-tooltip {
            position: absolute;
            background: white;
            border: 1px solid #ccc;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            z-index: 1000;
            max-width: 300px;
        }
        .grammar-error-tooltip h4 {
            margin: 0 0 5px;
            font-weight: 600;
            font-size: 16px;
        }
        .grammar-error-tooltip p {
            margin: 0 0 8px;
        }
        .grammar-error-tooltip ul {
            margin: 0;
            padding-left: 20px;
        }
        .grammar-error-tooltip button {
            background: #4a6cf7;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            margin-top: 8px;
            cursor: pointer;
        }
        .grammar-error-tooltip button:hover {
            background: #3a5ce7;
        }
    `;
    document.head.appendChild(style);
    
    // Initialize the document extractor
    window.documentExtractor = new DocumentContentExtractor({
        apiEndpoint: '../includes/document_extractor.php',
        debug: true
    });
    
    // Initialize the grammar checker instance
    window.grammarChecker = new GrammarChecker({
        container: documentPreview,
        highlightColor: 'rgba(255, 0, 0, 0.3)',
        underlineColor: '#ff0000',
        showSummary: true
    });
    
    // Check if we need to extract document content
    if (documentPreview.textContent.trim() === 'Extracting document content...' || 
        documentPreview.innerHTML.includes('We\'re having trouble accessing this document automatically')) {
        extractDocumentContent();
    }
}

/**
 * Extract document content
 */
async function extractDocumentContent() {
    if (!window.documentExtractor) return;
    
    // Show loading indicator
    const loadingIndicator = document.getElementById('grammar-check-loading');
    if (loadingIndicator) {
        loadingIndicator.classList.remove('hidden');
        loadingIndicator.querySelector('span').textContent = 'Extracting document content...';
    }
    
    try {
        // Get document ID from URL or page
        const documentId = getDocumentIdFromPage();
        if (!documentId) {
            throw new Error('Could not determine document ID');
        }
        
        // Get document type (if available)
        const documentType = getDocumentTypeFromPage();
        
        // Extract content
        const content = await window.documentExtractor.extractContent(documentId, documentType);
        
        // Update document preview with extracted content
        const documentPreview = document.getElementById('document-preview');
        if (documentPreview && content) {
            documentPreview.innerHTML = formatDocumentText(content);
            
            // Run grammar check on the extracted content
            performGrammarCheck();
        } else {
            // Show paste text button if extraction failed
            const documentPreview = document.getElementById('document-preview');
            if (documentPreview) {
                documentPreview.innerHTML = `
                    <div class="p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                        <div class="flex items-center text-yellow-800 mb-2">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span class="font-semibold">Could not extract document content automatically</span>
                        </div>
                        <p class="text-sm text-gray-700 mb-3">We couldn't automatically extract the content of this document.</p>
                        <button id="paste-document-text-btn" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center text-sm">
                            <i class="fas fa-paste mr-1"></i> Paste Document Text
                        </button>
                    </div>
                `;
                
                // Add event listener for the paste button
                document.getElementById('paste-document-text-btn').addEventListener('click', function() {
                    if (window.documentExtractor) {
                        window.documentExtractor.showPasteTextModal(function(text) {
                            // Update document preview with pasted text
                            documentPreview.innerHTML = formatDocumentText(text);
                            // Run grammar check on the pasted text
                            performGrammarCheck();
                        });
                    }
                });
            }
        }
    } catch (error) {
        console.error('Error extracting document content:', error);
        
        // Show error message
        const documentPreview = document.getElementById('document-preview');
        if (documentPreview) {
            documentPreview.innerHTML = `
                <div class="p-4 bg-red-50 rounded-lg border border-red-200">
                    <div class="flex items-center text-red-800 mb-2">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span class="font-semibold">Error extracting document content</span>
                    </div>
                    <p class="text-sm text-gray-700 mb-3">${error.message || 'An unknown error occurred while extracting document content.'}</p>
                    <button id="paste-document-text-btn" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center text-sm">
                        <i class="fas fa-paste mr-1"></i> Paste Document Text
                    </button>
                </div>
            `;
            
            // Add event listener for the paste button
            document.getElementById('paste-document-text-btn').addEventListener('click', function() {
                if (window.documentExtractor) {
                    window.documentExtractor.showPasteTextModal(function(text) {
                        // Update document preview with pasted text
                        documentPreview.innerHTML = formatDocumentText(text);
                        // Run grammar check on the pasted text
                        performGrammarCheck();
                    });
                }
            });
        }
    } finally {
        // Hide loading indicator
        hideLoadingIndicator();
    }
}

/**
 * Format document text for display
 * @param {string} text - The document text
 * @returns {string} - Formatted HTML
 */
function formatDocumentText(text) {
    if (!text) return '';
    
    // Replace newlines with <br> tags
    let formattedText = text.replace(/\n/g, '<br>');
    
    // Wrap in a div with appropriate styling
    return `<div class="document-text">${formattedText}</div>`;
}

/**
 * Get document ID from page
 * @returns {string} - Document ID
 */
function getDocumentIdFromPage() {
    // Try to get document ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    const idFromUrl = urlParams.get('id') || urlParams.get('doc_id');
    if (idFromUrl) return idFromUrl;
    
    // Try to get document ID from page elements
    const docIdElement = document.querySelector('[data-document-id]');
    if (docIdElement && docIdElement.dataset.documentId) {
        return docIdElement.dataset.documentId;
    }
    
    // Try to get from document ID field
    const docIdField = document.getElementById('document-id');
    if (docIdField && docIdField.textContent) {
        return docIdField.textContent.trim().replace(/^DOC-/, '');
    }
    
    return null;
}

/**
 * Get document type from page
 * @returns {string} - Document type
 */
function getDocumentTypeFromPage() {
    // Try to get document type from URL
    const urlParams = new URLSearchParams(window.location.search);
    const typeFromUrl = urlParams.get('type') || urlParams.get('doc_type');
    if (typeFromUrl) return typeFromUrl;
    
    // Try to get document type from page elements
    const docTypeElement = document.querySelector('[data-document-type]');
    if (docTypeElement && docTypeElement.dataset.documentType) {
        return docTypeElement.dataset.documentType;
    }
    
    return null;
}

/**
 * Perform grammar check on the document
 */
function performGrammarCheck() {
    if (!window.grammarChecker) return;
    
    // Show loading indicator
    const loadingIndicator = document.getElementById('grammar-check-loading');
    if (loadingIndicator) {
        loadingIndicator.classList.remove('hidden');
        loadingIndicator.querySelector('span').textContent = 'Checking grammar and spelling...';
    }
    
    // Extract text from the document preview
    const documentPreview = document.getElementById('document-preview');
    if (!documentPreview) {
        hideLoadingIndicator();
        return;
    }
    
    // Get the text content
    const textContent = documentPreview.innerText || documentPreview.textContent;
    
    // Perform the grammar check
    setTimeout(() => {
        window.grammarChecker.checkText(textContent);
        hideLoadingIndicator();
    }, 1000); // Simulate processing time
}

/**
 * Show grammar check results
 */
function showGrammarCheckResults() {
    if (!window.grammarChecker) return;
    
    // Toggle the visibility of grammar errors
    const errors = document.querySelectorAll('.grammar-error');
    const errorSummary = document.getElementById('grammar-error-summary');
    
    if (errors.length > 0 || errorSummary) {
        // If errors are already shown, hide them
        if (document.querySelector('.grammar-error-visible')) {
            errors.forEach(error => {
                error.classList.remove('grammar-error-visible');
            });
            if (errorSummary) {
                errorSummary.style.display = 'none';
            }
            document.getElementById('show-grammar-check-btn').textContent = 'Show Grammar Check';
        } else {
            // Otherwise show them
            errors.forEach(error => {
                error.classList.add('grammar-error-visible');
            });
            if (errorSummary) {
                errorSummary.style.display = 'block';
            }
            document.getElementById('show-grammar-check-btn').textContent = 'Hide Grammar Check';
        }
    } else {
        // If no errors have been found yet, run the check
        performGrammarCheck();
    }
}

/**
 * Hide the loading indicator
 */
function hideLoadingIndicator() {
    const loadingIndicator = document.getElementById('grammar-check-loading');
    if (loadingIndicator) {
        loadingIndicator.classList.add('hidden');
    }
} 