-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 13, 2026 at 04:26 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `beauty_pageant_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audience_votes`
--

CREATE TABLE `audience_votes` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `contestant_id` int(11) NOT NULL,
  `voted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audience_votes`
--

INSERT INTO `audience_votes` (`id`, `ticket_id`, `contestant_id`, `voted_at`) VALUES
(1, 2, 1, '2026-01-13 10:20:54');

--
-- Triggers `audience_votes`
--
DELIMITER $$
CREATE TRIGGER `prevent_double_voting` BEFORE INSERT ON `audience_votes` FOR EACH ROW BEGIN
    DECLARE current_status VARCHAR(10);
    
    -- Check current status of the ticket
    SELECT `status` INTO current_status FROM `tickets` WHERE `id` = NEW.ticket_id;
    
    -- If already Used, STOP the vote
    IF current_status = 'Used' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'DENIED: This ticket has already been used.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `process_audience_vote` AFTER INSERT ON `audience_votes` FOR EACH ROW BEGIN
    -- 1. Update the Ticket Status to 'Used'
    UPDATE `tickets` 
    SET `status` = 'Used', `used_at` = NOW() 
    WHERE `id` = NEW.ticket_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `awards`
--

CREATE TABLE `awards` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `category_type` enum('Major','Minor') NOT NULL DEFAULT 'Minor',
  `selection_method` enum('Manual','Audience_Vote','Highest_Segment','Highest_Round') NOT NULL DEFAULT 'Manual',
  `linked_round_id` int(11) DEFAULT NULL,
  `linked_segment_id` int(11) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `awards`
--

INSERT INTO `awards` (`id`, `event_id`, `title`, `description`, `category_type`, `selection_method`, `linked_round_id`, `linked_segment_id`, `status`, `is_deleted`, `created_at`) VALUES
(1, 1, 'Miss Photogenic', 'Awarded to the contestant who photographs best on camera.', 'Minor', 'Manual', NULL, NULL, 'Active', 0, '2026-01-13 06:13:13'),
(2, 1, 'Miss Congeniality', 'Awarded to the contestant with the best attitude and camaraderie.', 'Minor', 'Manual', NULL, NULL, 'Active', 0, '2026-01-13 06:14:20'),
(3, 1, 'Miss People\'s Choice', 'Awarded to the contestant with the highest public vote', 'Minor', 'Audience_Vote', NULL, NULL, 'Active', 0, '2026-01-13 06:14:59'),
(4, 1, 'Best in Production Number', 'Awarded to the top scorer in the Production Number segment.', 'Minor', 'Highest_Segment', NULL, 1, 'Active', 0, '2026-01-13 06:15:56'),
(5, 1, 'Best in Preliminary Round', 'Awarded to the contestant with the highest preliminary score.', 'Minor', 'Highest_Round', 1, NULL, 'Active', 0, '2026-01-13 06:18:18'),
(6, 1, 'Best in Semi-Final Round', 'Awarded to the contestant with the highest semi-final score.', 'Minor', 'Highest_Round', 2, NULL, 'Active', 0, '2026-01-13 12:33:24');

-- --------------------------------------------------------

--
-- Table structure for table `award_winners`
--

CREATE TABLE `award_winners` (
  `id` int(11) NOT NULL,
  `award_id` int(11) NOT NULL,
  `contestant_id` int(11) NOT NULL,
  `title_label` varchar(50) DEFAULT 'Winner',
  `winning_score` decimal(10,2) DEFAULT NULL,
  `awarded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `award_winners`
--

INSERT INTO `award_winners` (`id`, `award_id`, `contestant_id`, `title_label`, `winning_score`, `awarded_at`) VALUES
(1, 1, 6, 'Winner', NULL, '2026-01-13 10:11:44'),
(2, 2, 8, 'Winner', NULL, '2026-01-13 10:12:36');

-- --------------------------------------------------------

--
-- Table structure for table `criteria`
--

CREATE TABLE `criteria` (
  `id` int(11) NOT NULL,
  `segment_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `max_score` decimal(5,2) NOT NULL DEFAULT 100.00,
  `ordering` int(11) NOT NULL DEFAULT 1,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `criteria`
--

INSERT INTO `criteria` (`id`, `segment_id`, `title`, `description`, `max_score`, `ordering`, `is_deleted`, `created_at`) VALUES
(1, 1, 'Timing', 'Accuracy in following musical cues', 30.00, 1, 0, '2026-01-13 02:54:48'),
(2, 1, 'Energy', 'Enthusiasm and performance intensity', 40.00, 2, 0, '2026-01-13 02:55:48'),
(3, 1, 'Coordination', 'Precision and harmony of movements', 30.00, 3, 0, '2026-01-13 03:10:03'),
(4, 4, 'Confidence', 'Composure and self-assurance on stage', 40.00, 1, 0, '2026-01-13 03:17:08'),
(5, 4, 'Physique', 'Body proportion and posture', 30.00, 2, 0, '2026-01-13 03:17:31'),
(6, 4, 'Stage Presence', 'Command and engagement on stage', 30.00, 3, 0, '2026-01-13 03:18:00'),
(7, 5, 'Poise', 'Graceful movement and posture', 40.00, 1, 0, '2026-01-13 03:18:46'),
(8, 5, 'Elegance', 'Overall refinement and style', 35.00, 2, 0, '2026-01-13 03:19:15'),
(9, 5, 'Walk', 'Smoothness and control of runway walk', 25.00, 3, 0, '2026-01-13 03:19:57'),
(10, 6, 'Poise', 'Grace and confidence on stage', 30.00, 1, 0, '2026-01-13 03:24:02'),
(11, 6, 'Elegance', 'Overall polish and refinement', 30.00, 2, 0, '2026-01-13 03:24:45'),
(12, 6, 'Confidence', 'Strong stage presence', 40.00, 3, 0, '2026-01-13 03:25:16'),
(13, 7, 'Content', 'Substance and relevance of response', 50.00, 1, 0, '2026-01-13 03:25:47'),
(14, 7, 'Delivery', 'Clarity and composure in speaking', 25.00, 2, 0, '2026-01-13 03:26:12'),
(15, 7, 'Intelligence', 'Logic and insight of the answer', 25.00, 3, 0, '2026-01-13 03:26:41');

--
-- Triggers `criteria`
--
DELIMITER $$
CREATE TRIGGER `guard_criteria_deletion` BEFORE UPDATE ON `criteria` FOR EACH ROW BEGIN
    DECLARE round_status VARCHAR(20);
    
    IF NEW.is_deleted = 1 THEN
        -- We need to join tables to find the Round status
        SELECT r.status INTO round_status 
        FROM rounds r 
        JOIN segments s ON s.round_id = r.id 
        WHERE s.id = OLD.segment_id;
        
        IF round_status != 'Pending' THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'DENIED: Cannot delete Criteria. The Round has already started.';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_queue`
--

INSERT INTO `email_queue` (`id`, `recipient_email`, `subject`, `body`, `status`, `attempts`, `created_at`, `sent_at`) VALUES
(1, 'phoebe@gmail.com', 'Official Contestant Registration', '<h2>Welcome, Pheobe Buffay!</h2><p>You have been registered for <b>Mutya San Old Rizal</b> as Contestant #2.</p><div style=\'background:#f3f4f6; padding:15px;\'><strong>Credentials:</strong><br>Email: phoebe@gmail.com<br>Password: phoebe</div><p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\'>Login Now</a></p>', 'sent', 1, '2026-01-12 17:09:09', '2026-01-12 17:09:15'),
(2, 'monica@gmail.com', 'Official Contestant Registration', '<h2>Welcome, Monica Geller!</h2><p>You have been registered for <b>Mutya San Old Rizal</b> as Contestant #3.</p><div style=\'background:#f3f4f6; padding:15px;\'><strong>Credentials:</strong><br>Email: monica@gmail.com<br>Password: monica</div><p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\'>Login Now</a></p>', 'sent', 1, '2026-01-12 17:22:43', '2026-01-12 17:22:49'),
(3, 'michele@gmail.com', 'Official Contestant Registration', '<h2>Welcome, Michelle Anne Dela Cruz!</h2><p>You have been registered for <b>Mutya San Old Rizal</b> as Contestant #4.</p><div style=\'background:#f3f4f6; padding:15px;\'><strong>Credentials:</strong><br>Email: michele@gmail.com<br>Password: michele</div><p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\'>Login Now</a></p>', 'sent', 1, '2026-01-12 17:24:26', '2026-01-12 17:24:31'),
(4, 'trisha@gmail.com', 'Official Contestant Registration', '<h2>Welcome, Trisha Mae Gonzales!</h2><p>You have been registered for <b>Mutya San Old Rizal</b> as Contestant #5.</p><div style=\'background:#f3f4f6; padding:15px;\'><strong>Credentials:</strong><br>Email: trisha@gmail.com<br>Password: trisha</div><p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\'>Login Now</a></p>', 'sent', 1, '2026-01-12 17:26:33', '2026-01-12 17:26:38'),
(5, 'alyssa@gmail.com', 'Official Contestant Registration', '<h2>Welcome, Alyssa Montoya!</h2><p>You have been registered for <b>Mutya San Old Rizal</b> as Contestant #6.</p><div style=\'background:#f3f4f6; padding:15px;\'><strong>Credentials:</strong><br>Email: alyssa@gmail.com<br>Password: alyssa</div><p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\'>Login Now</a></p>', 'sent', 1, '2026-01-12 17:28:02', '2026-01-12 17:28:06'),
(6, 'francine@gmail.com', 'Official Contestant Registration', '<h2>Welcome, Francine Mae Navarro!</h2><p>You have been registered for <b>Mutya San Old Rizal</b> as Contestant #7.</p><div style=\'background:#f3f4f6; padding:15px;\'><strong>Credentials:</strong><br>Email: francine@gmail.com<br>Password: francine</div><p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\'>Login Now</a></p>', 'sent', 1, '2026-01-12 17:29:50', '2026-01-12 17:29:54'),
(7, 'fourthemail936@gmail.com', 'Official Contestant Registration', '<h2>Welcome, Nicole Patrice Uy!</h2><p>You have been registered for <b>Mutya San Old Rizal</b> as Contestant #8.</p><div style=\'background:#f3f4f6; padding:15px;\'><strong>Credentials:</strong><br>Email: fourthemail936@gmail.com<br>Password: nicole</div><p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\'>Login Now</a></p>', 'sent', 1, '2026-01-12 17:33:47', '2026-01-12 17:33:53');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `manager_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `venue` varchar(255) NOT NULL,
  `status` enum('Inactive','Active','Completed') DEFAULT 'Inactive',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `manager_id`, `title`, `event_date`, `venue`, `status`, `is_deleted`, `created_at`) VALUES
(1, 1, 'Mutya San Old Rizal', '2026-06-12', 'Brgy. Old Rizal, Covered Court', 'Active', 0, '2026-01-11 20:31:22'),
(2, 1, 'UEP Beauty Pageant', '2026-06-20', 'University Town, Gymnatorium', 'Inactive', 0, '2026-01-13 03:22:36');

-- --------------------------------------------------------

--
-- Table structure for table `event_activities`
--

CREATE TABLE `event_activities` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `venue` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `activity_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_activities`
--

INSERT INTO `event_activities` (`id`, `event_id`, `title`, `venue`, `description`, `activity_date`, `start_time`, `end_time`, `status`, `is_deleted`, `created_at`) VALUES
(1, 1, 'Contestant Registration and Screening', 'Old Rizal, Covered Court', 'Registration, document verification, and number assignment', '2026-04-15', '08:00:00', '10:00:00', 'Active', 0, '2026-01-13 03:32:56'),
(2, 1, 'Official Photoshoot', 'Catarman Town Plaza', 'Official promotional photos of contestants', '2026-04-20', '08:00:00', '11:00:00', 'Active', 0, '2026-01-13 03:46:16'),
(3, 1, 'Advocacy Training and Workshop', 'Catarman Convention Center', 'Workshop on advocacy presentation and public speaking', '2026-04-25', '13:00:00', '15:00:00', 'Active', 0, '2026-01-13 03:49:44'),
(4, 1, 'Runway Walk Coaching', 'Old Rizal, Covered Court', 'Coaching session for posture, walk, and turns', '2026-04-28', '09:00:00', '11:00:00', 'Active', 0, '2026-01-13 03:51:20');

--
-- Triggers `event_activities`
--
DELIMITER $$
CREATE TRIGGER `check_activity_time_logic` BEFORE INSERT ON `event_activities` FOR EACH ROW BEGIN
    -- Logic: Start Time must be BEFORE End Time
    IF NEW.start_time >= NEW.end_time THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Logic Error: Activity End Time cannot be earlier than or equal to Start Time.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `check_activity_time_logic_update` BEFORE UPDATE ON `event_activities` FOR EACH ROW BEGIN
    -- Logic: If the user changes the times, we must check again
    -- Start Time must be BEFORE End Time
    IF NEW.start_time >= NEW.end_time THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Logic Error: Activity End Time cannot be earlier than or equal to Start Time.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `event_contestants`
--

CREATE TABLE `event_contestants` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `contestant_number` int(11) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `hometown` varchar(100) DEFAULT NULL,
  `motto` text DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `bust` decimal(4,1) DEFAULT NULL,
  `waist` decimal(4,1) DEFAULT NULL,
  `hips` decimal(4,1) DEFAULT NULL,
  `photo` varchar(255) DEFAULT 'default_contestant.png',
  `status` enum('Pending','Active','Qualified','Eliminated','Inactive','Rejected') DEFAULT 'Pending',
  `is_deleted` tinyint(1) DEFAULT 0,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_contestants`
--

INSERT INTO `event_contestants` (`id`, `event_id`, `user_id`, `contestant_number`, `age`, `hometown`, `motto`, `height`, `bust`, `waist`, `hips`, `photo`, `status`, `is_deleted`, `registered_at`) VALUES
(1, 1, 10, 1, 21, 'Old Rizal', 'Confidence is Beauty that Speaks', 170.00, 34.0, 25.0, 36.0, 'contestant_1768235296.png', 'Eliminated', 0, '2026-01-12 16:28:16'),
(2, 1, 11, 2, 22, 'Old Rizal', 'Grace under pressure defines true strength', 168.00, 33.0, 26.0, 35.0, 'contestant_1768237749.png', 'Qualified', 0, '2026-01-12 17:09:09'),
(3, 1, 12, 3, 22, 'Old Rizal', 'Purpose gives Beauty its Power', 150.00, 34.0, 24.0, 34.0, 'contestant_1768238563.jpg', 'Qualified', 0, '2026-01-12 17:22:43'),
(4, 1, 13, 4, 20, 'Old Rizal', 'Elegance is a choice I make everyday', 157.00, 35.0, 25.0, 35.0, 'contestant_1768238666.jpg', 'Eliminated', 0, '2026-01-12 17:24:26'),
(5, 1, 14, 5, 23, 'Old Rizal', 'Growth begins when courage replaces fear', 169.00, 34.0, 26.0, 36.0, 'contestant_1768238793.jpg', 'Eliminated', 0, '2026-01-12 17:26:33'),
(6, 1, 15, 6, 19, 'Old Rizal', 'Strength is showing up as yourself', 166.00, 32.0, 24.0, 35.0, 'contestant_1768238882.jpg', 'Qualified', 0, '2026-01-12 17:28:02'),
(7, 1, 16, 7, 21, 'Old Rizal', 'Authenticity is my Greatest advantage', 168.00, 33.0, 25.0, 36.0, 'contestant_1768238990.jpg', 'Qualified', 0, '2026-01-12 17:29:50'),
(8, 1, 18, 8, 22, 'Old Rizal', 'Grace is power expressed softly', 171.00, 34.0, 26.0, 37.0, 'contestant_1768239227.jpg', 'Qualified', 0, '2026-01-12 17:33:47');

-- --------------------------------------------------------

--
-- Table structure for table `event_judges`
--

CREATE TABLE `event_judges` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `judge_id` int(11) NOT NULL,
  `is_chairman` tinyint(1) DEFAULT 0,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `is_deleted` tinyint(1) DEFAULT 0,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_judges`
--

INSERT INTO `event_judges` (`id`, `event_id`, `judge_id`, `is_chairman`, `status`, `is_deleted`, `assigned_at`) VALUES
(1, 1, 5, 1, 'Active', 0, '2026-01-12 02:42:24'),
(2, 1, 6, 0, 'Active', 0, '2026-01-12 03:06:06'),
(3, 1, 7, 0, 'Active', 0, '2026-01-12 03:15:59'),
(4, 1, 8, 0, 'Active', 0, '2026-01-12 03:25:10'),
(5, 1, 9, 0, 'Active', 0, '2026-01-12 05:47:27');

-- --------------------------------------------------------

--
-- Table structure for table `event_teams`
--

CREATE TABLE `event_teams` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('Tabulator','Judge Coordinator','Contestant Manager') NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `is_deleted` tinyint(1) DEFAULT 0,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_teams`
--

INSERT INTO `event_teams` (`id`, `event_id`, `user_id`, `role`, `status`, `is_deleted`, `assigned_at`) VALUES
(1, 1, 2, 'Judge Coordinator', 'Active', 0, '2026-01-12 02:23:05'),
(2, 1, 3, 'Contestant Manager', 'Active', 0, '2026-01-12 02:39:20'),
(3, 1, 4, 'Tabulator', 'Active', 0, '2026-01-12 02:40:38');

-- --------------------------------------------------------

--
-- Table structure for table `judge_comments`
--

CREATE TABLE `judge_comments` (
  `id` int(11) NOT NULL,
  `segment_id` int(11) NOT NULL,
  `judge_id` int(11) NOT NULL,
  `contestant_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `judge_comments`
--

INSERT INTO `judge_comments` (`id`, `segment_id`, `judge_id`, `contestant_id`, `comment`, `created_at`, `updated_at`) VALUES
(1, 1, 5, 1, '', '2026-01-13 07:08:33', NULL),
(3, 1, 5, 2, '', '2026-01-13 07:09:04', NULL),
(5, 1, 5, 3, '', '2026-01-13 07:09:29', NULL),
(7, 1, 5, 4, '', '2026-01-13 07:09:57', NULL),
(13, 1, 5, 5, '', '2026-01-13 07:19:24', NULL),
(16, 1, 5, 6, '', '2026-01-13 07:19:39', NULL),
(21, 1, 5, 7, '', '2026-01-13 07:19:56', NULL),
(25, 1, 5, 8, '', '2026-01-13 07:20:07', NULL),
(29, 1, 6, 1, '', '2026-01-13 09:23:56', NULL),
(33, 1, 6, 2, '', '2026-01-13 09:24:13', NULL),
(42, 1, 6, 3, '', '2026-01-13 09:52:00', NULL),
(48, 1, 6, 4, '', '2026-01-13 09:52:19', NULL),
(52, 1, 6, 5, '', '2026-01-13 09:52:48', NULL),
(57, 1, 6, 6, '', '2026-01-13 09:53:04', NULL),
(61, 1, 6, 7, '', '2026-01-13 09:53:25', NULL),
(65, 1, 6, 8, '', '2026-01-13 09:53:44', NULL),
(69, 1, 7, 1, '', '2026-01-13 10:43:38', NULL),
(73, 1, 7, 2, '', '2026-01-13 10:44:01', NULL),
(77, 1, 7, 3, '', '2026-01-13 10:44:13', NULL),
(81, 1, 7, 4, '', '2026-01-13 10:44:23', NULL),
(84, 1, 7, 5, '', '2026-01-13 10:44:36', NULL),
(88, 1, 7, 6, '', '2026-01-13 10:44:49', NULL),
(93, 1, 7, 7, '', '2026-01-13 10:45:03', NULL),
(98, 1, 7, 8, '', '2026-01-13 10:45:15', NULL),
(104, 1, 8, 1, '', '2026-01-13 10:47:07', NULL),
(108, 1, 8, 2, '', '2026-01-13 10:47:16', NULL),
(113, 1, 8, 3, '', '2026-01-13 10:47:30', NULL),
(117, 1, 8, 4, '', '2026-01-13 10:47:44', NULL),
(121, 1, 8, 5, '', '2026-01-13 10:47:57', NULL),
(125, 1, 8, 6, '', '2026-01-13 10:48:12', NULL),
(129, 1, 8, 7, '', '2026-01-13 10:48:29', NULL),
(133, 1, 8, 8, '', '2026-01-13 10:48:47', NULL),
(137, 1, 9, 1, '', '2026-01-13 10:50:54', NULL),
(141, 1, 9, 2, '', '2026-01-13 10:51:03', NULL),
(145, 1, 9, 3, '', '2026-01-13 10:51:13', NULL),
(149, 1, 9, 4, '', '2026-01-13 10:51:33', NULL),
(155, 1, 9, 5, '', '2026-01-13 10:51:53', NULL),
(159, 1, 9, 6, '', '2026-01-13 10:52:15', NULL),
(164, 1, 9, 7, '', '2026-01-13 10:52:39', NULL),
(168, 1, 9, 8, '', '2026-01-13 10:52:57', NULL),
(177, 4, 9, 2, '', '2026-01-13 11:56:15', NULL),
(178, 4, 9, 3, '', '2026-01-13 11:56:17', NULL),
(179, 4, 9, 6, '', '2026-01-13 11:56:20', NULL),
(180, 4, 9, 7, '', '2026-01-13 11:56:22', NULL),
(181, 4, 9, 8, '', '2026-01-13 11:56:25', NULL),
(182, 5, 9, 2, '', '2026-01-13 11:56:29', NULL),
(286, 5, 9, 3, '', '2026-01-13 12:31:33', NULL),
(288, 5, 9, 6, '', '2026-01-13 12:31:37', NULL),
(290, 5, 9, 7, '', '2026-01-13 12:31:43', NULL),
(293, 5, 9, 8, '', '2026-01-13 12:31:48', NULL);

--
-- Triggers `judge_comments`
--
DELIMITER $$
CREATE TRIGGER `prevent_comment_change_if_submitted` BEFORE INSERT ON `judge_comments` FOR EACH ROW BEGIN
    DECLARE judge_status VARCHAR(20);
    DECLARE current_round_id INT;

    -- 1. Find the Round ID based on the Segment
    SELECT round_id INTO current_round_id FROM segments WHERE id = NEW.segment_id;

    -- 2. Check the Judge's status for that Round
    SELECT status INTO judge_status 
    FROM judge_round_status 
    WHERE round_id = current_round_id AND judge_id = NEW.judge_id;

    -- 3. If Submitted, BLOCK the comment
    IF judge_status = 'Submitted' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'DENIED: You cannot add/edit comments after submitting. Ask the Coordinator to unlock you.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `judge_round_status`
--

CREATE TABLE `judge_round_status` (
  `id` int(11) NOT NULL,
  `round_id` int(11) NOT NULL,
  `judge_id` int(11) NOT NULL,
  `status` enum('Pending','Submitted') DEFAULT 'Pending',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `unlocked_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `judge_round_status`
--

INSERT INTO `judge_round_status` (`id`, `round_id`, `judge_id`, `status`, `submitted_at`, `unlocked_at`) VALUES
(1, 1, 5, 'Submitted', '2026-01-13 09:19:44', NULL),
(6, 1, 6, 'Submitted', '2026-01-13 09:53:54', NULL),
(7, 1, 7, 'Submitted', '2026-01-13 10:45:27', NULL),
(8, 1, 8, 'Submitted', '2026-01-13 10:48:59', NULL),
(9, 1, 9, 'Submitted', '2026-01-13 10:53:31', NULL),
(10, 2, 9, 'Submitted', '2026-01-13 12:32:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rounds`
--

CREATE TABLE `rounds` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `ordering` int(11) NOT NULL,
  `type` enum('Elimination','Final') NOT NULL DEFAULT 'Elimination',
  `qualify_count` int(11) NOT NULL DEFAULT 1,
  `status` enum('Pending','Active','Completed') DEFAULT 'Pending',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rounds`
--

INSERT INTO `rounds` (`id`, `event_id`, `title`, `ordering`, `type`, `qualify_count`, `status`, `is_deleted`, `created_at`) VALUES
(1, 1, 'Preliminary Round', 1, 'Elimination', 5, 'Completed', 0, '2026-01-12 18:10:48'),
(2, 1, 'Semi-Final Round', 2, 'Elimination', 3, 'Active', 0, '2026-01-12 18:11:42'),
(3, 1, 'Final Round', 3, 'Final', 1, 'Pending', 0, '2026-01-12 18:11:54');

--
-- Triggers `rounds`
--
DELIMITER $$
CREATE TRIGGER `prevent_round_deletion` BEFORE UPDATE ON `rounds` FOR EACH ROW BEGIN
    -- LOGIC: If trying to "Soft Delete" (set is_deleted = 1)
    IF NEW.is_deleted = 1 THEN
        -- CHECK: Is the round currently Active or Completed?
        IF OLD.status != 'Pending' THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'DENIED: You cannot delete a round that has already Started or Finished. Reset it to Pending first.';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `round_rankings`
--

CREATE TABLE `round_rankings` (
  `id` int(11) NOT NULL,
  `round_id` int(11) NOT NULL,
  `contestant_id` int(11) NOT NULL,
  `total_score` decimal(10,2) NOT NULL,
  `rank` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `round_rankings`
--

INSERT INTO `round_rankings` (`id`, `round_id`, `contestant_id`, `total_score`, `rank`) VALUES
(1, 1, 6, 92.60, 1),
(2, 1, 8, 92.40, 2),
(3, 1, 3, 90.60, 3),
(4, 1, 7, 90.60, 3),
(5, 1, 2, 89.00, 5),
(6, 1, 5, 88.40, 6),
(7, 1, 1, 88.20, 7),
(8, 1, 4, 86.40, 8);

-- --------------------------------------------------------

--
-- Table structure for table `scores`
--

CREATE TABLE `scores` (
  `id` int(11) NOT NULL,
  `criteria_id` int(11) NOT NULL,
  `judge_id` int(11) NOT NULL,
  `contestant_id` int(11) NOT NULL,
  `score_value` decimal(5,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scores`
--

INSERT INTO `scores` (`id`, `criteria_id`, `judge_id`, `contestant_id`, `score_value`, `created_at`, `updated_at`) VALUES
(47, 1, 5, 1, 25.00, '2026-01-13 07:08:33', '2026-01-13 07:37:24'),
(48, 2, 5, 1, 30.00, '2026-01-13 07:08:33', '2026-01-13 07:08:36'),
(54, 1, 5, 2, 25.00, '2026-01-13 07:09:04', '2026-01-13 07:37:24'),
(55, 2, 5, 2, 35.00, '2026-01-13 07:09:04', '2026-01-13 07:09:07'),
(58, 3, 5, 2, 25.00, '2026-01-13 07:09:07', '2026-01-13 07:37:24'),
(61, 1, 5, 3, 25.00, '2026-01-13 07:09:29', '2026-01-13 07:37:24'),
(62, 2, 5, 3, 35.00, '2026-01-13 07:09:29', '2026-01-13 07:37:24'),
(66, 1, 5, 4, 25.00, '2026-01-13 07:09:57', '2026-01-13 07:37:24'),
(67, 2, 5, 4, 25.00, '2026-01-13 07:09:57', '2026-01-13 07:09:59'),
(70, 3, 5, 4, 25.00, '2026-01-13 07:09:59', '2026-01-13 07:37:24'),
(75, 3, 5, 1, 30.00, '2026-01-13 07:19:13', '2026-01-13 07:37:24'),
(81, 3, 5, 3, 30.00, '2026-01-13 07:19:18', '2026-01-13 07:37:24'),
(85, 1, 5, 5, 25.00, '2026-01-13 07:19:24', '2026-01-13 07:37:24'),
(87, 2, 5, 5, 25.00, '2026-01-13 07:19:27', '2026-01-13 07:37:24'),
(90, 3, 5, 5, 26.00, '2026-01-13 07:19:33', '2026-01-13 07:37:24'),
(91, 1, 5, 6, 25.00, '2026-01-13 07:19:39', '2026-01-13 07:19:41'),
(94, 2, 5, 6, 35.00, '2026-01-13 07:19:45', '2026-01-13 07:37:24'),
(97, 3, 5, 6, 25.00, '2026-01-13 07:19:49', '2026-01-13 07:37:24'),
(101, 1, 5, 7, 26.00, '2026-01-13 07:19:56', '2026-01-13 07:37:24'),
(103, 2, 5, 7, 23.00, '2026-01-13 07:19:58', '2026-01-13 07:37:24'),
(106, 3, 5, 7, 28.00, '2026-01-13 07:20:01', '2026-01-13 07:37:24'),
(110, 1, 5, 8, 24.00, '2026-01-13 07:20:07', '2026-01-13 07:37:24'),
(112, 2, 5, 8, 36.00, '2026-01-13 07:20:10', '2026-01-13 07:37:24'),
(115, 3, 5, 8, 29.00, '2026-01-13 07:20:13', '2026-01-13 07:37:24'),
(119, 1, 6, 1, 26.00, '2026-01-13 09:23:56', NULL),
(121, 2, 6, 1, 23.00, '2026-01-13 09:23:58', NULL),
(124, 3, 6, 1, 28.00, '2026-01-13 09:24:03', NULL),
(128, 1, 6, 2, 26.00, '2026-01-13 09:51:04', '2026-01-13 09:51:14'),
(131, 2, 6, 2, 38.00, '2026-01-13 09:51:24', '2026-01-13 09:51:27'),
(136, 3, 6, 2, 24.00, '2026-01-13 09:51:31', NULL),
(140, 2, 6, 3, 35.00, '2026-01-13 09:52:00', '2026-01-13 09:52:02'),
(143, 3, 6, 3, 29.00, '2026-01-13 09:52:05', NULL),
(146, 1, 6, 3, 25.00, '2026-01-13 09:52:14', NULL),
(152, 1, 6, 4, 26.00, '2026-01-13 09:52:19', '2026-01-13 09:52:20'),
(155, 2, 6, 4, 38.00, '2026-01-13 09:52:24', NULL),
(158, 3, 6, 4, 24.00, '2026-01-13 09:52:26', NULL),
(159, 1, 6, 5, 28.00, '2026-01-13 09:52:48', NULL),
(161, 2, 6, 5, 35.00, '2026-01-13 09:52:51', '2026-01-13 09:52:52'),
(166, 3, 6, 5, 26.00, '2026-01-13 09:52:56', NULL),
(170, 1, 6, 6, 28.00, '2026-01-13 09:53:04', NULL),
(172, 2, 6, 6, 36.00, '2026-01-13 09:53:06', NULL),
(175, 3, 6, 6, 28.00, '2026-01-13 09:53:10', NULL),
(179, 1, 6, 7, 28.00, '2026-01-13 09:53:25', NULL),
(181, 2, 6, 7, 36.00, '2026-01-13 09:53:27', '2026-01-13 09:53:28'),
(186, 3, 6, 7, 24.00, '2026-01-13 09:53:32', NULL),
(187, 1, 6, 8, 28.00, '2026-01-13 09:53:44', NULL),
(189, 2, 6, 8, 38.00, '2026-01-13 09:53:47', NULL),
(192, 3, 6, 8, 26.00, '2026-01-13 09:53:50', NULL),
(196, 1, 7, 1, 28.00, '2026-01-13 10:43:38', NULL),
(198, 2, 7, 1, 38.00, '2026-01-13 10:43:49', NULL),
(201, 3, 7, 1, 28.00, '2026-01-13 10:43:51', NULL),
(205, 1, 7, 2, 25.00, '2026-01-13 10:44:01', NULL),
(207, 2, 7, 2, 36.00, '2026-01-13 10:44:05', NULL),
(210, 3, 7, 2, 28.00, '2026-01-13 10:44:09', NULL),
(214, 3, 7, 3, 28.00, '2026-01-13 10:44:13', NULL),
(215, 2, 7, 3, 35.00, '2026-01-13 10:44:16', NULL),
(217, 1, 7, 3, 24.00, '2026-01-13 10:44:19', NULL),
(223, 3, 7, 4, 28.00, '2026-01-13 10:44:23', NULL),
(224, 1, 7, 4, 28.00, '2026-01-13 10:44:32', NULL),
(225, 2, 7, 4, 36.00, '2026-01-13 10:44:32', NULL),
(230, 3, 7, 5, 28.00, '2026-01-13 10:44:36', NULL),
(231, 2, 7, 5, 36.00, '2026-01-13 10:44:39', NULL),
(233, 1, 7, 5, 24.00, '2026-01-13 10:44:42', NULL),
(239, 3, 7, 6, 28.00, '2026-01-13 10:44:49', NULL),
(240, 2, 7, 6, 39.00, '2026-01-13 10:44:53', NULL),
(242, 1, 7, 6, 27.00, '2026-01-13 10:44:55', '2026-01-13 10:44:56'),
(251, 3, 7, 7, 26.00, '2026-01-13 10:45:03', NULL),
(252, 2, 7, 7, 36.00, '2026-01-13 10:45:05', NULL),
(254, 1, 7, 7, 26.00, '2026-01-13 10:45:08', '2026-01-13 10:45:09'),
(263, 3, 7, 8, 27.00, '2026-01-13 10:45:15', '2026-01-13 10:45:19'),
(266, 2, 7, 8, 37.00, '2026-01-13 10:45:21', NULL),
(268, 1, 7, 8, 27.00, '2026-01-13 10:45:23', NULL),
(274, 1, 8, 1, 25.00, '2026-01-13 10:47:07', NULL),
(276, 2, 8, 1, 36.00, '2026-01-13 10:47:09', NULL),
(279, 3, 8, 1, 24.00, '2026-01-13 10:47:12', NULL),
(283, 3, 8, 2, 28.00, '2026-01-13 10:47:16', '2026-01-13 10:47:17'),
(285, 2, 8, 2, 39.00, '2026-01-13 10:47:23', NULL),
(287, 1, 8, 2, 30.00, '2026-01-13 10:47:25', NULL),
(293, 3, 8, 3, 30.00, '2026-01-13 10:47:30', NULL),
(294, 2, 8, 3, 39.00, '2026-01-13 10:47:35', NULL),
(296, 1, 8, 3, 26.00, '2026-01-13 10:47:37', NULL),
(302, 1, 8, 4, 25.00, '2026-01-13 10:47:44', NULL),
(304, 2, 8, 4, 39.00, '2026-01-13 10:47:48', NULL),
(307, 3, 8, 4, 26.00, '2026-01-13 10:47:50', NULL),
(311, 1, 8, 5, 29.00, '2026-01-13 10:47:57', NULL),
(313, 2, 8, 5, 39.00, '2026-01-13 10:48:01', NULL),
(316, 3, 8, 5, 29.00, '2026-01-13 10:48:05', '2026-01-13 10:48:06'),
(320, 1, 8, 6, 28.00, '2026-01-13 10:48:12', NULL),
(322, 2, 8, 6, 36.00, '2026-01-13 10:48:15', NULL),
(325, 3, 8, 6, 28.00, '2026-01-13 10:48:19', NULL),
(329, 1, 8, 7, 30.00, '2026-01-13 10:48:29', NULL),
(331, 2, 8, 7, 40.00, '2026-01-13 10:48:31', NULL),
(334, 3, 8, 7, 30.00, '2026-01-13 10:48:33', NULL),
(338, 1, 8, 8, 30.00, '2026-01-13 10:48:47', NULL),
(340, 2, 8, 8, 40.00, '2026-01-13 10:48:48', NULL),
(343, 3, 8, 8, 30.00, '2026-01-13 10:48:51', NULL),
(347, 1, 9, 1, 30.00, '2026-01-13 10:50:54', '2026-01-13 10:53:20'),
(349, 2, 9, 1, 40.00, '2026-01-13 10:50:56', '2026-01-13 10:53:17'),
(352, 3, 9, 1, 30.00, '2026-01-13 10:50:59', '2026-01-13 10:53:14'),
(356, 3, 9, 2, 25.00, '2026-01-13 10:51:03', NULL),
(357, 2, 9, 2, 34.00, '2026-01-13 10:51:06', NULL),
(359, 1, 9, 2, 27.00, '2026-01-13 10:51:08', NULL),
(365, 3, 9, 3, 28.00, '2026-01-13 10:51:13', NULL),
(366, 2, 9, 3, 39.00, '2026-01-13 10:51:15', NULL),
(368, 1, 9, 3, 25.00, '2026-01-13 10:51:18', NULL),
(374, 3, 9, 4, 26.00, '2026-01-13 10:51:33', NULL),
(375, 2, 9, 4, 36.00, '2026-01-13 10:51:35', '2026-01-13 10:51:37'),
(379, 1, 9, 4, 25.00, '2026-01-13 10:51:39', '2026-01-13 10:51:40'),
(388, 1, 9, 5, 28.00, '2026-01-13 10:51:53', NULL),
(390, 2, 9, 5, 36.00, '2026-01-13 10:51:59', NULL),
(393, 3, 9, 5, 28.00, '2026-01-13 10:52:09', NULL),
(397, 3, 9, 6, 30.00, '2026-01-13 10:52:15', NULL),
(398, 2, 9, 6, 40.00, '2026-01-13 10:52:20', '2026-01-13 10:52:22'),
(402, 1, 9, 6, 30.00, '2026-01-13 10:52:24', NULL),
(408, 3, 9, 7, 30.00, '2026-01-13 10:52:39', NULL),
(409, 2, 9, 7, 40.00, '2026-01-13 10:52:42', NULL),
(411, 1, 9, 7, 30.00, '2026-01-13 10:52:44', NULL),
(417, 3, 9, 8, 26.00, '2026-01-13 10:52:57', NULL),
(418, 2, 9, 8, 36.00, '2026-01-13 10:53:00', NULL),
(420, 1, 9, 8, 28.00, '2026-01-13 10:53:02', NULL),
(441, 4, 9, 2, 24.00, '2026-01-13 12:12:06', '2026-01-13 12:30:55'),
(464, 5, 9, 2, 22.00, '2026-01-13 12:18:56', '2026-01-13 12:30:02'),
(465, 6, 9, 2, 17.00, '2026-01-13 12:18:56', '2026-01-13 12:30:02'),
(478, 5, 9, 3, 25.00, '2026-01-13 12:19:15', '2026-01-13 12:23:44'),
(479, 6, 9, 3, 26.00, '2026-01-13 12:19:15', '2026-01-13 12:23:48'),
(480, 4, 9, 3, 22.00, '2026-01-13 12:19:20', '2026-01-13 12:19:22'),
(534, 4, 9, 6, 17.00, '2026-01-13 12:23:53', '2026-01-13 12:30:46'),
(536, 5, 9, 6, 30.00, '2026-01-13 12:23:55', NULL),
(539, 6, 9, 6, 25.00, '2026-01-13 12:23:59', NULL),
(543, 4, 9, 7, 30.00, '2026-01-13 12:24:03', NULL),
(626, 5, 9, 7, 24.00, '2026-01-13 12:30:20', '2026-01-13 12:30:26'),
(627, 6, 9, 7, 19.00, '2026-01-13 12:30:20', NULL),
(634, 4, 9, 8, 25.00, '2026-01-13 12:30:30', '2026-01-13 12:31:19'),
(635, 5, 9, 8, 14.00, '2026-01-13 12:30:30', NULL),
(636, 6, 9, 8, 20.00, '2026-01-13 12:30:30', NULL),
(691, 7, 9, 2, 24.00, '2026-01-13 12:31:27', NULL),
(692, 8, 9, 2, 26.00, '2026-01-13 12:31:27', '2026-01-13 12:31:29'),
(693, 9, 9, 2, 23.00, '2026-01-13 12:31:27', '2026-01-13 12:31:29'),
(709, 7, 9, 3, 34.00, '2026-01-13 12:31:33', '2026-01-13 12:31:34'),
(710, 8, 9, 3, 31.00, '2026-01-13 12:31:33', NULL),
(711, 9, 9, 3, 23.00, '2026-01-13 12:31:33', NULL),
(721, 7, 9, 6, 37.00, '2026-01-13 12:31:37', NULL),
(722, 8, 9, 6, 29.00, '2026-01-13 12:31:37', NULL),
(723, 9, 9, 6, 23.00, '2026-01-13 12:31:37', NULL),
(733, 7, 9, 7, 38.00, '2026-01-13 12:31:43', NULL),
(734, 8, 9, 7, 29.00, '2026-01-13 12:31:43', NULL),
(735, 9, 9, 7, 20.00, '2026-01-13 12:31:43', '2026-01-13 12:31:44'),
(751, 7, 9, 8, 31.00, '2026-01-13 12:31:48', NULL),
(752, 8, 9, 8, 29.00, '2026-01-13 12:31:48', NULL),
(753, 9, 9, 8, 21.00, '2026-01-13 12:31:48', NULL);

--
-- Triggers `scores`
--
DELIMITER $$
CREATE TRIGGER `prevent_score_change_if_submitted` BEFORE INSERT ON `scores` FOR EACH ROW BEGIN
    DECLARE judge_status VARCHAR(20);
    DECLARE current_round_id INT;

    -- 1. Find the Round ID based on the Criteria being scored
    SELECT s.round_id INTO current_round_id
    FROM criteria c
    JOIN segments s ON c.segment_id = s.id
    WHERE c.id = NEW.criteria_id;

    -- 2. Check the Judge's status for that Round
    SELECT status INTO judge_status 
    FROM judge_round_status 
    WHERE round_id = current_round_id AND judge_id = NEW.judge_id;

    -- 3. If Status is Submitted, BLOCK the score
    IF judge_status = 'Submitted' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'DENIED: You have already submitted your scores. Ask the Coordinator to unlock you.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `prevent_score_update_if_submitted` BEFORE UPDATE ON `scores` FOR EACH ROW BEGIN
    DECLARE judge_status VARCHAR(20);
    DECLARE current_round_id INT;

    -- 1. Find the Round ID based on the Criteria
    -- We use NEW.criteria_id to ensure we are checking the destination criteria
    SELECT s.round_id INTO current_round_id
    FROM criteria c
    JOIN segments s ON c.segment_id = s.id
    WHERE c.id = NEW.criteria_id;

    -- 2. Check the Judge's status for that Round
    SELECT status INTO judge_status 
    FROM judge_round_status 
    WHERE round_id = current_round_id AND judge_id = NEW.judge_id;

    -- 3. If Status is Submitted, BLOCK the edit
    IF judge_status = 'Submitted' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'DENIED: You cannot edit scores after submitting. Ask the Coordinator to unlock you first.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `segments`
--

CREATE TABLE `segments` (
  `id` int(11) NOT NULL,
  `round_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `weight_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `ordering` int(11) NOT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `segments`
--

INSERT INTO `segments` (`id`, `round_id`, `title`, `description`, `weight_percent`, `ordering`, `is_deleted`, `created_at`) VALUES
(1, 1, 'Production Number', 'Group performance showcasing stage presence and coordination', 100.00, 1, 0, '2026-01-12 18:23:43'),
(2, 1, 'Swimsuit Competition', 'Emphasizes confidence, posture, and physical fitness', 20.00, 2, 1, '2026-01-12 18:27:08'),
(3, 1, 'Evening Gown', 'Judges elegance, poise, and overall presentation', 25.00, 3, 1, '2026-01-12 18:28:35'),
(4, 2, 'Swimsuit Competition', 'Measures confidence and physical presentation', 50.00, 1, 0, '2026-01-12 18:39:46'),
(5, 2, 'Evening Gown', 'Showcases elegance and grace', 50.00, 2, 0, '2026-01-12 18:40:17'),
(6, 3, 'Evening Gown', 'Assesses refined elegance under pressure', 50.00, 1, 0, '2026-01-12 18:44:22'),
(7, 3, 'Question and Answer', 'Evaluates communication and critical thinking', 50.00, 2, 0, '2026-01-12 18:45:03');

--
-- Triggers `segments`
--
DELIMITER $$
CREATE TRIGGER `guard_segment_deletion` BEFORE UPDATE ON `segments` FOR EACH ROW BEGIN
    DECLARE round_status VARCHAR(20);
    
    -- Only check if we are trying to soft delete
    IF NEW.is_deleted = 1 THEN
        -- Get the status of the parent Round
        SELECT status INTO round_status FROM rounds WHERE id = OLD.round_id;
        
        IF round_status != 'Pending' THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'DENIED: Cannot delete Segment. The Round has already started.';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `ticket_code` varchar(6) NOT NULL,
  `status` enum('Unused','Used') DEFAULT 'Unused',
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `event_id`, `ticket_code`, `status`, `generated_at`, `used_at`) VALUES
(1, 1, 'F3E568', 'Used', '2026-01-12 05:52:12', NULL),
(2, 1, 'BJN4WS', 'Used', '2026-01-12 05:52:12', '2026-01-13 10:20:54'),
(3, 1, '6XGKWH', 'Unused', '2026-01-12 05:52:12', NULL),
(4, 1, 'CPF7NT', 'Unused', '2026-01-12 05:52:12', NULL),
(5, 1, 'FPNR4C', 'Unused', '2026-01-12 05:52:12', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Event Manager','Tabulator','Judge Coordinator','Contestant Manager','Judge','Contestant') NOT NULL DEFAULT 'Contestant',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `created_by`, `name`, `email`, `phone`, `password`, `role`, `status`, `is_deleted`, `created_at`) VALUES
(1, NULL, 'Debbie Custorio', 'eventmanager@gmail.com', NULL, '$2a$10$giwtTTitX12g2TyoNQ/GGesoXsPSEs8675b1Hd3HjwAn22hE9iZSS', 'Event Manager', 'Active', 0, '2026-01-11 16:02:03'),
(2, 1, 'John Doe', 'secondemailkoini@gmail.com', '01234567891', '$2y$10$QICU.JYUTaX9VBSCbdsDD.2TCDKS5IYw62jNDrLMubQkw93s0ehoy', 'Judge Coordinator', 'Active', 0, '2026-01-12 02:23:05'),
(3, 1, 'Maria Elena Cruz', 'maria@gmail.com', '11111111111', '$2y$10$SPAxpiekZrY4NUOD9dI5Z.s9JaN2L0AXA7TRHT0VyZXE9Uk0u2Z/m', 'Contestant Manager', 'Active', 0, '2026-01-12 02:39:20'),
(4, 1, 'Andrea Nicole Reyes', 'andrea@gmail.com', '22222222222', '$2y$10$6HQVHlrNaba2VHFK6JZfi.GwAx/k4PFyy2JKbeaIaYtQMhyrfSX3.', 'Tabulator', 'Active', 0, '2026-01-12 02:40:38'),
(5, 1, 'Atty. Ramon Velasco', 'thirdemail341@gmail.com', NULL, '$2y$10$eJpp2hlZQRtyeJakKzFhhek3C2HdOFC7Wzsxzx7wWisd6d9luNS6y', 'Judge', 'Active', 0, '2026-01-12 02:42:24'),
(6, 1, 'Bianca Lorraine Mendoza', 'bianca@gmail.com', NULL, '$2y$10$cGqpD.ZknR2uyBf2iXx4HOhi/Thl3.PpzHLKD9dm.1gOvZwOZOGt.', 'Judge', 'Active', 0, '2026-01-12 03:06:06'),
(7, 1, 'Dr. Luis Antonio Perez', 'luiz@gmail.com', NULL, '$2y$10$27eSj.7DuqmS9k4711gP0OE37ZouA6a09NDHjDao8EEZyR2To3zrW', 'Judge', 'Active', 0, '2026-01-12 03:15:59'),
(8, 1, 'Chandler Bing', 'chandlerbing@gmail.com', NULL, '$2y$10$ndS0UXqEDXTUif3vTFmImeCQhb37GS7pIGZ.FtQdoUP613cmP97BO', 'Judge', 'Active', 0, '2026-01-12 03:25:10'),
(9, 1, 'Marlo Adel', 'marlohermanoadel@gmail.com', NULL, '$2y$10$PmZQ/w051KFtaMqqR.S8he9atDmEIdO9ouzuzMGVQc.GR0mp2Wg7u', 'Judge', 'Active', 0, '2026-01-12 05:47:27'),
(10, 1, 'Rachel Green', 'rachel@gmail.com', NULL, '$2y$10$m5UO/lfORvGNFkkM0d0EPOCqHnWrJPfrLpo6AF8UMYohcOZyAOexy', 'Contestant', 'Active', 0, '2026-01-12 16:28:16'),
(11, 1, 'Pheobe Buffay', 'phoebe@gmail.com', NULL, '$2y$10$vwRUnTjvmk0oqYu.j2b0zesqJDGb47emlWCyCSmVApyxEMr7DtKwq', 'Contestant', 'Active', 0, '2026-01-12 17:09:09'),
(12, 1, 'Monica Geller', 'monica@gmail.com', NULL, '$2y$10$EvhMeeqkTXKtxZBciO88HOGD.KM8UYW5pl2tdBEVBHQMLPdgxa2SW', 'Contestant', 'Active', 0, '2026-01-12 17:22:43'),
(13, 1, 'Michelle Anne Dela Cruz', 'michele@gmail.com', NULL, '$2y$10$KYGKZQJOt1YPfPGFsu3k2eh1FYfx42.vp7LNtbUK.KQ65Fxc.EBK2', 'Contestant', 'Active', 0, '2026-01-12 17:24:26'),
(14, 1, 'Trisha Mae Gonzales', 'trisha@gmail.com', NULL, '$2y$10$LMezEnOuL1..i.bI1e9i3eqvoeVlLTN2T.ZWPqMEakkw89GAOogWa', 'Contestant', 'Active', 0, '2026-01-12 17:26:33'),
(15, 1, 'Alyssa Montoya', 'alyssa@gmail.com', NULL, '$2y$10$FMYOyB4SgyLBL7dTclqZmO2HUS1f9qbI038GmD7n8wwXMq2OYuNee', 'Contestant', 'Active', 0, '2026-01-12 17:28:02'),
(16, 1, 'Francine Mae Navarro', 'francine@gmail.com', NULL, '$2y$10$jqWdxbjis/OE0L73/rBR7uPde1gNExR4OPayzNAa5n2/FBg/aLDpK', 'Contestant', 'Active', 0, '2026-01-12 17:29:50'),
(18, 1, 'Nicole Patrice Uy', 'fourthemail936@gmail.com', NULL, '$2y$10$lcGS4h03.vKYUnBbjh.QuuRtSgOAVo.Y1hdX/petzVHP7OhC/T6i6', 'Contestant', 'Active', 0, '2026-01-12 17:33:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audience_votes`
--
ALTER TABLE `audience_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vote_per_ticket` (`ticket_id`),
  ADD KEY `fk_vote_contestant` (`contestant_id`);

--
-- Indexes for table `awards`
--
ALTER TABLE `awards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_award_event` (`event_id`),
  ADD KEY `fk_award_round` (`linked_round_id`),
  ADD KEY `fk_award_segment` (`linked_segment_id`);

--
-- Indexes for table `award_winners`
--
ALTER TABLE `award_winners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_award_winner` (`award_id`,`contestant_id`,`title_label`),
  ADD KEY `fk_aw_contestant` (`contestant_id`);

--
-- Indexes for table `criteria`
--
ALTER TABLE `criteria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_criteria_segment` (`segment_id`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_manager` (`manager_id`);

--
-- Indexes for table `event_activities`
--
ALTER TABLE `event_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_date` (`event_id`,`activity_date`);

--
-- Indexes for table `event_contestants`
--
ALTER TABLE `event_contestants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_per_event` (`event_id`,`user_id`),
  ADD KEY `fk_ec_user` (`user_id`);

--
-- Indexes for table `event_judges`
--
ALTER TABLE `event_judges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_event_judge` (`event_id`,`judge_id`),
  ADD KEY `fk_judge_user` (`judge_id`);

--
-- Indexes for table `event_teams`
--
ALTER TABLE `event_teams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_team_role` (`event_id`,`user_id`,`role`),
  ADD KEY `fk_team_user` (`user_id`);

--
-- Indexes for table `judge_comments`
--
ALTER TABLE `judge_comments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_segment_comment` (`segment_id`,`judge_id`,`contestant_id`),
  ADD KEY `fk_comment_judge` (`judge_id`),
  ADD KEY `fk_comment_contestant` (`contestant_id`);

--
-- Indexes for table `judge_round_status`
--
ALTER TABLE `judge_round_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_status_per_round` (`round_id`,`judge_id`),
  ADD KEY `fk_jrs_judge` (`judge_id`);

--
-- Indexes for table `rounds`
--
ALTER TABLE `rounds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_order` (`event_id`,`ordering`);

--
-- Indexes for table `round_rankings`
--
ALTER TABLE `round_rankings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `round_id` (`round_id`),
  ADD KEY `contestant_id` (`contestant_id`);

--
-- Indexes for table `scores`
--
ALTER TABLE `scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_score` (`criteria_id`,`judge_id`,`contestant_id`),
  ADD KEY `fk_score_judge` (`judge_id`),
  ADD KEY `fk_score_contestant` (`contestant_id`);

--
-- Indexes for table `segments`
--
ALTER TABLE `segments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_segment_round` (`round_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ticket_code` (`ticket_code`),
  ADD KEY `idx_event_status` (`event_id`,`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audience_votes`
--
ALTER TABLE `audience_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `awards`
--
ALTER TABLE `awards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `award_winners`
--
ALTER TABLE `award_winners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `criteria`
--
ALTER TABLE `criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `event_activities`
--
ALTER TABLE `event_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `event_contestants`
--
ALTER TABLE `event_contestants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `event_judges`
--
ALTER TABLE `event_judges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `event_teams`
--
ALTER TABLE `event_teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `judge_comments`
--
ALTER TABLE `judge_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=295;

--
-- AUTO_INCREMENT for table `judge_round_status`
--
ALTER TABLE `judge_round_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `rounds`
--
ALTER TABLE `rounds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `round_rankings`
--
ALTER TABLE `round_rankings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `scores`
--
ALTER TABLE `scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=760;

--
-- AUTO_INCREMENT for table `segments`
--
ALTER TABLE `segments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audience_votes`
--
ALTER TABLE `audience_votes`
  ADD CONSTRAINT `fk_vote_contestant` FOREIGN KEY (`contestant_id`) REFERENCES `event_contestants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vote_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `awards`
--
ALTER TABLE `awards`
  ADD CONSTRAINT `fk_award_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_award_round` FOREIGN KEY (`linked_round_id`) REFERENCES `rounds` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_award_segment` FOREIGN KEY (`linked_segment_id`) REFERENCES `segments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `award_winners`
--
ALTER TABLE `award_winners`
  ADD CONSTRAINT `fk_aw_award` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aw_contestant` FOREIGN KEY (`contestant_id`) REFERENCES `event_contestants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `criteria`
--
ALTER TABLE `criteria`
  ADD CONSTRAINT `fk_criteria_segment` FOREIGN KEY (`segment_id`) REFERENCES `segments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_event_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_activities`
--
ALTER TABLE `event_activities`
  ADD CONSTRAINT `fk_activity_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_contestants`
--
ALTER TABLE `event_contestants`
  ADD CONSTRAINT `fk_ec_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ec_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_judges`
--
ALTER TABLE `event_judges`
  ADD CONSTRAINT `fk_judge_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_judge_user` FOREIGN KEY (`judge_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_teams`
--
ALTER TABLE `event_teams`
  ADD CONSTRAINT `fk_team_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_team_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `judge_comments`
--
ALTER TABLE `judge_comments`
  ADD CONSTRAINT `fk_comment_contestant` FOREIGN KEY (`contestant_id`) REFERENCES `event_contestants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comment_judge` FOREIGN KEY (`judge_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comment_segment` FOREIGN KEY (`segment_id`) REFERENCES `segments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `judge_round_status`
--
ALTER TABLE `judge_round_status`
  ADD CONSTRAINT `fk_jrs_judge` FOREIGN KEY (`judge_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_jrs_round` FOREIGN KEY (`round_id`) REFERENCES `rounds` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rounds`
--
ALTER TABLE `rounds`
  ADD CONSTRAINT `fk_round_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scores`
--
ALTER TABLE `scores`
  ADD CONSTRAINT `fk_score_contestant` FOREIGN KEY (`contestant_id`) REFERENCES `event_contestants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_score_criteria` FOREIGN KEY (`criteria_id`) REFERENCES `criteria` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_score_judge` FOREIGN KEY (`judge_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `segments`
--
ALTER TABLE `segments`
  ADD CONSTRAINT `fk_segment_round` FOREIGN KEY (`round_id`) REFERENCES `rounds` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_ticket_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
