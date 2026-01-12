-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 11, 2026 at 08:32 PM
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
  `ordering` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, NULL, 'Debbie Custorio', 'eventmanager@gmail.com', NULL, '$2a$10$giwtTTitX12g2TyoNQ/GGesoXsPSEs8675b1Hd3HjwAn22hE9iZSS', 'Event Manager', 'Active', 0, '2026-01-11 16:02:03');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `awards`
--
ALTER TABLE `awards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `award_winners`
--
ALTER TABLE `award_winners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `criteria`
--
ALTER TABLE `criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_activities`
--
ALTER TABLE `event_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_contestants`
--
ALTER TABLE `event_contestants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_judges`
--
ALTER TABLE `event_judges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_teams`
--
ALTER TABLE `event_teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `judge_comments`
--
ALTER TABLE `judge_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `judge_round_status`
--
ALTER TABLE `judge_round_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rounds`
--
ALTER TABLE `rounds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scores`
--
ALTER TABLE `scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `segments`
--
ALTER TABLE `segments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
