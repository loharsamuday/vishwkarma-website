<?php
require_once __DIR__ . '/../api/tg_quiz/tg_helper.php';

$testQ = [
    'question_text' => 'What is the full form of RBI?',
    'option_a' => 'Reserve Bank of India',
    'option_b' => 'Regional Bank of India',
    'option_c' => 'Rural Bank of India',
    'option_d' => 'Revenue Bank of India',
    'correct_answer' => 'A',
    'explanation' => 'RBI was established in 1935.'
];

echo "Original question: " . $testQ['question_text'] . "\n";
// Let's test how it formats
$qNum = 1;
$totalQ = 5;
$header = "📝 Question {$qNum}/{$totalQ}\n\n";
$questionText = $header . $testQ['question_text'];
echo "Formatted question:\n$questionText\n\nOptions:\n";
foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field) {
    echo $letter . ". " . $testQ[$field] . "\n";
}
