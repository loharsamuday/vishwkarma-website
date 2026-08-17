<?php
// write-story.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$page_title = 'Write Your Own Story';
$error = '';
$success = '';

// Generate CSRF
$csrf_token = generateCsrfToken();

$default_name = '';
$default_email = '';
$is_logged_in = isset($_SESSION['user_id']);

if ($is_logged_in) {
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        $default_name = $user['name'];
        $default_email = $user['email'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token'] ?? '');
    
    $author_name = trim($_POST['author_name']);
    $email = trim($_POST['email']);
    $title = trim($_POST['title']);
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    $difficulty = $_POST['difficulty'];
    $content = trim($_POST['content']);
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    if (empty($author_name) || empty($title) || empty($content)) {
        $error = "Name, Title, and Story Content are required fields.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO user_stories (user_id, author_name, email, title, category_id, difficulty, content, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");
            $stmt->execute([$user_id, $author_name, $email, $title, $category_id, $difficulty, $content]);
            
            $success = "Thank you! Your story has been submitted and is waiting for admin review.";
            // Clear form
            $_POST = [];
        } catch(PDOException $e) {
            $error = "An error occurred while submitting your story. Please try again.";
        }
    }
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

include 'includes/header.php';
?>

<div class="bg-light py-5 mb-5 border-bottom text-center">
    <div class="container">
        <h1 class="fw-bold text-primary-custom mb-3">Write Your Own Story</h1>
        <p class="lead text-muted max-w-700 mx-auto">Practice your English by writing a story using the vocabulary you've learned. Once approved, it will be published for others to read!</p>
    </div>
</div>

<div class="container mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <?php if ($success): ?>
                <div class="alert alert-success text-center py-4 mb-4 shadow-sm border-0">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h4 class="alert-heading">Story Submitted!</h4>
                    <p class="mb-0 fs-5"><?= escape($success) ?></p>
                    <a href="stories.php" class="btn btn-outline-success mt-3">Read Other Stories</a>
                </div>
            <?php else: ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger shadow-sm border-0"><i class="fas fa-exclamation-circle me-2"></i><?= escape($error) ?></div>
                <?php endif; ?>

                <div class="card shadow border-0 rounded-4 overflow-hidden">
                    <div class="card-body p-4 p-md-5">
                        <form action="" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            
                            <h4 class="fw-bold text-primary-custom mb-4 border-bottom pb-2">About You</h4>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="author_name" class="form-label fw-bold">Your Name *</label>
                                    <input type="text" class="form-control form-control-lg" id="author_name" name="author_name" required value="<?= isset($_POST['author_name']) ? escape($_POST['author_name']) : escape($default_name) ?>" <?= $is_logged_in ? 'readonly' : '' ?>>
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label for="email" class="form-label fw-bold">Email (Optional)</label>
                                    <input type="email" class="form-control form-control-lg" id="email" name="email" value="<?= isset($_POST['email']) ? escape($_POST['email']) : escape($default_email) ?>" <?= $is_logged_in ? 'readonly' : '' ?>>
                                    <div class="form-text">We'll only use this to notify you when published.</div>
                                </div>
                            </div>
                            
                            <h4 class="fw-bold text-primary-custom mb-4 border-bottom pb-2 mt-5">Your Story</h4>
                            
                            <div class="mb-4">
                                <label for="title" class="form-label fw-bold">Story Title *</label>
                                <input type="text" class="form-control form-control-lg" id="title" name="title" required value="<?= isset($_POST['title']) ? escape($_POST['title']) : '' ?>">
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="category_id" class="form-label fw-bold">Category</label>
                                    <select class="form-select form-select-lg" id="category_id" name="category_id">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= escape($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <label for="difficulty" class="form-label fw-bold">Difficulty Level</label>
                                    <select class="form-select form-select-lg" id="difficulty" name="difficulty">
                                        <option value="Beginner" <?= (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Beginner') ? 'selected' : '' ?>>Beginner</option>
                                        <option value="Intermediate" <?= (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Intermediate') ? 'selected' : '' ?>>Intermediate</option>
                                        <option value="Advanced" <?= (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Advanced') ? 'selected' : '' ?>>Advanced</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="content" class="form-label fw-bold">Story Content *</label>
                                <div class="form-text text-muted mb-2"><i class="fas fa-info-circle me-1"></i> Write your story in English. Don't worry about minor mistakes!</div>
                                <textarea class="form-control" id="content" name="content" rows="12" required placeholder="Once upon a time..."><?= isset($_POST['content']) ? escape($_POST['content']) : '' ?></textarea>
                            </div>
                            
                            <div class="d-grid mt-5">
                                <button type="submit" class="btn btn-success btn-lg shadow-sm py-3 fw-bold fs-5 text-uppercase">Submit Story for Review</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
