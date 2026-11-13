-- ============================================================
-- Telegram Complete Quiz Sharing Migration
-- File: database/tg_quiz_share_migration.sql
-- Safe to execute multiple times (uses IF NOT EXISTS / ON DUPLICATE)
-- ============================================================

-- 1. Table to record complete quiz sharing history
CREATE TABLE IF NOT EXISTS `tg_quiz_shares` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT UNSIGNED NOT NULL,
    `quiz_type` ENUM('tg_set', 'mt_mock') NOT NULL DEFAULT 'tg_set',
    `quiz_id` INT UNSIGNED NOT NULL,
    `quiz_title` VARCHAR(500) NOT NULL,
    `chat_id` BIGINT NOT NULL,
    `chat_title` VARCHAR(500) DEFAULT NULL,
    `total_questions` INT UNSIGNED DEFAULT 0,
    `parts_count` INT UNSIGNED DEFAULT 1,
    `status` ENUM('SUCCESS', 'FAILED') DEFAULT 'SUCCESS',
    `telegram_message_ids` TEXT DEFAULT NULL COMMENT 'JSON array of Telegram message IDs',
    `error_message` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_admin_id` (`admin_id`),
    INDEX `idx_quiz` (`quiz_type`, `quiz_id`),
    INDEX `idx_chat_id` (`chat_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Configurable promotion and branding settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('tg_promo_group_name', 'Bankers Lifes❤️'),
('tg_promo_group_link', 'https://t.me/bankersguts'),
('tg_promo_admin_username', '@Abhijeet_Mishra'),
('tg_promo_admin_id', '9272349548'),
('tg_promo_footer_message', 'Stay connected for more quizzes, mock tests and exam preparation.')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
