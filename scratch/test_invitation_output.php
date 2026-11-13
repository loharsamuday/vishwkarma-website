<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/tg_quiz/tg_helper.php';
require_once __DIR__ . '/../api/tg_quiz/tg_playable_helper.php';

global $pdo;

$promo = getTelegramPromoSettings($pdo);
echo "Loaded promo settings:\n";
print_r($promo);

$invitation = TelegramPlayableQuizEngine::buildInvitationMessage(
    "Sheetal Ma'am July 2026 – Top 150 MCQs",
    79,
    20,
    'both',
    'Abhijeet_Mishra_bot',
    'demo_token_123',
    $promo
);

echo "\n--- GENERATED INVITATION MESSAGE ---\n";
echo $invitation['text'] . "\n";
echo "--- BUTTONS ---\n";
print_r($invitation['reply_markup']);
