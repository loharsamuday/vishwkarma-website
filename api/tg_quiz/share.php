<?php
// api/tg_quiz/share.php
// Telegram Complete Quiz Sharing API — Preview, Sharing, Destination Groups & Branding Settings

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/tg_helper.php';
require_once __DIR__ . '/tg_playable_helper.php';

// 1. Admin Authentication Check
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];

try {
    ensureTelegramPlayableQuizSchema($pdo);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Playable quiz database setup failed. Apply database/tg_playable_quiz_migration.sql and retry. Details: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 2. CSRF Token Validation
function validateCsrfToken() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        echo json_encode(['success' => false, 'message' => 'CSRF token invalid or expired. Please refresh the page.']);
        exit;
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
global $pdo;

switch ($action) {

    // ─────────────────────────────────────────────────────────────
    // GET quizzes — list all shareable quiz sets and mock tests
    // ─────────────────────────────────────────────────────────────
    case 'quizzes':
        try {
            $quizzes = getShareableQuizzes($pdo, $admin_id);
            echo json_encode([
                'success' => true,
                'data'    => $quizzes
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // GET chats — list all verified Telegram chats for this admin
    // ─────────────────────────────────────────────────────────────
    case 'chats':
        try {
            $stmt = $pdo->prepare("
                SELECT id, chat_id, chat_title, chat_type, can_send_polls, is_verified, verified_at
                FROM tg_chats
                WHERE admin_id = ? AND is_verified = 1
                ORDER BY updated_at DESC, created_at DESC
            ");
            $stmt->execute([$admin_id]);
            $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'chats'   => $chats
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // GET preview — preview formatted Telegram messages
    // ─────────────────────────────────────────────────────────────
    case 'preview':
        try {
            $quiz_type = trim($_GET['quiz_type'] ?? 'tg_set');
            $quiz_id   = (int)($_GET['quiz_id'] ?? 0);
            $includeExplanation = !empty($_GET['include_explanation']) && $_GET['include_explanation'] !== 'false';
            $explanationPos     = trim($_GET['explanation_position'] ?? 'answer_key');

            if (!$quiz_id) {
                echo json_encode(['success' => false, 'message' => 'Quiz ID required']);
                break;
            }

            $quizData = getQuizDetailsForSharing($pdo, $quiz_type, $quiz_id);
            if (!$quizData || empty($quizData['questions'])) {
                echo json_encode(['success' => false, 'message' => 'No questions found in selected quiz']);
                break;
            }

            $promo = getTelegramPromoSettings($pdo);
            $preview = buildCompleteQuizTelegramMessages($quizData, $promo, [
                'include_explanation'  => $includeExplanation,
                'explanation_position' => $explanationPos
            ]);

            echo json_encode([
                'success' => true,
                'quiz'    => [
                    'title'           => $quizData['title'],
                    'type'            => $quiz_type,
                    'total_questions' => $quizData['total_questions']
                ],
                'promo'   => $promo,
                'preview' => $preview
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // POST share — share complete quiz set to Telegram group
    // ─────────────────────────────────────────────────────────────
    case 'share':
        validateCsrfToken();
        $quiz_type        = trim($_POST['quiz_type'] ?? 'tg_set');
        $quiz_id          = (int)($_POST['quiz_id'] ?? 0);
        $chat_id          = trim($_POST['chat_id'] ?? '');
        $confirmDuplicate = !empty($_POST['confirm_duplicate']) && $_POST['confirm_duplicate'] !== '0' && $_POST['confirm_duplicate'] !== 'false';
        $includeExplanation = !empty($_POST['include_explanation']) && $_POST['include_explanation'] !== '0' && $_POST['include_explanation'] !== 'false';
        $explanationPos     = trim($_POST['explanation_position'] ?? 'answer_key');

        if (!$quiz_id || empty($chat_id)) {
            echo json_encode(['success' => false, 'message' => 'Quiz and destination group are required']);
            break;
        }

        try {
            $options = [
                'confirm_duplicate'    => $confirmDuplicate,
                'include_explanation'  => $includeExplanation,
                'explanation_position' => $explanationPos
            ];

            $res = shareCompleteQuizToTelegram($pdo, $admin_id, $quiz_type, $quiz_id, $chat_id, $options);

            if (!empty($res['success'])) {
                logActivity("Shared complete quiz '{$quiz_type}:{$quiz_id}' to Telegram chat '{$chat_id}'", 'admin', null, $admin_id);
            }

            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error sharing quiz: ' . $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // POST verify_chat — verify and add a new chat ID
    // ─────────────────────────────────────────────────────────────
    case 'verify_chat':
        validateCsrfToken();
        $raw_chat_id = trim($_POST['chat_id'] ?? '');
        if (empty($raw_chat_id) || !preg_match('/^-?\d+$/', $raw_chat_id)) {
            echo json_encode(['success' => false, 'message' => 'Invalid Chat ID format. Example: -1001234567890']);
            break;
        }
        $chat_id = $raw_chat_id;

        $token = getActiveBotToken($pdo);
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'No active bot configured. Connect a bot first.']);
            break;
        }

        // 1. Get Chat info from Telegram
        $chatResult = callTelegramAPI($token, 'getChat', ['chat_id' => $chat_id]);
        if (empty($chatResult['ok'])) {
            $err = $chatResult['description'] ?? 'Chat not found';
            echo json_encode([
                'success' => false,
                'message' => "Telegram group not found or inaccessible: {$err}. Please ensure your bot is added to the group first."
            ]);
            break;
        }

        $chat = $chatResult['result'];
        $chat_title = $chat['title'] ?? $chat['first_name'] ?? "Chat {$chat_id}";
        $chat_type  = $chat['type'] ?? 'supergroup';

        // 2. Check bot membership
        $botStmt = $pdo->prepare("SELECT bot_id FROM tg_bots WHERE admin_id = ? AND is_active = 1 LIMIT 1");
        $botStmt->execute([$admin_id]);
        $botId = $botStmt->fetchColumn();

        $can_post = true;
        $bot_status = 'member';

        if ($botId) {
            $memberResult = callTelegramAPI($token, 'getChatMember', ['chat_id' => $chat_id, 'user_id' => $botId]);
            if (!empty($memberResult['ok'])) {
                $member = $memberResult['result'];
                $bot_status = $member['status'] ?? 'unknown';
                if (in_array($bot_status, ['kicked', 'left'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => "The bot is not an active member of this group (status: {$bot_status}). Please add it to the group."
                    ]);
                    break;
                }
            }
        }

        // 3. Upsert chat into tg_chats
        $existCheck = $pdo->prepare("SELECT id FROM tg_chats WHERE admin_id = ? AND chat_id = ?");
        $existCheck->execute([$admin_id, $chat_id]);
        $existingId = $existCheck->fetchColumn();

        if ($existingId) {
            $pdo->prepare("
                UPDATE tg_chats
                SET chat_title = ?, chat_type = ?, can_send_polls = 1, is_verified = 1, verified_at = NOW()
                WHERE admin_id = ? AND chat_id = ?
            ")->execute([$chat_title, $chat_type, $admin_id, $chat_id]);
        } else {
            $pdo->prepare("
                INSERT INTO tg_chats (admin_id, chat_id, chat_title, chat_type, can_send_polls, is_verified, verified_at)
                VALUES (?, ?, ?, ?, 1, 1, NOW())
            ")->execute([$admin_id, $chat_id, $chat_title, $chat_type]);
        }

        logActivity("Added & verified Telegram chat: {$chat_title} ({$chat_id})", 'admin', null, $admin_id);

        echo json_encode([
            'success'    => true,
            'message'    => "✅ Telegram group '{$chat_title}' verified and added successfully!",
            'chat_id'    => $chat_id,
            'chat_title' => $chat_title,
            'chat_type'  => $chat_type,
            'bot_status' => $bot_status
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    // ─────────────────────────────────────────────────────────────
    // GET promo_settings — retrieve current promotion & branding
    // ─────────────────────────────────────────────────────────────
    case 'get_promo_settings':
        $settings = getTelegramPromoSettings($pdo);
        echo json_encode(['success' => true, 'settings' => $settings], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    // ─────────────────────────────────────────────────────────────
    // POST save_promo_settings — update promotion & branding
    // ─────────────────────────────────────────────────────────────
    case 'save_promo_settings':
        validateCsrfToken();
        $groupName     = trim($_POST['group_name'] ?? '');
        $groupLink     = trim($_POST['group_link'] ?? '');
        $adminUsername = trim($_POST['admin_username'] ?? '');
        $adminId       = trim($_POST['admin_id'] ?? '');
        $footerMsg     = trim($_POST['footer_message'] ?? '');

        $saved = saveTelegramPromoSettings($pdo, [
            'group_name'     => $groupName,
            'group_link'     => $groupLink,
            'admin_username' => $adminUsername,
            'admin_id'       => $adminId,
            'footer_message' => $footerMsg
        ]);

        if ($saved) {
            logActivity("Updated Telegram promotion settings", 'admin', null, $admin_id);
            echo json_encode(['success' => true, 'message' => 'Telegram promotion & branding settings updated successfully!']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save settings to database']);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // GET history — share history for this admin
    // ─────────────────────────────────────────────────────────────
    case 'history':
        try {
            $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
            $history = getQuizShareHistory($pdo, $admin_id, $limit);
            echo json_encode(['success' => true, 'history' => $history], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // POST share_playable — share playable quiz to one or multiple groups
    // ─────────────────────────────────────────────────────────────
    case 'share_playable':
        validateCsrfToken();
        $quiz_type    = trim($_POST['quiz_type'] ?? 'tg_set');
        $quiz_id      = (int)($_POST['quiz_id'] ?? 0);
        $chat_ids_raw = $_POST['chat_ids'] ?? [];
        $mode         = trim($_POST['mode'] ?? 'both');
        $timer_sec    = max(5, min(300, (int)($_POST['timer_seconds'] ?? 20)));
        $correct_mark = (float)($_POST['correct_mark'] ?? 1.00);
        $wrong_mark   = (float)($_POST['wrong_mark'] ?? 0.00);

        if (!is_array($chat_ids_raw)) {
            $chat_ids_raw = array_filter(array_map('trim', explode(',', (string)$chat_ids_raw)));
        }

        if (!$quiz_id || empty($chat_ids_raw)) {
            echo json_encode(['success' => false, 'message' => 'Please select a quiz and at least one destination group']);
            break;
        }

        if (!in_array($mode, ['individual', 'live', 'both'])) {
            $mode = 'both';
        }

        try {
            $options = [
                'timer_seconds' => $timer_sec,
                'correct_mark'  => $correct_mark,
                'wrong_mark'    => $wrong_mark
            ];
            $res = TelegramPlayableQuizEngine::sharePlayableQuiz($pdo, $admin_id, $quiz_type, $quiz_id, $chat_ids_raw, $mode, $options);
            if (!empty($res['success'])) {
                ensureTelegramQuizDaemonRunning();
            }
            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error sharing playable quiz: ' . $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // GET preview_playable — preview invitation message and buttons
    // ─────────────────────────────────────────────────────────────
    case 'preview_playable':
        try {
            $quiz_type = trim($_GET['quiz_type'] ?? 'tg_set');
            $quiz_id   = (int)($_GET['quiz_id'] ?? 0);
            $mode      = trim($_GET['mode'] ?? 'both');
            $timer_sec = max(5, min(300, (int)($_GET['timer_seconds'] ?? 20)));

            if (!$quiz_id) {
                echo json_encode(['success' => false, 'message' => 'Quiz ID required']);
                break;
            }

            $quizData = getQuizDetailsForSharing($pdo, $quiz_type, $quiz_id);
            if (!$quizData || empty($quizData['questions'])) {
                echo json_encode(['success' => false, 'message' => 'No questions found in selected quiz']);
                break;
            }

            $botStmt = $pdo->prepare("SELECT bot_username FROM tg_bots WHERE admin_id = ? AND is_active = 1 LIMIT 1");
            $botStmt->execute([$admin_id]);
            $botUsername = $botStmt->fetchColumn() ?: 'YourQuizBot';
            $botUsername = ltrim($botUsername, '@');

            $promo = getTelegramPromoSettings($pdo);
            $invitation = TelegramPlayableQuizEngine::buildInvitationMessage(
                $quizData['title'],
                (int)$quizData['total_questions'],
                $timer_sec,
                $mode,
                $botUsername,
                'preview_token',
                $promo
            );

            echo json_encode([
                'success'    => true,
                'quiz_title' => $quizData['title'],
                'total_q'    => $quizData['total_questions'],
                'mode'       => $mode,
                'text'       => $invitation['text'],
                'buttons'    => $invitation['reply_markup']['inline_keyboard'] ?? []
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // POST start_live — start a common live group quiz
    // ─────────────────────────────────────────────────────────────
    case 'start_live':
        validateCsrfToken();
        $shared_quiz_id = (int)($_POST['shared_quiz_id'] ?? 0);
        if (!$shared_quiz_id) {
            echo json_encode(['success' => false, 'message' => 'Shared quiz ID required']);
            break;
        }

        try {
            // Verify ownership
            $chk = $pdo->prepare("SELECT admin_id FROM tg_shared_quizzes WHERE id = ?");
            $chk->execute([$shared_quiz_id]);
            if ($chk->fetchColumn() != $admin_id) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized quiz access']);
                break;
            }

            $res = TelegramPlayableQuizEngine::startCommonLiveQuiz($pdo, $shared_quiz_id);
            if (!empty($res['success'])) {
                ensureTelegramQuizDaemonRunning();
            }
            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error starting live quiz: ' . $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // POST pause_live — pause countdown timer on active live quiz
    // ─────────────────────────────────────────────────────────────
    case 'pause_live':
        validateCsrfToken();
        $group_session_id = (int)($_POST['group_session_id'] ?? 0);
        if (!$group_session_id) {
            echo json_encode(['success' => false, 'message' => 'Group session ID required']);
            break;
        }

        try {
            $res = TelegramPlayableQuizEngine::pauseLiveGroupQuiz($pdo, $group_session_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // POST resume_live — resume timer on paused live quiz
    // ─────────────────────────────────────────────────────────────
    case 'resume_live':
        validateCsrfToken();
        $group_session_id = (int)($_POST['group_session_id'] ?? 0);
        if (!$group_session_id) {
            echo json_encode(['success' => false, 'message' => 'Group session ID required']);
            break;
        }

        try {
            $res = TelegramPlayableQuizEngine::resumeLiveGroupQuiz($pdo, $group_session_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // POST stop_live — terminate live quiz prematurely and post results
    // ─────────────────────────────────────────────────────────────
    case 'stop_live':
        validateCsrfToken();
        $group_session_id = (int)($_POST['group_session_id'] ?? 0);
        if (!$group_session_id) {
            echo json_encode(['success' => false, 'message' => 'Group session ID required']);
            break;
        }

        try {
            $res = TelegramPlayableQuizEngine::stopLiveGroupQuiz($pdo, $group_session_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // POST advance_live — manually push live quiz to next question
    // ─────────────────────────────────────────────────────────────
    case 'advance_live':
        validateCsrfToken();
        $group_session_id = (int)($_POST['group_session_id'] ?? 0);
        if (!$group_session_id) {
            echo json_encode(['success' => false, 'message' => 'Group session ID required']);
            break;
        }

        try {
            $res = TelegramPlayableQuizEngine::advanceLiveGroupQuiz($pdo, $group_session_id, true);
            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // GET live_status — retrieve real-time state of a live session
    // ─────────────────────────────────────────────────────────────
    case 'live_status':
        $group_session_id = (int)($_GET['group_session_id'] ?? 0);
        $shared_quiz_id   = (int)($_GET['shared_quiz_id'] ?? 0);

        try {
            if ($group_session_id > 0) {
                $stmt = $pdo->prepare("
                    SELECT g.*, q.quiz_title, q.chat_title, q.quiz_type, q.quiz_id
                    FROM tg_group_quiz_sessions g
                    JOIN tg_shared_quizzes q ON g.shared_quiz_id = q.id
                    WHERE g.id = ? AND q.admin_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$group_session_id, $admin_id]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT g.*, q.quiz_title, q.chat_title, q.quiz_type, q.quiz_id
                    FROM tg_group_quiz_sessions g
                    JOIN tg_shared_quizzes q ON g.shared_quiz_id = q.id
                    WHERE g.shared_quiz_id = ? AND q.admin_id = ?
                    ORDER BY g.id DESC
                    LIMIT 1
                ");
                $stmt->execute([$shared_quiz_id, $admin_id]);
            }

            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$session) {
                echo json_encode(['success' => false, 'message' => 'No live session found']);
                break;
            }

            // Calculate remaining seconds
            $timeRemaining = 0;
            if ($session['status'] === 'active' && !empty($session['timer_deadline'])) {
                $timeRemaining = max(0, strtotime($session['timer_deadline']) - time());
            }

            // Fetch recent answers
            $ansStmt = $pdo->prepare("
                SELECT user_first_name, username, created_at
                FROM tg_group_quiz_answers
                WHERE group_session_id = ? AND question_index = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $ansStmt->execute([(int)$session['id'], (int)$session['current_question_index']]);
            $recentAnswers = $ansStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'        => true,
                'session'        => $session,
                'time_remaining' => $timeRemaining,
                'recent_answers' => $recentAnswers
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // GET playable_quizzes_list — list shared playable quizzes
    // ─────────────────────────────────────────────────────────────
    case 'playable_quizzes_list':
        try {
            $stmt = $pdo->prepare("
                SELECT sq.*,
                       g.id AS active_group_session_id,
                       g.status AS group_session_status,
                       g.current_question_index,
                       g.total_questions AS live_total_questions,
                       g.total_participants AS live_participants,
                       (SELECT COUNT(DISTINCT user_id) FROM tg_individual_quiz_sessions WHERE shared_quiz_id = sq.id) AS individual_participants,
                       (SELECT COUNT(*) FROM tg_individual_quiz_sessions WHERE shared_quiz_id = sq.id AND status = 'completed') AS individual_completions
                FROM tg_shared_quizzes sq
                LEFT JOIN tg_group_quiz_sessions g ON g.shared_quiz_id = sq.id AND g.status IN ('waiting', 'active', 'paused')
                WHERE sq.admin_id = ?
                ORDER BY sq.id DESC
                LIMIT 100
            ");
            $stmt->execute([$admin_id]);
            $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => $quizzes
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // GET leaderboard — group live quiz rankings
    // ─────────────────────────────────────────────────────────────
    case 'leaderboard':
        $group_session_id = (int)($_GET['group_session_id'] ?? 0);
        if (!$group_session_id) {
            echo json_encode(['success' => false, 'message' => 'Group session ID required']);
            break;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT
                    user_id,
                    user_first_name,
                    username,
                    SUM(score_delta) AS total_score,
                    SUM(is_correct) AS correct_count,
                    COUNT(*) AS total_answered,
                    ROUND(SUM(time_taken_seconds), 2) AS total_time
                FROM tg_group_quiz_answers
                WHERE group_session_id = ?
                GROUP BY user_id, user_first_name, username
                ORDER BY total_score DESC, correct_count DESC, total_time ASC, user_id ASC
            ");
            $stmt->execute([$group_session_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $rankings = [];
            $rank = 1;
            foreach ($rows as $r) {
                $name = $r['user_first_name'] ?: ($r['username'] ? '@' . $r['username'] : 'User ' . $r['user_id']);
                $rankings[] = [
                    'rank'           => $rank++,
                    'user_id'        => $r['user_id'],
                    'name'           => $name,
                    'username'       => $r['username'],
                    'score'          => (float)$r['total_score'],
                    'correct_count'  => (int)$r['correct_count'],
                    'total_answered' => (int)$r['total_answered'],
                    'total_time'     => (float)$r['total_time']
                ];
            }

            echo json_encode([
                'success'      => true,
                'leaderboard'  => $rankings,
                'total_users'  => count($rankings)
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // GET individual_sessions — list individual quiz participants
    // ─────────────────────────────────────────────────────────────
    case 'individual_sessions':
        $shared_quiz_id = (int)($_GET['shared_quiz_id'] ?? 0);
        try {
            $query = "
                SELECT s.*, q.quiz_title, q.chat_title
                FROM tg_individual_quiz_sessions s
                JOIN tg_shared_quizzes q ON s.shared_quiz_id = q.id
                WHERE q.admin_id = ?
            ";
            $params = [$admin_id];

            if ($shared_quiz_id > 0) {
                $query .= " AND s.shared_quiz_id = ?";
                $params[] = $shared_quiz_id;
            }

            $query .= " ORDER BY s.id DESC LIMIT 150";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'  => true,
                'sessions' => $sessions
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // GET reporting_stats — aggregate reporting analytics
    // ─────────────────────────────────────────────────────────────
    case 'reporting_stats':
        try {
            $stats = TelegramPlayableQuizEngine::getPlayableReportingStats($pdo, $admin_id);
            echo json_encode([
                'success' => true,
                'stats'   => $stats
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // POST delete_shared_quiz — remove a shared quiz record
    // ─────────────────────────────────────────────────────────────
    case 'delete_shared_quiz':
        validateCsrfToken();
        $shared_quiz_id = (int)($_POST['shared_quiz_id'] ?? 0);
        if (!$shared_quiz_id) {
            echo json_encode(['success' => false, 'message' => 'Shared quiz ID required']);
            break;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM tg_shared_quizzes WHERE id = ? AND admin_id = ?");
            $stmt->execute([$shared_quiz_id, $admin_id]);
            echo json_encode(['success' => true, 'message' => 'Shared quiz record removed']);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // GET daemon_status — check if background polling daemon is active
    // ─────────────────────────────────────────────────────────────
    case 'daemon_status':
        $isActive = isTelegramQuizDaemonActive();
        echo json_encode([
            'success'   => true,
            'is_active' => $isActive
        ]);
        break;

    // ─────────────────────────────────────────────────────────────
    // POST start_daemon — launch the background polling daemon
    // ─────────────────────────────────────────────────────────────
    case 'start_daemon':
        validateCsrfToken();
        $started = ensureTelegramQuizDaemonRunning();
        echo json_encode([
            'success' => $started,
            'message' => $started ? 'Telegram Quiz Background Polling Service is now running!' : 'Failed to launch background daemon'
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}
