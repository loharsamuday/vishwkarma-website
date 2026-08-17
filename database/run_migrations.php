<?php
$host = 'localhost';
$db   = 'Vishwkarma';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $queries = explode(";", file_get_contents(__DIR__ . '/mock_test_schema.sql'));
    foreach ($queries as $index => $q) {
        $q = trim($q);
        if ($q) {
            try {
                $pdo->exec($q);
            } catch (PDOException $e) {
                echo "Error in query index $index: " . $e->getMessage() . "\n";
                echo "Query: $q\n\n";
                exit;
            }
        }
    }
    echo "Migration successful!\n";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
