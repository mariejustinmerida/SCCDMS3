<?php
require_once 'includes/config.php';

// Fix case sensitivity in document_workflow table
$fix_query = "UPDATE document_workflow SET status = 'current' WHERE LOWER(status) = 'current'";
$result = $conn->query($fix_query);

echo "Fixed " . $conn->affected_rows . " records.";
echo "<br><a href='pages/incoming.php'>Go to Inbox</a>";
?>