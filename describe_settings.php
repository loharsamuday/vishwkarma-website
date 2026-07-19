<?php
require 'includes/db.php';
$stmt = $pdo->query('DESCRIBE settings');
print_r($stmt->fetchAll());
?>
