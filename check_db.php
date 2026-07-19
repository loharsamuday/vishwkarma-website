<?php
require_once 'config/config.php';
require_once 'includes/db.php';
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
