<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$mem_id = (int)($_POST['mem_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($action === 'remove' && $mem_id) {
    $stmt = $pdo->prepare("DELETE FROM student_memory WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$mem_id, $user_id])) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
