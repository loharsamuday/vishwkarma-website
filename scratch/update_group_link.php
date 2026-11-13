<?php
require_once __DIR__ . '/../includes/db.php';
global $pdo;

// 1. Update setting
$stmt = $pdo->prepare("UPDATE settings SET setting_value = 'https://t.me/bankersguts' WHERE setting_key = 'tg_promo_group_link'");
$stmt->execute();
echo "Updated tg_promo_group_link to https://t.me/bankersguts. Rows affected: " . $stmt->rowCount() . "\n";

// 2. Clean any HTML entities in tg_shared_quizzes titles
$stmt = $pdo->query("SELECT id, quiz_title FROM tg_shared_quizzes WHERE quiz_title LIKE '%&#%' OR quiz_title LIKE '%&amp;%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($rows) . " shared quizzes with encoded entities in quiz_title:\n";
foreach ($rows as $r) {
    $decoded = htmlspecialchars_decode($r['quiz_title'], ENT_QUOTES | ENT_HTML5);
    echo "ID {$r['id']}: '{$r['quiz_title']}' -> '{$decoded}'\n";
    $up = $pdo->prepare("UPDATE tg_shared_quizzes SET quiz_title = ? WHERE id = ?");
    $up->execute([$decoded, $r['id']]);
}

// 3. Clean any HTML entities in tg_quiz_shares titles
$stmt = $pdo->query("SELECT id, quiz_title FROM tg_quiz_shares WHERE quiz_title LIKE '%&#%' OR quiz_title LIKE '%&amp;%'");
$shares = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($shares) . " shared history records with encoded entities in quiz_title:\n";
foreach ($shares as $s) {
    $decoded = htmlspecialchars_decode($s['quiz_title'], ENT_QUOTES | ENT_HTML5);
    echo "ID {$s['id']}: '{$s['quiz_title']}' -> '{$decoded}'\n";
    $up = $pdo->prepare("UPDATE tg_quiz_shares SET quiz_title = ? WHERE id = ?");
    $up->execute([$decoded, $s['id']]);
}

// 4. Verify setting now
$check = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'tg_promo_group_link'")->fetchColumn();
echo "Verified DB tg_promo_group_link: {$check}\n";
