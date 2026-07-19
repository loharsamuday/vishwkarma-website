<?php
$page_title = "Edit Content";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: cms.php");
    exit;
}

// Fetch Page
$stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
$stmt->execute([$id]);
$page = $stmt->fetch();

if (!$page) {
    die("Page not found.");
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = $_POST['content']; // Allow HTML
    
    if (empty($title)) {
        $error = "Title cannot be empty.";
    } else {
        $stmt = $pdo->prepare("UPDATE pages SET title = ?, content = ? WHERE id = ?");
        $stmt->execute([$title, $content, $id]);
        
        setFlashMessage('success', 'Content updated successfully!');
        header("Location: cms.php");
        exit;
    }
}
?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <h3 class="mb-0 text-dark"><i class="fa-solid fa-edit text-warning me-2"></i> Edit Content: <?= htmlspecialchars($page['slug']) ?></h3>
        <a href="cms.php" class="btn btn-secondary">Back to CMS</a>
    </div>
    
    <div class="card border-0 shadow-sm p-4">
        <?php if($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-bold">Page/Module Title</label>
                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($page['title']) ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold">Content (HTML allowed)</label>
                <textarea name="content" class="form-control" rows="15"><?= htmlspecialchars($page['content']) ?></textarea>
                <small class="text-muted">You can write HTML tags like &lt;h2&gt;, &lt;p&gt;, &lt;b&gt; here.</small>
            </div>
            
            <button type="submit" class="btn btn-warning fw-bold btn-lg w-100">Save Changes</button>
        </form>
    </div>
<?php require_once 'includes/footer.php'; ?>
