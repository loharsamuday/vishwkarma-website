<?php
// api/tg_quiz/tg_group.php
// Telegram Group / Channel Verification & Management API
// Replaces chat.php to prevent HTTP 403 Forbidden on web hosts (such as InfinityFree) that block URLs containing "chat"

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/tg_helper.php';

// Ensure required tables exist
if (isset($pdo) && $pdo instanceof PDO && function_exists('ensureTgTablesExist')) {
    ensureTgTablesExist($pdo);
}

// Admin authorization check
if (!isset($_SESSION['admin_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Please log in to admin dashboard.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// CSRF token validation without emitting 403 HTTP status to avoid web-server error document override
function validateCsrfTokenSafe() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        echo json_encode([
            'success' => false,
            'message' => 'Security token invalid or session expired. Please refresh the page and try again.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$action   = $_GET['action'] ?? $_POST['action'] ?? '';
$admin_id = (int)$_SESSION['admin_id'];

switch ($action) {

    // --------------------------------------------------------
    // GET list — all verified chats/groups for this admin
    // --------------------------------------------------------
    case 'list':
        $stmt = $pdo->prepare("SELECT id, chat_id, chat_title, chat_type, can_send_polls, is_verified, verified_at FROM tg_chats WHERE admin_id = ? ORDER BY created_at DESC");
        $stmt->execute([$admin_id]);
        echo json_encode([
            'success' => true,
            'chats'   => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    // --------------------------------------------------------
    // POST verify — verify group/channel and bot permissions
    // --------------------------------------------------------
    case 'verify':
        validateCsrfTokenSafe();
        $raw_chat_id = trim($_POST['chat_id'] ?? $_POST['target_id'] ?? $_POST['group_id'] ?? '');
        if (empty($raw_chat_id)) {
            echo json_encode(['success' => false, 'message' => 'Chat ID required.']);
            break;
        }

        // Clean any spaces
        $clean_chat_id = preg_replace('/\s+/', '', $raw_chat_id);

        // Build list of candidate chat IDs to try
        $candidates = [];
        if (preg_match('/^@[\w_]+$/', $clean_chat_id)) {
            // Public channel/group username (e.g. @my_channel)
            $candidates[] = $clean_chat_id;
        } elseif (preg_match('/^-?\d+$/', $clean_chat_id)) {
            // Numeric chat ID
            // 1. If user typed e.g. 1004457706126 (12-14 digits starting with 100, missed the minus sign)
            if (!str_starts_with($clean_chat_id, '-') && str_starts_with($clean_chat_id, '100')) {
                $candidates[] = '-' . $clean_chat_id; // Try supergroup format first
                $candidates[] = $clean_chat_id;
            } elseif (str_starts_with($clean_chat_id, '-')) {
                // Already negative
                $candidates[] = $clean_chat_id;
                // If negative but missed -100 prefix (e.g. -4457706126)
                if (!str_starts_with($clean_chat_id, '-100') && strlen($clean_chat_id) >= 8) {
                    $candidates[] = '-100' . ltrim($clean_chat_id, '-');
                }
            } else {
                // Positive number without 100 prefix (e.g. 4457706126)
                $candidates[] = '-100' . $clean_chat_id;
                $candidates[] = '-' . $clean_chat_id;
                $candidates[] = $clean_chat_id;
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid Chat ID format. Must be like -1001234567890 or @channelusername.'
            ]);
            break;
        }

        // Get active bot token
        $stmt = $pdo->prepare("SELECT bot_token_encrypted, bot_id FROM tg_bots WHERE admin_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$admin_id]);
        $botRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$botRow) {
            echo json_encode([
                'success' => false,
                'message' => 'No active bot configured. Please connect your bot first in Step 1.'
            ]);
            break;
        }

        $token = decryptToken($botRow['bot_token_encrypted']);
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Failed to decrypt bot token. Please reconnect the bot.']);
            break;
        }

        // Try candidate chat IDs with Telegram getChat
        $chatResult = null;
        $matchedCandidate = null;
        $lastErrorDescription = '';

        foreach ($candidates as $cand) {
            $res = callTelegramAPI($token, 'getChat', ['chat_id' => $cand]);
            if (!empty($res['ok']) && !empty($res['result'])) {
                $chatResult = $res['result'];
                $matchedCandidate = $cand;
                break;
            } else {
                $lastErrorDescription = $res['description'] ?? 'Chat not found';
            }
        }

        if (!$chatResult) {
            // Suggest auto-corrected format if user forgot minus sign
            $suggestFix = '';
            if (!str_starts_with($clean_chat_id, '-')) {
                $suggestFix = " Note: Supergroup/Channel IDs always start with a minus sign (e.g. <code>-" . htmlspecialchars($clean_chat_id) . "</code> or <code>-100" . htmlspecialchars($clean_chat_id) . "</code>).";
            }
            echo json_encode([
                'success' => false,
                'message' => 'Chat not found or bot is not in the chat. Please ensure the bot is added to your group/channel as an Administrator.' . $suggestFix,
                'detail'  => $lastErrorDescription
            ]);
            break;
        }

        // Canonical chat ID from Telegram
        $chat_id    = (string)$chatResult['id'];
        $chat_title = $chatResult['title'] ?? $chatResult['first_name'] ?? 'Unknown Chat';
        $chat_type  = $chatResult['type'] ?? 'supergroup';

        // Get bot ID (fetch via getMe if missing)
        $botId = $botRow['bot_id'] ?? null;
        if (empty($botId)) {
            $me = callTelegramAPI($token, 'getMe');
            if (!empty($me['ok']) && !empty($me['result']['id'])) {
                $botId = $me['result']['id'];
                $pdo->prepare("UPDATE tg_bots SET bot_id = ? WHERE admin_id = ?")->execute([$botId, $admin_id]);
            }
        }

        // Check bot member permissions in this chat
        $can_post   = false;
        $can_polls  = false;
        $bot_status = 'unknown';

        if (!empty($botId)) {
            $memberResult = callTelegramAPI($token, 'getChatMember', ['chat_id' => $chat_id, 'user_id' => $botId]);
            if (!empty($memberResult['ok']) && !empty($memberResult['result'])) {
                $member = $memberResult['result'];
                $bot_status = $member['status'] ?? 'unknown';
                if (in_array($bot_status, ['creator', 'administrator'])) {
                    $can_post  = true;
                    // In supergroups, admins typically have all poll permissions
                    $can_polls = $member['can_post_messages'] ?? $member['can_manage_topics'] ?? true;
                } elseif ($bot_status === 'member') {
                    // In standard groups, regular members can send messages and polls
                    $can_post  = in_array($chat_type, ['group', 'supergroup']);
                    $can_polls = $can_post;
                }
            }
        } else {
            // Default assumed true if getChat succeeded
            $can_post  = true;
            $can_polls = true;
        }

        // Upsert chat record in tg_chats
        try {
            $existCheck = $pdo->prepare("SELECT id FROM tg_chats WHERE admin_id = ? AND chat_id = ?");
            $existCheck->execute([$admin_id, $chat_id]);
            $existing = $existCheck->fetchColumn();

            if ($existing) {
                $pdo->prepare("
                    UPDATE tg_chats 
                    SET chat_title = ?, chat_type = ?, can_send_polls = ?, is_verified = 1, verified_at = NOW() 
                    WHERE admin_id = ? AND chat_id = ?
                ")->execute([$chat_title, $chat_type, $can_polls ? 1 : 0, $admin_id, $chat_id]);
            } else {
                $pdo->prepare("
                    INSERT INTO tg_chats (admin_id, chat_id, chat_title, chat_type, can_send_polls, is_verified, verified_at) 
                    VALUES (?, ?, ?, ?, ?, 1, NOW())
                ")->execute([$admin_id, $chat_id, $chat_title, $chat_type, $can_polls ? 1 : 0]);
            }

            if (function_exists('logActivity')) {
                logActivity("Verified Telegram chat: {$chat_title} ({$chat_id})", 'admin', null, $admin_id);
            }

            echo json_encode([
                'success'        => true,
                'chat_id'        => $chat_id,
                'chat_title'     => $chat_title,
                'chat_type'      => $chat_type,
                'bot_status'     => $bot_status,
                'can_send_polls' => (bool)$can_polls,
                'checks' => [
                    'bot_connected'   => true,
                    'chat_found'      => true,
                    'bot_in_chat'     => in_array($bot_status, ['creator', 'administrator', 'member']),
                    'can_send_polls'  => (bool)$can_polls,
                ],
                'message' => $can_polls
                    ? "✅ Group '{$chat_title}' (ID: {$chat_id}) verified! Bot is ready to send quiz polls."
                    : "⚠️ Group '{$chat_title}' found, but bot needs Administrator permissions to send polls.",
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Database error saving chat: ' . $e->getMessage()
            ]);
        }
        break;

    // --------------------------------------------------------
    // POST test_send — send a test poll to chat to verify permissions
    // --------------------------------------------------------
    case 'test_send':
        validateCsrfTokenSafe();
        $chat_id = trim($_POST['chat_id'] ?? '');
        if (empty($chat_id)) {
            echo json_encode(['success' => false, 'message' => 'Chat ID required']);
            break;
        }

        $stmt = $pdo->prepare("SELECT bot_token_encrypted FROM tg_bots WHERE admin_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$admin_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'No active bot configured. Connect a bot first.']);
            break;
        }
        $token = decryptToken($row['bot_token_encrypted']);

        $testQuestion = [
            'question_text'  => '🎯 [Test Question] भारत की राजधानी क्या है?',
            'option_a'       => 'मुंबई',
            'option_b'       => 'नई दिल्ली',
            'option_c'       => 'पटना',
            'option_d'       => 'जयपुर',
            'correct_answer' => 'B',
            'explanation'    => 'भारत की राजधानी नई दिल्ली है।'
        ];

        $pollRes = sendQuizPoll($token, $chat_id, $testQuestion, 0, 1, 1);

        if (!empty($pollRes['ok'])) {
            echo json_encode([
                'success' => true,
                'message' => '✅ Test quiz poll successfully sent to group! Check your Telegram group.',
                'poll_id' => $pollRes['result']['poll']['id'] ?? null
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Telegram Error: ' . ($pollRes['description'] ?? 'Failed to send poll'),
                'error_code' => $pollRes['error_code'] ?? null
            ]);
        }
        break;

    // --------------------------------------------------------
    // POST delete — remove a chat record
    // --------------------------------------------------------
    case 'delete':
        validateCsrfTokenSafe();
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM tg_chats WHERE id=? AND admin_id=?")->execute([$id, $admin_id]);
        echo json_encode(['success' => true, 'message' => 'Chat removed']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
