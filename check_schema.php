<?php
require_once 'config/config.php';
require_once 'includes/db.php';
$stmt = $pdo->query("DESCRIBE users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query("DESCRIBE member_profiles");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
?>
