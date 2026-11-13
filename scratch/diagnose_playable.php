<?php
require_once __DIR__ . '/../includes/db.php';

echo "=== TG_SHARED_QUIZZES ===\n";
$shares = $pdo->query("SELECT id, share_token, quiz_type, quiz_id, quiz_title, chat_id, chat_title, mode, status, created_at FROM tg_shared_quizzes ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($shares, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== TG_INDIVIDUAL_QUIZ_SESSIONS ===\n";
$indiv = $pdo->query("SELECT id, session_token, shared_quiz_id, telegram_user_id, telegram_username, current_question_index, total_questions, status, started_at, completed_at FROM tg_individual_quiz_sessions ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($indiv, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== TG_GROUP_QUIZ_SESSIONS ===\n";
$group = $pdo->query("SELECT id, shared_quiz_id, quiz_title, telegram_group_id, status, current_question_index, total_questions, answers_count, started_at, completed_at FROM tg_group_quiz_sessions ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($group, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
