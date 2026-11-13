<?php
require_once __DIR__ . '/../includes/db.php';
global $pdo;

$stmt = $pdo->query("SELECT id, title FROM mt_mock_tests WHERE title LIKE '%SHEETAL%'");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "mt_mock_tests #{$r['id']}: " . $r['title'] . "\n";
}

$stmt = $pdo->query("SELECT id, title FROM tg_quiz_sets WHERE title LIKE '%SHEETAL%'");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "tg_quiz_sets #{$r['id']}: " . $r['title'] . "\n";
}

$stmt = $pdo->query("SELECT id, quiz_title FROM tg_shared_quizzes");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "tg_shared_quizzes #{$r['id']}: " . $r['quiz_title'] . "\n";
}
