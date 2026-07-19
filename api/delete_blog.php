<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['blog_id'])) {
    header("Location: ../blogs.php");
    exit;
}

$blog_id = (int)$_POST['blog_id'];
$user_id = $_SESSION['user_id'];

// Verify ownership
$stmt = $pdo->prepare("SELECT image_path FROM blogs WHERE id = ? AND user_id = ?");
$stmt->execute([$blog_id, $user_id]);
$blog = $stmt->fetch();

if ($blog) {
    if (!empty($blog['image_path'])) {
        $file_path = '../uploads/blogs/' . $blog['image_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    $pdo->prepare("DELETE FROM blogs WHERE id = ?")->execute([$blog_id]);
    logActivity('Deleted a blog', 'user', $user_id);
    setFlashMessage('success', 'Blog deleted successfully.');
} else {
    setFlashMessage('danger', 'Blog not found or you do not have permission to delete it.');
}

header("Location: ../blogs.php");
exit;
?>
