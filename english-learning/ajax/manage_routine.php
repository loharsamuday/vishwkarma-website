<?php
// ajax/manage_routine.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Study');
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        
        if (empty($title) || empty($start_time) || empty($end_time)) {
            echo json_encode(['status' => 'error', 'message' => 'Title, Start Time, and End Time are required']);
            exit();
        }
        
        $stmt = $pdo->prepare("INSERT INTO student_routines (user_id, title, category, start_time, end_time) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $category, $start_time, $end_time]);
        
        echo json_encode(['status' => 'success', 'message' => 'Routine added successfully']);
        
    } elseif ($action === 'delete') {
        $routine_id = (int)$_POST['routine_id'];
        $stmt = $pdo->prepare("DELETE FROM student_routines WHERE id = ? AND user_id = ?");
        $stmt->execute([$routine_id, $user_id]);
        
        echo json_encode(['status' => 'success']);
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>
