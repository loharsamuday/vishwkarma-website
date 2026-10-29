<?php
// api/tg_quiz/worker_sync.php
// ============================================================
// Secure REST Sync Bridge for External Python Quiz Worker
// ============================================================
// Allows the Python worker to communicate with the online
// database via HTTPS instead of direct MySQL connection.
//
// Authentication: X-Worker-Secret header must match TG_WORKER_SECRET
// All responses are JSON. Bot tokens are NEVER returned in plaintext.
// ============================================================

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/tg_helper.php';

// ── Authentication via Worker Secret Key ────────────────────────
$worker_secret = defined('TG_WORKER_SECRET') ? TG_WORKER_SECRET : '';
$provided_secret = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';

if (empty($worker_secret) || strlen($worker_secret) < 16 || !hash_equals($worker_secret, $provided_secret)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ── Simple Rate Limiting (120 requests/minute per IP) ───────────
$rate_file = sys_get_temp_dir() . '/tgw_rate_' . md5($_SERVER['REMOTE_ADDR'] ?? 'cli');
$rate_data = @file_get_contents($rate_file);
$rate = $rate_data ? @json_decode($rate_data, true) : null;
if (!is_array($rate) || (time() - ($rate['t'] ?? 0)) > 60) {
    $rate = ['c' => 0, 't' => time()];
}
$rate['c']++;
@file_put_contents($rate_file, json_encode($rate));
if ($rate['c'] > 120) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Rate limit exceeded']);
    exit;
}

// ── Action Router ───────────────────────────────────────────────
$action = $_GET['action'] ?? $_POST['action'] ?? '';
global $pdo;

switch ($action) {

    // ─────────────────────────────────────────────────────────────
    // HEARTBEAT — health check
    // ─────────────────────────────────────────────────────────────
    case 'heartbeat':
        echo json_encode([
            'success'     => true,
            'message'     => 'Worker Sync API is active',
            'server_time' => date('Y-m-d H:i:s'),
            'timezone'    => date_default_timezone_get(),
        ]);
        break;

    // ─────────────────────────────────────────────────────────────
    // SYNC POLL ANSWERS — retrieve non-anonymous poll votes
    // ─────────────────────────────────────────────────────────────
    case 'sync_poll_answers':
        try {
            $botStmt = $pdo->query("
                SELECT DISTINCT b.id, b.admin_id, b.bot_token_encrypted, b.last_update_id
                FROM tg_bots b
                JOIN tg_quiz_sessions s ON s.admin_id = b.admin_id
                WHERE b.is_active = 1 AND s.status = 'RUNNING'
            ");
            $bots = $botStmt->fetchAll(PDO::FETCH_ASSOC);
            $allCaughtUp = true;
            $syncFailed = false;

            foreach ($bots as $bot) {
                $token = decryptToken($bot['bot_token_encrypted']);
                if ($token === '') {
                    $allCaughtUp = false;
                    $syncFailed = true;
                    continue;
                }

                $telegram = callTelegramAPI($token, 'getUpdates', [
                    'offset' => (int)$bot['last_update_id'] + 1,
                    'limit' => 100,
                    'timeout' => 0,
                    'allowed_updates' => ['poll_answer'],
                ]);
                if (empty($telegram['ok'])) {
                    $allCaughtUp = false;
                    $syncFailed = true;
                    continue;
                }

                $updates = $telegram['result'] ?? [];
                if (count($updates) >= 100) {
                    $allCaughtUp = false;
                }

                foreach ($updates as $update) {
                    try {
                        $answer = $update['poll_answer'] ?? null;
                        if ($answer) {
                            $pollId = (string)($answer['poll_id'] ?? '');
                            $userId = (int)($answer['user']['id'] ?? 0);
                            $options = $answer['option_ids'] ?? [];

                            if ($pollId !== '' && $userId > 0) {
                                $questionStmt = $pdo->prepare("
                                    SELECT l.session_id, l.question_id, l.question_number,
                                           q.correct_answer, q.option_a, q.option_b, q.option_c, q.option_d
                                    FROM tg_quiz_logs l
                                    JOIN tg_quiz_sessions s ON s.id = l.session_id
                                    JOIN tg_quiz_questions q ON q.id = l.question_id
                                    WHERE l.telegram_poll_id = ? AND l.status = 'SENT'
                                      AND s.status = 'RUNNING' AND s.admin_id = ?
                                    LIMIT 1
                                ");
                                $questionStmt->execute([$pollId, $bot['admin_id']]);
                                $question = $questionStmt->fetch(PDO::FETCH_ASSOC);

                                if ($question && !$options) {
                                    $pdo->prepare("
                                        DELETE FROM tg_quiz_responses
                                        WHERE telegram_poll_id = ? AND user_id = ?
                                    ")->execute([$pollId, $userId]);
                                } elseif ($question && $options) {
                                    $letters = [];
                                    foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field) {
                                        if (!empty($question[$field])) $letters[] = $letter;
                                    }
                                    $correctLetter = strtoupper($question['correct_answer'] ?? 'A');
                                    $correctIndex = array_search($correctLetter, $letters, true);
                                    if ($correctIndex === false) $correctIndex = 0;
                                    $selectedIndex = (int)$options[0];
                                    $user = $answer['user'] ?? [];

                                    $saveStmt = $pdo->prepare("
                                        INSERT INTO tg_quiz_responses
                                            (session_id, question_id, question_number, telegram_poll_id,
                                             user_id, username, first_name, last_name, selected_option_id, is_correct)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE
                                            question_id = VALUES(question_id),
                                            question_number = VALUES(question_number),
                                            username = VALUES(username),
                                            first_name = VALUES(first_name),
                                            last_name = VALUES(last_name),
                                            selected_option_id = VALUES(selected_option_id),
                                            is_correct = VALUES(is_correct),
                                            answered_at = NOW()
                                    ");
                                    $saveStmt->execute([
                                        $question['session_id'], $question['question_id'], $question['question_number'],
                                        $pollId, $userId, $user['username'] ?? null, $user['first_name'] ?? null,
                                        $user['last_name'] ?? null, $selectedIndex, (int)($selectedIndex === $correctIndex),
                                    ]);
                                }
                            }
                        }

                        $pdo->prepare("UPDATE tg_bots SET last_update_id = ? WHERE id = ?")
                            ->execute([(int)$update['update_id'], (int)$bot['id']]);
                    } catch (Throwable $e) {
                        $allCaughtUp = false;
                        $syncFailed = true;
                        break;
                    }
                }
            }

            if ($allCaughtUp) {
                $pdo->exec("
                    UPDATE tg_quiz_sessions
                    SET status = 'COMPLETED', end_time = NOW(), updated_at = NOW()
                    WHERE status = 'RUNNING' AND current_question >= total_questions
                      AND next_question_at <= NOW()
                ");
            }

            echo json_encode([
                'success' => !$syncFailed,
                'message' => $syncFailed ? 'Telegram poll answers could not be synchronized; check worker connectivity and webhook settings' : 'Poll answers synchronized',
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not synchronize poll answers']);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // GET PENDING TASKS — RUNNING sessions ready for next question
    // Returns session info + encrypted bot token + question data
    // ─────────────────────────────────────────────────────────────
    case 'get_pending_tasks':
        try {
            $stmt = $pdo->prepare("
                SELECT s.id AS session_id, s.quiz_set_id, s.admin_id, s.chat_id,
                       s.chat_title, s.title, s.status, s.total_questions,
                       s.current_question, s.questions_sent, s.question_order,
                       s.question_ids_json, s.timer_seconds, s.retry_count,
                       s.next_question_at,
                       b.bot_token_encrypted
                FROM tg_quiz_sessions s
                JOIN tg_bots b ON b.admin_id = s.admin_id AND b.is_active = 1
                WHERE s.status = 'RUNNING'
                  AND s.next_question_at <= NOW()
                  AND s.current_question < s.total_questions
                ORDER BY s.next_question_at ASC
                LIMIT 10
            ");
            $stmt->execute();
            $sessions = $stmt->fetchAll();

            $tasks = [];
            foreach ($sessions as $sess) {
                $question_ids = @json_decode($sess['question_ids_json'], true);
                $current_idx  = (int) $sess['current_question'];

                if (!is_array($question_ids) || $current_idx >= count($question_ids)) {
                    continue;
                }

                $q_id  = (int) $question_ids[$current_idx];
                $qStmt = $pdo->prepare("
                    SELECT id, question_text, option_a, option_b, option_c, option_d,
                           correct_answer, explanation
                    FROM tg_quiz_questions WHERE id = ? AND is_active = 1
                ");
                $qStmt->execute([$q_id]);
                $question = $qStmt->fetch();

                $tasks[] = [
                    'session_id'          => (int) $sess['session_id'],
                    'chat_id'             => $sess['chat_id'],
                    'timer_seconds'       => (int) $sess['timer_seconds'],
                    'current_question'    => (int) $sess['current_question'],
                    'total_questions'     => (int) $sess['total_questions'],
                    'retry_count'         => (int) $sess['retry_count'],
                    'bot_token_encrypted' => $sess['bot_token_encrypted'],
                    'question'            => $question ?: null,
                    'question_id'         => $q_id,
                    'question_number'     => $current_idx + 1,
                ];
            }

            echo json_encode(['success' => true, 'tasks' => $tasks, 'count' => count($tasks)]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // REPORT SENT — question successfully sent to Telegram
    // ─────────────────────────────────────────────────────────────
    case 'report_sent':
        $session_id      = (int) ($_POST['session_id'] ?? 0);
        $question_id     = (int) ($_POST['question_id'] ?? 0);
        $question_number = (int) ($_POST['question_number'] ?? 0);
        $poll_id         = trim($_POST['poll_id'] ?? '');
        $timer           = max(5, (int) ($_POST['timer_seconds'] ?? 15));

        if (!$session_id || !$question_id) {
            echo json_encode(['success' => false, 'message' => 'Missing session_id or question_id']);
            break;
        }

        try {
            // Verify session is still RUNNING (user may have paused/stopped)
            $chk = $pdo->prepare("SELECT status FROM tg_quiz_sessions WHERE id = ? AND status = 'RUNNING'");
            $chk->execute([$session_id]);
            if (!$chk->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Session no longer running', 'action' => 'stop']);
                break;
            }

            $next_at = date('Y-m-d H:i:s', time() + $timer);

            $pdo->beginTransaction();

            $pdo->prepare("
                UPDATE tg_quiz_sessions
                SET current_question        = current_question + 1,
                    questions_sent          = questions_sent + 1,
                    last_question_sent_at   = NOW(),
                    next_question_at        = ?,
                    last_telegram_message_id = ?,
                    retry_count             = 0,
                    updated_at              = NOW()
                WHERE id = ?
            ")->execute([$next_at, $poll_id, $session_id]);

            $pdo->prepare("
                INSERT INTO tg_quiz_logs
                    (session_id, question_id, question_number, status, telegram_poll_id, sent_at)
                VALUES (?, ?, ?, 'SENT', ?, NOW())
            ")->execute([$session_id, $question_id, $question_number, $poll_id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Question marked as sent']);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // REPORT FAILED — question send failed
    // ─────────────────────────────────────────────────────────────
    case 'report_failed':
        $session_id      = (int) ($_POST['session_id'] ?? 0);
        $question_id     = (int) ($_POST['question_id'] ?? 0);
        $question_number = (int) ($_POST['question_number'] ?? 0);
        $error_msg       = substr(trim($_POST['error_message'] ?? ''), 0, 500);
        $retry_count     = (int) ($_POST['retry_count'] ?? 0);
        $max_retries     = (int) ($_POST['max_retries'] ?? 3);
        $timer           = max(5, (int) ($_POST['timer_seconds'] ?? 15));
        $retry_delay     = max(5, (int) ($_POST['retry_delay'] ?? 10));

        if (!$session_id || !$question_id) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            break;
        }

        try {
            $pdo->beginTransaction();

            if ($retry_count >= $max_retries) {
                // Max retries reached — skip this question, move to next
                $next_at = date('Y-m-d H:i:s', time() + $timer);
                $pdo->prepare("
                    UPDATE tg_quiz_sessions
                    SET current_question = current_question + 1,
                        retry_count      = 0,
                        next_question_at = ?,
                        error_message    = ?,
                        updated_at       = NOW()
                    WHERE id = ?
                ")->execute([$next_at, $error_msg, $session_id]);
            } else {
                // Schedule retry after delay
                $next_at = date('Y-m-d H:i:s', time() + $retry_delay);
                $pdo->prepare("
                    UPDATE tg_quiz_sessions
                    SET retry_count      = ?,
                        next_question_at = ?,
                        error_message    = ?,
                        updated_at       = NOW()
                    WHERE id = ?
                ")->execute([$retry_count, $next_at, $error_msg, $session_id]);
            }

            $pdo->prepare("
                INSERT INTO tg_quiz_logs
                    (session_id, question_id, question_number, status, error_message, retry_count)
                VALUES (?, ?, ?, 'FAILED', ?, ?)
            ")->execute([$session_id, $question_id, $question_number, $error_msg, $retry_count]);

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Failure logged',
                'skipped' => ($retry_count >= $max_retries),
            ]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // MARK COMPLETED — all questions sent
    // ─────────────────────────────────────────────────────────────
    case 'mark_completed':
        $session_id = (int) ($_POST['session_id'] ?? 0);
        if (!$session_id) {
            echo json_encode(['success' => false, 'message' => 'Missing session_id']);
            break;
        }
        try {
            $pdo->prepare("
                UPDATE tg_quiz_sessions
                SET status = 'COMPLETED', end_time = NOW(), updated_at = NOW()
                WHERE id = ? AND status IN ('RUNNING','PAUSED')
            ")->execute([$session_id]);
            echo json_encode(['success' => true, 'message' => 'Session completed']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // MARK FAILED — unrecoverable error
    // ─────────────────────────────────────────────────────────────
    case 'mark_failed':
        $session_id = (int) ($_POST['session_id'] ?? 0);
        $error_msg  = substr(trim($_POST['error_message'] ?? ''), 0, 500);
        if (!$session_id) {
            echo json_encode(['success' => false, 'message' => 'Missing session_id']);
            break;
        }
        try {
            $pdo->prepare("
                UPDATE tg_quiz_sessions
                SET status = 'FAILED', end_time = NOW(), error_message = ?, updated_at = NOW()
                WHERE id = ? AND status IN ('RUNNING','PAUSED')
            ")->execute([$error_msg, $session_id]);
            echo json_encode(['success' => true, 'message' => 'Session marked failed']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // CHECK STATUS — pause/stop detection by worker
    // ─────────────────────────────────────────────────────────────
    case 'check_status':
        $session_id = (int) ($_GET['session_id'] ?? $_POST['session_id'] ?? 0);
        if (!$session_id) {
            echo json_encode(['success' => false, 'message' => 'Missing session_id']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT status, current_question, total_questions FROM tg_quiz_sessions WHERE id = ?");
            $stmt->execute([$session_id]);
            $row = $stmt->fetch();
            if ($row) {
                echo json_encode([
                    'success'          => true,
                    'status'           => $row['status'],
                    'current_question' => (int) $row['current_question'],
                    'total_questions'  => (int) $row['total_questions'],
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Session not found']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // REPORT RATE LIMIT — Telegram 429, delay next question
    // ─────────────────────────────────────────────────────────────
    case 'report_rate_limit':
        $session_id  = (int) ($_POST['session_id'] ?? 0);
        $retry_after = max(5, (int) ($_POST['retry_after'] ?? 30));
        if (!$session_id) {
            echo json_encode(['success' => false, 'message' => 'Missing session_id']);
            break;
        }
        try {
            $next_at = date('Y-m-d H:i:s', time() + $retry_after);
            $pdo->prepare("
                UPDATE tg_quiz_sessions SET next_question_at = ?, updated_at = NOW() WHERE id = ?
            ")->execute([$next_at, $session_id]);
            echo json_encode(['success' => true, 'message' => "Rate limit handled, retry in {$retry_after}s"]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    // SKIP QUESTION — missing or invalid question
    // ─────────────────────────────────────────────────────────────
    case 'skip_question':
        $session_id      = (int) ($_POST['session_id'] ?? 0);
        $question_id     = (int) ($_POST['question_id'] ?? 0);
        $question_number = (int) ($_POST['question_number'] ?? 0);
        if (!$session_id) {
            echo json_encode(['success' => false, 'message' => 'Missing session_id']);
            break;
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE tg_quiz_sessions SET current_question = current_question + 1, updated_at = NOW() WHERE id = ?")->execute([$session_id]);
            $pdo->prepare("INSERT INTO tg_quiz_logs (session_id, question_id, question_number, status) VALUES (?, ?, ?, 'SKIPPED')")->execute([$session_id, $question_id, $question_number]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Question skipped']);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    // ─────────────────────────────────────────────────────────────
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}
