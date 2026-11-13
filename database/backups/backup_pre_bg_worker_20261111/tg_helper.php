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

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

if (!function_exists('cleanUtf8String')) {
    /**
     * Clean and normalize a string to valid UTF-8.
     * Strips UTF-8 BOM, removes non-printable invalid byte sequences,
     * and preserves all valid Devanagari (Hindi) and English characters.
     */
    function cleanUtf8String(?string $str): string {
        if ($str === null || $str === '') return '';
        // Strip UTF-8 BOM (\xEF\xBB\xBF) and zero-width non-breaking space (\x{FEFF})
        $str = preg_replace('/^\xEF\xBB\xBF/u', '', $str);
        $str = preg_replace('/^[\x{FEFF}\x{200B}]/u', '', $str);
        // Verify UTF-8 validity
        if (!mb_check_encoding($str, 'UTF-8')) {
            $detected = mb_detect_encoding($str, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true);
            if ($detected && $detected !== 'UTF-8') {
                $str = mb_convert_encoding($str, 'UTF-8', $detected);
            } else {
                $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
            }
        }
        return trim($str);
    }
}

if (!function_exists('callTelegramAPI')) {
    /**
     * Call Telegram Bot API safely via cURL
     * Uses JSON_UNESCAPED_UNICODE and Content-Type: application/json; charset=UTF-8
     * Bot token is NEVER logged or leaked.
     */
    function callTelegramAPI(string $token, string $method, array $params = []): array {
        if (empty($token)) {
            return ['ok' => false, 'description' => 'Bot token is empty'];
        }

        $jsonPayload = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            $err = json_last_error_msg();
            error_log("callTelegramAPI JSON encode error for method {$method}: {$err}");
            return ['ok' => false, 'description' => 'JSON encoding failed: ' . $err];
        }

        $url = "https://api.telegram.org/bot{$token}/{$method}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=UTF-8',
                'Accept: application/json',
            ],
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
            return ['ok' => false, 'error_code' => $httpCode, 'description' => 'Invalid JSON from Telegram: ' . mb_substr((string)$response, 0, 200, 'UTF-8')];
        }

        return $decoded;
    }
}

/**
 * Send a native Telegram Quiz Poll
 * Fully preserves Hindi (Devanagari) characters using multi-byte UTF-8 handling.
 */
function sendQuizPoll(string $token, $chat_id, array $q, int $timerSeconds = 0): array {
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }

    $options = [];
    $optionLetters = [];
    foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field) {
        $val = trim((string)($q[$field] ?? ''));
        if ($val !== '') {
            $cleaned = cleanUtf8String($val);
            // Telegram poll option character limit is 100 characters
            $options[] = mb_substr($cleaned, 0, 100, 'UTF-8');
            $optionLetters[] = $letter;
        }
    }

    if (count($options) < 2) {
        return ['ok' => false, 'description' => 'Quiz poll requires at least 2 options'];
    }

    $correctLetter = strtoupper(trim((string)($q['correct_answer'] ?? 'A')));
    $correctIdx = array_search($correctLetter, $optionLetters, true);
    if ($correctIdx === false) $correctIdx = 0;

    // Telegram poll question character limit is 300 characters
    $questionRaw = cleanUtf8String($q['question_text'] ?? '');
    $questionText = mb_substr($questionRaw, 0, 300, 'UTF-8');

    if ($questionText === '') {
        return ['ok' => false, 'description' => 'Quiz poll question text is empty'];
    }

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
        $explRaw = cleanUtf8String($q['explanation']);
        if ($explRaw !== '') {
            // Telegram poll explanation character limit is 200 characters
            $payload['explanation'] = mb_substr($explRaw, 0, 200, 'UTF-8');
        }
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

            // Synchronize Live Telegram Status message with Question Sent update
            try {
                syncTelegramQuizLiveStatus($pdo, $session_id, 'question_sent', $timer);
            } catch (\Throwable $e) {
                error_log("Notice: Telegram live status sync in dispatch: " . $e->getMessage());
            }

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

if (!function_exists('getActiveBotToken')) {
    /**
     * Retrieve and decrypt the currently active Telegram Bot Token from DB
     */
    function getActiveBotToken(PDO $pdo): ?string {
        try {
            $stmt = $pdo->prepare("SELECT bot_token_encrypted FROM tg_bots WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['bot_token_encrypted'])) {
                $token = decryptToken($row['bot_token_encrypted']);
                if (!empty($token)) return $token;
            }
        } catch (\Throwable $e) {}

        if (defined('TG_BOT_TOKEN') && !empty(TG_BOT_TOKEN)) {
            return TG_BOT_TOKEN;
        }

        return null;
    }
}

if (!function_exists('getTelegramWebhookInfo')) {
    /**
     * Get current Telegram Webhook info
     */
    function getTelegramWebhookInfo(string $token): array {
        return callTelegramAPI($token, 'getWebhookInfo');
    }
}

if (!function_exists('setTelegramWebhook')) {
    /**
     * Set Telegram Webhook with optional secret token and allowed updates
     */
    function setTelegramWebhook(string $token, string $url, string $secretToken = '', array $allowedUpdates = []): array {
        $params = ['url' => $url];
        if (!empty($secretToken)) {
            $params['secret_token'] = $secretToken;
        }
        if (!empty($allowedUpdates)) {
            $params['allowed_updates'] = $allowedUpdates;
        } else {
            $params['allowed_updates'] = ['message', 'poll_answer', 'my_chat_member'];
        }
        return callTelegramAPI($token, 'setWebhook', $params);
    }
}

if (!function_exists('deleteTelegramWebhook')) {
    /**
     * Delete Telegram Webhook
     */
    function deleteTelegramWebhook(string $token, bool $dropPendingUpdates = false): array {
        $params = [];
        if ($dropPendingUpdates) {
            $params['drop_pending_updates'] = true;
        }
        return callTelegramAPI($token, 'deleteWebhook', $params);
    }
}

if (!function_exists('formatQuizTimer')) {
    /**
     * Format seconds into MM:SS format (e.g. 00:30, 01:15)
     */
    function formatQuizTimer(int $seconds): string {
        $seconds = max(0, $seconds);
        $min = (int)floor($seconds / 60);
        $sec = (int)($seconds % 60);
        return sprintf('%02d:%02d', $min, $sec);
    }
}

if (!function_exists('renderQuizProgressBar')) {
    /**
     * Render a visual text progress bar for Telegram and Web (e.g. [██████████░░░░░░░░░░])
     */
    function renderQuizProgressBar(int $current, int $total, int $barLength = 20): string {
        if ($total <= 0) return str_repeat('░', $barLength);
        $ratio = min(1.0, max(0.0, $current / $total));
        $filled = (int)round($ratio * $barLength);
        $empty = max(0, $barLength - $filled);
        return str_repeat('█', $filled) . str_repeat('░', $empty);
    }
}

if (!function_exists('calculateCandidateQuizScore')) {
    /**
     * Standardized Global Quiz Score Calculation
     *
     * Standard formula:
     *   Final Score = (Correct Answers * Positive Marks) - (Wrong Answers * Negative Marks) + (Skipped Questions * Skipped Marks)
     *   Skipped questions receive 0 marks unless specifically configured otherwise.
     *
     * @param int   $correctCount   Number of correct answers
     * @param int   $wrongCount     Number of wrong answers
     * @param int   $totalQuestions Total questions in the quiz
     * @param float $correctMark    Positive marks per correct answer (e.g. 1.00)
     * @param float $wrongMark      Negative penalty per wrong answer (can be 0.25 or -0.25, normalized via abs())
     * @param float $skippedMark    Marks for skipped questions (defaults to 0.00)
     * @return array
     */
    function calculateCandidateQuizScore(
        int $correctCount,
        int $wrongCount,
        int $totalQuestions,
        float $correctMark = 1.00,
        float $wrongMark = 0.25,
        float $skippedMark = 0.00
    ): array {
        $posMark  = abs((float)$correctMark);
        $negMark  = abs((float)$wrongMark);
        $skipMark = (float)$skippedMark;

        $totalQ    = max(0, $totalQuestions);
        $correct   = max(0, $correctCount);
        $wrong     = max(0, $wrongCount);
        $attempted = $correct + $wrong;
        $skipped   = max(0, $totalQ - $attempted);

        // Core score calculation
        $positiveMarks  = round($correct * $posMark, 4);
        $negativeMarks  = round($wrong * $negMark, 4);
        $skippedMarks   = round($skipped * $skipMark, 4);
        $finalScore     = round($positiveMarks - $negativeMarks + $skippedMarks, 2);

        $maxMarks       = round($totalQ * $posMark, 2);
        $percentage     = $maxMarks > 0 ? round(($finalScore / $maxMarks) * 100, 2) : 0.0;
        $accuracy       = $attempted > 0 ? round(($correct / $attempted) * 100, 2) : 0.0;

        return [
            'total_questions' => $totalQ,
            'attempted'       => $attempted,
            'correct'         => $correct,
            'wrong'           => $wrong,
            'skipped'         => $skipped,
            'positive_marks'  => round($positiveMarks, 2),
            'negative_marks'  => round($negativeMarks, 2),
            'skipped_marks'   => round($skippedMarks, 2),
            'final_score'     => $finalScore,
            'max_marks'       => $maxMarks,
            'percentage'      => $percentage,
            'accuracy'        => $accuracy,
            'pos_mark'        => $posMark,
            'neg_mark'        => $negMark,
        ];
    }
}

if (!function_exists('sendTelegramQuizAnnouncement')) {
    /**
     * Send Pre-Quiz Announcement Alert to Telegram Group/Channel
     */
    function sendTelegramQuizAnnouncement(PDO $pdo, int $sessionId): array {
        $stmt = $pdo->prepare("
            SELECT s.*, b.bot_token_encrypted
            FROM tg_quiz_sessions s
            JOIN tg_bots b ON b.admin_id = s.admin_id AND b.is_active = 1
            WHERE s.id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            return ['success' => false, 'message' => 'Session not found'];
        }

        $token = '';
        if (!empty($session['bot_token_encrypted'])) {
            $token = decryptToken($session['bot_token_encrypted']);
        }
        if (empty($token) && function_exists('getActiveBotToken')) {
            $token = getActiveBotToken($pdo);
        }
        if (empty($token)) {
            return ['success' => false, 'message' => 'Bot token not available'];
        }

        $titleHtml = htmlspecialchars((string)($session['title'] ?: 'Telegram Quiz'));
        $totalQ = max(1, (int)$session['total_questions']);

        $msg = "🔔 <b>UPCOMING QUIZ ALERT</b>\n\n"
             . "📚 <b>Quiz:</b> {$titleHtml}\n"
             . "📝 <b>Total Questions:</b> {$totalQ}\n"
             . "⏰ <b>Quiz Starting Soon!</b>\n\n"
             . "Get ready! The quiz will begin shortly.";

        $res = callTelegramAPI($token, 'sendMessage', [
            'chat_id'    => $session['chat_id'],
            'text'       => $msg,
            'parse_mode' => 'HTML',
        ]);

        if (!empty($res['ok'])) {
            $pdo->prepare("UPDATE tg_quiz_sessions SET announcement_sent_at = NOW(), updated_at = NOW() WHERE id = ?")
                ->execute([$sessionId]);
            return ['success' => true, 'message' => 'Pre-quiz announcement sent to Telegram!'];
        }

        $err = $res['description'] ?? 'Failed to send announcement';
        return ['success' => false, 'message' => "Telegram error: {$err}"];
    }
}

if (!function_exists('syncTelegramQuizLiveStatus')) {
    /**
     * Synchronize the Live Telegram Status Message for a Quiz Session.
     * Uses a single pinned/status message and updates it in-place using editMessageText.
     * Handles rate limits, avoids redundant updates, and falls back to sendMessage if deleted.
     *
     * @param PDO        $pdo
     * @param int|array  $session Session row array or session ID
     * @param string     $event   Event trigger: 'status_tick', 'question_sent', 'pause', 'resume', 'countdown', 'countdown_start', 'started', 'completed'
     * @param int|null   $customRemaining Custom remaining seconds (e.g. from timer calculation)
     * @return array     Result with status and message_id
     */
    function syncTelegramQuizLiveStatus(PDO $pdo, $session, string $event = 'status_tick', ?int $customRemaining = null): array {
        // 1. Fetch full session if ID passed
        if (!is_array($session)) {
            $stmt = $pdo->prepare("
                SELECT s.*, b.bot_token_encrypted
                FROM tg_quiz_sessions s
                JOIN tg_bots b ON b.admin_id = s.admin_id AND b.is_active = 1
                WHERE s.id = ?
            ");
            $stmt->execute([(int)$session]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$session) {
            return ['ok' => false, 'error' => 'Session not found'];
        }

        $sessionId = (int)$session['id'];
        $chatId    = $session['chat_id'];
        $status    = $session['status'];

        // 2. Decrypt token
        $token = '';
        if (!empty($session['bot_token_encrypted'])) {
            $token = decryptToken($session['bot_token_encrypted']);
        }
        if (empty($token) && function_exists('getActiveBotToken')) {
            $token = getActiveBotToken($pdo);
        }
        if (empty($token)) {
            return ['ok' => false, 'error' => 'No active bot token'];
        }

        // 3. Throttle check to avoid Telegram API rate limits (HTTP 429)
        // Question transitions, pause, resume, and completion always bypass throttle
        $isCriticalEvent = in_array($event, ['question_sent', 'pause', 'resume', 'started', 'completed', 'countdown_start']);
        if (!$isCriticalEvent && !empty($session['last_telegram_update'])) {
            $elapsed = time() - strtotime($session['last_telegram_update']);
            if ($elapsed < 3) {
                // Less than 3 seconds since last edit — skip tick to stay well within rate limits
                return ['ok' => true, 'skipped' => true, 'reason' => 'throttled'];
            }
        }

        // 4. Calculate timing & question metrics
        $totalQ = max(1, (int)$session['total_questions']);
        $sentQ  = (int)$session['questions_sent'];
        $currQ  = max(1, min($totalQ, $sentQ > 0 ? $sentQ : ((int)$session['current_question'] + 1)));
        $pct    = min(100, (int)round(($sentQ / $totalQ) * 100));
        $bar    = renderQuizProgressBar($sentQ, $totalQ, 20);

        $remainingSec = $customRemaining;
        if ($remainingSec === null) {
            if ($status === 'COUNTDOWN' && !empty($session['countdown_ends_at'])) {
                $remainingSec = max(0, strtotime($session['countdown_ends_at']) - time());
            } elseif ($status === 'PAUSED') {
                $remainingSec = (int)($session['remaining_time'] ?? 0);
            } elseif (!empty($session['next_question_at'])) {
                $remainingSec = max(0, strtotime($session['next_question_at']) - time());
            } else {
                $remainingSec = 0;
            }
        }
        $timeStr = formatQuizTimer((int)$remainingSec);
        $titleHtml = htmlspecialchars((string)($session['title'] ?: 'Telegram Quiz'));

        // 5. Compose the exact message text based on session state and event
        $text = '';
        if ($status === 'COUNTDOWN') {
            if ($event === 'started' || $remainingSec <= 0) {
                $text = "🟢 <b>QUIZ HAS STARTED!</b>\n\n"
                      . "📚 <b>{$titleHtml}</b>\n\n"
                      . "Best of luck to all participants!";
            } else {
                $text = "🚀 <b>QUIZ STARTING SOON!</b>\n\n"
                      . "📚 <b>{$titleHtml}</b>\n"
                      . "📝 Total Questions: <b>{$totalQ}</b>\n\n"
                      . "⏳ <b>Starting in:</b> <code>{$timeStr}</code>";
            }
        } elseif ($status === 'PAUSED') {
            $text = "⏸ <b>QUIZ PAUSED</b>\n\n"
                  . "📚 <b>{$titleHtml}</b>\n\n"
                  . "❓ Current Question: <b>{$currQ} / {$totalQ}</b>\n"
                  . "📈 Progress: <b>{$pct}%</b>\n"
                  . "<code>[{$bar}] {$pct}%</code>\n\n"
                  . "⏸ <i>Quiz has been temporarily paused by host. Timer is stopped.</i>";
        } elseif ($status === 'COMPLETED' || $event === 'completed') {
            $text = "🏆 <b>QUIZ COMPLETED!</b>\n\n"
                  . "📚 <b>{$titleHtml}</b>\n\n"
                  . "✅ <b>Total Questions:</b> {$totalQ}\n"
                  . "🎯 <b>Quiz Successfully Completed!</b>\n\n"
                  . "Thank you for participating.";
        } elseif ($status === 'RUNNING') {
            if ($event === 'resume') {
                $text = "▶️ <b>QUIZ RESUMED</b>\n\n"
                      . "📚 <b>{$titleHtml}</b>\n\n"
                      . "❓ Current Question: <b>{$currQ} / {$totalQ}</b>\n"
                      . "📈 Progress: <b>{$pct}%</b>\n"
                      . "<code>[{$bar}] {$pct}%</code>\n\n"
                      . "⏳ <b>Time Remaining:</b> <code>{$timeStr}</code>";
            } else {
                $text = "📊 <b>LIVE QUIZ STATUS</b>\n\n"
                      . "📚 <b>{$titleHtml}</b>\n\n"
                      . "🟢 Quiz Status: <b>LIVE</b>\n\n"
                      . "❓ Current Question: <b>{$currQ} / {$totalQ}</b>\n"
                      . "📈 Progress: <b>{$pct}%</b>\n"
                      . "<code>[{$bar}] {$pct}%</code>\n\n"
                      . "⏳ <b>Time Remaining:</b> <code>{$timeStr}</code>";
            }
        } else {
            return ['ok' => true, 'skipped' => true, 'reason' => "Status is {$status}"];
        }

        // 6. Check if text is identical to last sent text to avoid Telegram 400 "message is not modified"
        if (!empty($session['last_telegram_status_text']) && $session['last_telegram_status_text'] === $text) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'unmodified'];
        }

        // 7. Dispatch to Telegram
        $statusMsgId = !empty($session['telegram_status_message_id']) ? (int)$session['telegram_status_message_id'] : 0;
        $res = null;

        if ($statusMsgId > 0) {
            // Edit the existing status message
            $res = callTelegramAPI($token, 'editMessageText', [
                'chat_id'    => $chatId,
                'message_id' => $statusMsgId,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ]);

            // If edit returned "message is not modified", treat as successful
            if (empty($res['ok']) && strpos($res['description'] ?? '', 'message is not modified') !== false) {
                $pdo->prepare("UPDATE tg_quiz_sessions SET last_telegram_update = NOW(), last_telegram_status_text = ? WHERE id = ?")
                    ->execute([$text, $sessionId]);
                return ['ok' => true, 'message_id' => $statusMsgId];
            }

            // If message was deleted or invalid, fallback to sending a fresh status message
            if (empty($res['ok']) && (
                strpos($res['description'] ?? '', 'message to edit not found') !== false ||
                strpos($res['description'] ?? '', 'MESSAGE_ID_INVALID') !== false ||
                strpos($res['description'] ?? '', 'message can\'t be edited') !== false
            )) {
                $statusMsgId = 0; // force new message below
            }
        }

        if ($statusMsgId <= 0) {
            // Send new status message
            $res = callTelegramAPI($token, 'sendMessage', [
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ]);
            if (!empty($res['ok']) && !empty($res['result']['message_id'])) {
                $statusMsgId = (int)$res['result']['message_id'];
            }
        }

        // 8. Update database session with new status message state
        if (!empty($res['ok']) && $statusMsgId > 0) {
            $pdo->prepare("
                UPDATE tg_quiz_sessions
                SET telegram_status_message_id = ?,
                    last_telegram_update       = NOW(),
                    last_telegram_status_text  = ?
                WHERE id = ?
            ")->execute([$statusMsgId, $text, $sessionId]);

            return ['ok' => true, 'message_id' => $statusMsgId, 'text' => $text];
        }

        return ['ok' => false, 'error' => $res['description'] ?? 'Telegram API error'];
    }
}
