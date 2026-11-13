-- Mock Test Responses and Skipped Question Tracking Migration
-- Fixes missing mt_student_responses table and adds required attempt tracking columns

CREATE TABLE IF NOT EXISTS `mt_student_responses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `attempt_id` INT NOT NULL,
    `question_id` INT NOT NULL,
    `selected_option` VARCHAR(255) NULL,
    `status` ENUM('NOT_VISITED', 'VISITED_UNANSWERED', 'ANSWERED', 'SUBMITTED', 'unvisited', 'not_answered', 'answered', 'marked', 'answered_marked') DEFAULT 'NOT_VISITED',
    `is_correct` TINYINT(1) NULL,
    `marks_awarded` DECIMAL(6,2) DEFAULT 0,
    `time_spent_seconds` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_attempt_question` (`attempt_id`, `question_id`),
    KEY `idx_attempt` (`attempt_id`),
    KEY `idx_question` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure mt_test_attempts has complete status and score breakdown columns
ALTER TABLE `mt_test_attempts` MODIFY COLUMN `status` ENUM('in_progress', 'submitted', 'completed', 'time_up') DEFAULT 'in_progress';
ALTER TABLE `mt_test_attempts` ADD COLUMN IF NOT EXISTS `total_correct` INT DEFAULT 0 AFTER `score`;
ALTER TABLE `mt_test_attempts` ADD COLUMN IF NOT EXISTS `total_incorrect` INT DEFAULT 0 AFTER `total_correct`;
ALTER TABLE `mt_test_attempts` ADD COLUMN IF NOT EXISTS `total_unanswered` INT DEFAULT 0 AFTER `total_incorrect`;
ALTER TABLE `mt_test_attempts` ADD COLUMN IF NOT EXISTS `attempted_questions` INT DEFAULT 0 AFTER `total_unanswered`;
ALTER TABLE `mt_test_attempts` ADD COLUMN IF NOT EXISTS `skipped_questions` INT DEFAULT 0 AFTER `attempted_questions`;
