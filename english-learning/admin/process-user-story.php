<?php
// admin/process-user-story.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$id = (int)$_POST['id'];
$status = $_POST['status'];
$admin_note = trim($_POST['admin_note']);

// Update status
$stmt = $pdo->prepare("UPDATE user_stories SET status = ?, admin_note = ? WHERE id = ?");
$stmt->execute([$status, $admin_note, $id]);

// If admin wants to publish to main site
if (isset($_POST['publish_to_main']) && $_POST['publish_to_main'] == '1' && $status == 'Approved') {
    // Get story details
    $stmt = $pdo->prepare("SELECT * FROM user_stories WHERE id = ?");
    $stmt->execute([$id]);
    $u_story = $stmt->fetch();
    
    if ($u_story) {
        $title = $u_story['title'];
        $slug = generateSlug($title) . '-' . time(); // Avoid duplicate slugs easily
        // Append author info to content
        $content = nl2br(escape($u_story['content'])) . "<br><br><em>Submitted by: " . escape($u_story['author_name']) . "</em>";
        $category_id = $u_story['category_id'];
        $difficulty = $u_story['difficulty'];
        $reading_time = calculateReadingTime($u_story['content']);
        $main_status = 'Published';
        
        $insertStmt = $pdo->prepare("
            INSERT INTO stories (title, slug, content, category_id, difficulty, reading_time, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([$title, $slug, $content, $category_id, $difficulty, $reading_time, $main_status]);
    }
}

header("Location: user-stories.php?msg=processed");
exit();
?>
