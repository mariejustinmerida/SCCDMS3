-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 28, 2025 at 10:22 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET sql_require_primary_key = 0;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `scc_dms`
--

-- --------------------------------------------------------

--
-- Table structure for table `collaborative_cursors`
--

CREATE TABLE `collaborative_cursors` (
  `cursor_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_id` varchar(255) NOT NULL,
  `position` int(11) NOT NULL,
  `selection_start` int(11) DEFAULT NULL,
  `selection_end` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type_id` int(11) DEFAULT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `content_path` varchar(255) DEFAULT NULL,
  `current_step` int(11) DEFAULT NULL,
  `status` enum('draft','pending','approved','rejected','revision') DEFAULT 'draft',
  `verification_code` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `google_doc_id` varchar(255) DEFAULT NULL,
  `revision_requesting_office_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `has_qr_signature` tinyint(1) DEFAULT 0,
  `is_urgent` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`document_id`, `title`, `type_id`, `creator_id`, `file_path`, `content_path`, `current_step`, `status`, `verification_code`, `created_at`, `google_doc_id`, `revision_requesting_office_id`, `updated_at`, `has_qr_signature`, `is_urgent`) VALUES
(1, 'Memorandum', 3, 2, NULL, NULL, NULL, 'pending', '840394', '2025-07-10 10:02:12', '1lzTjZSnwMnVMA3ghaWhwhgUTM8eTsO7EyYWzg1YS-v8', NULL, '2025-07-10 13:56:50', 0, 0),
(2, 'Rainy Days Ahead', 2, 2, NULL, NULL, NULL, '', '148631', '2025-07-10 10:03:11', '1H_x6EtzUGjujksafsBUhPOfSflnqca-hEe84tC1xEDM', NULL, '2025-07-10 22:39:02', 0, 1),
(3, 'Memorandum', 1, 2, NULL, NULL, NULL, 'pending', NULL, '2025-07-10 14:08:42', '199ieJ4frLQnYmjnEgiYmVLiRTK3964urubCvphR1nIQ', NULL, '2025-07-10 14:15:01', 0, 0),
(4, 'Memorandum 1', 1, 2, NULL, NULL, NULL, 'pending', '801623', '2025-07-10 14:52:50', '1aPHWDmy1QFaFUvniFKIsefJbiDSoD6jWjIzvdMzwzC0', NULL, '2025-07-10 21:32:40', 0, 1),
(5, 'Memorandum 2', 3, 2, NULL, NULL, NULL, 'approved', '853601', '2025-07-10 14:54:26', '1aLUCXxYsBZeS5LZfEZ3Hn3orAQ3hCdOIRFrUIYF_Bts', 5, '2025-07-10 21:32:34', 0, 1),
(6, 'Memorandum 3', 2, 2, NULL, NULL, NULL, 'approved', '487481', '2025-07-10 14:55:22', '1Mrrz6iu0fJ_aMoVDYgd1zpRqRqmwhCaWp9kA1UPb3es', NULL, '2025-07-10 21:32:38', 0, 1),
(7, 'Memorandum 5', 1, 2, NULL, NULL, NULL, 'rejected', '617111', '2025-07-10 17:00:46', '1r9I5WKbN8ayrGOeestn-FAafeYTbGboI9ZUeS31iYsU', NULL, '2025-07-10 21:32:36', 0, 1),
(8, 'Memorandum 343', 1, 2, NULL, NULL, NULL, 'revision', '205879', '2025-07-10 19:45:22', '1dhOnNZRgDgrwb1ezG3jTi_kyTWDAUA-AlzttIDd2DFI', 5, '2025-07-11 02:31:01', 0, 1),
(9, 'Memorandum 4353', 2, 4, NULL, NULL, NULL, '', '814016', '2025-07-10 20:00:40', '1k--z6cAwqVom1BDm3hCyHEH31-8CCmnw4nQQnCbrj3c', NULL, '2025-07-11 02:30:54', 0, 1),
(10, 'New Document 7/11/2025, 10:35:33 AM', 1, 3, NULL, NULL, NULL, 'revision', NULL, '2025-07-11 02:35:53', '1dJBIlPQEexJciPW-WxrBlVgpWsO6xijgX78o40UmXd4', 5, '2025-07-11 02:36:10', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `document_actions`
--

CREATE TABLE `document_actions` (
  `action_id` int(11) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `step_id` int(11) DEFAULT NULL,
  `action_type` enum('approve','reject','revision','forward') NOT NULL,
  `comments` text DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_attachments`
--

CREATE TABLE `document_attachments` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_changes`
--

CREATE TABLE `document_changes` (
  `change_id` int(11) NOT NULL,
  `document_id` varchar(255) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `change_type` enum('insert','delete','replace','format') NOT NULL,
  `position` int(11) NOT NULL,
  `content` text DEFAULT NULL,
  `length` int(11) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `applied` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `document_changes`
--
DELIMITER $$
CREATE TRIGGER `after_document_change_insert` AFTER INSERT ON `document_changes` FOR EACH ROW BEGIN
    -- Update the document's last modified timestamp
    UPDATE `documents` 
    SET `last_modified` = NOW()
    WHERE `document_id` = NEW.document_id;
    
    -- Create a notification for collaborators
    INSERT INTO `notifications` (`user_id`, `document_id`, `message`)
    SELECT 
        dc.user_id,
        NEW.document_id,
        CONCAT('Document has been modified by ', (SELECT full_name FROM users WHERE user_id = NEW.user_id))
    FROM `document_collaborators` dc
    WHERE dc.document_id = NEW.document_id
    AND dc.user_id != NEW.user_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `document_collaborators`
--

CREATE TABLE `document_collaborators` (
  `id` int(11) NOT NULL,
  `document_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission` enum('view','edit','admin') NOT NULL DEFAULT 'view',
  `can_edit_metadata` tinyint(1) DEFAULT 0,
  `can_manage_versions` tinyint(1) DEFAULT 0,
  `added_by` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expiry_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_comments`
--

CREATE TABLE `document_comments` (
  `comment_id` int(11) NOT NULL,
  `document_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `position_start` int(11) DEFAULT NULL,
  `position_end` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_drafts`
--

CREATE TABLE `document_drafts` (
  `draft_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `type_id` int(11) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `workflow` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_edit_sessions`
--

CREATE TABLE `document_edit_sessions` (
  `session_id` varchar(255) NOT NULL,
  `document_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive','closed') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_hold`
--

CREATE TABLE `document_hold` (
  `hold_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_locks`
--

CREATE TABLE `document_locks` (
  `lock_id` int(11) NOT NULL,
  `document_id` varchar(255) NOT NULL,
  `section_id` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `acquired_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_logs`
--

CREATE TABLE `document_logs` (
  `log_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_logs`
--

INSERT INTO `document_logs` (`log_id`, `document_id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 2, 2, 'revised', 'Document revised and sent back to requesting office.', '2025-07-10 19:22:46');

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `type_id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_types`
--

INSERT INTO `document_types` (`type_id`, `type_name`, `description`) VALUES
(1, 'Requisition Letter', 'Official request for supplies or equipment'),
(2, 'Travel Order', 'Request for official travel authorization'),
(3, 'Leave Application', 'Employee leave request form');

-- --------------------------------------------------------

--
-- Table structure for table `document_versions`
--

CREATE TABLE `document_versions` (
  `version_id` int(11) NOT NULL,
  `document_id` varchar(255) NOT NULL,
  `version_number` int(11) NOT NULL,
  `parent_version_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `content` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `comment` varchar(255) DEFAULT NULL,
  `change_summary` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `document_versions`
--
DELIMITER $$
CREATE TRIGGER `before_version_create` BEFORE INSERT ON `document_versions` FOR EACH ROW BEGIN
    -- Set the version number automatically
    SET NEW.version_number = (
        SELECT COALESCE(MAX(version_number), 0) + 1
        FROM `document_versions`
        WHERE `document_id` = NEW.document_id
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `document_workflow`
--

CREATE TABLE `document_workflow` (
  `workflow_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `recipient_type` enum('office','person') NOT NULL DEFAULT 'office',
  `step_order` int(11) NOT NULL,
  `status` enum('CURRENT','PENDING','COMPLETED') DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_workflow`
--

INSERT INTO `document_workflow` (`workflow_id`, `document_id`, `office_id`, `user_id`, `recipient_type`, `step_order`, `status`, `created_at`, `completed_at`, `comments`) VALUES
(3, 2, 5, NULL, 'office', 1, '', '2025-07-10 10:03:11', NULL, 'Revision'),
(4, 2, 3, NULL, 'office', 2, 'PENDING', '2025-07-10 10:03:11', NULL, NULL),
(5, 2, 3, NULL, 'office', 0, 'PENDING', '2025-07-10 11:22:46', NULL, 'Document has been revised as requested. Please review the changes.'),
(6, 1, 5, NULL, 'office', 1, 'PENDING', '2025-07-10 13:56:50', NULL, NULL),
(7, 1, 3, NULL, 'office', 2, 'PENDING', '2025-07-10 13:56:50', NULL, NULL),
(10, 3, 5, NULL, 'office', 1, 'PENDING', '2025-07-10 14:15:01', NULL, NULL),
(11, 3, 3, NULL, 'office', 2, 'PENDING', '2025-07-10 14:15:01', NULL, NULL),
(16, 6, 5, NULL, 'office', 1, 'COMPLETED', '2025-07-10 14:55:22', '2025-07-10 16:15:50', 'wdwad'),
(17, 6, 3, NULL, 'office', 2, 'COMPLETED', '2025-07-10 14:55:22', '2025-07-10 16:24:43', 'dadawddwd'),
(18, 4, 5, NULL, 'office', 1, 'PENDING', '2025-07-10 15:34:42', NULL, NULL),
(19, 4, 3, NULL, 'office', 2, 'PENDING', '2025-07-10 15:34:42', NULL, NULL),
(20, 5, 5, NULL, 'office', 1, 'COMPLETED', '2025-07-10 15:51:15', '2025-07-10 16:22:15', 'daddawdwdw'),
(21, 5, 3, NULL, 'office', 2, 'COMPLETED', '2025-07-10 15:51:15', '2025-07-10 16:24:49', 'wdawdwadwad'),
(22, 7, 5, NULL, 'office', 1, 'COMPLETED', '2025-07-10 17:00:46', '2025-07-10 17:04:28', 'wdawdwadwadwasda'),
(23, 7, 3, NULL, 'office', 2, '', '2025-07-10 17:00:46', NULL, 'Please cahnge the things'),
(24, 8, 5, NULL, 'office', 1, '', '2025-07-10 19:45:22', NULL, 'adwdwadawa'),
(25, 8, 3, NULL, 'office', 2, 'PENDING', '2025-07-10 19:45:22', NULL, NULL),
(26, 9, 5, NULL, 'office', 1, '', '2025-07-10 20:00:40', NULL, 'dsadsad'),
(27, 9, 3, NULL, 'office', 2, 'PENDING', '2025-07-10 20:00:40', NULL, NULL),
(28, 10, 5, NULL, 'office', 1, '', '2025-07-11 02:35:53', NULL, 'cscac'),
(29, 10, 1, NULL, 'office', 2, 'PENDING', '2025-07-11 02:35:53', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `edit_conflicts`
--

CREATE TABLE `edit_conflicts` (
  `conflict_id` int(11) NOT NULL,
  `document_id` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `conflicting_user_id` int(11) NOT NULL,
  `conflict_type` enum('content','lock','version') NOT NULL,
  `conflict_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`conflict_data`)),
  `resolved` tinyint(1) DEFAULT 0,
  `resolved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `google_tokens`
--

CREATE TABLE `google_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `access_token` text NOT NULL,
  `refresh_token` text DEFAULT NULL,
  `expires_in` int(11) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `google_tokens`
--

-- Token columns left empty; users get tokens via OAuth login. Do not commit real tokens.
INSERT INTO `google_tokens` (`id`, `user_id`, `access_token`, `refresh_token`, `expires_in`, `created`) VALUES
(1, 2, '', '', NULL, '2025-07-28 20:08:56'),
(2, 4, '', '', NULL, '2025-07-10 19:59:14'),
(3, 1, '', '', NULL, '2025-07-10 05:54:32'),
(4, 3, '', '', NULL, '2025-07-11 02:35:29');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `document_id`, `title`, `message`, `status`, `is_read`, `created_at`) VALUES
(1, 2, NULL, 'Document Approved', 'Your document \"Budget Proposal\" has been approved', 'approved', 1, '2025-07-07 04:00:32'),
(2, 2, NULL, 'Document Rejected', 'Your document \"Meeting Minutes\" has been rejected', 'rejected', 1, '2025-07-07 04:00:32'),
(3, 2, NULL, 'Revision Requested', 'Your document \"Project Plan\" requires revision', 'revision_requested', 1, '2025-07-07 04:00:32'),
(4, 2, NULL, 'Document On Hold', 'Your document \"Financial Report\" has been put on hold', 'on_hold', 1, '2025-07-07 04:00:32'),
(5, 2, NULL, 'System Update', 'The document management system has been updated with new features', 'info', 1, '2025-07-07 04:00:32'),
(6, 2, NULL, 'Document Approved', 'Your document \"Budget Proposal\" has been approved', 'approved', 1, '2025-07-07 04:01:11'),
(7, 2, NULL, 'Document Rejected', 'Your document \"Meeting Minutes\" has been rejected', 'rejected', 1, '2025-07-07 04:01:11'),
(8, 2, NULL, 'Revision Requested', 'Your document \"Project Plan\" requires revision', 'revision_requested', 1, '2025-07-07 04:01:12'),
(9, 2, NULL, 'Document On Hold', 'Your document \"Financial Report\" has been put on hold', 'on_hold', 1, '2025-07-07 04:01:12'),
(10, 2, NULL, 'System Update', 'The document management system has been updated with new features', 'info', 1, '2025-07-07 04:01:12'),
(11, 2, NULL, 'Welcome to SCCDMS', 'Welcome to the SCC Document Management System. This notification system will keep you updated on document changes.', 'info', 1, '2025-07-07 05:12:26'),
(12, 2, NULL, 'Document Update', 'A document has been updated and requires your attention.', 'pending', 1, '2025-07-07 05:12:26'),
(13, 2, NULL, 'System Notification', 'The document management system has been updated with new features.', 'info', 1, '2025-07-07 05:12:26'),
(14, 2, NULL, 'Document Approved', 'Your document \"Budget Proposal\" has been approved.', 'approved', 1, '2025-07-07 05:12:26'),
(15, 2, NULL, 'Revision Requested', 'Your document \"Meeting Minutes\" requires revision.', 'revision_requested', 1, '2025-07-07 05:12:26'),
(16, 2, NULL, 'Document Rejected', 'Your document \"Expense Report\" has been rejected.', 'rejected', 1, '2025-07-07 05:12:26'),
(17, 2, NULL, 'Document On Hold', 'Your document \"Project Proposal\" has been put on hold.', 'on_hold', 1, '2025-07-07 05:12:26'),
(33, 1, NULL, 'Welcome to the Dashboard', 'This is your notification center where you will receive important updates.', 'info', 0, '2025-07-07 13:43:18'),
(34, 1, NULL, 'Document Update', 'A document has been updated and requires your attention.', 'pending', 0, '2025-07-07 13:43:18'),
(35, 1, NULL, 'System Notification', 'The document management system has been updated with new features.', 'info', 0, '2025-07-07 13:43:19'),
(36, 4, NULL, 'Welcome to the Dashboard', 'This is your notification center where you will receive important updates.', 'info', 1, '2025-07-08 16:59:02'),
(37, 4, NULL, 'Document Update', 'A document has been updated and requires your attention.', 'pending', 1, '2025-07-08 16:59:02'),
(38, 4, NULL, 'System Notification', 'The document management system has been updated with new features.', 'info', 1, '2025-07-08 16:59:02'),
(39, 3, NULL, 'Welcome to the Dashboard', 'This is your notification center where you will receive important updates.', 'info', 1, '2025-07-08 17:06:10'),
(40, 3, NULL, 'Document Update', 'A document has been updated and requires your attention.', 'pending', 1, '2025-07-08 17:06:10'),
(41, 3, NULL, 'System Notification', 'The document management system has been updated with new features.', 'info', 1, '2025-07-08 17:06:10'),
(57, 4, 1, '', 'New document \"Memorandum\" requires your attention', NULL, 1, '2025-07-10 10:02:12'),
(58, 4, 2, '', 'New document \"Rainy Days Ahead\" requires your attention', NULL, 1, '2025-07-10 10:03:11'),
(59, 4, 3, '', 'New document \"Memorandum\" requires your attention', NULL, 1, '2025-07-10 14:08:42'),
(60, 4, 4, '', 'New document \"Memorandum 1\" requires your attention', NULL, 1, '2025-07-10 14:52:50'),
(61, 4, 5, '', 'New document \"Memorandum 2\" requires your attention', NULL, 1, '2025-07-10 14:54:26'),
(62, 4, 6, '', 'New document \"Memorandum 3\" requires your attention', NULL, 1, '2025-07-10 14:55:22'),
(63, 4, 7, '', 'New document \"Memorandum 5\" requires your attention', NULL, 1, '2025-07-10 17:00:46'),
(64, 4, 8, '', 'New document \"Memorandum 343\" requires your attention', NULL, 1, '2025-07-10 19:45:22'),
(65, 4, 9, '', 'New document \"Memorandum 4353\" requires your attention', NULL, 1, '2025-07-10 20:00:40'),
(66, 4, 10, '', 'New document \"New Document 7/11/2025, 10:35:33 AM\" requires your attention', NULL, 0, '2025-07-11 02:35:53');

-- --------------------------------------------------------

--
-- Table structure for table `offices`
--

CREATE TABLE `offices` (
  `office_id` int(11) NOT NULL,
  `office_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offices`
--

INSERT INTO `offices` (`office_id`, `office_name`) VALUES
(1, 'President Office'),
(2, 'Admin Office'),
(3, 'HR Department'),
(4, 'IT Department'),
(5, 'Finance Department'),
(6, 'Office of the Vice President for Academic Affairs'),
(7, 'Office of the Vice President for Spirituality and Formation'),
(8, 'Office of the Vice President for Administration'),
(9, 'Office of the Vice President for Finance'),
(10, 'Saint Columban Law School'),
(11, 'Graduate School'),
(12, 'Colleges Office'),
(13, 'Principals Office'),
(14, 'Research Office'),
(15, 'Registrar Office'),
(16, 'Library Office'),
(17, 'Guidance Office'),
(18, 'Student Affairs Office'),
(19, 'Priest Chaplain Office'),
(20, 'Community Engagement Program Office'),
(21, 'Campus Ministry Office'),
(22, 'Religious Education Office'),
(23, 'Quality Assurance Office'),
(24, 'Human Resource Development Office'),
(25, 'General Services Office'),
(26, 'Security Office'),
(27, 'Management Information System Office'),
(28, 'Health Services Office'),
(29, 'Accounting Office'),
(30, 'Treasury Office'),
(31, 'Auxiliary Services Office'),
(32, 'Asset and Procurement Office');

-- --------------------------------------------------------

--
-- Table structure for table `reminders`
--

CREATE TABLE `reminders` (
  `reminder_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `reminder_date` date NOT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reminders`
--

INSERT INTO `reminders` (`reminder_id`, `user_id`, `title`, `description`, `reminder_date`, `is_completed`, `created_at`, `updated_at`) VALUES
(1, 1, 'qwedwaddwa', 'wdawdawdwadaw', '2025-05-01', 0, '2025-05-01 21:26:11', '2025-05-01 21:26:11'),
(2, 1, 'Test Reminder 2025-05-01 23:27:47', 'This is a test reminder created directly', '2025-05-01', 0, '2025-05-01 21:27:47', '2025-05-01 21:27:47'),
(17, 5, 'sdcd', 'awdawda', '2025-05-15', 0, '2025-05-07 23:31:27', '2025-05-07 23:31:27'),
(18, 5, 'csad', 'awdw', '2025-05-07', 0, '2025-05-07 23:31:43', '2025-05-07 23:43:17'),
(20, 2, 'wdawdw', 'dawdwd', '2025-07-04', 0, '2025-07-10 17:43:14', '2025-07-10 17:43:14');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(1, 'President'),
(2, 'Admin'),
(3, 'User'),
(4, 'Vice President for Academic Affairs'),
(5, 'Vice President for Spirituality and Formation'),
(6, 'Vice President for Administration'),
(7, 'Vice President for Finance'),
(8, 'Dean, Saint Columban Law School'),
(9, 'Dean, Graduate School'),
(10, 'Dean, Colleges'),
(11, 'Principal'),
(12, 'Research Director'),
(13, 'Registrar'),
(14, 'Chief Librarian'),
(15, 'Guidance Director'),
(16, 'Director of Student Affairs'),
(17, 'Priest Chaplain'),
(18, 'Community Engagement Program Director'),
(19, 'Campus Ministry Officer'),
(20, 'Religious Education Coordinator'),
(21, 'Quality Assurance Manager'),
(22, 'Human Resource Development Officer'),
(23, 'General Services Supervisor'),
(24, 'Chief Security Officer'),
(25, 'Management Information System Head'),
(26, 'Health Services Officer'),
(27, 'Accountant'),
(28, 'Treasury Manager'),
(29, 'Auxiliary Services Manager'),
(30, 'Asset and Procurement Manager'),
(31, 'Vice President for Academic Affairs'),
(32, 'Vice President for Spirituality and Formation'),
(33, 'Vice President for Administration'),
(34, 'Vice President for Finance'),
(35, 'Dean, Saint Columban Law School'),
(36, 'Dean, Graduate School'),
(37, 'Dean, Colleges'),
(38, 'Principal'),
(39, 'Research Director'),
(40, 'Registrar'),
(41, 'Chief Librarian'),
(42, 'Guidance Director'),
(43, 'Director of Student Affairs'),
(44, 'Priest Chaplain'),
(45, 'Community Engagement Program Director'),
(46, 'Campus Ministry Officer'),
(47, 'Religious Education Coordinator'),
(48, 'Quality Assurance Manager'),
(49, 'Human Resource Development Officer'),
(50, 'General Services Supervisor'),
(51, 'Chief Security Officer'),
(52, 'Management Information System Head'),
(53, 'Health Services Officer'),
(54, 'Accountant'),
(55, 'Treasury Manager'),
(56, 'Auxiliary Services Manager'),
(57, 'Asset and Procurement Manager');

-- --------------------------------------------------------

--
-- Table structure for table `role_office_mapping`
--

CREATE TABLE `role_office_mapping` (
  `mapping_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_office_mapping`
--

INSERT INTO `role_office_mapping` (`mapping_id`, `role_id`, `office_id`) VALUES
(1, 4, 6),
(2, 31, 6),
(3, 4, 6),
(4, 31, 6),
(8, 5, 7),
(9, 32, 7),
(10, 5, 7),
(11, 32, 7),
(15, 6, 8),
(16, 33, 8),
(17, 6, 8),
(18, 33, 8),
(22, 7, 9),
(23, 34, 9),
(24, 7, 9),
(25, 34, 9),
(29, 8, 10),
(30, 35, 10),
(31, 8, 10),
(32, 35, 10),
(36, 9, 11),
(37, 36, 11),
(38, 9, 11),
(39, 36, 11),
(43, 10, 12),
(44, 37, 12),
(45, 10, 12),
(46, 37, 12),
(50, 11, 13),
(51, 38, 13),
(52, 11, 13),
(53, 38, 13),
(57, 12, 14),
(58, 39, 14),
(59, 12, 14),
(60, 39, 14),
(64, 13, 15),
(65, 40, 15),
(66, 13, 15),
(67, 40, 15),
(71, 14, 16),
(72, 41, 16),
(73, 14, 16),
(74, 41, 16),
(78, 15, 17),
(79, 42, 17),
(80, 15, 17),
(81, 42, 17),
(85, 16, 18),
(86, 43, 18),
(87, 16, 18),
(88, 43, 18),
(92, 17, 19),
(93, 44, 19),
(94, 17, 19),
(95, 44, 19),
(99, 18, 20),
(100, 45, 20),
(101, 18, 20),
(102, 45, 20),
(106, 19, 21),
(107, 46, 21),
(108, 19, 21),
(109, 46, 21),
(113, 20, 22),
(114, 47, 22),
(115, 20, 22),
(116, 47, 22),
(120, 21, 23),
(121, 48, 23),
(122, 21, 23),
(123, 48, 23),
(127, 22, 24),
(128, 49, 24),
(129, 22, 24),
(130, 49, 24),
(134, 23, 25),
(135, 50, 25),
(136, 23, 25),
(137, 50, 25),
(141, 24, 26),
(142, 51, 26),
(143, 24, 26),
(144, 51, 26),
(148, 25, 27),
(149, 52, 27),
(150, 25, 27),
(151, 52, 27),
(155, 26, 28),
(156, 53, 28),
(157, 26, 28),
(158, 53, 28),
(162, 27, 29),
(163, 54, 29),
(164, 27, 29),
(165, 54, 29),
(169, 28, 30),
(170, 55, 30),
(171, 28, 30),
(172, 55, 30),
(176, 29, 31),
(177, 56, 31),
(178, 29, 31),
(179, 56, 31),
(183, 30, 32),
(184, 57, 32),
(185, 30, 32),
(186, 57, 32);

-- --------------------------------------------------------

--
-- Table structure for table `signatures`
--

CREATE TABLE `signatures` (
  `id` varchar(50) NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `office_id` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `verification_hash` varchar(255) NOT NULL,
  `is_revoked` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `signature_approvals`
--

CREATE TABLE `signature_approvals` (
  `approval_id` int(11) NOT NULL,
  `signature_id` varchar(50) NOT NULL,
  `office_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `approved_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `simple_verifications`
--

CREATE TABLE `simple_verifications` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `office_id` varchar(50) NOT NULL,
  `verification_code` varchar(10) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `simple_verifications`
--

INSERT INTO `simple_verifications` (`id`, `document_id`, `user_id`, `office_id`, `verification_code`, `created_at`) VALUES
(1, 6, 4, '5', '103681', '2025-07-11 00:15:50'),
(2, 5, 4, '5', '348891', '2025-07-11 00:22:15'),
(3, 6, 3, '3', '004734', '2025-07-11 00:24:43'),
(4, 5, 3, '3', '981233', '2025-07-11 00:24:49'),
(5, 7, 4, '5', '382824', '2025-07-11 01:04:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role_id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `full_name`, `role_id`, `office_id`, `profile_image`, `created_at`) VALUES
(1, 'admin', '$2y$10$8Qu.0QEJ2DL8AQWG.yup3.wKT3EhQMhT9ihPEZuWZgz4L2tzxVz8q', 'admin@scc.edu', 'System Administrator', 2, 2, NULL, '2025-02-23 08:00:22'),
(2, 'President', '$2y$10$z4P4w3om/pLRkkDXoDs93Oq87rCDfnEAsb.7qP7.qaldstLXiAXdG', 'president@sccpag.edu.ph', 'President', 1, 1, 'storage\\profiles\\profile_2_1741626908.jpg', '2025-02-23 08:02:55'),
(3, 'HR', '$2y$10$qrOx2p7Wb4j7zhR7vtjHUe190SH8TNxU.oCOOLoA/SnJH/cjT4oxS', 'hr@sccpag.edu.ph', 'HR', 2, 3, NULL, '2025-02-23 08:03:19'),
(4, 'Finance', '$2y$10$fKUxE3zRVK4pO0QIHBn9ce4Zo0A10w2eJoJhE1amjSk04TO5zdym6', 'finance@sccpag.edu.ph', 'Finance', 2, 5, NULL, '2025-02-23 08:03:52'),
(5, 'IT', '$2y$10$rlgIU3MFus7zGrZZaZq8eOYJQQSf80ijJlgtscTEUGiw6LZqTQ3Ka', 'it@sccpag.edu.ph', 'IT', 2, 4, NULL, '2025-02-23 08:04:21'),
(6, 'vpfinance', '$2y$10$cB.AEeZdzrpJFkxJ3sIbMuc2qKclcuk69vlVkPvQz21RNLY.D.H4e', 'vpfinance@sccpag.edu.ph', 'Vpfinance', 7, 9, NULL, '2025-03-28 17:50:03');

-- --------------------------------------------------------

--
-- Table structure for table `user_logs`
--

CREATE TABLE `user_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_logs`
--

INSERT INTO `user_logs` (`log_id`, `user_id`, `action`, `timestamp`) VALUES
(416, 2, 'login', '2025-03-16 08:08:17'),
(417, 2, 'logout', '2025-03-16 08:57:20'),
(418, 2, 'logout', '2025-03-16 08:12:15'),
(419, 2, 'login', '2025-03-16 08:12:46'),
(420, 2, 'logout', '2025-03-16 08:37:08'),
(421, 5, 'login', '2025-03-16 08:37:11'),
(422, 5, 'logout', '2025-03-16 08:37:40'),
(423, 2, 'login', '2025-03-16 08:37:42'),
(424, 2, 'logout', '2025-03-16 08:55:09'),
(425, 5, 'login', '2025-03-16 08:55:12'),
(426, 5, 'logout', '2025-03-16 09:17:17'),
(427, 2, 'login', '2025-03-16 09:17:20'),
(428, 2, 'logout', '2025-03-16 09:21:00'),
(429, 5, 'login', '2025-03-16 09:21:03'),
(430, 5, 'logout', '2025-03-16 09:22:57'),
(431, 2, 'login', '2025-03-16 09:23:00'),
(432, 2, 'logout', '2025-03-16 11:03:25'),
(433, 5, 'login', '2025-03-16 11:03:28'),
(434, 5, 'logout', '2025-03-16 11:03:36'),
(435, 5, 'login', '2025-03-16 14:12:45'),
(436, 5, 'logout', '2025-03-16 14:31:47'),
(437, 2, 'login', '2025-03-16 14:31:49'),
(438, 2, 'login', '2025-03-16 23:55:11'),
(439, 2, 'login', '2025-03-17 16:37:35'),
(440, 2, 'login', '2025-03-23 03:48:48'),
(441, 2, 'logout', '2025-03-23 05:18:48'),
(442, 3, 'login', '2025-03-23 05:18:51'),
(443, 3, 'logout', '2025-03-23 05:19:14'),
(444, 2, 'login', '2025-03-23 05:19:17'),
(445, 2, 'logout', '2025-03-23 05:50:30'),
(446, 3, 'login', '2025-03-23 05:50:32'),
(447, 3, 'logout', '2025-03-23 05:53:53'),
(448, 3, 'login', '2025-03-23 05:53:55'),
(449, 3, 'logout', '2025-03-23 05:53:58'),
(450, 2, 'login', '2025-03-23 05:54:01'),
(451, 2, 'login', '2025-03-23 06:26:57'),
(452, 2, 'login', '2025-03-23 06:38:12'),
(453, 2, 'logout', '2025-03-23 14:19:22'),
(454, 2, 'login', '2025-03-23 14:40:29'),
(455, 2, 'logout', '2025-03-23 15:57:27'),
(456, 2, 'login', '2025-03-23 15:57:29'),
(457, 2, 'login', '2025-03-26 16:22:15'),
(458, 2, 'login', '2025-03-28 16:48:59'),
(459, 2, 'logout', '2025-03-28 17:45:31'),
(460, 2, 'login', '2025-03-28 17:46:45'),
(461, 2, 'logout', '2025-03-28 17:47:52'),
(462, 2, 'login', '2025-03-28 17:48:31'),
(463, 2, 'logout', '2025-03-28 18:09:08'),
(464, 2, 'login', '2025-03-28 18:09:13'),
(465, 2, 'logout', '2025-03-28 18:15:02'),
(466, 4, 'login', '2025-03-28 18:15:13'),
(467, 4, 'logout', '2025-03-28 18:15:46'),
(468, 6, 'login', '2025-03-28 18:15:55'),
(469, 6, 'logout', '2025-03-28 18:16:12'),
(470, 2, 'login', '2025-03-28 18:16:18'),
(471, 2, 'logout', '2025-03-28 18:17:12'),
(472, 6, 'login', '2025-03-28 18:17:22'),
(473, 6, 'logout', '2025-03-28 18:17:28'),
(474, 2, 'login', '2025-03-28 18:19:55'),
(475, 2, 'logout', '2025-03-28 18:20:24'),
(476, 6, 'login', '2025-03-28 18:20:31'),
(477, 6, 'logout', '2025-03-28 18:20:34'),
(478, 4, 'login', '2025-03-28 18:20:43'),
(479, 4, 'logout', '2025-03-28 18:21:02'),
(480, 6, 'login', '2025-03-28 18:21:06'),
(481, 6, 'logout', '2025-03-28 18:21:09'),
(482, 2, 'login', '2025-03-28 18:21:13'),
(483, 2, 'logout', '2025-03-28 18:24:01'),
(484, 4, 'login', '2025-03-28 18:24:09'),
(485, 4, 'logout', '2025-03-28 18:24:20'),
(486, 2, 'login', '2025-03-28 18:24:28'),
(487, 2, 'logout', '2025-03-28 18:24:38'),
(488, 6, 'login', '2025-03-28 18:24:51'),
(489, 6, 'logout', '2025-03-28 18:30:06'),
(490, 2, 'login', '2025-03-28 18:35:02'),
(491, 2, 'logout', '2025-03-28 18:38:29'),
(492, 4, 'login', '2025-03-28 18:38:45'),
(493, 4, 'logout', '2025-03-28 18:38:58'),
(494, 2, 'login', '2025-03-28 18:39:03'),
(495, 2, 'logout', '2025-03-28 18:44:21'),
(496, 2, 'login', '2025-03-29 01:25:26'),
(497, 2, 'login', '2025-03-29 01:45:11'),
(498, 2, 'logout', '2025-03-29 01:59:32'),
(499, 6, 'login', '2025-03-29 01:59:35'),
(500, 6, 'logout', '2025-03-29 01:59:51'),
(501, 2, 'login', '2025-03-29 02:00:06'),
(502, 2, 'logout', '2025-03-29 02:09:32'),
(503, 4, 'login', '2025-03-29 02:09:38'),
(504, 4, 'logout', '2025-03-29 02:09:52'),
(505, 6, 'login', '2025-03-29 02:10:02'),
(506, 6, 'logout', '2025-03-29 02:10:11'),
(507, 2, 'login', '2025-03-29 02:10:15'),
(508, 2, 'logout', '2025-03-29 02:15:06'),
(509, 6, 'login', '2025-03-29 02:15:10'),
(510, 6, 'logout', '2025-03-29 02:15:22'),
(511, 2, 'login', '2025-03-29 02:15:26'),
(512, 2, 'logout', '2025-03-29 02:21:30'),
(513, 4, 'login', '2025-03-29 02:21:35'),
(514, 4, 'logout', '2025-03-29 02:21:53'),
(515, 6, 'login', '2025-03-29 02:21:56'),
(516, 6, 'logout', '2025-03-29 02:22:22'),
(517, 2, 'login', '2025-03-29 02:22:26'),
(518, 2, 'logout', '2025-03-29 02:25:19'),
(519, 2, 'login', '2025-03-29 02:25:23'),
(520, 2, 'logout', '2025-03-29 02:26:12'),
(521, 4, 'login', '2025-03-29 02:26:17'),
(522, 4, 'logout', '2025-03-29 02:26:33'),
(523, 2, 'login', '2025-03-29 02:26:37'),
(524, 2, 'logout', '2025-03-29 02:26:46'),
(525, 6, 'login', '2025-03-29 02:27:02'),
(526, 6, 'logout', '2025-03-29 02:27:15'),
(527, 2, 'login', '2025-03-29 02:27:28'),
(528, 2, 'logout', '2025-03-29 02:31:16'),
(529, 4, 'login', '2025-03-29 02:31:22'),
(530, 4, 'logout', '2025-03-29 02:32:21'),
(531, 2, 'login', '2025-03-29 02:32:27'),
(532, 2, 'logout', '2025-03-29 02:37:50'),
(533, 4, 'login', '2025-03-29 02:37:54'),
(534, 4, 'logout', '2025-03-29 02:38:12'),
(535, 2, 'login', '2025-03-29 02:38:26'),
(536, 2, 'logout', '2025-03-29 10:06:17'),
(537, 6, 'login', '2025-03-29 10:06:22'),
(538, 6, 'track_document', '2025-03-29 10:06:38'),
(539, 6, 'logout', '2025-03-29 10:06:57'),
(540, 2, 'login', '2025-03-29 10:07:00'),
(541, 2, 'access_compose', '2025-03-29 10:09:40'),
(542, 2, 'access_compose', '2025-03-29 10:18:36'),
(543, 2, 'access_compose', '2025-03-29 10:19:32'),
(544, 2, 'logout', '2025-03-29 10:25:43'),
(545, 2, 'login', '2025-03-29 10:31:28'),
(546, 2, 'access_compose', '2025-03-29 11:57:00'),
(547, 2, 'access_compose', '2025-03-29 12:03:11'),
(548, 2, 'access_compose', '2025-03-29 12:04:29'),
(549, 2, 'access_compose', '2025-03-29 12:22:18'),
(550, 2, 'access_compose', '2025-03-29 12:27:43'),
(551, 2, 'access_compose', '2025-03-29 12:30:56'),
(552, 2, 'access_compose', '2025-03-29 12:46:33'),
(553, 2, 'access_compose', '2025-03-29 12:46:35'),
(554, 2, 'access_compose', '2025-03-29 12:48:59'),
(555, 2, 'access_compose', '2025-03-29 12:54:22'),
(556, 2, 'access_compose', '2025-03-29 12:56:16'),
(557, 2, 'logout', '2025-03-29 12:56:18'),
(558, 2, 'login', '2025-03-29 12:58:48'),
(559, 2, 'access_compose', '2025-03-29 12:58:51'),
(560, 2, 'login', '2025-03-29 13:30:12'),
(561, 2, 'access_compose', '2025-03-29 13:30:15'),
(562, 2, 'access_compose', '2025-03-29 13:49:13'),
(563, 2, 'access_compose', '2025-03-29 13:49:16'),
(564, 2, 'access_compose', '2025-03-29 13:51:47'),
(565, 2, 'access_compose', '2025-03-29 13:57:23'),
(566, 2, 'access_compose', '2025-03-29 14:01:38'),
(567, 2, 'access_compose', '2025-03-29 14:11:58'),
(568, 2, 'access_compose', '2025-03-29 14:12:42'),
(569, 2, 'access_compose', '2025-03-29 14:13:31'),
(570, 2, 'access_compose', '2025-03-29 14:14:40'),
(571, 2, 'access_compose', '2025-03-29 14:21:42'),
(572, 2, 'access_compose', '2025-03-29 14:24:44'),
(573, 2, 'access_compose', '2025-03-29 14:25:24'),
(574, 2, 'access_compose', '2025-03-29 14:28:47'),
(575, 2, 'access_compose', '2025-03-29 14:28:50'),
(576, 2, 'access_compose', '2025-03-29 14:29:42'),
(577, 2, 'access_compose', '2025-03-29 14:29:53'),
(578, 2, 'access_compose', '2025-03-29 14:30:45'),
(579, 2, 'access_compose', '2025-03-29 14:37:40'),
(580, 2, 'access_compose', '2025-03-29 14:59:58'),
(581, 2, 'access_compose', '2025-03-29 15:00:11'),
(582, 2, 'access_compose', '2025-03-29 15:00:34'),
(583, 2, 'access_compose', '2025-03-29 15:00:55'),
(584, 2, 'access_compose', '2025-03-29 15:01:24'),
(585, 2, 'access_compose', '2025-03-29 15:01:37'),
(586, 2, 'access_compose', '2025-03-29 15:01:38'),
(587, 2, 'access_compose', '2025-03-29 15:01:38'),
(588, 2, 'access_compose', '2025-03-29 15:01:40'),
(589, 2, 'access_compose', '2025-03-29 15:01:53'),
(590, 2, 'access_compose', '2025-03-29 15:01:53'),
(591, 2, 'access_compose', '2025-03-29 15:01:54'),
(592, 2, 'access_compose', '2025-03-29 15:01:54'),
(593, 2, 'access_compose', '2025-03-29 15:01:54'),
(594, 2, 'access_compose', '2025-03-29 15:01:54'),
(595, 2, 'access_compose', '2025-03-29 15:02:00'),
(596, 2, 'access_compose', '2025-03-29 15:02:02'),
(597, 2, 'access_compose', '2025-03-29 15:02:13'),
(598, 2, 'access_compose', '2025-03-29 15:04:06'),
(599, 2, 'access_compose', '2025-03-29 15:05:09'),
(600, 2, 'access_compose', '2025-03-29 15:07:21'),
(601, 2, 'access_compose', '2025-03-29 15:07:28'),
(602, 2, 'access_compose', '2025-03-29 15:07:35'),
(603, 2, 'access_compose', '2025-03-29 15:10:11'),
(604, 2, 'access_compose', '2025-03-29 15:10:49'),
(605, 2, 'access_compose', '2025-03-29 15:11:03'),
(606, 2, 'access_compose', '2025-03-29 15:13:13'),
(607, 2, 'access_compose', '2025-03-29 15:13:45'),
(608, 2, 'access_compose', '2025-03-29 15:16:50'),
(609, 2, 'access_compose', '2025-03-29 15:23:31'),
(610, 2, 'access_compose', '2025-03-29 15:24:01'),
(611, 2, 'logout', '2025-03-29 15:24:19'),
(612, 2, 'login', '2025-03-29 15:24:23'),
(613, 2, 'access_compose', '2025-03-29 15:24:25'),
(614, 2, 'access_compose', '2025-03-29 15:24:30'),
(615, 2, 'access_compose', '2025-03-29 15:25:52'),
(616, 2, 'access_compose', '2025-03-29 15:28:05'),
(617, 2, 'access_compose', '2025-03-29 15:28:10'),
(618, 2, 'login', '2025-03-29 15:31:52'),
(619, 2, 'access_compose', '2025-03-29 15:31:54'),
(620, 2, 'login', '2025-03-29 15:56:14'),
(621, 2, 'access_compose', '2025-03-29 15:56:17'),
(622, 2, 'login', '2025-03-29 17:49:16'),
(623, 2, 'access_compose', '2025-03-29 17:49:20'),
(624, 2, 'access_compose', '2025-03-29 17:52:34'),
(625, 2, 'access_compose', '2025-03-29 17:53:08'),
(626, 2, 'access_compose', '2025-03-29 17:56:13'),
(627, 2, 'login', '2025-03-29 17:58:59'),
(628, 2, 'access_compose', '2025-03-29 17:59:02'),
(629, 2, 'access_compose', '2025-03-29 17:59:21'),
(630, 2, 'access_compose', '2025-03-29 17:59:49'),
(631, 2, 'access_compose', '2025-03-29 18:04:35'),
(632, 2, 'access_compose', '2025-03-29 18:18:21'),
(633, 2, 'access_compose', '2025-03-29 18:21:27'),
(634, 2, 'access_compose', '2025-03-29 18:26:43'),
(635, 2, 'access_compose', '2025-03-29 18:27:20'),
(636, 2, 'login', '2025-03-30 03:15:17'),
(637, 2, 'access_compose', '2025-03-30 03:15:32'),
(638, 2, 'access_compose', '2025-03-30 04:32:20'),
(639, 2, 'access_compose', '2025-03-30 04:32:26'),
(640, 2, 'access_compose', '2025-03-30 04:35:21'),
(641, 2, 'access_compose', '2025-03-30 04:40:03'),
(642, 2, 'access_compose', '2025-03-30 04:40:30'),
(643, 2, 'access_compose', '2025-03-30 04:48:16'),
(644, 2, 'access_compose', '2025-03-30 04:50:30'),
(645, 2, 'access_compose', '2025-03-30 10:22:09'),
(646, 2, 'access_compose', '2025-03-30 10:22:48'),
(647, 2, 'access_compose', '2025-03-30 10:22:58'),
(648, 2, 'access_compose', '2025-03-30 10:23:16'),
(649, 2, 'access_compose', '2025-03-30 10:32:04'),
(650, 2, 'access_compose', '2025-03-30 10:42:37'),
(651, 2, 'track_document', '2025-03-30 10:43:30'),
(652, 2, 'logout', '2025-03-30 10:43:38'),
(653, 4, 'login', '2025-03-30 10:43:47'),
(654, 4, 'logout', '2025-03-30 10:44:14'),
(655, 2, 'login', '2025-03-30 10:44:19'),
(656, 2, 'track_document', '2025-03-30 10:44:30'),
(657, 2, 'access_compose', '2025-03-30 10:50:24'),
(658, 2, 'access_compose', '2025-03-30 10:50:57'),
(659, 2, 'track_document', '2025-03-30 10:51:08'),
(660, 2, 'logout', '2025-03-30 10:51:11'),
(661, 4, 'login', '2025-03-30 10:51:15'),
(662, 4, 'access_compose', '2025-03-30 11:06:08'),
(663, 4, 'access_compose', '2025-03-30 11:06:29'),
(664, 4, 'access_compose', '2025-03-30 11:07:14'),
(665, 4, 'logout', '2025-03-30 11:07:22'),
(666, 2, 'login', '2025-03-30 11:07:26'),
(667, 2, 'access_compose', '2025-03-30 11:13:09'),
(668, 2, 'access_compose', '2025-03-30 11:14:15'),
(669, 2, 'track_document', '2025-03-30 11:14:19'),
(670, 2, 'logout', '2025-03-30 11:14:26'),
(671, 4, 'login', '2025-03-30 11:14:31'),
(672, 4, 'logout', '2025-03-30 11:16:59'),
(673, 2, 'login', '2025-03-30 11:17:03'),
(674, 2, 'track_document', '2025-03-30 11:17:08'),
(675, 2, 'logout', '2025-03-30 11:21:36'),
(676, 4, 'login', '2025-03-30 11:21:44'),
(677, 4, 'track_document', '2025-03-30 11:21:51'),
(678, 4, 'track_document', '2025-03-30 11:55:32'),
(679, 4, 'track_document', '2025-03-30 11:55:35'),
(680, 4, 'track_document', '2025-03-30 11:55:38'),
(681, 4, 'track_document', '2025-03-30 11:55:47'),
(682, 4, 'track_document', '2025-03-30 11:55:51'),
(683, 4, 'track_document', '2025-03-30 11:55:55'),
(684, 4, 'track_document', '2025-03-30 11:55:59'),
(685, 4, 'logout', '2025-03-30 11:59:10'),
(686, 2, 'login', '2025-03-30 11:59:14'),
(687, 2, 'access_compose', '2025-03-30 11:59:18'),
(688, 2, 'access_compose', '2025-03-30 11:59:37'),
(689, 2, 'access_compose', '2025-03-30 12:00:05'),
(690, 2, 'track_document', '2025-03-30 12:00:30'),
(691, 2, 'logout', '2025-03-30 12:00:36'),
(692, 4, 'login', '2025-03-30 12:00:45'),
(693, 4, 'logout', '2025-03-30 12:29:36'),
(694, 2, 'login', '2025-03-30 12:29:40'),
(695, 2, 'logout', '2025-03-30 12:31:15'),
(696, 4, 'login', '2025-03-30 12:31:19'),
(697, 2, 'login', '2025-03-30 13:27:32'),
(698, 2, 'track_document', '2025-03-30 13:27:38'),
(699, 2, 'logout', '2025-03-30 13:27:46'),
(700, 4, 'login', '2025-03-30 13:27:50'),
(701, 4, 'access_compose', '2025-03-30 13:45:47'),
(702, 4, 'logout', '2025-03-30 13:52:07'),
(703, 2, 'login', '2025-03-30 13:52:11'),
(704, 2, 'logout', '2025-03-30 13:52:22'),
(705, 4, 'login', '2025-03-30 13:52:36'),
(706, 4, 'track_document', '2025-03-30 15:17:34'),
(707, 4, 'access_compose', '2025-03-30 15:40:04'),
(708, 4, 'access_compose', '2025-03-30 15:40:07'),
(709, 4, 'access_compose', '2025-03-30 15:43:57'),
(710, 4, 'access_compose', '2025-03-30 15:44:18'),
(711, 4, 'track_document', '2025-03-30 15:44:45'),
(712, 4, 'logout', '2025-03-30 16:35:08'),
(713, 2, 'login', '2025-03-30 16:35:14'),
(714, 2, 'logout', '2025-03-30 16:35:31'),
(715, 4, 'login', '2025-03-30 16:35:34'),
(716, 4, 'track_document', '2025-03-30 16:49:09'),
(717, 4, 'track_document', '2025-03-30 16:49:43'),
(718, 4, 'logout', '2025-03-30 16:49:47'),
(719, 2, 'login', '2025-03-30 16:49:51'),
(720, 2, 'track_document', '2025-03-30 16:50:00'),
(721, 2, 'logout', '2025-03-30 16:50:04'),
(722, 4, 'login', '2025-03-30 16:50:08'),
(723, 4, 'access_compose', '2025-03-30 17:10:40'),
(724, 4, 'access_compose', '2025-03-30 17:10:55'),
(725, 4, 'access_compose', '2025-03-30 17:11:04'),
(726, 4, 'logout', '2025-03-30 17:11:41'),
(727, 2, 'login', '2025-03-30 17:11:45'),
(728, 2, 'access_compose', '2025-03-30 17:14:18'),
(729, 2, 'logout', '2025-03-30 17:14:30'),
(730, 4, 'login', '2025-03-30 17:14:35'),
(731, 4, 'access_compose', '2025-03-30 17:14:37'),
(732, 4, 'logout', '2025-03-30 17:14:43'),
(733, 2, 'login', '2025-03-30 17:14:47'),
(734, 2, 'access_compose', '2025-03-30 17:14:50'),
(735, 2, 'access_compose', '2025-03-30 17:15:03'),
(736, 2, 'logout', '2025-03-30 17:15:06'),
(737, 4, 'login', '2025-03-30 17:15:09'),
(738, 4, 'access_compose', '2025-03-30 17:15:11'),
(739, 2, 'logout', '2025-04-28 17:41:26'),
(740, 2, 'login', '2025-04-28 17:41:36'),
(741, 2, 'access_compose', '2025-04-28 17:41:38'),
(742, 2, 'access_compose', '2025-04-28 17:41:51'),
(743, 2, 'access_compose', '2025-04-28 17:41:54'),
(744, 2, 'access_compose', '2025-04-28 17:51:07'),
(745, 2, 'access_compose', '2025-04-28 18:07:28'),
(746, 2, 'access_compose', '2025-04-28 18:08:43'),
(747, 2, 'access_edit_document', '2025-04-28 18:37:45'),
(748, 2, 'access_compose', '2025-04-28 18:38:01'),
(749, 2, 'access_compose', '2025-04-28 18:38:19'),
(750, 2, 'track_document', '2025-04-28 18:38:23'),
(751, 2, 'logout', '2025-04-28 18:38:32'),
(752, 4, 'login', '2025-04-28 18:38:41'),
(753, 4, 'logout', '2025-04-28 18:42:24'),
(754, 2, 'login', '2025-04-28 18:42:29'),
(755, 2, 'track_document', '2025-04-28 18:42:33'),
(756, 2, 'logout', '2025-04-28 18:43:12'),
(757, 4, 'login', '2025-04-28 18:43:22'),
(758, 4, 'logout', '2025-04-28 18:53:15'),
(759, 2, 'login', '2025-04-28 18:53:19'),
(760, 2, 'track_document', '2025-04-28 18:53:24'),
(761, 2, 'logout', '2025-04-28 18:55:07'),
(762, 4, 'login', '2025-04-28 18:55:12'),
(763, 4, 'logout', '2025-04-28 18:55:33'),
(764, 2, 'login', '2025-04-28 18:55:37'),
(765, 2, 'access_compose', '2025-04-28 18:55:42'),
(766, 2, 'access_compose', '2025-04-28 18:55:57'),
(767, 2, 'access_compose', '2025-04-28 18:56:20'),
(768, 2, 'logout', '2025-04-28 18:56:23'),
(769, 4, 'login', '2025-04-28 18:56:31'),
(770, 4, 'logout', '2025-04-28 18:56:55'),
(771, 2, 'login', '2025-04-28 18:57:02'),
(772, 2, 'track_document', '2025-04-28 18:57:07'),
(773, 2, 'track_document', '2025-04-28 19:01:54'),
(774, 2, 'view_document', '2025-04-28 19:02:13'),
(775, 2, 'track_document', '2025-04-28 19:02:22'),
(776, 2, 'track_document', '2025-04-28 19:02:28'),
(777, 2, 'access_edit_document', '2025-04-28 19:02:31'),
(778, 2, 'view_document', '2025-04-28 19:02:53'),
(779, 2, 'access_compose', '2025-04-28 19:02:59'),
(780, 2, 'login', '2025-04-30 01:46:18'),
(781, 2, 'access_edit_document', '2025-04-30 01:46:37'),
(782, 2, 'edit_document', '2025-04-30 01:46:59'),
(783, 2, 'track_document', '2025-04-30 01:47:08'),
(784, 2, 'access_compose', '2025-04-30 01:49:47'),
(785, 2, 'track_document', '2025-04-30 02:16:14'),
(786, 2, 'track_document', '2025-04-30 02:16:18'),
(787, 2, 'track_document', '2025-04-30 02:16:24'),
(788, 2, 'track_document', '2025-04-30 02:16:28'),
(789, 2, 'logout', '2025-04-30 02:16:37'),
(790, 2, 'login', '2025-04-30 02:16:54'),
(791, 2, 'access_compose', '2025-04-30 02:17:10'),
(792, 2, 'access_compose', '2025-04-30 02:17:47'),
(793, 2, 'logout', '2025-04-30 02:17:51'),
(794, 4, 'login', '2025-04-30 02:17:55'),
(795, 4, 'logout', '2025-04-30 02:21:25'),
(796, 2, 'login', '2025-04-30 02:21:28'),
(797, 2, 'track_document', '2025-04-30 02:23:54'),
(798, 2, 'track_document', '2025-04-30 02:30:39'),
(799, 2, 'access_compose', '2025-04-30 02:36:21'),
(800, 2, 'access_compose', '2025-04-30 02:37:04'),
(801, 2, 'track_document', '2025-04-30 02:37:20'),
(802, 2, 'access_compose', '2025-04-30 02:38:56'),
(803, 2, 'access_compose', '2025-04-30 02:39:29'),
(804, 2, 'access_edit_document', '2025-04-30 02:39:35'),
(805, 2, 'logout', '2025-04-30 02:39:44'),
(806, 4, 'login', '2025-04-30 02:39:50'),
(807, 2, 'login', '2025-04-30 17:55:09'),
(808, 2, 'access_compose', '2025-04-30 17:55:13'),
(809, 2, 'access_compose', '2025-04-30 17:59:15'),
(810, 2, 'track_document', '2025-04-30 17:59:20'),
(811, 2, 'logout', '2025-04-30 17:59:29'),
(812, 4, 'login', '2025-04-30 17:59:35'),
(813, 4, 'track_document', '2025-04-30 18:00:37'),
(814, 4, 'logout', '2025-04-30 18:00:48'),
(815, 3, 'login', '2025-04-30 18:00:57'),
(816, 3, 'logout', '2025-04-30 18:01:11'),
(817, 2, 'login', '2025-04-30 18:01:15'),
(818, 2, 'track_document', '2025-04-30 18:04:53'),
(819, 2, 'access_compose', '2025-04-30 18:09:33'),
(820, 2, 'track_document', '2025-04-30 18:10:44'),
(821, 2, 'logout', '2025-04-30 18:10:47'),
(822, 4, 'login', '2025-04-30 18:10:53'),
(823, 4, 'track_document', '2025-04-30 18:11:19'),
(824, 4, 'logout', '2025-04-30 18:11:30'),
(825, 2, 'login', '2025-04-30 18:11:33'),
(826, 2, 'track_document', '2025-04-30 18:11:38'),
(827, 2, 'logout', '2025-04-30 18:11:46'),
(828, 3, 'login', '2025-04-30 18:12:04'),
(829, 3, 'logout', '2025-04-30 18:12:28'),
(830, 2, 'login', '2025-04-30 18:12:33'),
(831, 2, 'track_document', '2025-04-30 18:12:37'),
(832, 2, 'access_edit_document', '2025-04-30 18:16:32'),
(833, 2, 'access_compose', '2025-04-30 18:16:58'),
(834, 2, 'access_compose', '2025-04-30 18:19:35'),
(835, 2, 'track_document', '2025-04-30 18:19:52'),
(836, 2, 'logout', '2025-04-30 18:19:56'),
(837, 4, 'login', '2025-04-30 18:20:01'),
(838, 4, 'track_document', '2025-04-30 18:20:34'),
(839, 4, 'access_compose', '2025-04-30 18:21:51'),
(840, 4, 'logout', '2025-04-30 18:22:36'),
(841, 2, 'login', '2025-04-30 18:22:40'),
(842, 2, 'access_compose', '2025-04-30 18:22:43'),
(843, 2, 'access_compose', '2025-04-30 18:23:15'),
(844, 2, 'logout', '2025-04-30 18:23:21'),
(845, 4, 'login', '2025-04-30 18:23:25'),
(846, 4, 'logout', '2025-04-30 18:23:34'),
(847, 3, 'login', '2025-04-30 18:23:40'),
(848, 3, 'track_document', '2025-04-30 18:23:53'),
(849, 3, 'logout', '2025-04-30 18:24:05'),
(850, 5, 'login', '2025-04-30 18:24:09'),
(851, 5, 'view_document', '2025-04-30 18:24:34'),
(852, 5, 'view_document', '2025-04-30 18:24:41'),
(853, 5, 'logout', '2025-04-30 18:25:04'),
(854, 2, 'login', '2025-04-30 18:25:13'),
(855, 2, 'track_document', '2025-04-30 18:25:20'),
(856, 2, 'access_compose', '2025-04-30 18:29:41'),
(857, 2, 'access_compose', '2025-04-30 18:30:27'),
(858, 2, 'logout', '2025-04-30 18:30:30'),
(859, 2, 'login', '2025-04-30 18:30:34'),
(860, 2, 'logout', '2025-04-30 18:30:41'),
(861, 4, 'login', '2025-04-30 18:30:45'),
(862, 4, 'logout', '2025-04-30 18:55:14'),
(863, 2, 'login', '2025-04-30 18:55:17'),
(864, 2, 'access_compose', '2025-04-30 18:55:29'),
(865, 2, 'access_compose', '2025-04-30 18:55:42'),
(866, 2, 'access_compose', '2025-04-30 18:56:19'),
(867, 2, 'track_document', '2025-04-30 18:56:24'),
(868, 2, 'logout', '2025-04-30 18:56:40'),
(869, 4, 'login', '2025-04-30 18:56:43'),
(870, 4, 'track_document', '2025-04-30 18:57:00'),
(871, 4, 'logout', '2025-04-30 18:57:04'),
(872, 3, 'login', '2025-04-30 18:57:07'),
(873, 3, 'logout', '2025-04-30 19:05:10'),
(874, 2, 'login', '2025-04-30 19:05:14'),
(875, 2, 'track_document', '2025-04-30 19:05:21'),
(876, 2, 'track_document', '2025-04-30 19:05:37'),
(877, 2, 'track_document', '2025-04-30 19:08:48'),
(878, 2, 'track_document', '2025-04-30 19:08:52'),
(879, 2, 'access_compose', '2025-04-30 19:08:58'),
(880, 2, 'access_compose', '2025-04-30 19:09:25'),
(881, 2, 'logout', '2025-04-30 19:09:29'),
(882, 4, 'login', '2025-04-30 19:09:44'),
(883, 4, 'logout', '2025-04-30 19:09:53'),
(884, 3, 'login', '2025-04-30 19:09:57'),
(885, 3, 'logout', '2025-04-30 19:10:20'),
(886, 2, 'login', '2025-04-30 19:10:23'),
(887, 2, 'track_document', '2025-04-30 19:10:31'),
(888, 2, 'track_document', '2025-04-30 19:10:34'),
(889, 2, 'track_document', '2025-04-30 19:16:31'),
(890, 2, 'access_compose', '2025-04-30 19:16:34'),
(891, 2, 'logout', '2025-04-30 19:17:01'),
(892, 4, 'login', '2025-04-30 19:17:04'),
(893, 4, 'logout', '2025-04-30 19:17:22'),
(894, 3, 'login', '2025-04-30 19:17:26'),
(895, 3, 'logout', '2025-04-30 19:17:41'),
(896, 2, 'login', '2025-04-30 19:17:45'),
(897, 2, 'access_compose', '2025-04-30 19:26:44'),
(898, 2, 'access_compose', '2025-04-30 19:27:13'),
(899, 2, 'logout', '2025-04-30 19:27:20'),
(900, 4, 'login', '2025-04-30 19:27:24'),
(901, 4, 'logout', '2025-04-30 19:27:31'),
(902, 3, 'login', '2025-04-30 19:27:40'),
(903, 3, 'logout', '2025-04-30 19:27:57'),
(904, 2, 'login', '2025-04-30 19:28:01'),
(905, 2, 'track_document', '2025-04-30 19:28:34'),
(906, 2, 'track_document', '2025-04-30 19:28:46'),
(907, 2, 'track_document', '2025-04-30 19:37:54'),
(908, 2, 'track_document', '2025-04-30 19:37:55'),
(909, 2, 'access_edit_document', '2025-04-30 19:39:19'),
(910, 2, 'track_document', '2025-04-30 19:40:00'),
(911, 2, 'track_document', '2025-04-30 19:57:02'),
(912, 2, 'track_document', '2025-04-30 20:08:00'),
(913, 2, 'access_compose', '2025-04-30 20:20:07'),
(914, 2, 'access_compose', '2025-04-30 20:20:27'),
(915, 2, 'access_compose', '2025-04-30 20:20:57'),
(916, 2, 'logout', '2025-04-30 20:21:01'),
(917, 4, 'login', '2025-04-30 20:21:07'),
(918, 4, 'track_document', '2025-04-30 20:21:17'),
(919, 4, 'logout', '2025-04-30 20:21:21'),
(920, 3, 'login', '2025-04-30 20:21:25'),
(921, 3, 'logout', '2025-04-30 20:21:55'),
(922, 2, 'login', '2025-04-30 20:21:59'),
(923, 2, 'track_document', '2025-04-30 20:36:52'),
(924, 2, 'track_document', '2025-04-30 20:37:01'),
(925, 2, 'track_document', '2025-04-30 20:37:06'),
(926, 2, 'track_document', '2025-04-30 20:37:12'),
(927, 2, 'track_document', '2025-04-30 20:37:15'),
(928, 2, 'access_compose', '2025-04-30 20:38:34'),
(929, 2, 'access_compose', '2025-04-30 20:38:45'),
(930, 2, 'access_compose', '2025-04-30 20:38:52'),
(931, 2, 'access_compose', '2025-04-30 20:38:54'),
(932, 2, 'access_compose', '2025-04-30 20:39:22'),
(933, 2, 'track_document', '2025-04-30 20:39:27'),
(934, 2, 'logout', '2025-04-30 20:39:30'),
(935, 4, 'login', '2025-04-30 20:39:34'),
(936, 4, 'logout', '2025-04-30 20:39:49'),
(937, 3, 'login', '2025-04-30 20:39:54'),
(938, 3, 'logout', '2025-04-30 20:40:09'),
(939, 2, 'login', '2025-04-30 20:40:12'),
(940, 2, 'track_document', '2025-04-30 20:40:24'),
(941, 2, 'access_edit_document', '2025-04-30 20:41:58'),
(942, 2, 'access_edit_document', '2025-04-30 20:46:54'),
(943, 2, 'access_compose', '2025-04-30 20:47:15'),
(944, 2, 'access_compose', '2025-04-30 20:47:50'),
(945, 2, 'logout', '2025-04-30 20:47:52'),
(946, 4, 'login', '2025-04-30 20:47:56'),
(947, 4, 'logout', '2025-04-30 20:48:16'),
(948, 2, 'login', '2025-04-30 20:48:20'),
(949, 2, 'track_document', '2025-04-30 20:48:37'),
(950, 2, 'access_edit_document', '2025-04-30 21:26:31'),
(951, 2, 'track_document', '2025-04-30 21:50:24'),
(952, 2, 'track_document', '2025-04-30 21:50:31'),
(953, 2, 'logout', '2025-04-30 21:50:37'),
(954, 3, 'login', '2025-04-30 21:50:41'),
(955, 3, 'logout', '2025-04-30 21:51:12'),
(956, 2, 'login', '2025-04-30 21:51:16'),
(957, 2, 'track_document', '2025-04-30 21:51:23'),
(958, 2, 'logout', '2025-04-30 21:51:40'),
(959, 4, 'login', '2025-04-30 21:51:44'),
(960, 4, 'track_document', '2025-04-30 21:51:52'),
(961, 4, 'logout', '2025-04-30 21:52:23'),
(962, 3, 'login', '2025-04-30 21:52:27'),
(963, 3, 'access_compose', '2025-04-30 21:52:41'),
(964, 3, 'logout', '2025-04-30 21:52:45'),
(965, 2, 'login', '2025-04-30 21:52:49'),
(966, 2, 'access_compose', '2025-04-30 21:52:52'),
(967, 2, 'access_compose', '2025-04-30 21:53:11'),
(968, 2, 'access_compose', '2025-04-30 21:53:58'),
(969, 2, 'logout', '2025-04-30 21:53:58'),
(970, 4, 'login', '2025-04-30 21:54:03'),
(971, 4, 'logout', '2025-04-30 21:54:14'),
(972, 3, 'login', '2025-04-30 21:54:17'),
(973, 3, 'logout', '2025-04-30 21:54:34'),
(974, 2, 'login', '2025-04-30 21:54:38'),
(975, 2, 'logout', '2025-04-30 21:54:57'),
(976, 3, 'login', '2025-04-30 21:55:01'),
(977, 3, 'track_document', '2025-04-30 21:55:08'),
(978, 3, 'logout', '2025-04-30 21:55:16'),
(979, 5, 'login', '2025-04-30 21:55:21'),
(980, 5, 'logout', '2025-04-30 21:55:26'),
(981, 3, 'login', '2025-04-30 21:55:30'),
(982, 3, 'track_document', '2025-04-30 21:55:39'),
(983, 3, 'logout', '2025-04-30 21:57:30'),
(984, 2, 'login', '2025-04-30 21:57:33'),
(985, 2, 'access_compose', '2025-04-30 22:01:45'),
(986, 2, 'logout', '2025-04-30 22:02:25'),
(987, 4, 'login', '2025-04-30 22:02:28'),
(988, 4, 'logout', '2025-04-30 22:02:48'),
(989, 2, 'login', '2025-04-30 22:02:51'),
(990, 2, 'logout', '2025-04-30 22:07:10'),
(991, 4, 'login', '2025-04-30 22:07:14'),
(992, 4, 'logout', '2025-04-30 22:07:32'),
(993, 2, 'login', '2025-04-30 22:07:36'),
(994, 2, 'track_document', '2025-04-30 22:07:42'),
(995, 2, 'access_compose', '2025-04-30 22:11:16'),
(996, 2, 'logout', '2025-04-30 22:11:45'),
(997, 4, 'login', '2025-04-30 22:11:48'),
(998, 4, 'logout', '2025-04-30 22:12:04'),
(999, 2, 'login', '2025-04-30 22:12:08'),
(1000, 2, 'logout', '2025-04-30 22:12:27'),
(1001, 2, 'login', '2025-04-30 22:12:31'),
(1002, 2, 'track_document', '2025-04-30 22:12:37'),
(1003, 2, 'logout', '2025-04-30 22:12:45'),
(1004, 4, 'login', '2025-04-30 22:12:49'),
(1005, 4, 'logout', '2025-04-30 22:13:01'),
(1006, 2, 'login', '2025-04-30 22:13:06'),
(1007, 2, 'track_document', '2025-04-30 22:13:19'),
(1008, 2, 'access_compose', '2025-04-30 22:15:57'),
(1009, 2, 'logout', '2025-04-30 22:16:29'),
(1010, 4, 'login', '2025-04-30 22:16:33'),
(1011, 4, 'logout', '2025-04-30 22:17:07'),
(1012, 2, 'login', '2025-04-30 22:17:16'),
(1013, 2, 'track_document', '2025-04-30 22:17:33'),
(1014, 2, 'access_compose', '2025-04-30 22:20:37'),
(1015, 2, 'logout', '2025-04-30 22:21:01'),
(1016, 4, 'login', '2025-04-30 22:21:10'),
(1017, 4, 'logout', '2025-04-30 22:21:27'),
(1018, 2, 'login', '2025-04-30 22:21:32'),
(1019, 2, 'track_document', '2025-04-30 22:21:37'),
(1020, 2, 'track_document', '2025-04-30 22:22:04'),
(1021, 2, 'track_document', '2025-04-30 22:23:07'),
(1022, 2, 'logout', '2025-04-30 22:27:13'),
(1023, 3, 'login', '2025-04-30 22:27:17'),
(1024, 3, 'logout', '2025-04-30 22:27:39'),
(1025, 2, 'login', '2025-04-30 22:27:43'),
(1026, 2, 'track_document', '2025-04-30 22:27:47'),
(1027, 2, 'logout', '2025-04-30 22:27:50'),
(1028, 4, 'login', '2025-04-30 22:27:54'),
(1029, 4, 'logout', '2025-04-30 22:28:17'),
(1030, 2, 'login', '2025-04-30 22:28:21'),
(1031, 2, 'track_document', '2025-04-30 22:28:24'),
(1032, 2, 'logout', '2025-04-30 22:28:30'),
(1033, 3, 'login', '2025-04-30 22:28:34'),
(1034, 3, 'logout', '2025-04-30 22:28:55'),
(1035, 2, 'login', '2025-04-30 22:29:02'),
(1036, 2, 'access_compose', '2025-04-30 22:31:02'),
(1037, 2, 'access_compose', '2025-04-30 22:31:28'),
(1038, 2, 'track_document', '2025-04-30 22:31:32'),
(1039, 2, 'logout', '2025-04-30 22:31:35'),
(1040, 4, 'login', '2025-04-30 22:31:40'),
(1041, 4, 'logout', '2025-04-30 22:31:52'),
(1042, 2, 'login', '2025-04-30 22:31:56'),
(1043, 2, 'track_document', '2025-04-30 22:32:11'),
(1044, 2, 'logout', '2025-04-30 22:32:53'),
(1045, 2, 'login', '2025-04-30 22:32:57'),
(1046, 2, 'access_compose', '2025-04-30 22:33:41'),
(1047, 2, 'access_compose', '2025-05-01 01:50:22'),
(1048, 2, 'view_document', '2025-05-01 01:56:53'),
(1049, 2, 'track_document', '2025-05-01 02:14:32'),
(1050, 3, 'login', '2025-05-01 02:20:36'),
(1051, 5, 'login', '2025-05-01 02:20:52'),
(1052, 2, 'access_compose', '2025-05-01 02:22:17'),
(1053, 3, 'access_compose', '2025-05-01 02:22:24'),
(1054, 2, 'access_compose', '2025-05-01 02:22:35'),
(1055, 2, 'access_compose', '2025-05-01 02:23:25'),
(1056, 3, 'access_compose', '2025-05-01 02:32:05'),
(1057, 5, 'access_compose', '2025-05-01 02:32:33'),
(1058, 3, 'access_compose', '2025-05-01 02:33:00'),
(1059, 5, 'access_compose', '2025-05-01 02:33:24'),
(1060, 3, 'access_compose', '2025-05-01 02:34:22'),
(1061, 5, 'track_document', '2025-05-01 02:35:58'),
(1062, 5, 'view_document', '2025-05-01 02:36:05'),
(1063, 2, 'access_compose', '2025-05-01 02:36:35'),
(1064, 2, 'access_compose', '2025-05-01 02:40:37'),
(1065, 2, 'access_compose', '2025-05-01 02:41:37'),
(1066, 2, 'track_document', '2025-05-01 02:42:19'),
(1067, 2, 'track_document', '2025-05-01 06:00:38'),
(1068, 2, 'access_compose', '2025-05-01 07:41:27'),
(1069, 2, 'access_compose', '2025-05-01 07:41:46'),
(1070, 2, 'access_compose', '2025-05-01 08:00:09'),
(1071, 2, 'access_compose', '2025-05-01 08:00:24'),
(1072, 2, 'access_compose', '2025-05-01 08:00:28'),
(1073, 2, 'access_compose', '2025-05-01 08:05:37'),
(1074, 2, 'access_compose', '2025-05-01 08:06:53'),
(1075, 2, 'access_compose', '2025-05-01 08:09:36'),
(1076, 2, 'access_compose', '2025-05-01 08:19:20'),
(1077, 2, 'access_compose', '2025-05-01 08:21:43'),
(1078, 2, 'access_compose', '2025-05-01 20:18:53'),
(1079, 2, 'login', '2025-05-01 20:25:53'),
(1080, 2, 'access_compose', '2025-05-01 20:25:56'),
(1081, 2, 'access_compose', '2025-05-01 20:27:24'),
(1082, 2, 'access_compose', '2025-05-01 20:27:30'),
(1083, 2, 'access_compose', '2025-05-01 21:35:52'),
(1084, 2, 'access_compose', '2025-05-01 21:36:41'),
(1085, 2, 'access_compose', '2025-05-01 21:37:05'),
(1086, 2, 'access_compose', '2025-05-01 21:37:32'),
(1087, 2, 'access_compose', '2025-05-01 21:39:04'),
(1088, 2, 'login', '2025-05-05 14:07:20'),
(1089, 2, 'access_compose', '2025-05-05 14:07:22'),
(1090, 2, 'access_compose', '2025-05-05 14:08:45'),
(1091, 2, 'access_compose', '2025-05-05 14:09:41'),
(1092, 2, 'access_compose', '2025-05-05 14:12:00'),
(1093, 2, 'access_compose', '2025-05-05 14:12:44'),
(1094, 2, 'access_compose', '2025-05-05 14:18:46'),
(1095, 2, 'access_compose', '2025-05-05 14:19:25'),
(1096, 2, 'access_compose', '2025-05-05 14:23:39'),
(1097, 2, 'access_compose', '2025-05-05 14:28:50'),
(1098, 2, 'access_compose', '2025-05-05 14:29:14'),
(1099, 2, 'access_compose', '2025-05-05 14:34:29'),
(1100, 2, 'access_compose', '2025-05-05 14:37:05'),
(1101, 2, 'logout', '2025-05-05 14:37:52'),
(1102, 2, 'login', '2025-05-05 14:37:56'),
(1103, 2, 'access_compose', '2025-05-05 14:37:58'),
(1104, 2, 'access_compose', '2025-05-05 14:41:52'),
(1105, 2, 'access_compose', '2025-05-05 14:44:16'),
(1106, 2, 'track_document', '2025-05-05 14:44:19'),
(1107, 2, 'logout', '2025-05-05 14:44:26'),
(1108, 4, 'login', '2025-05-05 14:44:29'),
(1109, 4, 'logout', '2025-05-05 14:44:48'),
(1110, 2, 'login', '2025-05-05 14:44:51'),
(1111, 2, 'track_document', '2025-05-05 14:44:58'),
(1112, 2, 'access_compose', '2025-05-05 14:46:25'),
(1113, 2, 'access_compose', '2025-05-05 14:47:12'),
(1114, 2, 'track_document', '2025-05-05 14:47:22'),
(1115, 2, 'access_compose', '2025-05-05 14:49:02'),
(1116, 2, 'access_compose', '2025-05-05 14:49:26'),
(1117, 2, 'access_compose', '2025-05-05 14:55:29'),
(1118, 2, 'access_compose', '2025-05-05 14:55:49'),
(1119, 2, 'track_document', '2025-05-05 14:55:54'),
(1120, 2, 'logout', '2025-05-05 14:55:59'),
(1121, 4, 'login', '2025-05-05 14:56:04'),
(1122, 4, 'track_document', '2025-05-05 14:56:16'),
(1123, 4, 'logout', '2025-05-05 14:56:19'),
(1124, 3, 'login', '2025-05-05 14:56:22'),
(1125, 3, 'track_document', '2025-05-05 14:56:41'),
(1126, 3, 'logout', '2025-05-05 14:56:44'),
(1127, 5, 'login', '2025-05-05 14:56:48'),
(1128, 5, 'track_document', '2025-05-05 14:56:52'),
(1129, 5, 'logout', '2025-05-05 14:57:08'),
(1130, 2, 'login', '2025-05-05 14:57:14'),
(1131, 2, 'track_document', '2025-05-05 14:57:22'),
(1132, 2, 'access_compose', '2025-05-05 15:03:11'),
(1133, 2, 'access_compose', '2025-05-05 15:03:54'),
(1134, 2, 'track_document', '2025-05-05 15:04:03'),
(1135, 2, 'logout', '2025-05-05 15:04:05'),
(1136, 4, 'login', '2025-05-05 15:04:09'),
(1137, 4, 'track_document', '2025-05-05 15:04:20'),
(1138, 4, 'view_document', '2025-05-05 15:04:25'),
(1139, 4, 'logout', '2025-05-05 15:04:28'),
(1140, 3, 'login', '2025-05-05 15:04:35'),
(1141, 3, 'logout', '2025-05-05 15:08:45'),
(1142, 2, 'login', '2025-05-05 15:08:48'),
(1143, 2, 'access_compose', '2025-05-05 15:08:52'),
(1144, 2, 'access_compose', '2025-05-05 15:09:16'),
(1145, 2, 'access_compose', '2025-05-05 15:09:33'),
(1146, 2, 'access_compose', '2025-05-05 15:09:57'),
(1147, 2, 'track_document', '2025-05-05 15:10:23'),
(1148, 2, 'logout', '2025-05-05 15:10:26'),
(1149, 4, 'login', '2025-05-05 15:10:42'),
(1150, 4, 'track_document', '2025-05-05 15:10:58'),
(1151, 4, 'logout', '2025-05-05 15:11:01'),
(1152, 3, 'login', '2025-05-05 15:11:09'),
(1153, 3, 'logout', '2025-05-05 15:11:47'),
(1154, 2, 'login', '2025-05-05 15:11:51'),
(1155, 2, 'track_document', '2025-05-05 15:11:56'),
(1156, 2, 'access_compose', '2025-05-05 15:16:18'),
(1157, 2, 'access_compose', '2025-05-05 15:16:38'),
(1158, 2, 'logout', '2025-05-05 15:16:44'),
(1159, 4, 'login', '2025-05-05 15:16:53'),
(1160, 4, 'track_document', '2025-05-05 15:17:07'),
(1161, 4, 'logout', '2025-05-05 15:17:11'),
(1162, 3, 'login', '2025-05-05 15:17:17'),
(1163, 3, 'logout', '2025-05-05 15:17:28'),
(1164, 5, 'login', '2025-05-05 15:17:35'),
(1165, 5, 'track_document', '2025-05-05 15:17:40'),
(1166, 5, 'logout', '2025-05-05 15:17:59'),
(1167, 2, 'login', '2025-05-05 15:43:55'),
(1168, 2, 'access_compose', '2025-05-05 15:44:07'),
(1169, 2, 'access_compose', '2025-05-05 15:44:24'),
(1170, 2, 'track_document', '2025-05-05 15:44:30'),
(1171, 2, 'logout', '2025-05-05 15:44:36'),
(1172, 4, 'login', '2025-05-05 15:44:47'),
(1173, 4, 'logout', '2025-05-05 15:56:06'),
(1174, 2, 'login', '2025-05-05 15:56:14'),
(1175, 2, 'access_compose', '2025-05-05 15:56:20'),
(1176, 2, 'access_compose', '2025-05-05 15:56:45'),
(1177, 2, 'track_document', '2025-05-05 15:56:50'),
(1178, 2, 'logout', '2025-05-05 15:56:53'),
(1179, 4, 'login', '2025-05-05 15:57:00'),
(1180, 4, 'view_document', '2025-05-05 16:00:31'),
(1181, 4, 'track_document', '2025-05-05 16:00:35'),
(1182, 4, 'logout', '2025-05-05 16:00:40'),
(1183, 3, 'login', '2025-05-05 16:00:46'),
(1184, 3, 'view_document', '2025-05-05 16:01:41'),
(1185, 3, 'logout', '2025-05-05 16:02:41'),
(1186, 5, 'login', '2025-05-05 16:02:45'),
(1187, 5, 'view_document', '2025-05-05 16:03:06'),
(1188, 5, 'track_document', '2025-05-05 16:03:09'),
(1189, 5, 'logout', '2025-05-05 16:03:14'),
(1190, 2, 'login', '2025-05-05 16:03:18'),
(1191, 2, 'track_document', '2025-05-05 16:03:22'),
(1192, 2, 'access_edit_document', '2025-05-05 16:03:24'),
(1193, 2, 'track_document', '2025-05-05 16:03:35'),
(1194, 2, 'access_compose', '2025-05-05 16:06:19'),
(1195, 2, 'access_compose', '2025-05-05 16:06:52'),
(1196, 2, 'track_document', '2025-05-05 16:06:57'),
(1197, 2, 'logout', '2025-05-05 16:06:59'),
(1198, 3, 'login', '2025-05-05 16:07:03'),
(1199, 3, 'logout', '2025-05-05 16:07:19'),
(1200, 4, 'login', '2025-05-05 16:07:22'),
(1201, 4, 'view_document', '2025-05-05 16:08:07'),
(1202, 4, 'track_document', '2025-05-05 16:08:12'),
(1203, 4, 'logout', '2025-05-05 16:08:16'),
(1204, 3, 'login', '2025-05-05 16:08:24'),
(1205, 3, 'logout', '2025-05-05 16:08:53'),
(1206, 5, 'login', '2025-05-05 16:08:57'),
(1207, 5, 'logout', '2025-05-05 16:13:04'),
(1208, 2, 'login', '2025-05-05 16:13:09'),
(1209, 2, 'access_compose', '2025-05-05 16:13:12'),
(1210, 2, 'access_compose', '2025-05-05 16:13:26'),
(1211, 2, 'access_compose', '2025-05-05 16:13:44'),
(1212, 2, 'track_document', '2025-05-05 16:13:51'),
(1213, 2, 'track_document', '2025-05-05 16:13:56'),
(1214, 2, 'logout', '2025-05-05 16:13:57'),
(1215, 4, 'login', '2025-05-05 16:14:16'),
(1216, 4, 'logout', '2025-05-05 16:14:49'),
(1217, 3, 'login', '2025-05-05 16:15:00'),
(1218, 3, 'logout', '2025-05-05 16:43:25'),
(1219, 2, 'login', '2025-05-05 16:43:29'),
(1220, 2, 'access_compose', '2025-05-05 16:43:31'),
(1221, 2, 'access_compose', '2025-05-05 16:57:37'),
(1222, 2, 'logout', '2025-05-05 16:57:43'),
(1223, 4, 'login', '2025-05-05 16:57:47'),
(1224, 4, 'logout', '2025-05-05 17:31:25'),
(1225, 2, 'login', '2025-05-05 17:33:50'),
(1226, 2, 'access_compose', '2025-05-05 17:43:00'),
(1227, 2, 'access_compose', '2025-05-05 17:43:15'),
(1228, 2, 'access_compose', '2025-05-05 17:43:35'),
(1229, 2, 'logout', '2025-05-05 17:43:41'),
(1230, 4, 'login', '2025-05-05 17:43:45'),
(1231, 4, 'track_document', '2025-05-05 17:44:13'),
(1232, 4, 'track_document', '2025-05-05 18:11:25'),
(1233, 4, 'logout', '2025-05-05 18:11:28'),
(1234, 3, 'login', '2025-05-05 18:11:32'),
(1235, 3, 'track_document', '2025-05-05 18:30:40'),
(1236, 3, 'logout', '2025-05-05 18:30:46'),
(1237, 5, 'login', '2025-05-05 18:30:50'),
(1238, 5, 'track_document', '2025-05-05 18:31:19'),
(1239, 5, 'access_compose', '2025-05-05 18:31:25'),
(1240, 5, 'logout', '2025-05-05 18:31:29'),
(1241, 1, 'access_compose', '2025-05-05 18:32:27'),
(1242, 1, 'logout', '2025-05-05 18:32:36'),
(1243, 2, 'login', '2025-05-05 18:32:39'),
(1244, 2, 'access_compose', '2025-05-05 18:34:56'),
(1245, 2, 'access_compose', '2025-05-05 18:35:23'),
(1246, 2, 'logout', '2025-05-05 18:35:35'),
(1247, 4, 'login', '2025-05-05 18:35:40'),
(1248, 4, 'logout', '2025-05-05 18:45:03'),
(1249, 5, 'login', '2025-05-05 18:45:07'),
(1250, 5, 'logout', '2025-05-05 18:45:29'),
(1251, 2, 'login', '2025-05-05 18:46:33'),
(1252, 2, 'access_compose', '2025-05-05 18:46:38'),
(1253, 2, 'access_compose', '2025-05-05 18:50:11'),
(1254, 2, 'access_compose', '2025-05-05 18:50:35'),
(1255, 2, 'logout', '2025-05-05 18:50:46'),
(1256, 4, 'login', '2025-05-05 18:50:50'),
(1257, 4, 'logout', '2025-05-05 19:12:37'),
(1258, 5, 'login', '2025-05-05 19:12:41'),
(1259, 5, 'logout', '2025-05-05 19:23:14'),
(1260, 2, 'login', '2025-05-05 19:23:17'),
(1261, 2, 'access_compose', '2025-05-05 19:23:20'),
(1262, 2, 'access_compose', '2025-05-05 19:23:38'),
(1263, 2, 'logout', '2025-05-05 19:23:48'),
(1264, 4, 'login', '2025-05-05 19:35:59'),
(1265, 4, 'access_compose', '2025-05-05 19:41:58'),
(1266, 4, 'logout', '2025-05-05 19:42:00'),
(1267, 3, 'login', '2025-05-05 19:42:09'),
(1268, 3, 'access_compose', '2025-05-05 19:50:41'),
(1269, 3, 'logout', '2025-05-05 19:51:04'),
(1270, 2, 'login', '2025-05-05 19:51:08'),
(1271, 2, 'access_compose', '2025-05-05 19:51:10'),
(1272, 2, 'access_compose', '2025-05-05 19:51:22'),
(1273, 2, 'access_compose', '2025-05-05 19:51:46'),
(1274, 2, 'track_document', '2025-05-05 19:51:50'),
(1275, 2, 'logout', '2025-05-05 19:51:54'),
(1276, 4, 'login', '2025-05-05 19:51:57'),
(1277, 4, 'logout', '2025-05-05 19:54:59'),
(1278, 5, 'login', '2025-05-05 19:55:03'),
(1279, 5, 'logout', '2025-05-05 20:19:11'),
(1280, 2, 'login', '2025-05-05 20:19:16'),
(1281, 2, 'track_document', '2025-05-05 20:19:24'),
(1282, 2, 'logout', '2025-05-05 20:20:00'),
(1283, 5, 'login', '2025-05-05 20:20:34'),
(1284, 5, 'access_compose', '2025-05-05 20:20:42'),
(1285, 5, 'logout', '2025-05-05 20:20:51'),
(1286, 4, 'login', '2025-05-05 20:20:54'),
(1287, 4, 'logout', '2025-05-05 20:20:59'),
(1288, 2, 'login', '2025-05-05 20:21:12'),
(1289, 2, 'access_compose', '2025-05-05 20:21:14'),
(1290, 2, 'access_compose', '2025-05-05 20:21:41'),
(1291, 2, 'access_compose', '2025-05-05 20:22:03'),
(1292, 2, 'access_compose', '2025-05-05 20:22:38'),
(1293, 2, 'logout', '2025-05-05 20:22:49'),
(1294, 4, 'login', '2025-05-05 20:22:56'),
(1295, 4, 'logout', '2025-05-05 20:29:11'),
(1296, 2, 'login', '2025-05-05 20:29:16'),
(1297, 2, 'logout', '2025-05-05 20:41:08'),
(1298, 4, 'login', '2025-05-05 20:41:11'),
(1299, 4, 'logout', '2025-05-05 20:43:58'),
(1300, 2, 'login', '2025-05-05 20:44:01'),
(1301, 2, 'logout', '2025-05-05 20:47:35'),
(1302, 2, 'login', '2025-05-05 20:47:47'),
(1303, 2, 'logout', '2025-05-05 20:47:53'),
(1304, 3, 'login', '2025-05-05 20:48:02'),
(1305, 3, 'view_document', '2025-05-05 20:48:31'),
(1306, 3, 'track_document', '2025-05-05 20:49:15'),
(1307, 3, 'track_document', '2025-05-05 20:49:19'),
(1308, 3, 'logout', '2025-05-05 20:49:32'),
(1309, 5, 'login', '2025-05-05 20:49:35'),
(1310, 5, 'logout', '2025-05-05 20:49:47'),
(1311, 3, 'login', '2025-05-05 20:49:54'),
(1312, 3, 'logout', '2025-05-05 20:50:41'),
(1313, 5, 'login', '2025-05-05 20:50:45'),
(1314, 5, 'logout', '2025-05-05 20:56:51'),
(1315, 2, 'login', '2025-05-05 20:56:54'),
(1316, 2, 'logout', '2025-05-05 21:01:11'),
(1317, 2, 'login', '2025-05-05 21:01:14'),
(1318, 2, 'access_compose', '2025-05-05 21:13:45'),
(1319, 2, 'logout', '2025-05-05 21:20:02'),
(1320, 5, 'login', '2025-05-05 21:20:05'),
(1321, 5, 'logout', '2025-05-05 21:28:18'),
(1322, 3, 'login', '2025-05-05 21:28:25'),
(1323, 3, 'track_document', '2025-05-05 21:29:33'),
(1324, 3, 'logout', '2025-05-05 21:29:37'),
(1325, 5, 'login', '2025-05-05 21:29:41'),
(1326, 5, 'view_document', '2025-05-05 21:30:21'),
(1327, 5, 'logout', '2025-05-05 21:30:26'),
(1328, 2, 'login', '2025-05-05 21:30:32'),
(1329, 2, 'access_edit_document', '2025-05-05 21:30:38'),
(1330, 2, 'track_document', '2025-05-05 21:30:42'),
(1331, 2, 'access_compose', '2025-05-05 21:31:16'),
(1332, 2, 'logout', '2025-05-05 21:52:04'),
(1333, 5, 'login', '2025-05-05 21:52:11'),
(1334, 5, 'track_document', '2025-05-05 23:54:26'),
(1335, 5, 'logout', '2025-05-06 00:20:01'),
(1336, 2, 'login', '2025-05-06 00:20:05'),
(1337, 2, 'logout', '2025-05-06 01:16:53'),
(1338, 4, 'login', '2025-05-06 01:16:57'),
(1339, 4, 'logout', '2025-05-06 01:17:03'),
(1340, 5, 'login', '2025-05-06 01:17:09'),
(1341, 5, 'logout', '2025-05-06 01:17:18'),
(1342, 2, 'login', '2025-05-06 01:17:23'),
(1343, 2, 'access_compose', '2025-05-06 01:17:28'),
(1344, 2, 'access_compose', '2025-05-06 01:17:51'),
(1345, 2, 'access_compose', '2025-05-06 01:18:22'),
(1346, 2, 'access_compose', '2025-05-06 01:18:46'),
(1347, 2, 'access_compose', '2025-05-06 01:19:13'),
(1348, 2, 'logout', '2025-05-06 01:19:22'),
(1349, 4, 'login', '2025-05-06 01:19:27'),
(1350, 4, 'logout', '2025-05-06 01:20:10'),
(1351, 2, 'login', '2025-05-06 01:20:15'),
(1352, 2, 'logout', '2025-05-06 01:21:59'),
(1353, 4, 'login', '2025-05-06 01:22:02'),
(1354, 4, 'logout', '2025-05-06 01:27:21'),
(1355, 2, 'login', '2025-05-06 01:27:24'),
(1356, 2, 'logout', '2025-05-06 01:28:25'),
(1357, 5, 'login', '2025-05-06 01:28:29'),
(1358, 5, 'logout', '2025-05-06 01:28:33'),
(1359, 3, 'login', '2025-05-06 01:28:37'),
(1360, 3, 'logout', '2025-05-06 01:30:22'),
(1361, 2, 'login', '2025-05-06 01:30:26'),
(1362, 2, 'login', '2025-05-06 17:41:59'),
(1363, 2, 'track_document', '2025-05-06 18:14:45'),
(1364, 2, 'track_document', '2025-05-06 18:15:46'),
(1365, 2, 'logout', '2025-05-06 18:17:09'),
(1366, 3, 'login', '2025-05-06 18:17:15'),
(1367, 3, 'view_document', '2025-05-06 18:18:03'),
(1368, 3, 'logout', '2025-05-06 18:18:42'),
(1369, 2, 'login', '2025-05-06 18:18:47'),
(1370, 2, 'logout', '2025-05-06 18:30:02'),
(1371, 3, 'login', '2025-05-06 18:30:11'),
(1372, 3, 'logout', '2025-05-06 18:37:09'),
(1373, 2, 'login', '2025-05-06 18:37:13'),
(1374, 2, 'logout', '2025-05-06 20:33:58'),
(1375, 3, 'login', '2025-05-06 20:34:04'),
(1376, 3, 'approve_document', '2025-05-06 20:34:23'),
(1377, 3, 'logout', '2025-05-06 20:36:02'),
(1378, 2, 'login', '2025-05-06 20:36:06'),
(1379, 2, 'track_document', '2025-05-06 20:36:14'),
(1380, 2, 'access_compose', '2025-05-06 20:38:23'),
(1381, 2, 'access_compose', '2025-05-06 21:12:24'),
(1382, 2, 'access_compose', '2025-05-06 21:21:33'),
(1383, 2, 'login', '2025-05-06 21:27:11'),
(1384, 2, 'access_compose', '2025-05-06 21:27:19'),
(1385, 2, 'logout', '2025-05-06 21:28:01'),
(1386, 3, 'login', '2025-05-06 21:28:17'),
(1387, 3, 'approve_document', '2025-05-06 21:28:43'),
(1388, 3, 'reject_document', '2025-05-06 21:46:23'),
(1389, 2, 'track_document', '2025-05-06 21:47:04'),
(1390, 2, 'track_document', '2025-05-06 21:47:09'),
(1391, 2, 'access_compose', '2025-05-06 21:47:34'),
(1392, 2, 'access_compose', '2025-05-06 21:47:54'),
(1393, 2, 'access_compose', '2025-05-06 21:48:25'),
(1394, 3, 'logout', '2025-05-06 21:48:33'),
(1395, 4, 'login', '2025-05-06 21:48:41'),
(1396, 4, 'reject_document', '2025-05-06 21:49:05'),
(1397, 2, 'track_document', '2025-05-06 21:49:50'),
(1398, 2, 'access_edit_document', '2025-05-06 21:50:16'),
(1399, 2, 'access_compose', '2025-05-06 21:50:39'),
(1400, 2, 'access_compose', '2025-05-06 21:51:07'),
(1401, 4, 'request_revision', '2025-05-06 21:51:39'),
(1402, 2, 'track_document', '2025-05-06 21:52:34'),
(1403, 2, 'access_compose', '2025-05-06 21:53:06'),
(1404, 2, 'access_compose', '2025-05-06 21:53:28'),
(1405, 4, 'hold', '2025-05-06 21:53:51'),
(1406, 2, 'track_document', '2025-05-06 21:54:00'),
(1407, 4, 'hold', '2025-05-06 22:05:49'),
(1408, 2, 'access_compose', '2025-05-06 22:14:17'),
(1409, 2, 'access_compose', '2025-05-06 22:14:49'),
(1410, 4, 'hold', '2025-05-06 22:15:09'),
(1411, 2, 'track_document', '2025-05-06 22:15:44'),
(1412, 2, 'access_compose', '2025-05-06 22:26:33'),
(1413, 2, 'access_compose', '2025-05-06 22:27:04'),
(1414, 4, 'hold', '2025-05-06 22:27:25'),
(1415, 4, 'approve_document', '2025-05-06 22:34:15'),
(1416, 2, 'logout', '2025-05-07 21:58:54'),
(1417, 5, 'login', '2025-05-07 21:59:01'),
(1418, 5, 'approve_document', '2025-05-07 21:59:12'),
(1419, 5, 'access_compose', '2025-05-07 23:44:48'),
(1420, 5, 'logout', '2025-05-07 23:44:52'),
(1421, 2, 'login', '2025-05-07 23:45:01'),
(1422, 2, 'access_compose', '2025-05-07 23:45:56'),
(1423, 2, 'access_compose', '2025-05-07 23:48:10'),
(1424, 2, 'access_compose', '2025-05-08 00:09:21'),
(1425, 2, 'access_compose', '2025-05-08 00:16:07'),
(1426, 2, 'access_compose', '2025-05-08 00:22:17'),
(1427, 2, 'access_compose', '2025-05-08 00:32:55'),
(1428, 2, 'access_compose', '2025-05-08 01:02:03'),
(1429, 2, 'access_compose', '2025-05-08 01:02:26'),
(1430, 2, 'logout', '2025-05-08 11:25:15'),
(1431, 2, 'login', '2025-05-08 11:28:45'),
(1432, 2, 'logout', '2025-05-08 11:32:05'),
(1433, 4, 'login', '2025-05-08 11:32:14'),
(1434, 4, 'logout', '2025-05-08 11:32:26'),
(1435, 2, 'login', '2025-05-08 11:32:30'),
(1436, 2, 'access_compose', '2025-05-08 11:32:32'),
(1437, 2, 'access_compose', '2025-05-08 11:36:20'),
(1438, 2, 'logout', '2025-05-08 11:37:29'),
(1439, 4, 'login', '2025-05-08 11:37:40'),
(1440, 4, 'approve_document', '2025-05-08 11:38:48'),
(1441, 4, 'logout', '2025-05-08 13:06:57'),
(1442, 2, 'login', '2025-05-08 13:07:04'),
(1443, 2, 'access_compose', '2025-05-08 13:07:06'),
(1444, 2, 'access_compose', '2025-05-08 13:07:23'),
(1445, 2, 'logout', '2025-05-08 13:07:41'),
(1446, 2, 'login', '2025-05-08 13:07:51'),
(1447, 2, 'access_compose', '2025-05-08 13:07:54'),
(1448, 2, 'access_compose', '2025-05-08 13:08:51'),
(1449, 2, 'login', '2025-05-10 03:20:31'),
(1450, 2, 'access_compose', '2025-05-10 03:20:34'),
(1451, 2, 'login', '2025-06-04 00:31:43'),
(1452, 2, 'access_compose', '2025-06-04 00:54:48'),
(1453, 2, 'access_compose', '2025-06-04 00:55:12'),
(1454, 2, 'access_compose', '2025-06-04 00:55:26'),
(1455, 2, 'access_compose', '2025-06-04 00:55:39'),
(1456, 2, 'track_document', '2025-06-04 01:13:02'),
(1457, 2, 'access_edit_document', '2025-06-04 01:14:26'),
(1458, 2, 'login', '2025-07-03 23:46:50'),
(1459, 2, 'track_document', '2025-07-03 23:48:30'),
(1460, 2, 'login', '2025-07-05 07:30:48'),
(1461, 2, 'access_compose', '2025-07-05 07:49:55'),
(1462, 2, 'access_compose', '2025-07-05 09:11:08'),
(1463, 2, 'access_compose', '2025-07-05 09:11:31'),
(1464, 2, 'access_compose', '2025-07-05 09:13:18'),
(1465, 2, 'access_compose', '2025-07-05 09:13:59'),
(1466, 2, 'access_compose', '2025-07-05 09:15:18'),
(1467, 2, 'track_document', '2025-07-05 09:15:36'),
(1468, 2, 'logout', '2025-07-05 09:15:40'),
(1469, 4, 'login', '2025-07-05 09:15:44'),
(1470, 4, 'approve_document', '2025-07-05 09:17:34'),
(1471, 4, 'view_document', '2025-07-05 09:18:08'),
(1472, 4, 'track_document', '2025-07-05 09:18:13'),
(1473, 4, 'track_document', '2025-07-05 09:18:27'),
(1474, 4, 'track_document', '2025-07-05 09:18:47'),
(1475, 4, 'track_document', '2025-07-05 09:27:11'),
(1476, 4, 'track_document', '2025-07-05 09:27:19'),
(1477, 4, 'view_document', '2025-07-05 09:27:35'),
(1478, 4, 'logout', '2025-07-05 09:35:41'),
(1479, 3, 'login', '2025-07-05 09:39:20'),
(1480, 3, 'approve_document', '2025-07-05 09:39:50'),
(1481, 3, 'logout', '2025-07-05 09:40:11'),
(1482, 2, 'login', '2025-07-05 09:40:15'),
(1483, 2, 'access_compose', '2025-07-05 13:38:04'),
(1484, 2, 'access_compose', '2025-07-05 13:38:29'),
(1485, 2, 'access_compose', '2025-07-05 13:41:04'),
(1486, 2, 'logout', '2025-07-05 13:41:23'),
(1487, 4, 'login', '2025-07-05 13:41:32'),
(1488, 4, 'logout', '2025-07-05 16:50:56'),
(1489, 2, 'login', '2025-07-05 16:51:00'),
(1490, 2, 'access_compose', '2025-07-05 16:55:40'),
(1491, 2, 'logout', '2025-07-05 16:59:05'),
(1492, 4, 'login', '2025-07-05 16:59:10'),
(1493, 4, 'hold', '2025-07-05 16:59:24'),
(1494, 4, 'access_compose', '2025-07-05 20:06:20'),
(1495, 4, 'track_document', '2025-07-05 20:07:43'),
(1496, 4, 'track_document', '2025-07-05 20:07:56'),
(1497, 4, 'access_compose', '2025-07-05 20:30:07'),
(1498, 4, 'logout', '2025-07-05 20:32:02'),
(1499, 2, 'login', '2025-07-05 20:32:06'),
(1500, 2, 'access_compose', '2025-07-06 07:02:15'),
(1501, 2, 'access_compose', '2025-07-06 07:02:44'),
(1502, 2, 'track_document', '2025-07-06 08:04:37'),
(1503, 2, 'track_document', '2025-07-06 08:17:24'),
(1504, 2, 'track_document', '2025-07-06 08:28:22'),
(1505, 2, 'track_document', '2025-07-06 08:28:26'),
(1506, 2, 'access_compose', '2025-07-06 10:46:20'),
(1507, 2, 'logout', '2025-07-06 11:06:51'),
(1508, 4, 'login', '2025-07-06 11:06:57'),
(1509, 4, 'access_compose', '2025-07-06 12:00:14'),
(1510, 4, 'view_document', '2025-07-06 12:16:43'),
(1511, 4, 'access_compose', '2025-07-06 14:15:24'),
(1512, 4, 'access_compose', '2025-07-06 14:17:44'),
(1513, 4, 'logout', '2025-07-06 14:59:13'),
(1514, 2, 'login', '2025-07-06 14:59:22'),
(1515, 2, 'access_compose', '2025-07-06 15:00:13'),
(1516, 2, 'access_compose', '2025-07-06 15:09:56'),
(1517, 2, 'logout', '2025-07-06 15:14:18'),
(1518, 4, 'login', '2025-07-06 15:15:04'),
(1519, 4, 'access_compose', '2025-07-07 03:01:30'),
(1520, 4, 'access_compose', '2025-07-07 03:04:57'),
(1521, 4, 'access_compose', '2025-07-07 03:44:20');
INSERT INTO `user_logs` (`log_id`, `user_id`, `action`, `timestamp`) VALUES
(1522, 4, 'access_compose', '2025-07-07 03:53:43'),
(1523, 4, 'approve_document', '2025-07-07 03:54:34'),
(1524, 4, 'logout', '2025-07-07 03:54:46'),
(1525, 2, 'login', '2025-07-07 03:54:49'),
(1526, 2, 'logout', '2025-07-07 03:55:28'),
(1527, 4, 'login', '2025-07-07 03:55:33'),
(1528, 4, 'logout', '2025-07-07 04:04:00'),
(1529, 4, 'login', '2025-07-07 04:04:05'),
(1530, 4, 'access_compose', '2025-07-07 04:25:53'),
(1531, 4, 'logout', '2025-07-07 04:25:59'),
(1532, 2, 'login', '2025-07-07 05:11:56'),
(1533, 2, 'access_compose', '2025-07-07 05:12:09'),
(1534, 2, 'access_compose', '2025-07-07 05:12:10'),
(1535, 2, 'access_compose', '2025-07-07 05:12:11'),
(1536, 2, 'login', '2025-07-07 13:39:48'),
(1537, 2, 'access_compose', '2025-07-07 13:39:56'),
(1538, 2, 'access_edit_document', '2025-07-07 13:41:02'),
(1539, 1, 'logout', '2025-07-08 16:33:52'),
(1540, 2, 'login', '2025-07-08 16:33:56'),
(1541, 2, 'logout', '2025-07-08 16:58:58'),
(1542, 4, 'login', '2025-07-08 16:59:02'),
(1543, 4, 'hold', '2025-07-08 16:59:39'),
(1544, 4, 'view_document', '2025-07-08 17:00:00'),
(1545, 4, 'track_document', '2025-07-08 17:00:04'),
(1546, 4, 'approve_document', '2025-07-08 17:00:20'),
(1547, 4, 'track_document', '2025-07-08 17:02:01'),
(1548, 4, 'logout', '2025-07-08 17:02:09'),
(1549, 2, 'login', '2025-07-08 17:02:12'),
(1550, 2, 'track_document', '2025-07-08 17:02:16'),
(1551, 2, 'logout', '2025-07-08 17:03:21'),
(1552, 3, 'login', '2025-07-08 17:06:10'),
(1553, 3, 'approve_document', '2025-07-08 17:24:47'),
(1554, 3, 'logout', '2025-07-08 18:53:46'),
(1555, 4, 'login', '2025-07-08 18:53:50'),
(1556, 4, 'hold', '2025-07-08 18:54:01'),
(1557, 4, 'logout', '2025-07-08 18:54:36'),
(1558, 2, 'login', '2025-07-08 18:54:39'),
(1559, 2, 'track_document', '2025-07-08 18:54:44'),
(1560, 2, 'track_document', '2025-07-08 18:54:49'),
(1561, 2, 'logout', '2025-07-08 18:57:38'),
(1562, 4, 'login', '2025-07-08 18:57:42'),
(1563, 4, 'access_compose', '2025-07-08 19:31:59'),
(1564, 4, 'access_compose', '2025-07-08 19:32:28'),
(1565, 4, 'access_compose', '2025-07-08 19:34:04'),
(1566, 4, 'logout', '2025-07-08 19:34:21'),
(1567, 4, 'login', '2025-07-08 19:34:30'),
(1568, 4, 'hold', '2025-07-08 19:34:38'),
(1569, 4, 'track_document', '2025-07-08 19:34:49'),
(1570, 4, 'logout', '2025-07-08 19:34:55'),
(1571, 2, 'login', '2025-07-08 19:34:59'),
(1572, 2, 'access_compose', '2025-07-08 19:35:42'),
(1573, 2, 'access_compose', '2025-07-08 19:36:27'),
(1574, 2, 'logout', '2025-07-08 19:36:38'),
(1575, 4, 'login', '2025-07-08 19:36:42'),
(1576, 4, 'hold', '2025-07-08 19:37:09'),
(1577, 4, 'logout', '2025-07-08 19:37:34'),
(1578, 2, 'login', '2025-07-08 19:37:42'),
(1579, 2, 'track_document', '2025-07-08 19:37:47'),
(1580, 2, 'access_compose', '2025-07-08 19:53:09'),
(1581, 2, 'access_compose', '2025-07-08 19:54:12'),
(1582, 2, 'logout', '2025-07-08 19:54:20'),
(1583, 4, 'login', '2025-07-08 19:54:25'),
(1584, 4, 'hold', '2025-07-08 20:25:03'),
(1585, 4, 'logout', '2025-07-08 20:25:18'),
(1586, 2, 'login', '2025-07-08 20:25:25'),
(1587, 2, 'track_document', '2025-07-08 20:25:30'),
(1588, 2, 'logout', '2025-07-08 20:25:43'),
(1589, 3, 'login', '2025-07-08 20:25:48'),
(1590, 3, 'logout', '2025-07-08 20:25:56'),
(1591, 4, 'login', '2025-07-08 20:44:54'),
(1592, 4, 'logout', '2025-07-08 20:47:56'),
(1593, 2, 'login', '2025-07-08 20:47:59'),
(1594, 2, 'access_edit_document', '2025-07-08 20:48:05'),
(1595, 2, 'track_document', '2025-07-08 20:48:16'),
(1596, 2, 'track_document', '2025-07-08 20:51:45'),
(1597, 2, 'track_document', '2025-07-08 20:52:03'),
(1598, 2, 'logout', '2025-07-08 20:52:07'),
(1599, 4, 'login', '2025-07-08 20:52:11'),
(1600, 4, 'logout', '2025-07-08 20:52:26'),
(1601, 2, 'login', '2025-07-08 20:52:31'),
(1602, 2, 'access_compose', '2025-07-08 20:52:37'),
(1603, 2, 'access_compose', '2025-07-08 20:52:57'),
(1604, 2, 'access_compose', '2025-07-08 20:53:38'),
(1605, 2, 'logout', '2025-07-08 20:53:42'),
(1606, 4, 'login', '2025-07-08 20:53:47'),
(1607, 4, 'hold', '2025-07-08 20:54:05'),
(1608, 4, 'logout', '2025-07-08 20:59:21'),
(1609, 2, 'login', '2025-07-08 20:59:27'),
(1610, 2, 'access_compose', '2025-07-08 20:59:37'),
(1611, 2, 'access_compose', '2025-07-08 21:00:28'),
(1612, 2, 'track_document', '2025-07-08 21:01:59'),
(1613, 2, 'logout', '2025-07-08 21:02:04'),
(1614, 2, 'login', '2025-07-08 21:02:22'),
(1615, 2, 'logout', '2025-07-08 21:02:32'),
(1616, 4, 'login', '2025-07-08 21:02:36'),
(1617, 4, 'hold', '2025-07-08 21:02:45'),
(1618, 4, 'access_compose', '2025-07-08 21:09:08'),
(1619, 4, 'access_compose', '2025-07-08 21:09:11'),
(1620, 4, 'access_compose', '2025-07-08 21:09:25'),
(1621, 4, 'access_compose', '2025-07-08 21:09:52'),
(1622, 4, 'logout', '2025-07-08 21:12:20'),
(1623, 2, 'login', '2025-07-08 21:12:23'),
(1624, 2, 'access_compose', '2025-07-08 21:12:57'),
(1625, 2, 'access_compose', '2025-07-08 21:13:40'),
(1626, 2, 'track_document', '2025-07-08 21:13:45'),
(1627, 2, 'logout', '2025-07-08 21:13:48'),
(1628, 4, 'login', '2025-07-08 21:13:51'),
(1629, 4, 'hold', '2025-07-08 21:13:59'),
(1630, 1, 'logout', '2025-07-09 07:14:30'),
(1631, 2, 'login', '2025-07-09 07:14:35'),
(1632, 2, 'track_document', '2025-07-09 07:14:50'),
(1633, 2, 'logout', '2025-07-09 07:14:59'),
(1634, 4, 'login', '2025-07-09 07:15:02'),
(1635, 4, 'logout', '2025-07-09 07:15:13'),
(1636, 2, 'login', '2025-07-09 07:15:16'),
(1637, 2, 'logout', '2025-07-09 07:15:28'),
(1638, 4, 'login', '2025-07-09 07:15:37'),
(1639, 4, 'hold', '2025-07-09 07:16:19'),
(1640, 4, 'logout', '2025-07-09 07:23:17'),
(1641, 2, 'login', '2025-07-09 07:23:21'),
(1642, 2, 'access_compose', '2025-07-09 07:23:23'),
(1643, 2, 'access_compose', '2025-07-09 07:23:59'),
(1644, 2, 'logout', '2025-07-09 07:24:54'),
(1645, 4, 'login', '2025-07-09 07:25:00'),
(1646, 4, 'hold', '2025-07-09 07:25:10'),
(1647, 4, 'logout', '2025-07-09 07:29:01'),
(1648, 2, 'login', '2025-07-09 07:29:05'),
(1649, 2, 'access_compose', '2025-07-09 07:29:07'),
(1650, 2, 'access_compose', '2025-07-09 07:29:43'),
(1651, 2, 'logout', '2025-07-09 07:29:47'),
(1652, 4, 'login', '2025-07-09 07:29:51'),
(1653, 4, 'hold', '2025-07-09 07:30:03'),
(1654, 4, 'logout', '2025-07-09 19:02:28'),
(1655, 2, 'login', '2025-07-09 19:02:32'),
(1656, 2, 'access_compose', '2025-07-09 19:02:35'),
(1657, 2, 'access_compose', '2025-07-09 19:03:19'),
(1658, 2, 'track_document', '2025-07-09 19:03:36'),
(1659, 2, 'logout', '2025-07-09 19:03:49'),
(1660, 4, 'login', '2025-07-09 19:04:05'),
(1661, 4, 'hold', '2025-07-09 19:04:24'),
(1662, 4, 'logout', '2025-07-09 19:04:39'),
(1663, 2, 'login', '2025-07-09 19:04:51'),
(1664, 2, 'track_document', '2025-07-09 19:04:55'),
(1665, 2, 'access_compose', '2025-07-09 19:23:29'),
(1666, 2, 'access_compose', '2025-07-09 19:28:18'),
(1667, 2, 'access_compose', '2025-07-09 19:28:21'),
(1668, 2, 'access_compose', '2025-07-09 19:28:53'),
(1669, 2, 'track_document', '2025-07-09 19:28:57'),
(1670, 2, 'logout', '2025-07-09 19:28:59'),
(1671, 4, 'login', '2025-07-09 19:29:04'),
(1672, 4, 'hold', '2025-07-09 19:29:12'),
(1673, 2, 'login', '2025-07-09 19:48:06'),
(1674, 2, 'track_document', '2025-07-09 19:48:12'),
(1675, 2, 'logout', '2025-07-09 19:50:08'),
(1676, 4, 'login', '2025-07-09 19:50:20'),
(1677, 4, 'hold', '2025-07-09 19:50:29'),
(1678, 4, 'logout', '2025-07-09 20:01:31'),
(1679, 2, 'login', '2025-07-09 20:01:34'),
(1680, 2, 'access_compose', '2025-07-09 20:01:48'),
(1681, 2, 'access_compose', '2025-07-09 20:02:18'),
(1682, 2, 'logout', '2025-07-09 20:02:28'),
(1683, 4, 'login', '2025-07-09 20:02:37'),
(1684, 4, 'logout', '2025-07-09 20:07:28'),
(1685, 2, 'login', '2025-07-09 20:07:32'),
(1686, 2, 'access_compose', '2025-07-09 20:07:56'),
(1687, 2, 'access_compose', '2025-07-09 20:08:44'),
(1688, 2, 'access_compose', '2025-07-09 20:09:14'),
(1689, 2, 'logout', '2025-07-09 20:09:26'),
(1690, 4, 'login', '2025-07-09 20:09:30'),
(1691, 4, 'reject_document', '2025-07-09 20:09:44'),
(1692, 4, 'logout', '2025-07-09 20:10:01'),
(1693, 2, 'login', '2025-07-09 20:10:05'),
(1694, 2, 'access_compose', '2025-07-09 20:10:23'),
(1695, 2, 'access_compose', '2025-07-09 20:10:48'),
(1696, 2, 'access_compose', '2025-07-09 20:11:14'),
(1697, 2, 'logout', '2025-07-09 20:11:18'),
(1698, 4, 'login', '2025-07-09 20:11:24'),
(1699, 4, 'request_revision', '2025-07-09 20:11:39'),
(1700, 4, 'logout', '2025-07-09 20:11:45'),
(1701, 2, 'login', '2025-07-09 20:11:53'),
(1702, 2, 'track_document', '2025-07-09 20:12:05'),
(1703, 2, 'logout', '2025-07-09 20:12:40'),
(1704, 4, 'login', '2025-07-09 20:12:44'),
(1705, 4, 'logout', '2025-07-09 20:13:13'),
(1706, 2, 'login', '2025-07-09 20:13:18'),
(1707, 2, 'track_document', '2025-07-09 20:13:22'),
(1708, 2, 'logout', '2025-07-09 20:13:32'),
(1709, 3, 'login', '2025-07-09 20:13:38'),
(1710, 3, 'logout', '2025-07-09 20:14:29'),
(1711, 2, 'login', '2025-07-09 20:14:33'),
(1712, 2, 'track_document', '2025-07-09 20:14:45'),
(1713, 2, 'logout', '2025-07-09 20:17:03'),
(1714, 4, 'login', '2025-07-09 20:17:07'),
(1715, 4, 'logout', '2025-07-09 20:19:08'),
(1716, 2, 'login', '2025-07-09 20:19:12'),
(1717, 2, 'access_compose', '2025-07-09 20:19:27'),
(1718, 2, 'access_compose', '2025-07-09 20:24:17'),
(1719, 2, 'access_compose', '2025-07-10 05:39:59'),
(1720, 2, 'access_compose', '2025-07-10 05:40:50'),
(1721, 2, 'access_compose', '2025-07-10 05:44:11'),
(1722, 2, 'access_compose', '2025-07-10 05:46:51'),
(1723, 1, 'access_compose', '2025-07-10 05:54:01'),
(1724, 1, 'access_compose', '2025-07-10 05:54:35'),
(1725, 1, 'access_compose', '2025-07-10 06:00:21'),
(1726, 1, 'logout', '2025-07-10 06:04:16'),
(1727, 2, 'login', '2025-07-10 06:04:20'),
(1728, 2, 'access_compose', '2025-07-10 06:04:24'),
(1729, 2, 'access_compose', '2025-07-10 06:05:04'),
(1730, 2, 'login', '2025-07-10 06:14:42'),
(1731, 2, 'access_compose', '2025-07-10 06:14:45'),
(1732, 2, 'access_compose', '2025-07-10 06:15:15'),
(1733, 2, 'logout', '2025-07-10 06:15:41'),
(1734, 4, 'login', '2025-07-10 06:15:45'),
(1735, 4, 'logout', '2025-07-10 06:25:12'),
(1736, 2, 'login', '2025-07-10 06:25:15'),
(1737, 2, 'track_document', '2025-07-10 06:25:24'),
(1738, 2, 'track_document', '2025-07-10 06:25:33'),
(1739, 2, 'logout', '2025-07-10 06:37:35'),
(1740, 4, 'login', '2025-07-10 06:37:39'),
(1741, 1, 'logout', '2025-07-10 07:13:46'),
(1742, 3, 'login', '2025-07-10 07:13:51'),
(1743, 3, 'access_compose', '2025-07-10 07:14:40'),
(1744, 3, 'logout', '2025-07-10 07:14:47'),
(1745, 2, 'login', '2025-07-10 07:14:54'),
(1746, 2, 'access_compose', '2025-07-10 07:52:38'),
(1747, 2, 'access_compose', '2025-07-10 07:53:51'),
(1748, 2, 'access_compose', '2025-07-10 07:54:30'),
(1749, 1, 'logout', '2025-07-10 08:27:07'),
(1750, 2, 'login', '2025-07-10 08:27:10'),
(1751, 2, 'logout', '2025-07-10 08:27:33'),
(1752, 2, 'login', '2025-07-10 08:27:43'),
(1753, 2, 'login', '2025-07-10 10:01:03'),
(1754, 2, 'access_compose', '2025-07-10 10:01:19'),
(1755, 2, 'access_compose', '2025-07-10 10:01:36'),
(1756, 2, 'access_compose', '2025-07-10 10:02:12'),
(1757, 2, 'track_document', '2025-07-10 10:02:16'),
(1758, 2, 'access_compose', '2025-07-10 10:02:22'),
(1759, 2, 'access_compose', '2025-07-10 10:03:11'),
(1760, 2, 'logout', '2025-07-10 10:07:23'),
(1761, 4, 'login', '2025-07-10 10:07:26'),
(1762, 4, 'access_compose', '2025-07-10 10:24:27'),
(1763, 4, 'access_compose', '2025-07-10 10:26:34'),
(1764, 4, 'access_compose', '2025-07-10 11:03:55'),
(1765, 4, 'logout', '2025-07-10 11:04:15'),
(1766, 2, 'login', '2025-07-10 11:04:26'),
(1767, 2, 'track_document', '2025-07-10 11:04:32'),
(1768, 2, 'track_document', '2025-07-10 11:04:35'),
(1769, 2, 'logout', '2025-07-10 11:20:47'),
(1770, 4, 'login', '2025-07-10 11:21:01'),
(1771, 4, 'request_revision', '2025-07-10 11:21:19'),
(1772, 4, 'logout', '2025-07-10 11:21:25'),
(1773, 2, 'login', '2025-07-10 11:21:46'),
(1774, 2, 'track_document', '2025-07-10 11:21:54'),
(1775, 2, 'track_document', '2025-07-10 11:22:03'),
(1776, 2, 'track_document', '2025-07-10 11:22:54'),
(1777, 2, 'logout', '2025-07-10 11:23:04'),
(1778, 4, 'login', '2025-07-10 11:23:08'),
(1779, 4, 'logout', '2025-07-10 11:23:17'),
(1780, 3, 'login', '2025-07-10 11:23:26'),
(1781, 2, 'login', '2025-07-10 11:42:46'),
(1782, 2, 'track_document', '2025-07-10 11:42:50'),
(1783, 2, 'logout', '2025-07-10 11:42:57'),
(1784, 4, 'login', '2025-07-10 11:43:00'),
(1785, 4, 'request_revision', '2025-07-10 11:43:22'),
(1786, 4, 'logout', '2025-07-10 11:43:29'),
(1787, 2, 'login', '2025-07-10 11:43:36'),
(1788, 2, 'track_document', '2025-07-10 11:43:40'),
(1789, 2, 'logout', '2025-07-10 13:56:57'),
(1790, 4, 'login', '2025-07-10 13:57:03'),
(1791, 4, 'logout', '2025-07-10 13:57:09'),
(1792, 2, 'login', '2025-07-10 13:57:12'),
(1793, 2, 'track_document', '2025-07-10 13:57:16'),
(1794, 2, 'access_compose', '2025-07-10 14:01:35'),
(1795, 2, 'access_compose', '2025-07-10 14:04:19'),
(1796, 2, 'track_document', '2025-07-10 14:04:19'),
(1797, 2, 'access_compose', '2025-07-10 14:05:42'),
(1798, 2, 'access_compose', '2025-07-10 14:08:42'),
(1799, 2, 'logout', '2025-07-10 14:12:36'),
(1800, 4, 'login', '2025-07-10 14:12:39'),
(1801, 4, 'request_revision', '2025-07-10 14:14:05'),
(1802, 4, 'logout', '2025-07-10 14:14:44'),
(1803, 2, 'login', '2025-07-10 14:14:53'),
(1804, 2, 'logout', '2025-07-10 14:15:04'),
(1805, 4, 'login', '2025-07-10 14:15:15'),
(1806, 4, 'logout', '2025-07-10 14:15:21'),
(1807, 2, 'login', '2025-07-10 14:15:25'),
(1808, 2, 'track_document', '2025-07-10 14:15:31'),
(1809, 2, 'logout', '2025-07-10 14:15:38'),
(1810, 2, 'login', '2025-07-10 14:28:35'),
(1811, 2, 'track_document', '2025-07-10 14:28:39'),
(1812, 2, 'logout', '2025-07-10 14:29:45'),
(1813, 4, 'login', '2025-07-10 14:29:49'),
(1814, 4, 'logout', '2025-07-10 14:50:14'),
(1815, 2, 'login', '2025-07-10 14:52:15'),
(1816, 2, 'access_compose', '2025-07-10 14:52:18'),
(1817, 2, 'access_compose', '2025-07-10 14:52:50'),
(1818, 2, 'access_compose', '2025-07-10 14:54:03'),
(1819, 2, 'access_compose', '2025-07-10 14:54:26'),
(1820, 2, 'access_compose', '2025-07-10 14:54:33'),
(1821, 2, 'access_compose', '2025-07-10 14:55:22'),
(1822, 2, 'logout', '2025-07-10 14:55:26'),
(1823, 4, 'login', '2025-07-10 14:55:34'),
(1824, 4, 'request_revision', '2025-07-10 14:56:01'),
(1825, 4, 'logout', '2025-07-10 14:56:06'),
(1826, 2, 'login', '2025-07-10 14:56:39'),
(1827, 2, 'logout', '2025-07-10 15:34:46'),
(1828, 4, 'login', '2025-07-10 15:34:49'),
(1829, 4, 'logout', '2025-07-10 15:34:58'),
(1830, 2, 'login', '2025-07-10 15:35:01'),
(1831, 2, 'track_document', '2025-07-10 15:35:05'),
(1832, 2, 'logout', '2025-07-10 15:42:58'),
(1833, 4, 'login', '2025-07-10 15:43:01'),
(1834, 4, 'request_revision', '2025-07-10 15:50:57'),
(1835, 4, 'logout', '2025-07-10 15:51:04'),
(1836, 2, 'login', '2025-07-10 15:51:07'),
(1837, 2, 'logout', '2025-07-10 15:51:17'),
(1838, 4, 'login', '2025-07-10 15:51:21'),
(1839, 4, 'logout', '2025-07-10 15:52:06'),
(1840, 2, 'login', '2025-07-10 15:52:12'),
(1841, 2, 'logout', '2025-07-10 16:15:24'),
(1842, 4, 'login', '2025-07-10 16:15:28'),
(1843, 4, 'approve_document', '2025-07-10 16:15:50'),
(1844, 4, 'approve_document', '2025-07-10 16:22:16'),
(1845, 4, 'logout', '2025-07-10 16:22:20'),
(1846, 3, 'login', '2025-07-10 16:22:24'),
(1847, 3, 'approve_document', '2025-07-10 16:24:43'),
(1848, 3, 'approve_document', '2025-07-10 16:24:49'),
(1849, 3, 'logout', '2025-07-10 16:42:43'),
(1850, 2, 'login', '2025-07-10 16:42:46'),
(1851, 2, 'access_compose', '2025-07-10 16:42:56'),
(1852, 2, 'access_compose', '2025-07-10 16:42:58'),
(1853, 2, 'access_compose', '2025-07-10 16:45:39'),
(1854, 2, 'access_compose', '2025-07-10 17:00:46'),
(1855, 2, 'logout', '2025-07-10 17:00:50'),
(1856, 4, 'login', '2025-07-10 17:04:20'),
(1857, 4, 'approve_document', '2025-07-10 17:04:28'),
(1858, 4, 'logout', '2025-07-10 17:04:35'),
(1859, 3, 'login', '2025-07-10 17:04:39'),
(1860, 3, 'reject_document', '2025-07-10 17:04:54'),
(1861, 3, 'logout', '2025-07-10 17:05:01'),
(1862, 2, 'login', '2025-07-10 17:05:10'),
(1863, 2, 'track_document', '2025-07-10 17:09:46'),
(1864, 2, 'track_document', '2025-07-10 17:29:12'),
(1865, 2, 'access_compose', '2025-07-10 17:45:54'),
(1866, 2, 'access_compose', '2025-07-10 17:48:59'),
(1867, 2, 'access_compose', '2025-07-10 17:50:19'),
(1868, 2, 'access_compose', '2025-07-10 17:50:56'),
(1869, 2, 'access_compose', '2025-07-10 19:30:04'),
(1870, 2, 'access_compose', '2025-07-10 19:43:37'),
(1871, 2, 'access_compose', '2025-07-10 19:43:58'),
(1872, 2, 'access_compose', '2025-07-10 19:45:22'),
(1873, 2, 'track_document', '2025-07-10 19:45:41'),
(1874, 2, 'logout', '2025-07-10 19:45:46'),
(1875, 4, 'login', '2025-07-10 19:45:50'),
(1876, 4, 'access_compose', '2025-07-10 19:59:14'),
(1877, 4, 'access_compose', '2025-07-10 20:00:40'),
(1878, 4, 'logout', '2025-07-10 20:38:30'),
(1879, 2, 'login', '2025-07-10 20:38:33'),
(1880, 2, 'logout', '2025-07-10 20:53:34'),
(1881, 4, 'login', '2025-07-10 20:53:37'),
(1882, 4, 'logout', '2025-07-10 20:53:43'),
(1883, 2, 'login', '2025-07-10 20:53:50'),
(1884, 2, 'access_compose', '2025-07-10 21:03:06'),
(1885, 2, 'logout', '2025-07-10 21:16:16'),
(1886, 4, 'login', '2025-07-10 21:16:19'),
(1887, 4, 'logout', '2025-07-10 21:16:39'),
(1888, 2, 'login', '2025-07-10 21:16:43'),
(1889, 2, 'track_document', '2025-07-10 21:16:53'),
(1890, 2, 'logout', '2025-07-10 21:32:49'),
(1891, 4, 'login', '2025-07-10 21:32:53'),
(1892, 4, 'logout', '2025-07-10 21:33:12'),
(1893, 2, 'login', '2025-07-10 21:52:24'),
(1894, 2, 'logout', '2025-07-10 21:52:31'),
(1895, 4, 'login', '2025-07-10 21:52:35'),
(1896, 4, 'access_compose', '2025-07-10 22:00:40'),
(1897, 4, 'logout', '2025-07-10 22:00:44'),
(1898, 2, 'login', '2025-07-10 22:00:48'),
(1899, 2, 'access_compose', '2025-07-10 22:00:51'),
(1900, 2, 'access_compose', '2025-07-10 22:01:12'),
(1901, 2, 'access_compose', '2025-07-10 22:07:28'),
(1902, 2, 'access_compose', '2025-07-10 22:12:07'),
(1903, 2, 'access_compose', '2025-07-10 22:20:09'),
(1904, 2, 'access_compose', '2025-07-10 22:21:45'),
(1905, 2, 'access_edit_document', '2025-07-11 00:02:20'),
(1906, 2, 'login', '2025-07-11 02:30:34'),
(1907, 2, 'logout', '2025-07-11 02:30:43'),
(1908, 4, 'login', '2025-07-11 02:30:46'),
(1909, 4, 'hold', '2025-07-11 02:30:54'),
(1910, 4, 'request_revision', '2025-07-11 02:31:01'),
(1911, 4, 'logout', '2025-07-11 02:31:29'),
(1912, 2, 'login', '2025-07-11 02:31:33'),
(1913, 2, 'logout', '2025-07-11 02:35:11'),
(1914, 3, 'login', '2025-07-11 02:35:18'),
(1915, 3, 'access_compose', '2025-07-11 02:35:20'),
(1916, 3, 'access_compose', '2025-07-11 02:35:32'),
(1917, 3, 'access_compose', '2025-07-11 02:35:53'),
(1918, 3, 'logout', '2025-07-11 02:35:57'),
(1919, 4, 'login', '2025-07-11 02:36:01'),
(1920, 4, 'request_revision', '2025-07-11 02:36:10'),
(1921, 4, 'logout', '2025-07-11 02:59:24'),
(1922, 2, 'login', '2025-07-11 02:59:37'),
(1923, 2, 'login', '2025-07-28 19:02:49'),
(1924, 2, 'access_compose', '2025-07-28 20:08:56'),
(1925, 2, 'access_compose', '2025-07-28 20:08:58');

-- --------------------------------------------------------

--
-- Table structure for table `version_diffs`
--

CREATE TABLE `version_diffs` (
  `diff_id` int(11) NOT NULL,
  `version_id` int(11) NOT NULL,
  `previous_version_id` int(11) NOT NULL,
  `diff_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`diff_content`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workflow_steps`
--

CREATE TABLE `workflow_steps` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `type_id` int(11) DEFAULT NULL,
  `office_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `step_order` int(11) NOT NULL,
  `is_required` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workflow_steps`
--

INSERT INTO `workflow_steps` (`id`, `document_id`, `type_id`, `office_id`, `role_id`, `step_order`, `is_required`) VALUES
(10, 0, 2, 3, NULL, 1, 1),
(11, 0, 2, 5, NULL, 2, 1),
(12, 0, 2, 1, NULL, 3, 1),
(13, 0, 3, 5, NULL, 1, 1),
(14, 0, 3, 1, NULL, 2, 1),
(20, 0, 2, 1, NULL, 1, 1),
(26, 0, 1, 9, NULL, 2, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `collaborative_cursors`
--
ALTER TABLE `collaborative_cursors`
  ADD PRIMARY KEY (`cursor_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_collaborative_cursors_last_updated` (`last_updated`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `type_id` (`type_id`),
  ADD KEY `creator_id` (`creator_id`),
  ADD KEY `current_step` (`current_step`),
  ADD KEY `idx_documents_status` (`status`),
  ADD KEY `idx_documents_created_at` (`created_at`),
  ADD KEY `idx_documents_updated_at` (`updated_at`);

--
-- Indexes for table `document_actions`
--
ALTER TABLE `document_actions`
  ADD PRIMARY KEY (`action_id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `step_id` (`step_id`);

--
-- Indexes for table `document_attachments`
--
ALTER TABLE `document_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `document_changes`
--
ALTER TABLE `document_changes`
  ADD PRIMARY KEY (`change_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_document_changes_document_id` (`document_id`),
  ADD KEY `idx_document_changes_timestamp` (`timestamp`);

--
-- Indexes for table `document_collaborators`
--
ALTER TABLE `document_collaborators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_id` (`document_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `idx_document_collaborators_document_id` (`document_id`);

--
-- Indexes for table `document_comments`
--
ALTER TABLE `document_comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `idx_document_comments_document_id` (`document_id`);

--
-- Indexes for table `document_drafts`
--
ALTER TABLE `document_drafts`
  ADD PRIMARY KEY (`draft_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `document_edit_sessions`
--
ALTER TABLE `document_edit_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_document_edit_sessions_document_id` (`document_id`);

--
-- Indexes for table `document_hold`
--
ALTER TABLE `document_hold`
  ADD PRIMARY KEY (`hold_id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `office_id` (`office_id`);

--
-- Indexes for table `document_locks`
--
ALTER TABLE `document_locks`
  ADD PRIMARY KEY (`lock_id`),
  ADD UNIQUE KEY `document_id` (`document_id`,`section_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_document_locks_document_id` (`document_id`);

--
-- Indexes for table `document_logs`
--
ALTER TABLE `document_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_logs_action` (`action`),
  ADD KEY `idx_logs_created_at` (`created_at`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`type_id`);

--
-- Indexes for table `document_versions`
--
ALTER TABLE `document_versions`
  ADD PRIMARY KEY (`version_id`),
  ADD UNIQUE KEY `document_id` (`document_id`,`version_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_document_versions_document_id` (`document_id`),
  ADD KEY `parent_version_id` (`parent_version_id`),
  ADD KEY `idx_document_versions_created` (`created_at`);

--
-- Indexes for table `document_workflow`
--
ALTER TABLE `document_workflow`
  ADD PRIMARY KEY (`workflow_id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `office_id` (`office_id`),
  ADD KEY `idx_document_workflow_user_id` (`user_id`),
  ADD KEY `idx_workflow_status` (`status`);

--
-- Indexes for table `edit_conflicts`
--
ALTER TABLE `edit_conflicts`
  ADD PRIMARY KEY (`conflict_id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `conflicting_user_id` (`conflicting_user_id`),
  ADD KEY `resolved_by` (`resolved_by`);

--
-- Indexes for table `google_tokens`
--
ALTER TABLE `google_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`office_id`);

--
-- Indexes for table `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`reminder_id`),
  ADD KEY `idx_reminders_user` (`user_id`),
  ADD KEY `idx_reminders_date` (`reminder_date`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `role_office_mapping`
--
ALTER TABLE `role_office_mapping`
  ADD PRIMARY KEY (`mapping_id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `office_id` (`office_id`);

--
-- Indexes for table `signatures`
--
ALTER TABLE `signatures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `signature_approvals`
--
ALTER TABLE `signature_approvals`
  ADD PRIMARY KEY (`approval_id`),
  ADD KEY `signature_id` (`signature_id`),
  ADD KEY `office_id` (`office_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `simple_verifications`
--
ALTER TABLE `simple_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `office_id` (`office_id`);

--
-- Indexes for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_user_logs_timestamp` (`timestamp`),
  ADD KEY `idx_user_logs_action` (`action`);

--
-- Indexes for table `version_diffs`
--
ALTER TABLE `version_diffs`
  ADD PRIMARY KEY (`diff_id`),
  ADD KEY `version_id` (`version_id`),
  ADD KEY `previous_version_id` (`previous_version_id`);

--
-- Indexes for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `type_id` (`type_id`),
  ADD KEY `office_id` (`office_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `collaborative_cursors`
--
ALTER TABLE `collaborative_cursors`
  MODIFY `cursor_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `document_actions`
--
ALTER TABLE `document_actions`
  MODIFY `action_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_attachments`
--
ALTER TABLE `document_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_changes`
--
ALTER TABLE `document_changes`
  MODIFY `change_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_collaborators`
--
ALTER TABLE `document_collaborators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_comments`
--
ALTER TABLE `document_comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_drafts`
--
ALTER TABLE `document_drafts`
  MODIFY `draft_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `document_hold`
--
ALTER TABLE `document_hold`
  MODIFY `hold_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_locks`
--
ALTER TABLE `document_locks`
  MODIFY `lock_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_logs`
--
ALTER TABLE `document_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `document_versions`
--
ALTER TABLE `document_versions`
  MODIFY `version_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_workflow`
--
ALTER TABLE `document_workflow`
  MODIFY `workflow_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `edit_conflicts`
--
ALTER TABLE `edit_conflicts`
  MODIFY `conflict_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `google_tokens`
--
ALTER TABLE `google_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `office_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `reminder_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `role_office_mapping`
--
ALTER TABLE `role_office_mapping`
  MODIFY `mapping_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=187;

--
-- AUTO_INCREMENT for table `signature_approvals`
--
ALTER TABLE `signature_approvals`
  MODIFY `approval_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `simple_verifications`
--
ALTER TABLE `simple_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1926;

--
-- AUTO_INCREMENT for table `version_diffs`
--
ALTER TABLE `version_diffs`
  MODIFY `diff_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `collaborative_cursors`
--
ALTER TABLE `collaborative_cursors`
  ADD CONSTRAINT `collaborative_cursors_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `document_edit_sessions` (`session_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `collaborative_cursors_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `document_types` (`type_id`),
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`creator_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`current_step`) REFERENCES `workflow_steps` (`id`);

--
-- Constraints for table `document_actions`
--
ALTER TABLE `document_actions`
  ADD CONSTRAINT `document_actions_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`),
  ADD CONSTRAINT `document_actions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `document_actions_ibfk_3` FOREIGN KEY (`step_id`) REFERENCES `workflow_steps` (`id`);

--
-- Constraints for table `document_changes`
--
ALTER TABLE `document_changes`
  ADD CONSTRAINT `document_changes_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `document_edit_sessions` (`session_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_changes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `document_collaborators`
--
ALTER TABLE `document_collaborators`
  ADD CONSTRAINT `document_collaborators_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_collaborators_ibfk_2` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `document_comments`
--
ALTER TABLE `document_comments`
  ADD CONSTRAINT `document_comments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_comments_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `document_comments` (`comment_id`) ON DELETE CASCADE;

--
-- Constraints for table `document_drafts`
--
ALTER TABLE `document_drafts`
  ADD CONSTRAINT `document_drafts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `document_edit_sessions`
--
ALTER TABLE `document_edit_sessions`
  ADD CONSTRAINT `document_edit_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `document_hold`
--
ALTER TABLE `document_hold`
  ADD CONSTRAINT `fk_document_hold_document_id` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_document_hold_office_id` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_document_hold_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `document_locks`
--
ALTER TABLE `document_locks`
  ADD CONSTRAINT `document_locks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `document_versions`
--
ALTER TABLE `document_versions`
  ADD CONSTRAINT `document_versions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_versions_ibfk_2` FOREIGN KEY (`parent_version_id`) REFERENCES `document_versions` (`version_id`);

--
-- Constraints for table `document_workflow`
--
ALTER TABLE `document_workflow`
  ADD CONSTRAINT `document_workflow_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`),
  ADD CONSTRAINT `document_workflow_ibfk_2` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`),
  ADD CONSTRAINT `document_workflow_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `edit_conflicts`
--
ALTER TABLE `edit_conflicts`
  ADD CONSTRAINT `edit_conflicts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `edit_conflicts_ibfk_2` FOREIGN KEY (`conflicting_user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `edit_conflicts_ibfk_3` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE;

--
-- Constraints for table `reminders`
--
ALTER TABLE `reminders`
  ADD CONSTRAINT `reminders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `role_office_mapping`
--
ALTER TABLE `role_office_mapping`
  ADD CONSTRAINT `role_office_mapping_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  ADD CONSTRAINT `role_office_mapping_ibfk_2` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`);

--
-- Constraints for table `signatures`
--
ALTER TABLE `signatures`
  ADD CONSTRAINT `signatures_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE;

--
-- Constraints for table `signature_approvals`
--
ALTER TABLE `signature_approvals`
  ADD CONSTRAINT `signature_approvals_ibfk_1` FOREIGN KEY (`signature_id`) REFERENCES `signatures` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `signature_approvals_ibfk_2` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`),
  ADD CONSTRAINT `signature_approvals_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `simple_verifications`
--
ALTER TABLE `simple_verifications`
  ADD CONSTRAINT `simple_verifications_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`);

--
-- Constraints for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD CONSTRAINT `user_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `version_diffs`
--
ALTER TABLE `version_diffs`
  ADD CONSTRAINT `version_diffs_ibfk_1` FOREIGN KEY (`version_id`) REFERENCES `document_versions` (`version_id`),
  ADD CONSTRAINT `version_diffs_ibfk_2` FOREIGN KEY (`previous_version_id`) REFERENCES `document_versions` (`version_id`);

--
-- Constraints for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  ADD CONSTRAINT `workflow_steps_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `document_types` (`type_id`),
  ADD CONSTRAINT `workflow_steps_ibfk_2` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
