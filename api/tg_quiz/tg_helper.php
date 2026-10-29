<?php
// api/tg_quiz/tg_helper.php
// ============================================================
// Core Shared Helpers for Telegram Quiz Engine
// Encryption, Telegram API calls, and Question Dispatching
// ============================================================

require_once __DIR__ . '/../../config/config.php';

if (!function_exists('decryptToken')) {
    /**
     * AES-256-CBC Decryption for Bot Token
     */
    function decryptToken(string $encrypted): string {
        $key = defined('TG_ENCRYPTION_KEY') ? TG_ENCRYPTION_KEY : (getenv('TG_ENCRYPTION_KEY') ?: 'default-32-char-key-change-this!!');
        $key = substr(hash('sha256', $key, true), 0, 32);
        $data = @base64_decode($encrypted);
        if (!$data || strlen($data) < 17) return '';
        $iv   = substr($data, 0, 16);
        $cipher = substr($data, 16);
        return openssl_decrypt($cipher, 'AES-256-CBC', $key, 0, $iv) ?: '';
    }
}

if (!function_exists('encryptToken')) {
    /**
     * AES-256-CBC Encryption for Bot Token
     */
    function encryptToken(string $token): string {
        $key = defined('TG_ENCRYPTION_KEY') ? TG_ENCRYPTION_KEY : (getenv('TG_ENCRYPTION_KEY') ?: 'default-32-char-key-change-this!!');
        $key = substr(hash('sha256', $key, true), 0, 32);
        $iv  = random_bytes(16);
        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
}

if (!function_exists('callTelegramAPI')) {
    /**
     * Call Telegram Bot API safely via cURL
     * Bot token is NEVER logged or leaked.
     */
    function callTelegramAPI(string $token, string $method, array $params = []): array {
        if (empty($token)) {
            return ['ok' => false, 'description' => 'Bot token is empty'];
        }

        $url = "https://api.telegram.org/bot{$token}/{$method}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            return ['ok' => false, 'error_code' => $httpCode ?: 0, 'description' => 'cURL Error: ' . $error];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error_code' => $httpCode, 'description' => 'Invalid JSON from Telegram: ' . mb_substr($response, 0, 200)];
        }

        return $decoded;
    }
}

/**
 * Send a native Telegram Quiz Poll
 */
function sendQuizPoll(string $token, $chat_id, array $q, int $timerSeconds = 0): array {
    $options = [];
    $optionLetters = [];
    foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field) {
        if (!empty($q[$field])) {
            $options[] = mb_substr(trim($q[$field]), 0, 100);
            $optionLetters[] = $letter;
        }
    }

    if (count($options) < 2) {
        return ['ok' => false, 'description' => 'Quiz poll requires at least 2 options'];
    }

    $correctLetter = strtoupper(trim($q['correct_answer'] ?? 'A'));
    $correctIdx = array_search($correctLetter, $optionLetters, true);
    if ($correctIdx === false) $correctIdx = 0;

    $questionText = mb_substr(trim($q['question_text']), 0, 300);

    $payload = [
        'chat_id'                 => $chat_id,
        'question'                => $questionText,
        'options'                 => $options,
        'type'                    => 'quiz',
        'correct_option_id'       => $correctIdx,
        'is_anonymous'            => false,
        'allows_multiple_answers' => false,
    ];

    if ($timerSeconds > 0) {
        $payload['open_period'] = max(5, min(600, $timerSeconds));
    }

    if (!empty($q['explanation'])) {
        $payload['explanation'] = mb_substr(trim($q['explanation']), 0, 200);
    }

    return callTelegramAPI($token, 'sendPoll', $payload);
}

/**
 * Dispatch Next Due Question for a Session
 * Thread-safe: Atomically claims the turn so multiple drivers / workers
 * NEVER send duplicate questions.
 *
 * @param PDO  $pdo
 * @param int  $session_id
 * @param bool $force Force sending immediately even if timer hasn't expired
 * @return array Result status & details
 */
function dispatchSessionQuestion(PDO $pdo, int $session_id, bool $force = false): array {
    // 1. Fetch session and encrypted token
    $stmt = $pdo->prepare("
        SELECT s.*, b.bot_token_encrypted
        FROM tg_quiz_sessions s
        JOIN tg_bots b ON b.admin_id = s.admin_id AND b.is_active = 1
        WHERE s.id = ?
    ");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        return ['success' => false, 'status' => 'not_found', 'message' => 'Session not found'];
    }

    if ($session['status'] !== 'RUNNING') {
        return ['success' => false, 'status' => $session['status'], 'message' => "Session is {$session['status']}"];
    }

    $current_q = (int)$session['current_question'];
    $total_q   = (int)$session['total_questions'];
    $timer     = max(5, (int)$session['timer_seconds']);
    $is_final  = ($current_q + 1 >= $total_q);

    // Check if completed
    if ($current_q >= $total_q) {
        return ['success' => true, 'status' => 'waiting_results', 'message' => 'Collecting final Telegram answers'];
    }

    // Check due time unless forced
    $now = time();
    $nextTs = $session['next_question_at'] ? strtotime($session['next_question_at']) : 0;
    if (!$force && $nextTs > $now) {
        return [
            'success'           => true,
            'status'            => 'waiting',
            'seconds_remaining' => max(0, $nextTs - $now),
            'message'           => 'Waiting for timer'
        ];
    }

    // 2. ATOMIC CLAIM: Lock this turn for 30 seconds so no other worker claims it
    $claimStmt = $pdo->prepare("
        UPDATE tg_quiz_sessions
        SET next_question_at = DATE_ADD(NOW(), INTERVAL 30 SECOND), updated_at = NOW()
        WHERE id = ? AND status = 'RUNNING'
    ");
    $claimStmt->execute([$session_id]);

    // 3. Decrypt Bot Token
    $token = decryptToken($session['bot_token_encrypted']);
    if (empty($token)) {
        $pdo->prepare("UPDATE tg_quiz_sessions SET status = 'FAILED', error_message = 'Failed to decrypt bot token' WHERE id = ?")->execute([$session_id]);
        return ['success' => false, 'status' => 'failed', 'message' => 'Bot token decryption failed'];
    }

    // 4. Get Question ID
    $question_ids = @json_decode($session['question_ids_json'], true);
    if (!is_array($question_ids) || !isset($question_ids[$current_q])) {
        // Fallback: fetch directly from DB
        $qFetch = $pdo->prepare("SELECT id FROM tg_quiz_questions WHERE quiz_set_id = ? ORDER BY question_number ASC");
        $qFetch->execute([$session['quiz_set_id']]);
        $question_ids = $qFetch->fetchAll(PDO::FETCH_COLUMN);
    }

    if (!isset($question_ids[$current_q])) {
        $pdo->prepare("UPDATE tg_quiz_sessions SET status = 'COMPLETED', end_time = NOW() WHERE id = ?")->execute([$session_id]);
        return ['success' => true, 'status' => 'completed', 'message' => 'All questions processed'];
    }

    $question_id = (int)$question_ids[$current_q];
    $qNum        = $current_q + 1;

    // Fetch Question content
    $qStmt = $pdo->prepare("SELECT * FROM tg_quiz_questions WHERE id = ?");
    $qStmt->execute([$question_id]);
    $question = $qStmt->fetch(PDO::FETCH_ASSOC);

    if (!$question) {
        // Skip missing question
        $pdo->prepare("UPDATE tg_quiz_sessions SET current_question = current_question + 1, updated_at = NOW() WHERE id = ?")->execute([$session_id]);
        return ['success' => false, 'status' => 'skipped', 'message' => "Question ID {$question_id} not found, skipped."];
    }

    // 5. Send to Telegram as Quiz Poll
    $res = sendQuizPoll($token, $session['chat_id'], $question, $timer);

    if (!empty($res['ok'])) {
        $poll_id = (string)($res['result']['poll']['id'] ?? $res['result']['message_id'] ?? '');

        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare("
                UPDATE tg_quiz_sessions
                SET current_question        = current_question + 1,
                    questions_sent          = questions_sent + 1,
                    status                  = 'RUNNING',
                    last_question_sent_at   = NOW(),
                    next_question_at        = DATE_ADD(NOW(), INTERVAL ? SECOND),
                    last_telegram_message_id = ?,
                    error_message           = NULL,
                    retry_count             = 0,
                    end_time                = NULL,
                    updated_at              = NOW()
                WHERE id = ?
            ");
            $upd->execute([$timer, $poll_id, $session_id]);

            $logIns = $pdo->prepare("
                INSERT INTO tg_quiz_logs (session_id, question_id, question_number, status, telegram_poll_id, sent_at)
                VALUES (?, ?, ?, 'SENT', ?, NOW())
            ");
            $logIns->execute([$session_id, $question_id, $qNum, $poll_id]);

            $pdo->commit();

            return [
                'success'         => true,
                'status'          => 'sent',
                'question_number' => $qNum,
                'total_questions' => $total_q,
                'poll_id'         => $poll_id,
                'is_completed'    => false,
                'awaiting_results' => $is_final,
                'message'         => "Question {$qNum} of {$total_q} sent to Telegram!"
            ];
        } catch (PDOException $e) {
            $pdo->rollBack();
            return ['success' => false, 'status' => 'db_error', 'message' => $e->getMessage()];
        }
    } else {
        // Telegram Error
        $errorDesc = $res['description'] ?? 'Telegram API error';
        $errorCode = $res['error_code'] ?? 0;
        $retry_count = ((int)$session['retry_count']) + 1;

        // Rate limit 429
        $retry_after = (int)($res['parameters']['retry_after'] ?? 30);
        if ($errorCode == 429 || strpos($errorDesc, 'Too Many Requests') !== false) {
            $pdo->prepare("
                UPDATE tg_quiz_sessions
                SET next_question_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                    error_message = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$retry_after, "Rate limit: {$errorDesc}", $session_id]);

            return ['success' => false, 'status' => 'rate_limited', 'retry_after' => $retry_after, 'message' => $errorDesc];
        }

        if ($retry_count >= 3) {
            // Max retries reached — skip to next question so quiz doesn't halt forever
            $pdo->prepare("
                UPDATE tg_quiz_sessions
                SET current_question = current_question + 1,
                    retry_count = 0,
                    next_question_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                    error_message = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$timer, "Skipped Q{$qNum}: {$errorDesc}", $session_id]);
        } else {
            // Retry in 10 seconds
            $pdo->prepare("
                UPDATE tg_quiz_sessions
                SET retry_count = ?,
                    next_question_at = DATE_ADD(NOW(), INTERVAL 10 SECOND),
                    error_message = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$retry_count, $errorDesc, $session_id]);
        }

        $pdo->prepare("
            INSERT INTO tg_quiz_logs (session_id, question_id, question_number, status, error_message, retry_count)
            VALUES (?, ?, ?, 'FAILED', ?, ?)
        ")->execute([$session_id, $question_id, $qNum, $errorDesc, $retry_count]);

        return ['success' => false, 'status' => 'telegram_error', 'error' => $errorDesc, 'message' => $errorDesc];
    }
}
