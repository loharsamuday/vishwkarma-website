<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/test_share_builder.php';

$qs = $pdo->query("SELECT * FROM tg_quiz_questions WHERE quiz_set_id = 2 ORDER BY question_number ASC")->fetchAll(PDO::FETCH_ASSOC);
$sub = array_slice($qs, 0, 5);
$r = buildQuizParts('Banking Awareness Mock Test', $sub, $promo, false);
echo "Parts: " . $r['total_parts'] . "\n\n";
echo $r['messages'][0];
