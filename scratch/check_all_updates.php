<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/tg_quiz/tg_helper.php';

$token = getActiveBotToken($pdo);
$res = callTelegramAPI($token, 'getUpdates', [
    'offset'          => 0,
    'limit'           => 100,
    'allowed_updates' => ['message', 'callback_query', 'poll_answer', 'my_chat_member']
]);

echo "Count: " . count($res['result'] ?? []) . "\n";
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo "\n";
