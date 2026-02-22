<?php
// Fix for approved.php

// Include this at the top of approved.php
require_once "../includes/document_approval.php";

// Replace the document approval code with this
/*
$result = update_document_approval($conn, $document_id, $office_id, $action_type);
if ($result["success"]) {
    // Document updated successfully
    $notification = "Document " . $action_type . "d successfully";
    $notification_type = "success";
    
    // Redirect based on whether there is a next step
    if ($action_type == "approve" && !$result["has_next_step"]) {
        // Document fully approved, redirect to approved page
        header("Location: dashboard.php?page=approved&action_success=1&doc_id=$document_id&action=$action_type&t=" . time());
    } else {
        // Document moved to next step or rejected/on hold
        header("Location: dashboard.php?page=incoming&action_success=1&doc_id=$document_id&action=$action_type&t=" . time());
    }
    exit();
} else {
    // Error updating document
    $error_message = $result["error"];
    $notification = "Error: " . $error_message;
    $notification_type = "error";
}
*/
?>