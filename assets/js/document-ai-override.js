/**
 * Document AI Features Override
 * This file overrides the document AI functions in documents.php to use the ones from document-ai-features.js
 */

// Wait for document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Document AI override loaded');
    
    // Add the summary and analyze classes to the buttons
    document.querySelectorAll('button').forEach(button => {
        const onclickAttr = button.getAttribute('onclick') || '';
        
        if (onclickAttr.includes('summarizeDocument(')) {
            // Extract the document ID and file name from the onclick attribute
            const match = onclickAttr.match(/summarizeDocument\((\d+),\s*['"]([^'"]+)['"]\)/);
            if (match) {
                const documentId = match[1];
                const fileName = match[2];
                
                // Add the summary-btn class and data attributes
                button.classList.add('summary-btn');
                button.setAttribute('data-document-id', documentId);
                button.setAttribute('data-file-name', fileName);
                
                // Replace the onclick handler
                button.setAttribute('onclick', `event.stopPropagation(); window.summarizeDocumentAI(${documentId}, '${fileName}')`);
            }
        }
        
        if (onclickAttr.includes('analyzeDocument(')) {
            // Extract the document ID and file name from the onclick attribute
            const match = onclickAttr.match(/analyzeDocument\((\d+),\s*['"]([^'"]+)['"]\)/);
            if (match) {
                const documentId = match[1];
                const fileName = match[2];
                
                // Add the analyze-btn class and data attributes
                button.classList.add('analyze-btn');
                button.setAttribute('data-document-id', documentId);
                button.setAttribute('data-file-name', fileName);
                
                // Replace the onclick handler
                button.setAttribute('onclick', `event.stopPropagation(); window.analyzeDocumentAI(${documentId}, '${fileName}')`);
            }
        }
    });
    
    // Create global wrapper functions that will call the AI functions
    window.summarizeDocumentAI = function(documentId, fileName) {
        console.log('Calling AI summarize for document:', documentId, fileName);
        // Import the summarizeDocument function from document-ai-features.js
        if (typeof summarizeDocument === 'function') {
            summarizeDocument(documentId, fileName);
        } else {
            console.error('summarizeDocument function not found');
            alert('Document summarization is not available. Please check the console for errors.');
        }
    };
    
    window.analyzeDocumentAI = function(documentId, fileName) {
        console.log('Calling AI analyze for document:', documentId, fileName);
        // Import the analyzeDocument function from document-ai-features.js
        if (typeof analyzeDocument === 'function') {
            analyzeDocument(documentId, fileName);
        } else {
            console.error('analyzeDocument function not found');
            alert('Document analysis is not available. Please check the console for errors.');
        }
    };
}); 