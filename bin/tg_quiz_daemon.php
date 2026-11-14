<?php
/**
 * bin/tg_quiz_daemon.php
 * ============================================================
 * XAMPP-Native PHP Background Quiz Daemon for Telegram Integration
 * ============================================================
 * Runs detached in the background on the local server / CLI.
 * 
 * Features:
 *  - Automatically processes countdowns and dispatches questions on exact wall-clock schedule.
 *  - Works continuously even if browser is closed, tab minimized, screen locked, or display turned off.
 *  - Enforces single-instance mutex using file locks (prevents duplicate runners or question dispatching).
 *  - Continuously syncs Telegram live messages and poll answers.
 *  - Gracefully self-terminates after 60 seconds of idle time (0% CPU when no quiz is active).
 *  - Automatically restarted by api/tg_quiz/session.php whenever a quiz starts or resumes.
 */

declare(ticks=1);

if (php_sapi_name() !== 'cli') {
    // Only allow CLI execution or secure local key
    if (($_GET['key'] ?? '') !== 'V1shwk4rma_local_daemon_key') {
        http_response_code(403);
        die("CLI access only.\n");
    }
}

// Disable execution timeouts and ignore client disconnects
@ini_set('max_execution_time', '0');
@set_time_limit(0);
if (function_exists('ignore_user_abort')) {
    @ignore_user_abort(true);
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/tg_quiz/tg_helper.php';
require_once __DIR__ . '/../api/tg_quiz/tg_results.php';
require_once __DIR__ . '/../api/tg_quiz/tg_playable_helper.php';

// Single-Instance Mutex via flock
$lockFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tg_quiz_daemon.lock';
$lockFp = fopen($lockFilePath, 'c+');

if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    // Another instance is already running
    echo "[" . date('Y-m-d H:i:s') . "] Daemon is already active. Exiting duplicate instance.\n";
    if ($lockFp) fclose($lockFp);
    exit(0);
}

// Register cleanup on shutdown
register_shutdown_function(function() use ($lockFp) {
    if ($lockFp) {
        @flock($lockFp, LOCK_UN);
        @fclose($lockFp);
    }
});

// Write current PID to lock file
ftruncate($lockFp, 0);
fwrite($lockFp, (string)getmypid() . "\n" . date('Y-m-d H:i:s') . "\n");
fflush($lockFp);

// Setup daemon log
$logFile = __DIR__ . '/../logs/tg_quiz_daemon.log';
function daemonLog($msg, $logFile) {
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    if (php_sapi_name() === 'cli') {
        echo $entry;
    }
}

daemonLog("=== Vishwakarma Telegram Quiz Daemon Started (PID: " . getmypid() . ") ===", $logFile);

$idleSeconds = 0;
$lastPollSyncTime = 0;
$lastStatusTickTime = 0;

while (true) {
    try {
        $now = time();
        $nowStr = date('Y-m-d H:i:s', $now);

        // Fetch all active sessions
        $stmt = $pdo->prepare("
            SELECT s.*, b.bot_token_encrypted
            FROM tg_quiz_sessions s
            JOIN tg_bots b ON b.admin_id = s.admin_id AND b.is_active = 1
            WHERE s.status IN ('COUNTDOWN', 'RUNNING')
            ORDER BY s.id ASC
        ");
        $stmt->execute();
        $activeSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch active common live group quiz sessions
        $liveStmt = $pdo->query("
            SELECT id, question_deadline FROM tg_group_quiz_sessions
            WHERE status = 'RUNNING'
        ");
        $activeLiveGroupSessions = $liveStmt->fetchAll(PDO::FETCH_ASSOC);

        // Advance any live group session whose deadline has expired
        foreach ($activeLiveGroupSessions as $liveSess) {
            if (!empty($liveSess['question_deadline']) && strtotime($liveSess['question_deadline']) <= $now) {
                daemonLog("Group Live Session #{$liveSess['id']}: Timer expired. Advancing to next question.", $logFile);
                TelegramPlayableQuizEngine::advanceLiveGroupQuiz($pdo, (int)$liveSess['id']);
            }
        }

        // Drain Telegram updates in polling mode (every 1 second)
        if (($now - $lastPollSyncTime) >= 1) {
            $token = getActiveBotToken($pdo);
            if (!empty($token)) {
                $botRow = $pdo->query("SELECT id, last_update_id FROM tg_bots WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($botRow) {
                    $offset = ((int)$botRow['last_update_id']) + 1;
                    $tgUpdates = callTelegramAPI($token, 'getUpdates', [
                        'offset'          => $offset,
                        'limit'           => 50,
                        'timeout'         => 0,
                        'allowed_updates' => ['message', 'callback_query', 'poll_answer', 'my_chat_member']
                    ]);

                    if (!empty($tgUpdates['ok']) && !empty($tgUpdates['result'])) {
                        foreach ($tgUpdates['result'] as $u) {
                            $uId = (int)($u['update_id'] ?? 0);
                            if ($uId >= $offset) {
                                $offset = $uId + 1;
                            }
                            $uType = isset($u['callback_query']) ? 'callback_query (' . ($u['callback_query']['data'] ?? '') . ')' : (isset($u['message']) ? 'message (' . ($u['message']['text'] ?? '') . ')' : 'other');
                            daemonLog("Processing Telegram update #{$uId}: {$uType}", $logFile);

                            $updRes = TelegramPlayableQuizEngine::handleTelegramUpdate($pdo, $u, $token);
                            daemonLog("Update #{$uId} outcome: " . json_encode($updRes), $logFile);

                            $pdo->prepare("UPDATE tg_bots SET last_update_id = ? WHERE id = ?")->execute([$uId, $botRow['id']]);
                        }
                    }
                }
            }
            $lastPollSyncTime = $now;
        }

        $hasWork = (!empty($activeSessions) || !empty($activeLiveGroupSessions));
        if (!$hasWork) {
            $idleSeconds++;
            // When running in polling mode on local server, keep polling continuously for incoming user clicks
            $botActive = (bool)$pdo->query("SELECT id FROM tg_bots WHERE is_active = 1 LIMIT 1")->fetchColumn();
            if ($botActive) {
                usleep(500000); // 0.5s pause to prevent CPU burn
                continue;
            }

            // If idle for over 90 seconds and no bot is active, exit cleanly
            if ($idleSeconds >= 90) {
                daemonLog("No active bot or quiz sessions for 90 seconds. Daemon shutting down cleanly.", $logFile);
                break;
            }
            sleep(1);
            continue;
        }

        // Active session found, reset idle counter
        $idleSeconds = 0;

        foreach ($activeSessions as $session) {
            $sessionId = (int)$session['id'];
            $status    = $session['status'];

            // ─────────────────────────────────────────────────────────────
            // 1. COUNTDOWN STATE
            // ─────────────────────────────────────────────────────────────
            if ($status === 'COUNTDOWN') {
                $countdownEndsTs = !empty($session['countdown_ends_at']) ? strtotime($session['countdown_ends_at']) : 0;
                $rem = max(0, $countdownEndsTs - $now);

                if ($rem <= 0) {
                    // Countdown ended! Transition to RUNNING and dispatch first question
                    $startQ = (int)($session['start_question'] ?? 1);
                    daemonLog("Session #{$sessionId}: Countdown completed. Transitioning to RUNNING and sending Question #{$startQ}.", $logFile);

                    try {
                        syncTelegramQuizLiveStatus($pdo, $session, 'started', 0);
                    } catch (\Throwable $e) {
                        daemonLog("Session #{$sessionId}: Sync error on start: " . $e->getMessage(), $logFile);
                    }

                    $pdo->prepare("
                        UPDATE tg_quiz_sessions
                        SET status = 'RUNNING',
                            start_time = :now_dt,
                            updated_at = :now_dt
                        WHERE id = :id AND status = 'COUNTDOWN'
                    ")->execute([':now_dt' => $nowStr, ':id' => $sessionId]);

                    // Dispatch first Question
                    $disp = dispatchSessionQuestion($pdo, $sessionId, true);
                    daemonLog("Session #{$sessionId}: Question #{$startQ} dispatch result: " . json_encode($disp), $logFile);

                } else {
                    // Live countdown tick: update Telegram status every 5 seconds or when <= 5s
                    if ($rem <= 5 || ($rem % 5 === 0 && ($now - $lastStatusTickTime) >= 3)) {
                        try {
                            syncTelegramQuizLiveStatus($pdo, $session, 'countdown', $rem);
                            $lastStatusTickTime = $now;
                        } catch (\Throwable $e) {}
                    }
                }
            }

            // ─────────────────────────────────────────────────────────────
            // 2. RUNNING STATE
            // ─────────────────────────────────────────────────────────────
            elseif ($status === 'RUNNING') {
                $nextTs = !empty($session['next_question_at']) ? strtotime($session['next_question_at']) : 0;
                $currQ  = (int)$session['current_question'];
                $totalQ = (int)$session['total_questions'];

                // Check if next question is due
                if ($nextTs > 0 && $nextTs <= $now && $currQ < $totalQ) {
                    $startQ = (int)($session['start_question'] ?? 1);
                    $dispQNum = ($startQ > 1) ? ($startQ + $currQ) : ($currQ + 1);
                    daemonLog("Session #{$sessionId}: Next question is due. Dispatching question #{$dispQNum} (batch turn " . ($currQ + 1) . " of {$totalQ}).", $logFile);
                    $disp = dispatchSessionQuestion($pdo, $sessionId, false);
                    daemonLog("Session #{$sessionId}: Dispatch result: " . json_encode($disp), $logFile);
                }

                // Check for auto-finalization (all questions sent and timer expired)
                elseif (
                    empty($session['results_announced_at'])
                    && $currQ >= $totalQ
                    && $totalQ > 0
                    && ($nextTs <= 0 || $nextTs <= $now)
                ) {
                    daemonLog("Session #{$sessionId}: All questions completed and timer expired. Finalizing quiz and publishing results.", $logFile);
                    try {
                        syncTelegramQuizLiveStatus($pdo, $sessionId, 'completed');
                    } catch (\Throwable $e) {}

                    $final = finalizeTelegramQuiz($pdo, $sessionId);
                    daemonLog("Session #{$sessionId}: Finalization result: " . json_encode($final), $logFile);
                }

                // Routine background Telegram poll votes & status tick
                else {
                    $secRem = max(0, $nextTs - $now);
                    // Update live status bar every 5 seconds
                    if (($now - $lastStatusTickTime) >= 5) {
                        try {
                            syncTelegramQuizLiveStatus($pdo, $session, 'status_tick', $secRem);
                            $lastStatusTickTime = $now;
                        } catch (\Throwable $e) {}
                    }

                    // Poll Telegram votes every 3 seconds
                    if (($now - $lastPollSyncTime) >= 3) {
                        $token = decryptToken($session['bot_token_encrypted']);
                        if (!empty($token) && function_exists('syncSessionPollAnswers')) {
                            try {
                                $botStmt = $pdo->prepare("SELECT id, last_update_id FROM tg_bots WHERE admin_id = ? AND is_active = 1 LIMIT 1");
                                $botStmt->execute([$session['admin_id']]);
                                $botRow = $botStmt->fetch(PDO::FETCH_ASSOC);
                                if ($botRow) {
                                    syncSessionPollAnswers($pdo, $session, $botRow, $token);
                                }
                            } catch (\Throwable $e) {}
                        }
                        $lastPollSyncTime = $now;
                    }
                }
            }
        }

    } catch (\Throwable $e) {
        daemonLog("Daemon iteration exception: " . $e->getMessage() . "\n" . $e->getTraceAsString(), $logFile);
    }

    // Sleep 1 second before next cycle
    sleep(1);
}

daemonLog("=== Vishwakarma Telegram Quiz Daemon Exited ===", $logFile);
