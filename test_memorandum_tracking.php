<?php
require_once 'includes/config.php';

session_start();

echo "<h1>Test Memorandum Tracking</h1>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p><strong>❌ Not logged in!</strong></p>";
    echo "<p>Please log in as Finance Department to test memorandum tracking.</p>";
    exit();
}

$user_id = $_SESSION['user_id'];
$office_id = $_SESSION['office_id'];

echo "<p><strong>Logged in as:</strong> User ID $user_id, Office ID $office_id</p>";

// Get office name
$office_query = "SELECT office_name FROM offices WHERE office_id = ?";
$office_stmt = $conn->prepare($office_query);
$office_stmt->bind_param('i', $office_id);
$office_stmt->execute();
$office_result = $office_stmt->get_result();
$office_name = $office_result->fetch_assoc()['office_name'] ?? 'Unknown Office';
echo "<p><strong>Office:</strong> $office_name</p>";

// Find a memorandum to test with
$memorandum_query = "SELECT document_id, title, memorandum_total_offices, memorandum_read_offices FROM documents WHERE is_memorandum = 1 LIMIT 1";
$memorandum_result = $conn->query($memorandum_query);

if ($memorandum_result && $memorandum_result->num_rows > 0) {
    $memorandum = $memorandum_result->fetch_assoc();
    $document_id = $memorandum['document_id'];
    $title = $memorandum['title'];
    $total_offices = $memorandum['memorandum_total_offices'];
    $read_offices = $memorandum['memorandum_read_offices'];
    
    echo "<h2>Testing Memorandum: $title (DOC-$document_id)</h2>";
    echo "<p><strong>Current Status:</strong> $read_offices / $total_offices offices have read it</p>";
    
    // Check if this office has already read it
    $read_check_query = "SELECT is_read, read_at FROM memorandum_distribution WHERE document_id = ? AND office_id = ?";
    $read_check_stmt = $conn->prepare($read_check_query);
    $read_check_stmt->bind_param('ii', $document_id, $office_id);
    $read_check_stmt->execute();
    $read_check_result = $read_check_stmt->get_result();
    
    if ($read_check_result->num_rows > 0) {
        $read_status = $read_check_result->fetch_assoc();
        if ($read_status['is_read']) {
            echo "<p><strong>✅ This office has already read this memorandum</strong></p>";
            echo "<p><strong>Read at:</strong> " . $read_status['read_at'] . "</p>";
        } else {
            echo "<p><strong>❌ This office has NOT read this memorandum yet</strong></p>";
        }
    } else {
        echo "<p><strong>❌ This office is not in the memorandum distribution</strong></p>";
    }
    
    echo "<h3>Test Memorandum Tracking</h3>";
    echo "<p>Click the button below to simulate viewing this memorandum:</p>";
    echo "<button onclick='testMemorandumTracking($document_id)' class='bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700'>Test Memorandum View</button>";
    echo "<div id='tracking-result' class='mt-4 p-4 bg-gray-100 rounded hidden'></div>";
    
} else {
    echo "<p><strong>❌ No memorandums found in the database</strong></p>";
}

echo "<h2>All Memorandum Distribution Status</h2>";
$all_memorandums_query = "SELECT d.document_id, d.title, d.memorandum_total_offices, d.memorandum_read_offices, 
                         md.office_id, md.is_read, md.read_at, o.office_name
                         FROM documents d
                         LEFT JOIN memorandum_distribution md ON d.document_id = md.document_id
                         LEFT JOIN offices o ON md.office_id = o.office_id
                         WHERE d.is_memorandum = 1
                         ORDER BY d.document_id, md.office_id";

$all_result = $conn->query($all_memorandums_query);

if ($all_result && $all_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Document</th><th>Office</th><th>Read Status</th><th>Read At</th></tr>";
    
    while ($row = $all_result->fetch_assoc()) {
        $status = $row['is_read'] ? '✅ Read' : '❌ Not Read';
        $read_at = $row['read_at'] ? $row['read_at'] : '-';
        echo "<tr>";
        echo "<td>DOC-" . str_pad($row['document_id'], 3, '0', STR_PAD_LEFT) . " - " . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . htmlspecialchars($row['office_name']) . "</td>";
        echo "<td>$status</td>";
        echo "<td>$read_at</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No memorandum distribution data found.</p>";
}
?>

<script>
function testMemorandumTracking(documentId) {
    const resultDiv = document.getElementById('tracking-result');
    resultDiv.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 inline-block mr-2"></div> Testing memorandum tracking...';
    resultDiv.classList.remove('hidden');
    
    fetch('../api/track_memorandum_view.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            document_id: documentId,
            action: 'viewed'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <strong>✅ Memorandum tracking successful!</strong><br>
                    Progress: ${data.data.progress}%<br>
                    Read Offices: ${data.data.read_offices} / ${data.data.total_offices}<br>
                    <small>Response: ${JSON.stringify(data)}</small>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <strong>❌ Memorandum tracking failed!</strong><br>
                    Error: ${data.error}<br>
                    <small>Response: ${JSON.stringify(data)}</small>
                </div>
            `;
        }
    })
    .catch(error => {
        resultDiv.innerHTML = `
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <strong>❌ Network error!</strong><br>
                Error: ${error.message}
            </div>
        `;
    });
}
</script> 