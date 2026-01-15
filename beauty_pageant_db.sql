-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 15, 2026 at 02:45 AM
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
(1, 2, 1, '2026-01-13 10:20:54'),
(2, 3, 3, '2026-01-13 16:47:39'),
(3, 4, 3, '2026-01-13 16:48:07'),
(4, 5, 3, '2026-01-13 16:48:32'),
(5, 6, 12, '2026-01-14 14:33:47');

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
(6, 1, 'Best in Semi-Final Round', 'Awarded to the contestant with the highest semi-final score.', 'Minor', 'Highest_Round', 2, NULL, 'Active', 0, '2026-01-13 12:33:24'),
(7, 1, 'Mutya San Old Rizal', 'Official winner of the Mutya San Old Rizal', 'Major', 'Highest_Round', 3, NULL, 'Active', 0, '2026-01-13 16:02:51'),
(8, 2, 'Miss UEP 2026', 'Awarded to the contestant with the highest overall score in the Final Round.', 'Major', 'Highest_Round', 6, NULL, 'Active', 0, '2026-01-14 14:13:18'),
(9, 2, 'Best in Swimsuit', 'Given to the contestant with the highest Swimsuit segment score.', 'Minor', 'Highest_Segment', NULL, 9, 'Active', 1, '2026-01-14 14:14:42'),
(10, 2, 'Best in Evening Gown', 'Awarded to the contestant who best demonstrated elegance and poise.', 'Minor', 'Highest_Segment', NULL, 10, 'Active', 0, '2026-01-14 14:16:48'),
(11, 2, 'People’s Choice Award', 'Given to the contestant who received the highest number of audience votes.', 'Minor', 'Audience_Vote', NULL, NULL, 'Active', 0, '2026-01-14 14:17:07'),
(12, 2, 'Miss Photogenic', 'Awarded by the organizers based on official photos and media appeal.', 'Minor', 'Manual', NULL, NULL, 'Active', 0, '2026-01-14 14:17:27');

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
(15, 7, 'Intelligence', 'Logic and insight of the answer', 25.00, 3, 0, '2026-01-13 03:26:41'),
(16, 8, 'Timing', 'Accuracy in following music cues.', 30.00, 1, 0, '2026-01-14 10:47:58'),
(17, 8, 'Energy', 'Enthusiasm and performance intensity.', 40.00, 2, 0, '2026-01-14 10:48:25'),
(18, 8, 'Coordination', 'Precision and harmony of movements.', 30.00, 3, 0, '2026-01-14 10:49:03'),
(19, 9, 'Confidence', 'Composure and self-assurance on stage.', 40.00, 1, 0, '2026-01-14 10:50:42'),
(20, 9, 'Physique', 'Body proportion and posture.', 30.00, 2, 0, '2026-01-14 10:52:47'),
(21, 9, 'Stage Presence', 'Command and engagement on stage.', 30.00, 3, 0, '2026-01-14 10:54:22'),
(22, 10, 'Poise', 'Graceful movement and posture.', 40.00, 1, 0, '2026-01-14 11:03:00'),
(23, 10, 'Elegance', 'Refinement and overall appearance', 35.00, 2, 0, '2026-01-14 11:03:39'),
(24, 10, 'Walk', 'Smoothness and control of runway walk', 25.00, 3, 0, '2026-01-14 11:04:05'),
(25, 11, 'Clarity of Thought', 'Organization and clarity of ideas.', 40.00, 1, 0, '2026-01-14 11:06:00'),
(26, 11, 'Confidence', 'Composure and self-assurance during the interview.', 35.00, 2, 0, '2026-01-14 11:06:33'),
(27, 11, 'Authenticity', 'Genuineness and honesty in responses', 25.00, 3, 0, '2026-01-14 11:08:26'),
(28, 12, 'Poise', 'Graceful posture and movement.', 35.00, 1, 0, '2026-01-14 11:09:32'),
(29, 12, 'Stage Presence', 'Ability to command attention', 35.00, 2, 0, '2026-01-14 11:10:01'),
(30, 12, 'Overall Impact', 'Lasting impression on judges and audience.', 30.00, 3, 0, '2026-01-14 11:10:34'),
(31, 13, 'Content and Relevance', 'Substance and relevance of the advocacy', 45.00, 1, 0, '2026-01-14 11:11:46'),
(32, 13, 'Delivery', 'Clarity and effectiveness of speech delivery.', 30.00, 2, 0, '2026-01-14 11:12:21'),
(33, 13, 'Impact', 'Ability to inspire and persuade.', 25.00, 3, 0, '2026-01-14 11:12:43');

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
(7, 'fourthemail936@gmail.com', 'Official Contestant Registration', '<h2>Welcome, Nicole Patrice Uy!</h2><p>You have been registered for <b>Mutya San Old Rizal</b> as Contestant #8.</p><div style=\'background:#f3f4f6; padding:15px;\'><strong>Credentials:</strong><br>Email: fourthemail936@gmail.com<br>Password: nicole</div><p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\'>Login Now</a></p>', 'sent', 1, '2026-01-12 17:33:47', '2026-01-12 17:33:53'),
(8, 'camille@gmail.com', 'Team Assignment: Judge Coordinator for UEP Beauty Pageant', '\r\n            <h2>Welcome, Camille Rose Bautista!</h2>\r\n            <p>You have been assigned as a <b>Judge Coordinator</b> for <b>UEP Beauty Pageant</b>.</p>\r\n            \r\n            <div style=\'background:#f3f4f6; padding:15px; border-radius:8px; border:1px solid #ddd; margin:20px 0;\'>\r\n                <strong>Your Login Credentials:</strong><br>\r\n                Email: <b>camille@gmail.com</b><br>\r\n                Password: <b>camille</b>\r\n            </div>\r\n\r\n            <p>Please login to your dashboard to start managing your tasks:</p>\r\n            <p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\' style=\'background:#F59E0B; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;\'>Login to Dashboard</a></p>\r\n        ', 'sent', 1, '2026-01-14 05:13:05', '2026-01-14 05:13:09'),
(9, 'mark@gmail.com', 'Team Assignment: Contestant Manager for UEP Beauty Pageant', '\r\n            <h2>Welcome, Mark Anthony Robles!</h2>\r\n            <p>You have been assigned as a <b>Contestant Manager</b> for <b>UEP Beauty Pageant</b>.</p>\r\n            \r\n            <div style=\'background:#f3f4f6; padding:15px; border-radius:8px; border:1px solid #ddd; margin:20px 0;\'>\r\n                <strong>Your Login Credentials:</strong><br>\r\n                Email: <b>mark@gmail.com</b><br>\r\n                Password: <b>mark</b>\r\n            </div>\r\n\r\n            <p>Please login to your dashboard to start managing your tasks:</p>\r\n            <p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\' style=\'background:#F59E0B; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;\'>Login to Dashboard</a></p>\r\n        ', 'sent', 1, '2026-01-14 05:13:53', '2026-01-14 05:13:58'),
(10, 'joel@gmail.com', 'Team Assignment: Tabulator for UEP Beauty Pageant', '\r\n            <h2>Welcome, Joel Vincent Ramos!</h2>\r\n            <p>You have been assigned as a <b>Tabulator</b> for <b>UEP Beauty Pageant</b>.</p>\r\n            \r\n            <div style=\'background:#f3f4f6; padding:15px; border-radius:8px; border:1px solid #ddd; margin:20px 0;\'>\r\n                <strong>Your Login Credentials:</strong><br>\r\n                Email: <b>joel@gmail.com</b><br>\r\n                Password: <b>joel</b>\r\n            </div>\r\n\r\n            <p>Please login to your dashboard to start managing your tasks:</p>\r\n            <p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\' style=\'background:#F59E0B; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;\'>Login to Dashboard</a></p>\r\n        ', 'sent', 1, '2026-01-14 05:15:04', '2026-01-14 05:15:08'),
(11, 'liza@gmail.com', 'Team Assignment: Judge Coordinator for UEP Beauty Pageant', '\r\n            <h2>Welcome, Dr. Liza Mae Navarro!</h2>\r\n            <p>You have been assigned as a <b>Judge Coordinator</b> for <b>UEP Beauty Pageant</b>.</p>\r\n            \r\n            <div style=\'background:#f3f4f6; padding:15px; border-radius:8px; border:1px solid #ddd; margin:20px 0;\'>\r\n                <strong>Your Login Credentials:</strong><br>\r\n                Email: <b>liza@gmail.com</b><br>\r\n                Password: <b>liza</b>\r\n            </div>\r\n\r\n            <p>Please login to your dashboard to start managing your tasks:</p>\r\n            <p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\' style=\'background:#F59E0B; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;\'>Login to Dashboard</a></p>\r\n        ', 'sent', 1, '2026-01-14 05:16:15', '2026-01-14 05:16:19'),
(12, 'angela@gmail.com', 'Official Contestant Registration', '<h2>Welcome, Angela Mae Torres!</h2><p>You have been registered for <b>UEP Beauty Pageant</b> as Contestant #2.</p><div style=\'background:#f3f4f6; padding:15px;\'><strong>Credentials:</strong><br>Email: angela@gmail.com<br>Password: angela</div><p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\'>Login Now</a></p>', 'sent', 1, '2026-01-14 05:18:08', '2026-01-14 05:18:21'),
(13, 'janelle@gmail.com', 'Official Contestant Registration', '<h2>Welcome, Janelle Marie Aquino!</h2><p>You have been registered for <b>UEP Beauty Pageant</b> as Contestant #3.</p><div style=\'background:#f3f4f6; padding:15px;\'><strong>Credentials:</strong><br>Email: janelle@gmail.com<br>Password: janelle</div><p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\'>Login Now</a></p>', 'sent', 1, '2026-01-14 05:27:12', '2026-01-14 05:27:18'),
(14, 'rhea@gmail.com', 'Official Contestant Registration', '<h2>Welcome, Rhea Camille Domingo!</h2><p>You have been registered for <b>UEP Beauty Pageant</b> as Contestant #4.</p><div style=\'background:#f3f4f6; padding:15px;\'><strong>Credentials:</strong><br>Email: rhea@gmail.com<br>Password: rhea</div><p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\'>Login Now</a></p>', 'sent', 1, '2026-01-14 05:28:24', '2026-01-14 05:28:49'),
(15, 'nicole@gmail.com', 'Official Contestant Registration', '<h2>Welcome, Nicole Anne Villarin!</h2><p>You have been registered for <b>UEP Beauty Pageant</b> as Contestant #5.</p><div style=\'background:#f3f4f6; padding:15px;\'><strong>Credentials:</strong><br>Email: nicole@gmail.com<br>Password: nicole</div><p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\'>Login Now</a></p>', 'sent', 1, '2026-01-14 05:29:46', '2026-01-14 05:29:57'),
(16, 'patricia@gmail.com', 'Official Contestant Registration', '<h2>Welcome, Patricia Joy Alvero!</h2><p>You have been registered for <b>UEP Beauty Pageant</b> as Contestant #6.</p><div style=\'background:#f3f4f6; padding:15px;\'><strong>Credentials:</strong><br>Email: patricia@gmail.com<br>Password: patricia</div><p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\'>Login Now</a></p>', 'sent', 1, '2026-01-14 05:31:18', '2026-01-14 05:31:21'),
(17, 'hannah@gmail.com', 'Official Contestant Registration', '<h2>Welcome, Hannah Louise Perez!</h2><p>You have been registered for <b>UEP Beauty Pageant</b> as Contestant #7.</p><div style=\'background:#f3f4f6; padding:15px;\'><strong>Credentials:</strong><br>Email: hannah@gmail.com<br>Password: hannah</div><p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\'>Login Now</a></p>', 'sent', 1, '2026-01-14 05:32:34', '2026-01-14 05:32:38'),
(18, 'kimberly@gmail.com', 'Official Invitation: Judge for UEP Beauty Pageant', '\r\n            <h2>Hello, Kimberly Anne Rosales!</h2>\r\n            <p>You have been assigned as a Judge for <b>UEP Beauty Pageant</b>.</p>\r\n            <div style=\'background:#f3f4f6; padding:15px; border-radius:8px; margin:20px 0;\'>\r\n                <strong>Your Login Credentials:</strong><br>\r\n                Email: <b>kimberly@gmail.com</b><br>\r\n                Password: <b>kimberly</b>\r\n            </div>\r\n            <p><a href=\'https://juvenal-esteban-octavalent.ngrok-free.dev/bpms_v2/public/index.php\' style=\'background:#F59E0B; color:white; padding:10px 20px; text-decoration:none;\'>Login Now</a></p>\r\n        ', 'sent', 1, '2026-01-14 10:18:57', '2026-01-14 10:19:26');

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
(1, 1, 'Mutya San Old Rizal', '2026-06-12', 'Brgy. Old Rizal, Covered Court', 'Inactive', 0, '2026-01-11 20:31:22'),
(2, 1, 'UEP Beauty Pageant', '2026-06-20', 'University Town, Gymnatorium', 'Active', 0, '2026-01-13 03:22:36');

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
(4, 1, 'Runway Walk Coaching', 'Old Rizal, Covered Court', 'Coaching session for posture, walk, and turns', '2026-04-28', '09:00:00', '11:00:00', 'Active', 0, '2026-01-13 03:51:20'),
(5, 2, 'Contestant Orientation & Program Briefing', 'UEP Audio-Visual Room, Catarman', 'Introduction to rules, schedule, scoring system, and pageant expectations.', '2026-03-16', '09:00:00', '11:00:00', 'Active', 0, '2026-01-14 11:16:10'),
(6, 2, 'Advocacy Development Workshop', 'UEP College of Arts and Sciences Hall', 'Guidance on advocacy selection, message clarity, and public speaking.', '2026-03-17', '13:00:00', '16:00:00', 'Active', 0, '2026-01-14 11:20:07'),
(7, 2, 'Official Photoshoot', 'UEP Main Campus Grounds', 'Official photos for publicity, profiles, and judging materials.', '2026-03-19', '08:00:00', '12:00:00', 'Active', 0, '2026-01-14 11:21:07'),
(8, 2, 'Full Dress Rehearsal', 'UEP Gymnasium', 'Complete run-through of all segments before the pageant night.', '2026-03-23', '13:00:00', '18:00:00', 'Active', 0, '2026-01-14 11:22:42');

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
(2, 1, 11, 2, 22, 'Old Rizal', 'Grace under pressure defines true strength', 168.00, 33.0, 26.0, 35.0, 'contestant_1768237749.png', 'Eliminated', 0, '2026-01-12 17:09:09'),
(3, 1, 12, 3, 22, 'Old Rizal', 'Purpose gives Beauty its Power', 150.00, 34.0, 24.0, 34.0, 'contestant_1768238563.jpg', 'Qualified', 0, '2026-01-12 17:22:43'),
(4, 1, 13, 4, 20, 'Old Rizal', 'Elegance is a choice I make everyday', 157.00, 35.0, 25.0, 35.0, 'contestant_1768238666.jpg', 'Eliminated', 0, '2026-01-12 17:24:26'),
(5, 1, 14, 5, 23, 'Old Rizal', 'Growth begins when courage replaces fear', 169.00, 34.0, 26.0, 36.0, 'contestant_1768238793.jpg', 'Eliminated', 0, '2026-01-12 17:26:33'),
(6, 1, 15, 6, 19, 'Old Rizal', 'Strength is showing up as yourself', 166.00, 32.0, 24.0, 35.0, 'contestant_1768238882.jpg', 'Eliminated', 0, '2026-01-12 17:28:02'),
(7, 1, 16, 7, 21, 'Old Rizal', 'Authenticity is my Greatest advantage', 168.00, 33.0, 25.0, 36.0, 'contestant_1768238990.jpg', 'Eliminated', 0, '2026-01-12 17:29:50'),
(8, 1, 18, 8, 22, 'Old Rizal', 'Grace is power expressed softly', 171.00, 34.0, 26.0, 37.0, 'contestant_1768239227.jpg', 'Eliminated', 0, '2026-01-12 17:33:47'),
(9, 2, 19, 1, 22, 'Old Rizal', 'Life is Short', 170.00, 36.0, 24.0, 36.0, 'contestant_1768326763.jpg', 'Eliminated', 0, '2026-01-13 17:52:43'),
(10, 2, 24, 2, 20, 'UEP, Catarman N. Samar', 'Confidence begins with self-belief', 168.00, 34.0, 25.0, 36.0, 'contestant_1768367888.jpg', 'Eliminated', 0, '2026-01-14 05:18:08'),
(11, 2, 25, 3, 21, 'UEP, Catarman N. Samar', 'Grace is strength in motion', 170.00, 33.0, 26.0, 35.0, 'contestant_1768368432.jpg', 'Eliminated', 0, '2026-01-14 05:27:12'),
(12, 2, 26, 4, 22, 'UEP, Catarman N. Samar', 'Purpose gives beauty meaning', 167.00, 34.0, 25.0, 36.0, 'contestant_1768368504.jpg', 'Eliminated', 0, '2026-01-14 05:28:24'),
(13, 2, 27, 5, 19, 'UEP, Catarman N. Samar', 'Be bold, be kind, be real', 165.00, 32.0, 24.0, 35.0, 'contestant_1768368586.jpg', 'Eliminated', 0, '2026-01-14 05:29:46'),
(14, 2, 28, 6, 23, 'UEP, Catarman N. Samar', 'Discipline turns dreams into results', 172.00, 35.0, 26.0, 37.0, 'contestant_1768368677.jpg', 'Eliminated', 0, '2026-01-14 05:31:18'),
(15, 2, 29, 7, 21, 'UEP, Catarman N. Samar', 'Elegance is earned, not worn', 169.00, 34.0, 25.0, 36.0, 'contestant_1768368753.jpg', 'Eliminated', 0, '2026-01-14 05:32:34'),
(16, 2, 30, 8, 21, 'UEP, Catarman N. Samar', 'Stand firm, shine bright', 163.00, 35.0, 22.0, 33.0, 'contestant_1768369081.jpg', 'Qualified', 0, '2026-01-14 05:38:01'),
(17, 2, 31, 9, 26, 'Bobon, Northern Samar', 'Life is an Adventure', 175.00, 36.0, 23.0, 37.0, 'contestant_1768369612.jpg', 'Rejected', 0, '2026-01-14 05:46:53');

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
(5, 1, 9, 0, 'Active', 0, '2026-01-12 05:47:27'),
(6, 2, 32, 1, 'Active', 0, '2026-01-14 09:48:11'),
(7, 2, 33, 0, 'Active', 0, '2026-01-14 09:49:01'),
(8, 2, 34, 0, 'Active', 0, '2026-01-14 09:50:21'),
(9, 2, 35, 0, 'Active', 0, '2026-01-14 09:58:10'),
(10, 2, 36, 0, 'Inactive', 0, '2026-01-14 10:18:57');

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
(3, 1, 4, 'Tabulator', 'Active', 0, '2026-01-12 02:40:38'),
(4, 2, 20, 'Judge Coordinator', 'Active', 0, '2026-01-14 05:13:05'),
(5, 2, 21, 'Contestant Manager', 'Active', 0, '2026-01-14 05:13:53'),
(6, 2, 22, 'Tabulator', 'Active', 0, '2026-01-14 05:15:04'),
(7, 2, 23, 'Judge Coordinator', 'Inactive', 0, '2026-01-14 05:16:15');

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
(293, 5, 9, 8, '', '2026-01-13 12:31:48', NULL),
(295, 4, 5, 2, '', '2026-01-13 15:43:24', NULL),
(301, 4, 5, 3, '', '2026-01-13 15:43:44', NULL),
(309, 4, 5, 6, '', '2026-01-13 15:44:11', NULL),
(312, 4, 5, 7, '', '2026-01-13 15:44:16', NULL),
(315, 4, 5, 8, '', '2026-01-13 15:44:21', NULL),
(319, 5, 5, 2, '', '2026-01-13 15:44:37', NULL),
(320, 5, 5, 6, '', '2026-01-13 15:44:42', NULL),
(326, 5, 5, 3, '', '2026-01-13 15:45:00', NULL),
(329, 5, 5, 7, '', '2026-01-13 15:45:04', NULL),
(332, 5, 5, 8, '', '2026-01-13 15:45:09', NULL),
(336, 4, 6, 2, '', '2026-01-13 15:54:28', NULL),
(340, 4, 6, 3, '', '2026-01-13 15:54:39', NULL),
(344, 4, 6, 6, '', '2026-01-13 15:54:47', NULL),
(346, 4, 6, 7, '', '2026-01-13 15:54:51', NULL),
(348, 4, 6, 8, '', '2026-01-13 15:54:55', NULL),
(350, 5, 6, 2, '', '2026-01-13 15:55:00', NULL),
(351, 5, 6, 3, '', '2026-01-13 15:55:03', NULL),
(353, 5, 6, 6, '', '2026-01-13 15:55:07', NULL),
(355, 5, 6, 7, '', '2026-01-13 15:55:11', NULL),
(359, 5, 6, 8, '', '2026-01-13 15:55:19', NULL),
(361, 4, 7, 2, '', '2026-01-13 15:56:14', NULL),
(368, 4, 7, 3, '', '2026-01-13 15:56:32', NULL),
(372, 4, 7, 6, '', '2026-01-13 15:56:39', NULL),
(374, 4, 7, 7, '', '2026-01-13 15:56:43', NULL),
(378, 4, 7, 8, '', '2026-01-13 15:56:50', NULL),
(380, 5, 7, 2, '', '2026-01-13 15:56:56', NULL),
(383, 5, 7, 3, '', '2026-01-13 15:57:00', NULL),
(386, 5, 7, 6, '', '2026-01-13 15:57:04', NULL),
(388, 5, 7, 7, '', '2026-01-13 15:57:08', NULL),
(390, 5, 7, 8, '', '2026-01-13 15:57:12', NULL),
(392, 4, 8, 2, '', '2026-01-13 15:57:59', NULL),
(394, 4, 8, 3, '', '2026-01-13 15:58:02', NULL),
(396, 4, 8, 6, '', '2026-01-13 15:58:06', NULL),
(398, 4, 8, 7, '', '2026-01-13 15:58:10', NULL),
(400, 4, 8, 8, '', '2026-01-13 15:58:13', NULL),
(402, 5, 8, 2, '', '2026-01-13 15:58:17', NULL),
(426, 5, 8, 3, '', '2026-01-13 16:00:07', NULL),
(427, 5, 8, 6, '', '2026-01-13 16:00:11', NULL),
(429, 5, 8, 7, '', '2026-01-13 16:00:14', NULL),
(430, 5, 8, 8, '', '2026-01-13 16:00:17', NULL),
(432, 6, 8, 2, '', '2026-01-13 16:04:34', NULL),
(435, 6, 8, 3, '', '2026-01-13 16:04:44', NULL),
(437, 6, 8, 6, '', '2026-01-13 16:04:49', NULL),
(440, 7, 8, 2, '', '2026-01-13 16:04:57', NULL),
(443, 7, 8, 3, '', '2026-01-13 16:05:01', NULL),
(447, 7, 8, 6, '', '2026-01-13 16:05:09', NULL),
(449, 6, 5, 2, '', '2026-01-13 16:06:12', NULL),
(450, 6, 5, 3, '', '2026-01-13 16:06:21', NULL),
(452, 6, 5, 6, '', '2026-01-13 16:06:25', NULL),
(454, 7, 5, 2, '', '2026-01-13 16:06:36', NULL),
(456, 7, 5, 3, '', '2026-01-13 16:06:41', NULL),
(460, 7, 5, 6, '', '2026-01-13 16:06:46', NULL),
(462, 6, 6, 2, '', '2026-01-13 16:07:25', NULL),
(465, 6, 6, 3, '', '2026-01-13 16:07:29', NULL),
(468, 6, 6, 6, '', '2026-01-13 16:07:35', NULL),
(470, 7, 6, 2, '', '2026-01-13 16:07:38', NULL),
(472, 7, 6, 3, '', '2026-01-13 16:07:47', NULL),
(476, 7, 6, 6, '', '2026-01-13 16:07:53', NULL),
(477, 6, 7, 2, '', '2026-01-13 16:08:22', NULL),
(479, 6, 7, 3, '', '2026-01-13 16:08:25', NULL),
(482, 6, 7, 6, '', '2026-01-13 16:08:30', NULL),
(484, 7, 7, 2, '', '2026-01-13 16:08:36', NULL),
(485, 7, 7, 3, '', '2026-01-13 16:08:37', NULL),
(490, 7, 7, 6, '', '2026-01-13 16:08:48', NULL),
(492, 6, 9, 2, '', '2026-01-13 16:09:42', NULL),
(494, 6, 9, 3, '', '2026-01-13 16:09:49', NULL),
(496, 6, 9, 6, '', '2026-01-13 16:09:53', NULL),
(498, 7, 9, 2, '', '2026-01-13 16:09:59', NULL),
(501, 7, 9, 3, '', '2026-01-13 16:10:02', NULL),
(504, 7, 9, 6, '', '2026-01-13 16:10:09', NULL),
(506, 8, 32, 9, '', '2026-01-14 14:50:35', NULL),
(517, 8, 32, 10, '', '2026-01-14 14:57:33', NULL),
(520, 8, 32, 11, '', '2026-01-14 14:57:38', NULL),
(523, 8, 32, 12, '', '2026-01-14 14:57:44', NULL),
(525, 8, 32, 13, '', '2026-01-14 14:57:49', NULL),
(527, 8, 32, 14, '', '2026-01-14 14:57:52', NULL),
(528, 8, 32, 15, '', '2026-01-14 14:57:56', NULL),
(529, 8, 32, 16, '', '2026-01-14 14:58:00', NULL),
(531, 8, 33, 9, '', '2026-01-14 14:59:34', NULL),
(533, 8, 33, 10, '', '2026-01-14 14:59:38', NULL),
(536, 8, 33, 11, '', '2026-01-14 14:59:43', NULL),
(539, 8, 33, 12, '', '2026-01-14 14:59:47', NULL),
(542, 8, 33, 13, '', '2026-01-14 14:59:53', NULL),
(544, 8, 33, 14, '', '2026-01-14 14:59:57', NULL),
(546, 8, 33, 16, '', '2026-01-14 15:00:02', NULL),
(548, 8, 33, 15, '', '2026-01-14 15:00:06', NULL),
(551, 8, 34, 9, '', '2026-01-14 15:17:24', NULL),
(557, 8, 34, 10, '', '2026-01-14 15:17:43', NULL),
(560, 8, 34, 11, '', '2026-01-14 15:17:50', NULL),
(562, 8, 34, 12, '', '2026-01-14 15:17:54', NULL),
(564, 8, 34, 13, '', '2026-01-14 15:17:59', NULL),
(565, 8, 34, 14, '', '2026-01-14 15:18:02', NULL),
(568, 8, 34, 15, '', '2026-01-14 15:18:07', NULL),
(569, 8, 34, 16, '', '2026-01-14 15:18:10', NULL),
(572, 8, 35, 9, '', '2026-01-14 15:20:11', NULL),
(574, 8, 35, 10, '', '2026-01-14 15:20:21', NULL),
(576, 8, 35, 11, '', '2026-01-14 15:20:24', NULL),
(578, 8, 35, 12, '', '2026-01-14 15:20:27', NULL),
(581, 8, 35, 13, '', '2026-01-14 15:20:33', NULL),
(582, 8, 35, 14, '', '2026-01-14 15:20:36', NULL),
(584, 8, 35, 15, '', '2026-01-14 15:20:40', NULL),
(586, 8, 35, 16, '', '2026-01-14 15:20:45', NULL),
(589, 9, 32, 10, '', '2026-01-14 15:41:39', NULL),
(591, 9, 32, 11, '', '2026-01-14 15:41:49', NULL),
(593, 9, 32, 12, '', '2026-01-14 15:41:53', NULL),
(595, 9, 32, 13, '', '2026-01-14 15:41:56', NULL),
(597, 9, 32, 14, '', '2026-01-14 15:41:59', NULL),
(599, 9, 32, 16, '', '2026-01-14 15:42:04', NULL),
(602, 10, 32, 10, '', '2026-01-14 15:42:17', NULL),
(604, 10, 32, 11, '', '2026-01-14 15:42:21', NULL),
(607, 10, 32, 12, '', '2026-01-14 15:42:25', NULL),
(609, 10, 32, 13, '', '2026-01-14 15:42:29', NULL),
(613, 10, 32, 14, '', '2026-01-14 15:42:34', NULL),
(615, 10, 32, 16, '', '2026-01-14 15:42:39', NULL),
(616, 9, 33, 10, '', '2026-01-14 15:43:49', NULL),
(618, 9, 33, 11, '', '2026-01-14 15:43:51', NULL),
(621, 9, 33, 12, '', '2026-01-14 15:43:57', NULL),
(622, 9, 33, 13, '', '2026-01-14 15:44:00', NULL),
(623, 9, 33, 14, '', '2026-01-14 15:44:04', NULL),
(624, 9, 33, 16, '', '2026-01-14 15:44:06', NULL),
(627, 10, 33, 10, '', '2026-01-14 15:44:11', NULL),
(629, 10, 33, 11, '', '2026-01-14 15:44:17', NULL),
(632, 10, 33, 12, '', '2026-01-14 15:44:22', NULL),
(634, 10, 33, 13, '', '2026-01-14 15:44:26', NULL),
(637, 10, 33, 14, '', '2026-01-14 15:44:33', NULL),
(639, 10, 33, 16, '', '2026-01-14 15:44:37', NULL),
(642, 9, 34, 10, '', '2026-01-14 15:45:32', NULL),
(644, 9, 34, 11, '', '2026-01-14 15:45:35', NULL),
(647, 9, 34, 12, '', '2026-01-14 15:45:40', NULL),
(649, 9, 34, 13, '', '2026-01-14 15:45:43', NULL),
(652, 9, 34, 14, '', '2026-01-14 15:45:49', NULL),
(653, 9, 34, 16, '', '2026-01-14 15:45:52', NULL),
(655, 10, 34, 12, '', '2026-01-14 15:46:02', NULL),
(656, 10, 34, 10, '', '2026-01-14 15:46:06', NULL),
(657, 10, 34, 11, '', '2026-01-14 15:46:09', NULL),
(658, 10, 34, 13, '', '2026-01-14 15:46:13', NULL),
(659, 10, 34, 14, '', '2026-01-14 15:46:16', NULL),
(660, 10, 34, 16, '', '2026-01-14 15:46:19', NULL),
(661, 9, 35, 10, '', '2026-01-14 15:46:53', NULL),
(663, 9, 35, 11, '', '2026-01-14 15:46:59', NULL),
(665, 9, 35, 12, '', '2026-01-14 15:47:03', NULL),
(667, 9, 35, 13, '', '2026-01-14 15:47:08', NULL),
(668, 9, 35, 14, '', '2026-01-14 15:47:11', NULL),
(670, 9, 35, 16, '', '2026-01-14 15:47:15', NULL),
(672, 10, 35, 16, '', '2026-01-14 15:47:21', NULL),
(673, 10, 35, 14, '', '2026-01-14 15:47:25', NULL),
(675, 10, 35, 13, '', '2026-01-14 15:47:29', NULL),
(676, 10, 35, 12, '', '2026-01-14 15:47:32', NULL),
(677, 10, 35, 11, '', '2026-01-14 15:47:35', NULL),
(679, 10, 35, 10, '', '2026-01-14 15:47:40', NULL),
(680, 11, 35, 16, '', '2026-01-14 15:49:05', NULL),
(682, 11, 35, 12, '', '2026-01-14 15:49:09', NULL),
(683, 11, 35, 14, '', '2026-01-14 15:49:12', NULL),
(684, 12, 35, 12, '', '2026-01-14 15:49:25', NULL),
(685, 12, 35, 14, '', '2026-01-14 15:49:29', NULL),
(686, 12, 35, 16, '', '2026-01-14 15:49:32', NULL),
(687, 13, 35, 12, '', '2026-01-14 15:49:38', NULL),
(688, 13, 35, 14, '', '2026-01-14 15:49:41', NULL),
(689, 13, 35, 16, '', '2026-01-14 15:49:44', NULL),
(691, 11, 34, 12, '', '2026-01-14 15:50:38', NULL),
(693, 11, 34, 14, '', '2026-01-14 15:50:42', NULL),
(694, 11, 34, 16, '', '2026-01-14 15:50:45', NULL),
(696, 12, 34, 12, '', '2026-01-14 15:50:55', NULL),
(697, 12, 34, 14, '', '2026-01-14 15:50:58', NULL),
(698, 12, 34, 16, '', '2026-01-14 15:51:01', NULL),
(700, 13, 34, 12, '', '2026-01-14 15:51:06', NULL),
(701, 13, 34, 14, '', '2026-01-14 15:51:09', NULL),
(702, 13, 34, 16, '', '2026-01-14 15:51:13', NULL),
(703, 11, 33, 12, '', '2026-01-14 15:51:46', NULL),
(705, 11, 33, 14, '', '2026-01-14 15:51:50', NULL),
(706, 11, 33, 16, '', '2026-01-14 15:51:54', NULL),
(707, 12, 33, 12, '', '2026-01-14 15:52:05', NULL),
(709, 12, 33, 14, '', '2026-01-14 15:52:08', NULL),
(711, 12, 33, 16, '', '2026-01-14 15:52:15', NULL),
(713, 13, 33, 12, '', '2026-01-14 15:52:19', NULL),
(715, 13, 33, 14, '', '2026-01-14 15:52:24', NULL),
(716, 13, 33, 16, '', '2026-01-14 15:52:27', NULL),
(718, 11, 32, 12, '', '2026-01-14 15:53:10', NULL),
(720, 11, 32, 14, '', '2026-01-14 15:53:14', NULL),
(721, 11, 32, 16, '', '2026-01-14 15:53:16', NULL),
(724, 12, 32, 12, '', '2026-01-14 15:53:25', NULL),
(725, 12, 32, 14, '', '2026-01-14 15:53:28', NULL),
(726, 12, 32, 16, '', '2026-01-14 15:53:31', NULL),
(727, 13, 32, 12, '', '2026-01-14 15:53:35', NULL),
(729, 13, 32, 14, '', '2026-01-14 15:53:40', NULL),
(731, 13, 32, 16, '', '2026-01-14 15:53:44', NULL);

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
(10, 2, 9, 'Submitted', '2026-01-13 12:32:00', NULL),
(11, 2, 5, 'Submitted', '2026-01-13 15:45:40', NULL),
(12, 2, 6, 'Submitted', '2026-01-13 15:55:21', NULL),
(13, 2, 7, 'Submitted', '2026-01-13 15:57:21', NULL),
(14, 2, 8, 'Submitted', '2026-01-13 16:00:55', NULL),
(15, 3, 8, 'Submitted', '2026-01-13 16:05:14', NULL),
(16, 3, 5, 'Submitted', '2026-01-13 16:06:58', NULL),
(17, 3, 6, 'Submitted', '2026-01-13 16:08:00', NULL),
(18, 3, 7, 'Submitted', '2026-01-13 16:08:53', NULL),
(19, 3, 9, 'Submitted', '2026-01-13 16:10:20', NULL),
(20, 4, 32, 'Submitted', '2026-01-14 14:58:23', NULL),
(21, 4, 33, 'Submitted', '2026-01-14 15:00:41', NULL),
(22, 4, 34, 'Submitted', '2026-01-14 15:18:20', NULL),
(23, 4, 35, 'Submitted', '2026-01-14 15:20:50', NULL),
(24, 5, 32, 'Submitted', '2026-01-14 15:42:51', NULL),
(25, 5, 33, 'Submitted', '2026-01-14 15:44:47', NULL),
(26, 5, 34, 'Submitted', '2026-01-14 15:46:24', NULL),
(27, 5, 35, 'Submitted', '2026-01-14 15:47:45', NULL),
(28, 6, 35, 'Submitted', '2026-01-14 15:49:53', NULL),
(29, 6, 34, 'Submitted', '2026-01-14 15:51:18', NULL),
(30, 6, 33, 'Submitted', '2026-01-14 15:52:33', NULL),
(31, 6, 32, 'Submitted', '2026-01-14 15:53:51', NULL);

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
(2, 1, 'Semi-Final Round', 2, 'Elimination', 3, 'Completed', 0, '2026-01-12 18:11:42'),
(3, 1, 'Final Round', 3, 'Final', 1, 'Completed', 0, '2026-01-12 18:11:54'),
(4, 2, 'Preliminary Round', 1, 'Elimination', 6, 'Completed', 0, '2026-01-14 10:27:51'),
(5, 2, 'Semi-Final Round', 2, 'Elimination', 3, 'Completed', 0, '2026-01-14 10:28:42'),
(6, 2, 'Final Round', 3, 'Final', 1, 'Completed', 0, '2026-01-14 10:35:04');

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
(8, 1, 4, 86.40, 8),
(9, 2, 3, 86.90, 1),
(10, 2, 2, 83.50, 2),
(11, 2, 6, 76.50, 3),
(12, 2, 7, 74.10, 4),
(13, 2, 8, 72.30, 5),
(14, 3, 3, 88.30, 1),
(15, 3, 2, 81.40, 2),
(16, 3, 6, 80.70, 3),
(17, 4, 16, 91.75, 1),
(18, 4, 12, 89.75, 2),
(19, 4, 10, 87.00, 3),
(20, 4, 14, 86.25, 4),
(21, 4, 13, 85.50, 5),
(22, 4, 11, 81.00, 6),
(23, 4, 9, 80.25, 7),
(24, 4, 15, 80.00, 8),
(25, 5, 16, 94.00, 1),
(26, 5, 14, 87.88, 2),
(27, 5, 12, 83.63, 3),
(28, 5, 11, 81.63, 4),
(29, 5, 10, 80.00, 5),
(30, 5, 13, 79.63, 6),
(31, 6, 16, 90.33, 1),
(32, 6, 14, 83.38, 2),
(33, 6, 12, 82.28, 3);

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
(753, 9, 9, 8, 21.00, '2026-01-13 12:31:48', NULL),
(760, 4, 5, 2, 34.00, '2026-01-13 15:43:24', '2026-01-13 15:43:33'),
(761, 5, 5, 2, 26.00, '2026-01-13 15:43:24', '2026-01-13 15:43:35'),
(762, 6, 5, 2, 28.00, '2026-01-13 15:43:24', '2026-01-13 15:43:37'),
(778, 4, 5, 3, 27.00, '2026-01-13 15:43:44', '2026-01-13 15:43:57'),
(779, 5, 5, 3, 27.00, '2026-01-13 15:43:44', '2026-01-13 15:43:58'),
(780, 6, 5, 3, 28.00, '2026-01-13 15:43:44', '2026-01-13 15:44:00'),
(802, 4, 5, 6, 30.00, '2026-01-13 15:44:11', '2026-01-13 15:44:12'),
(803, 5, 5, 6, 26.00, '2026-01-13 15:44:11', NULL),
(804, 6, 5, 6, 28.00, '2026-01-13 15:44:11', NULL),
(811, 4, 5, 7, 30.00, '2026-01-13 15:44:16', '2026-01-13 15:44:17'),
(812, 5, 5, 7, 28.00, '2026-01-13 15:44:16', NULL),
(813, 6, 5, 7, 23.00, '2026-01-13 15:44:16', NULL),
(820, 4, 5, 8, 23.00, '2026-01-13 15:44:21', NULL),
(821, 5, 5, 8, 26.00, '2026-01-13 15:44:21', NULL),
(822, 6, 5, 8, 30.00, '2026-01-13 15:44:21', '2026-01-13 15:44:23'),
(835, 7, 5, 2, 26.00, '2026-01-13 15:44:37', '2026-01-13 15:44:53'),
(836, 8, 5, 2, 26.00, '2026-01-13 15:44:37', '2026-01-13 15:44:52'),
(837, 9, 5, 2, 19.00, '2026-01-13 15:44:37', '2026-01-13 15:44:52'),
(841, 7, 5, 6, 37.00, '2026-01-13 15:44:42', NULL),
(842, 8, 5, 6, 29.00, '2026-01-13 15:44:42', '2026-01-13 15:44:44'),
(843, 9, 5, 6, 21.00, '2026-01-13 15:44:42', '2026-01-13 15:44:44'),
(877, 7, 5, 3, 35.00, '2026-01-13 15:45:00', NULL),
(878, 8, 5, 3, 31.00, '2026-01-13 15:45:00', '2026-01-13 15:45:31'),
(879, 9, 5, 3, 24.00, '2026-01-13 15:45:00', '2026-01-13 15:45:01'),
(895, 7, 5, 7, 28.00, '2026-01-13 15:45:04', '2026-01-13 15:45:05'),
(896, 8, 5, 7, 30.00, '2026-01-13 15:45:04', NULL),
(897, 9, 5, 7, 24.00, '2026-01-13 15:45:04', NULL),
(913, 7, 5, 8, 29.00, '2026-01-13 15:45:09', NULL),
(914, 8, 5, 8, 31.00, '2026-01-13 15:45:09', NULL),
(915, 9, 5, 8, 22.00, '2026-01-13 15:45:09', NULL),
(934, 4, 6, 2, 36.00, '2026-01-13 15:54:28', NULL),
(935, 5, 6, 2, 18.00, '2026-01-13 15:54:28', '2026-01-13 15:54:29'),
(936, 6, 6, 2, 22.00, '2026-01-13 15:54:28', '2026-01-13 15:54:30'),
(946, 4, 6, 3, 26.00, '2026-01-13 15:54:39', NULL),
(947, 5, 6, 3, 25.00, '2026-01-13 15:54:39', '2026-01-13 15:54:42'),
(948, 6, 6, 3, 29.00, '2026-01-13 15:54:39', '2026-01-13 15:54:40'),
(958, 4, 6, 6, 34.00, '2026-01-13 15:54:47', NULL),
(959, 5, 6, 6, 29.00, '2026-01-13 15:54:47', NULL),
(960, 6, 6, 6, 29.00, '2026-01-13 15:54:47', NULL),
(964, 4, 6, 7, 28.00, '2026-01-13 15:54:51', NULL),
(965, 5, 6, 7, 17.00, '2026-01-13 15:54:51', NULL),
(966, 6, 6, 7, 20.00, '2026-01-13 15:54:51', NULL),
(970, 4, 6, 8, 31.00, '2026-01-13 15:54:55', NULL),
(971, 5, 6, 8, 21.00, '2026-01-13 15:54:55', NULL),
(972, 6, 6, 8, 21.00, '2026-01-13 15:54:55', NULL),
(979, 7, 6, 2, 38.00, '2026-01-13 15:55:00', NULL),
(980, 8, 6, 2, 30.00, '2026-01-13 15:55:00', NULL),
(981, 9, 6, 2, 22.00, '2026-01-13 15:55:00', NULL),
(985, 7, 6, 3, 38.00, '2026-01-13 15:55:03', NULL),
(986, 8, 6, 3, 33.00, '2026-01-13 15:55:03', NULL),
(987, 9, 6, 3, 24.00, '2026-01-13 15:55:03', NULL),
(997, 7, 6, 6, 34.00, '2026-01-13 15:55:07', NULL),
(998, 8, 6, 6, 32.00, '2026-01-13 15:55:07', NULL),
(999, 9, 6, 6, 23.00, '2026-01-13 15:55:07', NULL),
(1009, 7, 6, 7, 34.00, '2026-01-13 15:55:11', '2026-01-13 15:55:16'),
(1010, 8, 6, 7, 25.00, '2026-01-13 15:55:11', '2026-01-13 15:55:16'),
(1011, 9, 6, 7, 18.00, '2026-01-13 15:55:11', '2026-01-13 15:55:16'),
(1033, 7, 6, 8, 34.00, '2026-01-13 15:55:19', '2026-01-13 15:55:19'),
(1034, 8, 6, 8, 22.00, '2026-01-13 15:55:19', NULL),
(1035, 9, 6, 8, 22.00, '2026-01-13 15:55:19', NULL),
(1042, 4, 7, 2, 38.00, '2026-01-13 15:56:14', '2026-01-13 15:56:29'),
(1043, 5, 7, 2, 30.00, '2026-01-13 15:56:14', '2026-01-13 15:56:19'),
(1044, 6, 7, 2, 28.00, '2026-01-13 15:56:14', '2026-01-13 15:56:22'),
(1063, 4, 7, 3, 38.00, '2026-01-13 15:56:32', NULL),
(1064, 5, 7, 3, 27.00, '2026-01-13 15:56:32', '2026-01-13 15:56:33'),
(1065, 6, 7, 3, 29.00, '2026-01-13 15:56:32', '2026-01-13 15:56:34'),
(1075, 4, 7, 6, 27.00, '2026-01-13 15:56:39', NULL),
(1076, 5, 7, 6, 21.00, '2026-01-13 15:56:39', NULL),
(1077, 6, 7, 6, 22.00, '2026-01-13 15:56:39', NULL),
(1081, 4, 7, 7, 26.00, '2026-01-13 15:56:43', '2026-01-13 15:56:44'),
(1082, 5, 7, 7, 19.00, '2026-01-13 15:56:43', NULL),
(1083, 6, 7, 7, 15.00, '2026-01-13 15:56:43', '2026-01-13 15:56:46'),
(1093, 4, 7, 8, 29.00, '2026-01-13 15:56:50', NULL),
(1094, 5, 7, 8, 19.00, '2026-01-13 15:56:50', NULL),
(1095, 6, 7, 8, 23.00, '2026-01-13 15:56:50', NULL),
(1102, 7, 7, 2, 34.00, '2026-01-13 15:56:56', NULL),
(1103, 8, 7, 2, 32.00, '2026-01-13 15:56:56', NULL),
(1104, 9, 7, 2, 21.00, '2026-01-13 15:56:56', '2026-01-13 15:56:57'),
(1120, 7, 7, 3, 38.00, '2026-01-13 15:57:00', NULL),
(1121, 8, 7, 3, 29.00, '2026-01-13 15:57:00', NULL),
(1122, 9, 7, 3, 22.00, '2026-01-13 15:57:00', '2026-01-13 15:57:01'),
(1138, 7, 7, 6, 28.00, '2026-01-13 15:57:04', '2026-01-13 15:57:05'),
(1139, 8, 7, 6, 20.00, '2026-01-13 15:57:04', NULL),
(1140, 9, 7, 6, 13.00, '2026-01-13 15:57:04', NULL),
(1150, 7, 7, 7, 32.00, '2026-01-13 15:57:08', NULL),
(1151, 8, 7, 7, 20.00, '2026-01-13 15:57:08', NULL),
(1152, 9, 7, 7, 16.00, '2026-01-13 15:57:08', NULL),
(1162, 7, 7, 8, 28.00, '2026-01-13 15:57:12', NULL),
(1163, 8, 7, 8, 29.00, '2026-01-13 15:57:12', NULL),
(1164, 9, 7, 8, 17.00, '2026-01-13 15:57:12', NULL),
(1171, 4, 8, 2, 39.00, '2026-01-13 15:57:59', '2026-01-13 15:59:33'),
(1172, 5, 8, 2, 29.00, '2026-01-13 15:57:59', '2026-01-13 15:59:33'),
(1173, 6, 8, 2, 30.00, '2026-01-13 15:57:59', '2026-01-13 15:59:34'),
(1177, 4, 8, 3, 37.00, '2026-01-13 15:58:02', '2026-01-13 15:59:38'),
(1178, 5, 8, 3, 28.00, '2026-01-13 15:58:02', '2026-01-13 15:59:38'),
(1179, 6, 8, 3, 29.00, '2026-01-13 15:58:02', '2026-01-13 15:59:38'),
(1183, 4, 8, 6, 25.00, '2026-01-13 15:58:06', '2026-01-13 15:59:42'),
(1184, 5, 8, 6, 15.00, '2026-01-13 15:58:06', '2026-01-13 15:59:40'),
(1185, 6, 8, 6, 17.00, '2026-01-13 15:58:06', '2026-01-13 15:59:42'),
(1189, 4, 8, 7, 29.00, '2026-01-13 15:58:10', '2026-01-13 15:59:45'),
(1190, 5, 8, 7, 26.00, '2026-01-13 15:58:10', '2026-01-13 15:59:45'),
(1191, 6, 8, 7, 26.00, '2026-01-13 15:58:10', '2026-01-13 15:59:45'),
(1195, 4, 8, 8, 23.00, '2026-01-13 15:58:13', '2026-01-13 15:59:50'),
(1196, 5, 8, 8, 22.00, '2026-01-13 15:58:13', '2026-01-13 15:59:49'),
(1197, 6, 8, 8, 23.00, '2026-01-13 15:58:13', '2026-01-13 15:59:49'),
(1204, 7, 8, 2, 38.00, '2026-01-13 15:58:17', '2026-01-13 16:00:02'),
(1205, 8, 8, 2, 32.00, '2026-01-13 15:58:17', '2026-01-13 16:00:00'),
(1206, 9, 8, 2, 23.00, '2026-01-13 15:58:17', '2026-01-13 16:00:02'),
(1303, 7, 8, 3, 34.00, '2026-01-13 16:00:07', NULL),
(1304, 8, 8, 3, 29.00, '2026-01-13 16:00:07', NULL),
(1305, 9, 8, 3, 21.00, '2026-01-13 16:00:07', NULL),
(1309, 7, 8, 6, 29.00, '2026-01-13 16:00:11', NULL),
(1310, 8, 8, 6, 18.00, '2026-01-13 16:00:11', NULL),
(1311, 9, 8, 6, 17.00, '2026-01-13 16:00:11', NULL),
(1321, 7, 8, 7, 33.00, '2026-01-13 16:00:14', NULL),
(1322, 8, 8, 7, 19.00, '2026-01-13 16:00:14', NULL),
(1323, 9, 8, 7, 15.00, '2026-01-13 16:00:14', NULL),
(1327, 7, 8, 8, 19.00, '2026-01-13 16:00:17', NULL),
(1328, 8, 8, 8, 22.00, '2026-01-13 16:00:17', NULL),
(1329, 9, 8, 8, 17.00, '2026-01-13 16:00:17', NULL),
(1336, 10, 8, 2, 25.00, '2026-01-13 16:04:34', NULL),
(1337, 11, 8, 2, 26.00, '2026-01-13 16:04:34', NULL),
(1338, 12, 8, 2, 34.00, '2026-01-13 16:04:34', '2026-01-13 16:04:35'),
(1345, 10, 8, 3, 30.00, '2026-01-13 16:04:44', NULL),
(1346, 11, 8, 3, 30.00, '2026-01-13 16:04:44', NULL),
(1347, 12, 8, 3, 40.00, '2026-01-13 16:04:44', NULL),
(1351, 10, 8, 6, 22.00, '2026-01-13 16:04:49', NULL),
(1352, 11, 8, 6, 26.00, '2026-01-13 16:04:49', NULL),
(1353, 12, 8, 6, 33.00, '2026-01-13 16:04:49', '2026-01-13 16:04:50'),
(1363, 13, 8, 2, 46.00, '2026-01-13 16:04:57', NULL),
(1364, 14, 8, 2, 22.00, '2026-01-13 16:04:57', NULL),
(1365, 15, 8, 2, 23.00, '2026-01-13 16:04:57', '2026-01-13 16:04:57'),
(1381, 13, 8, 3, 50.00, '2026-01-13 16:05:01', NULL),
(1382, 14, 8, 3, 23.00, '2026-01-13 16:05:01', '2026-01-13 16:05:02'),
(1383, 15, 8, 3, 23.00, '2026-01-13 16:05:01', '2026-01-13 16:05:03'),
(1405, 13, 8, 6, 43.00, '2026-01-13 16:05:09', NULL),
(1406, 14, 8, 6, 21.00, '2026-01-13 16:05:09', NULL),
(1407, 15, 8, 6, 23.00, '2026-01-13 16:05:09', NULL),
(1414, 10, 5, 2, 24.00, '2026-01-13 16:06:12', NULL),
(1415, 11, 5, 2, 26.00, '2026-01-13 16:06:12', NULL),
(1416, 12, 5, 2, 31.00, '2026-01-13 16:06:12', NULL),
(1417, 10, 5, 3, 28.00, '2026-01-13 16:06:21', NULL),
(1418, 11, 5, 3, 26.00, '2026-01-13 16:06:21', NULL),
(1419, 12, 5, 3, 37.00, '2026-01-13 16:06:21', NULL),
(1423, 10, 5, 6, 22.00, '2026-01-13 16:06:25', NULL),
(1424, 11, 5, 6, 30.00, '2026-01-13 16:06:25', NULL),
(1425, 12, 5, 6, 40.00, '2026-01-13 16:06:25', NULL),
(1432, 13, 5, 2, 42.00, '2026-01-13 16:06:36', NULL),
(1433, 14, 5, 2, 22.00, '2026-01-13 16:06:36', NULL),
(1434, 15, 5, 2, 20.00, '2026-01-13 16:06:36', NULL),
(1444, 13, 5, 3, 46.00, '2026-01-13 16:06:41', NULL),
(1445, 14, 5, 3, 22.00, '2026-01-13 16:06:41', '2026-01-13 16:06:42'),
(1446, 15, 5, 3, 25.00, '2026-01-13 16:06:41', '2026-01-13 16:06:43'),
(1468, 13, 5, 6, 36.00, '2026-01-13 16:06:46', NULL),
(1469, 14, 5, 6, 20.00, '2026-01-13 16:06:46', '2026-01-13 16:06:49'),
(1470, 15, 5, 6, 25.00, '2026-01-13 16:06:46', '2026-01-13 16:06:49'),
(1477, 10, 6, 2, 24.00, '2026-01-13 16:07:25', '2026-01-13 16:07:26'),
(1478, 11, 6, 2, 24.00, '2026-01-13 16:07:25', '2026-01-13 16:07:26'),
(1479, 12, 6, 2, 38.00, '2026-01-13 16:07:25', '2026-01-13 16:07:26'),
(1486, 10, 6, 3, 28.00, '2026-01-13 16:07:29', NULL),
(1487, 11, 6, 3, 27.00, '2026-01-13 16:07:29', '2026-01-13 16:07:31'),
(1488, 12, 6, 3, 36.00, '2026-01-13 16:07:29', '2026-01-13 16:07:31'),
(1495, 10, 6, 6, 22.00, '2026-01-13 16:07:35', NULL),
(1496, 11, 6, 6, 27.00, '2026-01-13 16:07:35', NULL),
(1497, 12, 6, 6, 37.00, '2026-01-13 16:07:35', NULL),
(1504, 13, 6, 2, 50.00, '2026-01-13 16:07:38', NULL),
(1505, 14, 6, 2, 23.00, '2026-01-13 16:07:38', '2026-01-13 16:07:44'),
(1506, 15, 6, 2, 22.00, '2026-01-13 16:07:38', '2026-01-13 16:07:44'),
(1516, 13, 6, 3, 46.00, '2026-01-13 16:07:47', NULL),
(1517, 14, 6, 3, 25.00, '2026-01-13 16:07:47', '2026-01-13 16:07:47'),
(1518, 15, 6, 3, 25.00, '2026-01-13 16:07:47', '2026-01-13 16:07:49'),
(1540, 13, 6, 6, 37.00, '2026-01-13 16:07:53', NULL),
(1541, 14, 6, 6, 23.00, '2026-01-13 16:07:53', NULL),
(1542, 15, 6, 6, 22.00, '2026-01-13 16:07:53', NULL),
(1543, 10, 7, 2, 25.00, '2026-01-13 16:08:22', NULL),
(1544, 11, 7, 2, 26.00, '2026-01-13 16:08:22', NULL),
(1545, 12, 7, 2, 37.00, '2026-01-13 16:08:22', NULL),
(1549, 10, 7, 3, 21.00, '2026-01-13 16:08:25', NULL),
(1550, 11, 7, 3, 27.00, '2026-01-13 16:08:25', NULL),
(1551, 12, 7, 3, 35.00, '2026-01-13 16:08:25', '2026-01-13 16:08:26'),
(1558, 10, 7, 6, 28.00, '2026-01-13 16:08:30', NULL),
(1559, 11, 7, 6, 26.00, '2026-01-13 16:08:30', NULL),
(1560, 12, 7, 6, 29.00, '2026-01-13 16:08:30', NULL),
(1567, 13, 7, 2, 28.00, '2026-01-13 16:08:36', '2026-01-13 16:08:40'),
(1568, 14, 7, 2, 20.00, '2026-01-13 16:08:36', '2026-01-13 16:08:40'),
(1569, 15, 7, 2, 17.00, '2026-01-13 16:08:36', '2026-01-13 16:08:40'),
(1573, 13, 7, 3, 34.00, '2026-01-13 16:08:37', '2026-01-13 16:08:45'),
(1574, 14, 7, 3, 21.00, '2026-01-13 16:08:37', '2026-01-13 16:08:45'),
(1575, 15, 7, 3, 17.00, '2026-01-13 16:08:37', '2026-01-13 16:08:45'),
(1603, 13, 7, 6, 31.00, '2026-01-13 16:08:48', NULL),
(1604, 14, 7, 6, 21.00, '2026-01-13 16:08:48', NULL),
(1605, 15, 7, 6, 16.00, '2026-01-13 16:08:48', NULL),
(1612, 10, 9, 2, 18.00, '2026-01-13 16:09:42', NULL),
(1613, 11, 9, 2, 25.00, '2026-01-13 16:09:42', NULL),
(1614, 12, 9, 2, 28.00, '2026-01-13 16:09:42', NULL),
(1618, 10, 9, 3, 20.00, '2026-01-13 16:09:49', '2026-01-13 16:09:50'),
(1619, 11, 9, 3, 19.00, '2026-01-13 16:09:49', NULL),
(1620, 12, 9, 3, 37.00, '2026-01-13 16:09:49', NULL),
(1624, 10, 9, 6, 18.00, '2026-01-13 16:09:53', NULL),
(1625, 11, 9, 6, 23.00, '2026-01-13 16:09:53', NULL),
(1626, 12, 9, 6, 26.00, '2026-01-13 16:09:53', NULL),
(1633, 13, 9, 2, 26.00, '2026-01-13 16:09:59', NULL),
(1634, 14, 9, 2, 22.00, '2026-01-13 16:09:59', NULL),
(1635, 15, 9, 2, 20.00, '2026-01-13 16:09:59', '2026-01-13 16:10:00'),
(1651, 13, 9, 3, 43.00, '2026-01-13 16:10:02', '2026-01-13 16:10:05'),
(1652, 14, 9, 3, 22.00, '2026-01-13 16:10:02', '2026-01-13 16:10:05'),
(1653, 15, 9, 3, 20.00, '2026-01-13 16:10:02', '2026-01-13 16:10:06'),
(1669, 13, 9, 6, 40.00, '2026-01-13 16:10:09', NULL),
(1670, 14, 9, 6, 21.00, '2026-01-13 16:10:09', NULL),
(1671, 15, 9, 6, 19.00, '2026-01-13 16:10:09', NULL),
(1678, 16, 32, 9, 20.00, '2026-01-14 14:50:35', '2026-01-14 14:57:11'),
(1679, 17, 32, 9, 28.00, '2026-01-14 14:50:35', '2026-01-14 14:57:11'),
(1680, 18, 32, 9, 25.00, '2026-01-14 14:50:35', '2026-01-14 14:57:12'),
(1711, 16, 32, 10, 27.00, '2026-01-14 14:57:33', NULL),
(1712, 17, 32, 10, 31.00, '2026-01-14 14:57:33', '2026-01-14 14:57:34'),
(1713, 18, 32, 10, 23.00, '2026-01-14 14:57:33', '2026-01-14 14:57:34'),
(1720, 16, 32, 11, 21.00, '2026-01-14 14:57:38', NULL),
(1721, 17, 32, 11, 35.00, '2026-01-14 14:57:38', '2026-01-14 14:57:40'),
(1722, 18, 32, 11, 23.00, '2026-01-14 14:57:38', '2026-01-14 14:57:40'),
(1729, 16, 32, 12, 28.00, '2026-01-14 14:57:44', NULL),
(1730, 17, 32, 12, 33.00, '2026-01-14 14:57:44', NULL),
(1731, 18, 32, 12, 25.00, '2026-01-14 14:57:44', NULL),
(1735, 16, 32, 13, 22.00, '2026-01-14 14:57:49', NULL),
(1736, 17, 32, 13, 37.00, '2026-01-14 14:57:49', NULL),
(1737, 18, 32, 13, 26.00, '2026-01-14 14:57:49', NULL),
(1741, 16, 32, 14, 23.00, '2026-01-14 14:57:52', NULL),
(1742, 17, 32, 14, 38.00, '2026-01-14 14:57:52', NULL),
(1743, 18, 32, 14, 24.00, '2026-01-14 14:57:52', NULL),
(1744, 16, 32, 15, 26.00, '2026-01-14 14:57:56', NULL),
(1745, 17, 32, 15, 32.00, '2026-01-14 14:57:56', NULL),
(1746, 18, 32, 15, 23.00, '2026-01-14 14:57:56', NULL),
(1747, 16, 32, 16, 29.00, '2026-01-14 14:58:00', NULL),
(1748, 17, 32, 16, 31.00, '2026-01-14 14:58:00', NULL),
(1749, 18, 32, 16, 28.00, '2026-01-14 14:58:00', NULL),
(1753, 16, 33, 9, 25.00, '2026-01-14 14:59:34', NULL),
(1754, 17, 33, 9, 34.00, '2026-01-14 14:59:34', NULL),
(1755, 18, 33, 9, 25.00, '2026-01-14 14:59:34', NULL),
(1759, 16, 33, 10, 27.00, '2026-01-14 14:59:38', '2026-01-14 14:59:39'),
(1760, 17, 33, 10, 38.00, '2026-01-14 14:59:38', NULL),
(1761, 18, 33, 10, 27.00, '2026-01-14 14:59:38', '2026-01-14 14:59:39'),
(1768, 16, 33, 11, 22.00, '2026-01-14 14:59:43', NULL),
(1769, 17, 33, 11, 32.00, '2026-01-14 14:59:43', NULL),
(1770, 18, 33, 11, 23.00, '2026-01-14 14:59:43', '2026-01-14 14:59:44'),
(1777, 16, 33, 12, 27.00, '2026-01-14 14:59:47', NULL),
(1778, 17, 33, 12, 39.00, '2026-01-14 14:59:47', NULL),
(1779, 18, 33, 12, 22.00, '2026-01-14 14:59:47', '2026-01-14 14:59:49'),
(1786, 16, 33, 13, 20.00, '2026-01-14 14:59:53', NULL),
(1787, 17, 33, 13, 36.00, '2026-01-14 14:59:53', NULL),
(1788, 18, 33, 13, 24.00, '2026-01-14 14:59:53', NULL),
(1792, 16, 33, 14, 25.00, '2026-01-14 14:59:57', NULL),
(1793, 17, 33, 14, 36.00, '2026-01-14 14:59:57', NULL),
(1794, 18, 33, 14, 27.00, '2026-01-14 14:59:57', NULL),
(1798, 16, 33, 16, 28.00, '2026-01-14 15:00:02', NULL),
(1799, 17, 33, 16, 38.00, '2026-01-14 15:00:02', NULL),
(1800, 18, 33, 16, 30.00, '2026-01-14 15:00:02', NULL),
(1804, 16, 33, 15, 25.00, '2026-01-14 15:00:06', NULL),
(1805, 17, 33, 15, 33.00, '2026-01-14 15:00:06', NULL),
(1806, 18, 33, 15, 26.00, '2026-01-14 15:00:06', '2026-01-14 15:00:07'),
(1813, 16, 34, 9, 25.00, '2026-01-14 15:17:24', '2026-01-14 15:17:34'),
(1814, 17, 34, 9, 27.00, '2026-01-14 15:17:24', '2026-01-14 15:17:35'),
(1815, 18, 34, 9, 26.00, '2026-01-14 15:17:24', '2026-01-14 15:17:36'),
(1831, 16, 34, 10, 17.00, '2026-01-14 15:17:43', NULL),
(1832, 17, 34, 10, 37.00, '2026-01-14 15:17:43', '2026-01-14 15:17:45'),
(1833, 18, 34, 10, 28.00, '2026-01-14 15:17:43', '2026-01-14 15:17:45'),
(1840, 16, 34, 11, 25.00, '2026-01-14 15:17:50', '2026-01-14 15:17:50'),
(1841, 17, 34, 11, 30.00, '2026-01-14 15:17:50', NULL),
(1842, 18, 34, 11, 25.00, '2026-01-14 15:17:50', NULL),
(1846, 16, 34, 12, 27.00, '2026-01-14 15:17:54', NULL),
(1847, 17, 34, 12, 37.00, '2026-01-14 15:17:54', NULL),
(1848, 18, 34, 12, 26.00, '2026-01-14 15:17:54', NULL),
(1852, 16, 34, 13, 28.00, '2026-01-14 15:17:59', NULL),
(1853, 17, 34, 13, 35.00, '2026-01-14 15:17:59', NULL),
(1854, 18, 34, 13, 23.00, '2026-01-14 15:17:59', NULL),
(1855, 16, 34, 14, 23.00, '2026-01-14 15:18:02', '2026-01-14 15:18:03'),
(1856, 17, 34, 14, 31.00, '2026-01-14 15:18:02', NULL),
(1857, 18, 34, 14, 29.00, '2026-01-14 15:18:02', NULL),
(1864, 16, 34, 15, 27.00, '2026-01-14 15:18:07', NULL),
(1865, 17, 34, 15, 33.00, '2026-01-14 15:18:07', NULL),
(1866, 18, 34, 15, 24.00, '2026-01-14 15:18:07', NULL),
(1867, 16, 34, 16, 27.00, '2026-01-14 15:18:10', NULL),
(1868, 17, 34, 16, 39.00, '2026-01-14 15:18:10', '2026-01-14 15:18:12'),
(1869, 18, 34, 16, 28.00, '2026-01-14 15:18:10', NULL),
(1876, 16, 35, 9, 24.00, '2026-01-14 15:20:11', NULL),
(1877, 17, 35, 9, 34.00, '2026-01-14 15:20:11', NULL),
(1878, 18, 35, 9, 28.00, '2026-01-14 15:20:11', NULL),
(1882, 16, 35, 10, 29.00, '2026-01-14 15:20:21', NULL),
(1883, 17, 35, 10, 36.00, '2026-01-14 15:20:21', NULL),
(1884, 18, 35, 10, 28.00, '2026-01-14 15:20:21', NULL),
(1888, 16, 35, 11, 26.00, '2026-01-14 15:20:24', '2026-01-14 15:20:24'),
(1889, 17, 35, 11, 35.00, '2026-01-14 15:20:24', NULL),
(1890, 18, 35, 11, 27.00, '2026-01-14 15:20:24', NULL),
(1894, 16, 35, 12, 29.00, '2026-01-14 15:20:27', NULL),
(1895, 17, 35, 12, 37.00, '2026-01-14 15:20:27', '2026-01-14 15:20:28'),
(1896, 18, 35, 12, 29.00, '2026-01-14 15:20:27', '2026-01-14 15:20:28'),
(1903, 16, 35, 13, 27.00, '2026-01-14 15:20:33', NULL),
(1904, 17, 35, 13, 36.00, '2026-01-14 15:20:33', NULL),
(1905, 18, 35, 13, 28.00, '2026-01-14 15:20:33', NULL),
(1906, 16, 35, 14, 24.00, '2026-01-14 15:20:36', NULL),
(1907, 17, 35, 14, 36.00, '2026-01-14 15:20:36', NULL),
(1908, 18, 35, 14, 29.00, '2026-01-14 15:20:36', NULL),
(1912, 16, 35, 15, 16.00, '2026-01-14 15:20:40', NULL),
(1913, 17, 35, 15, 33.00, '2026-01-14 15:20:40', NULL),
(1914, 18, 35, 15, 22.00, '2026-01-14 15:20:40', '2026-01-14 15:20:41'),
(1918, 16, 35, 16, 29.00, '2026-01-14 15:20:45', NULL),
(1919, 17, 35, 16, 32.00, '2026-01-14 15:20:45', NULL),
(1920, 18, 35, 16, 28.00, '2026-01-14 15:20:45', '2026-01-14 15:20:45'),
(1927, 19, 32, 10, 32.00, '2026-01-14 15:41:39', NULL),
(1928, 20, 32, 10, 25.00, '2026-01-14 15:41:39', NULL),
(1929, 21, 32, 10, 23.00, '2026-01-14 15:41:39', NULL),
(1933, 19, 32, 11, 33.00, '2026-01-14 15:41:49', NULL),
(1934, 20, 32, 11, 27.00, '2026-01-14 15:41:49', NULL),
(1935, 21, 32, 11, 26.00, '2026-01-14 15:41:49', NULL),
(1939, 19, 32, 12, 36.00, '2026-01-14 15:41:53', NULL),
(1940, 20, 32, 12, 25.00, '2026-01-14 15:41:53', NULL),
(1941, 21, 32, 12, 27.00, '2026-01-14 15:41:53', NULL),
(1945, 19, 32, 13, 36.00, '2026-01-14 15:41:56', NULL),
(1946, 20, 32, 13, 28.00, '2026-01-14 15:41:56', NULL),
(1947, 21, 32, 13, 27.00, '2026-01-14 15:41:56', NULL),
(1951, 19, 32, 14, 36.00, '2026-01-14 15:41:59', NULL),
(1952, 20, 32, 14, 28.00, '2026-01-14 15:41:59', '2026-01-14 15:42:01'),
(1953, 21, 32, 14, 29.00, '2026-01-14 15:41:59', '2026-01-14 15:42:01'),
(1957, 19, 32, 16, 40.00, '2026-01-14 15:42:04', NULL),
(1958, 20, 32, 16, 28.00, '2026-01-14 15:42:04', NULL),
(1959, 21, 32, 16, 28.00, '2026-01-14 15:42:04', '2026-01-14 15:42:05'),
(1969, 22, 32, 10, 35.00, '2026-01-14 15:42:17', NULL),
(1970, 23, 32, 10, 29.00, '2026-01-14 15:42:17', NULL),
(1971, 24, 32, 10, 24.00, '2026-01-14 15:42:17', NULL),
(1981, 22, 32, 11, 37.00, '2026-01-14 15:42:21', NULL),
(1982, 23, 32, 11, 32.00, '2026-01-14 15:42:21', NULL),
(1983, 24, 32, 11, 17.00, '2026-01-14 15:42:21', '2026-01-14 15:42:22'),
(1999, 22, 32, 12, 31.00, '2026-01-14 15:42:25', NULL),
(2000, 23, 32, 12, 30.00, '2026-01-14 15:42:25', NULL),
(2001, 24, 32, 12, 18.00, '2026-01-14 15:42:25', '2026-01-14 15:42:26'),
(2011, 22, 32, 13, 30.00, '2026-01-14 15:42:29', NULL),
(2012, 23, 32, 13, 30.00, '2026-01-14 15:42:29', NULL),
(2013, 24, 32, 13, 20.00, '2026-01-14 15:42:29', '2026-01-14 15:42:31'),
(2035, 22, 32, 14, 38.00, '2026-01-14 15:42:34', NULL),
(2036, 23, 32, 14, 25.00, '2026-01-14 15:42:34', '2026-01-14 15:42:36'),
(2037, 24, 32, 14, 22.00, '2026-01-14 15:42:34', '2026-01-14 15:42:36'),
(2047, 22, 32, 16, 37.00, '2026-01-14 15:42:39', NULL),
(2048, 23, 32, 16, 32.00, '2026-01-14 15:42:39', NULL),
(2049, 24, 32, 16, 24.00, '2026-01-14 15:42:39', NULL),
(2050, 19, 33, 10, 36.00, '2026-01-14 15:43:49', NULL),
(2051, 20, 33, 10, 27.00, '2026-01-14 15:43:49', NULL),
(2052, 21, 33, 10, 24.00, '2026-01-14 15:43:49', NULL),
(2056, 19, 33, 11, 39.00, '2026-01-14 15:43:51', NULL),
(2057, 20, 33, 11, 24.00, '2026-01-14 15:43:51', '2026-01-14 15:43:53'),
(2058, 21, 33, 11, 23.00, '2026-01-14 15:43:51', '2026-01-14 15:43:53'),
(2065, 19, 33, 12, 38.00, '2026-01-14 15:43:57', NULL),
(2066, 20, 33, 12, 27.00, '2026-01-14 15:43:57', NULL),
(2067, 21, 33, 12, 25.00, '2026-01-14 15:43:57', NULL),
(2068, 19, 33, 13, 26.00, '2026-01-14 15:44:00', NULL),
(2069, 20, 33, 13, 25.00, '2026-01-14 15:44:00', NULL),
(2070, 21, 33, 13, 22.00, '2026-01-14 15:44:00', NULL),
(2071, 19, 33, 14, 33.00, '2026-01-14 15:44:04', NULL),
(2072, 20, 33, 14, 25.00, '2026-01-14 15:44:04', NULL),
(2073, 21, 33, 14, 27.00, '2026-01-14 15:44:04', NULL),
(2074, 19, 33, 16, 38.00, '2026-01-14 15:44:06', NULL),
(2075, 20, 33, 16, 29.00, '2026-01-14 15:44:06', NULL),
(2076, 21, 33, 16, 27.00, '2026-01-14 15:44:06', '2026-01-14 15:44:07'),
(2086, 22, 33, 10, 27.00, '2026-01-14 15:44:11', '2026-01-14 15:44:14'),
(2087, 23, 33, 10, 29.00, '2026-01-14 15:44:11', '2026-01-14 15:44:14'),
(2088, 24, 33, 10, 23.00, '2026-01-14 15:44:11', '2026-01-14 15:44:14'),
(2098, 22, 33, 11, 29.00, '2026-01-14 15:44:17', '2026-01-14 15:44:18'),
(2099, 23, 33, 11, 31.00, '2026-01-14 15:44:17', NULL),
(2100, 24, 33, 11, 21.00, '2026-01-14 15:44:17', '2026-01-14 15:44:19'),
(2116, 22, 33, 12, 32.00, '2026-01-14 15:44:22', NULL),
(2117, 23, 33, 12, 32.00, '2026-01-14 15:44:22', NULL),
(2118, 24, 33, 12, 21.00, '2026-01-14 15:44:22', NULL),
(2128, 22, 33, 13, 32.00, '2026-01-14 15:44:26', NULL),
(2129, 23, 33, 13, 31.00, '2026-01-14 15:44:26', NULL),
(2130, 24, 33, 13, 17.00, '2026-01-14 15:44:26', '2026-01-14 15:44:28'),
(2146, 22, 33, 14, 35.00, '2026-01-14 15:44:33', NULL),
(2147, 23, 33, 14, 29.00, '2026-01-14 15:44:33', NULL),
(2148, 24, 33, 14, 22.00, '2026-01-14 15:44:33', NULL),
(2158, 22, 33, 16, 36.00, '2026-01-14 15:44:37', NULL),
(2159, 23, 33, 16, 35.00, '2026-01-14 15:44:37', '2026-01-14 15:44:39'),
(2160, 24, 33, 16, 21.00, '2026-01-14 15:44:37', NULL),
(2173, 19, 34, 10, 27.00, '2026-01-14 15:45:32', NULL),
(2174, 20, 34, 10, 23.00, '2026-01-14 15:45:32', NULL),
(2175, 21, 34, 10, 22.00, '2026-01-14 15:45:32', NULL),
(2179, 19, 34, 11, 33.00, '2026-01-14 15:45:35', NULL),
(2180, 20, 34, 11, 21.00, '2026-01-14 15:45:35', '2026-01-14 15:45:36'),
(2181, 21, 34, 11, 21.00, '2026-01-14 15:45:35', '2026-01-14 15:45:36'),
(2188, 19, 34, 12, 35.00, '2026-01-14 15:45:40', NULL),
(2189, 20, 34, 12, 24.00, '2026-01-14 15:45:40', NULL),
(2190, 21, 34, 12, 26.00, '2026-01-14 15:45:40', '2026-01-14 15:45:41'),
(2194, 19, 34, 13, 36.00, '2026-01-14 15:45:43', NULL),
(2195, 20, 34, 13, 26.00, '2026-01-14 15:45:43', NULL),
(2196, 21, 34, 13, 27.00, '2026-01-14 15:45:43', '2026-01-14 15:45:44'),
(2203, 19, 34, 14, 33.00, '2026-01-14 15:45:49', NULL),
(2204, 20, 34, 14, 26.00, '2026-01-14 15:45:49', NULL),
(2205, 21, 34, 14, 28.00, '2026-01-14 15:45:49', NULL),
(2206, 19, 34, 16, 39.00, '2026-01-14 15:45:52', NULL),
(2207, 20, 34, 16, 25.00, '2026-01-14 15:45:52', NULL),
(2208, 21, 34, 16, 28.00, '2026-01-14 15:45:52', NULL),
(2215, 22, 34, 12, 33.00, '2026-01-14 15:46:02', NULL),
(2216, 23, 34, 12, 29.00, '2026-01-14 15:46:02', NULL),
(2217, 24, 34, 12, 21.00, '2026-01-14 15:46:02', NULL),
(2221, 22, 34, 10, 37.00, '2026-01-14 15:46:06', NULL),
(2222, 23, 34, 10, 32.00, '2026-01-14 15:46:06', NULL),
(2223, 24, 34, 10, 20.00, '2026-01-14 15:46:06', NULL),
(2227, 22, 34, 11, 39.00, '2026-01-14 15:46:09', NULL),
(2228, 23, 34, 11, 31.00, '2026-01-14 15:46:09', NULL),
(2229, 24, 34, 11, 23.00, '2026-01-14 15:46:09', NULL),
(2233, 22, 34, 13, 32.00, '2026-01-14 15:46:13', NULL),
(2234, 23, 34, 13, 32.00, '2026-01-14 15:46:13', NULL),
(2235, 24, 34, 13, 23.00, '2026-01-14 15:46:13', NULL),
(2239, 22, 34, 14, 33.00, '2026-01-14 15:46:16', NULL),
(2240, 23, 34, 14, 32.00, '2026-01-14 15:46:16', NULL),
(2241, 24, 34, 14, 23.00, '2026-01-14 15:46:16', NULL),
(2245, 22, 34, 16, 39.00, '2026-01-14 15:46:19', NULL),
(2246, 23, 34, 16, 33.00, '2026-01-14 15:46:19', NULL),
(2247, 24, 34, 16, 23.00, '2026-01-14 15:46:19', NULL),
(2248, 19, 35, 10, 24.00, '2026-01-14 15:46:53', NULL),
(2249, 20, 35, 10, 25.00, '2026-01-14 15:46:53', NULL),
(2250, 21, 35, 10, 25.00, '2026-01-14 15:46:53', NULL),
(2254, 19, 35, 11, 36.00, '2026-01-14 15:46:59', NULL),
(2255, 20, 35, 11, 27.00, '2026-01-14 15:46:59', NULL),
(2256, 21, 35, 11, 23.00, '2026-01-14 15:46:59', NULL),
(2260, 19, 35, 12, 36.00, '2026-01-14 15:47:03', '2026-01-14 15:47:04'),
(2261, 20, 35, 12, 29.00, '2026-01-14 15:47:03', NULL),
(2262, 21, 35, 12, 27.00, '2026-01-14 15:47:03', NULL),
(2266, 19, 35, 13, 30.00, '2026-01-14 15:47:08', NULL),
(2267, 20, 35, 13, 21.00, '2026-01-14 15:47:08', NULL),
(2268, 21, 35, 13, 23.00, '2026-01-14 15:47:08', NULL),
(2269, 19, 35, 14, 39.00, '2026-01-14 15:47:11', NULL),
(2270, 20, 35, 14, 25.00, '2026-01-14 15:47:11', NULL),
(2271, 21, 35, 14, 27.00, '2026-01-14 15:47:11', NULL),
(2275, 19, 35, 16, 37.00, '2026-01-14 15:47:15', NULL),
(2276, 20, 35, 16, 28.00, '2026-01-14 15:47:15', NULL),
(2277, 21, 35, 16, 29.00, '2026-01-14 15:47:15', NULL),
(2284, 22, 35, 16, 39.00, '2026-01-14 15:47:21', NULL),
(2285, 23, 35, 16, 33.00, '2026-01-14 15:47:21', NULL),
(2286, 24, 35, 16, 24.00, '2026-01-14 15:47:21', NULL),
(2290, 22, 35, 14, 36.00, '2026-01-14 15:47:25', NULL),
(2291, 23, 35, 14, 29.00, '2026-01-14 15:47:25', NULL),
(2292, 24, 35, 14, 23.00, '2026-01-14 15:47:25', NULL),
(2302, 22, 35, 13, 22.00, '2026-01-14 15:47:29', NULL),
(2303, 23, 35, 13, 24.00, '2026-01-14 15:47:29', NULL),
(2304, 24, 35, 13, 17.00, '2026-01-14 15:47:29', NULL),
(2308, 22, 35, 12, 29.00, '2026-01-14 15:47:32', NULL),
(2309, 23, 35, 12, 25.00, '2026-01-14 15:47:32', NULL),
(2310, 24, 35, 12, 13.00, '2026-01-14 15:47:32', NULL),
(2314, 22, 35, 11, 28.00, '2026-01-14 15:47:35', NULL),
(2315, 23, 35, 11, 21.00, '2026-01-14 15:47:35', NULL),
(2316, 24, 35, 11, 11.00, '2026-01-14 15:47:35', '2026-01-14 15:47:36'),
(2326, 22, 35, 10, 31.00, '2026-01-14 15:47:40', NULL),
(2327, 23, 35, 10, 28.00, '2026-01-14 15:47:40', NULL),
(2328, 24, 35, 10, 12.00, '2026-01-14 15:47:40', NULL),
(2329, 25, 35, 16, 30.00, '2026-01-14 15:49:05', NULL),
(2330, 26, 35, 16, 33.00, '2026-01-14 15:49:05', NULL),
(2331, 27, 35, 16, 21.00, '2026-01-14 15:49:05', NULL),
(2335, 25, 35, 12, 31.00, '2026-01-14 15:49:09', NULL),
(2336, 26, 35, 12, 28.00, '2026-01-14 15:49:09', NULL),
(2337, 27, 35, 12, 17.00, '2026-01-14 15:49:09', NULL),
(2338, 25, 35, 14, 37.00, '2026-01-14 15:49:12', NULL),
(2339, 26, 35, 14, 29.00, '2026-01-14 15:49:12', NULL),
(2340, 27, 35, 14, 17.00, '2026-01-14 15:49:12', NULL),
(2344, 28, 35, 12, 32.00, '2026-01-14 15:49:25', NULL),
(2345, 29, 35, 12, 31.00, '2026-01-14 15:49:25', NULL),
(2346, 30, 35, 12, 23.00, '2026-01-14 15:49:25', NULL),
(2350, 28, 35, 14, 31.00, '2026-01-14 15:49:29', NULL),
(2351, 29, 35, 14, 33.00, '2026-01-14 15:49:29', NULL),
(2352, 30, 35, 14, 26.00, '2026-01-14 15:49:29', NULL),
(2356, 28, 35, 16, 30.00, '2026-01-14 15:49:32', NULL),
(2357, 29, 35, 16, 34.00, '2026-01-14 15:49:32', NULL),
(2358, 30, 35, 16, 25.00, '2026-01-14 15:49:32', NULL),
(2365, 31, 35, 12, 43.00, '2026-01-14 15:49:38', NULL),
(2366, 32, 35, 12, 24.00, '2026-01-14 15:49:38', NULL),
(2367, 33, 35, 12, 21.00, '2026-01-14 15:49:38', NULL),
(2374, 31, 35, 14, 32.00, '2026-01-14 15:49:41', NULL),
(2375, 32, 35, 14, 26.00, '2026-01-14 15:49:41', NULL),
(2376, 33, 35, 14, 19.00, '2026-01-14 15:49:41', NULL),
(2383, 31, 35, 16, 45.00, '2026-01-14 15:49:44', NULL),
(2384, 32, 35, 16, 27.00, '2026-01-14 15:49:44', NULL),
(2385, 33, 35, 16, 22.00, '2026-01-14 15:49:44', NULL),
(2395, 25, 34, 12, 35.00, '2026-01-14 15:50:38', NULL),
(2396, 26, 34, 12, 31.00, '2026-01-14 15:50:38', NULL),
(2397, 27, 34, 12, 20.00, '2026-01-14 15:50:38', NULL),
(2401, 25, 34, 14, 29.00, '2026-01-14 15:50:41', NULL),
(2402, 26, 34, 14, 32.00, '2026-01-14 15:50:42', NULL),
(2403, 27, 34, 14, 17.00, '2026-01-14 15:50:42', NULL),
(2404, 25, 34, 16, 37.00, '2026-01-14 15:50:45', NULL),
(2405, 26, 34, 16, 33.00, '2026-01-14 15:50:45', NULL),
(2406, 27, 34, 16, 22.00, '2026-01-14 15:50:45', NULL),
(2413, 28, 34, 12, 29.00, '2026-01-14 15:50:55', NULL),
(2414, 29, 34, 12, 29.00, '2026-01-14 15:50:55', NULL),
(2415, 30, 34, 12, 25.00, '2026-01-14 15:50:55', NULL),
(2419, 28, 34, 14, 27.00, '2026-01-14 15:50:58', NULL),
(2420, 29, 34, 14, 31.00, '2026-01-14 15:50:58', NULL),
(2421, 30, 34, 14, 29.00, '2026-01-14 15:50:58', NULL),
(2425, 28, 34, 16, 35.00, '2026-01-14 15:51:01', NULL),
(2426, 29, 34, 16, 29.00, '2026-01-14 15:51:01', NULL),
(2427, 30, 34, 16, 27.00, '2026-01-14 15:51:01', NULL),
(2440, 31, 34, 12, 37.00, '2026-01-14 15:51:06', NULL),
(2441, 32, 34, 12, 26.00, '2026-01-14 15:51:06', NULL),
(2442, 33, 34, 12, 20.00, '2026-01-14 15:51:06', NULL),
(2449, 31, 34, 14, 42.00, '2026-01-14 15:51:09', NULL),
(2450, 32, 34, 14, 26.00, '2026-01-14 15:51:09', NULL),
(2451, 33, 34, 14, 18.00, '2026-01-14 15:51:09', NULL),
(2458, 31, 34, 16, 45.00, '2026-01-14 15:51:13', NULL),
(2459, 32, 34, 16, 28.00, '2026-01-14 15:51:13', NULL),
(2460, 33, 34, 16, 22.00, '2026-01-14 15:51:13', NULL),
(2461, 25, 33, 12, 29.00, '2026-01-14 15:51:46', NULL),
(2462, 26, 33, 12, 29.00, '2026-01-14 15:51:46', NULL),
(2463, 27, 33, 12, 20.00, '2026-01-14 15:51:46', NULL),
(2467, 25, 33, 14, 31.00, '2026-01-14 15:51:50', NULL),
(2468, 26, 33, 14, 27.00, '2026-01-14 15:51:50', NULL),
(2469, 27, 33, 14, 20.00, '2026-01-14 15:51:50', NULL),
(2470, 25, 33, 16, 36.00, '2026-01-14 15:51:54', NULL),
(2471, 26, 33, 16, 32.00, '2026-01-14 15:51:54', NULL),
(2472, 27, 33, 16, 22.00, '2026-01-14 15:51:54', NULL),
(2476, 28, 33, 12, 32.00, '2026-01-14 15:52:05', NULL),
(2477, 29, 33, 12, 30.00, '2026-01-14 15:52:05', NULL),
(2478, 30, 33, 12, 26.00, '2026-01-14 15:52:05', NULL),
(2488, 28, 33, 14, 28.00, '2026-01-14 15:52:08', NULL),
(2489, 29, 33, 14, 29.00, '2026-01-14 15:52:08', NULL),
(2490, 30, 33, 14, 22.00, '2026-01-14 15:52:08', NULL),
(2500, 28, 33, 16, 32.00, '2026-01-14 15:52:15', NULL),
(2501, 29, 33, 16, 32.00, '2026-01-14 15:52:15', NULL),
(2502, 30, 33, 16, 27.00, '2026-01-14 15:52:15', NULL),
(2515, 31, 33, 12, 31.00, '2026-01-14 15:52:19', NULL),
(2516, 32, 33, 12, 27.00, '2026-01-14 15:52:19', '2026-01-14 15:52:21'),
(2517, 33, 33, 12, 21.00, '2026-01-14 15:52:19', '2026-01-14 15:52:21'),
(2533, 31, 33, 14, 37.00, '2026-01-14 15:52:24', NULL),
(2534, 32, 33, 14, 26.00, '2026-01-14 15:52:24', NULL),
(2535, 33, 33, 14, 20.00, '2026-01-14 15:52:24', NULL),
(2542, 31, 33, 16, 43.00, '2026-01-14 15:52:27', NULL),
(2543, 32, 33, 16, 28.00, '2026-01-14 15:52:27', NULL),
(2544, 33, 33, 16, 21.00, '2026-01-14 15:52:27', NULL),
(2554, 25, 32, 12, 29.00, '2026-01-14 15:53:10', NULL),
(2555, 26, 32, 12, 31.00, '2026-01-14 15:53:10', NULL),
(2556, 27, 32, 12, 18.00, '2026-01-14 15:53:10', NULL),
(2560, 25, 32, 14, 31.00, '2026-01-14 15:53:13', NULL),
(2561, 26, 32, 14, 29.00, '2026-01-14 15:53:14', NULL),
(2562, 27, 32, 14, 21.00, '2026-01-14 15:53:14', NULL),
(2563, 25, 32, 16, 40.00, '2026-01-14 15:53:16', NULL),
(2564, 26, 32, 16, 31.00, '2026-01-14 15:53:16', NULL),
(2565, 27, 32, 16, 18.00, '2026-01-14 15:53:16', '2026-01-14 15:53:18'),
(2575, 28, 32, 12, 28.00, '2026-01-14 15:53:25', NULL),
(2576, 29, 32, 12, 31.00, '2026-01-14 15:53:25', NULL),
(2577, 30, 32, 12, 24.00, '2026-01-14 15:53:25', NULL),
(2581, 28, 32, 14, 35.00, '2026-01-14 15:53:28', NULL),
(2582, 29, 32, 14, 29.00, '2026-01-14 15:53:28', NULL),
(2583, 30, 32, 14, 28.00, '2026-01-14 15:53:28', NULL),
(2587, 28, 32, 16, 34.00, '2026-01-14 15:53:31', NULL),
(2588, 29, 32, 16, 31.00, '2026-01-14 15:53:31', NULL),
(2589, 30, 32, 16, 26.00, '2026-01-14 15:53:31', NULL),
(2596, 31, 32, 12, 37.00, '2026-01-14 15:53:35', NULL),
(2597, 32, 32, 12, 23.00, '2026-01-14 15:53:35', NULL),
(2598, 33, 32, 12, 23.00, '2026-01-14 15:53:35', '2026-01-14 15:53:37'),
(2614, 31, 32, 14, 43.00, '2026-01-14 15:53:40', NULL),
(2615, 32, 32, 14, 27.00, '2026-01-14 15:53:40', NULL),
(2616, 33, 32, 14, 21.00, '2026-01-14 15:53:40', '2026-01-14 15:53:40'),
(2632, 31, 32, 16, 37.00, '2026-01-14 15:53:44', NULL),
(2633, 32, 32, 16, 28.00, '2026-01-14 15:53:44', NULL),
(2634, 33, 32, 16, 23.00, '2026-01-14 15:53:44', NULL);

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
(7, 3, 'Question and Answer', 'Evaluates communication and critical thinking', 50.00, 2, 0, '2026-01-12 18:45:03'),
(8, 4, 'Production Number', 'Group performance assessing synchronization and energy.', 100.00, 1, 0, '2026-01-14 10:39:05'),
(9, 5, 'Swimsuit Competition', 'Evaluation of confidence and physical presentation.', 50.00, 1, 0, '2026-01-14 10:49:52'),
(10, 5, 'Evening Gown', 'Showcasing elegance and grace.', 50.00, 2, 0, '2026-01-14 10:54:52'),
(11, 6, 'Final Interview', 'One-on-one interview evaluating personality and mindset.', 40.00, 1, 0, '2026-01-14 11:05:20'),
(12, 6, 'Final Walk', 'Final runway presentation showcasing poise and impact.', 30.00, 2, 0, '2026-01-14 11:09:10'),
(13, 6, 'Advocacy Speech', 'Speech highlighting the contestant\'s advocacy and purpose.', 30.00, 3, 0, '2026-01-14 11:11:16');

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
(3, 1, '6XGKWH', 'Used', '2026-01-12 05:52:12', '2026-01-13 16:47:39'),
(4, 1, 'CPF7NT', 'Used', '2026-01-12 05:52:12', '2026-01-13 16:48:07'),
(5, 1, 'FPNR4C', 'Used', '2026-01-12 05:52:12', '2026-01-13 16:48:32'),
(6, 2, '24DTYY', 'Used', '2026-01-14 14:21:18', '2026-01-14 14:33:47'),
(7, 2, '77STZB', 'Unused', '2026-01-14 14:21:18', NULL),
(8, 2, 'XSV37J', 'Unused', '2026-01-14 14:21:18', NULL),
(9, 2, 'UCQKBX', 'Unused', '2026-01-14 14:21:18', NULL),
(10, 2, 'X4MX32', 'Unused', '2026-01-14 14:21:18', NULL),
(11, 2, 'VTSPZR', 'Unused', '2026-01-14 14:21:18', NULL),
(12, 2, 'KNLNLU', 'Unused', '2026-01-14 14:21:18', NULL),
(13, 2, 'BXQRFK', 'Unused', '2026-01-14 14:21:18', NULL),
(14, 2, '945JUH', 'Unused', '2026-01-14 14:21:18', NULL),
(15, 2, '4NPKDR', 'Unused', '2026-01-14 14:21:18', NULL),
(16, 2, 'KERT98', 'Unused', '2026-01-14 14:21:18', NULL),
(17, 2, '5JXASY', 'Unused', '2026-01-14 14:21:18', NULL),
(18, 2, 'Q3CSZA', 'Unused', '2026-01-14 14:21:18', NULL),
(19, 2, 'C8XBF4', 'Unused', '2026-01-14 14:21:18', NULL),
(20, 2, '72TPFF', 'Unused', '2026-01-14 14:21:18', NULL),
(21, 2, 'NF3H5X', 'Unused', '2026-01-14 14:21:18', NULL),
(22, 2, 'B3EXM7', 'Unused', '2026-01-14 14:21:18', NULL),
(23, 2, 'EPPV9G', 'Unused', '2026-01-14 14:21:18', NULL),
(24, 2, 'AFTLG8', 'Unused', '2026-01-14 14:21:18', NULL),
(25, 2, 'ZY468J', 'Unused', '2026-01-14 14:21:18', NULL);

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
(18, 1, 'Nicole Patrice Uy', 'fourthemail936@gmail.com', NULL, '$2y$10$lcGS4h03.vKYUnBbjh.QuuRtSgOAVo.Y1hdX/petzVHP7OhC/T6i6', 'Contestant', 'Active', 0, '2026-01-12 17:33:47'),
(19, NULL, 'Maria Elena Cruz', 'maria_contestant@gmail.com', NULL, '$2y$10$SPAxpiekZrY4NUOD9dI5Z.s9JaN2L0AXA7TRHT0VyZXE9Uk0u2Z/m', 'Contestant', 'Active', 0, '2026-01-13 18:47:42'),
(20, 1, 'Camille Rose Bautista', 'camille@gmail.com', '09087654321', '$2y$10$/myqekoPUb4ff1xwlJhxR.WQ2ds8sMZDmG94BE9lB1QaH/58aNBni', 'Judge Coordinator', 'Active', 0, '2026-01-14 05:13:05'),
(21, 1, 'Mark Anthony Robles', 'mark@gmail.com', '09283456789', '$2y$10$vJwVx/jzkkF9tr4wVrul4OjInh8OIQwcgyNcuEduJuspqQO8UI8O.', 'Contestant Manager', 'Active', 0, '2026-01-14 05:13:53'),
(22, 1, 'Joel Vincent Ramos', 'joel@gmail.com', '09394567891', '$2y$10$Q4K2OoWPCYLtj3kUx3xLbeFmEU7ovbDKEX7Y9P1HCSR80C30CQxoa', 'Tabulator', 'Active', 0, '2026-01-14 05:15:04'),
(23, 1, 'Dr. Liza Mae Navarro', 'liza@gmail.com', '09171234567', '$2y$10$glQI/u9MpMO7.256qgx5RegseYXwSu5CAZQeLFGNj8i4Y4cVEq6dG', 'Judge Coordinator', 'Inactive', 0, '2026-01-14 05:16:15'),
(24, 1, 'Angela Mae Torres', 'angela@gmail.com', NULL, '$2y$10$sfpKjKzxWyEjDaIk7ImKFOyu957NVXXHeVp1Q3a/KWvA7XiudEOEK', 'Contestant', 'Active', 0, '2026-01-14 05:18:08'),
(25, 1, 'Janelle Marie Aquino', 'janelle@gmail.com', NULL, '$2y$10$5Uz1rJeoziwCX1XS3udk8uDkyVTXwVaVIFGNE51BWIENkjFq7O.q.', 'Contestant', 'Active', 0, '2026-01-14 05:27:12'),
(26, 1, 'Rhea Camille Domingo', 'rhea@gmail.com', NULL, '$2y$10$XDPvgoVmsGQhNZQ5cDKgxeDJIYa8pR2t2voNyonQJBbm4vSuwrixa', 'Contestant', 'Active', 0, '2026-01-14 05:28:24'),
(27, 1, 'Nicole Anne Villarin', 'nicole@gmail.com', NULL, '$2y$10$8pZz7NwLXH3qjoPkEG0eueizO8DoLbts2OPpbucjmWN7iIZmNSxj2', 'Contestant', 'Active', 0, '2026-01-14 05:29:46'),
(28, 1, 'Patricia Joy Alvero', 'patricia@gmail.com', NULL, '$2y$10$7naqx0uQHxxJ95Wq36FPdeo/MymEcgbOeKBpGfg8/fCrk9SN/sM/y', 'Contestant', 'Active', 0, '2026-01-14 05:31:18'),
(29, 1, 'Hannah Louise Perez', 'hannah@gmail.com', NULL, '$2y$10$zVdcMFZ/ceXjXRjy5tGqNuaoN2yDI4SrKi84ShGsdsA6v23lqXney', 'Contestant', 'Active', 0, '2026-01-14 05:32:34'),
(30, NULL, 'Monica Geller', 'geller@gmail.com', NULL, '$2y$10$RreiVPLKHXRgkIHHDPepeO8fH72d3Q3fxupmf8XwCMZeuDUZB0.dC', 'Contestant', 'Active', 0, '2026-01-14 05:38:01'),
(31, NULL, 'Phoebe Buffay', 'buffay@gmail.com', NULL, '$2y$10$5Rn7JYNbm0W8FzPK38oDSu7qJ8DzO57y0VesEJ4rZz5/zUxweADU6', 'Contestant', 'Inactive', 0, '2026-01-14 05:46:53'),
(32, 1, 'Prof. Renato L. Cruz', 'renato@gmail.com', NULL, '$2y$10$4gliTLsAIyz3FO8HNF1XuezYygLKCmlaKthlKL5S9HzC5UIzbqjW2', 'Judge', 'Active', 0, '2026-01-14 09:48:11'),
(33, 1, 'Ms. Andrea Nicole Santos', 'santos@gmail.com', NULL, '$2y$10$JEfgUIG0e5Lb2xhKuD4OZeMiusc.6hyERyIcUnH9FDzp60lE2dRqe', 'Judge', 'Active', 0, '2026-01-14 09:49:01'),
(34, 1, 'Dr. Manuel T. Garcia', 'manuel@gmail.com', NULL, '$2y$10$6ZCPJ2ex46CYBQQ2i4THr.bOxrsNQXljXUDofPIXyWV9pB8Hipolq', 'Judge', 'Active', 0, '2026-01-14 09:50:21'),
(35, 1, 'Ms. Bianca Rose Lim', 'lim@gmail.com', NULL, '$2y$10$EEWv2Z1VBsnNGYECcZWsMetgxQ6iAD6NR9R2htEH8rtHmgl9HOAyq', 'Judge', 'Active', 0, '2026-01-14 09:58:10'),
(36, 1, 'Kimberly Anne Rosales', 'kimberly@gmail.com', NULL, '$2y$10$YkRtnXNTkatn77OgQUEDnuoglarQ7me6umf1e4m.nEkNvxKE1glF.', 'Judge', 'Inactive', 0, '2026-01-14 10:18:57');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `awards`
--
ALTER TABLE `awards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `award_winners`
--
ALTER TABLE `award_winners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `criteria`
--
ALTER TABLE `criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `event_activities`
--
ALTER TABLE `event_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `event_contestants`
--
ALTER TABLE `event_contestants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `event_judges`
--
ALTER TABLE `event_judges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `event_teams`
--
ALTER TABLE `event_teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `judge_comments`
--
ALTER TABLE `judge_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=733;

--
-- AUTO_INCREMENT for table `judge_round_status`
--
ALTER TABLE `judge_round_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `rounds`
--
ALTER TABLE `rounds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `round_rankings`
--
ALTER TABLE `round_rankings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `scores`
--
ALTER TABLE `scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2644;

--
-- AUTO_INCREMENT for table `segments`
--
ALTER TABLE `segments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

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
