<?php
require_once __DIR__ . '/../api/tg_quiz/tg_helper.php';

$testStr = "SHEETAL MA&#039;AM JULY 2026 – TOP 150 MCQS <test> & 'quotes'";
echo "Raw: $testStr\n";

function safeTgHtml(?string $text): string {
    if ($text === null || $text === '') return '';
    $clean = function_exists('cleanUtf8String') ? cleanUtf8String($text) : $text;
    $decoded = htmlspecialchars_decode($clean, ENT_QUOTES | ENT_HTML5);
    return htmlspecialchars($decoded, ENT_NOQUOTES, 'UTF-8');
}

echo "Current escape: " . escapeTelegramHtml($testStr) . "\n";
echo "Safe Tg Html:   " . safeTgHtml($testStr) . "\n";
