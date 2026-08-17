<?php
// ajax/manage_timer.php
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
    if ($action === 'save_session') {
        $duration = (int)($_POST['duration_minutes'] ?? 0);
        $task_id = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;
        
        if ($duration <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid duration']);
            exit();
        }
        
        $stmt = $pdo->prepare("INSERT INTO student_focus_sessions (user_id, task_id, duration_minutes) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $task_id, $duration]);
        
        echo json_encode(['status' => 'success', 'message' => 'Study session saved successfully!']);
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>
