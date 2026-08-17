<?php
// ajax/manage_task.php
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
        $subject = trim($_POST['subject'] ?? '');
        $priority = $_POST['priority'] ?? 'Medium';
        $category = $_POST['category'] ?? 'Study';
        $estimated_minutes = (int)($_POST['estimated_minutes'] ?? 30);
        $goal_id = !empty($_POST['goal_id']) ? (int)$_POST['goal_id'] : null;
        
        if (empty($title)) {
            echo json_encode(['status' => 'error', 'message' => 'Task title is required']);
            exit();
        }
        
        $stmt = $pdo->prepare("INSERT INTO student_tasks (user_id, title, subject, category, priority, task_date, estimated_minutes, goal_id) VALUES (?, ?, ?, ?, ?, CURRENT_DATE, ?, ?)");
        $stmt->execute([$user_id, $title, $subject, $category, $priority, $estimated_minutes, $goal_id]);
        
        echo json_encode(['status' => 'success', 'message' => 'Task added successfully', 'task_id' => $pdo->lastInsertId()]);
        
    } elseif ($action === 'toggle_complete') {
        $task_id = (int)$_POST['task_id'];
        $is_completed = $_POST['is_completed'] === 'true';
        
        $status = $is_completed ? 'Completed' : 'Pending';
        $completed_at = $is_completed ? date('Y-m-d H:i:s') : null;
        
        // Fetch task to see if it has a goal_id
        $stmt = $pdo->prepare("SELECT goal_id, status FROM student_tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$task_id, $user_id]);
        $task = $stmt->fetch();
        
        $stmt = $pdo->prepare("UPDATE student_tasks SET status = ?, completed_at = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$status, $completed_at, $task_id, $user_id]);
        
        // Increment or decrement goal progress if task status actually changed to/from completed
        if ($task && $task['goal_id'] && $task['status'] !== $status) {
            $increment = $is_completed ? 1 : -1;
            $gstmt = $pdo->prepare("UPDATE student_goals SET current_value = current_value + ? WHERE id = ? AND user_id = ?");
            $gstmt->execute([$increment, $task['goal_id'], $user_id]);
        }
        
        echo json_encode(['status' => 'success']);
        
    } elseif ($action === 'delete') {
        $task_id = (int)$_POST['task_id'];
        $stmt = $pdo->prepare("DELETE FROM student_tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$task_id, $user_id]);
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>
