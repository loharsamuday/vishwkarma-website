<?php
// admin/edit-story.php
session_start();
$page_title = 'Edit Story';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch story
$stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ?");
$stmt->execute([$id]);
$story = $stmt->fetch();

if (!$story) {
    header("Location: stories.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    // Update slug only if changed to avoid breaking URLs, or just update it
    $slug = generateSlug($title);
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    $difficulty = $_POST['difficulty'];
    $status = $_POST['status'];
    $short_description = trim($_POST['short_description']);
    $content = trim($_POST['content']);
    $seo_title = trim($_POST['seo_title']) ?: $title;
    $seo_description = trim($_POST['seo_description']) ?: $short_description;
    $reading_time = calculateReadingTime($content);
    
    if (empty($title) || empty($content)) {
        $error = "Title and Content are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE stories SET 
                title = ?, slug = ?, short_description = ?, content = ?, category_id = ?, 
                difficulty = ?, reading_time = ?, status = ?, seo_title = ?, seo_description = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $slug, $short_description, $content, $category_id, $difficulty, $reading_time, $status, $seo_title, $seo_description, $id]);
            
            header("Location: stories.php?msg=updated");
            exit();
        } catch(PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Story</h1>
    <a href="stories.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= escape($error) ?></div>
<?php endif; ?>

<form method="POST" action="">
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold">Story Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required value="<?= escape($story['title']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="short_description" class="form-label fw-bold">Short Description</label>
                        <textarea class="form-control" id="short_description" name="short_description" rows="2"><?= escape($story['short_description']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label fw-bold">Story Content *</label>
                        <textarea class="form-control rich-editor" id="content" name="content"><?= escape($story['content']) ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">SEO Settings</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="seo_title" class="form-label">SEO Title</label>
                        <input type="text" class="form-control" id="seo_title" name="seo_title" value="<?= escape($story['seo_title']) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="seo_description" class="form-label">SEO Description</label>
                        <textarea class="form-control" id="seo_description" name="seo_description" rows="2"><?= escape($story['seo_description']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Update Publish</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="Draft" <?= $story['status'] == 'Draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="Published" <?= $story['status'] == 'Published' ? 'selected' : '' ?>>Published</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary bg-primary-custom">Update Story</button>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Attributes</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $story['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= escape($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="difficulty" class="form-label">Difficulty Level</label>
                        <select class="form-select" id="difficulty" name="difficulty">
                            <option value="Beginner" <?= $story['difficulty'] == 'Beginner' ? 'selected' : '' ?>>Beginner</option>
                            <option value="Intermediate" <?= $story['difficulty'] == 'Intermediate' ? 'selected' : '' ?>>Intermediate</option>
                            <option value="Advanced" <?= $story['difficulty'] == 'Advanced' ? 'selected' : '' ?>>Advanced</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
