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

if (!function_exists('getTelegramApiBaseUrl')) {
    /**
     * Get base URL for Telegram Bot API calls.
     * Allows reverse-proxy override for hosts (like InfinityFree) that block api.telegram.org.
     */
    function getTelegramApiBaseUrl(): string {
        if (defined('TG_API_BASE_URL') && TG_API_BASE_URL !== '') {
            return rtrim(TG_API_BASE_URL, '/');
        }
        $env = getenv('TG_API_BASE_URL');
        if ($env) {
            return rtrim($env, '/');
        }
        return 'https://api.telegram.org';
    }
}

if (!function_exists('callTelegramAPI')) {
    /**
     * Call Telegram Bot API safely via cURL
     * Uses JSON_UNESCAPED_UNICODE and Content-Type: application/json; charset=UTF-8
     * Bot token is NEVER logged or leaked.
     */
    function callTelegramAPI(string $token, string $method, array $params = []): array {
        if (defined('TG_TEST_MODE') && constant('TG_TEST_MODE') === true) {
            return [
                'ok' => true,
                'result' => [
                    'message_id' => rand(10000, 99999),
                    'chat'       => ['id' => $params['chat_id'] ?? 1],
                    'poll'       => ['id' => 'poll_' . rand(1000, 9999)]
                ]
            ];
        }

        if (empty($token)) {
            return ['ok' => false, 'description' => 'Bot token is empty'];
        }

        $jsonPayload = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            $err = json_last_error_msg();
            error_log("callTelegramAPI JSON encode error for method {$method}: {$err}");
            return ['ok' => false, 'description' => 'JSON encoding failed: ' . $err];
        }

        $baseUrl = getTelegramApiBaseUrl();
        $url = "{$baseUrl}/bot{$token}/{$method}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=UTF-8',
                'Accept: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            $hint = '';
            if (stripos($error, 'Could not resolve host') !== false && stripos($url, 'api.telegram.org') !== false) {
                $hint = ' (Notice: Free hosting such as InfinityFree blocks direct outbound connections to api.telegram.org. Set TG_API_BASE_URL to a reverse proxy or switch to standard hosting/VPS).';
            }
            return ['ok' => false, 'error_code' => $httpCode ?: 0, 'description' => 'cURL Error: ' . $error . $hint];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error_code' => $httpCode, 'description' => 'Invalid JSON from Telegram: ' . mb_substr((string)$response, 0, 200, 'UTF-8')];
        }

        return $decoded;
    }
}

/**
 * Format question text with numbering (e.g. 📝 Question 1/5)
 * Enforces Telegram poll question length limit (default 300 chars)
 */
function formatTelegramQuestionText(string $questionRaw, int $qNum = 0, int $totalQ = 0, int $maxLen = 300): string {
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }
    $questionRaw = cleanUtf8String($questionRaw);
    if ($qNum > 0 && $totalQ > 0) {
        if (!preg_match('/^(📝\s*)?Question\s*\d+\s*(\/|of)\s*\d+/ui', $questionRaw)) {
            $prefix = "📝 Question {$qNum}/{$totalQ}\n\n";
            $allowedLen = $maxLen - mb_strlen($prefix, 'UTF-8');
            return $prefix . mb_substr($questionRaw, 0, max(1, $allowedLen), 'UTF-8');
        }
    }
    return mb_substr($questionRaw, 0, $maxLen, 'UTF-8');
}

/**
 * Format poll option strings with "A. ", "B. ", "C. ", "D. " without double prefixing
 */
function formatTelegramPollOptions(array $rawOptions, int $maxLen = 100): array {
    $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
    $formatted = [];
    foreach ($rawOptions as $idx => $opt) {
        $val = trim((string)$opt);
        if ($val === '') continue;
        $cleaned = cleanUtf8String($val);
        $letter = $letters[$idx] ?? chr(65 + $idx);
        if (!preg_match('/^[A-Z][\.\)\s-]/iu', $cleaned)) {
            $cleaned = $letter . '. ' . $cleaned;
        }
        $formatted[] = mb_substr($cleaned, 0, $maxLen, 'UTF-8');
    }
    return $formatted;
}

/**
 * Send a native Telegram Quiz Poll
 * Fully preserves Hindi (Devanagari) characters using multi-byte UTF-8 handling.
 * Displays question numbering (e.g. 📝 Question 1/5) and option letters (A, B, C, D)
 */
function sendQuizPoll(string $token, $chat_id, array $q, int $timerSeconds = 0, int $qNum = 0, int $totalQ = 0): array {
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }

    $rawOpts = [];
    $optionLetters = [];
    foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field) {
        $val = trim((string)($q[$field] ?? ''));
        if ($val !== '') {
            $rawOpts[] = $val;
            $optionLetters[] = $letter;
        }
    }

    $options = formatTelegramPollOptions($rawOpts, 100);

    if (count($options) < 2) {
        return ['ok' => false, 'description' => 'Quiz poll requires at least 2 options'];
    }

    $correctLetter = strtoupper(trim((string)($q['correct_answer'] ?? $q['correct_option'] ?? 'A')));
    $correctIdx = array_search($correctLetter, $optionLetters, true);
    if ($correctIdx === false) $correctIdx = 0;

    // Telegram poll question character limit is 300 characters
    $questionRaw = cleanUtf8String($q['question_text'] ?? '');
    if ($questionRaw === '') {
        return ['ok' => false, 'description' => 'Quiz poll question text is empty'];
    }

    // Add Question Numbering (e.g. 📝 Question 1/5)
    $questionText = formatTelegramQuestionText($questionRaw, $qNum, $totalQ, 300);

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

    // 5. Send to Telegram as Quiz Poll (with Question Numbering & Option Letters)
    $res = sendQuizPoll($token, $session['chat_id'], $question, $timer, $qNum, $total_q);

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
            $params['allowed_updates'] = ['message', 'poll_answer', 'my_chat_member', 'callback_query'];
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

// ============================================================
// COMPLETE QUIZ SHARING & PROMOTION HELPERS
// ============================================================

if (!function_exists('escapeTelegramHtml')) {
    /**
     * Escape text safely for Telegram HTML parse_mode.
     * Preserves UTF-8 Hindi / Devanagari characters.
     * Keeps quotes (' and ") natural because Telegram Bot API HTML parser
     * does not support &#039; and prints it literally.
     */
    function escapeTelegramHtml(?string $text): string {
        if ($text === null || $text === '') return '';
        $clean = function_exists('cleanUtf8String') ? cleanUtf8String($text) : $text;
        $decoded = htmlspecialchars_decode($clean, ENT_QUOTES | ENT_HTML5);
        return htmlspecialchars($decoded, ENT_NOQUOTES, 'UTF-8');
    }
}

if (!function_exists('getTelegramPromoSettings')) {
    /**
     * Retrieve configurable Telegram promotion & branding details from settings table
     */
    function getTelegramPromoSettings(PDO $pdo): array {
        $keys = [
            'tg_promo_group_name',
            'tg_promo_group_link',
            'tg_promo_admin_username',
            'tg_promo_admin_id',
            'tg_promo_footer_message'
        ];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        
        $settings = [
            'group_name'     => 'Bankers Lifes❤️',
            'group_link'     => 'https://t.me/bankersguts',
            'admin_username' => '@Abhijeet_Mishra',
            'admin_id'       => '9272349548',
            'footer_message' => 'Stay connected for more quizzes, mock tests and exam preparation.'
        ];

        try {
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
            $stmt->execute($keys);
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            if (isset($rows['tg_promo_group_name']) && trim($rows['tg_promo_group_name']) !== '') {
                $settings['group_name'] = trim($rows['tg_promo_group_name']);
            }
            if (isset($rows['tg_promo_group_link']) && trim($rows['tg_promo_group_link']) !== '') {
                $settings['group_link'] = trim($rows['tg_promo_group_link']);
            }
            if (isset($rows['tg_promo_admin_username']) && trim($rows['tg_promo_admin_username']) !== '') {
                $settings['admin_username'] = trim($rows['tg_promo_admin_username']);
            }
            if (isset($rows['tg_promo_admin_id'])) {
                $settings['admin_id'] = trim($rows['tg_promo_admin_id']);
            }
            if (isset($rows['tg_promo_footer_message']) && trim($rows['tg_promo_footer_message']) !== '') {
                $settings['footer_message'] = trim($rows['tg_promo_footer_message']);
            }
        } catch (\Throwable $e) {
            error_log("Error in getTelegramPromoSettings: " . $e->getMessage());
        }

        return $settings;
    }
}

if (!function_exists('saveTelegramPromoSettings')) {
    /**
     * Save configurable Telegram promotion & branding details to settings table
     */
    function saveTelegramPromoSettings(PDO $pdo, array $settings): bool {
        $map = [
            'tg_promo_group_name'     => trim($settings['group_name'] ?? ''),
            'tg_promo_group_link'     => trim($settings['group_link'] ?? ''),
            'tg_promo_admin_username' => trim($settings['admin_username'] ?? ''),
            'tg_promo_admin_id'       => trim($settings['admin_id'] ?? ''),
            'tg_promo_footer_message' => trim($settings['footer_message'] ?? '')
        ];

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            foreach ($map as $key => $val) {
                $stmt->execute([$key, $val]);
            }
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Error in saveTelegramPromoSettings: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getShareableQuizzes')) {
    /**
     * Retrieve all available quizzes (Telegram Quiz Sets + Website Mock Tests)
     */
    function getShareableQuizzes(PDO $pdo, int $admin_id): array {
        $res = [
            'tg_sets'  => [],
            'mt_tests' => []
        ];

        // 1. Telegram Quiz Sets
        try {
            $stmt = $pdo->prepare("
                SELECT id, title, description, total_questions, default_timer, created_at
                FROM tg_quiz_sets
                WHERE admin_id = ? AND is_active = 1
                ORDER BY created_at DESC
            ");
            $stmt->execute([$admin_id]);
            $res['tg_sets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        // 2. Website Mock Tests
        try {
            $stmt = $pdo->query("
                SELECT m.id, m.title, m.total_questions, m.status, m.created_at,
                       e.name as exam_name, c.name as category_name
                FROM mt_mock_tests m
                LEFT JOIN mt_exams e ON e.id = m.exam_id
                LEFT JOIN mt_exam_categories c ON c.id = e.category_id
                ORDER BY m.id DESC
            ");
            $res['mt_tests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        return $res;
    }
}

if (!function_exists('getQuizDetailsForSharing')) {
    /**
     * Retrieve questions and details of a quiz set or mock test for sharing
     */
    function getQuizDetailsForSharing(PDO $pdo, string $quiz_type, int $quiz_id): ?array {
        if ($quiz_type === 'mt_mock') {
            // Mock test
            $stmt = $pdo->prepare("SELECT id, title, total_questions FROM mt_mock_tests WHERE id = ?");
            $stmt->execute([$quiz_id]);
            $mock = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$mock) return null;

            $qStmt = $pdo->prepare("
                SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
                       q.correct_option as correct_answer, q.explanation
                FROM mt_test_questions tq
                JOIN mt_questions q ON q.id = tq.question_id
                WHERE tq.mock_test_id = ? AND q.status = 'active'
                ORDER BY tq.display_order ASC, tq.id ASC
            ");
            $qStmt->execute([$quiz_id]);
            $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'type'            => 'mt_mock',
                'id'              => (int)$mock['id'],
                'title'           => $mock['title'],
                'total_questions' => count($questions),
                'questions'       => $questions
            ];
        } else {
            // Telegram Quiz Set (default)
            $stmt = $pdo->prepare("SELECT id, title, total_questions FROM tg_quiz_sets WHERE id = ?");
            $stmt->execute([$quiz_id]);
            $set = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$set) return null;

            $qStmt = $pdo->prepare("
                SELECT id, question_number, question_text, option_a, option_b, option_c, option_d,
                       correct_answer, explanation
                FROM tg_quiz_questions
                WHERE quiz_set_id = ? AND is_active = 1
                ORDER BY question_number ASC, id ASC
            ");
            $qStmt->execute([$quiz_id]);
            $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'type'            => 'tg_set',
                'id'              => (int)$set['id'],
                'title'           => $set['title'],
                'total_questions' => count($questions),
                'questions'       => $questions
            ];
        }
    }
}

if (!function_exists('buildCompleteQuizTelegramMessages')) {
    /**
     * Build sequential Telegram formatted messages for the complete quiz set.
     * Complies with Telegram's 4096 character limit (uses 3800 safe limit).
     * Never breaks a question or options in the middle.
     * Includes answer key and promotional details.
     */
    function buildCompleteQuizTelegramMessages(array $quizData, array $promoSettings, array $options = []): array {
        $title     = trim((string)($quizData['title'] ?? 'Mock Test'));
        $questions = $quizData['questions'] ?? [];
        $totalQ    = count($questions);

        $includeExplanation = !empty($options['include_explanation']);
        $explanationPos     = $options['explanation_position'] ?? 'answer_key'; // 'answer_key' or 'below_question' or 'none'

        if ($totalQ === 0) {
            return [
                'total_parts'     => 0,
                'total_questions' => 0,
                'messages'        => []
            ];
        }

        // 1. Build individual Question Blocks and Answer Key Items
        $qBlocks = [];
        $answerKeyItems = [];

        foreach ($questions as $idx => $q) {
            $num   = $idx + 1;
            $qText = trim((string)($q['question_text'] ?? ''));

            $lines = [];
            $lines[] = "📝 <b>Question {$num}/{$totalQ}</b>\n";
            $lines[] = escapeTelegramHtml($qText) . "\n";

            foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field) {
                $val = trim((string)($q[$field] ?? ''));
                if ($val !== '') {
                    if (!preg_match('/^[A-D][\.\)\s-]/iu', $val)) {
                        $optText = "<b>{$letter}.</b> " . escapeTelegramHtml($val);
                    } else {
                        $optText = escapeTelegramHtml($val);
                    }
                    $lines[] = $optText;
                }
            }

            if ($includeExplanation && $explanationPos === 'below_question' && !empty($q['explanation'])) {
                $lines[] = "\n💡 <i>Explanation: " . escapeTelegramHtml(trim((string)$q['explanation'])) . "</i>";
            }

            $qBlocks[] = implode("\n", $lines);

            // Answer Key Item
            $correctLetter = strtoupper(trim((string)($q['correct_answer'] ?? $q['correct_option'] ?? '')));
            $ansLine = "{$num}. <b>{$correctLetter}</b>";
            if ($includeExplanation && $explanationPos === 'answer_key' && !empty($q['explanation'])) {
                $ansLine .= " — <i>" . escapeTelegramHtml(trim((string)$q['explanation'])) . "</i>";
            }
            $answerKeyItems[] = $ansLine;
        }

        // 2. Build Answer Key Block
        $answerKeyBlock = "📌 <b>ANSWER KEY</b>\n\n" . implode("\n", $answerKeyItems);

        // 3. Build Promotional & Branding Block
        $promoLines = [];
        $groupLink     = trim((string)($promoSettings['group_link'] ?? ''));
        $groupName     = trim((string)($promoSettings['group_name'] ?? 'Our Telegram Group'));
        $adminUsername = trim((string)($promoSettings['admin_username'] ?? ''));
        $adminId       = trim((string)($promoSettings['admin_id'] ?? ''));
        $footerMsg     = trim((string)($promoSettings['footer_message'] ?? 'Stay connected for more quizzes, mock tests and exam preparation.'));

        $promoLines[] = "📢 <b>JOIN OUR TELEGRAM GROUP</b>\n";
        if ($groupLink !== '') {
            $promoLines[] = "<a href=\"" . escapeTelegramHtml($groupLink) . "\">" . escapeTelegramHtml($groupName ?: $groupLink) . "</a>\n" . escapeTelegramHtml($groupLink);
        } else {
            $promoLines[] = escapeTelegramHtml($groupName);
        }

        if ($adminUsername !== '' || $adminId !== '') {
            $promoLines[] = "\n👤 <b>Contact / Telegram Admin:</b>";
            if ($adminUsername !== '') {
                $cleanUser = ltrim($adminUsername, '@');
                $promoLines[] = "<a href=\"https://t.me/" . escapeTelegramHtml($cleanUser) . "\">@" . escapeTelegramHtml($cleanUser) . "</a>";
            }
            if ($adminId !== '') {
                $promoLines[] = "Telegram ID: <code>" . escapeTelegramHtml($adminId) . "</code>";
            }
        }

        if ($footerMsg !== '') {
            $promoLines[] = "\n" . escapeTelegramHtml($footerMsg);
        }

        $promoBlock = implode("\n", $promoLines);

        // 4. Chunking Engine: 3800 safe character limit
        $MAX_LEN   = 3800;
        $separator = "\n\n━━━━━━━━━━━━━━━━\n\n";
        $headerEstimate = 220;

        $qChunks  = [];
        $curChunk = [];
        $curLen   = $headerEstimate;

        foreach ($qBlocks as $block) {
            $blockLen = mb_strlen($block . $separator, 'UTF-8');
            if ($curLen + $blockLen > $MAX_LEN && !empty($curChunk)) {
                $qChunks[] = $curChunk;
                $curChunk  = [$block];
                $curLen    = $headerEstimate + $blockLen;
            } else {
                $curChunk[] = $block;
                $curLen    += $blockLen;
            }
        }
        if (!empty($curChunk)) {
            $qChunks[] = $curChunk;
        }

        // Tail block (Answer key + Promo)
        $tailBlock = $answerKeyBlock . $separator . $promoBlock;
        $tailLen   = mb_strlen($separator . $tailBlock, 'UTF-8');

        // Check if tail fits in the final question chunk
        $hasSeparateTailChunk = false;
        $lastChunkIdx = count($qChunks) - 1;

        if ($lastChunkIdx >= 0) {
            $lastChunkQuestionsLen = $headerEstimate;
            foreach ($qChunks[$lastChunkIdx] as $b) {
                $lastChunkQuestionsLen += mb_strlen($b . $separator, 'UTF-8');
            }

            if ($lastChunkQuestionsLen + $tailLen <= $MAX_LEN) {
                $hasSeparateTailChunk = false;
            } else {
                $hasSeparateTailChunk = true;
            }
        } else {
            $hasSeparateTailChunk = true;
        }

        $totalParts = count($qChunks) + ($hasSeparateTailChunk ? 1 : 0);
        if ($totalParts <= 0) $totalParts = 1;

        $makeHeader = function(int $partNum, int $totalParts) use ($title, $totalQ): string {
            $partStr = $totalParts > 1 ? " (Part {$partNum}/{$totalParts})" : "";
            return "🎯 <b>" . escapeTelegramHtml(mb_strtoupper($title, 'UTF-8')) . "</b>{$partStr}\n\n"
                 . "📚 <b>Total Questions:</b> {$totalQ}\n\n"
                 . "━━━━━━━━━━━━━━━━\n\n";
        };

        $messages = [];
        foreach ($qChunks as $pIdx => $blocks) {
            $partNum = $pIdx + 1;
            $isLastQChunk = ($pIdx === count($qChunks) - 1);

            $msg = $makeHeader($partNum, $totalParts);
            $msg .= implode($separator, $blocks);

            if ($isLastQChunk && !$hasSeparateTailChunk) {
                $msg .= $separator . $tailBlock;
            }
            $messages[] = $msg;
        }

        if ($hasSeparateTailChunk) {
            $partNum = count($messages) + 1;
            $msg = $makeHeader($partNum, $totalParts);
            $msg .= $tailBlock;
            $messages[] = $msg;
        }

        return [
            'total_parts'     => count($messages),
            'total_questions' => $totalQ,
            'messages'        => $messages
        ];
    }
}

if (!function_exists('shareCompleteQuizToTelegram')) {
    /**
     * Dispatch complete quiz set to an authorized Telegram group.
     * Validates bot permissions, detects duplicates, splits long quizzes into sequential messages.
     */
    function shareCompleteQuizToTelegram(PDO $pdo, int $admin_id, string $quiz_type, int $quiz_id, $chat_id, array $options = []): array {
        // 1. Get Active Bot Token
        $token = getActiveBotToken($pdo);
        if (empty($token)) {
            return ['success' => false, 'message' => 'No active Telegram bot configured. Please connect a bot first.'];
        }

        // 2. Get Bot Info (bot_id)
        $botStmt = $pdo->prepare("SELECT bot_id, bot_username FROM tg_bots WHERE admin_id = ? AND is_active = 1 LIMIT 1");
        $botStmt->execute([$admin_id]);
        $botRow = $botStmt->fetch(PDO::FETCH_ASSOC);
        $botId = $botRow['bot_id'] ?? null;
        $botUsername = $botRow['bot_username'] ?? 'Bot';

        // 3. Verify target chat exists and check bot permissions
        $chatStmt = $pdo->prepare("SELECT chat_title FROM tg_chats WHERE admin_id = ? AND chat_id = ? LIMIT 1");
        $chatStmt->execute([$admin_id, $chat_id]);
        $chatTitle = $chatStmt->fetchColumn() ?: "Chat {$chat_id}";

        $permCheck = callTelegramAPI($token, 'getChat', ['chat_id' => $chat_id]);
        if (empty($permCheck['ok'])) {
            $err = $permCheck['description'] ?? 'Telegram chat unreachable';
            return [
                'success' => false,
                'message' => "Cannot access Telegram group ({$chat_id}): {$err}. Make sure @{$botUsername} is added to the group."
            ];
        }

        if (!empty($permCheck['result']['title'])) {
            $chatTitle = $permCheck['result']['title'];
        }

        // Check bot membership status in the group
        if ($botId) {
            $memberCheck = callTelegramAPI($token, 'getChatMember', ['chat_id' => $chat_id, 'user_id' => $botId]);
            if (!empty($memberCheck['ok'])) {
                $status = $memberCheck['result']['status'] ?? '';
                if (in_array($status, ['kicked', 'left'])) {
                    return [
                        'success' => false,
                        'message' => "The bot @{$botUsername} is not in this group (status: {$status}). Please add it to the group first."
                    ];
                }
            }
        }

        // 4. Duplicate Share Protection (recent threshold: 15 minutes)
        $confirmDuplicate = !empty($options['confirm_duplicate']);
        if (!$confirmDuplicate) {
            $dupStmt = $pdo->prepare("
                SELECT id, created_at
                FROM tg_quiz_shares
                WHERE quiz_type = ? AND quiz_id = ? AND chat_id = ? AND status = 'SUCCESS'
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ORDER BY id DESC
                LIMIT 1
            ");
            $dupStmt->execute([$quiz_type, $quiz_id, $chat_id]);
            $recentShare = $dupStmt->fetch(PDO::FETCH_ASSOC);

            if ($recentShare) {
                $timeAgo = date('H:i', strtotime($recentShare['created_at']));
                return [
                    'success'              => false,
                    'is_duplicate_warning' => true,
                    'message'              => "⚠️ This quiz was already shared to '{$chatTitle}' at {$timeAgo} (within the last 15 minutes). If you want to share it again, please confirm."
                ];
            }
        }

        // 5. Fetch Quiz Data
        $quizData = getQuizDetailsForSharing($pdo, $quiz_type, $quiz_id);
        if (!$quizData || empty($quizData['questions'])) {
            return ['success' => false, 'message' => 'Quiz contains no active questions to share.'];
        }

        // 6. Fetch Promo Settings
        $promoSettings = getTelegramPromoSettings($pdo);

        // 7. Format Complete Quiz into Sequential Telegram Messages
        $built = buildCompleteQuizTelegramMessages($quizData, $promoSettings, $options);
        $messages = $built['messages'];
        $totalParts = count($messages);

        if ($totalParts === 0) {
            return ['success' => false, 'message' => 'Failed to format quiz messages.'];
        }

        // 8. Dispatch Messages to Telegram
        $sentMessageIds = [];
        $firstError = null;

        foreach ($messages as $idx => $msg) {
            $res = callTelegramAPI($token, 'sendMessage', [
                'chat_id'                  => $chat_id,
                'text'                     => $msg,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if (!empty($res['ok']) && !empty($res['result']['message_id'])) {
                $sentMessageIds[] = (int)$res['result']['message_id'];
            } else {
                $firstError = $res['description'] ?? 'Telegram API error';
                break;
            }

            // Small delay between parts to maintain sequence and respect rate limits
            if ($idx < $totalParts - 1) {
                usleep(700000); // 0.7 second
            }
        }

        // 9. Record Share History
        $isSuccess = ($firstError === null && count($sentMessageIds) === $totalParts);
        $statusStr = $isSuccess ? 'SUCCESS' : 'FAILED';
        $msgIdsJson = json_encode($sentMessageIds);

        try {
            $ins = $pdo->prepare("
                INSERT INTO tg_quiz_shares
                    (admin_id, quiz_type, quiz_id, quiz_title, chat_id, chat_title,
                     total_questions, parts_count, status, telegram_message_ids, error_message, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([
                $admin_id, $quiz_type, $quiz_id, $quizData['title'], $chat_id, $chatTitle,
                $quizData['total_questions'], $totalParts, $statusStr, $msgIdsJson, $firstError
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to insert tg_quiz_shares log: " . $e->getMessage());
        }

        if ($isSuccess) {
            return [
                'success'         => true,
                'parts_sent'      => $totalParts,
                'total_questions' => $quizData['total_questions'],
                'chat_title'      => $chatTitle,
                'message_ids'     => $sentMessageIds,
                'message'         => "🎉 Complete quiz successfully shared to '{$chatTitle}' in {$totalParts} message" . ($totalParts > 1 ? 's' : '') . "!"
            ];
        } else {
            return [
                'success'     => false,
                'error'       => $firstError,
                'parts_sent'  => count($sentMessageIds),
                'total_parts' => $totalParts,
                'message'     => "Failed to share quiz to Telegram: {$firstError}"
            ];
        }
    }
}

if (!function_exists('getQuizShareHistory')) {
    /**
     * Retrieve complete quiz sharing history for admin
     */
    function getQuizShareHistory(PDO $pdo, int $admin_id, int $limit = 50): array {
        try {
            $stmt = $pdo->prepare("
                SELECT id, quiz_type, quiz_id, quiz_title, chat_id, chat_title,
                       total_questions, parts_count, status, telegram_message_ids, error_message, created_at
                FROM tg_quiz_shares
                WHERE admin_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $admin_id, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('isTelegramQuizDaemonActive')) {
    /**
     * Check if background Telegram quiz polling daemon is currently active
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
        return true;
    }
}

if (!function_exists('ensureTelegramQuizDaemonRunning')) {
    /**
     * Ensure background Telegram polling daemon is running
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
            if (class_exists('COM')) {
                try {
                    $wsh = new COM("WScript.Shell");
                    $wsh->Run("\"{$phpCli}\" \"{$daemonScript}\"", 0, false);
                    return true;
                } catch (\Throwable $e) {}
            }
            $cmd = "start /B \"\" \"{$phpCli}\" \"{$daemonScript}\"";
            @pclose(@popen($cmd, "r"));
        } else {
            @exec("\"{$phpCli}\" \"{$daemonScript}\" > /dev/null 2>&1 &");
        }

        return true;
    }
}
