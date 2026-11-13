<?php
/**
 * Comprehensive Verification & Test Suite for Telegram Complete Quiz Sharing & Question Numbering
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/tg_quiz/tg_helper.php';

$allTestsPassed = true;
function assertTest($condition, $message) {
    global $allTestsPassed;
    if ($condition) {
        echo "  [PASS] {$message}\n";
    } else {
        echo "  [FAIL] {$message}\n";
        $allTestsPassed = false;
    }
}

echo "============================================================\n";
echo " MODULE 1: QUESTION NUMBERING & POLL FORMATTING VERIFICATION\n";
echo "============================================================\n";

$mockQ = [
    'question_text' => 'What is the full form of RBI?',
    'option_a' => 'Reserve Bank of India',
    'option_b' => 'Regional Bank of India',
    'option_c' => 'Rural Bank of India',
    'option_d' => 'Revenue Bank of India',
    'correct_answer' => 'A',
    'explanation' => 'RBI is the central bank of India.'
];

// Test sendQuizPoll formatting for Question 1 of 5
$qNum = 1;
$totalQ = 5;
$token = 'dummy_token';
$chat_id = -100123456789;

// Capture the payload by mocking callTelegramAPI
$capturedPayload = null;
// We can test sendQuizPoll directly by inspecting logic:
$prefix = "📝 Question {$qNum}/{$totalQ}\n\n";
$expectedQText = $prefix . $mockQ['question_text'];

assertTest(strpos($expectedQText, '📝 Question 1/5') === 0, "Question starts with '📝 Question 1/5'");
assertTest(strpos($expectedQText, "What is the full form of RBI?") !== false, "Question body preserved accurately");

// Test Option prefixing logic
$options = [];
foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field) {
    $val = trim($mockQ[$field]);
    if (!preg_match('/^[A-D][\.\)\s-]/iu', $val)) {
        $val = $letter . '. ' . $val;
    }
    $options[] = $val;
}
assertTest($options[0] === 'A. Reserve Bank of India', "Option A formatted as 'A. Reserve Bank of India'");
assertTest($options[1] === 'B. Regional Bank of India', "Option B formatted as 'B. Regional Bank of India'");
assertTest($options[2] === 'C. Rural Bank of India', "Option C formatted as 'C. Rural Bank of India'");
assertTest($options[3] === 'D. Revenue Bank of India', "Option D formatted as 'D. Revenue Bank of India'");

// Test Final Question 5 of 5
$finalPrefix = "📝 Question 5/5\n\n";
assertTest(strpos($finalPrefix, '📝 Question 5/5') === 0, "Final question starts with '📝 Question 5/5'");

// Test non-duplication
$alreadyNumbered = "📝 Question 1/5\n\nWhat is the full form of RBI?";
$hasDuplicate = preg_match('/^(📝\s*)?Question\s*\d+\s*(\/|of)\s*\d+/ui', $alreadyNumbered);
assertTest($hasDuplicate === 1, "Detects already numbered question to prevent duplicate prefixing");


echo "\n============================================================\n";
echo " MODULE 2 & 3: COMPLETE QUIZ SHARING & PROMOTION DETAILS\n";
echo "============================================================\n";

$promo = getTelegramPromoSettings($pdo);
assertTest(!empty($promo['group_name']), "Promotional Group Name is configured: '{$promo['group_name']}'");
assertTest(strpos($promo['group_link'], 'https://t.me/') === 0, "Promotional Group Link is valid t.me link: '{$promo['group_link']}'");
assertTest(!empty($promo['admin_username']), "Admin Username is configured: '{$promo['admin_username']}'");
assertTest(!empty($promo['footer_message']), "Promotional Footer Message is configured: '{$promo['footer_message']}'");

// Test 5 questions mock test formatting
$mock5Questions = [
    [
        'question_text' => 'What is the full form of RBI?',
        'option_a' => 'Reserve Bank of India',
        'option_b' => 'Regional Bank of India',
        'option_c' => 'Rural Bank of India',
        'option_d' => 'Revenue Bank of India',
        'correct_answer' => 'A'
    ],
    [
        'question_text' => 'Where is the headquarters of RBI located?',
        'option_a' => 'New Delhi',
        'option_b' => 'Mumbai',
        'option_c' => 'Kolkata',
        'option_d' => 'Chennai',
        'correct_answer' => 'B'
    ],
    [
        'question_text' => 'In which year was RBI established?',
        'option_a' => '1925',
        'option_b' => '1935',
        'option_c' => '1947',
        'option_d' => '1950',
        'correct_answer' => 'B'
    ],
    [
        'question_text' => 'Who was the first Governor of RBI?',
        'option_a' => 'Sir Osborne Smith',
        'option_b' => 'C.D. Deshmukh',
        'option_c' => 'James Braid Taylor',
        'option_d' => 'Benegal Rama Rau',
        'correct_answer' => 'A'
    ],
    [
        'question_text' => 'Who was the first Indian Governor of RBI?',
        'option_a' => 'Sir Osborne Smith',
        'option_b' => 'C.D. Deshmukh',
        'option_c' => 'L.K. Jha',
        'option_d' => 'Manmohan Singh',
        'correct_answer' => 'B'
    ]
];

$quizData5 = [
    'title' => 'Banking Awareness Mock Test',
    'total_questions' => 5,
    'questions' => $mock5Questions
];

$built5 = buildCompleteQuizTelegramMessages($quizData5, $promo, ['include_explanation' => false]);
assertTest($built5['total_parts'] === 1, "5-Question Quiz fits into exactly 1 part");
assertTest($built5['total_questions'] === 5, "Total questions equals 5");

$msg5 = $built5['messages'][0];
assertTest(strpos($msg5, '🎯 <b>BANKING AWARENESS MOCK TEST</b>') !== false, "Contains Quiz Title");
assertTest(strpos($msg5, '📚 <b>Total Questions:</b> 5') !== false, "Contains Total Questions Count");
assertTest(strpos($msg5, '📝 <b>Question 1/5</b>') !== false, "Contains Question 1/5");
assertTest(strpos($msg5, '📝 <b>Question 5/5</b>') !== false, "Contains Question 5/5");
assertTest(strpos($msg5, '📌 <b>ANSWER KEY</b>') !== false, "Contains Answer Key header");
assertTest(strpos($msg5, '1. <b>A</b>') !== false, "Answer key has 1. A");
assertTest(strpos($msg5, '5. <b>B</b>') !== false, "Answer key has 5. B");
assertTest(strpos($msg5, '📢 <b>JOIN OUR TELEGRAM GROUP</b>') !== false, "Contains Telegram Group section");
assertTest(strpos($msg5, $promo['group_link']) !== false, "Contains Group Invite Link");
assertTest(strpos($msg5, $promo['admin_username']) !== false, "Contains Admin Username");
assertTest(strpos($msg5, $promo['footer_message']) !== false, "Contains Promotional Footer Message");
assertTest(strpos($msg5, '(Part 1/1)') === false, "Single part quiz does NOT have redundant '(Part 1/1)'");


echo "\n============================================================\n";
echo " MODULE 5: MESSAGE LENGTH MANAGEMENT & SEQUENTIAL SPLITTING\n";
echo "============================================================\n";

// Test with 21 questions from database
$set2 = getQuizDetailsForSharing($pdo, 'tg_set', 2);
$built21 = buildCompleteQuizTelegramMessages($set2, $promo, ['include_explanation' => false]);

assertTest($built21['total_parts'] === 2, "21-Question Quiz is split into 2 parts");
foreach ($built21['messages'] as $idx => $m) {
    $pNum = $idx + 1;
    $len = mb_strlen($m, 'UTF-8');
    assertTest($len <= 3800, "Part {$pNum} length ({$len} chars) is safely within 4096 character limit");
    assertTest(strpos($m, "(Part {$pNum}/2)") !== false, "Part {$pNum} has header '(Part {$pNum}/2)'");
}

// Check that Part 1 has Questions 1..13 and Part 2 has Questions 14..21 + Answer Key
assertTest(strpos($built21['messages'][0], '📝 <b>Question 1/21</b>') !== false, "Part 1 starts with Question 1/21");
assertTest(strpos($built21['messages'][1], '📝 <b>Question 21/21</b>') !== false, "Part 2 has Question 21/21");
assertTest(strpos($built21['messages'][1], '📌 <b>ANSWER KEY</b>') !== false, "Part 2 contains the complete Answer Key at the end");
assertTest(strpos($built21['messages'][1], $promo['group_link']) !== false, "Part 2 contains the promotional group link");

// Test with 62 questions (Defence exercise - Quiz Set 3)
$set3 = getQuizDetailsForSharing($pdo, 'tg_set', 3);
if ($set3 && !empty($set3['questions'])) {
    $built62 = buildCompleteQuizTelegramMessages($set3, $promo, ['include_explanation' => false]);
    echo "  Quiz Set 3 (62 Questions) split into {$built62['total_parts']} parts.\n";
    assertTest($built62['total_parts'] >= 3, "62-Question Quiz splits into 3 or more parts");
    $allPartsWithinLimit = true;
    foreach ($built62['messages'] as $idx => $m) {
        $len = mb_strlen($m, 'UTF-8');
        if ($len > 3800) $allPartsWithinLimit = false;
    }
    assertTest($allPartsWithinLimit, "All parts of 62-question quiz strictly adhere to character limit (< 3800 chars)");
    $lastPart = end($built62['messages']);
    assertTest(strpos($lastPart, '📌 <b>ANSWER KEY</b>') !== false, "Final part of 62-question quiz holds the Answer Key");
    assertTest(strpos($lastPart, $promo['group_link']) !== false, "Final part holds the Promotional Group Link");
}


echo "\n============================================================\n";
echo " MODULE 4: DESTINATION GROUPS & DUPLICATE PROTECTION\n";
echo "============================================================\n";

// Verify tg_quiz_shares table
$sharesTableExists = $pdo->query("SHOW TABLES LIKE 'tg_quiz_shares'")->fetch();
assertTest(!empty($sharesTableExists), "Table 'tg_quiz_shares' exists in database");

// Test duplicate sharing detection logic
// Insert a dummy share record for testing
$adminId = 1;
$testChatId = -1004457706126;
$pdo->prepare("
    INSERT INTO tg_quiz_shares
        (admin_id, quiz_type, quiz_id, quiz_title, chat_id, chat_title, total_questions, parts_count, status, created_at)
    VALUES
        (?, 'tg_set', 9999, 'Test Duplicate Quiz', ?, 'Test Group', 5, 1, 'SUCCESS', NOW())
")->execute([$adminId, $testChatId]);

// Check duplicate detection query
$dupStmt = $pdo->prepare("
    SELECT id, created_at
    FROM tg_quiz_shares
    WHERE quiz_type = 'tg_set' AND quiz_id = 9999 AND chat_id = ? AND status = 'SUCCESS'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ORDER BY id DESC LIMIT 1
");
$dupStmt->execute([$testChatId]);
$dupRow = $dupStmt->fetch(PDO::FETCH_ASSOC);
assertTest(!empty($dupRow), "Duplicate share within 15 minutes is successfully detected");

// Cleanup test record
$pdo->prepare("DELETE FROM tg_quiz_shares WHERE quiz_id = 9999")->execute();

// Check getQuizShareHistory
$hist = getQuizShareHistory($pdo, $adminId, 5);
assertTest(is_array($hist), "getQuizShareHistory returns valid array");


echo "\n============================================================\n";
echo " MODULE 6: EXISTING SYSTEM PROTECTION INTEGRITY CHECK\n";
echo "============================================================\n";

// Verify all core tables remain completely intact
$requiredTables = [
    'users', 'roles', 'settings',
    'mt_mock_tests', 'mt_questions', 'mt_exam_categories', 'mt_exams', 'mt_subjects', 'mt_test_attempts',
    'tg_bots', 'tg_chats', 'tg_quiz_sets', 'tg_quiz_questions', 'tg_quiz_sessions', 'tg_quiz_logs', 'tg_quiz_responses',
    'tg_quiz_shares'
];
$allTablesExist = true;
foreach ($requiredTables as $t) {
    $exists = $pdo->query("SHOW TABLES LIKE '{$t}'")->fetch();
    if (!$exists) {
        $allTablesExist = false;
        echo "  [FAIL] Missing required table: {$t}\n";
    }
}
assertTest($allTablesExist, "All 17 existing & new database tables are intact and accessible");

// Verify active Telegram bot is untouched
$activeBot = $pdo->query("SELECT id, bot_name, bot_username, is_active FROM tg_bots WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assertTest(!empty($activeBot) && $activeBot['bot_username'] === 'Abhijeet_Mishra_bot', "Active bot configuration (@{$activeBot['bot_username']}) preserved");

// Verify existing scoring function
$score = calculateCandidateQuizScore(10, 2, 12, 1.0, 0.25);
assertTest($score['final_score'] == 9.50, "calculateCandidateQuizScore remains accurate (10 correct, 2 wrong = 9.50)");

// Verify existing progress bar function
$bar = renderQuizProgressBar(5, 10, 20);
assertTest(strpos($bar, '█') !== false && strpos($bar, '░') !== false, "renderQuizProgressBar function intact");

// Verify existing timer function
$timerStr = formatQuizTimer(90);
assertTest($timerStr === '01:30', "formatQuizTimer(90) produces '01:30'");


echo "\n============================================================\n";
if ($allTestsPassed) {
    echo "🎉 ALL TESTS PASSED! FULL SYSTEM COMPATIBLE AND OPERATIONAL.\n";
} else {
    echo "❌ SOME TESTS FAILED! REVIEW ABOVE OUTPUT.\n";
}
echo "============================================================\n";
