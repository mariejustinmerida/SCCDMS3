
<?php
require_once 'config.php';

// Add content_path column to documents table if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM `documents` LIKE 'content_path'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE `documents` ADD COLUMN `content_path` VARCHAR(255) DEFAULT NULL AFTER `file_path`");
    echo "Added content_path column to documents table<br>";
}

// Create document_drafts table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `document_drafts` (
    `draft_id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `title` VARCHAR(255),
    `type_id` INT(11),
    `content` LONGTEXT,
    `workflow` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`draft_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
)");
echo "Created document_drafts table<br>";

// Create document_workflow table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `document_workflow` (
    `workflow_id` INT(11) NOT NULL AUTO_INCREMENT,
    `document_id` INT(11) NOT NULL,
    `office_id` INT(11) NOT NULL,
    `step_order` INT(11) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`workflow_id`),
    FOREIGN KEY (`document_id`) REFERENCES `documents`(`document_id`),
    FOREIGN KEY (`office_id`) REFERENCES `offices`(`office_id`)
)");
echo "Created document_workflow table<br>";

// Create document_contents directory if it doesn't exist
$content_dir = "document_contents/";
if (!file_exists($content_dir)) {
    mkdir($content_dir, 0777, true);
    echo "Created document_contents directory<br>";
}

echo "Database update completed successfully!";
?>
