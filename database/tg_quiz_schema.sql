-- ============================================================
-- Telegram Bulk Quiz Manager — Database Schema
-- File: database/tg_quiz_schema.sql
-- Safe to run multiple times (uses IF NOT EXISTS)
-- Does NOT modify any existing tables
-- ============================================================

-- 1. Bot Configurations (encrypted token stored)
CREATE TABLE IF NOT EXISTS `tg_bots` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT UNSIGNED NOT NULL,
    `bot_token_encrypted` TEXT NOT NULL COMMENT 'AES-256 encrypted token',
    `bot_name` VARCHAR(255) DEFAULT NULL,
    `bot_username` VARCHAR(255) DEFAULT NULL,
    `bot_id` BIGINT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_update_id` BIGINT NOT NULL DEFAULT 0,
    `last_verified_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Verified Telegram Chats (groups/channels)
CREATE TABLE IF NOT EXISTS `tg_chats` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT UNSIGNED NOT NULL,
    `chat_id` BIGINT NOT NULL COMMENT 'Telegram chat_id (negative for groups)',
    `chat_title` VARCHAR(500) DEFAULT NULL,
    `chat_type` ENUM('group','supergroup','channel','private') DEFAULT 'supergroup',
    `can_send_polls` TINYINT(1) DEFAULT 0,
    `is_verified` TINYINT(1) DEFAULT 0,
    `verified_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_admin_chat` (`admin_id`, `chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Quiz Sets (Banking Quiz Set 1, Indian Polity, etc.)
CREATE TABLE IF NOT EXISTS `tg_quiz_sets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(500) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `total_questions` INT UNSIGNED DEFAULT 0,
    `default_timer` INT UNSIGNED DEFAULT 15 COMMENT 'seconds per question',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Quiz Questions
CREATE TABLE IF NOT EXISTS `tg_quiz_questions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `quiz_set_id` INT UNSIGNED NOT NULL,
    `question_number` INT UNSIGNED NOT NULL,
    `question_text` TEXT NOT NULL,
    `option_a` VARCHAR(1000) NOT NULL,
    `option_b` VARCHAR(1000) NOT NULL,
    `option_c` VARCHAR(1000) DEFAULT NULL,
    `option_d` VARCHAR(1000) DEFAULT NULL,
    `correct_answer` ENUM('A','B','C','D') NOT NULL,
    `explanation` TEXT DEFAULT NULL,
    `time_limit` INT UNSIGNED DEFAULT NULL COMMENT 'seconds for this question; NULL uses quiz default',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_quiz_set` (`quiz_set_id`),
    INDEX `idx_question_number` (`quiz_set_id`, `question_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Quiz Sessions (live state tracking)
CREATE TABLE IF NOT EXISTS `tg_quiz_sessions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `quiz_set_id` INT UNSIGNED NOT NULL,
    `admin_id` INT UNSIGNED NOT NULL,
    `chat_id` BIGINT NOT NULL,
    `chat_title` VARCHAR(500) DEFAULT NULL,
    `title` VARCHAR(500) DEFAULT NULL COMMENT 'custom title for this run',
    `status` ENUM('PENDING','RUNNING','PAUSED','COMPLETED','STOPPED','FAILED') DEFAULT 'PENDING',
    `total_questions` INT UNSIGNED DEFAULT 0,
    `current_question` INT UNSIGNED DEFAULT 0 COMMENT '0-indexed, starts from 0',
    `current_question_id` INT UNSIGNED DEFAULT NULL COMMENT 'currently active question id',
    `questions_sent` INT UNSIGNED DEFAULT 0,
    `question_order` ENUM('original','random') DEFAULT 'original',
    `question_ids_json` LONGTEXT DEFAULT NULL COMMENT 'JSON array of question IDs in send order',
    `timer_seconds` INT UNSIGNED DEFAULT 15,
    `correct_mark` DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    `wrong_mark` DECIMAL(8,2) NOT NULL DEFAULT -0.25,
    `remaining_time` INT UNSIGNED DEFAULT 0 COMMENT 'seconds left in current question',
    `start_question` INT UNSIGNED DEFAULT 1 COMMENT '1-indexed range start',
    `end_question` INT UNSIGNED DEFAULT 0 COMMENT '0 = all',
    `started_at` DATETIME DEFAULT NULL,
    `start_time` DATETIME DEFAULT NULL,
    `paused_at` DATETIME DEFAULT NULL,
    `stopped_at` DATETIME DEFAULT NULL,
    `end_time` DATETIME DEFAULT NULL,
    `last_question_sent_at` DATETIME DEFAULT NULL,
    `next_question_at` DATETIME DEFAULT NULL COMMENT 'worker sends next q after this timestamp',
    `last_telegram_message_id` BIGINT DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `retry_count` INT UNSIGNED DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_quiz_set_id` (`quiz_set_id`),
    INDEX `idx_next_question` (`status`, `next_question_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Quiz Question Logs (per-question tracking)
CREATE TABLE IF NOT EXISTS `tg_quiz_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT UNSIGNED NOT NULL,
    `question_id` INT UNSIGNED NOT NULL,
    `question_number` INT UNSIGNED NOT NULL,
    `status` ENUM('SENT','FAILED','SKIPPED','RETRY') DEFAULT 'SENT',
    `telegram_poll_id` VARCHAR(255) DEFAULT NULL,
    `sent_at` DATETIME DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `retry_count` INT UNSIGNED DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_session_id` (`session_id`),
    INDEX `idx_question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Per-user answers received from non-anonymous Telegram quiz polls
CREATE TABLE IF NOT EXISTS `tg_quiz_responses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT UNSIGNED NOT NULL,
    `question_id` INT UNSIGNED NOT NULL,
    `question_number` INT UNSIGNED NOT NULL,
    `telegram_poll_id` VARCHAR(128) NOT NULL,
    `user_id` BIGINT NOT NULL,
    `username` VARCHAR(255) DEFAULT NULL,
    `first_name` VARCHAR(255) DEFAULT NULL,
    `last_name` VARCHAR(255) DEFAULT NULL,
    `selected_option_id` TINYINT UNSIGNED NOT NULL,
    `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
    `answered_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_poll_user` (`telegram_poll_id`, `user_id`),
    INDEX `idx_session_user` (`session_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
