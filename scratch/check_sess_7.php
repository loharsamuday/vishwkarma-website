<?php
require_once __DIR__ . '/../includes/db.php';
$stmt = $pdo->query("SELECT id, status, current_question_index, total_questions, question_deadline, answers_count FROM tg_group_quiz_sessions WHERE id = 7");
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
