-- Telegram Quiz migration: add per-question time limits and pause/resume state support
-- Run this on an existing database that already has tg_quiz_questions and tg_quiz_sessions.

ALTER TABLE tg_quiz_questions
  ADD COLUMN IF NOT EXISTS time_limit INT UNSIGNED NULL COMMENT 'seconds for this question; NULL uses quiz default';

ALTER TABLE tg_quiz_sessions
  ADD COLUMN IF NOT EXISTS current_question_id INT UNSIGNED NULL COMMENT 'currently active question id',
  ADD COLUMN IF NOT EXISTS remaining_time INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'seconds left in current question',
  ADD COLUMN IF NOT EXISTS started_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS paused_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS stopped_at DATETIME NULL;

-- Optional: backfill current_question_id for running sessions using current_question index and question_ids_json
-- If question_ids_json is empty, no backfill occurs.
UPDATE tg_quiz_sessions s
LEFT JOIN JSON_TABLE(
  CONCAT('[', TRIM(LEADING '[' FROM TRIM(TRAILING ']' FROM s.question_ids_json)), ']'),
  '$[*]' COLUMNS (`question_id` INT PATH '$')
) jt
SET s.current_question_id = jt.question_id
WHERE s.status IN ('RUNNING','PAUSED')
  AND s.current_question > 0
  AND s.question_ids_json IS NOT NULL;

-- For any existing active sessions, default remaining time to the session timer so the current question keeps its value.
UPDATE tg_quiz_sessions
SET remaining_time = COALESCE(remaining_time, timer_seconds)
WHERE status IN ('RUNNING','PAUSED');
