<?php
// Check if included in dashboard
if (!defined('INCLUDED_IN_DASHBOARD')) {
    require_once '../includes/config.php';
    require_once '../includes/auth_check.php';
    require_once '../includes/header.php';
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Test AI Document Features</h1>
        <a href="dashboard.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded">
            Back to Dashboard
        </a>
    </div>
    
    <div class="bg-yellow-100 p-4 mb-6 rounded-lg">
        <p class="text-yellow-800">
            <strong>Note:</strong> This page demonstrates the AI document processing features using realistic mock responses. These examples show how the system would analyze and summarize different document types such as contracts, financial reports, policies, meeting minutes, and project proposals.
        </p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Test Summary Feature -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Test Document Summary</h2>
            <p class="mb-4 text-gray-600">
                Test the document summarization feature using realistic mock responses. Each time you click the button, you'll see a summary for a randomly selected document type.
            </p>
            <div class="flex justify-center">
                <button id="testSummary" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Test Summary
                </button>
            </div>
        </div>
        
        <!-- Test Analysis Feature -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Test Document Analysis</h2>
            <p class="mb-4 text-gray-600">
                Test the document analysis feature using realistic mock responses. Each time you click the button, you'll see a detailed analysis for a randomly selected document type.
            </p>
            <div class="flex justify-center">
                <button id="testAnalysis" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                    Test Analysis
                </button>
            </div>
        </div>
    </div>
    
    <!-- Results Section -->
    <div class="mt-8">
        <h2 class="text-xl font-semibold mb-4">Test Results</h2>
        <div id="resultContainer" class="bg-white shadow-md rounded-lg p-6 min-h-[200px]">
            <p class="text-gray-500 text-center">Click one of the test buttons above to see results.</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Test summary button
    document.getElementById('testSummary').addEventListener('click', function() {
        // Show loading state
        document.getElementById('resultContainer').innerHTML = `
            <div class="flex justify-center items-center h-[200px]">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-700"></div>
                <span class="ml-2">Loading...</span>
            </div>
        `;
        
        // Fetch mock summary response
        fetch('../actions/test_mock_responses.php?type=summary')
            .then(response => response.json())
            .then(data => {
                let html = `<h3 class="font-semibold mb-3">Summary Result:</h3>`;
                
                if (data.success) {
                    html += `
                        <div class="mb-4">
                            <h4 class="font-medium text-sm mb-2">Summary:</h4>
                            <p class="bg-gray-50 p-3 rounded">${data.summary}</p>
                        </div>
                    `;
                    
                    if (data.keyPoints && data.keyPoints.length > 0) {
                        html += `
                            <div>
                                <h4 class="font-medium text-sm mb-2">Key Points:</h4>
                                <ul class="list-disc pl-5 space-y-1">
                                    ${data.keyPoints.map(point => `<li>${point}</li>`).join('')}
                                </ul>
                            </div>
                        `;
                    }
                } else {
                    html += `
                        <div class="bg-red-100 text-red-700 p-3 rounded">
                            Error: ${data.message || 'Unknown error occurred'}
                        </div>
                    `;
                }
                
                document.getElementById('resultContainer').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('resultContainer').innerHTML = `
                    <div class="bg-red-100 text-red-700 p-3 rounded">
                        Error connecting to server: ${error.message}
                    </div>
                `;
            });
    });
    
    // Test analysis button
    document.getElementById('testAnalysis').addEventListener('click', function() {
        // Show loading state
        document.getElementById('resultContainer').innerHTML = `
            <div class="flex justify-center items-center h-[200px]">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-700"></div>
                <span class="ml-2">Loading...</span>
            </div>
        `;
        
        // Fetch mock analysis response
        fetch('../actions/test_mock_responses.php?type=analysis')
            .then(response => response.json())
            .then(data => {
                let html = `<h3 class="font-semibold mb-3">Analysis Result:</h3>`;
                
                if (data.success) {
                    // Classification
                    if (data.classification && data.classification.length > 0) {
                        html += `
                            <div class="mb-4">
                                <h4 class="font-medium text-sm mb-2">Classification:</h4>
                                <div class="space-y-2">
                                    ${data.classification.map(cls => `
                                        <div class="flex items-center">
                                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                                <div class="bg-green-600 h-2.5 rounded-full" style="width: ${cls.confidence}%"></div>
                                            </div>
                                            <span class="ml-2 text-sm">${cls.name} (${cls.confidence}%)</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }
                    
                    // Entities
                    if (data.entities && data.entities.length > 0) {
                        html += `
                            <div class="mb-4">
                                <h4 class="font-medium text-sm mb-2">Entities:</h4>
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
                    
                    // Sentiment
                    if (data.sentiment) {
                        html += `
                            <div class="mb-4">
                                <h4 class="font-medium text-sm mb-2">Sentiment:</h4>
                                <p>${data.sentiment.sentiment_label}</p>
                                ${data.sentiment.tones ? `
                                    <div class="mt-2">
                                        <h5 class="text-xs font-medium text-gray-500 mb-1">Tones:</h5>
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
                    
                    // Keywords
                    if (data.keywords && data.keywords.length > 0) {
                        html += `
                            <div class="mb-4">
                                <h4 class="font-medium text-sm mb-2">Keywords:</h4>
                                <div class="flex flex-wrap gap-2">
                                    ${data.keywords.map(keyword => `
                                        <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">${keyword}</span>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }
                    
                    // Summary
                    if (data.summary) {
                        html += `
                            <div class="mb-4">
                                <h4 class="font-medium text-sm mb-2">Summary:</h4>
                                <p class="bg-gray-50 p-3 rounded">${data.summary}</p>
                            </div>
                        `;
                        
                        if (data.keyPoints && data.keyPoints.length > 0) {
                            html += `
                                <div>
                                    <h4 class="font-medium text-sm mb-2">Key Points:</h4>
                                    <ul class="list-disc pl-5 space-y-1">
                                        ${data.keyPoints.map(point => `<li>${point}</li>`).join('')}
                                    </ul>
                                </div>
                            `;
                        }
                    }
                } else {
                    html += `
                        <div class="bg-red-100 text-red-700 p-3 rounded">
                            Error: ${data.message || 'Unknown error occurred'}
                        </div>
                    `;
                }
                
                document.getElementById('resultContainer').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('resultContainer').innerHTML = `
                    <div class="bg-red-100 text-red-700 p-3 rounded">
                        Error connecting to server: ${error.message}
                    </div>
                `;
            });
    });
});
</script>

<?php if (!defined('INCLUDED_IN_DASHBOARD')) require_once '../includes/footer.php'; ?> 