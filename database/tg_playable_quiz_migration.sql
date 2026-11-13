-- ============================================================
-- Telegram Playable Quiz Sharing System — Database Migration
-- Supports Both Modes: Individual Quiz + Common Live Group Quiz
-- Safe to execute multiple times (uses IF NOT EXISTS / ON DUPLICATE)
-- ============================================================

-- 1. Shared Quizzes Table (Invitations and Group Configurations)
CREATE TABLE IF NOT EXISTS `tg_shared_quizzes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT UNSIGNED NOT NULL,
    `share_token` VARCHAR(64) NOT NULL UNIQUE,
    `quiz_type` ENUM('tg_set', 'mt_mock') NOT NULL DEFAULT 'tg_set',
    `quiz_id` INT UNSIGNED NOT NULL,
    `quiz_title` VARCHAR(500) NOT NULL,
    `total_questions` INT UNSIGNED DEFAULT 0,
    `chat_id` BIGINT NOT NULL COMMENT 'Destination Telegram Group ID',
    `chat_title` VARCHAR(500) DEFAULT NULL,
    `mode` ENUM('individual', 'live', 'both') NOT NULL DEFAULT 'both',
    `timer_seconds` INT UNSIGNED DEFAULT 20,
    `correct_mark` DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    `wrong_mark` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `include_explanation` TINYINT(1) DEFAULT 1,
    `telegram_invitation_message_id` BIGINT DEFAULT NULL,
    `status` ENUM('ACTIVE', 'PAUSED', 'COMPLETED', 'EXPIRED') DEFAULT 'ACTIVE',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_token` (`share_token`),
    INDEX `idx_admin` (`admin_id`),
    INDEX `idx_chat` (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Individual Quiz Sessions Table (Private Chat Mode A)
CREATE TABLE IF NOT EXISTS `tg_individual_quiz_sessions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_token` VARCHAR(64) NOT NULL UNIQUE,
    `shared_quiz_id` INT UNSIGNED NOT NULL,
    `quiz_type` ENUM('tg_set', 'mt_mock') NOT NULL DEFAULT 'tg_set',
    `quiz_id` INT UNSIGNED NOT NULL,
    `admin_id` INT UNSIGNED NOT NULL,
    `telegram_user_id` BIGINT NOT NULL,
    `telegram_username` VARCHAR(255) DEFAULT NULL,
    `telegram_first_name` VARCHAR(255) DEFAULT NULL,
    `telegram_last_name` VARCHAR(255) DEFAULT NULL,
    `source_group_id` BIGINT NOT NULL,
    `current_question_index` INT UNSIGNED DEFAULT 0,
    `total_questions` INT UNSIGNED DEFAULT 0,
    `question_ids_json` LONGTEXT DEFAULT NULL,
    `score` DECIMAL(8,2) DEFAULT 0.00,
    `correct_count` INT UNSIGNED DEFAULT 0,
    `wrong_count` INT UNSIGNED DEFAULT 0,
    `unattempted_count` INT UNSIGNED DEFAULT 0,
    `current_message_id` BIGINT DEFAULT NULL,
    `question_started_at` DATETIME DEFAULT NULL,
    `status` ENUM('RUNNING', 'COMPLETED', 'ABANDONED') DEFAULT 'RUNNING',
    `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`telegram_user_id`),
    INDEX `idx_shared` (`shared_quiz_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Individual Quiz Answers Table
CREATE TABLE IF NOT EXISTS `tg_individual_quiz_answers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT UNSIGNED NOT NULL,
    `telegram_user_id` BIGINT NOT NULL,
    `question_index` INT UNSIGNED NOT NULL,
    `question_id` INT UNSIGNED NOT NULL,
    `selected_option` CHAR(1) NOT NULL,
    `correct_option` CHAR(1) NOT NULL,
    `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
    `marks_awarded` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `time_taken_seconds` INT UNSIGNED DEFAULT 0,
    `answered_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_session_q` (`session_id`, `question_index`),
    INDEX `idx_user` (`telegram_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Common Live Group Quiz Sessions Table (Group Mode B)
CREATE TABLE IF NOT EXISTS `tg_group_quiz_sessions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `shared_quiz_id` INT UNSIGNED NOT NULL,
    `quiz_type` ENUM('tg_set', 'mt_mock') NOT NULL DEFAULT 'tg_set',
    `quiz_id` INT UNSIGNED NOT NULL,
    `quiz_title` VARCHAR(500) NOT NULL,
    `admin_id` INT UNSIGNED NOT NULL,
    `telegram_group_id` BIGINT NOT NULL,
    `telegram_topic_id` BIGINT DEFAULT NULL,
    `status` ENUM('PENDING', 'RUNNING', 'PAUSED', 'COMPLETED', 'STOPPED') DEFAULT 'PENDING',
    `current_question_index` INT UNSIGNED DEFAULT 0,
    `total_questions` INT UNSIGNED DEFAULT 0,
    `question_ids_json` LONGTEXT DEFAULT NULL,
    `timer_seconds` INT UNSIGNED DEFAULT 20,
    `correct_mark` DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    `wrong_mark` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `question_started_at` DATETIME DEFAULT NULL,
    `question_deadline` DATETIME DEFAULT NULL,
    `current_message_id` BIGINT DEFAULT NULL,
    `leaderboard_message_id` BIGINT DEFAULT NULL,
    `answers_count` INT UNSIGNED DEFAULT 0,
    `lock_version` INT UNSIGNED DEFAULT 0,
    `started_at` DATETIME DEFAULT NULL,
    `paused_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_group_status` (`telegram_group_id`, `status`),
    INDEX `idx_deadline` (`status`, `question_deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Common Live Group Quiz Answers Table
CREATE TABLE IF NOT EXISTS `tg_group_quiz_answers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_session_id` INT UNSIGNED NOT NULL,
    `telegram_group_id` BIGINT NOT NULL,
    `question_index` INT UNSIGNED NOT NULL,
    `question_id` INT UNSIGNED NOT NULL,
    `telegram_user_id` BIGINT NOT NULL,
    `telegram_username` VARCHAR(255) DEFAULT NULL,
    `telegram_first_name` VARCHAR(255) DEFAULT NULL,
    `telegram_last_name` VARCHAR(255) DEFAULT NULL,
    `selected_option` CHAR(1) NOT NULL,
    `correct_option` CHAR(1) NOT NULL,
    `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
    `marks_awarded` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `time_taken_seconds` INT UNSIGNED DEFAULT 0,
    `answered_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_group_q_user` (`group_session_id`, `question_index`, `telegram_user_id`),
    INDEX `idx_user` (`telegram_user_id`),
    INDEX `idx_sess_user` (`group_session_id`, `telegram_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Add default settings for group & live timers if not present
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('tg_default_individual_timer', '30'),
('tg_default_group_timer', '20')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
