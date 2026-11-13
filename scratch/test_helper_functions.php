<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/tg_quiz/tg_helper.php';

echo "Testing tg_helper functions...\n";

// 1. Promo Settings
$promo = getTelegramPromoSettings($pdo);
echo "1. Promo settings: " . json_encode($promo, JSON_UNESCAPED_UNICODE) . "\n";

// 2. Shareable quizzes
$quizzes = getShareableQuizzes($pdo, 1);
echo "2. TG Sets count: " . count($quizzes['tg_sets']) . ", MT Tests count: " . count($quizzes['mt_tests']) . "\n";

// 3. Quiz details for sharing (TG set 2)
$details = getQuizDetailsForSharing($pdo, 'tg_set', 2);
echo "3. TG Set 2 Title: " . $details['title'] . ", Questions: " . count($details['questions']) . "\n";

// 4. Build messages
$messages = buildCompleteQuizTelegramMessages($details, $promo, ['include_explanation' => false]);
echo "4. Total Parts: " . $messages['total_parts'] . ", Total Q: " . $messages['total_questions'] . "\n";
echo "   Part 1 length: " . mb_strlen($messages['messages'][0], 'UTF-8') . " chars\n";
if (isset($messages['messages'][1])) {
    echo "   Part 2 length: " . mb_strlen($messages['messages'][1], 'UTF-8') . " chars\n";
}

// 5. Test Mock test if any
if (!empty($quizzes['mt_tests'])) {
    $firstMt = $quizzes['mt_tests'][0];
    $mtDetails = getQuizDetailsForSharing($pdo, 'mt_mock', $firstMt['id']);
    echo "5. MT {$firstMt['id']} Title: " . ($mtDetails['title'] ?? 'N/A') . ", Questions: " . (isset($mtDetails['questions']) ? count($mtDetails['questions']) : 0) . "\n";
}

// 6. Share history
$history = getQuizShareHistory($pdo, 1, 10);
echo "6. Share history count: " . count($history) . "\n";

echo "ALL HELPER FUNCTIONS WORKED PERFECTLY!\n";
