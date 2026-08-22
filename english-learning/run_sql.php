<?php
require_once 'config/database.php';
$sql = file_get_contents('study_management.sql');
try {
    $pdo->exec($sql);
    echo "Success";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
