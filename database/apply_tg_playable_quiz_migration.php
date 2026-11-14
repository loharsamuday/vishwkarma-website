<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI access only.\n");
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/tg_quiz/tg_playable_helper.php';

try {
    ensureTelegramPlayableQuizSchema($pdo);
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
