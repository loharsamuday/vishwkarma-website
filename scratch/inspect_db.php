<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=Vishwkarma;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== MT_QUESTIONS COLUMNS ===\n";
    $cols = $pdo->query("DESCRIBE `mt_questions`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo "  {$c['Field']} ({$c['Type']})\n";

    echo "\n=== MT_MOCK_TESTS COLUMNS ===\n";
    $cols2 = $pdo->query("DESCRIBE `mt_mock_tests`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols2 as $c) echo "  {$c['Field']} ({$c['Type']})\n";

    echo "\n=== MT_TEST_QUESTIONS COLUMNS ===\n";
    $cols3 = $pdo->query("DESCRIBE `mt_test_questions`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols3 as $c) echo "  {$c['Field']} ({$c['Type']})\n";

    $sample = $pdo->query("SELECT * FROM mt_questions LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    echo "\n=== SAMPLE MT_QUESTION ===\n";
    print_r($sample);

} catch (PDOException $e) {
    echo "PDO Error: " . $e->getMessage() . "\n";
}
