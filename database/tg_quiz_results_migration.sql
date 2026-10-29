-- Telegram quiz results: scoring settings, voter answers, and worker update offset.
ALTER TABLE tg_quiz_sessions
  ADD COLUMN IF NOT EXISTS correct_mark DECIMAL(8,2) NOT NULL DEFAULT 1.00,
  ADD COLUMN IF NOT EXISTS wrong_mark DECIMAL(8,2) NOT NULL DEFAULT -0.25;

ALTER TABLE tg_bots
  ADD COLUMN IF NOT EXISTS last_update_id BIGINT NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS tg_quiz_responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    question_number INT UNSIGNED NOT NULL,
    telegram_poll_id VARCHAR(128) NOT NULL,
    user_id BIGINT NOT NULL,
    username VARCHAR(255) DEFAULT NULL,
    first_name VARCHAR(255) DEFAULT NULL,
    last_name VARCHAR(255) DEFAULT NULL,
    selected_option_id TINYINT UNSIGNED NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    answered_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_poll_user (telegram_poll_id, user_id),
    INDEX idx_session_user (session_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
