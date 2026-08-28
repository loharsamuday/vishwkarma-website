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

// Send rejection email if applicable
if ($status === 'Rejected') {
    $stmt = $pdo->prepare("SELECT author_name, email, title FROM user_stories WHERE id = ?");
    $stmt->execute([$id]);
    $storyData = $stmt->fetch();
    
    if ($storyData && !empty($storyData['email'])) {
        $to = $storyData['email'];
        $subject = "Update on your story submission: " . $storyData['title'];
        $message = "Hello " . $storyData['author_name'] . ",\n\n";
        $message .= "Thank you for submitting your story '" . $storyData['title'] . "' to our English Learning platform.\n\n";
        $message .= "Unfortunately, we are unable to publish your story at this time.\n\n";
        if (!empty($admin_note)) {
            $message .= "Reason for rejection:\n" . $admin_note . "\n\n";
        }
        $message .= "We encourage you to practice and submit again.\n\nBest regards,\nAdmin Team";
        
        $headers = "From: no-reply@" . $_SERVER['SERVER_NAME'];
        
        @mail($to, $subject, $message, $headers);
    }
}

// Remove from main public site if status is changed to Rejected or Pending
if ($status !== 'Approved') {
    $stmt = $pdo->prepare("SELECT author_name, title FROM user_stories WHERE id = ?");
    $stmt->execute([$id]);
    $u_story = $stmt->fetch();
    if ($u_story) {
        $title = $u_story['title'];
        $author_search = "%Submitted by: " . escape($u_story['author_name']) . "%";
        $delStmt = $pdo->prepare("DELETE FROM stories WHERE title = ? AND content LIKE ?");
        $delStmt->execute([$title, $author_search]);
    }
}

// If admin wants to publish to main site
if (isset($_POST['publish_to_main']) && $_POST['publish_to_main'] == '1' && $status == 'Approved') {
    // Get story details
    $stmt = $pdo->prepare("SELECT * FROM user_stories WHERE id = ?");
    $stmt->execute([$id]);
    $u_story = $stmt->fetch();
    
    if ($u_story) {
        $title = $u_story['title'];
        
        // Check if it was already published (prevent duplicate entries on multiple saves)
        $checkStmt = $pdo->prepare("SELECT id FROM stories WHERE title = ? AND content LIKE ?");
        // We match by title and a partial match of author name in content to be safe
        $author_search = "%Submitted by: " . escape($u_story['author_name']) . "%";
        $checkStmt->execute([$title, $author_search]);
        
        if ($checkStmt->rowCount() == 0) {
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
}

header("Location: user-stories.php?msg=processed");
exit();
?>
