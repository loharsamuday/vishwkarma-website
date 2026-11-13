<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/tg_quiz/tg_helper.php';
require_once __DIR__ . '/../api/tg_quiz/tg_playable_helper.php';

$token = getActiveBotToken($pdo);
// Let's test startIndividualSession with an admin user ID:
$adminIdStmt = $pdo->query("SELECT admin_id FROM tg_shared_quizzes WHERE id = 11");
$adminId = $adminIdStmt->fetchColumn() ?: 1;

// Call startIndividualSession
$res = TelegramPlayableQuizEngine::startIndividualSession(
    $pdo,
    'qz_fd077ef65ff1310a9bba7b6f2f672c9b',
    9272349548, // Real bot/admin Telegram user ID
    ['id' => 9272349548, 'first_name' => 'Abhijeet', 'username' => 'Abhijeet_Mishra'],
    9272349548
);

echo "startIndividualSession Result:\n";
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
