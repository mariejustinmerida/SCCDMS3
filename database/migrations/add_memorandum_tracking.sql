-- Add memorandum tracking functionality
-- This migration adds tables and columns to track memorandum distribution and read status

-- Add memorandum_distribution table to track which offices received a memorandum
CREATE TABLE `memorandum_distribution` (
  `distribution_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `read_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`distribution_id`),
  KEY `document_id` (`document_id`),
  KEY `office_id` (`office_id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add memorandum_read_logs table to track detailed read history
CREATE TABLE `memorandum_read_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('viewed','downloaded','printed') NOT NULL DEFAULT 'viewed',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `document_id` (`document_id`),
  KEY `office_id` (`office_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add is_memorandum column to documents table
ALTER TABLE `documents` ADD COLUMN `is_memorandum` tinyint(1) DEFAULT 0 AFTER `is_urgent`;

-- Add memorandum_sent_to_all_offices column to documents table
ALTER TABLE `documents` ADD COLUMN `memorandum_sent_to_all_offices` tinyint(1) DEFAULT 0 AFTER `is_memorandum`;

-- Add memorandum_total_offices column to documents table
ALTER TABLE `documents` ADD COLUMN `memorandum_total_offices` int(11) DEFAULT 0 AFTER `memorandum_sent_to_all_offices`;

-- Add memorandum_read_offices column to documents table
ALTER TABLE `documents` ADD COLUMN `memorandum_read_offices` int(11) DEFAULT 0 AFTER `memorandum_total_offices`;

-- Create index for better performance
CREATE INDEX `idx_memorandum_tracking` ON `documents` (`is_memorandum`, `memorandum_sent_to_all_offices`);
CREATE INDEX `idx_distribution_document` ON `memorandum_distribution` (`document_id`, `office_id`);
CREATE INDEX `idx_read_logs_document` ON `memorandum_read_logs` (`document_id`, `office_id`, `user_id`); 