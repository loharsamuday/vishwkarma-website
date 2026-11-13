<?php
function testEscape(?string $text): string {
    if ($text === null || $text === '') return '';
    $clean = $text;
    $decoded = htmlspecialchars_decode($clean, ENT_QUOTES | ENT_HTML5);
    return htmlspecialchars($decoded, ENT_NOQUOTES, 'UTF-8');
}

$cases = [
    "Sheetal Ma'am July 2026 – Top 150 MCQs",
    "SHEETAL MA&#039;AM JULY 2026 – TOP 150 MCQS",
    "What is RBI's primary function?",
    "Growth is < 5% & > 2%",
    "\"Quoted string\" and 'single quoted'",
    "हिंदी: क्या यह सही है? (बैंकर्स)"
];

foreach ($cases as $c) {
    echo "IN:  $c\nOUT: " . testEscape($c) . "\n\n";
}
