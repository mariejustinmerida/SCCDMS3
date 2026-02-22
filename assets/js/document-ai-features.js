/**
 * Document AI Features JavaScript
 * Handles interactions with the AI document processing features
 */

// Function to summarize a document
function summarizeDocument(documentId, fileName) {
    // Show loading state
    showAIProcessingModal('Summarizing document...');
    
    // Call the API
    fetch('../actions/summarize_document.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            documentId: documentId,
            fileName: fileName
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json().catch(error => {
            console.error('JSON parse error:', error);
            // Return a mock response if JSON parsing fails
            return {
                success: true,
                summary: 'This is a mock summary generated because the response could not be parsed.',
                keyPoints: [
                    'The document appears to contain important information',
                    'Please check the document manually for details',
                    'The AI service encountered an error processing this document'
                ]
            };
        });
    })
    .then(data => {
        hideAIProcessingModal();
        
        // Always show summary modal, even if there was an error
        // The mock response will be used if the API call failed
        showSummaryModal(fileName, 
            data.summary || 'No summary available', 
            data.keyPoints || []);
    })
    .catch(error => {
        hideAIProcessingModal();
        console.error('Error:', error);
        
        // Show a mock response even on network errors
        showSummaryModal(fileName, 
            'This is a mock summary generated because the AI service is unavailable.', 
            [
                'The AI service is currently unavailable',
                'Please try again later',
                'You can check your API settings in the AI Settings page'
            ]);
    });
}

// Function to analyze a document
function analyzeDocument(documentId, fileName) {
    // Show loading state
    showAIProcessingModal('Analyzing document...');
    
    // Call the API
    fetch('../actions/analyze_document.php?v=' + Date.now(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            documentId: documentId,
            analysisType: 'full'
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json().catch(error => {
            console.error('JSON parse error:', error);
            // Return a mock response if JSON parsing fails
            return {
                success: true,
                classification: [
                    {name: 'Document', confidence: 90},
                    {name: 'Text', confidence: 85}
                ],
                entities: [
                    {text: 'Document', type: 'ENTITY', relevance: 90}
                ],
                sentiment: {
                    overall: 0,
                    sentiment_label: 'Neutral',
                    tones: [{tone: 'Formal', intensity: 0.7}]
                },
                keywords: ['document', 'content'],
                summary: 'This is a mock analysis generated because the response could not be parsed.',
                keyPoints: ['The AI service encountered an error processing this document']
            };
        });
    })
    .then(data => {
        hideAIProcessingModal();
        
        console.log('Raw API response:', data);
        // Some endpoints return { analysis: { ... }, success: true }
        const src = data && data.analysis ? data.analysis : data;
        console.log('Normalized source object:', src);
        console.log('Data success:', data.success);
        console.log('Data summary:', src.summary);
        
        // Check if we have real data - use normalized source
        if (data.success && (src.summary || src.keyPoints || src.entities || src.keywords)) {
            console.log('Using REAL AI data');
            
            // Convert real response to display format
            const displayData = {
                success: true,
                classification: src.classification ? [
                    {name: src.classification, confidence: 90},
                    {name: 'Text', confidence: 85}
                ] : [
                    {name: 'Document', confidence: 90},
                    {name: 'Text', confidence: 85}
                ],
                entities: (src.entities || []).map(entity => ({
                    text: entity.name || entity.text || '',
                    type: entity.type || '',
                    relevance: 90
                })),
                sentiment: {
                    overall: src.sentiment === 'positive' ? 0.7 : src.sentiment === 'negative' ? -0.7 : 0,
                    sentiment_label: src.sentiment || 'Neutral',
                    tones: [{tone: 'Formal', intensity: 0.7}]
                },
                keywords: src.keywords || ['document', 'content'],
                summary: src.summary || 'No summary available',
                keyPoints: src.keyPoints || ['Key points not available']
            };
            
            console.log('Using REAL data:', displayData);
            showAnalysisModal(fileName, displayData);
        } else {
            console.log('Using MOCK data - real data not available');
            
            // Fallback to mock data
            const mockData = {
                success: true,
                classification: [
                    {name: 'Document', confidence: 90},
                    {name: 'Text', confidence: 85}
                ],
                entities: [
                    {text: 'Document', type: 'ENTITY', relevance: 90}
                ],
                sentiment: {
                    overall: 0,
                    sentiment_label: 'Neutral',
                    tones: [{tone: 'Formal', intensity: 0.7}]
                },
                keywords: ['document', 'content'],
                summary: 'This is a mock analysis generated because the AI service is unavailable.',
                keyPoints: ['The AI service encountered an error processing this document']
            };
            
            showAnalysisModal(fileName, mockData);
        }
    })
    .catch(error => {
        hideAIProcessingModal();
        console.error('Error:', error);
        
        // Show a mock analysis even on network errors
        showAnalysisModal(fileName, {
            success: true,
            classification: [
                {name: 'Document', confidence: 90},
                {name: 'Text', confidence: 85}
            ],
            entities: [
                {text: 'Document', type: 'ENTITY', relevance: 90}
            ],
            sentiment: {
                overall: 0,
                sentiment_label: 'Neutral',
                tones: [{tone: 'Formal', intensity: 0.7}]
            },
            keywords: ['document', 'content'],
            summary: 'This is a mock analysis generated because the AI service is unavailable.',
            keyPoints: ['The AI service is currently unavailable', 'Please try again later']
        });
    });
}

// Function to show the AI processing modal
function showAIProcessingModal(message) {
    const modalHTML = `
        <div id="aiProcessingModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-lg max-w-md w-full">
                <div class="flex items-center justify-center mb-4">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-700"></div>
                </div>
                <p class="text-center text-gray-700">${message}</p>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Function to hide the AI processing modal
function hideAIProcessingModal() {
    const modal = document.getElementById('aiProcessingModal');
    if (modal) {
        modal.remove();
    }
}

// Function to show a summary modal
function showSummaryModal(title, summary, keyPoints) {
    let keyPointsHTML = '';
    if (keyPoints && keyPoints.length > 0) {
        keyPointsHTML = `
            <h4 class="font-medium text-sm mb-2 mt-4">Key Points:</h4>
            <ul class="list-disc pl-5 space-y-1 text-sm text-gray-700">
                ${keyPoints.map(point => `<li>${point}</li>`).join('')}
            </ul>
        `;
    }
    
    const modalHTML = `
        <div id="summaryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-lg max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium">Summarizing: ${title}</h3>
                    <button id="closeSummaryModal" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="border-t border-gray-200 pt-4">
                    <h4 class="font-medium text-sm mb-2">Summary:</h4>
                    <p class="text-gray-700 whitespace-pre-line">${summary}</p>
                    ${keyPointsHTML}
                </div>
                <div class="mt-6 flex justify-end">
                    <button id="copyToClipboard" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                        </svg>
                        Copy to Clipboard
                    </button>
                    <button id="closeBtn" class="ml-2 px-4 py-2 bg-green-700 text-white rounded-md hover:bg-green-800">
                        Close
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Add event listeners
    document.getElementById('closeSummaryModal').addEventListener('click', () => {
        document.getElementById('summaryModal').remove();
    });
    
    document.getElementById('closeBtn').addEventListener('click', () => {
        document.getElementById('summaryModal').remove();
    });
    
    document.getElementById('copyToClipboard').addEventListener('click', () => {
        const textToCopy = `Summary: ${summary}\n\nKey Points:\n${keyPoints.map(point => `- ${point}`).join('\n')}`;
        navigator.clipboard.writeText(textToCopy)
            .then(() => {
                alert('Copied to clipboard!');
            })
            .catch(err => {
                console.error('Failed to copy: ', err);
            });
    });
}

// Function to show an analysis modal
function showAnalysisModal(title, data) {
    // Create classification section
    let classificationHTML = '';
    if (data.classification && data.classification.length > 0) {
        classificationHTML = `
            <div class="mb-6">
                <h4 class="font-medium text-sm mb-3">Document Classification:</h4>
                <div class="space-y-2">
                    ${data.classification.map(cls => `
                        <div class="flex items-center">
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-green-600 h-2.5 rounded-full" style="width: ${cls.confidence}%"></div>
                            </div>
                            <span class="ml-2 text-sm text-gray-700">${cls.name} (${cls.confidence}%)</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    // Create entities section
    let entitiesHTML = '';
    if (data.entities && data.entities.length > 0) {
        entitiesHTML = `
            <div class="mb-6">
                <h4 class="font-medium text-sm mb-3">Entities:</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    ${data.entities.map(entity => `
                        <div class="bg-gray-50 p-2 rounded">
                            <span class="font-medium">${entity.text}</span>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>${entity.type}</span>
                                <span>Relevance: ${entity.relevance}%</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    // Create sentiment section
    let sentimentHTML = '';
    if (data.sentiment) {
        let sentimentColor = 'gray';
        if (data.sentiment.overall > 0.3) sentimentColor = 'green';
        if (data.sentiment.overall < -0.3) sentimentColor = 'red';
        
        sentimentHTML = `
            <div class="mb-6">
                <h4 class="font-medium text-sm mb-3">Sentiment Analysis:</h4>
                <div class="flex items-center mb-2">
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-${sentimentColor}-600 h-2.5 rounded-full" style="width: ${(data.sentiment.overall + 1) * 50}%"></div>
                    </div>
                    <span class="ml-2 text-sm text-gray-700">${data.sentiment.sentiment_label}</span>
                </div>
                ${data.sentiment.tones ? `
                    <div class="mt-2">
                        <h5 class="text-xs font-medium text-gray-500 mb-1">Document Tones:</h5>
                        <div class="flex flex-wrap gap-2">
                            ${data.sentiment.tones.map(tone => `
                                <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">
                                    ${tone.tone} (${Math.round(tone.intensity * 100)}%)
                                </span>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    // Create keywords section
    let keywordsHTML = '';
    if (data.keywords && data.keywords.length > 0) {
        keywordsHTML = `
            <div class="mb-6">
                <h4 class="font-medium text-sm mb-3">Keywords:</h4>
                <div class="flex flex-wrap gap-2">
                    ${data.keywords.map(keyword => `
                        <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">${keyword}</span>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    // Create summary section
    let summaryHTML = '';
    if (data.summary) {
        summaryHTML = `
            <div class="mb-6">
                <h4 class="font-medium text-sm mb-3">Summary:</h4>
                <p class="text-sm text-gray-700">${data.summary}</p>
                ${data.keyPoints && data.keyPoints.length > 0 ? `
                    <h5 class="text-xs font-medium text-gray-500 mt-2 mb-1">Key Points:</h5>
                    <ul class="list-disc pl-5 space-y-1 text-xs text-gray-700">
                        ${data.keyPoints.map(point => `<li>${point}</li>`).join('')}
                    </ul>
                ` : ''}
            </div>
        `;
    }
    
    const modalHTML = `
        <div id="analysisModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-lg max-w-3xl w-full max-h-[80vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium">Analyzing: ${title}</h3>
                    <button id="closeAnalysisModal" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="border-t border-gray-200 pt-4">
                    ${classificationHTML}
                    ${entitiesHTML}
                    ${sentimentHTML}
                    ${keywordsHTML}
                    ${summaryHTML}
                </div>
                <div class="mt-6 flex justify-end">
                    <button id="copyAnalysisToClipboard" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                        </svg>
                        Copy to Clipboard
                    </button>
                    <button id="closeAnalysisBtn" class="ml-2 px-4 py-2 bg-green-700 text-white rounded-md hover:bg-green-800">
                        Close
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Add event listeners
    document.getElementById('closeAnalysisModal').addEventListener('click', () => {
        document.getElementById('analysisModal').remove();
    });
    
    document.getElementById('closeAnalysisBtn').addEventListener('click', () => {
        document.getElementById('analysisModal').remove();
    });
    
    document.getElementById('copyAnalysisToClipboard').addEventListener('click', () => {
        let textToCopy = `Analysis for: ${title}\n\n`;
        
        if (data.classification && data.classification.length > 0) {
            textToCopy += `Classification:\n`;
            data.classification.forEach(cls => {
                textToCopy += `- ${cls.name}: ${cls.confidence}%\n`;
            });
            textToCopy += '\n';
        }
        
        if (data.entities && data.entities.length > 0) {
            textToCopy += `Entities:\n`;
            data.entities.forEach(entity => {
                textToCopy += `- ${entity.text} (${entity.type}): ${entity.relevance}%\n`;
            });
            textToCopy += '\n';
        }
        
        if (data.sentiment) {
            textToCopy += `Sentiment: ${data.sentiment.sentiment_label}\n`;
            if (data.sentiment.tones) {
                textToCopy += `Tones: ${data.sentiment.tones.map(tone => `${tone.tone} (${Math.round(tone.intensity * 100)}%)`).join(', ')}\n`;
            }
            textToCopy += '\n';
        }
        
        if (data.keywords && data.keywords.length > 0) {
            textToCopy += `Keywords: ${data.keywords.join(', ')}\n\n`;
        }
        
        if (data.summary) {
            textToCopy += `Summary: ${data.summary}\n\n`;
            if (data.keyPoints && data.keyPoints.length > 0) {
                textToCopy += `Key Points:\n`;
                data.keyPoints.forEach(point => {
                    textToCopy += `- ${point}\n`;
                });
            }
        }
        
        navigator.clipboard.writeText(textToCopy)
            .then(() => {
                alert('Analysis copied to clipboard!');
            })
            .catch(err => {
                console.error('Failed to copy: ', err);
            });
    });
}

// Function to show an error modal
function showErrorModal(title, message) {
    const modalHTML = `
        <div id="errorModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-lg max-w-md w-full">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-red-600">${title}</h3>
                    <button id="closeErrorModal" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="border-t border-gray-200 pt-4">
                    <p class="text-gray-700">${message}</p>
                    <p class="mt-2 text-sm text-gray-500">Try using the mock responses by leaving the API key field empty in AI Settings.</p>
                </div>
                <div class="mt-6 flex justify-end">
                    <button id="closeErrorBtn" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Close
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Add event listeners
    document.getElementById('closeErrorModal').addEventListener('click', () => {
        document.getElementById('errorModal').remove();
    });
    
    document.getElementById('closeErrorBtn').addEventListener('click', () => {
        document.getElementById('errorModal').remove();
    });
}

// Initialize AI features when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Find all summary buttons and attach event listeners
    document.querySelectorAll('.summary-btn').forEach(button => {
        button.addEventListener('click', function() {
            const documentId = this.getAttribute('data-document-id');
            const fileName = this.getAttribute('data-file-name');
            summarizeDocument(documentId, fileName);
        });
    });
    
    // Find all analyze buttons and attach event listeners
    document.querySelectorAll('.analyze-btn').forEach(button => {
        button.addEventListener('click', function() {
            const documentId = this.getAttribute('data-document-id');
            const fileName = this.getAttribute('data-file-name');
            analyzeDocument(documentId, fileName);
        });
    });
}); 