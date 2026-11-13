<?php
// api/tg_quiz/tg_results.php
// Finalize a Telegram quiz after its last poll timer expires and publish final results to Telegram.

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/tg_helper.php';

/**
 * Synchronize poll answers via getUpdates (active in long-polling mode).
 * In Webhook mode, Telegram sends updates directly to api/tg_quiz/webhook.php,
 * so getUpdates returns 409 Conflict. This function handles both modes gracefully.
 */
function syncSessionPollAnswers(PDO $pdo, array $session, array $bot, string $token): bool {
    if (empty($token)) {
        return false;
    }

    $botId = (int)($bot['id'] ?? 0);
    $offset = (int)($bot['last_update_id'] ?? 0) + 1;
    $maxBatches = 50; // Safety cap: up to 5,000 updates drained
    $batchCount = 0;

    do {
        $batchCount++;
        $updates = callTelegramAPI($token, 'getUpdates', [
            'offset'          => $offset,
            'limit'           => 100,
            'timeout'         => 0,
            'allowed_updates' => ['poll_answer'],
        ]);

        if (empty($updates['ok'])) {
            // If a webhook is active, Telegram rejects getUpdates with HTTP 409 Conflict.
            // In that case, poll answers are received and stored directly in real-time by webhook.php.
            if (isset($updates['error_code']) && (int)$updates['error_code'] === 409) {
                return true;
            }
            if (!empty($updates['description'])) {
                error_log("syncSessionPollAnswers notice: " . $updates['description']);
            }
            break;
        }

        $resultList = $updates['result'] ?? [];
        $receivedCount = count($resultList);
        if ($receivedCount === 0) {
            break;
        }

        foreach ($resultList as $update) {
            $updateId = (int)($update['update_id'] ?? 0);
            if ($updateId >= $offset) {
                $offset = $updateId + 1;
            }

            $answer = $update['poll_answer'] ?? null;
            if ($answer) {
                $pollId = (string)($answer['poll_id'] ?? '');
                $user   = $answer['user'] ?? [];
                $userId = (int)($user['id'] ?? 0);

                if ($pollId !== '' && $userId > 0) {
                    $questionStmt = $pdo->prepare("
                        SELECT l.session_id, l.question_id, l.question_number, q.correct_answer,
                               q.option_a, q.option_b, q.option_c, q.option_d
                        FROM tg_quiz_logs l
                        JOIN tg_quiz_sessions s ON s.id = l.session_id
                        JOIN tg_quiz_questions q ON q.id = l.question_id
                        WHERE l.telegram_poll_id = ? AND l.status = 'SENT'
                        LIMIT 1
                    ");
                    $questionStmt->execute([$pollId]);
                    $question = $questionStmt->fetch(PDO::FETCH_ASSOC);
                    $selectedOptions = $answer['option_ids'] ?? [];

                    if ($question && empty($selectedOptions)) {
                        $pdo->prepare("
                            DELETE FROM tg_quiz_responses
                            WHERE telegram_poll_id = ? AND user_id = ?
                        ")->execute([$pollId, $userId]);
                    } elseif ($question && !empty($selectedOptions)) {
                        $letters = [];
                        foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field) {
                            if (!empty($question[$field])) $letters[] = $letter;
                        }
                        $correctIndex = array_search(strtoupper($question['correct_answer']), $letters, true);
                        if ($correctIndex === false) $correctIndex = 0;
                        $selectedIndex = (int)$selectedOptions[0];

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
                            $user['username'] ?? null,
                            $user['first_name'] ?? null,
                            $user['last_name'] ?? null,
                            $selectedIndex,
                            (int)($selectedIndex === $correctIndex),
                        ]);
                    }
                }
            }

            if ($botId > 0 && $updateId > 0) {
                $pdo->prepare("UPDATE tg_bots SET last_update_id = ? WHERE id = ?")
                    ->execute([$updateId, $botId]);
            }
        }
    } while ($receivedCount === 100 && $batchCount < $maxBatches);

    return true;
}

/**
 * Finalize a Telegram Quiz session and automatically publish results to Telegram.
 * Thread-safe: uses atomic DB claim to prevent duplicate result announcements.
 *
 * @param PDO  $pdo
 * @param int  $sessionId
 * @param bool $force Force publishing even if already announced
 * @return array
 */
function finalizeTelegramQuiz(PDO $pdo, int $sessionId, bool $force = false): array {
    $stmt = $pdo->prepare("
        SELECT s.*, b.id AS bot_id, b.bot_token_encrypted, b.last_update_id
        FROM tg_quiz_sessions s
        LEFT JOIN tg_bots b ON b.admin_id = s.admin_id AND b.is_active = 1
        WHERE s.id = ?
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        return ['success' => false, 'message' => "Session #{$sessionId} not found"];
    }

    // Prevent duplicate announcement unless force is explicitly requested
    if (!$force && !empty($session['results_announced_at'])) {
        return ['success' => true, 'completed' => true, 'already_announced' => true, 'message' => 'Results have already been published.'];
    }

    // Check completion condition
    $currentQ = (int)$session['current_question'];
    $totalQ   = (int)$session['total_questions'];
    if (!$force && $currentQ < $totalQ) {
        return ['success' => false, 'message' => "Quiz is not ready to finalize ({$currentQ}/{$totalQ} questions sent)"];
    }

    // Wait until the final question's timer has expired
    $now = time();
    $nextTs = !empty($session['next_question_at']) ? strtotime($session['next_question_at']) : 0;
    if (!$force && $nextTs > $now) {
        return [
            'success'           => false,
            'waiting'           => true,
            'seconds_remaining' => max(0, $nextTs - $now),
            'message'           => 'Final question timer is still active'
        ];
    }

    // ATOMIC CLAIM: lock this session so concurrent requests/workers never publish duplicate results
    if (!$force) {
        $claim = $pdo->prepare("
            UPDATE tg_quiz_sessions
            SET results_claimed_at = NOW(), updated_at = NOW()
            WHERE id = ? AND results_announced_at IS NULL
              AND (results_claimed_at IS NULL OR results_claimed_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE))
              AND (next_question_at IS NULL OR next_question_at <= NOW())
        ");
        $claim->execute([$sessionId]);
        if ($claim->rowCount() !== 1) {
            return ['success' => true, 'pending' => true, 'message' => 'Results already claimed by another process'];
        }
    } else {
        $pdo->prepare("UPDATE tg_quiz_sessions SET results_claimed_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$sessionId]);
    }

    // Retrieve and decrypt Bot Token
    $token = '';
    if (!empty($session['bot_token_encrypted'])) {
        $token = decryptToken($session['bot_token_encrypted']);
    }
    if (empty($token) && function_exists('getActiveBotToken')) {
        $token = getActiveBotToken($pdo);
    }

    if (empty($token)) {
        $pdo->prepare("UPDATE tg_quiz_sessions SET results_claimed_at = NULL, error_message = 'Failed to decrypt bot token' WHERE id = ?")->execute([$sessionId]);
        return ['success' => false, 'message' => 'Could not decrypt bot token'];
    }

    try {
        // Attempt polling sync (safely handled: in webhook mode, 409 is treated as normal)
        try {
            syncSessionPollAnswers($pdo, $session, [
                'id'             => $session['bot_id'] ?? 0,
                'last_update_id' => $session['last_update_id'] ?? 0,
            ], $token);
        } catch (\Throwable $e) {
            error_log("Notice during poll answers sync for session {$sessionId}: " . $e->getMessage());
        }

        // Calculate participant scores from tg_quiz_responses using standardized formula
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
        $resultStmt->execute([$sessionId]);
        $results = $resultStmt->fetchAll(PDO::FETCH_ASSOC);

        // Format publication message
        $title = trim((string)($session['title'] ?? 'Telegram Quiz'));
        $totalQuestions = max(1, (int)($session['total_questions'] ?? count($results)));
        $correctMark = abs((float)($session['correct_mark'] ?? 1.00));
        $wrongMark   = abs((float)($session['wrong_mark'] ?? 0.25));
        $maxPossibleMarks = round($totalQuestions * $correctMark, 2);

        $correctMarkStr = number_format($correctMark, 2);
        $wrongMarkStr   = number_format($wrongMark, 2);

        $messages = [];
        $header = "🏁 <b>Quiz Completed!</b>\n";
        $header .= "📋 <b>" . htmlspecialchars($title) . "</b>\n";
        $header .= "❓ Total Questions: <b>{$totalQuestions}</b> (Max Marks: <b>{$maxPossibleMarks}</b>)\n";
        $header .= "📊 Marking Scheme: Correct: <b>+{$correctMarkStr}</b> | Wrong: <b>-{$wrongMarkStr}</b> | Skipped: <b>0.00</b>\n";
        $header .= "👥 Total Participants: <b>" . count($results) . "</b>\n";
        $header .= "━━━━━━━━━━━━━━━━━━━━\n";
        $header .= "🏆 <b>FINAL LEADERBOARD:</b>\n\n";

        if (empty($results)) {
            $header .= "<i>No participants submitted answers for this quiz.</i>\n\n";
        } else {
            $medals = ['🥇', '🥈', '🥉'];
            foreach ($results as $index => $result) {
                $rankIcon = $medals[$index] ?? ('<b>#' . ($index + 1) . '</b>');
                $name = trim(($result['first_name'] ?? '') . ' ' . ($result['last_name'] ?? ''));
                if ($name === '') {
                    $name = !empty($result['username']) ? '@' . $result['username'] : 'User ' . $result['user_id'];
                }
                $nameClean = htmlspecialchars(mb_substr(preg_replace('/[\r\n]+/u', ' ', $name), 0, 40, 'UTF-8'));

                $metrics = calculateCandidateQuizScore(
                    (int)$result['correct_count'],
                    (int)$result['wrong_count'],
                    $totalQuestions,
                    $correctMark,
                    $wrongMark
                );

                $scoreVal    = number_format($metrics['final_score'], 2);
                $maxMarksVal = number_format($metrics['max_marks'], 2);
                $correctVal  = $metrics['correct'];
                $wrongVal    = $metrics['wrong'];
                $skippedVal  = $metrics['skipped'];
                $pctVal      = number_format($metrics['percentage'], 1);
                $accVal      = number_format($metrics['accuracy'], 1);

                $line = "{$rankIcon} <b>{$nameClean}</b>\n";
                $line .= "   Score: <b>{$scoreVal} / {$maxMarksVal}</b> ({$pctVal}%)\n";
                $line .= "   ✅ Correct: {$correctVal} | ❌ Wrong: {$wrongVal} | ⏭ Skipped: {$skippedVal} | 🎯 Accuracy: {$accVal}%\n\n";

                if (mb_strlen($header . $line, 'UTF-8') > 3500) {
                    $messages[] = $header;
                    $header = $line;
                } else {
                    $header .= $line;
                }
            }
        }

        $header .= "━━━━━━━━━━━━━━━━━━━━\n";
        $header .= "🎉 Congratulations to all participants!";
        $messages[] = $header;

        // Publish to Telegram group/channel
        $chatId = $session['chat_id'];
        foreach ($messages as $msgText) {
            // First attempt with HTML formatting
            $sent = callTelegramAPI($token, 'sendMessage', [
                'chat_id'    => $chatId,
                'text'       => $msgText,
                'parse_mode' => 'HTML',
            ]);

            // Fallback to plain text if HTML was rejected by Telegram
            if (empty($sent['ok'])) {
                $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $msgText));
                $sent = callTelegramAPI($token, 'sendMessage', [
                    'chat_id' => $chatId,
                    'text'    => $plainText,
                ]);
            }

            if (empty($sent['ok'])) {
                $errorDesc = $sent['description'] ?? 'Telegram API error';
                $pdo->prepare("UPDATE tg_quiz_sessions SET results_claimed_at = NULL, error_message = ? WHERE id = ?")
                    ->execute(["Could not post results to Telegram: {$errorDesc}", $sessionId]);
                error_log("Failed to post quiz results to Telegram for session #{$sessionId}: {$errorDesc}");
                return ['success' => false, 'message' => "Could not post final results to Telegram: {$errorDesc}"];
            }
        }

        // Successfully published! Mark session as COMPLETED and record announcement timestamp
        $pdo->prepare("
            UPDATE tg_quiz_sessions
            SET status = 'COMPLETED', end_time = NOW(), results_announced_at = NOW(),
                results_claimed_at = NULL, error_message = NULL, updated_at = NOW()
            WHERE id = ?
        ")->execute([$sessionId]);

        // Log admin activity
        if (function_exists('logActivity') && !empty($session['admin_id'])) {
            logActivity("Telegram quiz results published for session #{$sessionId}: {$title}", 'admin', null, (int)$session['admin_id']);
        }

        return [
            'success'      => true,
            'completed'    => true,
            'session_id'   => $sessionId,
            'participants' => count($results),
            'announced_at' => date('Y-m-d H:i:s'),
            'message'      => 'Final quiz results published successfully to Telegram!'
        ];

    } catch (\Throwable $e) {
        $pdo->prepare("UPDATE tg_quiz_sessions SET results_claimed_at = NULL, error_message = ? WHERE id = ?")
            ->execute([substr($e->getMessage(), 0, 255), $sessionId]);
        error_log('Telegram quiz finalization exception for session ' . $sessionId . ': ' . $e->getMessage());
        return ['success' => false, 'message' => 'Could not finalize quiz results: ' . $e->getMessage()];
    }
}

/**
 * Scan for and finalize any quiz sessions that have finished but whose results are not yet announced.
 */
function checkAndFinalizeDueSessions(PDO $pdo): array {
    $finalized = [];
    try {
        $stmt = $pdo->query("
            SELECT id FROM tg_quiz_sessions
            WHERE results_announced_at IS NULL
              AND current_question >= total_questions
              AND total_questions > 0
              AND (next_question_at IS NULL OR next_question_at <= NOW())
              AND status IN ('RUNNING', 'COMPLETED')
            ORDER BY next_question_at ASC
            LIMIT 5
        ");
        $sessionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($sessionIds as $sid) {
            $res = finalizeTelegramQuiz($pdo, (int)$sid);
            if (!empty($res['completed'])) {
                $finalized[] = (int)$sid;
            }
        }
    } catch (\Throwable $e) {
        error_log("checkAndFinalizeDueSessions error: " . $e->getMessage());
    }
    return $finalized;
}
