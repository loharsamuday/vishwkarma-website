<?php
$page_title = "Online Mock Tests & Exam Practice";
require_once 'includes/db.php';
require_once 'includes/session.php';

// Fetch Filters
$categories = $pdo->query("SELECT * FROM mt_exam_categories WHERE status='active' ORDER BY name ASC")->fetchAll();
$exams = $pdo->query("SELECT * FROM mt_exams WHERE status='active' ORDER BY name ASC")->fetchAll();
$types = $pdo->query("SELECT * FROM mt_test_types WHERE status='active' ORDER BY name ASC")->fetchAll();

// Build Query
$where = ["t.status = 'published'"];
$params = [];

if (!empty($_GET['category'])) {
    $where[] = "e.category_id = ?";
    $params[] = $_GET['category'];
}
if (!empty($_GET['exam'])) {
    $where[] = "t.exam_id = ?";
    $params[] = $_GET['exam'];
}
if (!empty($_GET['type'])) {
    $where[] = "t.test_type_id = ?";
    $params[] = $_GET['type'];
}
if (isset($_GET['is_premium']) && $_GET['is_premium'] != '') {
    $where[] = "t.is_premium = ?";
    $params[] = $_GET['is_premium'];
}

$where_clause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

$sql = "SELECT t.*, e.name as exam_name, tt.name as type_name, 
        (SELECT COUNT(*) FROM mt_test_attempts WHERE mock_test_id = t.id) as attempts_count 
        FROM mt_mock_tests t
        JOIN mt_exams e ON t.exam_id = e.id
        JOIN mt_test_types tt ON t.test_type_id = tt.id
        $where_clause
        ORDER BY t.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tests = $stmt->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>
<?php require_once 'includes/navbar.php'; ?>

<!-- Hero Section -->
<section class="py-5 bg-dark text-white text-center position-relative" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
    <div class="container position-relative z-1">
        <h1 class="display-4 fw-bold mb-3"><i class="fa-solid fa-graduation-cap text-warning"></i> Online Mock Test Series</h1>
        <p class="lead mb-4">Practice with the latest pattern mock tests for Banking, SSC, Railway & other competitive exams.</p>
    </div>
</section>

<div class="container py-5">
    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 mb-4">
            <div class="card border-0 shadow-sm sticky-top" style="top: 100px;">
                <div class="card-header bg-white p-3 border-bottom-0">
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-filter text-primary"></i> Filter Tests</h5>
                </div>
                <div class="card-body bg-light">
                    <form method="GET" action="mock-tests.php">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Exam Category</label>
                            <select name="category" class="form-select shadow-none">
                                <option value="">All Categories</option>
                                <?php foreach($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= (isset($_GET['category']) && $_GET['category'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Specific Exam</label>
                            <select name="exam" class="form-select shadow-none">
                                <option value="">All Exams</option>
                                <?php foreach($exams as $e): ?>
                                    <option value="<?= $e['id'] ?>" <?= (isset($_GET['exam']) && $_GET['exam'] == $e['id']) ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Test Type</label>
                            <select name="type" class="form-select shadow-none">
                                <option value="">All Types</option>
                                <?php foreach($types as $ty): ?>
                                    <option value="<?= $ty['id'] ?>" <?= (isset($_GET['type']) && $_GET['type'] == $ty['id']) ? 'selected' : '' ?>><?= htmlspecialchars($ty['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted text-uppercase">Access Type</label>
                            <select name="is_premium" class="form-select shadow-none">
                                <option value="">Free & Premium</option>
                                <option value="0" <?= (isset($_GET['is_premium']) && $_GET['is_premium'] === '0') ? 'selected' : '' ?>>Free Tests Only</option>
                                <option value="1" <?= (isset($_GET['is_premium']) && $_GET['is_premium'] === '1') ? 'selected' : '' ?>>Premium Tests Only</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">Apply Filters</button>
                        <a href="mock-tests.php" class="btn btn-outline-secondary w-100 mt-2">Clear Filters</a>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tests List -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0 text-dark">Available Tests (<?= count($tests) ?>)</h4>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="student-dashboard.php" class="btn btn-dark"><i class="fa-solid fa-chart-pie me-2"></i> My Dashboard</a>
                <?php endif; ?>
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                <?php if(count($tests) > 0): ?>
                    <?php foreach($tests as $t): ?>
                        <div class="col">
                            <div class="card h-100 border-0 shadow-sm test-card transition-all" style="border-radius: 12px; overflow: hidden;">
                                <div class="card-header bg-white border-bottom-0 pt-4 pb-0 position-relative">
                                    <?php if($t['is_premium']): ?>
                                        <span class="position-absolute top-0 end-0 bg-warning text-dark px-3 py-1 fw-bold" style="border-bottom-left-radius: 12px; font-size: 0.8rem;">
                                            <i class="fa-solid fa-crown me-1"></i> Premium
                                        </span>
                                    <?php else: ?>
                                        <span class="position-absolute top-0 end-0 bg-success text-white px-3 py-1 fw-bold" style="border-bottom-left-radius: 12px; font-size: 0.8rem;">
                                            Free
                                        </span>
                                    <?php endif; ?>
                                    <span class="badge bg-light text-primary border border-primary mb-2"><?= htmlspecialchars($t['exam_name']) ?></span>
                                    <h5 class="card-title fw-bold text-dark lh-sm" style="font-size: 1.1rem; min-height: 40px;"><?= htmlspecialchars($t['title']) ?></h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between text-muted small mb-3">
                                        <span><i class="fa-regular fa-file-lines text-primary"></i> <?= $t['total_questions'] ?> Qs</span>
                                        <span><i class="fa-solid fa-check-double text-success"></i> <?= (float)$t['total_marks'] ?> Marks</span>
                                        <span><i class="fa-regular fa-clock text-danger"></i> <?= $t['duration_minutes'] ?> Mins</span>
                                    </div>
                                    <div class="text-muted small mb-3">
                                        <i class="fa-solid fa-language text-secondary"></i> <?= htmlspecialchars($t['language']) ?><br>
                                        <i class="fa-solid fa-users text-secondary"></i> <?= number_format($t['attempts_count']) ?>+ Attempts
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-top-0 pb-4">
                                    <a href="mock-test-detail.php?slug=<?= $t['slug'] ?>" class="btn btn-outline-primary w-100 fw-bold rounded-pill">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <div class="text-muted mb-3"><i class="fa-solid fa-magnifying-glass fs-1"></i></div>
                        <h5>No mock tests found matching your criteria.</h5>
                        <p class="text-muted">Try adjusting your filters or check back later.</p>
                        <a href="mock-tests.php" class="btn btn-primary mt-2">View All Tests</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.test-card { transition: transform 0.2s, box-shadow 0.2s; }
.test-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
</style>

<?php require_once 'includes/footer.php'; ?>
