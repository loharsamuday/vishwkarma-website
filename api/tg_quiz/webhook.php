<?php
// api/tg_quiz/webhook.php
// ============================================================
// Telegram Bot API Webhook Endpoint
// Compatible with Localhost (via HTTPS tunnel) and InfinityFree
// Handles: poll_answer, message, my_chat_member
// Authenticates incoming requests via X-Telegram-Bot-Api-Secret-Token
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/tg_helper.php';

// ── Webhook Logger (Tokens are NEVER logged) ───────────────────
function logWebhookEvent(string $type, string $details = ''): void {
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/telegram_webhook.log';
    $time = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $entry = "[{$time}] [IP: {$ip}] [TYPE: {$type}] {$details}" . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

// ── 1. Handle GET requests (Health check / verification) ────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'ok'          => true,
        'service'     => 'Telegram Webhook Handler',
        'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'undefined',
        'status'      => 'ready',
        'method'      => $_SERVER['REQUEST_METHOD'],
        'time'        => date('Y-m-d H:i:s')
    ]);
    exit;
}

// ── 2. Authenticate Secret Token ───────────────────────────────
// Telegram sends this header if configured during setWebhook
$configuredSecret = defined('TG_WEBHOOK_SECRET') ? TG_WEBHOOK_SECRET : '';
$receivedSecret   = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

if (!empty($configuredSecret)) {
    if (empty($receivedSecret) || !hash_equals($configuredSecret, $receivedSecret)) {
        http_response_code(403);
        logWebhookEvent('AUTH_FAILED', 'Invalid or missing X-Telegram-Bot-Api-Secret-Token header');
        echo json_encode(['ok' => false, 'error' => 'Forbidden: Invalid secret token']);
        exit;
    }
}

// Load database connection for event processing
require_once __DIR__ . '/../../includes/db.php';

// ── 3. Read & Validate Incoming Payload ────────────────────────
$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    echo json_encode(['ok' => true, 'description' => 'Empty request body']);
    exit;
}

$update = json_decode($rawInput, true);
if (!is_array($update)) {
    http_response_code(400);
    logWebhookEvent('JSON_ERROR', 'Payload is not valid JSON: ' . substr($rawInput, 0, 100));
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$updateId = $update['update_id'] ?? 0;

// ── 4. Process Event Types ─────────────────────────────────────
try {
    // ── A. Poll Answer Update (Quiz participation response) ────
    if (isset($update['poll_answer'])) {
        $answer  = $update['poll_answer'];
        $pollId  = (string)($answer['poll_id'] ?? '');
        $user    = $answer['user'] ?? [];
        $userId  = (int)($user['id'] ?? 0);
        $options = $answer['option_ids'] ?? [];

        if ($pollId !== '' && $userId > 0) {
            // Find corresponding quiz question from tg_quiz_logs
            $qStmt = $pdo->prepare("
                SELECT l.session_id, l.question_id, l.question_number,
                       q.correct_answer, q.option_a, q.option_b, q.option_c, q.option_d,
                       s.status AS session_status, s.admin_id
                FROM tg_quiz_logs l
                JOIN tg_quiz_sessions s ON s.id = l.session_id
                JOIN tg_quiz_questions q ON q.id = l.question_id
                WHERE l.telegram_poll_id = ?
                ORDER BY l.id DESC
                LIMIT 1
            ");
            $qStmt->execute([$pollId]);
            $question = $qStmt->fetch(PDO::FETCH_ASSOC);

            if ($question) {
                if (empty($options)) {
                    // User retracted their vote
                    $delStmt = $pdo->prepare("DELETE FROM tg_quiz_responses WHERE telegram_poll_id = ? AND user_id = ?");
                    $delStmt->execute([$pollId, $userId]);
                    logWebhookEvent('POLL_RETRACT', "Poll {$pollId}, User {$userId}");
                } else {
                    // User voted for an option
                    $selectedIndex = (int)$options[0];

                    // Map option letters A, B, C, D to indices
                    $letters = [];
                    foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field) {
                        if (!empty($question[$field])) {
                            $letters[] = $letter;
                        }
                    }
                    $correctIndex = array_search(strtoupper($question['correct_answer']), $letters, true);
                    if ($correctIndex === false) {
                        $correctIndex = 0;
                    }
                    $isCorrect = ($selectedIndex === $correctIndex) ? 1 : 0;

                    $username  = !empty($user['username']) ? substr($user['username'], 0, 255) : null;
                    $firstName = !empty($user['first_name']) ? substr($user['first_name'], 0, 255) : null;
                    $lastName  = !empty($user['last_name']) ? substr($user['last_name'], 0, 255) : null;

                    $saveStmt = $pdo->prepare("
                        INSERT INTO tg_quiz_responses
                            (session_id, question_id, question_number, telegram_poll_id,
                             user_id, username, first_name, last_name, selected_option_id, is_correct, answered_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                            username = VALUES(username),
                            first_name = VALUES(first_name),
                            last_name = VALUES(last_name),
                            selected_option_id = VALUES(selected_option_id),
                            is_correct = VALUES(is_correct),
                            answered_at = NOW()
                    ");
                    $saveStmt->execute([
                        (int)$question['session_id'],
                        (int)$question['question_id'],
                        (int)$question['question_number'],
                        $pollId,
                        $userId,
                        $username,
                        $firstName,
                        $lastName,
                        $selectedIndex,
                        $isCorrect
                    ]);

                    logWebhookEvent('POLL_ANSWER', "Poll {$pollId}, User {$userId} (@{$username}), Option {$selectedIndex}, Correct: {$isCorrect}");
                }
            } else {
                logWebhookEvent('POLL_NOT_FOUND', "Poll ID {$pollId} not found in tg_quiz_logs");
            }
        }
    }

    // ── B. Direct or Group Message Update ───────────────────────
    elseif (isset($update['message'])) {
        $message = $update['message'];
        $chat    = $message['chat'] ?? [];
        $chatId  = $chat['id'] ?? 0;
        $chatType= $chat['type'] ?? 'private';
        $text    = trim($message['text'] ?? '');
        $user    = $message['from'] ?? [];
        $userId  = $user['id'] ?? 0;

        logWebhookEvent('MESSAGE', "Chat: {$chatId} ({$chatType}), From: {$userId}, Text: " . substr($text, 0, 50));

        // Basic command handling
        if (strpos($text, '/start') === 0) {
            $token = getActiveBotToken($pdo);
            if ($token && $chatId) {
                callTelegramAPI($token, 'sendMessage', [
                    'chat_id'    => $chatId,
                    'text'       => "👋 Welcome to Vishwakarma Telegram Bot!\n\nThis bot is connected to the Vishwakarma platform for real-time quizzes and notifications.\nStatus: Active ✅",
                    'parse_mode' => 'HTML'
                ]);
            }
        } elseif (strpos($text, '/ping') === 0) {
            $token = getActiveBotToken($pdo);
            if ($token && $chatId) {
                callTelegramAPI($token, 'sendMessage', [
                    'chat_id' => $chatId,
                    'text'    => "🏓 Pong! Webhook is responding normally. (" . (defined('ENVIRONMENT') ? ENVIRONMENT : 'active') . ")"
                ]);
            }
        }
    }

    // ── C. Bot Membership / Status Update (my_chat_member) ─────
    elseif (isset($update['my_chat_member'])) {
        $membership = $update['my_chat_member'];
        $chat       = $membership['chat'] ?? [];
        $chatIdVal  = $chat['id'] ?? 0;
        $chatTitle  = $chat['title'] ?? 'N/A';
        $newStatus  = $membership['new_chat_member']['status'] ?? '';
        logWebhookEvent('CHAT_MEMBER', "Chat {$chatIdVal} ({$chatTitle}), Bot status: {$newStatus}");
    }

    // ── D. Other unhandled update types ─────────────────────────
    else {
        logWebhookEvent('UPDATE_OTHER', "Update ID {$updateId}");
    }

} catch (\Throwable $e) {
    logWebhookEvent('EXCEPTION', $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

// ── 5. Always Return 200 OK to Telegram ─────────────────────────
// Telegram requires a 200 OK response; otherwise it will continue to retry the webhook
echo json_encode(['ok' => true]);
