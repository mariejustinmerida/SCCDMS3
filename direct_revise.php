<?php
require_once 'includes/config.php';
require_once 'includes/document_workflow.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p>Please log in first.</p>";
    echo "<p><a href='pages/dashboard.php'>Go to Dashboard</a></p>";
    exit();
}

$user_id = $_SESSION['user_id'];
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';

// If no document ID is provided, show a form to enter one
if ($document_id == 0) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Direct Document Revision</title>
        <script src='https://cdn.tailwindcss.com'></script>
    </head>
    <body class='bg-gray-100 p-6'>
        <div class='max-w-md mx-auto bg-white rounded-lg shadow-md p-6'>
            <h1 class='text-xl font-bold mb-4'>Direct Document Revision</h1>
            <form method='get' action='direct_revise.php'>
                <div class='mb-4'>
                    <label class='block text-sm font-medium text-gray-700 mb-1'>Document ID</label>
                    <input type='number' name='id' class='w-full px-3 py-2 border rounded-lg' required>
                </div>
                <button type='submit' class='w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700'>
                    Load Document
                </button>
            </form>
            <div class='mt-4 text-center'>
                <a href='pages/dashboard.php' class='text-blue-600 hover:underline'>Return to Dashboard</a>
            </div>
        </div>
    </body>
    </html>";
    exit();
}

// If submitting revision
if ($action === 'submit' && !empty($comments)) {
    // Process the document revision
    $result = process_document_revision($conn, $document_id, $user_id, $comments);
    
    if ($result['success']) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Revision Submitted</title>
            <script src='https://cdn.tailwindcss.com'></script>
        </head>
        <body class='bg-gray-100 p-6'>
            <div class='max-w-md mx-auto bg-white rounded-lg shadow-md p-6'>
                <div class='mb-4 bg-green-100 border-l-4 border-green-500 p-4 text-green-700'>
                    <p class='font-bold'>Success!</p>
                    <p>Document has been revised and is now back in the workflow process.</p>
                </div>
                <div class='text-center'>
                    <a href='pages/dashboard.php' class='inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700'>
                        Return to Dashboard
                    </a>
                </div>
            </div>
        </body>
        </html>";
        exit();
    } else {
        $error = $result['error'] ?? 'Unknown error';
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Revision Error</title>
            <script src='https://cdn.tailwindcss.com'></script>
        </head>
        <body class='bg-gray-100 p-6'>
            <div class='max-w-md mx-auto bg-white rounded-lg shadow-md p-6'>
                <div class='mb-4 bg-red-100 border-l-4 border-red-500 p-4 text-red-700'>
                    <p class='font-bold'>Error</p>
                    <p>{$error}</p>
                </div>
                <div class='text-center'>
                    <a href='direct_revise.php?id={$document_id}' class='inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 mr-2'>
                        Try Again
                    </a>
                    <a href='pages/dashboard.php' class='inline-block bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700'>
                        Return to Dashboard
                    </a>
                </div>
            </div>
        </body>
        </html>";
        exit();
    }
}

// Get document details
$doc_query = "SELECT d.*, dt.type_name, u.full_name as creator_name, o.office_name as creator_office
             FROM documents d
             JOIN document_types dt ON d.type_id = dt.type_id
             JOIN users u ON d.creator_id = u.user_id
             JOIN offices o ON u.office_id = o.office_id
             WHERE d.document_id = ?";
$stmt = $conn->prepare($doc_query);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Document Not Found</title>
        <script src='https://cdn.tailwindcss.com'></script>
    </head>
    <body class='bg-gray-100 p-6'>
        <div class='max-w-md mx-auto bg-white rounded-lg shadow-md p-6'>
            <div class='mb-4 bg-red-100 border-l-4 border-red-500 p-4 text-red-700'>
                <p class='font-bold'>Error</p>
                <p>Document not found.</p>
            </div>
            <div class='text-center'>
                <a href='direct_revise.php' class='inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 mr-2'>
                    Try Another Document
                </a>
                <a href='pages/dashboard.php' class='inline-block bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700'>
                    Return to Dashboard
                </a>
            </div>
        </div>
    </body>
    </html>";
    exit();
}

$document = $result->fetch_assoc();

// Get revision comments from workflow
$comments_query = "SELECT dw.comments, o.office_name 
                  FROM document_workflow dw
                  JOIN offices o ON dw.office_id = o.office_id
                  WHERE dw.document_id = ? AND dw.status = 'revision_requested'
                  ORDER BY dw.step_order DESC LIMIT 1";
$comments_stmt = $conn->prepare($comments_query);
$comments_stmt->bind_param("i", $document_id);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();

$revision_comments = "No specific comments provided";
$requesting_office = "Unknown Office";

if ($comments_result && $comments_result->num_rows > 0) {
    $comments_data = $comments_result->fetch_assoc();
    $revision_comments = $comments_data['comments'] ?? $revision_comments;
    $requesting_office = $comments_data['office_name'] ?? $requesting_office;
}

// Display the revision form
echo "<!DOCTYPE html>
<html>
<head>
    <title>Direct Document Revision</title>
    <script src='https://cdn.tailwindcss.com'></script>
</head>
<body class='bg-gray-100 p-6'>
    <div class='max-w-3xl mx-auto bg-white rounded-lg shadow-md overflow-hidden'>
        <div class='border-b px-6 py-3 bg-[#163b20]'>
            <h2 class='text-lg font-semibold text-white flex items-center'>
                <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 mr-2' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' />
                </svg>
                Direct Document Revision
            </h2>
        </div>
        
        <div class='p-6'>
            <div class='mb-6'>
                <h3 class='text-xl font-semibold mb-2'>" . htmlspecialchars($document['title']) . "</h3>
                <p class='text-gray-600'>Document Code: DOC-" . str_pad($document['document_id'], 3, '0', STR_PAD_LEFT) . "</p>
                <p class='text-gray-600'>Document Type: " . htmlspecialchars($document['type_name']) . "</p>
                <p class='text-gray-600'>Status: 
                    <span class='px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-medium'>
                        " . ucfirst($document['status']) . "
                    </span>
                </p>
                <p class='text-gray-600'>Revision Requested By: " . htmlspecialchars($requesting_office) . "</p>
            </div>
            
            <div class='mb-6 p-4 bg-amber-50 border-l-4 border-amber-500'>
                <h4 class='font-medium text-amber-800 mb-2'>Revision Comments:</h4>
                <p class='text-amber-700'>" . nl2br(htmlspecialchars($revision_comments)) . "</p>
            </div>
            
            <div class='mb-6 p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-700'>
                <p class='font-medium'>Important:</p>
                <p>Please make the requested changes to your document. Once you've made the changes, add your comments below and submit your revised document. The document will then continue through the workflow process, skipping offices that have already approved it.</p>
            </div>";
            
            if ($document['google_doc_id']) {
                echo "<div class='mb-6'>
                    <a href='https://docs.google.com/document/d/" . htmlspecialchars($document['google_doc_id']) . "/edit' target='_blank' class='inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors'>
                        <svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5 inline-block mr-1' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14' />
                        </svg>
                        Open in Google Docs
                    </a>
                </div>";
            }
            
            echo "<form action='direct_revise.php?id=" . $document_id . "&action=submit' method='post'>
                <div class='mb-6'>
                    <label for='comments' class='block text-sm font-medium text-gray-700 mb-1'>Your Comments (Required)</label>
                    <textarea id='comments' name='comments' rows='4' class='w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500' placeholder='Add your comments about the changes you've made...' required></textarea>
                </div>
                
                <div class='flex justify-end space-x-3'>
                    <a href='pages/dashboard.php' class='px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors'>
                        Cancel
                    </a>
                    <button type='submit' class='px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors'>
                        Submit Revised Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>";
?>
