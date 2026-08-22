<?php
// index.php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard/");
    exit;
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$page_title = 'Welcome to English Stories & Learning';
include 'includes/header.php';

// Fetch Latest Stories
$stmt = $pdo->query("
    SELECT s.*, c.name as category_name 
    FROM stories s 
    LEFT JOIN categories c ON s.category_id = c.id 
    WHERE s.status = 'Published' 
    ORDER BY s.created_at DESC 
    LIMIT 6
");
$latest_stories = $stmt->fetchAll();

// Fetch some categories
$stmt = $pdo->query("SELECT * FROM categories LIMIT 4");
$categories = $stmt->fetchAll();

?>

<!-- Hero Section -->
<div class="bg-primary-custom text-white py-5 mb-5 shadow-sm">
    <div class="container text-center py-4">
        <h1 class="display-4 fw-bold mb-3">Improve Your English Through Stories</h1>
        <p class="lead mb-4">Read engaging stories, learn new vocabulary with Hindi meanings, and practice by writing your own stories.</p>
        <a href="stories.php" class="btn btn-light btn-lg text-primary-custom fw-bold px-4 me-md-2">Start Reading</a>
        <a href="write-story.php" class="btn btn-outline-light btn-lg px-4">Write a Story</a>
    </div>
</div>

<div class="container">
    <div class="row mb-5 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold mb-3">Why learn with stories?</h2>
            <p class="fs-5 text-muted">Reading stories is one of the most natural ways to improve your English. You learn vocabulary in context, see grammar rules in action, and stay engaged with interesting plots.</p>
            <ul class="list-unstyled fs-5 mt-4">
                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Highlighted difficult vocabulary</li>
                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Hindi meanings for better understanding</li>
                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Synonyms & Antonyms</li>
                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Categorized by difficulty levels</li>
            </ul>
        </div>
        <div class="col-md-6 text-center">
            <!-- Generate a placeholder or use an icon layout -->
            <div class="p-5 bg-white rounded-circle shadow-sm d-inline-block border border-2 border-primary" style="width: 300px; height: 300px;">
                <i class="fas fa-book-open text-primary-custom" style="font-size: 8rem; margin-top: 2rem;"></i>
            </div>
        </div>
    </div>

    <!-- Latest Stories Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
        <h3 class="fw-bold mb-0">Latest Stories</h3>
        <a href="stories.php" class="text-decoration-none">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    
    <div class="row g-4 mb-5">
        <?php foreach($latest_stories as $story): ?>
        <div class="col-md-4">
            <div class="card story-card rounded-3 overflow-hidden">
                <div class="card-body p-4">
                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="badge bg-secondary"><?= escape($story['category_name'] ?: 'Uncategorized') ?></span>
                        <?php if($story['difficulty'] == 'Beginner'): ?>
                            <span class="badge badge-beginner">Beginner</span>
                        <?php elseif($story['difficulty'] == 'Intermediate'): ?>
                            <span class="badge badge-intermediate">Intermediate</span>
                        <?php else: ?>
                            <span class="badge badge-advanced">Advanced</span>
                        <?php endif; ?>
                    </div>
                    <h5 class="card-title fw-bold mt-3 mb-3">
                        <a href="story.php?id=<?= $story['id'] ?>" class="text-dark"><?= escape($story['title']) ?></a>
                    </h5>
                    <p class="card-text text-muted mb-4"><?= escape($story['short_description']) ?></p>
                    <div class="d-flex justify-content-between align-items-center mt-auto">
                        <small class="text-muted"><i class="far fa-clock me-1"></i> <?= $story['reading_time'] ?> min read</small>
                        <a href="story.php?id=<?= $story['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Read</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($latest_stories)): ?>
            <div class="col-12"><div class="alert alert-info">No stories published yet.</div></div>
        <?php endif; ?>
    </div>

    <!-- Idioms & Phrasal Verbs Section -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 bg-light">
        <div class="row g-0">
            <div class="col-md-5 bg-primary d-flex align-items-center justify-content-center py-5">
                <i class="fas fa-brain text-white opacity-75" style="font-size: 8rem;"></i>
            </div>
            <div class="col-md-7 p-5">
                <h3 class="fw-bold text-primary mb-3">Idioms & Phrasal Verbs</h3>
                <p class="fs-5 text-muted mb-4">Master English vocabulary for competitive exams like SSC, Banking, and Railway. Learn with easy Hindi explanations, examples, and Memory Tricks!</p>
                
                <div class="d-flex gap-3 flex-wrap">
                    <a href="idioms-phrasal-verbs/idioms.php" class="btn btn-outline-primary"><i class="fas fa-comment-dots me-1"></i> Important Idioms</a>
                    <a href="idioms-phrasal-verbs/phrasal-verbs.php" class="btn btn-outline-success"><i class="fas fa-layer-group me-1"></i> Phrasal Verbs</a>
                    <a href="idioms-phrasal-verbs/practice.php" class="btn btn-warning fw-bold"><i class="fas fa-question-circle me-1"></i> Practice MCQ</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Categories Section -->
    <div class="row mb-5">
        <div class="col-12 text-center mb-4">
            <h3 class="fw-bold">Browse by Category</h3>
        </div>
        <?php foreach($categories as $cat): ?>
        <div class="col-md-3 col-6 mb-4">
            <a href="stories.php?category=<?= $cat['id'] ?>" class="text-decoration-none">
                <div class="card bg-white border-0 shadow-sm text-center py-4 hover-shadow transition">
                    <i class="fas fa-folder text-accent fa-3x mb-3"></i>
                    <h5 class="text-dark fw-bold mb-0"><?= escape($cat['name']) ?></h5>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Call to action -->
    <div class="card bg-light border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="row g-0">
            <div class="col-md-8 p-5">
                <h3 class="fw-bold text-primary-custom mb-3">Ready to test your skills?</h3>
                <p class="fs-5 text-muted mb-4">Write your own story using the vocabulary you've learned. Submit it for review and get published on our platform!</p>
                <a href="write-story.php" class="btn btn-success btn-lg px-4 shadow"><i class="fas fa-pen me-2"></i> Write Your Story</a>
            </div>
            <div class="col-md-4 bg-primary-custom d-flex align-items-center justify-content-center">
                <i class="fas fa-pencil-alt text-white opacity-50" style="font-size: 8rem;"></i>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
