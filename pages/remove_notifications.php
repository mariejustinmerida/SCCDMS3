<?php
// Read the dashboard_content.php file
$content = file_get_contents('dashboard_content.php');

// Remove the notification container HTML
$content = preg_replace('/<\!-- Notifications Container -->(.*?)<\!-- Calendar Section -->/s', '<!-- Calendar Section -->', $content);

// Remove the notification JavaScript
$content = preg_replace('/<script>\s*\/\/ Notification functionality(.*?)<\/script>/s', '', $content);

// Write the modified content back to the file
file_put_contents('dashboard_content.php', $content);

echo "Notifications removed from dashboard_content.php";
?> 