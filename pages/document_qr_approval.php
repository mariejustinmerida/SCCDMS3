<?php
// This page should be included in dashboard.php
// Set the page title
$page_title = 'Document QR Approval';

// If accessed directly, redirect to dashboard with this page as parameter
if (basename($_SERVER['PHP_SELF']) == 'document_qr_approval.php') {
    $document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    header("Location: dashboard.php?page=document_qr_approval&id=$document_id");
    exit();
}

// Include database configuration
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get document ID from URL
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'] ?? 0;

// Validate document ID
if ($document_id <= 0) {
    echo "<div class='container mx-auto px-4 py-8'><div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4'>Invalid document ID</div></div>";
    require_once '../includes/footer.php';
    exit();
}

// Get document details
$doc_query = "SELECT d.*, dt.type_name, u.username as creator_name, o.office_name as creator_office
             FROM documents d
             LEFT JOIN document_types dt ON d.type_id = dt.type_id
             LEFT JOIN users u ON d.creator_id = u.user_id
             LEFT JOIN offices o ON u.office_id = o.office_id
             WHERE d.document_id = ?";

$stmt = $conn->prepare($doc_query);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo "<div class='container mx-auto px-4 py-8'><div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4'>Document not found</div></div>";
    require_once '../includes/footer.php';
    exit();
}

$document = $result->fetch_assoc();

// Check if the document is assigned to the current office
$check_query = "SELECT * FROM document_workflow 
               WHERE document_id = ? AND office_id = ? AND status = 'CURRENT'";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("ii", $document_id, $office_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

$can_approve = ($check_result && $check_result->num_rows > 0);

// Get workflow history
$workflow_query = "SELECT dw.*, o.office_name 
                 FROM document_workflow dw 
                 JOIN offices o ON dw.office_id = o.office_id 
                 WHERE dw.document_id = ? 
                 ORDER BY dw.step_order ASC";

// Add error handling for prepare statement
if ($workflow_stmt = $conn->prepare($workflow_query)) {
    $workflow_stmt->bind_param("i", $document_id);
    $workflow_stmt->execute();
    $workflow_result = $workflow_stmt->get_result();
} else {
    echo "<div class='container mx-auto px-4 py-8'><div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4'>Error preparing workflow query: " . $conn->error . "</div></div>";
    $workflow_result = false;
}
$workflow_steps = [];

while ($step = $workflow_result->fetch_assoc()) {
    $workflow_steps[] = $step;
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Status Message Container -->
    <div id="statusMessage" class="hidden mb-6"></div>
    
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Document Details</h1>
        <a href="dashboard.php?page=incoming" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
            Back to Inbox
        </a>
    </div>
    
    <!-- Document Info Card -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="border-b px-6 py-4">
            <h2 class="text-xl font-semibold"><?php echo htmlspecialchars($document['title']); ?></h2>
            <p class="text-gray-600 text-sm">
                Created by <?php echo htmlspecialchars($document['creator_name']); ?> 
                (<?php echo htmlspecialchars($document['creator_office']); ?>)
                on <?php echo date('F j, Y, g:i a', strtotime($document['created_at'])); ?>
            </p>
        </div>
        
        <div class="px-6 py-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <p class="text-gray-600 text-sm">Document Type:</p>
                    <p class="font-medium"><?php echo htmlspecialchars($document['type_name']); ?></p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Status:</p>
                    <p>
                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full
                        <?php 
                        switch($document['status']) {
                            case 'approved': echo 'bg-green-100 text-green-800'; break;
                            case 'rejected': echo 'bg-red-100 text-red-800'; break;
                            case 'pending': echo 'bg-blue-100 text-blue-800'; break;
                            case 'draft': echo 'bg-gray-100 text-gray-800'; break;
                            case 'revision': echo 'bg-yellow-100 text-yellow-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
                            <?php echo ucfirst(htmlspecialchars($document['status'])); ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <?php if (!empty($document['google_doc_id'])): ?>
            <div class="mb-4">
                <p class="text-gray-600 text-sm mb-2">Document Preview:</p>
                <div class="border rounded-lg overflow-hidden">
                    <iframe src="https://docs.google.com/document/d/<?php echo $document['google_doc_id']; ?>/preview" 
                            width="100%" height="500" frameborder="0" class="block w-full"></iframe>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Workflow Steps -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="border-b px-6 py-4">
            <h2 class="text-xl font-semibold">Approval Workflow</h2>
        </div>
        
        <div class="px-6 py-4">
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 uppercase text-xs">
                            <th class="py-3 px-4 text-left">Step</th>
                            <th class="py-3 px-4 text-left">Office</th>
                            <th class="py-3 px-4 text-left">Status</th>
                            <th class="py-3 px-4 text-left">Approved By</th>
                            <th class="py-3 px-4 text-left">Date</th>
                            <th class="py-3 px-4 text-left">Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workflow_steps as $step): ?>
                        <tr class="border-b hover:bg-gray-50 <?php echo ($step['office_id'] == $office_id && $step['status'] == 'CURRENT') ? 'bg-green-50' : ''; ?>">
                            <td class="py-3 px-4"><?php echo $step['step_order']; ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($step['office_name']); ?></td>
                            <td class="py-3 px-4">
                                <?php if ($step['status'] == 'COMPLETED'): ?>
                                <span class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">Completed</span>
                                <?php elseif ($step['status'] == 'CURRENT'): ?>
                                <span class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium">Current</span>
                                <?php else: ?>
                                <span class="inline-block bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs font-medium">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">-</td>
                            <td class="py-3 px-4">
                                <?php 
                                if (isset($step['completed_at']) && $step['completed_at']) {
                                    echo date('M j, Y', strtotime($step['completed_at']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td class="py-3 px-4"><?php echo $step['comments'] ? htmlspecialchars($step['comments']) : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php if ($can_approve): ?>
    <!-- QR Code Approval Section -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="border-b px-6 py-4">
            <h2 class="text-xl font-semibold">Document Approval with QR Signature</h2>
        </div>
        
        <div class="px-6 py-4">
            <p class="mb-4">Approve this document and add a secure QR code signature that can be verified by anyone with access to the document.</p>
            
            <div class="mb-4">
                <label for="comments" class="block text-sm font-medium text-gray-700 mb-1">Comments (Optional)</label>
                <textarea id="comments" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            
            <div>
                <button id="approveWithQrBtn" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded mr-2">
                    Approve with QR Signature
                </button>
                <button id="rejectBtn" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded mr-2">
                    Reject Document
                </button>
                <button id="holdBtn" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded">
                    Hold for Review
                </button>
            </div>
        </div>
    </div>
    
    <!-- QR Code Result Section (Hidden initially) -->
    <div id="qrResultSection" class="bg-white rounded-lg shadow-md overflow-hidden mb-6 hidden">
        <div class="border-b px-6 py-4">
            <h2 class="text-xl font-semibold">QR Signature Generated</h2>
        </div>
        
        <div class="px-6 py-4">
            <div class="text-center">
                <p class="mb-4 text-green-600 font-medium">Document has been approved and QR signature has been added successfully!</p>
                
                <div id="qrCodeImage" class="mb-4 flex justify-center">
                    <!-- QR Code will be inserted here -->
                </div>
                
                <p class="mb-2 text-sm text-gray-600">Scan this QR code to verify the document's authenticity.</p>
                <p class="mb-4 text-sm text-gray-600">The QR code has been added to the top-right corner of your Google Document.</p>
                
                <a id="verificationLink" href="#" target="_blank" class="inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                    Verify Document
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get elements
    const approveWithQrBtn = document.getElementById('approveWithQrBtn');
    const rejectBtn = document.getElementById('rejectBtn');
    const holdBtn = document.getElementById('holdBtn');
    const commentsField = document.getElementById('comments');
    const statusMessage = document.getElementById('statusMessage');
    const qrResultSection = document.getElementById('qrResultSection');
    const qrCodeImage = document.getElementById('qrCodeImage');
    const verificationLink = document.getElementById('verificationLink');
    
    // Document ID
    const documentId = <?php echo $document_id; ?>;
    
    // Show status message
    function showStatus(message, type = 'success') {
        statusMessage.innerHTML = `
            <div class="${type === 'success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700'} border-l-4 p-4">
                <p>${message}</p>
            </div>
        `;
        statusMessage.classList.remove('hidden');
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    // Handle Approve with QR button click
    if (approveWithQrBtn) {
        approveWithQrBtn.addEventListener('click', function() {
            // Disable buttons to prevent multiple submissions
            approveWithQrBtn.disabled = true;
            approveWithQrBtn.innerHTML = 'Processing...';
            
            // Prepare data
            const data = {
                document_id: documentId,
                comments: commentsField.value
            };
            
            // Send approval request
            fetch('../api/approve_with_qr.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Show success message
                    showStatus(result.message);
                    
                    // Check if document was already approved
                    if (result.already_approved) {
                        console.log('Document was already approved');
                        // Disable the button
                        approveWithQrBtn.disabled = true;
                        approveWithQrBtn.innerHTML = 'Already Approved';
                        approveWithQrBtn.classList.remove('bg-green-500', 'hover:bg-green-600');
                        approveWithQrBtn.classList.add('bg-gray-400');
                        
                        // If we have QR code data for the already approved document, show it
                        if (result.qr_path && result.verification_url) {
                            if (qrCodeImage && qrResultSection && verificationLink) {
                                try {
                                    // Display QR code using Google Charts API instead of local file
                                    if (result.google_qr_url) {
                                        qrCodeImage.innerHTML = `<img src="${result.google_qr_url}" alt="QR Code Signature" class="border p-2 rounded">`;
                                    } else {
                                        // Fallback to text link if no QR code
                                        qrCodeImage.innerHTML = `<div class="p-4 bg-gray-100 rounded">QR Code not available. Use verification link below.</div>`;
                                    }
                                    verificationLink.href = result.verification_url;
                                    qrResultSection.classList.remove('hidden');
                                } catch (e) {
                                    console.error('Error displaying QR code for already approved document:', e);
                                }
                            }
                        }
                        return;
                    }
                    
                    // Check if we have QR code data
                    if (result.qr_path && result.verification_url) {
                        // Check if QR code elements exist
                        if (qrCodeImage && qrResultSection && verificationLink) {
                            try {
                                // Display QR code using Google Charts API instead of local file
                                if (result.google_qr_url) {
                                    qrCodeImage.innerHTML = `<img src="${result.google_qr_url}" alt="QR Code Signature" class="border p-2 rounded">`;
                                } else {
                                    // Fallback to text link if no QR code
                                    qrCodeImage.innerHTML = `<div class="p-4 bg-gray-100 rounded">QR Code not available. Use verification link below.</div>`;
                                }
                                verificationLink.href = result.verification_url;
                                qrResultSection.classList.remove('hidden');
                                
                                // Hide approval buttons
                                approveWithQrBtn.parentElement.classList.add('hidden');
                                commentsField.parentElement.classList.add('hidden');
                            } catch (e) {
                                console.error('Error displaying QR code:', e);
                                // Fallback to simple success message
                                approveWithQrBtn.disabled = true;
                                approveWithQrBtn.innerHTML = 'Approved Successfully';
                                approveWithQrBtn.classList.remove('bg-green-500', 'hover:bg-green-600');
                                approveWithQrBtn.classList.add('bg-green-700');
                            }
                        } else {
                            console.log('QR code elements not found, showing simple success');
                            // Fallback to simple success message
                            approveWithQrBtn.disabled = true;
                            approveWithQrBtn.innerHTML = 'Approved Successfully';
                            approveWithQrBtn.classList.remove('bg-green-500', 'hover:bg-green-600');
                            approveWithQrBtn.classList.add('bg-green-700');
                        }
                    } else if (result.redirect_to_approved) {
                        // Show a message that we're redirecting
                        approveWithQrBtn.disabled = true;
                        approveWithQrBtn.innerHTML = 'Approved Successfully';
                        approveWithQrBtn.classList.remove('bg-green-500', 'hover:bg-green-600');
                        approveWithQrBtn.classList.add('bg-green-700');
                        
                        const redirectMsg = document.createElement('div');
                        redirectMsg.className = 'mt-4 text-sm text-gray-600';
                        redirectMsg.innerHTML = 'Redirecting to approved documents...';
                        approveWithQrBtn.parentElement.appendChild(redirectMsg);
                        
                        // Redirect after a delay
                        setTimeout(function() {
                            window.location.href = 'dashboard.php?page=approved';
                        }, 2000);
                    }
                } else {
                    // Show error message
                    showStatus(result.error, 'error');
                    
                    // Re-enable button
                    approveWithQrBtn.disabled = false;
                    approveWithQrBtn.innerHTML = 'Approve with QR Signature';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showStatus('An error occurred. Please try again.', 'error');
                
                // Re-enable button
                approveWithQrBtn.disabled = false;
                approveWithQrBtn.innerHTML = 'Approve with QR Signature';
            });
        });
    }
    
    // Handle Reject button click
    if (rejectBtn) {
        rejectBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to reject this document?')) {
                window.location.href = `approve_document.php?id=${documentId}&action=reject`;
            }
        });
    }
    
    // Handle Hold button click
    if (holdBtn) {
        holdBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to place this document on hold?')) {
                window.location.href = `approve_document.php?id=${documentId}&action=hold`;
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
