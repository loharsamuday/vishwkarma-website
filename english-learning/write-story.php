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

<style>
    .writer-hero { background: radial-gradient(circle at 15% 10%, rgba(255,255,255,.18), transparent 27%), linear-gradient(135deg, #082b49 0%, #0b3b60 48%, #198754 150%); padding: 4.5rem 0 5.75rem; overflow: hidden; }
    .writer-hero::before, .writer-hero::after { content: ''; position: absolute; border: 1px solid rgba(255,255,255,.16); border-radius: 50%; }
    .writer-hero::before { width: 340px; height: 340px; top: -210px; right: -70px; }
    .writer-hero::after { width: 180px; height: 180px; bottom: -115px; left: 8%; }
    .writer-eyebrow { display: inline-flex; align-items: center; gap: .45rem; padding: .45rem .85rem; border: 1px solid rgba(255,255,255,.32); border-radius: 999px; background: rgba(255,255,255,.11); font-size: .78rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .writer-shell { margin-top: -3.2rem; position: relative; z-index: 3; }
    .writer-form-card { border: 1px solid rgba(11,59,96,.08); box-shadow: 0 20px 50px rgba(15, 38, 61, .13) !important; }
    .writer-section-title { display: flex; align-items: center; gap: .8rem; padding-bottom: 1rem; margin-bottom: 1.4rem; border-bottom: 1px solid #e8eef3; }
    .writer-section-icon { width: 42px; height: 42px; border-radius: 13px; display: inline-flex; align-items: center; justify-content: center; background: #e9f3fb; color: var(--primary-color); }
    .writer-form-card .form-control, .writer-form-card .form-select { border-color: #d9e2ea; border-radius: .8rem; }
    .writer-form-card .form-control:focus, .writer-form-card .form-select:focus { border-color: var(--accent-color); box-shadow: 0 0 0 .22rem rgba(52,152,219,.15); }
    .writer-form-card .form-floating > .form-control, .writer-form-card .form-floating > .form-select { min-height: 60px; }
    .story-editor { min-height: 300px; resize: vertical; font-family: Lora, Georgia, serif; font-size: 1.05rem; line-height: 1.75; }
    .writer-tip-card { border: 0; border-radius: 1.15rem; overflow: hidden; box-shadow: 0 12px 30px rgba(15,38,61,.08); }
    .writer-tip-card .list-group-item { border-color: rgba(255,255,255,.12); background: transparent; }
    .writer-tip-number { width: 27px; height: 27px; flex: 0 0 27px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,.18); font-size: .78rem; font-weight: 700; }
    .writer-submit { min-height: 52px; border-radius: .85rem; font-weight: 700; letter-spacing: .01em; }
    .writer-word-progress { height: 7px; border-radius: 999px; background: #e9eef2; overflow: hidden; }
    .writer-word-progress > span { display: block; width: 0; height: 100%; background: #dc3545; transition: width .2s ease, background-color .2s ease; }
    @media (max-width: 767.98px) {
        .writer-hero { padding: 2.7rem 0 4.4rem; }
        .writer-hero h1 { font-size: 2rem; line-height: 1.2; }
        .writer-hero .lead { font-size: 1rem; }
        .writer-shell { margin-top: -2.45rem; padding-left: .45rem; padding-right: .45rem; }
        .writer-form-card .card-body { padding: 1.25rem !important; }
        .writer-section-title { margin-bottom: 1.1rem; }
        .story-editor { min-height: 250px; font-size: 1rem; }
        .writer-tip-card { margin-top: 1rem; }
    }
</style>

<div class="writer-hero text-center text-white position-relative">
    <div class="container position-relative" style="z-index: 2;">
        <span class="writer-eyebrow mb-3"><i class="fas fa-pen-nib"></i> Your writing space</span>
        <h1 class="fw-bold mb-3 display-5">Write a Story That Stays With Readers</h1>
        <p class="lead mb-0 mx-auto" style="max-width: 700px; opacity: .9;">Turn your English practice into a story. Submit it for review and share it with the learning community.</p>
    </div>
    <!-- Decorative shape divider -->
    <div class="position-absolute bottom-0 start-0 w-100 overflow-hidden" style="line-height: 0;">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none" style="position: relative; display: block; width: calc(137% + 1.3px); height: 40px;">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" style="fill: #f8f9fa;"></path>
        </svg>
    </div>
</div>

<div class="container writer-shell mb-5">
    <div class="row justify-content-center">
        <div class="col-xl-11">
            
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

                <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                <div class="card writer-form-card shadow-lg border-0 rounded-4 overflow-hidden">
                    <div class="card-body p-4 p-md-5">
                        <form action="" method="POST" id="storySubmissionForm">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            
                            <div class="writer-section-title">
                                <span class="writer-section-icon"><i class="fas fa-user"></i></span>
                                <div><h4 class="fw-bold text-primary-custom mb-0">About You</h4><small class="text-muted">Tell us who wrote this story.</small></div>
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
                            
                            <div class="writer-section-title mt-5">
                                <span class="writer-section-icon"><i class="fas fa-book-open"></i></span>
                                <div><h4 class="fw-bold text-primary-custom mb-0">Your Story</h4><small class="text-muted">A title, a level, and your original words.</small></div>
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
                                <textarea class="form-control story-editor" id="content" name="content" rows="12" required placeholder="Once upon a time..." aria-describedby="wordCountHelp"><?= isset($_POST['content']) ? escape($_POST['content']) : '' ?></textarea>
                                <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                                    <div id="wordCountHelp" class="form-text text-muted small"><i class="fas fa-info-circle me-1"></i> Minimum 100 words required.</div>
                                    <div id="wordCount" class="badge rounded-pill bg-danger" style="font-size: .82rem;">0 / 100 words</div>
                                </div>
                                <div class="writer-word-progress mt-2"><span id="wordProgress"></span></div>
                            </div>
                            
                            <div class="d-grid mt-5">
                                <button type="submit" id="submitBtn" class="btn bg-primary-custom text-white writer-submit shadow-sm header-btn-hover">
                                    <i class="fas fa-paper-plane me-2"></i> Submit Story for Review
                                </button>
                                <p class="text-center text-muted small mt-3 mb-0"><i class="fas fa-shield-alt me-1"></i>Your story is reviewed before publication.</p>
                            </div>
                        </form>
                    </div>
                </div>
                </div>
                <aside class="col-lg-4">
                    <div class="card writer-tip-card bg-primary-custom text-white">
                        <div class="card-body p-4">
                            <span class="small text-uppercase fw-bold opacity-75" style="letter-spacing:.08em;">Before you submit</span>
                            <h4 class="fw-bold mt-2 mb-3">Write with confidence</h4>
                            <div class="list-group list-group-flush">
                                <div class="list-group-item text-white d-flex gap-3 px-0 py-3"><span class="writer-tip-number">1</span><span><strong>Start with a hook</strong><br><small class="opacity-75">Give readers a reason to keep reading.</small></span></div>
                                <div class="list-group-item text-white d-flex gap-3 px-0 py-3"><span class="writer-tip-number">2</span><span><strong>Use new words</strong><br><small class="opacity-75">Practice vocabulary naturally in sentences.</small></span></div>
                                <div class="list-group-item text-white d-flex gap-3 px-0 py-3"><span class="writer-tip-number">3</span><span><strong>Keep it original</strong><br><small class="opacity-75">Share writing created by you.</small></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="card border-0 shadow-sm rounded-4 mt-4">
                        <div class="card-body p-4"><i class="fas fa-clock text-success me-2"></i><strong>Take your time.</strong><p class="text-muted small mb-0 mt-2">Your draft stays on this page until you submit it.</p></div>
                    </div>
                </aside>
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
    if (!categorySelect || !customCategoryDiv || !customCategoryInput) return;
    
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
    const wordProgress = document.getElementById('wordProgress');
    const submitBtn = document.getElementById('submitBtn');
    const storyForm = document.getElementById('storySubmissionForm');

    if (contentTextarea) {
        function updateWordCount() {
            const text = contentTextarea.value.trim();
            const words = text ? text.split(/\s+/).filter(word => word.length > 0).length : 0;
            
            wordCountDisplay.textContent = `${words} / 100 words`;
            wordProgress.style.width = Math.min((words / 100) * 100, 100) + '%';
            
            if (words < 100) {
                wordCountDisplay.style.backgroundColor = '#dc3545'; // bootstrap danger color
                wordProgress.style.backgroundColor = '#dc3545';
                submitBtn.disabled = true;
                submitBtn.title = "Please write at least 100 words to submit";
            } else {
                wordCountDisplay.style.backgroundColor = '#198754'; // bootstrap success color
                wordProgress.style.backgroundColor = '#198754';
                submitBtn.disabled = false;
                submitBtn.title = "";
            }
        }

        contentTextarea.addEventListener('input', updateWordCount);
        
        // Initial check on load (in case of validation error repopulating the form)
        updateWordCount();
    }

    if (storyForm) {
        storyForm.addEventListener('submit', function () {
            if (!storyForm.checkValidity() || submitBtn.disabled) return;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Submitting your story...';
        });
    }
    
    // Initial check for category
    toggleCustomCategory();
});
</script>

<?php include 'includes/footer.php'; ?>
