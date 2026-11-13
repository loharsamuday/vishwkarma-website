<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/tg_playable_quiz_migration.sql');
    $pdo->exec($sql);
    echo "tg_playable_quiz_migration.sql applied successfully!\n";

    // Verify all 5 tables
    $tables = [
        'tg_shared_quizzes',
        'tg_individual_quiz_sessions',
        'tg_individual_quiz_answers',
        'tg_group_quiz_sessions',
        'tg_group_quiz_answers'
    ];

    foreach ($tables as $t) {
        $has = $pdo->query("SHOW TABLES LIKE '{$t}'")->fetch();
        echo "Table {$t}: " . ($has ? "EXISTS" : "NOT FOUND") . "\n";
    }

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
