<?php
// admin/delete-story.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM stories WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: stories.php?msg=deleted");
exit();
?>
