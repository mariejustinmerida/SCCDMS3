<?php
require_once 'includes/config.php';

// Set content type to plain text for easier reading
header('Content-Type: text/plain');

echo "=== FIXING DOCUMENT WORKFLOW ISSUES ===\n\n";

// 1. Check if there are any documents with workflow steps
$check_query = "SELECT COUNT(*) as count FROM document_workflow";
$check_result = $conn->query($check_query);
$workflow_count = 0;
if ($check_result && $row = $check_result->fetch_assoc()) {
    $workflow_count = $row['count'];
}

echo "Found $workflow_count workflow entries in the database.\n\n";

// 2. Update all workflow statuses to lowercase to ensure consistency
echo "Updating workflow statuses to lowercase...\n";
$update_case_query = "UPDATE document_workflow SET status = LOWER(status)";
$update_case_result = $conn->query($update_case_query);
if ($update_case_result) {
    echo "Successfully updated workflow statuses to lowercase.\n\n";
} else {
    echo "Error updating workflow statuses: " . $conn->error . "\n\n";
}

// 3. Fix any missing 'current' statuses for first steps
echo "Fixing missing 'current' statuses for first steps...\n";

// Find documents that don't have any 'current' workflow steps
$missing_current_query = "SELECT DISTINCT d.document_id, d.title 
    FROM documents d 
    LEFT JOIN document_workflow dw ON d.document_id = dw.document_id AND dw.status = 'current' 
    WHERE d.status = 'pending' AND dw.workflow_id IS NULL";
$missing_current_result = $conn->query($missing_current_query);

if ($missing_current_result) {
    $fixed_count = 0;
    
    while ($doc = $missing_current_result->fetch_assoc()) {
        $doc_id = $doc['document_id'];
        echo "Fixing document ID: $doc_id - {$doc['title']}\n";
        
        // Find the first step for this document
        $first_step_query = "SELECT workflow_id FROM document_workflow 
            WHERE document_id = $doc_id ORDER BY step_order ASC LIMIT 1";
        $first_step_result = $conn->query($first_step_query);
        
        if ($first_step_result && $first_step = $first_step_result->fetch_assoc()) {
            $workflow_id = $first_step['workflow_id'];
            
            // Update the first step to 'current'
            $update_query = "UPDATE document_workflow SET status = 'current' WHERE workflow_id = $workflow_id";
            $update_result = $conn->query($update_query);
            
            if ($update_result) {
                $fixed_count++;
                echo "  - Successfully updated workflow ID: $workflow_id to 'current'\n";
            } else {
                echo "  - Error updating workflow: " . $conn->error . "\n";
            }
        } else {
            echo "  - No workflow steps found for this document\n";
        }
    }
    
    echo "\nFixed $fixed_count documents with missing 'current' status.\n\n";
} else {
    echo "Error finding documents with missing 'current' status: " . $conn->error . "\n\n";
}

// 4. Check for documents with multiple 'current' steps and fix them
echo "Checking for documents with multiple 'current' steps...\n";

$multiple_current_query = "SELECT document_id, COUNT(*) as count 
    FROM document_workflow 
    WHERE status = 'current' 
    GROUP BY document_id 
    HAVING COUNT(*) > 1";
$multiple_current_result = $conn->query($multiple_current_query);

if ($multiple_current_result) {
    $fixed_multiple_count = 0;
    
    while ($doc = $multiple_current_result->fetch_assoc()) {
        $doc_id = $doc['document_id'];
        $current_count = $doc['count'];
        echo "Document ID: $doc_id has $current_count 'current' steps\n";
        
        // Keep only the earliest step as 'current'
        $fix_multiple_query = "UPDATE document_workflow 
            SET status = 'pending' 
            WHERE document_id = $doc_id 
            AND status = 'current' 
            AND workflow_id NOT IN (
                SELECT workflow_id FROM (
                    SELECT workflow_id 
                    FROM document_workflow 
                    WHERE document_id = $doc_id AND status = 'current' 
                    ORDER BY step_order ASC 
                    LIMIT 1
                ) as t
            )";
        $fix_multiple_result = $conn->query($fix_multiple_query);
        
        if ($fix_multiple_result) {
            $fixed_multiple_count++;
            echo "  - Successfully fixed multiple 'current' steps\n";
        } else {
            echo "  - Error fixing multiple 'current' steps: " . $conn->error . "\n";
        }
    }
    
    echo "\nFixed $fixed_multiple_count documents with multiple 'current' steps.\n\n";
} else {
    echo "Error checking for multiple 'current' steps: " . $conn->error . "\n\n";
}

echo "=== FIX COMPLETED ===\n";
echo "Please check your incoming documents page now.\n";
?>
