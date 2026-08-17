<?php
// admin/delete-vocabulary.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM vocabulary WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: vocabulary.php?msg=deleted");
exit();
?>
