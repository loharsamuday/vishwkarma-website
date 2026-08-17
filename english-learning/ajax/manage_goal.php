<?php
// ajax/manage_goal.php
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
        $goal_name = trim($_POST['goal_name'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $target_value = (int)($_POST['target_value'] ?? 100);
        $target_date = $_POST['target_date'] ?? null;
        
        if (empty($goal_name) || $target_value <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Goal name and a valid target value are required']);
            exit();
        }
        
        $stmt = $pdo->prepare("INSERT INTO student_goals (user_id, goal_name, category, target_value, start_date, target_date) VALUES (?, ?, ?, ?, CURRENT_DATE, ?)");
        $stmt->execute([$user_id, $goal_name, $category, $target_value, $target_date]);
        
        echo json_encode(['status' => 'success', 'message' => 'Goal added successfully']);
        
    } elseif ($action === 'delete') {
        $goal_id = (int)$_POST['goal_id'];
        $stmt = $pdo->prepare("DELETE FROM student_goals WHERE id = ? AND user_id = ?");
        $stmt->execute([$goal_id, $user_id]);
        
        echo json_encode(['status' => 'success']);
        
    } elseif ($action === 'update_progress') {
        $goal_id = (int)$_POST['goal_id'];
        $increment = (int)($_POST['increment'] ?? 1);
        
        // Ensure the goal belongs to the user
        $stmt = $pdo->prepare("UPDATE student_goals SET current_value = current_value + ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$increment, $goal_id, $user_id]);
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>
