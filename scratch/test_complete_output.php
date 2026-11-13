<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/tg_quiz/tg_helper.php';

global $pdo;

$quizData = getQuizDetailsForSharing($pdo, 'tg_set', 9);
$promo = getTelegramPromoSettings($pdo);

$res = buildCompleteQuizTelegramMessages($quizData, $promo);
$finalMsg = end($res['messages']);
echo "End of Final Part:\n" . substr($finalMsg, -500) . "\n";
