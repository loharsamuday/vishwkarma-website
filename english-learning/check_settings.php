<?php
require_once 'config/database.php';
$stmt = $pdo->query("SHOW TABLES LIKE '%settings%'");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);
