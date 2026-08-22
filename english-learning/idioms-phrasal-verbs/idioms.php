<?php
// idioms-phrasal-verbs/idioms.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$page_title = "Important Idioms for Competitive Exams with Hindi Meaning";
$seo_desc = "Learn important English idioms with Hindi meaning, examples, and memory tricks for SSC, Bank, and other competitive exams.";
require_once '../includes/header.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : '';
$difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';

$where = ["i.status = 'Published'"];
$params = [];

if ($category_id) {
    $where[] = "i.category_id = ?";
    $params[] = $category_id;
}
if ($difficulty) {
    $where[] = "i.difficulty = ?";
    $params[] = $difficulty;
}

$where_sql = implode(' AND ', $where);

// Total
$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM idioms i WHERE $where_sql");
$stmt_total->execute($params);
$total_records = $stmt_total->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch
$sql = "SELECT i.*, c.name as category_name FROM idioms i LEFT JOIN exam_categories c ON i.category_id = c.id WHERE $where_sql ORDER BY i.featured DESC, i.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$idioms = $stmt->fetchAll();

// Categories for filter
$stmt_cat = $pdo->query("SELECT id, name FROM exam_categories WHERE status='active' ORDER BY name ASC");
$categories = $stmt_cat->fetchAll();
?>

<div class="bg-light py-4 border-bottom mb-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php">Vocabulary</a></li>
                <li class="breadcrumb-item active" aria-current="page">Idioms</li>
            </ol>
        </nav>
        <h1 class="fw-bold">Important Idioms</h1>
        <p class="text-muted mb-0">Enhance your vocabulary for competitive exams with easy explanations and memory tricks.</p>
    </div>
</div>

<div class="container pb-5">
    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 mb-4">
            <div class="card border-0 shadow-sm sticky-top" style="top: 80px;">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="fas fa-filter me-2 text-primary"></i> Filters
                </div>
                <div class="card-body">
                    <form action="" method="GET">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Exam Category</label>
                            <select class="form-select" name="category" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($category_id == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Difficulty</label>
                            <select class="form-select" name="difficulty" onchange="this.form.submit()">
                                <option value="">All Levels</option>
                                <option value="Easy" <?= ($difficulty == 'Easy') ? 'selected' : '' ?>>Easy</option>
                                <option value="Moderate" <?= ($difficulty == 'Moderate') ? 'selected' : '' ?>>Moderate</option>
                                <option value="Hard" <?= ($difficulty == 'Hard') ? 'selected' : '' ?>>Hard</option>
                                <option value="Very Important" <?= ($difficulty == 'Very Important') ? 'selected' : '' ?>>Very Important</option>
                            </select>
                        </div>
                        <?php if($category_id || $difficulty): ?>
                            <a href="idioms.php" class="btn btn-sm btn-outline-secondary w-100">Clear Filters</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="col-lg-9">
            <?php if(count($idioms) > 0): ?>
                <div class="row g-4">
                    <?php foreach($idioms as $item): ?>
                        <div class="col-md-6">
                            <div class="card h-100 border-0 shadow-sm idiom-card transition-hover">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title fw-bold text-primary mb-0">
                                            <a href="../idioms/<?= htmlspecialchars($item['slug']) ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($item['idiom']) ?>
                                            </a>
                                        </h5>
                                        <?php if($item['featured']): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-star"></i> Important</span>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="card-subtitle mb-3 text-muted">
                                        <i class="fas fa-language me-1"></i> <?= htmlspecialchars($item['hindi_meaning']) ?>
                                    </h6>
                                    <p class="card-text small mb-3">
                                        <?= htmlspecialchars(substr($item['english_meaning'], 0, 100)) ?>...
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($item['difficulty']) ?>
                                        </span>
                                        <a href="../idioms/<?= htmlspecialchars($item['slug']) ?>" class="btn btn-sm btn-outline-primary stretched-link">Learn More <i class="fas fa-arrow-right ms-1"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-5">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&category=<?= $category_id ?>&difficulty=<?= urlencode($difficulty) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-info text-center py-5">
                    <i class="fas fa-search fa-3x mb-3 text-muted"></i>
                    <h4>No Idioms Found</h4>
                    <p>Try adjusting your filters or check back later for new content.</p>
                    <a href="idioms.php" class="btn btn-outline-primary">View All Idioms</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.idiom-card.transition-hover {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.idiom-card.transition-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
}
</style>

<?php require_once '../includes/footer.php'; ?>
