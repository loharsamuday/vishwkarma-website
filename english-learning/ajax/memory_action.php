<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login first']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$item_type = $_POST['item_type'] ?? '';
$item_id = (int)($_POST['item_id'] ?? 0);

if (!$item_type || !$item_id || !in_array($item_type, ['idiom', 'phrasal_verb', 'vocabulary'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

try {
    // Check existing
    $stmt = $pdo->prepare("SELECT * FROM student_memory WHERE user_id = ? AND item_type = ? AND item_id = ?");
    $stmt->execute([$user_id, $item_type, $item_id]);
    $memory = $stmt->fetch();
    
    if ($action === 'remember') {
        if (!$memory) {
            $stmt = $pdo->prepare("INSERT INTO student_memory (user_id, item_type, item_id, status) VALUES (?, ?, ?, 'learning')");
            $stmt->execute([$user_id, $item_type, $item_id]);
        }
        echo json_encode(['status' => 'success', 'message' => 'Saved in My Memory', 'button_text' => '💾 Saved in My Memory']);
    } elseif ($action === 'know' || $action === 'forgot') {
        if (!$memory) {
            // Auto save if not already in memory
            $stmt = $pdo->prepare("INSERT INTO student_memory (user_id, item_type, item_id, status, mastery_score) VALUES (?, ?, ?, 'learning', 0)");
            $stmt->execute([$user_id, $item_type, $item_id]);
            $memory = ['mastery_score' => 0, 'status' => 'learning'];
        }
        
        $score = $memory['mastery_score'] ?? 0;
        
        if ($action === 'know') {
            $score += 10;
            $new_status = ($score >= 50) ? 'mastered' : 'learning';
            $interval = '+7 days';
            
            // Add points
            $pdo->prepare("INSERT INTO student_activity (user_id, activity_type, points_earned) VALUES (?, 'recall_correct', 3)")->execute([$user_id]);
            
        } else {
            $score = max(0, $score - 10);
            $new_status = 'need_revision';
            $interval = '+1 day'; // revision next day
            
            // Add to mistake book (simplistic approach for now)
        }
        
        $next_revision = date('Y-m-d', strtotime($interval));
        
        $stmt = $pdo->prepare("UPDATE student_memory SET mastery_score = ?, status = ?, next_revision_date = ? WHERE user_id = ? AND item_type = ? AND item_id = ?");
        $stmt->execute([$score, $new_status, $next_revision, $user_id, $item_type, $item_id]);
        
        echo json_encode(['status' => 'success', 'message' => 'Response recorded!', 'new_status' => $new_status]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
