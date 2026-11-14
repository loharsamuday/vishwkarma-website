<?php
// api/tg_quiz/tg_playable_helper.php
// ============================================================
// Core Playable Quiz Engine:
// Mode A: Individual Quiz (Private Chat via Deep Link)
// Mode B: Common Live Group Quiz (Simultaneous Group Quiz with Timer & Leaderboard)
// Mode C: Both Modes in Shared Invitation
// Concurrency Control, Real-Time Scoring, Dynamic Leaderboard & Session Recovery
// ============================================================

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/tg_helper.php';

function ensureTelegramPlayableQuizSchema(PDO $pdo): void {
    static $schemaReady = false;
    if ($schemaReady) {
        return;
    }

    $requiredTables = [
        'tg_shared_quizzes',
        'tg_individual_quiz_sessions',
        'tg_individual_quiz_answers',
        'tg_group_quiz_sessions',
        'tg_group_quiz_answers',
    ];
    $tableExists = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
    ");
    $missingTables = [];
    foreach ($requiredTables as $table) {
        $tableExists->execute([$table]);
        if (!(int)$tableExists->fetchColumn()) {
            $missingTables[] = $table;
        }
    }

    if ($missingTables) {
        $migrationPath = __DIR__ . '/../../database/tg_playable_quiz_migration.sql';
        if (!is_file($migrationPath) || !is_readable($migrationPath)) {
            throw new RuntimeException('Playable quiz migration file is missing or unreadable');
        }
        $migrationSql = file_get_contents($migrationPath);
        if ($migrationSql === false) {
            throw new RuntimeException('Playable quiz migration file could not be read');
        }

        foreach (explode(';', $migrationSql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }

        $stillMissing = [];
        foreach ($requiredTables as $table) {
            $tableExists->execute([$table]);
            if (!(int)$tableExists->fetchColumn()) {
                $stillMissing[] = $table;
            }
        }
        if ($stillMissing) {
            throw new RuntimeException('Playable quiz migration did not create: ' . implode(', ', $stillMissing));
        }
    }

    $schemaReady = true;
}

class TelegramPlayableQuizEngine {

    /**
     * Generate secure random token
     */
    public static function generateToken(string $prefix = 'quiz'): string {
        return $prefix . '_' . bin2hex(random_bytes(16));
    }

    /**
     * Share a quiz with one or multiple Telegram groups
     */
    public static function sharePlayableQuiz(
        PDO $pdo,
        int $admin_id,
        string $quiz_type,
        int $quiz_id,
        array $chat_ids,
        string $mode = 'both',
        array $options = []
    ): array {
        $token = getActiveBotToken($pdo);
        if (empty($token)) {
            return ['success' => false, 'message' => 'No active Telegram bot configured.'];
        }

        // Fetch bot username
        $botStmt = $pdo->prepare("SELECT bot_username FROM tg_bots WHERE admin_id = ? AND is_active = 1 LIMIT 1");
        $botStmt->execute([$admin_id]);
        $botUsername = $botStmt->fetchColumn() ?: 'YourQuizBot';
        $botUsername = ltrim($botUsername, '@');

        // Fetch quiz details
        $quizData = getQuizDetailsForSharing($pdo, $quiz_type, $quiz_id);
        if (!$quizData || empty($quizData['questions'])) {
            return ['success' => false, 'message' => 'Quiz contains no questions to share.'];
        }

        $totalQ        = (int)$quizData['total_questions'];
        $quizTitle     = $quizData['title'];
        $timerSeconds  = max(5, min(300, (int)($options['timer_seconds'] ?? 20)));
        $correctMark   = round((float)($options['correct_mark'] ?? 1.00), 2);
        $wrongMark     = round((float)($options['wrong_mark'] ?? 0.00), 2);
        $promoSettings = getTelegramPromoSettings($pdo);

        $results = [];
        $successCount = 0;

        foreach ($chat_ids as $chatId) {
            $chatId = trim((string)$chatId);
            if (empty($chatId)) continue;

            // Fetch chat title
            $cStmt = $pdo->prepare("SELECT chat_title FROM tg_chats WHERE admin_id = ? AND chat_id = ? LIMIT 1");
            $cStmt->execute([$admin_id, $chatId]);
            $chatTitle = $cStmt->fetchColumn() ?: "Group {$chatId}";

            // Generate unique share token for this group
            $shareToken = self::generateToken('qz');

            // Build invitation message
            $invitation = self::buildInvitationMessage(
                $quizTitle,
                $totalQ,
                $timerSeconds,
                $mode,
                $botUsername,
                $shareToken,
                $promoSettings
            );

            // Send to Telegram Group
            $tgRes = callTelegramAPI($token, 'sendMessage', [
                'chat_id'                  => $chatId,
                'text'                     => $invitation['text'],
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
                'reply_markup'             => $invitation['reply_markup']
            ]);

            $isSent = !empty($tgRes['ok']);
            $msgId = $isSent ? ($tgRes['result']['message_id'] ?? null) : null;
            $errorDesc = $tgRes['description'] ?? null;

            if ($isSent) {
                $ins = $pdo->prepare("
                    INSERT INTO tg_shared_quizzes
                        (admin_id, share_token, quiz_type, quiz_id, quiz_title, total_questions,
                         chat_id, chat_title, mode, timer_seconds, correct_mark, wrong_mark,
                         include_explanation, telegram_invitation_message_id, status, created_at)
                    VALUES
                        (?, ?, ?, ?, ?, ?,
                         ?, ?, ?, ?, ?, ?,
                         1, ?, 'ACTIVE', NOW())
                ");
                $ins->execute([
                    $admin_id, $shareToken, $quiz_type, $quiz_id, $quizTitle, $totalQ,
                    $chatId, $chatTitle, $mode, $timerSeconds, $correctMark, $wrongMark,
                    $msgId
                ]);
                $sharedQuizId = (int)$pdo->lastInsertId();
                $successCount++;

                $results[] = [
                    'chat_id'        => $chatId,
                    'chat_title'     => $chatTitle,
                    'success'        => true,
                    'shared_quiz_id' => $sharedQuizId,
                    'share_token'    => $shareToken,
                    'message_id'     => $msgId
                ];
            } else {
                $results[] = [
                    'chat_id'    => $chatId,
                    'chat_title' => $chatTitle,
                    'success'    => false,
                    'error'      => $errorDesc
                ];
            }
        }

        if ($successCount > 0) {
            if (function_exists('logActivity')) {
                logActivity("Shared playable quiz '{$quizTitle}' with {$successCount} group(s) [Mode: {$mode}]", 'admin', null, $admin_id);
            }
            return [
                'success'      => true,
                'success_count'=> $successCount,
                'total_groups' => count($chat_ids),
                'results'      => $results,
                'message'      => "Playable quiz shared successfully to {$successCount} Telegram group(s)!"
            ];
        } else {
            return [
                'success' => false,
                'results' => $results,
                'message' => 'Failed to share quiz to the selected group(s).'
            ];
        }
    }

    /**
     * Build formatted invitation text and inline keyboard buttons
     */
    public static function buildInvitationMessage(
        string $quizTitle,
        int $totalQ,
        int $timerSeconds,
        string $mode,
        string $botUsername,
        string $shareToken,
        array $promoSettings
    ): array {
        $titleUpper = escapeTelegramHtml(mb_strtoupper($quizTitle, 'UTF-8'));
        $groupName  = escapeTelegramHtml($promoSettings['group_name'] ?? 'Our Telegram Group');
        $groupLink  = escapeTelegramHtml($promoSettings['group_link'] ?? '');
        $adminUser  = escapeTelegramHtml(ltrim($promoSettings['admin_username'] ?? '', '@'));

        $text = "🎯 <b>{$titleUpper}</b>\n\n"
              . "📚 <b>Total Questions:</b> {$totalQ}\n"
              . "⏱ <b>Timer per Question:</b> {$timerSeconds} Seconds\n\n"
              . "━━━━━━━━━━━━━━━━\n\n";

        if ($mode === 'individual') {
            $text .= "🎮 <b>MODE: INDIVIDUAL QUIZ</b>\n"
                  . "Tap the button below to start your private quiz session.\n"
                  . "Play independently at your own pace!\n\n";
        } elseif ($mode === 'live') {
            $text .= "⚡ <b>MODE: COMMON LIVE GROUP QUIZ</b>\n"
                  . "All group members will compete together with a synchronized timer and live leaderboard!\n\n";
        } else {
            $text .= "🌟 <b>SELECT YOUR PREFERRED QUIZ MODE:</b>\n"
                  . "• <b>Play Individually:</b> Solve questions in your private chat at your own pace.\n"
                  . "• <b>Join Live Group Quiz:</b> Compete live with group members on a common timer!\n\n";
        }

        $text .= "━━━━━━━━━━━━━━━━\n\n";

        if (!empty($groupLink)) {
            $text .= "📢 <b>Main Group:</b> <a href=\"{$groupLink}\">{$groupName}</a>\n";
        }
        if (!empty($adminUser)) {
            $text .= "👤 <b>Quiz Host:</b> <a href=\"https://t.me/{$adminUser}\">@{$adminUser}</a>\n";
        }

        // Build Inline Keyboard
        $buttons = [];
        $deepLink = "https://t.me/{$botUsername}?start=quiz_{$shareToken}";

        if ($mode === 'individual' || $mode === 'both') {
            $buttons[] = [
                ['text' => '👤 Play Individually (Private Chat)', 'url' => $deepLink]
            ];
        }

        if ($mode === 'live' || $mode === 'both') {
            $buttons[] = [
                ['text' => '👥 Start / Join Live Group Quiz', 'callback_data' => "live:start:{$shareToken}"]
            ];
        }

        return [
            'text'         => $text,
            'reply_markup' => ['inline_keyboard' => $buttons]
        ];
    }

    // ============================================================
    // MODE A: INDIVIDUAL QUIZ ENGINE (Private Chat)
    // ============================================================

    /**
     * Start or resume an individual quiz session in user's private chat
     */
    public static function startIndividualSession(
        PDO $pdo,
        string $shareToken,
        int $userId,
        array $user = [],
        int $privateChatId = 0
    ): array {
        $token = getActiveBotToken($pdo);
        if (empty($token)) {
            return ['success' => false, 'message' => 'No active bot token'];
        }

        $shareToken = preg_replace('/^quiz_/i', '', trim($shareToken));
        if ($privateChatId === 0) {
            $privateChatId = $userId;
        }
        if (empty($user)) {
            $user = ['id' => $userId, 'first_name' => 'Friend'];
        }

        // 1. Validate share token
        $stmt = $pdo->prepare("SELECT * FROM tg_shared_quizzes WHERE (share_token = ? OR share_token = ?) AND status = 'ACTIVE' LIMIT 1");
        $stmt->execute([$shareToken, 'quiz_' . $shareToken]);
        $sharedQuiz = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sharedQuiz) {
            callTelegramAPI($token, 'sendMessage', [
                'chat_id' => $privateChatId,
                'text'    => "⚠️ <b>Quiz Not Found or Inactive</b>\n\nThis quiz link has expired or is no longer available.",
                'parse_mode' => 'HTML'
            ]);
            return ['success' => false, 'message' => 'Invalid or inactive share token'];
        }

        if ($sharedQuiz['mode'] === 'live') {
            callTelegramAPI($token, 'sendMessage', [
                'chat_id' => $privateChatId,
                'text'    => "ℹ️ This quiz is configured exclusively for <b>Common Live Group Quiz</b> mode. Please return to the group to play!",
                'parse_mode' => 'HTML'
            ]);
            return ['success' => false, 'message' => 'Quiz configured only for group mode'];
        }

        // 2. Check for existing RUNNING session for this user from this group
        $sessStmt = $pdo->prepare("
            SELECT * FROM tg_individual_quiz_sessions
            WHERE shared_quiz_id = ? AND telegram_user_id = ? AND status = 'RUNNING'
            ORDER BY id DESC LIMIT 1
        ");
        $sessStmt->execute([$sharedQuiz['id'], $userId]);
        $session = $sessStmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            // Fetch questions
            $quizDetails = getQuizDetailsForSharing($pdo, $sharedQuiz['quiz_type'], (int)$sharedQuiz['quiz_id']);
            if (!$quizDetails || empty($quizDetails['questions'])) {
                callTelegramAPI($token, 'sendMessage', [
                    'chat_id' => $privateChatId,
                    'text'    => "⚠️ Quiz questions could not be loaded. Please notify the quiz admin.",
                    'parse_mode' => 'HTML'
                ]);
                return ['success' => false, 'message' => 'No questions found'];
            }

            $questionIds = array_column($quizDetails['questions'], 'id');
            $sessionToken = self::generateToken('ind');

            $ins = $pdo->prepare("
                INSERT INTO tg_individual_quiz_sessions
                    (session_token, shared_quiz_id, quiz_type, quiz_id, admin_id,
                     telegram_user_id, telegram_username, telegram_first_name, telegram_last_name,
                     source_group_id, current_question_index, total_questions, question_ids_json,
                     score, correct_count, wrong_count, unattempted_count, status, started_at)
                VALUES
                    (?, ?, ?, ?, ?,
                     ?, ?, ?, ?,
                     ?, 0, ?, ?,
                     0.00, 0, 0, 0, 'RUNNING', NOW())
            ");
            $ins->execute([
                $sessionToken, $sharedQuiz['id'], $sharedQuiz['quiz_type'], (int)$sharedQuiz['quiz_id'], (int)$sharedQuiz['admin_id'],
                $userId, $user['username'] ?? null, $user['first_name'] ?? null, $user['last_name'] ?? null,
                $sharedQuiz['chat_id'], count($questionIds), json_encode($questionIds)
            ]);
            $sessionId = (int)$pdo->lastInsertId();

            $sessStmt->execute([$sharedQuiz['id'], $userId]);
            $session = $sessStmt->fetch(PDO::FETCH_ASSOC);

            // Send Welcome Banner
            $welcome = "👋 Welcome <b>" . escapeTelegramHtml($user['first_name'] ?? 'Friend') . "</b>!\n\n"
                     . "🎯 <b>" . escapeTelegramHtml(mb_strtoupper($sharedQuiz['quiz_title'], 'UTF-8')) . "</b>\n"
                     . "📚 Total Questions: <b>{$session['total_questions']}</b>\n"
                     . "📝 Source Group: <b>" . escapeTelegramHtml($sharedQuiz['chat_title']) . "</b>\n\n"
                     . "🚀 Starting Question 1 now! Tap an option below to answer:";

            callTelegramAPI($token, 'sendMessage', [
                'chat_id'    => $privateChatId,
                'text'       => $welcome,
                'parse_mode' => 'HTML'
            ]);
        }

        // Send current question
        return self::sendIndividualQuestion($pdo, $session, $privateChatId, $token);
    }

    /**
     * Send current individual question to user's private chat
     */
    public static function sendIndividualQuestion(PDO $pdo, array $session, int $chatId, string $token): array {
        $qIdx   = (int)$session['current_question_index'];
        $totalQ = (int)$session['total_questions'];

        $qIds = json_decode($session['question_ids_json'] ?? '[]', true);
        if (!isset($qIds[$qIdx])) {
            return self::finalizeIndividualSession($pdo, (int)$session['id'], $chatId, $token);
        }

        $questionId = (int)$qIds[$qIdx];
        $qNum = $qIdx + 1;

        // Fetch question
        $qData = self::fetchSingleQuestion($pdo, $session['quiz_type'], $questionId);
        if (!$qData) {
            // Auto skip missing
            $pdo->prepare("UPDATE tg_individual_quiz_sessions SET current_question_index = current_question_index + 1 WHERE id = ?")
                ->execute([(int)$session['id']]);
            $session['current_question_index']++;
            return self::sendIndividualQuestion($pdo, $session, $chatId, $token);
        }

        $qText = "📝 <b>QUESTION {$qNum}/{$totalQ}</b>\n\n"
               . escapeTelegramHtml($qData['question_text']) . "\n\n";

        $buttons = [];
        $row = [];
        foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field) {
            $val = trim((string)($qData[$field] ?? ''));
            if ($val !== '') {
                $qText .= "<b>{$letter}.</b> " . escapeTelegramHtml($val) . "\n";
                $row[] = [
                    'text'          => $letter,
                    'callback_data' => "ind:ans:{$session['session_token']}:{$qIdx}:{$letter}"
                ];
                if (count($row) === 2) {
                    $buttons[] = $row;
                    $row = [];
                }
            }
        }
        if (!empty($row)) $buttons[] = $row;

        $res = callTelegramAPI($token, 'sendMessage', [
            'chat_id'      => $chatId,
            'text'         => $qText,
            'parse_mode'   => 'HTML',
            'reply_markup' => ['inline_keyboard' => $buttons]
        ]);

        if (!empty($res['ok']) && !empty($res['result']['message_id'])) {
            $msgId = (int)$res['result']['message_id'];
            $pdo->prepare("
                UPDATE tg_individual_quiz_sessions
                SET current_message_id = ?, question_started_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ")->execute([$msgId, (int)$session['id']]);
        }

        return [
            'success'         => true,
            'session_id'      => (int)$session['id'],
            'session_token'   => $session['session_token'],
            'question_num'    => $qNum,
            'total_questions' => $totalQ
        ];
    }

    /**
     * Process an answer submitted in Individual Quiz Mode
     */
    public static function handleIndividualAnswer(
        PDO $pdo,
        string $sessionToken,
        int $qIdx,
        string $selectedOption,
        int $userId,
        string $callbackQueryId = ''
    ): array {
        $token = getActiveBotToken($pdo);

        // 1. Fetch Session
        $stmt = $pdo->prepare("SELECT * FROM tg_individual_quiz_sessions WHERE session_token = ? LIMIT 1");
        $stmt->execute([$sessionToken]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            callTelegramAPI($token, 'answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text'              => 'Session not found.',
                'show_alert'        => true
            ]);
            return ['success' => false, 'message' => 'Session not found'];
        }

        // Ownership validation
        if ((int)$session['telegram_user_id'] !== $userId) {
            callTelegramAPI($token, 'answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text'              => '⚠️ Unauthorized: This quiz session belongs to another user.',
                'show_alert'        => true
            ]);
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        if ($session['status'] !== 'RUNNING') {
            callTelegramAPI($token, 'answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text'              => 'This quiz session has already completed.',
                'show_alert'        => true
            ]);
            return ['success' => false, 'message' => 'Session not running'];
        }

        // Check question index alignment
        if ((int)$session['current_question_index'] !== $qIdx) {
            callTelegramAPI($token, 'answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text'              => 'Question already submitted.',
                'show_alert'        => false
            ]);
            return ['success' => false, 'message' => 'Already submitted'];
        }

        // Check for duplicate answer
        $dupCheck = $pdo->prepare("SELECT id FROM tg_individual_quiz_answers WHERE session_id = ? AND question_index = ?");
        $dupCheck->execute([(int)$session['id'], $qIdx]);
        if ($dupCheck->fetchColumn()) {
            callTelegramAPI($token, 'answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text'              => 'Answer already recorded.',
                'show_alert'        => false
            ]);
            return ['success' => false, 'message' => 'Duplicate answer'];
        }

        // Fetch question content
        $qIds = json_decode($session['question_ids_json'] ?? '[]', true);
        $questionId = (int)($qIds[$qIdx] ?? 0);
        $qData = self::fetchSingleQuestion($pdo, $session['quiz_type'], $questionId);

        if (!$qData) {
            return ['success' => false, 'message' => 'Question not found'];
        }

        // Correctness check
        $correctOption = strtoupper(trim((string)$qData['correct_answer']));
        $selectedOption = strtoupper(trim($selectedOption));
        $isCorrect = ($selectedOption === $correctOption) ? 1 : 0;

        // Fetch scoring configuration
        $sharedStmt = $pdo->prepare("SELECT correct_mark, wrong_mark FROM tg_shared_quizzes WHERE id = ?");
        $sharedStmt->execute([(int)$session['shared_quiz_id']]);
        $shared = $sharedStmt->fetch(PDO::FETCH_ASSOC) ?: ['correct_mark' => 1.0, 'wrong_mark' => 0.0];

        $posMark = abs((float)$shared['correct_mark']);
        $negMark = abs((float)$shared['wrong_mark']);
        $marksAwarded = $isCorrect ? $posMark : -$negMark;

        $timeTaken = 0;
        if (!empty($session['question_started_at'])) {
            $timeTaken = max(1, time() - strtotime($session['question_started_at']));
        }

        // Record Answer
        $pdo->beginTransaction();
        try {
            $insAns = $pdo->prepare("
                INSERT INTO tg_individual_quiz_answers
                    (session_id, telegram_user_id, question_index, question_id,
                     selected_option, correct_option, is_correct, marks_awarded, time_taken_seconds, answered_at)
                VALUES
                    (?, ?, ?, ?,
                     ?, ?, ?, ?, ?, NOW())
            ");
            $insAns->execute([
                (int)$session['id'], $userId, $qIdx, $questionId,
                $selectedOption, $correctOption, $isCorrect, $marksAwarded, $timeTaken
            ]);

            $upd = $pdo->prepare("
                UPDATE tg_individual_quiz_sessions
                SET current_question_index = current_question_index + 1,
                    score                  = score + ?,
                    correct_count          = correct_count + ?,
                    wrong_count            = wrong_count + ?,
                    updated_at             = NOW()
                WHERE id = ?
            ");
            $upd->execute([
                $marksAwarded,
                $isCorrect ? 1 : 0,
                $isCorrect ? 0 : 1,
                (int)$session['id']
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'DB error: ' . $e->getMessage()];
        }

        // Acknowledge callback query
        $feedbackMsg = $isCorrect ? "✅ Correct! (+{$posMark})" : "❌ Incorrect (Answer: {$correctOption})";
        callTelegramAPI($token, 'answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text'              => $feedbackMsg,
            'show_alert'        => false
        ]);

        // Re-fetch updated session state
        $stmt->execute([$sessionToken]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        // Advance to next question or finalize
        $chatId = $userId;
        return self::sendIndividualQuestion($pdo, $session, $chatId, $token);
    }

    /**
     * Finalize an Individual Quiz Session and display comprehensive score
     */
    public static function finalizeIndividualSession(PDO $pdo, int $sessionId, int $chatId, string $token): array {
        $stmt = $pdo->prepare("
            SELECT s.*, sq.quiz_title, sq.correct_mark, sq.wrong_mark
            FROM tg_individual_quiz_sessions s
            JOIN tg_shared_quizzes sq ON sq.id = s.shared_quiz_id
            WHERE s.id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) return ['success' => false, 'message' => 'Session not found'];

        $totalQ    = max(1, (int)$session['total_questions']);
        $correct   = (int)$session['correct_count'];
        $wrong     = (int)$session['wrong_count'];
        $attempted = $correct + $wrong;
        $unatt     = max(0, $totalQ - $attempted);
        $finalScore= round((float)$session['score'], 2);
        $accuracy  = $attempted > 0 ? round(($correct / $attempted) * 100, 1) : 0.0;
        $promo     = getTelegramPromoSettings($pdo);

        $pdo->prepare("
            UPDATE tg_individual_quiz_sessions
            SET status = 'COMPLETED', unattempted_count = ?, completed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ")->execute([$unatt, $sessionId]);

        $titleHtml = escapeTelegramHtml(mb_strtoupper($session['quiz_title'], 'UTF-8'));
        $nameHtml  = escapeTelegramHtml($session['telegram_first_name'] ?: 'Participant');

        $resultMsg = "🎉 <b>QUIZ COMPLETED!</b>\n\n"
                   . "Well done, <b>{$nameHtml}</b>!\n\n"
                   . "📚 <b>Quiz:</b> {$titleHtml}\n"
                   . "━━━━━━━━━━━━━━━━\n"
                   . "📝 <b>Total Questions:</b> {$totalQ}\n"
                   . "✍️ <b>Attempted:</b> {$attempted}\n"
                   . "✅ <b>Correct:</b> {$correct}\n"
                   . "❌ <b>Incorrect:</b> {$wrong}\n"
                   . "⏭ <b>Unattempted:</b> {$unatt}\n\n"
                   . "🎯 <b>Final Score:</b> <code>{$finalScore}</code>\n"
                   . "📊 <b>Accuracy:</b> <code>{$accuracy}%</code>\n"
                   . "━━━━━━━━━━━━━━━━\n\n";

        if (!empty($promo['group_link'])) {
            $resultMsg .= "📢 <b>Join Our Group:</b> <a href=\"{$promo['group_link']}\">" . escapeTelegramHtml($promo['group_name']) . "</a>\n";
        }
        if (!empty($promo['admin_username'])) {
            $cleanUser = ltrim($promo['admin_username'], '@');
            $resultMsg .= "👤 <b>Quiz Admin:</b> <a href=\"https://t.me/{$cleanUser}\">@{$cleanUser}</a>\n";
        }
        if (!empty($promo['footer_message'])) {
            $resultMsg .= "\n" . escapeTelegramHtml($promo['footer_message']);
        }

        callTelegramAPI($token, 'sendMessage', [
            'chat_id'                  => $chatId,
            'text'                     => $resultMsg,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true
        ]);

        return [
            'success'     => true,
            'status'      => 'COMPLETED',
            'score'       => $finalScore,
            'accuracy'    => $accuracy,
            'correct'     => $correct,
            'wrong'       => $wrong,
            'attempted'   => $attempted,
            'total_q'     => $totalQ
        ];
    }

    // ============================================================
    // MODE B: COMMON LIVE GROUP QUIZ ENGINE (Group Synchronization)
    // ============================================================

    /**
     * Start a Common Live Group Quiz
     */
    public static function startCommonLiveQuiz(PDO $pdo, int $shared_quiz_id, int $admin_id = 0): array {
        $token = getActiveBotToken($pdo);
        if (empty($token)) {
            return ['success' => false, 'message' => 'No active bot token found'];
        }

        $sharedStmt = $pdo->prepare("SELECT * FROM tg_shared_quizzes WHERE id = ?");
        $sharedStmt->execute([$shared_quiz_id]);
        $shared = $sharedStmt->fetch(PDO::FETCH_ASSOC);

        if (!$shared) {
            return ['success' => false, 'message' => 'Shared quiz not found'];
        }

        if ($admin_id === 0) {
            $admin_id = (int)($shared['admin_id'] ?? 1);
        }

        $chatId = $shared['chat_id'];

        // Concurrency guard: Check if a live session is currently RUNNING or PAUSED in this group
        $activeCheck = $pdo->prepare("
            SELECT id FROM tg_group_quiz_sessions
            WHERE telegram_group_id = ? AND status IN ('RUNNING', 'PAUSED')
            LIMIT 1
        ");
        $activeCheck->execute([$chatId]);
        if ($activeCheck->fetchColumn()) {
            return ['success' => false, 'message' => 'A live quiz session is already active in this group. Please finish or stop it first.'];
        }

        // Fetch questions
        $quizDetails = getQuizDetailsForSharing($pdo, $shared['quiz_type'], (int)$shared['quiz_id']);
        if (!$quizDetails || empty($quizDetails['questions'])) {
            return ['success' => false, 'message' => 'No questions found for this quiz'];
        }

        $questionIds = array_column($quizDetails['questions'], 'id');
        $totalQ = count($questionIds);
        $timer = max(5, (int)$shared['timer_seconds']);

        $ins = $pdo->prepare("
            INSERT INTO tg_group_quiz_sessions
                (shared_quiz_id, quiz_type, quiz_id, quiz_title, admin_id,
                 telegram_group_id, status, current_question_index, total_questions,
                 question_ids_json, timer_seconds, correct_mark, wrong_mark,
                 question_started_at, question_deadline, started_at, created_at)
            VALUES
                (?, ?, ?, ?, ?,
                 ?, 'RUNNING', 0, ?,
                 ?, ?, ?, ?,
                 NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), NOW(), NOW())
        ");
        $ins->execute([
            $shared['id'], $shared['quiz_type'], (int)$shared['quiz_id'], $shared['quiz_title'], $admin_id,
            $chatId, $totalQ, json_encode($questionIds), $timer,
            $shared['correct_mark'], $shared['wrong_mark'], $timer
        ]);
        $groupSessionId = (int)$pdo->lastInsertId();

        // Dispatch Question 1 to Group
        $disp = self::dispatchLiveGroupQuestion($pdo, $groupSessionId, $token);

        if (function_exists('logActivity')) {
            logActivity("Started Common Live Quiz #{$groupSessionId}: '{$shared['quiz_title']}' in chat {$chatId}", 'admin', null, $admin_id);
        }

        return [
            'success'          => true,
            'group_session_id' => $groupSessionId,
            'total_questions'  => $totalQ,
            'timer_seconds'    => $timer,
            'dispatch'         => $disp,
            'message'          => "⚡ Live Group Quiz started! Question 1 posted in group."
        ];
    }

    /**
     * Dispatch the current question to the Telegram Group
     */
    public static function dispatchLiveGroupQuestion(PDO $pdo, int $groupSessionId, string $token): array {
        $stmt = $pdo->prepare("SELECT * FROM tg_group_quiz_sessions WHERE id = ?");
        $stmt->execute([$groupSessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session || $session['status'] !== 'RUNNING') {
            return ['success' => false, 'message' => 'Session not running'];
        }

        $qIdx   = (int)$session['current_question_index'];
        $totalQ = (int)$session['total_questions'];
        $timer  = (int)$session['timer_seconds'];
        $qIds   = json_decode($session['question_ids_json'] ?? '[]', true);

        if ($qIdx >= $totalQ || !isset($qIds[$qIdx])) {
            return self::finalizeLiveGroupQuiz($pdo, $groupSessionId, $token);
        }

        $questionId = (int)$qIds[$qIdx];
        $qNum = $qIdx + 1;
        $qData = self::fetchSingleQuestion($pdo, $session['quiz_type'], $questionId);

        if (!$qData) {
            // Skip missing
            $pdo->prepare("UPDATE tg_group_quiz_sessions SET current_question_index = current_question_index + 1 WHERE id = ?")
                ->execute([$groupSessionId]);
            return self::dispatchLiveGroupQuestion($pdo, $groupSessionId, $token);
        }

        $titleUpper = escapeTelegramHtml(mb_strtoupper($session['quiz_title'], 'UTF-8'));
        $qText = "🎯 <b>{$titleUpper} — LIVE QUIZ</b>\n\n"
               . "📝 <b>QUESTION {$qNum}/{$totalQ}</b>\n"
               . "⏱ <b>Time Allowed:</b> {$timer} Seconds\n\n"
               . escapeTelegramHtml($qData['question_text']) . "\n\n";

        $buttons = [];
        $row = [];
        foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field) {
            $val = trim((string)($qData[$field] ?? ''));
            if ($val !== '') {
                $qText .= "<b>{$letter}.</b> " . escapeTelegramHtml($val) . "\n";
                $row[] = [
                    'text'          => $letter,
                    'callback_data' => "live:ans:{$groupSessionId}:{$qIdx}:{$letter}"
                ];
                if (count($row) === 2) {
                    $buttons[] = $row;
                    $row = [];
                }
            }
        }
        if (!empty($row)) $buttons[] = $row;

        $qText .= "\n👥 <b>Answers Submitted:</b> 0\n"
               . "<i>Tap your answer below! Answers are recorded individually.</i>";

        $res = callTelegramAPI($token, 'sendMessage', [
            'chat_id'      => $session['telegram_group_id'],
            'text'         => $qText,
            'parse_mode'   => 'HTML',
            'reply_markup' => ['inline_keyboard' => $buttons]
        ]);

        if (!empty($res['ok']) && !empty($res['result']['message_id'])) {
            $msgId = (int)$res['result']['message_id'];
            $pdo->prepare("
                UPDATE tg_group_quiz_sessions
                SET current_message_id = ?,
                    question_started_at = NOW(),
                    question_deadline = DATE_ADD(NOW(), INTERVAL ? SECOND),
                    answers_count = 0,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$msgId, $timer, $groupSessionId]);

            return ['success' => true, 'question_num' => $qNum, 'message_id' => $msgId];
        }

        return ['success' => false, 'error' => $res['description'] ?? 'API error'];
    }

    /**
     * Handle button click in Common Live Group Quiz
     */
    public static function handleLiveGroupAnswer(
        PDO $pdo,
        int $groupSessionId,
        int $qIdx,
        string $selectedOption,
        array $user,
        string $callbackQueryId
    ): array {
        $token = getActiveBotToken($pdo);
        $userId = (int)($user['id'] ?? 0);

        // Fetch Session
        $stmt = $pdo->prepare("SELECT * FROM tg_group_quiz_sessions WHERE id = ?");
        $stmt->execute([$groupSessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            callTelegramAPI($token, 'answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text'              => 'Live session not found.',
                'show_alert'        => true
            ]);
            return ['success' => false, 'message' => 'Session not found'];
        }

        if ($session['status'] !== 'RUNNING') {
            callTelegramAPI($token, 'answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text'              => "⚠️ Quiz is currently {$session['status']}.",
                'show_alert'        => true
            ]);
            return ['success' => false, 'message' => 'Not running'];
        }

        if ((int)$session['current_question_index'] !== $qIdx) {
            callTelegramAPI($token, 'answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text'              => '⏱ This question has closed!',
                'show_alert'        => false
            ]);
            return ['success' => false, 'message' => 'Question expired'];
        }

        // Deadline check
        if (!empty($session['question_deadline']) && strtotime($session['question_deadline']) < time()) {
            callTelegramAPI($token, 'answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text'              => '⏱ Time is up for this question!',
                'show_alert'        => false
            ]);
            return ['success' => false, 'message' => 'Time up'];
        }

        // Check if user already answered this question
        $chk = $pdo->prepare("
            SELECT id FROM tg_group_quiz_answers
            WHERE group_session_id = ? AND question_index = ? AND telegram_user_id = ?
        ");
        $chk->execute([$groupSessionId, $qIdx, $userId]);
        if ($chk->fetchColumn()) {
            callTelegramAPI($token, 'answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text'              => '⚠️ You have already submitted your answer for this question!',
                'show_alert'        => false
            ]);
            return ['success' => false, 'message' => 'Already answered'];
        }

        // Fetch question
        $qIds = json_decode($session['question_ids_json'] ?? '[]', true);
        $questionId = (int)($qIds[$qIdx] ?? 0);
        $qData = self::fetchSingleQuestion($pdo, $session['quiz_type'], $questionId);

        $correctOption = strtoupper(trim((string)($qData['correct_answer'] ?? 'A')));
        $selectedOption = strtoupper(trim($selectedOption));
        $isCorrect = ($selectedOption === $correctOption) ? 1 : 0;

        $posMark = abs((float)$session['correct_mark']);
        $negMark = abs((float)$session['wrong_mark']);
        $marksAwarded = $isCorrect ? $posMark : -$negMark;

        $timeTaken = 0;
        if (!empty($session['question_started_at'])) {
            $timeTaken = max(1, time() - strtotime($session['question_started_at']));
        }

        // Record Answer
        try {
            $ins = $pdo->prepare("
                INSERT INTO tg_group_quiz_answers
                    (group_session_id, telegram_group_id, question_index, question_id,
                     telegram_user_id, telegram_username, telegram_first_name, telegram_last_name,
                     selected_option, correct_option, is_correct, marks_awarded, time_taken_seconds, answered_at)
                VALUES
                    (?, ?, ?, ?,
                     ?, ?, ?, ?,
                     ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([
                $groupSessionId, $session['telegram_group_id'], $qIdx, $questionId,
                $userId, $user['username'] ?? null, $user['first_name'] ?? null, $user['last_name'] ?? null,
                $selectedOption, $correctOption, $isCorrect, $marksAwarded, $timeTaken
            ]);

            $pdo->prepare("UPDATE tg_group_quiz_sessions SET answers_count = answers_count + 1 WHERE id = ?")
                ->execute([$groupSessionId]);

        } catch (\Throwable $e) {
            // Ignore duplicate key collision
        }

        // Acknowledge user privately
        callTelegramAPI($token, 'answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text'              => "Option {$selectedOption} recorded! (Answer locked)",
            'show_alert'        => false
        ]);

        return ['success' => true, 'is_correct' => $isCorrect];
    }

    /**
     * Advance Live Group Quiz (advance to next question or finalize)
     * Thread-safe with atomic locking to prevent duplicate advancement
     */
    public static function advanceLiveGroupQuiz(PDO $pdo, int $groupSessionId, bool $force = false): array {
        $token = getActiveBotToken($pdo);
        if (empty($token)) {
            return ['success' => false, 'message' => 'No active bot token'];
        }

        // Atomic turn claim with lock_version increment
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM tg_group_quiz_sessions WHERE id = ? FOR UPDATE");
            $stmt->execute([$groupSessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session || $session['status'] !== 'RUNNING') {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Session not active'];
            }

            // Deadline check unless forced
            if (!$force && !empty($session['question_deadline']) && strtotime($session['question_deadline']) > time()) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Timer not expired yet'];
            }

            $currentQIdx = (int)$session['current_question_index'];
            $totalQ      = (int)$session['total_questions'];
            $msgId       = (int)($session['current_message_id'] ?? 0);
            $chatId      = $session['telegram_group_id'];

            // 1. Close current question message in group (remove buttons)
            if ($msgId > 0) {
                $qIds = json_decode($session['question_ids_json'] ?? '[]', true);
                $qData = isset($qIds[$currentQIdx]) ? self::fetchSingleQuestion($pdo, $session['quiz_type'], (int)$qIds[$currentQIdx]) : null;
                $correctOption = $qData ? strtoupper(trim($qData['correct_answer'])) : '';

                // Fetch submission count
                $subCount = (int)$session['answers_count'];
                $closureText = "⏱ <b>QUESTION " . ($currentQIdx + 1) . "/{$totalQ} CLOSED!</b>\n\n"
                             . "✅ Correct Answer: <b>Option {$correctOption}</b>\n"
                             . "👥 Submissions Received: <b>{$subCount}</b>";

                callTelegramAPI($token, 'editMessageReplyMarkup', [
                    'chat_id'      => $chatId,
                    'message_id'   => $msgId,
                    'reply_markup' => ['inline_keyboard' => []]
                ]);

                callTelegramAPI($token, 'sendMessage', [
                    'chat_id'    => $chatId,
                    'text'       => $closureText,
                    'parse_mode' => 'HTML'
                ]);
            }

            // 2. Increment question index
            $nextQIdx = $currentQIdx + 1;
            $pdo->prepare("
                UPDATE tg_group_quiz_sessions
                SET current_question_index = ?,
                    lock_version = lock_version + 1,
                    answers_count = 0,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$nextQIdx, $groupSessionId]);

            $pdo->commit();

            // 3. Dispatch next question or finalize
            if ($nextQIdx >= $totalQ) {
                return self::finalizeLiveGroupQuiz($pdo, $groupSessionId, $token);
            } else {
                usleep(800000); // 0.8s breather
                return self::dispatchLiveGroupQuestion($pdo, $groupSessionId, $token);
            }

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['success' => false, 'message' => 'Advance error: ' . $e->getMessage()];
        }
    }

    /**
     * Finalize Common Live Group Quiz and post Leaderboard
     */
    public static function finalizeLiveGroupQuiz(PDO $pdo, int $groupSessionId, string $token): array {
        $stmt = $pdo->prepare("SELECT * FROM tg_group_quiz_sessions WHERE id = ?");
        $stmt->execute([$groupSessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) return ['success' => false, 'message' => 'Session not found'];

        $chatId = $session['telegram_group_id'];
        $totalQ = max(1, (int)$session['total_questions']);

        // Mark COMPLETED
        $pdo->prepare("
            UPDATE tg_group_quiz_sessions
            SET status = 'COMPLETED', completed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ")->execute([$groupSessionId]);

        // Calculate Leaderboard:
        // Rank by: Score DESC, Correct Count DESC, Cumulative Time Taken ASC (Tie-breaker), User ID ASC
        $leadStmt = $pdo->prepare("
            SELECT telegram_user_id,
                   COALESCE(MAX(telegram_first_name), MAX(telegram_username), 'User') as display_name,
                   MAX(telegram_username) as username,
                   SUM(marks_awarded) as total_score,
                   SUM(is_correct) as correct_count,
                   COUNT(*) as attempted_count,
                   SUM(time_taken_seconds) as total_time
            FROM tg_group_quiz_answers
            WHERE group_session_id = ?
            GROUP BY telegram_user_id
            ORDER BY total_score DESC, correct_count DESC, total_time ASC, telegram_user_id ASC
            LIMIT 20
        ");
        $leadStmt->execute([$groupSessionId]);
        $leaderboard = $leadStmt->fetchAll(PDO::FETCH_ASSOC);

        $titleHtml = escapeTelegramHtml(mb_strtoupper($session['quiz_title'], 'UTF-8'));
        $promo     = getTelegramPromoSettings($pdo);

        $leadMsg = "🏆 <b>LIVE QUIZ RESULTS &amp; LEADERBOARD</b>\n\n"
                 . "📚 <b>Quiz:</b> {$titleHtml}\n"
                 . "📝 <b>Total Questions:</b> {$totalQ}\n"
                 . "👥 <b>Participants:</b> " . count($leaderboard) . "\n"
                 . "━━━━━━━━━━━━━━━━\n\n";

        if (empty($leaderboard)) {
            $leadMsg .= "No participants submitted answers during this quiz.\n";
        } else {
            $medals = ['🥇', '🥈', '🥉'];
            foreach ($leaderboard as $idx => $row) {
                $rank = $idx + 1;
                $medal = $medals[$idx] ?? "<b>#{$rank}</b>";
                $name = escapeTelegramHtml($row['display_name']);
                if (!empty($row['username'])) {
                    $name .= " (@" . escapeTelegramHtml($row['username']) . ")";
                }
                $score = round((float)$row['total_score'], 2);
                $correct = (int)$row['correct_count'];
                $time = (int)$row['total_time'];

                $leadMsg .= "{$medal} <b>{$name}</b> — <b>{$correct}/{$totalQ}</b> (Score: {$score})\n"
                          . "   ⏱ Speed: {$time}s total\n\n";
            }
        }

        $leadMsg .= "━━━━━━━━━━━━━━━━\n"
                  . "🎉 Congratulations to all participants!\n\n";

        if (!empty($promo['group_link'])) {
            $leadMsg .= "📢 <b>Main Group:</b> <a href=\"{$promo['group_link']}\">" . escapeTelegramHtml($promo['group_name']) . "</a>\n";
        }
        if (!empty($promo['admin_username'])) {
            $cleanUser = ltrim($promo['admin_username'], '@');
            $leadMsg .= "👤 <b>Quiz Host:</b> <a href=\"https://t.me/{$cleanUser}\">@{$cleanUser}</a>\n";
        }

        callTelegramAPI($token, 'sendMessage', [
            'chat_id'                  => $chatId,
            'text'                     => $leadMsg,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true
        ]);

        return ['success' => true, 'status' => 'COMPLETED', 'leaderboard' => $leaderboard];
    }

    /**
     * Pause Common Live Group Quiz
     */
    public static function pauseLiveGroupQuiz(PDO $pdo, int $groupSessionId, int $admin_id = 0): array {
        $token = getActiveBotToken($pdo);
        $stmt = $pdo->prepare("SELECT * FROM tg_group_quiz_sessions WHERE id = ? AND status = 'RUNNING'");
        $stmt->execute([$groupSessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) return ['success' => false, 'message' => 'Active session not found'];

        $rem = 0;
        if (!empty($session['question_deadline'])) {
            $rem = max(0, strtotime($session['question_deadline']) - time());
        }

        $pdo->prepare("
            UPDATE tg_group_quiz_sessions
            SET status = 'PAUSED', paused_at = NOW(), timer_seconds = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$rem, $groupSessionId]);

        if ($token) {
            callTelegramAPI($token, 'sendMessage', [
                'chat_id'    => $session['telegram_group_id'],
                'text'       => "⏸ <b>LIVE QUIZ PAUSED</b>\n\nThe quiz has been paused by the administrator. Timers are temporarily stopped.",
                'parse_mode' => 'HTML'
            ]);
        }

        if (function_exists('logActivity')) {
            logActivity("Paused Common Live Quiz #{$groupSessionId}", 'admin', null, $admin_id);
        }
        return ['success' => true, 'message' => 'Live quiz paused.'];
    }

    /**
     * Resume Common Live Group Quiz
     */
    public static function resumeLiveGroupQuiz(PDO $pdo, int $groupSessionId, int $admin_id = 0): array {
        $token = getActiveBotToken($pdo);
        $stmt = $pdo->prepare("SELECT * FROM tg_group_quiz_sessions WHERE id = ? AND status = 'PAUSED'");
        $stmt->execute([$groupSessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) return ['success' => false, 'message' => 'Paused session not found'];

        $rem = max(5, (int)$session['timer_seconds']);

        $pdo->prepare("
            UPDATE tg_group_quiz_sessions
            SET status = 'RUNNING',
                question_deadline = DATE_ADD(NOW(), INTERVAL ? SECOND),
                paused_at = NULL,
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$rem, $groupSessionId]);

        if ($token) {
            callTelegramAPI($token, 'sendMessage', [
                'chat_id'    => $session['telegram_group_id'],
                'text'       => "▶️ <b>LIVE QUIZ RESUMED!</b>\n\nTimer continuing with <b>{$rem} seconds</b> remaining for the current question.",
                'parse_mode' => 'HTML'
            ]);
        }

        if (function_exists('logActivity')) {
            logActivity("Resumed Common Live Quiz #{$groupSessionId}", 'admin', null, $admin_id);
        }
        return ['success' => true, 'message' => 'Live quiz resumed.'];
    }

    /**
     * Stop Common Live Group Quiz
     */
    public static function stopLiveGroupQuiz(PDO $pdo, int $groupSessionId, int $admin_id = 0): array {
        $token = getActiveBotToken($pdo);
        $stmt = $pdo->prepare("SELECT * FROM tg_group_quiz_sessions WHERE id = ? AND status IN ('RUNNING', 'PAUSED', 'PENDING')");
        $stmt->execute([$groupSessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) return ['success' => false, 'message' => 'Session not found or already stopped'];

        $pdo->prepare("
            UPDATE tg_group_quiz_sessions
            SET status = 'STOPPED', completed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ")->execute([$groupSessionId]);

        if ($token) {
            callTelegramAPI($token, 'sendMessage', [
                'chat_id'    => $session['telegram_group_id'],
                'text'       => "⏹ <b>LIVE QUIZ STOPPED</b>\n\nThis live quiz session has been terminated by the administrator.",
                'parse_mode' => 'HTML'
            ]);
        }

        if (function_exists('logActivity')) {
            logActivity("Stopped Common Live Quiz #{$groupSessionId}", 'admin', null, $admin_id);
        }
        return ['success' => true, 'message' => 'Live quiz stopped.'];
    }

    // ============================================================
    // UNIFIED TELEGRAM UPDATE DISPATCHER
    // (Used by both webhook.php and background polling daemon)
    // ============================================================

    /**
     * Process any incoming Telegram Update (message, callback_query, poll_answer, etc.)
     */
    public static function handleTelegramUpdate(PDO $pdo, array $update, string $token): array {
        // ── 1. Message with deep link (/start quiz_TOKEN) ───────────
        if (isset($update['message'])) {
            $msg     = $update['message'];
            $text    = trim($msg['text'] ?? '');
            $chat    = $msg['chat'] ?? [];
            $chatId  = (int)($chat['id'] ?? 0);
            $type    = $chat['type'] ?? 'private';
            $user    = $msg['from'] ?? [];
            $userId  = (int)($user['id'] ?? 0);

            if (strpos($text, '/start') === 0) {
                $parts = explode(' ', $text);
                $param = trim($parts[1] ?? '');

                if (strpos($param, 'quiz_') === 0 || strpos($param, 'qz_') === 0) {
                    // Deep link to start Individual Quiz
                    if ($type === 'private') {
                        return self::startIndividualSession($pdo, $param, $userId, $user, $chatId);
                    } else {
                        // User clicked in group
                        callTelegramAPI($token, 'sendMessage', [
                            'chat_id' => $chatId,
                            'text'    => "👋 Hi @" . ($user['username'] ?? 'there') . "! To play individually, please start me in a Private Chat.",
                            'parse_mode' => 'HTML'
                        ]);
                        return ['success' => true, 'handled' => 'group_start_notice'];
                    }
                }
            }
        }

        // ── 2. Callback Query (Inline Keyboard Buttons) ──────────────
        elseif (isset($update['callback_query'])) {
            $cb      = $update['callback_query'];
            $cbId    = (string)($cb['id'] ?? '');
            $data    = (string)($cb['data'] ?? '');
            $user    = $cb['from'] ?? [];
            $userId  = (int)($user['id'] ?? 0);
            $msg     = $cb['message'] ?? [];
            $chatId  = (int)($msg['chat']['id'] ?? 0);

            // A. Individual Answer: ind:ans:{session_token}:{q_idx}:{letter}
            if (strpos($data, 'ind:ans:') === 0) {
                $parts = explode(':', $data);
                $sessionToken   = $parts[2] ?? '';
                $qIdx           = (int)($parts[3] ?? 0);
                $selectedOption = $parts[4] ?? '';

                return self::handleIndividualAnswer($pdo, $sessionToken, $qIdx, $selectedOption, $userId, $cbId);
            }

            // B. Live Group Answer: live:ans:{group_session_id}:{q_idx}:{letter}
            elseif (strpos($data, 'live:ans:') === 0) {
                $parts = explode(':', $data);
                $groupSessionId = (int)($parts[2] ?? 0);
                $qIdx           = (int)($parts[3] ?? 0);
                $selectedOption = $parts[4] ?? '';

                return self::handleLiveGroupAnswer($pdo, $groupSessionId, $qIdx, $selectedOption, $user, $cbId);
            }

            // C. Live Group Start via Invitation: live:start:{share_token}
            elseif (strpos($data, 'live:start:') === 0) {
                $shareToken = substr($data, strlen('live:start:'));
                $sharedStmt = $pdo->prepare("SELECT * FROM tg_shared_quizzes WHERE share_token = ? LIMIT 1");
                $sharedStmt->execute([$shareToken]);
                $shared = $sharedStmt->fetch(PDO::FETCH_ASSOC);

                if ($shared) {
                    callTelegramAPI($token, 'answerCallbackQuery', [
                        'callback_query_id' => $cbId,
                        'text'              => "Starting Common Live Quiz...",
                        'show_alert'        => false
                    ]);
                    return self::startCommonLiveQuiz($pdo, (int)$shared['id'], (int)$shared['admin_id']);
                } else {
                    callTelegramAPI($token, 'answerCallbackQuery', [
                        'callback_query_id' => $cbId,
                        'text'              => "Quiz invitation expired.",
                        'show_alert'        => true
                    ]);
                }
            }
        }

        return ['success' => true, 'handled' => 'ignored'];
    }

    /**
     * Helper to fetch a single question regardless of quiz type
     */
    public static function fetchSingleQuestion(PDO $pdo, string $quizType, int $questionId): ?array {
        if ($quizType === 'mt_mock') {
            $stmt = $pdo->prepare("
                SELECT id, question_text, option_a, option_b, option_c, option_d,
                       correct_option as correct_answer, explanation
                FROM mt_questions WHERE id = ?
            ");
            $stmt->execute([$questionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } else {
            $stmt = $pdo->prepare("
                SELECT id, question_number, question_text, option_a, option_b, option_c, option_d,
                       correct_answer, explanation
                FROM tg_quiz_questions WHERE id = ?
            ");
            $stmt->execute([$questionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    /**
     * Comprehensive Reporting & Statistics
     */
    public static function getPlayableReportingStats(PDO $pdo, int $admin_id): array {
        // Shared Quizzes
        $shStmt = $pdo->prepare("SELECT COUNT(*) as total_shared FROM tg_shared_quizzes WHERE admin_id = ?");
        $shStmt->execute([$admin_id]);
        $totalShared = (int)$shStmt->fetchColumn();

        // Authorized Groups
        $grpStmt = $pdo->prepare("SELECT COUNT(*) as total_groups FROM tg_chats WHERE admin_id = ? AND is_verified = 1");
        $grpStmt->execute([$admin_id]);
        $totalGroups = (int)$grpStmt->fetchColumn();

        // Individual Participants
        $indStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT telegram_user_id) as total_users,
                   COUNT(*) as total_sessions,
                   SUM(status = 'RUNNING') as active_sessions,
                   SUM(status = 'COMPLETED') as completed_sessions,
                   COALESCE(AVG(CASE WHEN total_questions > 0 THEN (correct_count / total_questions) * 100 END), 0) as avg_accuracy
            FROM tg_individual_quiz_sessions
            WHERE admin_id = ?
        ");
        $indStmt->execute([$admin_id]);
        $indStats = $indStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Live Group Participants
        $liveStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT a.telegram_user_id) as total_live_users,
                   COUNT(DISTINCT s.id) as total_live_sessions,
                   SUM(s.status = 'RUNNING') as active_live_sessions,
                   SUM(s.status = 'COMPLETED') as completed_live_sessions
            FROM tg_group_quiz_sessions s
            LEFT JOIN tg_group_quiz_answers a ON a.group_session_id = s.id
            WHERE s.admin_id = ?
        ");
        $liveStmt->execute([$admin_id]);
        $liveStats = $liveStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $ansStmt = $pdo->query("SELECT (SELECT COUNT(*) FROM tg_individual_quiz_answers) + (SELECT COUNT(*) FROM tg_group_quiz_answers)");
        $totalAnswers = $ansStmt ? (int)$ansStmt->fetchColumn() : 0;
        $totalIndSess = (int)($indStats['total_sessions'] ?? 0);
        $compIndSess  = (int)($indStats['completed_sessions'] ?? 0);
        $indCompRate  = ($totalIndSess > 0) ? round(($compIndSess / $totalIndSess) * 100, 1) : 0.0;
        $avgAcc       = round((float)($indStats['avg_accuracy'] ?? 0), 1);

        return [
            'total_shared'                  => $totalShared,
            'total_groups'                  => $totalGroups,
            'total_individual_users'        => (int)($indStats['total_users'] ?? 0),
            'total_individual_sessions'     => $totalIndSess,
            'active_individual_sessions'    => (int)($indStats['active_sessions'] ?? 0),
            'completed_individual_sessions' => $compIndSess,
            'avg_individual_accuracy'       => $avgAcc,
            'total_live_users'              => (int)($liveStats['total_live_users'] ?? 0),
            'total_live_sessions'           => (int)($liveStats['total_live_sessions'] ?? 0),
            'active_live_sessions'          => (int)($liveStats['active_live_sessions'] ?? 0),
            'completed_live_sessions'       => (int)($liveStats['completed_live_sessions'] ?? 0),
            'total_answers'                 => $totalAnswers,
            'individual_completion_rate'    => $indCompRate,

            // Aliases for dashboard UI and test suites
            'total_playable_shares'         => $totalShared,
            'authorized_groups_count'       => $totalGroups,
            'individual_participants_count' => (int)($indStats['total_users'] ?? 0),
            'live_participants_count'       => (int)($liveStats['total_live_users'] ?? 0),
            'average_accuracy'              => $avgAcc,
            'total_answers_recorded'        => $totalAnswers,
            'active_group_sessions'         => (int)($liveStats['active_live_sessions'] ?? 0),
        ];
    }
}
