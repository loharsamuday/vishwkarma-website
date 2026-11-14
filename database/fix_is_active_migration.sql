-- ============================================================
-- Telegram Quiz Manager — Fix for Unknown column 'is_active' in 'WHERE'
-- File: database/fix_is_active_migration.sql
-- Run this in InfinityFree phpMyAdmin -> SQL tab
-- ============================================================

-- Step 1: Add is_active to tg_quiz_questions (Main cause of error)
ALTER TABLE `tg_quiz_questions` ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1;

-- If your MySQL doesn't support IF NOT EXISTS, run this line instead:
-- ALTER TABLE `tg_quiz_questions` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1;

-- Step 2: Add time_limit to tg_quiz_questions if not present
ALTER TABLE `tg_quiz_questions` ADD COLUMN IF NOT EXISTS `time_limit` INT UNSIGNED NULL;

-- Step 3: Add is_active to tg_quiz_sets if not present
ALTER TABLE `tg_quiz_sets` ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1;

-- Step 4: Add is_active to tg_bots if not present
ALTER TABLE `tg_bots` ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE `tg_bots` ADD COLUMN IF NOT EXISTS `last_update_id` BIGINT NOT NULL DEFAULT 0;

-- Step 5: Ensure all questions are active
UPDATE `tg_quiz_questions` SET `is_active` = 1 WHERE `is_active` IS NULL;
UPDATE `tg_quiz_sets` SET `is_active` = 1 WHERE `is_active` IS NULL;
UPDATE `tg_bots` SET `is_active` = 1 WHERE `is_active` IS NULL;
