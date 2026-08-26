<?php
require_once 'config/database.php';
$stmt = $pdo->query("DESCRIBE study_routines");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($columns);
