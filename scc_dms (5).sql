-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 06, 2025 at 10:39 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `has_qr_signature` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`document_id`, `title`, `type_id`, `creator_id`, `file_path`, `content_path`, `current_step`, `status`, `verification_code`, `created_at`, `google_doc_id`, `updated_at`, `has_qr_signature`) VALUES
(100, 'New Document 5/5/2025, 10:41:53 PM', 3, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 14:42:10', '1eCLdsUsmEnUo4BYz-VnSY3oE1sLaqJm_dZM2viwRAts', '2025-05-05 14:44:40', 0),
(101, 'New Document Mark', 1, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 14:46:48', '1xpFLaOZhjRwbmIxy9ojGsX9PpGwolQeqr22qJ5wweiw', '2025-05-05 18:03:03', 1),
(102, 'New Message', 3, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 14:49:26', '1XTJSkRIpsLGOHvi5lDPjxdbvSiwA5GxTaKwCgkWHNUA', '2025-05-05 15:54:17', 0),
(103, 'New Doc', 1, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 14:55:49', '1v5Nfdykem-g7Sh6Y9geIhDeT7QA60JSA9MD10LD2gGA', '2025-05-05 14:57:05', 0),
(104, 'BimBong', 3, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 15:03:54', '1eMnssKGFDw9Qc6YfFJ16JTnjPs1EB6snPhUB851rlS4', '2025-05-05 15:04:18', 0),
(105, 'Google Docs', 1, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 15:09:57', '1j0fDQ30qMDZzlP0aSqw6rSw1rgC_tcsYbqBVvUnOynE', '2025-05-05 15:54:03', 0),
(106, 'New Document 5/5/2025, 11:16:19 PM', 1, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 15:16:38', '1dCNVMgmFQjyW1TNbI4Cm69R9h7_ngGW1Ssk9yEGbm2c', '2025-05-05 15:17:49', 0),
(107, 'New Document', 1, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 15:44:24', '1I8B5zkN7rZ_OS8l9dxRavSHc7QEWbNyKymVK9j4a_2s', '2025-05-05 15:54:03', 0),
(108, 'New Document 5/5/2025, 11:56:20 PM', 1, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 15:56:45', '1aL-I7Coj0DBQhq5ghFLcuURt2Rj8za-X78X8FLJ1rMQ', '2025-05-05 16:02:53', 0),
(109, 'QR CODE DOCUMENT', 2, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 16:06:52', '1anZBrbEdVfWVuWqgY31qz_zc8D8HNlPQFvYLj8b_mgg', '2025-05-05 16:08:46', 0),
(110, 'New Document 5/6/2025, 12:13:27 AM', 3, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 16:13:44', '1GuPvcHK2Y9lFcy75be5o6xVGiGHVm8vnDJ7_qml7IfA', '2025-05-05 16:14:41', 0),
(111, 'New Document', 3, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 16:57:37', '1vFS6eZJDV8ekS4cs0XN-DrLTdkSQtBr47IRiC5W3LI0', '2025-05-05 18:45:14', 1),
(112, 'New Document', 3, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 17:43:35', '1M26meRjrX_GuBwWiBViEb_DM068u01de_KYcflF4r8c', '2025-05-05 18:31:06', 1),
(113, 'Blood Strike', 3, 2, NULL, NULL, NULL, 'approved', '785889', '2025-05-05 18:35:23', '1LlY6OCTopDQQA7Y3RtBSMqub_xqaI-i4KgBK-QWixo0', '2025-05-06 20:34:56', 1),
(114, 'AKIMBO UZI', 3, 2, NULL, NULL, NULL, 'approved', '726334', '2025-05-05 18:50:35', '1x46xna6lgtlgmhHTAZlm8HZ0UgQIFqsaZ7SfaqKEHY8', '2025-05-06 18:33:32', 0),
(115, 'New Document 5/6/2025, 3:23:21 AM', 1, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 19:23:38', '1QpKFREPEgQOPGVJ4bOf8ijR8bTKnsAdsblIlg5Cp8oE', '2025-05-05 19:55:21', 0),
(116, 'Tung Tung Sahur', 1, 2, NULL, NULL, NULL, 'approved', '464995', '2025-05-05 19:51:46', '1azS2fGa0kUdGXOFEQTp9DlFcz8rE9vSgSzpvqGvKXWs', '2025-05-06 00:18:19', 0),
(117, 'Document', 1, 2, NULL, NULL, NULL, 'approved', '397100', '2025-05-05 20:21:41', '1o9Gno5NDBSYCjMmRDz8TgPJk3wxmquwA_ZScm28FJEU', '2025-05-06 00:18:57', 0),
(118, 'Binhi', 3, 2, NULL, NULL, NULL, 'approved', '599251', '2025-05-05 20:22:03', '1JXa4oHB2Tn-YtBna2GLZeJagjD7pQeK9guT9-Vq-DiU', '2025-05-06 20:36:57', 0),
(119, 'Paraluman', 3, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-05 20:22:38', '1UlaM0FxkEpJhG8UxLQzhEUJyoUPjDB-rUQLEH2O6viA', '2025-05-05 21:21:01', 0),
(120, 'Wala Naman Akong Magagawa', 3, 2, NULL, NULL, NULL, 'approved', NULL, '2025-05-06 01:18:22', '1qRW5V3WIY9QMRIGtgOG22snQh0OJcAdDh7_8uLzEgM4', '2025-05-06 18:18:01', 0),
(121, 'Mag Isa', 1, 2, NULL, NULL, NULL, 'pending', NULL, '2025-05-06 01:18:46', '1dBE3nVaMhbktZ0yogunDv3IW6BbCWtnFGts3uPEwcO4', '2025-05-06 20:36:14', 0),
(122, 'Pawi Sa Luha', 2, 2, NULL, NULL, NULL, 'pending', NULL, '2025-05-06 01:19:13', '1UBGGQ3GFGwHrBy90G1iIgdR8aVgQhN42f8kFXPOG61A', '2025-05-06 01:19:13', 0);

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
(1, 86, 3, 'revision_requested', 'Wrong spelling and wrong names', '2025-05-01 03:05:04'),
(2, 87, 3, 'revision_requested', 'D(UGD*IWGDWGH HW) Y})Y )}U W)UD )(U)(Q) )*GH', '2025-05-01 03:10:11'),
(3, 88, 3, 'revision_requested', 'adiha[woyd[dwoay odowaig ig ig iawgdigawdawafasas', '2025-05-01 03:17:38'),
(4, 89, 3, 'revision_requested', 'adad7twa8dt waaw8t iii    aietyai  tdidit wia7iw 9awydadgagdiaw', '2025-05-01 03:27:52'),
(5, 90, 3, 'revision_requested', 'There are multiple pages that are wrong spelling and the margin is all wrong', '2025-05-01 04:21:53'),
(6, 91, 3, 'revision_requested', 'I need revision on this', '2025-05-01 04:40:06'),
(7, 92, 4, 'revision', 'This document needs revision ASAP', '2025-05-01 04:48:14'),
(8, 92, 2, 'revised', 'Document revised after requested changes.', '2025-05-01 05:49:52'),
(9, 89, 2, 'revised', 'Document revised after requested changes.', '2025-05-01 05:50:09'),
(10, 93, 3, 'revision', 'This needs revision', '2025-05-01 05:54:33'),
(11, 93, 2, 'revised', 'Document revised after requested changes. Comments: Fixed', '2025-05-01 05:54:53'),
(12, 94, 4, 'revision', 'This Document Needs Revision', '2025-05-01 06:02:45'),
(13, 94, 2, 'revised', 'Document revised after requested changes. Comments: Done', '2025-05-01 06:07:04'),
(14, 95, 4, 'revision', 'Revise It', '2025-05-01 06:12:02'),
(15, 95, 2, 'revised', 'Document revised after requested changes. Comments: I have done changes', '2025-05-01 06:12:24'),
(16, 96, 4, 'revision', 'Revise', '2025-05-01 06:17:05'),
(17, 96, 2, 'revised', 'Document revised after requested changes. Comments: Doneee', '2025-05-01 06:17:29'),
(18, 97, 4, 'revision', 'uwaaaaowefug o\'w rwaod hwoaqh oawy dodiywao ywaou hah  a ouhoawh doahwod haow haw dhawoph dd', '2025-05-01 06:21:24'),
(19, 97, 2, 'revised', 'Document revised and sent back to requesting office.', '2025-05-01 06:23:00'),
(20, 98, 4, 'revision', 'wdawdwadwa', '2025-05-01 06:31:51'),
(21, 98, 2, 'revised', 'Document revised and sent back to requesting office.', '2025-05-01 06:32:06'),
(22, 87, 2, 'hold', 'Document placed on hold: Requires meeting or further discussion before approval.', '2025-05-01 09:49:08'),
(23, 87, 2, 'hold', 'Document placed on hold: Requires meeting or further discussion before approval.', '2025-05-01 09:52:43'),
(24, 87, 2, 'hold', 'Document placed on hold: Requires meeting or further discussion before approval.', '2025-05-01 10:14:18'),
(25, 86, 2, 'hold', 'Document placed on hold: Requires meeting or further discussion before approval.', '2025-05-01 10:35:23'),
(26, 86, 2, 'resume', 'Resume', '2025-05-01 10:37:14'),
(27, 86, 2, 'resume', 'Resume', '2025-05-01 10:37:14'),
(28, 86, 2, 'hold', 'Document placed on hold: Requires meeting or further discussion before approval.', '2025-05-01 10:37:29'),
(29, 86, 2, 'resume', '', '2025-05-01 10:42:02'),
(30, 86, 2, 'resume', '', '2025-05-01 10:42:02'),
(31, 85, 2, 'hold', 'Document placed on hold: Requires meeting or further discussion before approval.', '2025-05-01 10:42:33'),
(32, 85, 2, 'resume', '', '2025-05-01 10:42:41'),
(33, 85, 2, 'resume', '', '2025-05-01 10:42:41'),
(34, 101, 4, 'qr_signed', 'QR code signature added by Finance from ', '2025-05-06 02:03:03'),
(35, 112, 3, 'qr_signed', 'QR code signature added by HR from ', '2025-05-06 02:11:41'),
(36, 111, 3, 'qr_signed', 'QR code signature added by HR from ', '2025-05-06 02:30:30'),
(37, 112, 5, 'qr_signed', 'QR code signature added by IT from ', '2025-05-06 02:31:06'),
(38, 113, 4, 'qr_signed', 'QR code signature added by Finance from ', '2025-05-06 02:35:53'),
(39, 111, 5, 'qr_signed', 'QR code signature added by IT from ', '2025-05-06 02:45:14');

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
(191, 100, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 14:42:10', '2025-05-05 14:44:40', NULL),
(192, 101, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 14:46:49', '2025-05-05 18:03:03', 'Yes'),
(193, 102, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 14:49:26', '2025-05-05 15:54:17', ''),
(194, 103, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 14:55:49', '2025-05-05 14:56:11', NULL),
(195, 103, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-05 14:55:49', '2025-05-05 14:56:35', NULL),
(196, 103, 4, NULL, 'office', 3, 'COMPLETED', '2025-05-05 14:55:49', '2025-05-05 14:57:05', NULL),
(197, 104, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 15:03:54', '2025-05-05 15:04:16', NULL),
(198, 104, 3, NULL, 'office', 2, 'CURRENT', '2025-05-05 15:03:54', NULL, NULL),
(199, 104, 4, NULL, 'office', 3, 'PENDING', '2025-05-05 15:03:54', NULL, NULL),
(200, 105, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 15:09:57', '2025-05-05 15:10:51', NULL),
(201, 105, 3, NULL, 'office', 2, 'CURRENT', '2025-05-05 15:09:57', NULL, NULL),
(202, 105, 4, NULL, 'office', 3, 'PENDING', '2025-05-05 15:09:57', NULL, NULL),
(203, 106, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 15:16:38', '2025-05-05 15:17:03', NULL),
(204, 106, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-05 15:16:38', '2025-05-05 15:17:25', NULL),
(205, 106, 4, NULL, 'office', 3, 'COMPLETED', '2025-05-05 15:16:38', '2025-05-05 15:17:49', NULL),
(206, 107, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 15:44:24', '2025-05-05 15:50:31', 'sdasdada'),
(207, 107, 3, NULL, 'office', 2, 'CURRENT', '2025-05-05 15:44:24', NULL, NULL),
(208, 107, 4, NULL, 'office', 3, 'PENDING', '2025-05-05 15:44:24', NULL, NULL),
(209, 108, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 15:56:45', '2025-05-05 15:57:14', ''),
(210, 108, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-05 15:56:45', '2025-05-05 16:01:02', ''),
(211, 108, 4, NULL, 'office', 3, 'COMPLETED', '2025-05-05 15:56:45', '2025-05-05 16:02:53', 'ddadaa'),
(212, 109, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 16:06:52', '2025-05-05 16:07:30', 'Yes'),
(213, 109, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-05 16:06:52', '2025-05-05 16:08:36', ''),
(214, 109, 4, NULL, 'office', 3, 'COMPLETED', '2025-05-05 16:06:52', '2025-05-05 19:13:13', 'Approvers'),
(215, 110, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 16:13:44', '2025-05-05 16:14:29', 'Yes i have approved'),
(216, 110, 3, NULL, 'office', 2, 'CURRENT', '2025-05-05 16:13:44', NULL, NULL),
(217, 110, 4, NULL, 'office', 3, 'PENDING', '2025-05-05 16:13:44', NULL, NULL),
(218, 111, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 16:57:37', '2025-05-05 17:05:37', 'I have approved'),
(219, 111, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-05 16:57:37', '2025-05-05 18:30:30', 'Yess'),
(220, 111, 4, NULL, 'office', 3, 'COMPLETED', '2025-05-05 16:57:37', '2025-05-05 18:45:14', 'awddwadwad'),
(221, 112, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 17:43:35', '2025-05-05 17:43:51', 'adaawdwa'),
(222, 112, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-05 17:43:35', '2025-05-05 18:11:41', 'aedqweaweda'),
(223, 112, 4, NULL, 'office', 3, 'COMPLETED', '2025-05-05 17:43:35', '2025-05-05 18:31:06', ''),
(224, 113, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 18:35:23', '2025-05-05 18:35:53', 'Approved\n'),
(225, 113, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-05 18:35:23', '2025-05-06 20:34:23', 'I have approved of this'),
(226, 114, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 18:50:35', '2025-05-05 19:11:12', 'Approved\\r\\n'),
(227, 114, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-05 18:50:35', '2025-05-06 18:30:54', 'Approved'),
(228, 114, 4, NULL, 'office', 3, 'CURRENT', '2025-05-05 18:50:35', NULL, NULL),
(229, 115, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 19:23:38', '2025-05-05 19:36:08', 'AApproved'),
(230, 115, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-05 19:23:38', '2025-05-05 19:42:35', 'AApproved'),
(231, 115, 4, NULL, 'office', 3, 'COMPLETED', '2025-05-05 19:23:38', '2025-05-05 19:55:21', 'Approved'),
(232, 116, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 19:51:46', '2025-05-05 19:52:05', 'AApproved'),
(233, 116, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-05 19:51:46', '2025-05-05 21:29:15', 'erwererse'),
(234, 116, 4, NULL, 'office', 3, 'COMPLETED', '2025-05-05 19:51:46', '2025-05-05 21:30:03', 'Approved'),
(235, 117, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 20:21:41', '2025-05-05 20:23:30', 'Kayat dahan na'),
(236, 117, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-05 20:21:41', '2025-05-05 21:28:38', 'Approved'),
(237, 117, 4, NULL, 'office', 3, 'COMPLETED', '2025-05-05 20:21:41', '2025-05-06 00:19:35', 'Approved'),
(238, 118, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 20:22:03', '2025-05-05 20:41:24', 'AAApproved'),
(239, 118, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-05 20:22:03', '2025-05-05 20:50:04', 'Approved'),
(240, 118, 4, NULL, 'office', 3, 'COMPLETED', '2025-05-05 20:22:03', '2025-05-05 21:24:34', ''),
(241, 119, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-05 20:22:38', '2025-05-05 20:28:52', 'Approved'),
(242, 119, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-05 20:22:38', '2025-05-05 20:48:10', 'App'),
(243, 119, 4, NULL, 'office', 3, 'COMPLETED', '2025-05-05 20:22:38', '2025-05-05 21:21:01', 'adwd'),
(244, 120, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-06 01:18:22', '2025-05-06 01:19:48', 'Wag naman sana'),
(245, 120, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-06 01:18:22', '2025-05-06 01:28:53', 'Approved'),
(246, 120, 4, NULL, 'office', 3, 'CURRENT', '2025-05-06 01:18:22', NULL, NULL),
(247, 121, 5, NULL, 'office', 1, 'COMPLETED', '2025-05-06 01:18:46', '2025-05-06 01:23:13', 'Approved'),
(248, 121, 3, NULL, 'office', 2, 'COMPLETED', '2025-05-06 01:18:46', '2025-05-06 01:29:24', 'Approved'),
(249, 121, 4, NULL, 'office', 3, 'CURRENT', '2025-05-06 01:18:46', NULL, NULL),
(250, 122, 5, NULL, 'office', 1, 'CURRENT', '2025-05-06 01:19:13', NULL, NULL),
(251, 122, 3, NULL, 'office', 2, 'PENDING', '2025-05-06 01:19:13', NULL, NULL),
(252, 122, 4, NULL, 'office', 3, 'PENDING', '2025-05-06 01:19:13', NULL, NULL);

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
(1, 2, '', '', NULL, '2025-05-06 20:38:24'),
(2, 4, '', '', NULL, '2025-05-05 19:41:58');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `document_id`, `message`, `is_read`, `created_at`) VALUES
(65, 4, 100, 'New document \"New Document 5/5/2025, 10:41:53 PM\" requires your attention', 0, '2025-05-05 14:42:10'),
(66, 4, 101, 'New document \"New Document Mark\" requires your attention', 0, '2025-05-05 14:46:49'),
(67, 4, 102, 'New document \"New Message\" requires your attention', 0, '2025-05-05 14:49:26'),
(68, 4, 103, 'New document \"New Doc\" requires your attention', 0, '2025-05-05 14:55:49'),
(69, 4, 104, 'New document \"BimBong\" requires your attention', 0, '2025-05-05 15:03:54'),
(70, 4, 105, 'New document \"Google Docs\" requires your attention', 0, '2025-05-05 15:09:57'),
(71, 4, 106, 'New document \"New Document 5/5/2025, 11:16:19 PM\" requires your attention', 0, '2025-05-05 15:16:38'),
(72, 4, 107, 'New document \"New Document\" requires your attention', 0, '2025-05-05 15:44:24'),
(73, 4, 108, 'New document \"New Document 5/5/2025, 11:56:20 PM\" requires your attention', 0, '2025-05-05 15:56:45'),
(74, 4, 109, 'New document \"QR CODE DOCUMENT\" requires your attention', 0, '2025-05-05 16:06:52'),
(75, 4, 110, 'New document \"New Document 5/6/2025, 12:13:27 AM\" requires your attention', 0, '2025-05-05 16:13:44'),
(76, 4, 111, 'New document \"New Document\" requires your attention', 0, '2025-05-05 16:57:37'),
(77, 4, 112, 'New document \"New Document\" requires your attention', 0, '2025-05-05 17:43:35'),
(78, 4, 113, 'New document \"Blood Strike\" requires your attention', 0, '2025-05-05 18:35:23'),
(79, 4, 114, 'New document \"AKIMBO UZI\" requires your attention', 0, '2025-05-05 18:50:35'),
(80, 4, 115, 'New document \"New Document 5/6/2025, 3:23:21 AM\" requires your attention', 0, '2025-05-05 19:23:38'),
(81, 4, 116, 'New document \"Tung Tung Sahur\" requires your attention', 0, '2025-05-05 19:51:46'),
(82, 4, 117, 'New document \"Document\" requires your attention', 0, '2025-05-05 20:21:41'),
(83, 4, 118, 'New document \"Binhi\" requires your attention', 0, '2025-05-05 20:22:03'),
(84, 4, 119, 'New document \"Paraluman\" requires your attention', 0, '2025-05-05 20:22:38'),
(85, 4, 120, 'New document \"Wala Naman Akong Magagawa\" requires your attention', 0, '2025-05-06 01:18:22'),
(86, 4, 121, 'New document \"Mag Isa\" requires your attention', 0, '2025-05-06 01:18:46'),
(87, 4, 122, 'New document \"Pawi Sa Luha\" requires your attention', 0, '2025-05-06 01:19:13');

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
-- Table structure for table `offices_backup`
--

CREATE TABLE `offices_backup` (
  `office_id` int(11) NOT NULL DEFAULT 0,
  `office_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offices_backup`
--

INSERT INTO `offices_backup` (`office_id`, `office_name`) VALUES
(1, 'President Office'),
(2, 'Admin Office'),
(3, 'HR Department'),
(4, 'IT Department'),
(5, 'Finance Department');

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
(4, 2, 'This is an example', 'Yes yes this is an reminder description', '2025-05-13', 1, '2025-05-01 21:29:41', '2025-05-01 21:34:17'),
(5, 2, 'This is an example', 'Yes yes this is an reminder description', '2025-05-13', 1, '2025-05-01 21:29:42', '2025-05-01 21:34:18'),
(6, 2, 'This is an example', 'Yes yes this is an reminder description', '2025-05-13', 1, '2025-05-01 21:29:43', '2025-05-01 21:34:27'),
(7, 2, 'This is an example', 'Yes yes this is an reminder description', '2025-05-13', 1, '2025-05-01 21:29:43', '2025-05-01 21:34:28'),
(8, 2, 'This is an example', 'Yes yes this is an reminder description', '2025-05-13', 1, '2025-05-01 21:29:43', '2025-05-01 21:34:21'),
(9, 2, 'This is an example', 'Yes yes this is an reminder description', '2025-05-13', 1, '2025-05-01 21:29:43', '2025-05-01 21:33:20'),
(10, 2, 'This is an example', 'Yes yes this is an reminder description', '2025-05-13', 1, '2025-05-01 21:29:43', '2025-05-01 21:34:19'),
(11, 2, 'This is an example', 'Yes yes this is an reminder description', '2025-05-13', 1, '2025-05-01 21:29:43', '2025-05-01 21:34:19'),
(12, 2, 'This is an example', 'Yes yes this is an reminder description', '2025-05-13', 1, '2025-05-01 21:29:44', '2025-05-01 21:34:15'),
(13, 2, 'This is an example', 'Yes yes this is an reminder description', '2025-05-13', 1, '2025-05-01 21:29:44', '2025-05-01 21:34:24'),
(14, 2, 'This is an example', 'Yes yes this is an reminder description', '2025-05-13', 1, '2025-05-01 21:29:44', '2025-05-01 21:34:25'),
(15, 2, 'This is an example', 'Yes yes this is an reminder description', '2025-05-13', 1, '2025-05-01 21:29:44', '2025-05-01 21:34:27');

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
-- Table structure for table `roles_backup`
--

CREATE TABLE `roles_backup` (
  `role_id` int(11) NOT NULL DEFAULT 0,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles_backup`
--

INSERT INTO `roles_backup` (`role_id`, `role_name`) VALUES
(1, 'President'),
(2, 'Admin'),
(3, 'User');

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

--
-- Dumping data for table `signatures`
--

INSERT INTO `signatures` (`id`, `document_id`, `user_id`, `office_id`, `created_at`, `expires_at`, `verification_hash`, `is_revoked`) VALUES
('sig_6818fd579254f5.53792921', 101, 4, '5', '2025-05-06 02:03:03', '2026-05-05 20:03:03', 'c91ed76b5853c474a90a9da5df918a67769d708c3b8fa248742517ba83251fe0', 0),
('sig_6818ff5dce1689.95513917', 112, 3, '3', '2025-05-06 02:11:41', '2026-05-05 20:11:41', '75eb7ade0205edad59e7eefc44c5972e329f4a97b6e3f1f9a8b6e26bfea57f39', 0),
('sig_681903c60c0da1.77520171', 111, 3, '3', '2025-05-06 02:30:30', '2026-05-06 02:30:30', '046886', 0),
('sig_681903ea610572.31906813', 112, 5, '4', '2025-05-06 02:31:06', '2026-05-06 02:31:06', '617424', 0),
('sig_681905097aebf4.89048109', 113, 4, '5', '2025-05-06 02:35:53', '2026-05-06 02:35:53', '539086', 0),
('sig_6819073aba2c03.78631618', 111, 5, '4', '2025-05-06 02:45:14', '2026-05-06 02:45:14', '345602', 0);

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
(1, 114, 4, '5', '061197', '2025-05-06 03:11:12'),
(2, 109, 5, '4', '155718', '2025-05-06 03:13:13'),
(3, 115, 4, '5', '794988', '2025-05-06 03:36:08'),
(4, 115, 3, '3', '771622', '2025-05-06 03:42:35'),
(5, 116, 4, '5', '484051', '2025-05-06 03:52:05'),
(6, 115, 5, '4', '861609', '2025-05-06 03:55:21'),
(7, 117, 4, '5', '192038', '2025-05-06 04:23:30'),
(8, 119, 4, '5', '165735', '2025-05-06 04:28:52'),
(9, 118, 4, '5', '506399', '2025-05-06 04:41:24'),
(10, 119, 3, '3', '385815', '2025-05-06 04:48:10'),
(11, 118, 3, '3', '925814', '2025-05-06 04:50:04'),
(12, 119, 5, '4', '493821', '2025-05-06 05:21:01'),
(13, 118, 5, '4', '184308', '2025-05-06 05:24:34'),
(14, 117, 3, '3', '698209', '2025-05-06 05:28:38'),
(15, 116, 3, '3', '122436', '2025-05-06 05:29:15'),
(16, 116, 5, '4', '376190', '2025-05-06 05:30:03'),
(17, 117, 5, '4', '464334', '2025-05-06 08:19:35'),
(18, 120, 4, '5', '036488', '2025-05-06 09:19:48'),
(19, 121, 4, '5', '350551', '2025-05-06 09:23:13'),
(20, 120, 3, '3', '954406', '2025-05-06 09:28:53'),
(21, 121, 3, '3', '758136', '2025-05-06 09:29:24'),
(22, 114, 3, '3', '987241', '2025-05-07 02:30:54'),
(23, 113, 3, '3', '362061', '2025-05-07 04:34:23');

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
(1380, 2, 'access_compose', '2025-05-06 20:38:23');

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
(15, 0, 1, 3, NULL, 1, 1),
(16, 0, 1, 5, NULL, 2, 1),
(17, 0, 1, 1, NULL, 3, 1),
(18, 0, 1, 3, NULL, 1, 1),
(19, 0, 1, 1, NULL, 2, 1),
(20, 0, 2, 1, NULL, 1, 1),
(21, 0, 3, 1, NULL, 1, 1),
(22, 0, 1, 3, NULL, 1, 1),
(23, 0, 1, 5, NULL, 2, 1),
(24, 0, 1, 1, NULL, 3, 1),
(25, 0, 3, 3, NULL, 1, 1),
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
  ADD KEY `current_step` (`current_step`);

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
  ADD KEY `user_id` (`user_id`);

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
  ADD KEY `idx_document_workflow_user_id` (`user_id`);

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
  ADD KEY `user_id` (`user_id`);

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
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT for table `document_actions`
--
ALTER TABLE `document_actions`
  MODIFY `action_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

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
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

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
  MODIFY `workflow_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=253;

--
-- AUTO_INCREMENT for table `edit_conflicts`
--
ALTER TABLE `edit_conflicts`
  MODIFY `conflict_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `google_tokens`
--
ALTER TABLE `google_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `office_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `reminder_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1381;

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
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`);

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
