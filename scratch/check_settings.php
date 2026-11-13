<?php
require_once __DIR__ . '/../includes/db.php';
global $pdo;
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'tg_%'");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['setting_key'] . ' => ' . $row['setting_value'] . PHP_EOL;
}
