<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/tg_quiz_share_migration.sql');
    $pdo->exec($sql);
    echo "Migration tg_quiz_share_migration.sql applied successfully!\n";
    
    // Verify
    $hasTable = $pdo->query("SHOW TABLES LIKE 'tg_quiz_shares'")->fetch();
    echo "Table tg_quiz_shares exists: " . ($hasTable ? "YES" : "NO") . "\n";
    
    $settings = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'tg_promo_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    echo "Promo settings in DB:\n";
    print_r($settings);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
