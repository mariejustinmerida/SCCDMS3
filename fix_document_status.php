<?php
/**
 * Fix Document Status Script
 * 
 * This utility script fixes document status values that are NULL but should be 'on_hold'
 * based on the most recent document_logs entries.
 */

// Include the database connection
require_once './includes/config.php';

// Start tracking fixes
$fixes = array();
$errors = array();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Document Status</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .fixed { background-color: #d4edda; }
    </style>
</head>
<body>
    <h1>Document Status Fix Utility</h1>";

// 1. Find documents with NULL status but have 'hold' log entries
$find_null_with_hold = "SELECT d.document_id, d.title, d.status, MAX(dl.created_at) as last_hold_date
                       FROM documents d
                       JOIN document_logs dl ON d.document_id = dl.document_id
                       WHERE dl.action = 'hold'
                       GROUP BY d.document_id, d.title, d.status";

$null_result = $conn->query($find_null_with_hold);

if (!$null_result) {
    $errors[] = "Error querying for NULL status documents: " . $conn->error;
} else {
    // Track documents to fix
    $docs_to_fix = array();
    
    while ($row = $null_result->fetch_assoc()) {
        if ($row['status'] === NULL || $row['status'] === '') {
            $docs_to_fix[] = array(
                'id' => $row['document_id'],
                'title' => $row['title'],
                'last_hold_date' => $row['last_hold_date']
            );
        }
    }
    
    echo "<h2>Documents with NULL status that have hold logs:</h2>";
    if (count($docs_to_fix) === 0) {
        echo "<p>No documents with NULL status and hold logs found.</p>";
    } else {
        echo "<p>Found " . count($docs_to_fix) . " documents with NULL status that should be on hold.</p>";
        
        // Fix each document
        foreach ($docs_to_fix as $doc) {
            $update_sql = "UPDATE documents SET status = 'on_hold' WHERE document_id = " . $doc['id'];
            
            if ($conn->query($update_sql)) {
                $fixes[] = "Updated document ID " . $doc['id'] . " (" . $doc['title'] . ") status to 'on_hold'";
            } else {
                $errors[] = "Failed to update document ID " . $doc['id'] . ": " . $conn->error;
            }
        }
    }
}

// 2. Fix documents with 'on_hold' in document_logs but different status in documents table
$find_inconsistent = "SELECT d.document_id, d.title, d.status, 
                      MAX(dl.created_at) as last_action_date,
                      (SELECT dl2.action FROM document_logs dl2 
                       WHERE dl2.document_id = d.document_id 
                       ORDER BY dl2.created_at DESC LIMIT 1) as last_action
                      FROM documents d
                      JOIN document_logs dl ON d.document_id = dl.document_id
                      WHERE (d.status != 'on_hold' OR d.status IS NULL)
                      GROUP BY d.document_id, d.title, d.status
                      HAVING last_action = 'hold'";

$inconsistent_result = $conn->query($find_inconsistent);

if (!$inconsistent_result) {
    $errors[] = "Error querying for inconsistent status documents: " . $conn->error;
} else {
    // Track documents to fix
    $inconsistent_docs = array();
    
    while ($row = $inconsistent_result->fetch_assoc()) {
        $inconsistent_docs[] = array(
            'id' => $row['document_id'],
            'title' => $row['title'],
            'current_status' => $row['status'],
            'last_action' => $row['last_action'],
            'last_action_date' => $row['last_action_date']
        );
    }
    
    echo "<h2>Documents with inconsistent status:</h2>";
    if (count($inconsistent_docs) === 0) {
        echo "<p>No documents with inconsistent status found.</p>";
    } else {
        echo "<p>Found " . count($inconsistent_docs) . " documents with inconsistent status.</p>";
        
        // Fix each document
        foreach ($inconsistent_docs as $doc) {
            if ($doc['last_action'] === 'hold') {
                $update_sql = "UPDATE documents SET status = 'on_hold' WHERE document_id = " . $doc['id'];
                
                if ($conn->query($update_sql)) {
                    $fixes[] = "Updated document ID " . $doc['id'] . " (" . $doc['title'] . ") status from '" . 
                              ($doc['current_status'] ?? 'NULL') . "' to 'on_hold'";
                } else {
                    $errors[] = "Failed to update document ID " . $doc['id'] . ": " . $conn->error;
                }
            }
        }
    }
}

// 3. Check for any resume actions that should have changed status back from on_hold
$find_resumed = "SELECT d.document_id, d.title, d.status,
                MAX(dl.created_at) as last_action_date,
                (SELECT dl2.action FROM document_logs dl2 
                 WHERE dl2.document_id = d.document_id 
                 ORDER BY dl2.created_at DESC LIMIT 1) as last_action
                FROM documents d
                JOIN document_logs dl ON d.document_id = dl.document_id
                WHERE d.status = 'on_hold'
                GROUP BY d.document_id, d.title, d.status
                HAVING last_action = 'resume'";

$resumed_result = $conn->query($find_resumed);

if (!$resumed_result) {
    $errors[] = "Error querying for resumed documents: " . $conn->error;
} else {
    // Track documents to fix
    $resumed_docs = array();
    
    while ($row = $resumed_result->fetch_assoc()) {
        $resumed_docs[] = array(
            'id' => $row['document_id'],
            'title' => $row['title'],
            'current_status' => $row['status'],
            'last_action' => $row['last_action'],
            'last_action_date' => $row['last_action_date']
        );
    }
    
    echo "<h2>Documents that should be resumed:</h2>";
    if (count($resumed_docs) === 0) {
        echo "<p>No documents needing resume status updates found.</p>";
    } else {
        echo "<p>Found " . count($resumed_docs) . " documents that should be resumed to 'pending' status.</p>";
        
        // Fix each document
        foreach ($resumed_docs as $doc) {
            $update_sql = "UPDATE documents SET status = 'pending' WHERE document_id = " . $doc['id'];
            
            if ($conn->query($update_sql)) {
                $fixes[] = "Updated document ID " . $doc['id'] . " (" . $doc['title'] . ") status from 'on_hold' to 'pending'";
            } else {
                $errors[] = "Failed to update document ID " . $doc['id'] . ": " . $conn->error;
            }
        }
    }
}

// Output results
echo "<h2>Actions Taken:</h2>";
if (count($fixes) > 0) {
    echo "<ul class='success'>";
    foreach ($fixes as $fix) {
        echo "<li>" . $fix . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No fixes were needed.</p>";
}

if (count($errors) > 0) {
    echo "<h2>Errors:</h2>";
    echo "<ul class='error'>";
    foreach ($errors as $error) {
        echo "<li>" . $error . "</li>";
    }
    echo "</ul>";
}

// Get current documents
$current_docs = $conn->query("SELECT d.document_id, d.title, d.status, 
                            (SELECT dl.action FROM document_logs dl 
                             WHERE dl.document_id = d.document_id 
                             ORDER BY dl.created_at DESC LIMIT 1) as last_action,
                            (SELECT dl.created_at FROM document_logs dl 
                             WHERE dl.document_id = d.document_id 
                             ORDER BY dl.created_at DESC LIMIT 1) as last_action_date
                            FROM documents d
                            ORDER BY d.document_id DESC
                            LIMIT 20");

echo "<h2>Current Document Status (Last 20 Documents):</h2>";
echo "<table>";
echo "<tr>
        <th>ID</th>
        <th>Title</th>
        <th>Status</th>
        <th>Last Action</th>
        <th>Last Action Date</th>
      </tr>";

while ($row = $current_docs->fetch_assoc()) {
    $rowClass = "";
    if (($row['status'] === 'on_hold' && $row['last_action'] === 'hold') || 
        ($row['status'] === 'pending' && $row['last_action'] === 'resume')) {
        $rowClass = "class='fixed'";
    }
    
    echo "<tr $rowClass>";
    echo "<td>" . $row['document_id'] . "</td>";
    echo "<td>" . $row['title'] . "</td>";
    echo "<td>" . ($row['status'] !== null ? $row['status'] : 'NULL') . "</td>";
    echo "<td>" . ($row['last_action'] !== null ? $row['last_action'] : 'None') . "</td>";
    echo "<td>" . ($row['last_action_date'] !== null ? $row['last_action_date'] : 'None') . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p><a href='dashboard.php?page=hold'>Go to Hold Documents Page</a></p>";
echo "</body></html>";
?> 