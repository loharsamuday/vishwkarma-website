<?php
// admin/add-story.php
session_start();
$page_title = 'Add Story';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $slug = generateSlug($title);
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    $difficulty = $_POST['difficulty'];
    $status = $_POST['status'];
    $short_description = trim($_POST['short_description']);
    $content = trim($_POST['content']);
    $seo_title = trim($_POST['seo_title']) ?: $title;
    $seo_description = trim($_POST['seo_description']) ?: $short_description;
    $reading_time = calculateReadingTime($content);
    
    // Feature image upload logic (simplified for now, ideally with validation)
    $featured_image = null;
    
    if (empty($title) || empty($content)) {
        $error = "Title and Content are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO stories (title, slug, short_description, content, category_id, difficulty, reading_time, featured_image, status, seo_title, seo_description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $slug, $short_description, $content, $category_id, $difficulty, $reading_time, $featured_image, $status, $seo_title, $seo_description]);
            
            header("Location: stories.php?msg=added");
            exit();
        } catch(PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch categories for dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Add New Story</h1>
    <a href="stories.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Stories</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= escape($error) ?></div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold">Story Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required value="<?= isset($_POST['title']) ? escape($_POST['title']) : '' ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="short_description" class="form-label fw-bold">Short Description</label>
                        <textarea class="form-control" id="short_description" name="short_description" rows="2"><?= isset($_POST['short_description']) ? escape($_POST['short_description']) : '' ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label fw-bold">Story Content *</label>
                        <textarea class="form-control rich-editor" id="content" name="content"><?= isset($_POST['content']) ? escape($_POST['content']) : '' ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">SEO Settings</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="seo_title" class="form-label">SEO Title</label>
                        <input type="text" class="form-control" id="seo_title" name="seo_title" value="<?= isset($_POST['seo_title']) ? escape($_POST['seo_title']) : '' ?>">
                    </div>
                    <div class="mb-3">
                        <label for="seo_description" class="form-label">SEO Description</label>
                        <textarea class="form-control" id="seo_description" name="seo_description" rows="2"><?= isset($_POST['seo_description']) ? escape($_POST['seo_description']) : '' ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Publish</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="Draft">Draft</option>
                            <option value="Published">Published</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary bg-primary-custom">Save Story</button>
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
                                <option value="<?= $cat['id'] ?>"><?= escape($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="difficulty" class="form-label">Difficulty Level</label>
                        <select class="form-select" id="difficulty" name="difficulty">
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
