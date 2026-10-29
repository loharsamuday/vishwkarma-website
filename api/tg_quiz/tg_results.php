<?php
// Finalize a Telegram quiz after its last poll timer expires.

require_once __DIR__ . '/tg_helper.php';

function syncSessionPollAnswers(PDO $pdo, array $session, array $bot, string $token): bool {
    $updates = callTelegramAPI($token, 'getUpdates', [
        'offset' => (int)$bot['last_update_id'] + 1,
        'limit' => 100,
        'timeout' => 0,
        'allowed_updates' => ['poll_answer'],
    ]);

    if (empty($updates['ok'])) {
        return false;
    }

    foreach ($updates['result'] ?? [] as $update) {
        $answer = $update['poll_answer'] ?? null;
        if ($answer) {
            $pollId = (string)($answer['poll_id'] ?? '');
            $user = $answer['user'] ?? [];
            $userId = (int)($user['id'] ?? 0);

            if ($pollId !== '' && $userId > 0) {
                $questionStmt = $pdo->prepare("
                    SELECT l.session_id, l.question_id, l.question_number, q.correct_answer,
                           q.option_a, q.option_b, q.option_c, q.option_d
                    FROM tg_quiz_logs l
                    JOIN tg_quiz_sessions s ON s.id = l.session_id
                    JOIN tg_quiz_questions q ON q.id = l.question_id
                    WHERE l.telegram_poll_id = ? AND l.status = 'SENT'
                      AND s.status = 'RUNNING' AND s.admin_id = ?
                    LIMIT 1
                ");
                $questionStmt->execute([$pollId, (int)$session['admin_id']]);
                $question = $questionStmt->fetch(PDO::FETCH_ASSOC);
                $selectedOptions = $answer['option_ids'] ?? [];

                if ($question && !$selectedOptions) {
                    $pdo->prepare("
                        DELETE FROM tg_quiz_responses
                        WHERE telegram_poll_id = ? AND user_id = ?
                    ")->execute([$pollId, $userId]);
                } elseif ($question && $selectedOptions) {
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
                             user_id, username, first_name, last_name, selected_option_id, is_correct)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            username = VALUES(username),
                            first_name = VALUES(first_name),
                            last_name = VALUES(last_name),
                            selected_option_id = VALUES(selected_option_id),
                            is_correct = VALUES(is_correct),
                            answered_at = NOW()
                    ");
                    $saveStmt->execute([
                        (int)$question['session_id'], (int)$question['question_id'], (int)$question['question_number'],
                        $pollId, $userId, $user['username'] ?? null, $user['first_name'] ?? null,
                        $user['last_name'] ?? null, $selectedIndex, (int)($selectedIndex === $correctIndex),
                    ]);
                }
            }
        }

        $pdo->prepare("UPDATE tg_bots SET last_update_id = ? WHERE id = ?")
            ->execute([(int)$update['update_id'], (int)$bot['id']]);
    }

    return count($updates['result'] ?? []) < 100;
}

function finalizeTelegramQuiz(PDO $pdo, int $sessionId): array {
    $stmt = $pdo->prepare("
        SELECT s.*, b.id AS bot_id, b.bot_token_encrypted, b.last_update_id
        FROM tg_quiz_sessions s
        JOIN tg_bots b ON b.admin_id = s.admin_id AND b.is_active = 1
        WHERE s.id = ?
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) return ['success' => false, 'message' => 'Session or active bot not found'];
    if ($session['status'] === 'COMPLETED') return ['success' => true, 'completed' => true];
    if ($session['status'] !== 'RUNNING' || (int)$session['current_question'] < (int)$session['total_questions']) {
        return ['success' => false, 'message' => 'Quiz is not ready to finalize'];
    }
    if (empty($session['next_question_at']) || strtotime($session['next_question_at']) > time()) {
        return ['success' => false, 'waiting' => true, 'message' => 'Final question timer is still active'];
    }

    $claim = $pdo->prepare("
        UPDATE tg_quiz_sessions
        SET results_claimed_at = NOW(), updated_at = NOW()
        WHERE id = ? AND status = 'RUNNING' AND results_announced_at IS NULL
          AND (results_claimed_at IS NULL OR results_claimed_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))
          AND next_question_at <= NOW()
    ");
    $claim->execute([$sessionId]);
    if ($claim->rowCount() !== 1) return ['success' => true, 'pending' => true];

    $token = decryptToken($session['bot_token_encrypted']);
    if ($token === '') {
        $pdo->prepare("UPDATE tg_quiz_sessions SET results_claimed_at = NULL WHERE id = ?")->execute([$sessionId]);
        return ['success' => false, 'message' => 'Could not decrypt bot token'];
    }

    try {
        if (!syncSessionPollAnswers($pdo, $session, [
            'id' => $session['bot_id'],
            'last_update_id' => $session['last_update_id'],
        ], $token)) {
            $pdo->prepare("UPDATE tg_quiz_sessions SET results_claimed_at = NULL WHERE id = ?")->execute([$sessionId]);
            return ['success' => false, 'message' => 'Telegram answers are still syncing; check webhook/worker settings'];
        }

        $resultStmt = $pdo->prepare("
            SELECT r.user_id, MAX(r.username) AS username, MAX(r.first_name) AS first_name,
                   MAX(r.last_name) AS last_name, COUNT(*) AS answered_count,
                   SUM(r.is_correct = 1) AS correct_count,
                   SUM(r.is_correct = 0) AS wrong_count,
                   SUM(CASE WHEN r.is_correct = 1 THEN s.correct_mark ELSE s.wrong_mark END) AS score
            FROM tg_quiz_responses r
            JOIN tg_quiz_sessions s ON s.id = r.session_id
            WHERE r.session_id = ?
            GROUP BY r.user_id
            ORDER BY score DESC, correct_count DESC, answered_count DESC, r.user_id ASC
        ");
        $resultStmt->execute([$sessionId]);
        $results = $resultStmt->fetchAll(PDO::FETCH_ASSOC);

        $title = trim((string)($session['title'] ?? 'Telegram Quiz'));
        $messages = ["🏆 Quiz results: {$title}"];
        if (!$results) {
            $messages[] = 'No participants submitted answers.';
        } else {
            foreach ($results as $index => $result) {
                $name = trim(($result['first_name'] ?? '') . ' ' . ($result['last_name'] ?? ''));
                if ($name === '') $name = !empty($result['username']) ? '@' . $result['username'] : 'User ' . $result['user_id'];
                $name = preg_replace('/[\r\n]+/', ' ', $name);
                $name = mb_substr($name, 0, 60);
                $line = sprintf(
                    '%d. %s — Score: %s | Correct: %d | Wrong: %d',
                    $index + 1,
                    $name,
                    number_format((float)$result['score'], 2),
                    (int)$result['correct_count'],
                    (int)$result['wrong_count']
                );
                $lastIndex = count($messages) - 1;
                if (mb_strlen($messages[$lastIndex] . "\n" . $line) > 3500) {
                    $messages[] = $line;
                } else {
                    $messages[$lastIndex] .= "\n" . $line;
                }
            }
        }

        $messages[] = sprintf(
            'Scoring: correct +%s | wrong %s',
            number_format((float)$session['correct_mark'], 2),
            number_format((float)$session['wrong_mark'], 2)
        );

        foreach ($messages as $message) {
            $sent = callTelegramAPI($token, 'sendMessage', [
                'chat_id' => $session['chat_id'],
                'text' => $message,
            ]);
            if (empty($sent['ok'])) {
                $pdo->prepare("UPDATE tg_quiz_sessions SET results_claimed_at = NULL WHERE id = ?")->execute([$sessionId]);
                return ['success' => false, 'message' => 'Could not post final results to Telegram group'];
            }
        }

        $pdo->prepare("
            UPDATE tg_quiz_sessions
            SET status = 'COMPLETED', end_time = NOW(), results_announced_at = NOW(),
                results_claimed_at = NULL, updated_at = NOW()
            WHERE id = ? AND status = 'RUNNING'
        ")->execute([$sessionId]);

        return ['success' => true, 'completed' => true, 'participants' => count($results)];
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE tg_quiz_sessions SET results_claimed_at = NULL WHERE id = ?")->execute([$sessionId]);
        error_log('Telegram quiz finalization failed for session ' . $sessionId . ': ' . $e->getMessage());
        return ['success' => false, 'message' => 'Could not finalize quiz results'];
    }
}
