<?php
$page_title = "Manage Blogs";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT image_path FROM blogs WHERE id = ?");
    $stmt->execute([$id]);
    $blog = $stmt->fetch();
    
    if ($blog) {
        if ($blog['image_path']) {
            $path = '../uploads/blogs/' . $blog['image_path'];
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $pdo->prepare("DELETE FROM blogs WHERE id = ?")->execute([$id]);
        setFlashMessage('success', 'Blog deleted successfully.');
    } else {
        setFlashMessage('danger', 'Blog not found.');
    }
    header("Location: blogs.php");
    exit;
}

// Fetch all blogs
$blogs = $pdo->query("
    SELECT b.*, u.first_name, u.last_name, u.email,
        (SELECT COUNT(*) FROM blog_comments bc WHERE bc.blog_id = b.id) as comment_count,
        (SELECT AVG(rating) FROM blog_ratings br WHERE br.blog_id = b.id) as avg_rating
    FROM blogs b 
    JOIN users u ON b.user_id = u.id 
    ORDER BY b.created_at DESC
")->fetchAll();

require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <div class="d-flex align-items-center">
            <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
            <h3 class="mb-0 text-dark"><i class="fa-solid fa-blog text-primary me-2"></i> Manage Blogs</h3>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="card border-0 shadow-sm p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Stats</th>
                        <th>Date Published</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($blogs)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No blogs published yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($blogs as $blog): ?>
                        <tr>
                            <td>#<?= $blog['id'] ?></td>
                            <td class="fw-bold">
                                <?= htmlspecialchars(mb_strimwidth($blog['title'], 0, 40, "...")) ?>
                                <a href="../blog-detail.php?id=<?= $blog['id'] ?>" target="_blank" class="ms-2 text-primary" title="View Blog"><i class="fa-solid fa-external-link-alt"></i></a>
                            </td>
                            <td><?= htmlspecialchars($blog['first_name'] . ' ' . $blog['last_name']) ?><br><small class="text-muted"><?= htmlspecialchars($blog['email']) ?></small></td>
                            <td>
                                <span class="badge bg-warning text-dark me-1" title="Average Rating"><i class="fa-solid fa-star"></i> <?= number_format($blog['avg_rating'] ?? 0, 1) ?></span>
                                <span class="badge bg-secondary" title="Total Comments"><i class="fa-solid fa-comment"></i> <?= $blog['comment_count'] ?></span>
                            </td>
                            <td><?= date('d M Y, h:i A', strtotime($blog['created_at'])) ?></td>
                            <td>
                                <a href="blogs.php?action=delete&id=<?= $blog['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('WARNING: This will permanently delete the blog and all its comments. Continue?')"><i class="fa-solid fa-trash"></i> Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>
