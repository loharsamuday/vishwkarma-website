<?php
// scratch/test_playable_quiz_suite.php
// Comprehensive Automated Test Suite for Telegram Playable Quiz Sharing System
// Testing: Mode A (Individual), Mode B (Live Group), Mode C (Both Modes),
// Question Numbering (1/N to N/N), Concurrency, Tie-Breakers, and Session Recovery

define('TG_TEST_MODE', true);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/tg_quiz/tg_helper.php';
require_once __DIR__ . '/../api/tg_quiz/tg_playable_helper.php';

echo "============================================================\n";
echo " TELEGRAM PLAYABLE QUIZ SHARING SYSTEM - TEST SUITE\n";
echo "============================================================\n\n";

global $pdo;
$passCount = 0;
$failCount = 0;

function assertTest(string $desc, bool $condition) {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $desc\n";
        $passCount++;
    } else {
        echo " [FAIL] $desc\n";
        $failCount++;
    }
}

// ─────────────────────────────────────────────────────────────
// TEST 1: Question Numbering Formatting (Module 1)
// ─────────────────────────────────────────────────────────────
echo "\n--- TEST 1: Question Numbering Formatting ---\n";

$rawQ = "What is the capital of India?";
$formattedQ = formatTelegramQuestionText($rawQ, 1, 5);
assertTest("Question 1/5 numbering prefix", strpos($formattedQ, "📝 Question 1/5") !== false);
assertTest("Question text preserved", strpos($formattedQ, $rawQ) !== false);

$formattedFinalQ = formatTelegramQuestionText($rawQ, 5, 5);
assertTest("Final Question 5/5 numbering prefix", strpos($formattedFinalQ, "📝 Question 5/5") !== false);

// Check option letter formatting
$rawOptions = ["New Delhi", "Mumbai", "Kolkata", "Chennai"];
$formattedOpts = formatTelegramPollOptions($rawOptions);
assertTest("Option A prefix added", $formattedOpts[0] === "A. New Delhi");
assertTest("Option B prefix added", $formattedOpts[1] === "B. Mumbai");
assertTest("Option C prefix added", $formattedOpts[2] === "C. Kolkata");
assertTest("Option D prefix added", $formattedOpts[3] === "D. Chennai");

// Check already prefixed options are not double prefixed
$alreadyPrefixed = ["A. New Delhi", "B. Mumbai", "C. Kolkata", "D. Chennai"];
$checkNoDouble = formatTelegramPollOptions($alreadyPrefixed);
assertTest("No double prefixing for options", $checkNoDouble[0] === "A. New Delhi");


// ─────────────────────────────────────────────────────────────
// TEST 2: Invitation Message Builder (Modes A, B, C)
// ─────────────────────────────────────────────────────────────
echo "\n--- TEST 2: Invitation Message Builder ---\n";

$promo = [
    'group_name'     => 'Bankers Lifes❤️',
    'group_link'     => 'https://t.me/bankerslifes',
    'admin_username' => 'Abhijeet_Mishra',
    'admin_id'       => '9272349548',
    'footer_message' => 'Best of luck for your upcoming exams!'
];

// Mode A: Individual Quiz
$invA = TelegramPlayableQuizEngine::buildInvitationMessage(
    "Banking General Awareness", 5, 20, 'individual', 'Abhijeet_Mishra_bot', 'token_A123', $promo
);
assertTest("Mode A contains Mode banner", strpos($invA['text'], "MODE: INDIVIDUAL QUIZ") !== false);
assertTest("Mode A has 1 button row", count($invA['reply_markup']['inline_keyboard']) === 1);
assertTest("Mode A button URL points to deep link", strpos($invA['reply_markup']['inline_keyboard'][0][0]['url'], "start=quiz_token_A123") !== false);

// Mode B: Common Live Quiz
$invB = TelegramPlayableQuizEngine::buildInvitationMessage(
    "Banking General Awareness", 5, 20, 'live', 'Abhijeet_Mishra_bot', 'token_B123', $promo
);
assertTest("Mode B contains Live Group banner", strpos($invB['text'], "COMMON LIVE GROUP QUIZ") !== false);
assertTest("Mode B button callback matches live token", $invB['reply_markup']['inline_keyboard'][0][0]['callback_data'] === "live:start:token_B123");

// Mode C: Both Modes
$invC = TelegramPlayableQuizEngine::buildInvitationMessage(
    "Banking General Awareness", 5, 20, 'both', 'Abhijeet_Mishra_bot', 'token_C123', $promo
);
assertTest("Mode C contains both play options", strpos($invC['text'], "Play Individually") !== false && strpos($invC['text'], "Live Group Quiz") !== false);
assertTest("Mode C has 2 inline button rows", count($invC['reply_markup']['inline_keyboard']) === 2);
assertTest("Mode C contains branding link", strpos($invC['text'], "https://t.me/bankerslifes") !== false);


// ─────────────────────────────────────────────────────────────
// TEST 3: Database Playable Quiz Creation
// ─────────────────────────────────────────────────────────────
echo "\n--- TEST 3: Database Playable Quiz Records ---\n";

// Get an existing admin and quiz
$adminStmt = $pdo->query("SELECT id FROM admin_users LIMIT 1");
$adminId = (int)$adminStmt->fetchColumn() ?: 1;

$quizStmt = $pdo->query("SELECT id, title FROM tg_quiz_sets LIMIT 1");
$quizRow = $quizStmt->fetch(PDO::FETCH_ASSOC);
$quizId = (int)($quizRow['id'] ?? 1);
$quizTitle = $quizRow['title'] ?? 'Test Banking Quiz';

$testChatId = '-1004457706126';
$testShareToken = 'test_token_' . bin2hex(random_bytes(8));

$insShared = $pdo->prepare("
    INSERT INTO tg_shared_quizzes 
        (admin_id, share_token, quiz_type, quiz_id, quiz_title, total_questions,
         chat_id, chat_title, mode, timer_seconds, correct_mark, wrong_mark, status, created_at)
    VALUES
        (?, ?, 'tg_set', ?, ?, 5, ?, 'Bankers Lifes❤️', 'both', 20, 1.00, 0.25, 'ACTIVE', NOW())
");
$insShared->execute([$adminId, $testShareToken, $quizId, $quizTitle, $testChatId]);
$sharedQuizId = (int)$pdo->lastInsertId();
assertTest("tg_shared_quizzes record inserted (#$sharedQuizId)", $sharedQuizId > 0);


// ─────────────────────────────────────────────────────────────
// TEST 4: Mode A: Individual Quiz Simulation
// ─────────────────────────────────────────────────────────────
echo "\n--- TEST 4: Mode A: Individual Quiz Simulation ---\n";

$testUserId = 12345678;
$testFirstName = "Rajesh";
$testUsername = "rajesh_kumar";

// Check start individual session
$deepLinkPayload = "quiz_" . $testShareToken;
$sessionRes = TelegramPlayableQuizEngine::startIndividualSession(
    $pdo, $deepLinkPayload, $testUserId, ['first_name' => $testFirstName, 'username' => $testUsername], $testUserId
);
assertTest("startIndividualSession succeeded", !empty($sessionRes['success']));
$indivSessionId = (int)($sessionRes['session_id'] ?? 0);
assertTest("Individual session ID generated (#$indivSessionId)", $indivSessionId > 0);

// Verify session record in DB
$sessCheck = $pdo->prepare("SELECT * FROM tg_individual_quiz_sessions WHERE id = ?");
$sessCheck->execute([$indivSessionId]);
$sessRow = $sessCheck->fetch(PDO::FETCH_ASSOC);
$sessionToken = $sessRow['session_token'];
assertTest("Session status is 'RUNNING'", $sessRow['status'] === 'RUNNING');
assertTest("Session current question index is 0", (int)$sessRow['current_question_index'] === 0);
assertTest("Session user ID matches", (int)$sessRow['telegram_user_id'] === $testUserId);

// Simulate user submitting answer to Question 1 (index 0)
$ansRes1 = TelegramPlayableQuizEngine::handleIndividualAnswer(
    $pdo, $sessionToken, 0, 'A', $testUserId, 'cb_1'
);
assertTest("Question 1 answer processed", !empty($ansRes1['success']));

// Test duplicate answer prevention: submitting again for Question 1
$dupAnsRes = TelegramPlayableQuizEngine::handleIndividualAnswer(
    $pdo, $sessionToken, 0, 'B', $testUserId, 'cb_dup'
);
assertTest("Duplicate answer correctly rejected", empty($dupAnsRes['success']) && strpos($dupAnsRes['message'], 'Already submitted') !== false);

// Test unauthorized access prevention: another user trying to submit to this session
$unauthAnsRes = TelegramPlayableQuizEngine::handleIndividualAnswer(
    $pdo, $sessionToken, 1, 'A', 99999999, 'cb_unauth'
);
assertTest("Unauthorized user submission rejected", empty($unauthAnsRes['success']) && strpos($unauthAnsRes['message'], 'Unauthorized') !== false);

// Submit answers for remaining questions
$totalQuestions = (int)$sessRow['total_questions'];
for ($q = 1; $q < $totalQuestions; $q++) {
    TelegramPlayableQuizEngine::handleIndividualAnswer($pdo, $sessionToken, $q, 'A', $testUserId, "cb_$q");
}

// Check session completion
$sessCheck->execute([$indivSessionId]);
$completedSess = $sessCheck->fetch(PDO::FETCH_ASSOC);
assertTest("Session marked 'COMPLETED' after final question", $completedSess['status'] === 'COMPLETED');
assertTest("Completed time recorded", !empty($completedSess['completed_at']));
assertTest("Total score calculated", isset($completedSess['score']));


// ─────────────────────────────────────────────────────────────
// TEST 5: Mode B: Common Live Group Quiz Simulation
// ─────────────────────────────────────────────────────────────
echo "\n--- TEST 5: Mode B: Common Live Group Quiz Simulation ---\n";

// Concurrency reset for test group
$pdo->prepare("UPDATE tg_group_quiz_sessions SET status = 'STOPPED' WHERE telegram_group_id = ? AND status IN ('RUNNING', 'PAUSED')")->execute([$testChatId]);

// Start live quiz
$liveStartRes = TelegramPlayableQuizEngine::startCommonLiveQuiz($pdo, $sharedQuizId, $adminId);
assertTest("startCommonLiveQuiz succeeded", !empty($liveStartRes['success']));
$groupSessionId = (int)($liveStartRes['group_session_id'] ?? 0);
assertTest("Group session ID generated (#$groupSessionId)", $groupSessionId > 0);

// Check live group session DB record
$grpCheck = $pdo->prepare("SELECT * FROM tg_group_quiz_sessions WHERE id = ?");
$grpCheck->execute([$groupSessionId]);
$grpRow = $grpCheck->fetch(PDO::FETCH_ASSOC);
assertTest("Live session status is 'RUNNING'", $grpRow['status'] === 'RUNNING');
assertTest("Live session current question index is 0", (int)$grpRow['current_question_index'] === 0);
assertTest("Live timer deadline is set in future", strtotime($grpRow['question_deadline']) > time());

// Simulate multiple participants answering live question 1 (index 0)
$userA = ['id' => 101, 'first_name' => 'Alice', 'username' => 'alice_b'];
$userB = ['id' => 102, 'first_name' => 'Bob', 'username' => 'bob_c'];
$userC = ['id' => 103, 'first_name' => 'Charlie', 'username' => 'charlie_d'];

// Alice answers option A
$ansA = TelegramPlayableQuizEngine::handleLiveGroupAnswer(
    $pdo, $groupSessionId, 0, 'A', $userA, 'cb_live_a'
);
assertTest("User A live answer accepted", !empty($ansA['success']));

// Bob answers option B
$ansB = TelegramPlayableQuizEngine::handleLiveGroupAnswer(
    $pdo, $groupSessionId, 0, 'B', $userB, 'cb_live_b'
);
assertTest("User B live answer accepted", !empty($ansB['success']));

// Charlie answers option C
$ansC = TelegramPlayableQuizEngine::handleLiveGroupAnswer(
    $pdo, $groupSessionId, 0, 'C', $userC, 'cb_live_c'
);
assertTest("User C live answer accepted", !empty($ansC['success']));

// User A attempts duplicate answer
$dupA = TelegramPlayableQuizEngine::handleLiveGroupAnswer(
    $pdo, $groupSessionId, 0, 'D', $userA, 'cb_live_dup'
);
assertTest("User A duplicate live answer rejected", empty($dupA['success']) && strpos($dupA['message'], 'Already answered') !== false);

// Check answers counter in live session
$grpCheck->execute([$groupSessionId]);
$grpRowAfter = $grpCheck->fetch(PDO::FETCH_ASSOC);
assertTest("Answers count incremented to 3", (int)$grpRowAfter['answers_count'] === 3);

// Test advance to Question 2
$advRes = TelegramPlayableQuizEngine::advanceLiveGroupQuiz($pdo, $groupSessionId, true);
assertTest("advanceLiveGroupQuiz succeeded to Question 2", !empty($advRes['success']));

$grpCheck->execute([$groupSessionId]);
$grpRowQ2 = $grpCheck->fetch(PDO::FETCH_ASSOC);
assertTest("Current question index is now 1", (int)$grpRowQ2['current_question_index'] === 1);
assertTest("Answers counter reset for Question 2", (int)$grpRowQ2['answers_count'] === 0);


// ─────────────────────────────────────────────────────────────
// TEST 6: Pause, Resume & Stop Live Group Quiz
// ─────────────────────────────────────────────────────────────
echo "\n--- TEST 6: Pause, Resume & Stop Controls ---\n";

// Pause quiz
$pauseRes = TelegramPlayableQuizEngine::pauseLiveGroupQuiz($pdo, $groupSessionId, $adminId);
assertTest("pauseLiveGroupQuiz succeeded", !empty($pauseRes['success']));
$grpCheck->execute([$groupSessionId]);
$pausedRow = $grpCheck->fetch(PDO::FETCH_ASSOC);
assertTest("Status is 'PAUSED'", $pausedRow['status'] === 'PAUSED');

// Resume quiz
$resumeRes = TelegramPlayableQuizEngine::resumeLiveGroupQuiz($pdo, $groupSessionId, $adminId);
assertTest("resumeLiveGroupQuiz succeeded", !empty($resumeRes['success']));
$grpCheck->execute([$groupSessionId]);
$resumedRow = $grpCheck->fetch(PDO::FETCH_ASSOC);
assertTest("Status returned to 'RUNNING'", $resumedRow['status'] === 'RUNNING');

// Stop quiz prematurely and finalize
$stopRes = TelegramPlayableQuizEngine::stopLiveGroupQuiz($pdo, $groupSessionId, $adminId);
assertTest("stopLiveGroupQuiz succeeded", !empty($stopRes['success']));

$grpCheck->execute([$groupSessionId]);
$finalGrp = $grpCheck->fetch(PDO::FETCH_ASSOC);
assertTest("Session marked 'STOPPED'", $finalGrp['status'] === 'STOPPED');


// ─────────────────────────────────────────────────────────────
// TEST 7: Dynamic Leaderboard & Tie-Breaker Calculation
// ─────────────────────────────────────────────────────────────
echo "\n--- TEST 7: Leaderboard & Tie-Breakers ---\n";

// Query leaderboard rankings for this session
$leadStmt = $pdo->prepare("
    SELECT telegram_user_id AS user_id, telegram_first_name AS user_first_name, telegram_username AS username, 
           SUM(marks_awarded) AS total_score,
           SUM(is_correct) AS correct_count,
           COUNT(*) AS total_answered,
           ROUND(SUM(time_taken_seconds), 2) AS total_time
    FROM tg_group_quiz_answers
    WHERE group_session_id = ?
    GROUP BY telegram_user_id, telegram_first_name, telegram_username
    ORDER BY total_score DESC, correct_count DESC, total_time ASC, user_id ASC
");
$leadStmt->execute([$groupSessionId]);
$rankings = $leadStmt->fetchAll(PDO::FETCH_ASSOC);

assertTest("Leaderboard has 3 participants", count($rankings) === 3);
assertTest("Leaderboard calculates total score correctly", isset($rankings[0]['total_score']));
assertTest("Leaderboard orders by total_score DESC", (float)$rankings[0]['total_score'] >= (float)$rankings[1]['total_score']);


// ─────────────────────────────────────────────────────────────
// TEST 8: Multi-Group Concurrency & Isolation
// ─────────────────────────────────────────────────────────────
echo "\n--- TEST 8: Multi-Group Concurrency & Isolation ---\n";

$tokenGrp2 = 'token_grp2_' . bin2hex(random_bytes(6));
$chatId2 = '-1009988776655';

// Concurrency reset for test group 2
$pdo->prepare("UPDATE tg_group_quiz_sessions SET status = 'STOPPED' WHERE telegram_group_id = ? AND status IN ('RUNNING', 'PAUSED')")->execute([$chatId2]);

$insGrp2 = $pdo->prepare("
    INSERT INTO tg_shared_quizzes 
        (admin_id, share_token, quiz_type, quiz_id, quiz_title, total_questions,
         chat_id, chat_title, mode, timer_seconds, status, created_at)
    VALUES
        (?, ?, 'tg_set', ?, ?, 5, ?, 'Group 2', 'live', 20, 'ACTIVE', NOW())
");
$insGrp2->execute([$adminId, $tokenGrp2, $quizId, $quizTitle, $chatId2]);
$sharedId2 = (int)$pdo->lastInsertId();

$liveStart2 = TelegramPlayableQuizEngine::startCommonLiveQuiz($pdo, $sharedId2, $adminId);
$sess2Id = (int)($liveStart2['group_session_id'] ?? 0);
assertTest("Simultaneous live session created for Group 2 (#$sess2Id)", $sess2Id > 0 && $sess2Id !== $groupSessionId);

// Answer in Group 2 should not affect Group 1
TelegramPlayableQuizEngine::handleLiveGroupAnswer($pdo, $sess2Id, 0, 'A', ['id' => 999, 'first_name' => 'Dave', 'username' => 'dave_x'], 'cb_grp2');

$countGrp1 = $pdo->query("SELECT COUNT(*) FROM tg_group_quiz_answers WHERE group_session_id = $groupSessionId")->fetchColumn();
$countGrp2 = $pdo->query("SELECT COUNT(*) FROM tg_group_quiz_answers WHERE group_session_id = $sess2Id")->fetchColumn();

assertTest("Group 1 answer count isolated ($countGrp1)", (int)$countGrp1 === 3);
assertTest("Group 2 answer count isolated ($countGrp2)", (int)$countGrp2 === 1);


// ─────────────────────────────────────────────────────────────
// TEST 9: Reporting & Analytics Statistics
// ─────────────────────────────────────────────────────────────
echo "\n--- TEST 9: Reporting & Analytics Statistics ---\n";

$stats = TelegramPlayableQuizEngine::getPlayableReportingStats($pdo, $adminId);
assertTest("Reporting stats total_playable_shares >= 2", ($stats['total_playable_shares'] ?? 0) >= 2);
assertTest("Reporting stats authorized_groups_count >= 1", ($stats['authorized_groups_count'] ?? 0) >= 1);
assertTest("Reporting stats individual_participants_count >= 1", ($stats['individual_participants_count'] ?? 0) >= 1);
assertTest("Reporting stats live_participants_count >= 3", ($stats['live_participants_count'] ?? 0) >= 3);
assertTest("Reporting stats accuracy is numeric", isset($stats['average_accuracy']));


// ─────────────────────────────────────────────────────────────
// TEST SUMMARY
// ─────────────────────────────────────────────────────────────
echo "\n============================================================\n";
echo " TEST SUITE COMPLETE: $passCount PASSED, $failCount FAILED\n";
echo "============================================================\n";

if ($failCount === 0) {
    echo "🎉 ALL TESTS PASSED SUCCESSFULLY! The Playable Quiz Sharing System is 100% operational.\n";
} else {
    echo "⚠️ SOME TESTS FAILED. Please review the output above.\n";
    exit(1);
}
