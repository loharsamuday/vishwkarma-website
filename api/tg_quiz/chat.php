<?php
// api/tg_quiz/chat.php
// Telegram Chat Verification API

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function validateCsrfToken() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
        exit;
    }
}

function decryptToken(string $encrypted): string {
    $key = defined('TG_ENCRYPTION_KEY') ? TG_ENCRYPTION_KEY : (getenv('TG_ENCRYPTION_KEY') ?: 'default-32-char-key-change-this!!');
    $key = substr(hash('sha256', $key, true), 0, 32);
    $data = base64_decode($encrypted);
    $iv   = substr($data, 0, 16);
    $cipher = substr($data, 16);
    return openssl_decrypt($cipher, 'AES-256-CBC', $key, 0, $iv) ?: '';
}

function callTelegramAPI(string $token, string $method, array $params = []): array {
    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($params),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);
    if ($error) return ['ok' => false, 'description' => 'cURL error'];
    return json_decode($response, true) ?? ['ok' => false, 'description' => 'Invalid response'];
}

$action   = $_GET['action'] ?? $_POST['action'] ?? '';
$admin_id = (int)$_SESSION['admin_id'];

switch ($action) {

    // --------------------------------------------------------
    // GET list — all verified chats for this admin
    // --------------------------------------------------------
    case 'list':
        $stmt = $pdo->prepare("SELECT id, chat_id, chat_title, chat_type, can_send_polls, is_verified, verified_at FROM tg_chats WHERE admin_id = ? ORDER BY created_at DESC");
        $stmt->execute([$admin_id]);
        echo json_encode(['success' => true, 'chats' => $stmt->fetchAll()]);
        break;

    // --------------------------------------------------------
    // POST verify — verify a chat ID and check bot permissions
    // --------------------------------------------------------
    case 'verify':
        validateCsrfToken();
        $raw_chat_id = trim($_POST['chat_id'] ?? '');
        if (empty($raw_chat_id)) {
            echo json_encode(['success' => false, 'message' => 'Chat ID required']);
            break;
        }

        // Validate chat_id format (integer, can be negative)
        if (!preg_match('/^-?\d+$/', $raw_chat_id)) {
            echo json_encode(['success' => false, 'message' => 'Invalid Chat ID format. Example: -1001234567890']);
            break;
        }
        $chat_id = (int)$raw_chat_id;

        // Get bot token
        $stmt = $pdo->prepare("SELECT bot_token_encrypted FROM tg_bots WHERE admin_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$admin_id]);
        $row = $stmt->fetch();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'No active bot configured. Connect a bot first.']);
            break;
        }
        $token = decryptToken($row['bot_token_encrypted']);

        // Step 1: Get chat info
        $chatResult = callTelegramAPI($token, 'getChat', ['chat_id' => $chat_id]);
        if (!$chatResult['ok']) {
            echo json_encode([
                'success' => false,
                'message' => 'Chat not found or bot not in chat. Add the bot to the group/channel first.',
                'detail'  => $chatResult['description'] ?? ''
            ]);
            break;
        }
        $chat = $chatResult['result'];

        // Step 2: Get bot member status in chat
        $botStmt = $pdo->prepare("SELECT bot_id FROM tg_bots WHERE admin_id = ? AND is_active = 1 LIMIT 1");
        $botStmt->execute([$admin_id]);
        $botId = $botStmt->fetchColumn();

        $memberResult = callTelegramAPI($token, 'getChatMember', ['chat_id' => $chat_id, 'user_id' => $botId]);
        $can_post  = false;
        $can_polls = false;
        $bot_status = 'unknown';

        if ($memberResult['ok']) {
            $member = $memberResult['result'];
            $bot_status = $member['status'] ?? 'unknown';
            if (in_array($bot_status, ['creator', 'administrator'])) {
                $can_post  = true;
                $can_polls = $member['can_post_messages'] ?? true; // supergroups usually allow
            } elseif ($bot_status === 'member') {
                // Regular member can still send polls in groups (not channels)
                $can_post  = in_array($chat['type'], ['group', 'supergroup']);
                $can_polls = $can_post;
            }
        }

        // Upsert chat record
        $existCheck = $pdo->prepare("SELECT id FROM tg_chats WHERE admin_id=? AND chat_id=?");
        $existCheck->execute([$admin_id, $chat_id]);
        $existing = $existCheck->fetchColumn();

        $chat_title = $chat['title'] ?? $chat['first_name'] ?? 'Unknown';
        $chat_type  = $chat['type'] ?? 'supergroup';

        if ($existing) {
            $pdo->prepare("UPDATE tg_chats SET chat_title=?, chat_type=?, can_send_polls=?, is_verified=1, verified_at=NOW() WHERE admin_id=? AND chat_id=?"
            )->execute([$chat_title, $chat_type, $can_polls ? 1 : 0, $admin_id, $chat_id]);
        } else {
            $pdo->prepare("INSERT INTO tg_chats (admin_id, chat_id, chat_title, chat_type, can_send_polls, is_verified, verified_at) VALUES (?,?,?,?,?,1,NOW())"
            )->execute([$admin_id, $chat_id, $chat_title, $chat_type, $can_polls ? 1 : 0]);
        }

        logActivity("Verified Telegram chat: {$chat_title} ({$chat_id})", 'admin', null, $admin_id);

        echo json_encode([
            'success'      => true,
            'chat_id'      => $chat_id,
            'chat_title'   => $chat_title,
            'chat_type'    => $chat_type,
            'bot_status'   => $bot_status,
            'can_send_polls' => $can_polls,
            'checks' => [
                'bot_connected'   => true,
                'chat_found'      => true,
                'bot_in_chat'     => in_array($bot_status, ['creator', 'administrator', 'member']),
                'can_send_polls'  => $can_polls,
            ],
            'message' => $can_polls
                ? '✅ Chat verified! Bot can send quiz polls.'
                : '⚠️ Chat found but bot may not have send permissions. Make bot an administrator.',
        ]);
        break;

    // --------------------------------------------------------
    // POST delete — remove a chat record
    // --------------------------------------------------------
    case 'delete':
        validateCsrfToken();
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM tg_chats WHERE id=? AND admin_id=?")->execute([$id, $admin_id]);
        echo json_encode(['success' => true, 'message' => 'Chat removed']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
