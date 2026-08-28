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
    $custom_category = trim($_POST['custom_category'] ?? '');
    
    // Handle custom category
    if ($category_id === 'other' && !empty($custom_category)) {
        $stmtCheck = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $stmtCheck->execute([$custom_category]);
        $existingCat = $stmtCheck->fetch();
        
        if ($existingCat) {
            $category_id = $existingCat['id'];
        } else {
            $slug = generateSlug($custom_category);
            $stmtInsert = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
            $stmtInsert->execute([$custom_category, $slug]);
            $category_id = $pdo->lastInsertId();
        }
    } elseif ($category_id === 'other') {
        $category_id = null; // If they selected other but didn't type anything
    }
    
    $difficulty = $_POST['difficulty'];
    $content = trim($_POST['content']);
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // Count words accurately for all languages including Hindi/UTF-8
    $clean_content = trim(strip_tags($content));
    $words = preg_split('/\s+/u', $clean_content, -1, PREG_SPLIT_NO_EMPTY);
    $word_count = $words ? count($words) : 0;
    
    if (empty($author_name) || empty($title) || empty($content)) {
        $error = "Name, Title, and Story Content are required fields.";
    } elseif ($word_count < 100) {
        $error = "Your story must be at least 100 words long. You currently have $word_count words.";
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

<div class="py-5 mb-5 text-center text-white position-relative" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);">
    <div class="container position-relative" style="z-index: 2;">
        <h1 class="fw-bold mb-3 display-5"><i class="fas fa-feather-alt me-2"></i>Write Your Own Story</h1>
        <p class="lead max-w-700 mx-auto" style="opacity: 0.9;">Practice your English by writing a story using the vocabulary you've learned. Once approved, it will be published for others to read!</p>
    </div>
    <!-- Decorative shape divider -->
    <div class="position-absolute bottom-0 start-0 w-100 overflow-hidden" style="line-height: 0;">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none" style="position: relative; display: block; width: calc(137% + 1.3px); height: 40px;">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" style="fill: #f8f9fa;"></path>
        </svg>
    </div>
</div>

<div class="container mb-5 mt-n3">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            
            <?php if ($success): ?>
                <div class="alert alert-success text-center py-5 mb-4 shadow-lg border-0 rounded-4">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h3 class="alert-heading fw-bold">Story Submitted!</h3>
                    <p class="mb-0 fs-5 text-muted"><?= escape($success) ?></p>
                    <a href="stories.php" class="btn btn-outline-success mt-4 px-4 py-2 rounded-pill fw-bold"><i class="fas fa-book me-2"></i>Read Other Stories</a>
                </div>
            <?php else: ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger shadow-sm border-0 rounded-3 mb-4"><i class="fas fa-exclamation-circle me-2"></i><?= escape($error) ?></div>
                <?php endif; ?>

                <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                    <div class="card-body p-4 p-md-5">
                        <form action="" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            
                            <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                                <div class="bg-primary-custom text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <h4 class="fw-bold text-primary-custom mb-0">About You</h4>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="author_name" name="author_name" placeholder="Your Name" required value="<?= isset($_POST['author_name']) ? escape($_POST['author_name']) : escape($default_name) ?>" <?= $is_logged_in ? 'readonly' : '' ?>>
                                        <label for="author_name" class="text-muted">Your Name *</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" value="<?= isset($_POST['email']) ? escape($_POST['email']) : escape($default_email) ?>" <?= $is_logged_in ? 'readonly' : '' ?>>
                                        <label for="email" class="text-muted">Email (Optional)</label>
                                    </div>
                                    <div class="form-text small mt-1 ms-1"><i class="fas fa-lock me-1 text-muted"></i>We'll only use this to notify you.</div>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center mb-4 pb-2 border-bottom mt-5">
                                <div class="bg-primary-custom text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-book-open"></i>
                                </div>
                                <h4 class="fw-bold text-primary-custom mb-0">Your Story</h4>
                            </div>
                            
                            <div class="mb-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="title" name="title" placeholder="Story Title" required value="<?= isset($_POST['title']) ? escape($_POST['title']) : '' ?>">
                                    <label for="title" class="text-muted">Story Title *</label>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3 mb-md-0">
                                        <select class="form-select" id="category_id" name="category_id" onchange="toggleCustomCategory()">
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= escape($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                            <option value="other" <?= (isset($_POST['category_id']) && $_POST['category_id'] == 'other') ? 'selected' : '' ?>>Other (Add New)</option>
                                        </select>
                                        <label for="category_id" class="text-muted">Category</label>
                                    </div>
                                    <div class="form-floating mt-3" id="custom_category_div" style="display: <?= (isset($_POST['category_id']) && $_POST['category_id'] == 'other') ? 'block' : 'none' ?>;">
                                        <input type="text" class="form-control" id="custom_category" name="custom_category" placeholder="Type new category" value="<?= isset($_POST['custom_category']) ? escape($_POST['custom_category']) : '' ?>">
                                        <label for="custom_category" class="text-muted">New Category Name</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mt-3 mt-md-0">
                                    <div class="form-floating">
                                        <select class="form-select" id="difficulty" name="difficulty">
                                            <option value="Beginner" <?= (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Beginner') ? 'selected' : '' ?>>Beginner</option>
                                            <option value="Intermediate" <?= (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Intermediate') ? 'selected' : '' ?>>Intermediate</option>
                                            <option value="Advanced" <?= (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Advanced') ? 'selected' : '' ?>>Advanced</option>
                                        </select>
                                        <label for="difficulty" class="text-muted">Difficulty Level</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="content" class="form-label fw-bold text-primary-custom ms-1">Story Content *</label>
                                <textarea class="form-control bg-light border-0" id="content" name="content" rows="12" required placeholder="Once upon a time..." style="box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); padding: 15px;"><?= isset($_POST['content']) ? escape($_POST['content']) : '' ?></textarea>
                                <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                                    <div class="form-text text-muted small"><i class="fas fa-info-circle me-1"></i> Minimum 100 words required.</div>
                                    <div id="wordCount" class="badge rounded-pill" style="background-color: #dc3545; font-size: 0.85rem;">Words: 0 / 100 min</div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-5">
                                <button type="submit" id="submitBtn" class="btn bg-primary-custom text-white px-5 py-2 fs-6 rounded-pill shadow-sm header-btn-hover" style="letter-spacing: 0.5px;">
                                    <i class="fas fa-paper-plane me-2"></i> Submit Story for Review
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleCustomCategory() {
    const categorySelect = document.getElementById('category_id');
    const customCategoryDiv = document.getElementById('custom_category_div');
    const customCategoryInput = document.getElementById('custom_category');
    
    if (categorySelect.value === 'other') {
        customCategoryDiv.style.display = 'block';
        customCategoryInput.setAttribute('required', 'required');
    } else {
        customCategoryDiv.style.display = 'none';
        customCategoryInput.removeAttribute('required');
        customCategoryInput.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const contentTextarea = document.getElementById('content');
    const wordCountDisplay = document.getElementById('wordCount');
    const submitBtn = document.getElementById('submitBtn');

    if (contentTextarea) {
        function updateWordCount() {
            const text = contentTextarea.value.trim();
            const words = text ? text.split(/\s+/).filter(word => word.length > 0).length : 0;
            
            wordCountDisplay.textContent = `Words: ${words} / 100 min`;
            
            if (words < 100) {
                wordCountDisplay.style.backgroundColor = '#dc3545'; // bootstrap danger color
                submitBtn.disabled = true;
                submitBtn.title = "Please write at least 100 words to submit";
            } else {
                wordCountDisplay.style.backgroundColor = '#198754'; // bootstrap success color
                submitBtn.disabled = false;
                submitBtn.title = "";
            }
        }

        contentTextarea.addEventListener('input', updateWordCount);
        
        // Initial check on load (in case of validation error repopulating the form)
        updateWordCount();
    }
    
    // Initial check for category
    toggleCustomCategory();
});
</script>

<?php include 'includes/footer.php'; ?>
