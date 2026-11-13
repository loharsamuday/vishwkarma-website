<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/tg_quiz/tg_helper.php';
require_once __DIR__ . '/../api/tg_quiz/tg_playable_helper.php';

$token = getActiveBotToken($pdo);
echo "Bot Token: " . substr($token, 0, 10) . "...\n";

// Test quiz 11
$shareToken = "qz_fd077ef65ff1310a9bba7b6f2f672c9b";

// Fetch destination chat ID
$stmt = $pdo->prepare("SELECT * FROM tg_shared_quizzes WHERE id = 11");
$stmt->execute();
$shared = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Quiz 11 Details:\n";
echo json_encode($shared, JSON_PRETTY_PRINT) . "\n\n";

// Let's check getChatMember or send test message to group
$groupMsg = callTelegramAPI($token, 'sendMessage', [
    'chat_id' => $shared['chat_id'],
    'text'    => "🤖 <b>Bot Diagnostic Test</b>\nTesting bot communication with this group...",
    'parse_mode' => 'HTML'
]);

echo "Group Message Test Result:\n";
echo json_encode($groupMsg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
