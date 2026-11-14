<?php
// api/tg_quiz/session.php
// Quiz Session Control API — Start / Pause / Resume / Stop + Status

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/tg_helper.php';
require_once __DIR__ . '/tg_results.php';

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

/**
 * Check if the background Telegram quiz daemon is currently active
 */
function isTelegramQuizDaemonActive(): bool {
    $lockFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tg_quiz_daemon.lock';
    if (!file_exists($lockFilePath)) {
        return false;
    }
    $fp = @fopen($lockFilePath, 'c+');
    if (!$fp) return false;
    $wouldBlock = false;
    $locked = flock($fp, LOCK_EX | LOCK_NB, $wouldBlock);
    if ($locked) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }
    fclose($fp);
    return true; // Another process currently holds the lock
}

/**
 * Ensures the background Telegram quiz daemon is running via PHP CLI on XAMPP.
 * Spawns detached background process without opening console window.
 */
function ensureTelegramQuizDaemonRunning(): bool {
    if (isTelegramQuizDaemonActive()) {
        return true;
    }

    $daemonScript = realpath(__DIR__ . '/../../bin/tg_quiz_daemon.php');
    if (!$daemonScript || !file_exists($daemonScript)) {
        return false;
    }

    $phpCli = 'C:\\xampp\\php\\php.exe';
    if (!file_exists($phpCli)) {
        $phpCli = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    }

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cmd = "start /B \"\" \"{$phpCli}\" \"{$daemonScript}\"";
        @pclose(@popen($cmd, "r"));
    } else {
        @exec("\"{$phpCli}\" \"{$daemonScript}\" > /dev/null 2>&1 &");
    }

    return true;
}

$action   = $_GET['action'] ?? $_POST['action'] ?? '';
$admin_id = (int)$_SESSION['admin_id'];
global $pdo;

switch ($action) {

    // --------------------------------------------------------
    // POST start — create a new quiz session with countdown & sync
    // --------------------------------------------------------
    case 'start':
        validateCsrfToken();
        $quiz_set_id        = (int)($_POST['quiz_set_id'] ?? 0);
        $chat_id            = trim($_POST['chat_id'] ?? '');
        $timer              = max(5, min(300, (int)($_POST['timer'] ?? 15)));
        $countdown          = isset($_POST['countdown']) ? max(0, min(300, (int)$_POST['countdown'])) : 30;
        $send_announcement  = !empty($_POST['send_announcement']) && $_POST['send_announcement'] !== '0' && $_POST['send_announcement'] !== 'false';
        $correct_mark       = round((float)($_POST['correct_mark'] ?? 1), 2);
        $wrong_mark         = round((float)($_POST['wrong_mark'] ?? -0.25), 2);
        $order              = ($_POST['question_order'] ?? 'original') === 'random' ? 'random' : 'original';
        $start_q            = max(1, (int)($_POST['start_question'] ?? 1));
        $end_q              = max(0, (int)($_POST['end_question'] ?? 0));
        $custom_title       = strip_tags(trim($_POST['title'] ?? ''));

        if (!$quiz_set_id || !$chat_id) {
            echo json_encode(['success' => false, 'message' => 'Quiz set and chat ID required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }
        if ($correct_mark < 0 || $correct_mark > 1000 || $wrong_mark > 0 || $wrong_mark < -1000) {
            echo json_encode(['success' => false, 'message' => 'Marks must be between 0 and 1000; wrong-answer marks must be between -1000 and 0'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        // Verify quiz set belongs to admin
        $setStmt = $pdo->prepare("SELECT * FROM tg_quiz_sets WHERE id=? AND admin_id=? AND is_active=1");
        $setStmt->execute([$quiz_set_id, $admin_id]);
        $quiz_set = $setStmt->fetch();
        if (!$quiz_set) {
            echo json_encode(['success'=>false,'message'=>'Quiz set not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        // Get total questions in quiz set
        $qStmt = $pdo->prepare("SELECT id, question_number FROM tg_quiz_questions WHERE quiz_set_id=? AND is_active=1 ORDER BY question_number ASC");
        $qStmt->execute([$quiz_set_id]);
        $all_rows = $qStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalInSet = count($all_rows);
        if ($totalInSet === 0) {
            echo json_encode(['success'=>false,'message'=>'No active questions found in this quiz set'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        if ($end_q <= 0 || $end_q > $totalInSet) {
            $end_q = $totalInSet;
        }
        if ($start_q > $end_q) {
            $start_q = 1;
        }

        // Apply range filter
        $start_idx = max(0, $start_q - 1);
        $end_idx   = min($end_q - 1, $totalInSet - 1);
        $filtered_rows = array_slice($all_rows, $start_idx, max(0, $end_idx - $start_idx + 1));
        $filtered = array_column($filtered_rows, 'id');

        if (empty($filtered)) {
            echo json_encode(['success'=>false,'message'=>'No questions found in the specified range'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        if ($order === 'random') shuffle($filtered);

        // Check for already running or countdown session for this quiz set
        $runCheck = $pdo->prepare("SELECT id FROM tg_quiz_sessions WHERE quiz_set_id=? AND status IN ('COUNTDOWN','RUNNING','PAUSED') LIMIT 1");
        $runCheck->execute([$quiz_set_id]);
        if ($runCheck->fetchColumn()) {
            echo json_encode(['success'=>false,'message'=>'A session for this quiz set is already active (countdown, running, or paused). Stop it first.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        // Verify chat
        $chatStmt = $pdo->prepare("SELECT chat_title FROM tg_chats WHERE admin_id=? AND chat_id=? AND is_verified=1");
        $chatStmt->execute([$admin_id, $chat_id]);
        $chatRow = $chatStmt->fetch();
        if (!$chatRow) {
            echo json_encode(['success'=>false,'message'=>'Chat not verified. Verify the chat ID first.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        $title = $custom_title ?: $quiz_set['title'];
        $total = count($filtered);

        $initialStatus    = ($countdown > 0) ? 'COUNTDOWN' : 'RUNNING';
        $countdownEndsAt  = ($countdown > 0) ? date('Y-m-d H:i:s', time() + $countdown) : null;
        $nextQuestionAt   = ($countdown > 0) ? $countdownEndsAt : date('Y-m-d H:i:s', time());

        $ins = $pdo->prepare("
            INSERT INTO tg_quiz_sessions
                (quiz_set_id, admin_id, chat_id, chat_title, title, status,
                 total_questions, current_question, questions_sent, question_order,
                 question_ids_json, timer_seconds, countdown_seconds, countdown_ends_at,
                 correct_mark, wrong_mark, start_question, end_question, start_time, next_question_at)
            VALUES
                (?, ?, ?, ?, ?, ?,
                 ?, 0, 0, ?,
                 ?, ?, ?, ?,
                 ?, ?, ?, ?, NOW(), ?)
        ");
        $ins->execute([
            $quiz_set_id, $admin_id, $chat_id, $chatRow['chat_title'], $title, $initialStatus,
            $total, $order, json_encode(array_values($filtered)), $timer, $countdown, $countdownEndsAt,
            $correct_mark, $wrong_mark, $start_q, $end_q, $nextQuestionAt
        ]);
        $session_id = (int)$pdo->lastInsertId();

        // 1. Send Pre-Quiz Announcement Alert if requested
        if ($send_announcement) {
            try {
                sendTelegramQuizAnnouncement($pdo, $session_id);
            } catch (\Throwable $e) {
                error_log("Pre-quiz announcement error: " . $e->getMessage());
            }
        }

        // 2. Either initialize Telegram countdown message OR dispatch first question immediately
        $dispatchRes = null;
        $qRangeNote = ($start_q > 1 || $end_q < $totalInSet) ? " (Questions #{$start_q} to #{$end_q})" : "";
        if ($countdown > 0) {
            // Live countdown on Telegram and Website simultaneously
            syncTelegramQuizLiveStatus($pdo, $session_id, 'countdown_start', $countdown);
            $msg = "🚀 Quiz initialized! Live {$countdown}s countdown started on Telegram & Website{$qRangeNote}.";
        } else {
            // Immediate Start without countdown
            $dispatchRes = dispatchSessionQuestion($pdo, $session_id, true);
            $firstQNum = $start_q;
            if (!empty($dispatchRes['question_number'])) {
                $firstQNum = (int)$dispatchRes['question_number'];
            }
            $msg = "Quiz started immediately! Question #{$firstQNum} sent to Telegram.";
        }

        // 3. Launch background PHP daemon on XAMPP (runs independently of browser window)
        ensureTelegramQuizDaemonRunning();

        logActivity("Started quiz session #{$session_id}: {$title} (Status: {$initialStatus})", 'admin', null, $admin_id);

        echo json_encode([
            'success'     => true,
            'message'     => $msg,
            'session_id'  => $session_id,
            'status'      => $initialStatus,
            'countdown'   => $countdown,
            'dispatch'    => $dispatchRes
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    // --------------------------------------------------------
    // POST announce — send pre-quiz announcement on demand
    // --------------------------------------------------------
    case 'announce':
        validateCsrfToken();
        $session_id = (int)($_POST['session_id'] ?? 0);
        $check = $pdo->prepare("SELECT id FROM tg_quiz_sessions WHERE id=? AND admin_id=?");
        $check->execute([$session_id, $admin_id]);
        if (!$check->fetchColumn()) {
            echo json_encode(['success'=>false,'message'=>'Session not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        $res = sendTelegramQuizAnnouncement($pdo, $session_id);
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    // --------------------------------------------------------
    // POST pause — pause the quiz and stop countdown on Telegram & Web
    // --------------------------------------------------------
    case 'pause':
        validateCsrfToken();
        $session_id = (int)($_POST['session_id'] ?? 0);
        $check = $pdo->prepare("SELECT * FROM tg_quiz_sessions WHERE id=? AND admin_id=? AND status IN ('RUNNING','COUNTDOWN')");
        $check->execute([$session_id, $admin_id]);
        $sess = $check->fetch(PDO::FETCH_ASSOC);
        if (!$sess) {
            echo json_encode(['success'=>false,'message'=>'Session not found or not active'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        $rem = 0;
        if ($sess['status'] === 'COUNTDOWN' && !empty($sess['countdown_ends_at'])) {
            $rem = max(0, strtotime($sess['countdown_ends_at']) - time());
        } elseif (!empty($sess['next_question_at'])) {
            $rem = max(0, strtotime($sess['next_question_at']) - time());
        }

        $pdo->prepare("UPDATE tg_quiz_sessions SET status='PAUSED', remaining_time=?, paused_at=NOW(), updated_at=NOW() WHERE id=?")
            ->execute([$rem, $session_id]);

        try {
            syncTelegramQuizLiveStatus($pdo, $session_id, 'pause', $rem);
        } catch (\Throwable $e) {}

        logActivity("Paused quiz session #{$session_id}", 'admin', null, $admin_id);
        echo json_encode(['success'=>true, 'message'=>'⏸ Quiz paused. Countdown stopped on Telegram and Website.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    // --------------------------------------------------------
    // POST resume — resume a paused quiz and continue countdown
    // --------------------------------------------------------
    case 'resume':
        validateCsrfToken();
        $session_id = (int)($_POST['session_id'] ?? 0);
        $check = $pdo->prepare("SELECT * FROM tg_quiz_sessions WHERE id=? AND admin_id=? AND status='PAUSED'");
        $check->execute([$session_id, $admin_id]);
        $sess = $check->fetch(PDO::FETCH_ASSOC);
        if (!$sess) {
            echo json_encode(['success'=>false,'message'=>'Session not found or not paused'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        $rem = max(3, (int)($sess['remaining_time'] ?: $sess['timer_seconds']));
        $next_at = date('Y-m-d H:i:s', time() + $rem);

        $isPreQuiz = ($sess['current_question'] == 0 && $sess['questions_sent'] == 0);
        $newStatus = $isPreQuiz ? 'COUNTDOWN' : 'RUNNING';
        $countdownEndsAt = $isPreQuiz ? $next_at : null;

        $pdo->prepare("
            UPDATE tg_quiz_sessions
            SET status=?, next_question_at=?, countdown_ends_at=?,
                remaining_time=0, paused_at=NULL, updated_at=NOW()
            WHERE id=?
        ")->execute([$newStatus, $next_at, $countdownEndsAt, $session_id]);

        try {
            syncTelegramQuizLiveStatus($pdo, $session_id, 'resume', $rem);
        } catch (\Throwable $e) {}

        ensureTelegramQuizDaemonRunning();

        logActivity("Resumed quiz session #{$session_id}", 'admin', null, $admin_id);
        echo json_encode(['success'=>true, 'message'=>'▶️ Quiz resumed! Live countdown continuing on Telegram and Website.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    // --------------------------------------------------------
    // POST stop — permanently stop the quiz
    // --------------------------------------------------------
    case 'stop':
        validateCsrfToken();
        $session_id = (int)($_POST['session_id'] ?? 0);
        $check = $pdo->prepare("SELECT id FROM tg_quiz_sessions WHERE id=? AND admin_id=? AND status IN ('COUNTDOWN','RUNNING','PAUSED')");
        $check->execute([$session_id, $admin_id]);
        if (!$check->fetchColumn()) {
            echo json_encode(['success'=>false,'message'=>'Session not found or already stopped'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        $pdo->prepare("UPDATE tg_quiz_sessions SET status='STOPPED', end_time=NOW(), updated_at=NOW() WHERE id=?")->execute([$session_id]);

        try {
            syncTelegramQuizLiveStatus($pdo, $session_id, 'completed');
        } catch (\Throwable $e) {}

        logActivity("Stopped quiz session #{$session_id}", 'admin', null, $admin_id);
        echo json_encode(['success'=>true, 'message'=>'Quiz stopped permanently.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    // --------------------------------------------------------
    // POST restart — restart a completed/stopped session
    // --------------------------------------------------------
    case 'restart':
        validateCsrfToken();
        $session_id = (int)($_POST['session_id'] ?? 0);
        $check = $pdo->prepare("SELECT * FROM tg_quiz_sessions WHERE id=? AND admin_id=?");
        $check->execute([$session_id, $admin_id]);
        $sess = $check->fetch(PDO::FETCH_ASSOC);
        if (!$sess) {
            echo json_encode(['success'=>false,'message'=>'Session not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        $pdo->prepare("
            UPDATE tg_quiz_sessions
            SET status='RUNNING', current_question=0, questions_sent=0,
                start_time=NOW(), end_time=NULL, next_question_at=NOW(),
                results_announced_at=NULL, results_claimed_at=NULL,
                telegram_status_message_id=NULL, last_telegram_status_text=NULL,
                error_message=NULL, retry_count=0, updated_at=NOW()
            WHERE id=?
        ")->execute([$session_id]);

        $pdo->prepare("DELETE FROM tg_quiz_logs WHERE session_id=?")->execute([$session_id]);
        $pdo->prepare("DELETE FROM tg_quiz_responses WHERE session_id=?")->execute([$session_id]);

        // Immediately send Question 1 on restart
        $dispatchRes = dispatchSessionQuestion($pdo, $session_id, true);
        ensureTelegramQuizDaemonRunning();

        logActivity("Restarted quiz session #{$session_id}", 'admin', null, $admin_id);
        echo json_encode(['success'=>true, 'message'=>'Quiz restarted! Question 1 sent to Telegram.', 'dispatch'=>$dispatchRes], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    // --------------------------------------------------------
    // POST finalize — manually publish final results to Telegram
    // --------------------------------------------------------
    case 'finalize':
        validateCsrfToken();
        $session_id = (int)($_POST['session_id'] ?? 0);
        $check = $pdo->prepare("SELECT id FROM tg_quiz_sessions WHERE id=? AND admin_id=?");
        $check->execute([$session_id, $admin_id]);
        if (!$check->fetchColumn()) {
            echo json_encode(['success'=>false, 'message'=>'Session not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        try {
            syncTelegramQuizLiveStatus($pdo, $session_id, 'completed');
        } catch (\Throwable $e) {}

        $res = finalizeTelegramQuiz($pdo, $session_id, true);
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    // --------------------------------------------------------
    // POST dispatch — manually or programmatically force send due/next question
    // --------------------------------------------------------
    case 'dispatch':
        validateCsrfToken();
        $session_id = (int)($_POST['session_id'] ?? 0);
        $check = $pdo->prepare("SELECT id FROM tg_quiz_sessions WHERE id=? AND admin_id=? AND status IN ('RUNNING','COUNTDOWN')");
        $check->execute([$session_id, $admin_id]);
        if (!$check->fetchColumn()) {
            echo json_encode(['success'=>false,'message'=>'Session not running'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        // If in countdown, dispatching forces it into RUNNING
        $pdo->prepare("UPDATE tg_quiz_sessions SET status='RUNNING' WHERE id=? AND status='COUNTDOWN'")->execute([$session_id]);

        $dispatchRes = dispatchSessionQuestion($pdo, $session_id, true); // force immediate send
        echo json_encode([
            'success' => !empty($dispatchRes['success']),
            'message' => $dispatchRes['message'] ?? 'Dispatched',
            'result'  => $dispatchRes
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    // --------------------------------------------------------
    // GET status — real-time status for live dashboard + auto-dispatch
    // --------------------------------------------------------
    case 'status':
        $session_id = (int)($_GET['session_id'] ?? 0);
        if (!$session_id) {
            // Return all active sessions for this admin
            $stmt = $pdo->prepare("SELECT id, title, chat_title, chat_id, status, total_questions, current_question, questions_sent, timer_seconds, countdown_seconds, start_time, next_question_at, results_announced_at, telegram_status_message_id, last_telegram_update, error_message FROM tg_quiz_sessions WHERE admin_id=? AND status IN ('COUNTDOWN','RUNNING','PAUSED') ORDER BY updated_at DESC");
            $stmt->execute([$admin_id]);
            echo json_encode(['success'=>true, 'sessions'=>$stmt->fetchAll()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        $stmt = $pdo->prepare("SELECT s.*, qs.title as quiz_set_title FROM tg_quiz_sessions s JOIN tg_quiz_sets qs ON qs.id=s.quiz_set_id WHERE s.id=? AND s.admin_id=?");
        $stmt->execute([$session_id, $admin_id]);
        $session = $stmt->fetch();
        if (!$session) {
            echo json_encode(['success'=>false,'message'=>'Session not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
        }

        if (in_array($session['status'], ['COUNTDOWN', 'RUNNING'])) {
            ensureTelegramQuizDaemonRunning();
        }

        $dispatchInfo = null;
        $countdown_remaining = 0;
        $seconds_remaining = 0;

        // 1. COUNTDOWN STATE HANDLING
        if ($session['status'] === 'COUNTDOWN') {
            $countdown_ends_ts = !empty($session['countdown_ends_at']) ? strtotime($session['countdown_ends_at']) : 0;
            $countdown_remaining = max(0, $countdown_ends_ts - time());

            if ($countdown_remaining > 0) {
                // Update live countdown in Telegram
                try {
                    syncTelegramQuizLiveStatus($pdo, $session, 'countdown', $countdown_remaining);
                } catch (\Throwable $e) {}
            } else {
                // Countdown reached ZERO! Automatically transition to RUNNING and send Q1!
                try {
                    syncTelegramQuizLiveStatus($pdo, $session, 'started', 0);
                } catch (\Throwable $e) {}

                $pdo->prepare("
                    UPDATE tg_quiz_sessions
                    SET status = 'RUNNING',
                        start_time = NOW(),
                        updated_at = NOW()
                    WHERE id = ? AND status = 'COUNTDOWN'
                ")->execute([$session_id]);

                // Dispatch Question 1
                $dispatchInfo = dispatchSessionQuestion($pdo, $session_id, true);

                // Re-fetch updated session state
                $stmt->execute([$session_id, $admin_id]);
                $session = $stmt->fetch();
            }
        }

        // 2. RUNNING STATE HANDLING (AUTO-DISPATCH NEXT QUESTION)
        if ($session['status'] === 'RUNNING' && $session['next_question_at']) {
            $next_ts = strtotime($session['next_question_at']);
            if ($next_ts <= time() && (int)$session['current_question'] < (int)$session['total_questions']) {
                $dispatchInfo = dispatchSessionQuestion($pdo, $session_id, false);
                // Re-fetch updated session state
                $stmt->execute([$session_id, $admin_id]);
                $session = $stmt->fetch();
            }

            // Sync live status tick with timer countdown and progress bar
            if ($session['status'] === 'RUNNING' && $session['next_question_at']) {
                $cur_next_ts = strtotime($session['next_question_at']);
                $seconds_remaining = max(0, $cur_next_ts - time());
                try {
                    syncTelegramQuizLiveStatus($pdo, $session, 'status_tick', $seconds_remaining);
                } catch (\Throwable $e) {}

                // Continuously synchronize poll votes from Telegram
                if (function_exists('syncSessionPollAnswers')) {
                    try {
                        $token = function_exists('getActiveBotToken') ? getActiveBotToken($pdo) : '';
                        if (!empty($token)) {
                            $botStmt = $pdo->prepare("SELECT id, last_update_id FROM tg_bots WHERE admin_id = ? AND is_active = 1 LIMIT 1");
                            $botStmt->execute([$admin_id]);
                            $botRow = $botStmt->fetch(PDO::FETCH_ASSOC);
                            if ($botRow) {
                                syncSessionPollAnswers($pdo, $session, $botRow, $token);
                            }
                        }
                    } catch (\Throwable $e) {}
                }
            }
        }

        // 3. AUTO-FINALIZE CHECK (LAST QUESTION TIMER EXPIRED)
        $finalizationInfo = null;
        if (
            empty($session['results_announced_at'])
            && (int)$session['current_question'] >= (int)$session['total_questions']
            && (int)$session['total_questions'] > 0
            && (empty($session['next_question_at']) || strtotime($session['next_question_at']) <= time())
            && in_array($session['status'], ['RUNNING', 'COMPLETED'])
        ) {
            // Update Telegram status message to QUIZ COMPLETED
            try {
                syncTelegramQuizLiveStatus($pdo, $session_id, 'completed');
            } catch (\Throwable $e) {}

            $finalizationInfo = finalizeTelegramQuiz($pdo, $session_id);
            $stmt->execute([$session_id, $admin_id]);
            $session = $stmt->fetch();
        }

        // Calculate time remaining for current question
        if ($session['status'] === 'RUNNING' && $session['next_question_at']) {
            $next_ts = strtotime($session['next_question_at']);
            $seconds_remaining = max(0, $next_ts - time());
        } elseif ($session['status'] === 'PAUSED') {
            $seconds_remaining = (int)($session['remaining_time'] ?? 0);
        }

        $remaining_questions = max(0, ((int)$session['total_questions']) - ((int)$session['questions_sent']));
        $totalQ = max(1, (int)$session['total_questions']);
        $sentQ  = (int)$session['questions_sent'];
        $progressPct = round(($sentQ / $totalQ) * 100, 1);
        $progressBar = renderQuizProgressBar($sentQ, $totalQ, 20);

        echo json_encode([
            'success'               => true,
            'session'               => $session,
            'server_time'           => time(),
            'countdown_ends_ts'     => !empty($session['countdown_ends_at']) ? strtotime($session['countdown_ends_at']) : 0,
            'next_question_ts'      => !empty($session['next_question_at']) ? strtotime($session['next_question_at']) : 0,
            'daemon_active'         => isTelegramQuizDaemonActive(),
            'seconds_remaining'     => $seconds_remaining,
            'countdown_remaining'   => $countdown_remaining,
            'remaining_questions'   => $remaining_questions,
            'progress_pct'          => $progressPct,
            'progress_bar'          => $progressBar,
            'telegram_status_msg_id'=> $session['telegram_status_message_id'] ?? null,
            'last_telegram_update'  => $session['last_telegram_update'] ?? null,
            'announcement_sent_at'  => $session['announcement_sent_at'] ?? null,
            'last_dispatch'         => $dispatchInfo,
            'finalization'          => $finalizationInfo,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    // --------------------------------------------------------
    // GET results — per-user quiz scores for this admin's session
    // --------------------------------------------------------
    case 'results':
        $session_id = (int)($_GET['session_id'] ?? 0);
        $sessionStmt = $pdo->prepare("
            SELECT id, title, chat_title, status, total_questions, questions_sent,
                   correct_mark, wrong_mark, start_time, end_time
            FROM tg_quiz_sessions
            WHERE id = ? AND admin_id = ?
        ");
        $sessionStmt->execute([$session_id, $admin_id]);
        $resultSession = $sessionStmt->fetch(PDO::FETCH_ASSOC);
        if (!$resultSession) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Quiz session not found']);
            break;
        }

        $resultStmt = $pdo->prepare("
            SELECT r.user_id,
                   COALESCE(MAX(r.username), '') AS username,
                   COALESCE(MAX(r.first_name), '') AS first_name,
                   COALESCE(MAX(r.last_name), '') AS last_name,
                   COUNT(*) AS answered_count,
                   SUM(r.is_correct = 1) AS correct_count,
                   SUM(r.is_correct = 0) AS wrong_count,
                   ROUND(
                       (SUM(r.is_correct = 1) * ABS(COALESCE(s.correct_mark, 1.00))) -
                       (SUM(r.is_correct = 0) * ABS(COALESCE(s.wrong_mark, 0.25))),
                       2
                   ) AS score
            FROM tg_quiz_responses r
            JOIN tg_quiz_sessions s ON s.id = r.session_id
            WHERE r.session_id = ?
            GROUP BY r.user_id
            ORDER BY score DESC, correct_count DESC, answered_count DESC, r.user_id ASC
        ");
        $resultStmt->execute([$session_id]);
        $results = $resultStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalQ  = max(1, (int)($resultSession['total_questions'] ?? 0));
        $posMark = (float)($resultSession['correct_mark'] ?? 1.00);
        $negMark = (float)($resultSession['wrong_mark'] ?? 0.25);

        foreach ($results as &$res) {
            $metrics = calculateCandidateQuizScore(
                (int)$res['correct_count'],
                (int)$res['wrong_count'],
                $totalQ,
                $posMark,
                $negMark
            );
            $res['attempted']      = $metrics['attempted'];
            $res['skipped']        = $metrics['skipped'];
            $res['positive_marks'] = $metrics['positive_marks'];
            $res['negative_marks'] = $metrics['negative_marks'];
            $res['final_score']    = $metrics['final_score'];
            $res['max_marks']      = $metrics['max_marks'];
            $res['percentage']     = $metrics['percentage'];
            $res['accuracy']       = $metrics['accuracy'];
        }
        unset($res);

        echo json_encode([
            'success' => true,
            'session' => $resultSession,
            'results' => $results,
            'participants' => count($results),
        ]);
        break;

    // --------------------------------------------------------
    // GET list — all sessions (for history page)
    // --------------------------------------------------------
    case 'list':
        $status_filter = $_GET['status'] ?? '';
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $where = "WHERE s.admin_id=?";
        $params = [$admin_id];
        if ($status_filter) { $where .= " AND s.status=?"; $params[] = $status_filter; }

        $stmt = $pdo->prepare("SELECT s.id, s.title, s.chat_title, s.status, s.total_questions, s.questions_sent, s.timer_seconds, s.start_time, s.end_time, qs.title as quiz_set_title FROM tg_quiz_sessions s JOIN tg_quiz_sets qs ON qs.id=s.quiz_set_id {$where} ORDER BY s.created_at DESC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);
        $sessions = $stmt->fetchAll();

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tg_quiz_sessions s {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        echo json_encode(['success'=>true, 'sessions'=>$sessions, 'total'=>$total, 'page'=>$page]);
        break;

    // --------------------------------------------------------
    // GET quiz_sets — for dropdown
    // --------------------------------------------------------
    case 'quiz_sets':
        $stmt = $pdo->prepare("SELECT id, title, total_questions, default_timer FROM tg_quiz_sets WHERE admin_id=? AND is_active=1 ORDER BY created_at DESC");
        $stmt->execute([$admin_id]);
        echo json_encode(['success'=>true, 'quiz_sets'=>$stmt->fetchAll()]);
        break;

    // --------------------------------------------------------
    // POST create_set — create a new quiz set
    // --------------------------------------------------------
    case 'create_set':
        validateCsrfToken();
        $title = strip_tags(trim($_POST['title'] ?? ''));
        $desc  = strip_tags(trim($_POST['description'] ?? ''));
        $timer = max(5, min(300, (int)($_POST['default_timer'] ?? 15)));
        if (empty($title)) { echo json_encode(['success'=>false,'message'=>'Quiz title required']); break; }

        $ins = $pdo->prepare("INSERT INTO tg_quiz_sets (admin_id, title, description, default_timer) VALUES (?,?,?,?)");
        $ins->execute([$admin_id, $title, $desc, $timer]);
        $new_id = $pdo->lastInsertId();
        logActivity("Created quiz set: {$title}", 'admin', null, $admin_id);
        echo json_encode(['success'=>true, 'message'=>'Quiz set created!', 'id'=>(int)$new_id]);
        break;

    // --------------------------------------------------------
    // POST update_set — update quiz set details
    // --------------------------------------------------------
    case 'update_set':
        validateCsrfToken();
        $id    = (int)($_POST['id'] ?? 0);
        $title = strip_tags(trim($_POST['title'] ?? ''));
        $desc  = strip_tags(trim($_POST['description'] ?? ''));
        $timer = max(5, min(300, (int)($_POST['default_timer'] ?? 15)));
        if (empty($title)) { echo json_encode(['success'=>false,'message'=>'Quiz title required']); break; }

        $check = $pdo->prepare("SELECT id FROM tg_quiz_sets WHERE id=? AND admin_id=?");
        $check->execute([$id, $admin_id]);
        if (!$check->fetchColumn()) { echo json_encode(['success'=>false,'message'=>'Not found']); break; }

        $pdo->prepare("UPDATE tg_quiz_sets SET title=?, description=?, default_timer=?, updated_at=NOW() WHERE id=?")->execute([$title, $desc, $timer, $id]);
        echo json_encode(['success'=>true, 'message'=>'Quiz set updated!']);
        break;

    // --------------------------------------------------------
    // POST delete_set — delete quiz set and all questions
    // --------------------------------------------------------
    case 'delete_set':
        validateCsrfToken();
        $id = (int)($_POST['id'] ?? 0);
        $check = $pdo->prepare("SELECT id FROM tg_quiz_sets WHERE id=? AND admin_id=?");
        $check->execute([$id, $admin_id]);
        if (!$check->fetchColumn()) { echo json_encode(['success'=>false,'message'=>'Not found']); break; }

        // Check for active sessions
        $active = $pdo->prepare("SELECT COUNT(*) FROM tg_quiz_sessions WHERE quiz_set_id=? AND status IN ('RUNNING','PAUSED')");
        $active->execute([$id]);
        if ($active->fetchColumn() > 0) { echo json_encode(['success'=>false,'message'=>'Cannot delete — quiz has active sessions. Stop them first.']); break; }

        $pdo->prepare("DELETE FROM tg_quiz_questions WHERE quiz_set_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM tg_quiz_sets WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true, 'message'=>'Quiz set deleted']);
        break;

    // --------------------------------------------------------
    // POST duplicate_set — clone a quiz set with all questions
    // --------------------------------------------------------
    case 'duplicate_set':
        validateCsrfToken();
        $id = (int)($_POST['id'] ?? 0);
        $check = $pdo->prepare("SELECT * FROM tg_quiz_sets WHERE id=? AND admin_id=?");
        $check->execute([$id, $admin_id]);
        $orig = $check->fetch();
        if (!$orig) { echo json_encode(['success'=>false,'message'=>'Not found']); break; }

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("INSERT INTO tg_quiz_sets (admin_id, title, description, default_timer) VALUES (?,?,?,?)");
            $ins->execute([$admin_id, $orig['title'].' (Copy)', $orig['description'], $orig['default_timer']]);
            $new_id = $pdo->lastInsertId();

            // Copy all questions
            $qStmt = $pdo->prepare("SELECT * FROM tg_quiz_questions WHERE quiz_set_id=? ORDER BY question_number");
            $qStmt->execute([$id]);
            $questions = $qStmt->fetchAll();

            $qIns = $pdo->prepare("INSERT INTO tg_quiz_questions (quiz_set_id, question_number, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES (?,?,?,?,?,?,?,?,?)");
            foreach ($questions as $q) {
                $qIns->execute([$new_id, $q['question_number'], $q['question_text'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_answer'], $q['explanation']]);
            }
            $pdo->prepare("UPDATE tg_quiz_sets SET total_questions=? WHERE id=?")->execute([count($questions), $new_id]);
            $pdo->commit();
            echo json_encode(['success'=>true, 'message'=>'Quiz set duplicated!', 'new_id'=>(int)$new_id]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success'=>false,'message'=>'Database error']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Unknown action']);
}
