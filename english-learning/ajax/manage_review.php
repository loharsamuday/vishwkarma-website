<?php
// ajax/manage_review.php
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
    if ($action === 'submit_review') {
        $learning_note = trim($_POST['learning_note'] ?? '');
        $tomorrow_priority = trim($_POST['tomorrow_priority'] ?? '');
        $study_minutes = (int)($_POST['study_minutes'] ?? 0);
        $tasks_completed = (int)($_POST['tasks_completed'] ?? 0);
        $tasks_total = (int)($_POST['tasks_total'] ?? 0);
        $productivity_score = (int)($_POST['productivity_score'] ?? 0);
        
        $today = date('Y-m-d');
        
        // 1. Save or Update the daily review
        $stmt = $pdo->prepare("SELECT id FROM student_daily_reviews WHERE user_id = ? AND review_date = ?");
        $stmt->execute([$user_id, $today]);
        $existing_review = $stmt->fetch();
        
        if ($existing_review) {
            $stmt = $pdo->prepare("UPDATE student_daily_reviews SET study_minutes = ?, tasks_completed = ?, tasks_total = ?, productivity_score = ?, learning_note = ?, tomorrow_priority = ? WHERE id = ?");
            $stmt->execute([$study_minutes, $tasks_completed, $tasks_total, $productivity_score, $learning_note, $tomorrow_priority, $existing_review['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO student_daily_reviews (user_id, review_date, study_minutes, tasks_completed, tasks_total, productivity_score, learning_note, tomorrow_priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $today, $study_minutes, $tasks_completed, $tasks_total, $productivity_score, $learning_note, $tomorrow_priority]);
        }
        
        // 2. Update Streak Logic
        $stmt = $pdo->prepare("SELECT * FROM student_daily_stats WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch();
        
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        if (!$stats) {
            // First time ever
            $stmt = $pdo->prepare("INSERT INTO student_daily_stats (user_id, current_streak, longest_streak, last_activity_date) VALUES (?, 1, 1, ?)");
            $stmt->execute([$user_id, $today]);
            $streak_awarded = true;
        } else {
            $last_active = $stats['last_activity_date'];
            $current_streak = (int)$stats['current_streak'];
            $longest_streak = (int)$stats['longest_streak'];
            $streak_awarded = false;
            
            if ($last_active !== $today) {
                if ($last_active === $yesterday) {
                    $current_streak++;
                } else {
                    $current_streak = 1; // streak broken
                }
                
                if ($current_streak > $longest_streak) {
                    $longest_streak = $current_streak;
                }
                
                $stmt = $pdo->prepare("UPDATE student_daily_stats SET current_streak = ?, longest_streak = ?, last_activity_date = ? WHERE user_id = ?");
                $stmt->execute([$current_streak, $longest_streak, $today, $user_id]);
                $streak_awarded = true;
            }
        }
        
        $current_hour = (int)date('H');
        if ($current_hour < 17) {
            $msg = "Abhi evening nahi hua hai, review is under process. Complete record saved for future!";
        } else {
            $msg = $streak_awarded ? 'Review saved! Your streak increased 🔥' : 'Review updated successfully!';
        }
        echo json_encode(['status' => 'success', 'message' => $msg]);
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>
