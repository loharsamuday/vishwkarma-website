<?php
// idioms-phrasal-verbs/index.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$page_title = "Idioms & Phrasal Verbs for Competitive Exams";
$seo_desc = "Learn Important Idioms & Phrasal Verbs with Hindi Meaning, Examples and Easy Memory Tricks for Bank, SSC, and other exams.";
require_once '../includes/header.php';

// Fetch stats
$stmt = $pdo->query("SELECT COUNT(*) FROM idioms WHERE status = 'Published'");
$total_idioms = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM phrasal_verbs WHERE status = 'Published'");
$total_phrasal_verbs = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM practice_questions WHERE status = 'Published'");
$total_practice_questions = $stmt->fetchColumn();

// Fetch recently added idioms
$stmt = $pdo->query("SELECT * FROM idioms WHERE status = 'Published' ORDER BY id DESC LIMIT 3");
$recent_idioms = $stmt->fetchAll();

// Fetch recently added phrasal verbs
$stmt = $pdo->query("SELECT * FROM phrasal_verbs WHERE status = 'Published' ORDER BY id DESC LIMIT 3");
$recent_phrasal_verbs = $stmt->fetchAll();
?>

<!-- Hero Section -->
<section class="bg-primary-custom text-white py-5 mb-5">
    <div class="container text-center">
        <h1 class="display-5 fw-bold mb-3">Idioms & Phrasal Verbs for Competitive Exams</h1>
        <p class="lead mb-4">Learn Important Idioms & Phrasal Verbs with Hindi Meaning, Examples and Easy Memory Tricks.</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="idioms.php" class="btn btn-light btn-lg shadow-sm"><i class="fas fa-comment-dots me-2"></i>Explore Idioms</a>
            <a href="phrasal-verbs.php" class="btn btn-outline-light btn-lg shadow-sm"><i class="fas fa-layer-group me-2"></i>Learn Phrasal Verbs</a>
            <a href="practice.php" class="btn btn-warning btn-lg shadow-sm text-dark fw-bold"><i class="fas fa-question-circle me-2"></i>Practice Now</a>
        </div>
    </div>
</section>

<div class="container pb-5">
    
    <!-- Search Bar -->
    <div class="row justify-content-center mb-5">
        <div class="col-md-8">
            <form action="search.php" method="GET" class="d-flex">
                <input type="text" name="q" class="form-control form-control-lg me-2 shadow-sm" placeholder="Search Idiom or Phrasal Verb..." required>
                <button type="submit" class="btn btn-primary btn-lg shadow-sm"><i class="fas fa-search"></i> Search</button>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-4 text-center mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-light">
                <div class="card-body py-4">
                    <i class="fas fa-comment-dots fa-3x text-primary mb-3"></i>
                    <h2 class="fw-bold"><?= number_format($total_idioms) ?>+</h2>
                    <h5 class="text-muted">Important Idioms</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-light">
                <div class="card-body py-4">
                    <i class="fas fa-layer-group fa-3x text-success mb-3"></i>
                    <h2 class="fw-bold"><?= number_format($total_phrasal_verbs) ?>+</h2>
                    <h5 class="text-muted">Phrasal Verbs</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-light">
                <div class="card-body py-4">
                    <i class="fas fa-question-circle fa-3x text-warning mb-3"></i>
                    <h2 class="fw-bold"><?= number_format($total_practice_questions) ?>+</h2>
                    <h5 class="text-muted">Practice Questions</h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Recently Added Sections -->
    <div class="row g-5">
        <div class="col-lg-6">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0">Recently Added Idioms</h3>
                <a href="idioms.php" class="btn btn-sm btn-outline-primary">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <?php if (count($recent_idioms) > 0): ?>
                <div class="row g-3">
                    <?php foreach ($recent_idioms as $item): ?>
                        <div class="col-12">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">
                                        <a href="view-idiom.php?slug=<?= htmlspecialchars($item['slug']) ?>" class="text-decoration-none text-dark">
                                            <?= htmlspecialchars($item['idiom']) ?>
                                        </a>
                                    </h5>
                                    <p class="card-text text-muted mb-2"><i class="fas fa-language me-1 text-primary"></i> <?= htmlspecialchars($item['hindi_meaning']) ?></p>
                                    <p class="card-text small"><?= htmlspecialchars(substr($item['english_meaning'], 0, 80)) ?>...</p>
                                    <?php if(!empty($item['memory_trick'])): ?>
                                        <div class="alert alert-warning py-1 px-2 small mb-0 d-inline-block">
                                            <i class="fas fa-lightbulb text-warning"></i> Trick Inside
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">More idioms coming soon!</p>
            <?php endif; ?>
        </div>

        <div class="col-lg-6">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0">Important Phrasal Verbs</h3>
                <a href="phrasal-verbs.php" class="btn btn-sm btn-outline-success">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <?php if (count($recent_phrasal_verbs) > 0): ?>
                <div class="row g-3">
                    <?php foreach ($recent_phrasal_verbs as $item): ?>
                        <div class="col-12">
                            <div class="card border-0 shadow-sm h-100 border-start border-success border-4">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">
                                        <a href="view-phrasal-verb.php?slug=<?= htmlspecialchars($item['slug']) ?>" class="text-decoration-none text-dark">
                                            <?= htmlspecialchars($item['phrasal_verb']) ?>
                                        </a>
                                    </h5>
                                    <p class="card-text text-muted mb-2"><i class="fas fa-language me-1 text-success"></i> <?= htmlspecialchars($item['hindi_meaning']) ?></p>
                                    <p class="card-text small"><?= htmlspecialchars(substr($item['english_meaning'], 0, 80)) ?>...</p>
                                    <?php if(!empty($item['memory_trick'])): ?>
                                        <div class="alert alert-warning py-1 px-2 small mb-0 d-inline-block">
                                            <i class="fas fa-lightbulb text-warning"></i> Trick Inside
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">More phrasal verbs coming soon!</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>
