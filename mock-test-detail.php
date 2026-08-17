<?php
$page_title = "Mock Test Details";
require_once 'includes/db.php';
require_once 'includes/session.php';

if (!isset($_GET['slug'])) {
    header("Location: mock-tests.php");
    exit;
}
$slug = $_GET['slug'];

$stmt = $pdo->prepare("SELECT t.*, e.name as exam_name, tt.name as type_name 
                       FROM mt_mock_tests t
                       JOIN mt_exams e ON t.exam_id = e.id
                       JOIN mt_test_types tt ON t.test_type_id = tt.id
                       WHERE t.slug = ? AND t.status = 'published'");
$stmt->execute([$slug]);
$test = $stmt->fetch();

if (!$test) {
    header("Location: mock-tests.php");
    exit;
}

// Check attempt limits if user is logged in
$can_attempt = true;
$attempts_count = 0;
$msg = "";
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    
    // Check if user is VIP for premium tests
    $is_vip = false;
    $vip_stmt = $pdo->prepare("SELECT COUNT(*) FROM user_vip_status WHERE user_id = ? AND status = 'active' AND expiry_date >= CURDATE()");
    $vip_stmt->execute([$uid]);
    if ($vip_stmt->fetchColumn() > 0) {
        $is_vip = true;
    }
    
    // Check if admin
    $is_admin = false;
    $role_stmt = $pdo->prepare("SELECT role_id FROM users WHERE id = ?");
    $role_stmt->execute([$uid]);
    if ($role_stmt->fetchColumn() == 1) $is_admin = true;
    
    if ($test['is_premium'] && !$is_vip && !$is_admin) {
        $can_attempt = false;
        $msg = "This is a Premium Test. Please upgrade your account to access it.";
    } else {
        $stmt_att = $pdo->prepare("SELECT COUNT(*) FROM mt_test_attempts WHERE user_id = ? AND mock_test_id = ? AND status IN ('completed', 'in_progress')");
        $stmt_att->execute([$uid, $test['id']]);
        $attempts_count = $stmt_att->fetchColumn();
        
        if ($attempts_count >= $test['attempt_limit']) {
            $can_attempt = false;
            $msg = "You have reached the maximum attempt limit (" . $test['attempt_limit'] . ") for this test.";
        }
        
        // Check for active attempt to resume
        $stmt_active = $pdo->prepare("SELECT id FROM mt_test_attempts WHERE user_id = ? AND mock_test_id = ? AND status = 'in_progress'");
        $stmt_active->execute([$uid, $test['id']]);
        $active_attempt = $stmt_active->fetch();
        if ($active_attempt) {
            $can_attempt = true; // Can resume
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>
<?php require_once 'includes/navbar.php'; ?>

<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>" class="text-decoration-none text-muted">Home</a></li>
            <li class="breadcrumb-item"><a href="mock-tests.php" class="text-decoration-none text-muted">Mock Tests</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($test['title']) ?></li>
        </ol>
    </nav>

    <div class="row">
        <!-- Test Details -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-4 p-md-5">
                    <?php if($test['is_premium']): ?>
                        <span class="badge bg-warning text-dark px-3 py-2 fw-bold mb-3"><i class="fa-solid fa-crown me-1"></i> Premium Test</span>
                    <?php else: ?>
                        <span class="badge bg-success text-white px-3 py-2 fw-bold mb-3">Free Test</span>
                    <?php endif; ?>
                    
                    <h2 class="fw-bold text-dark mb-4"><?= htmlspecialchars($test['title']) ?></h2>
                    
                    <div class="row g-4 mb-5 bg-light rounded p-4 text-center">
                        <div class="col-6 col-md-3 border-end">
                            <i class="fa-regular fa-file-lines text-primary fs-3 mb-2"></i>
                            <h5 class="fw-bold mb-0"><?= $test['total_questions'] ?></h5>
                            <span class="text-muted small">Questions</span>
                        </div>
                        <div class="col-6 col-md-3 border-end-md">
                            <i class="fa-solid fa-check-double text-success fs-3 mb-2"></i>
                            <h5 class="fw-bold mb-0"><?= (float)$test['total_marks'] ?></h5>
                            <span class="text-muted small">Max Marks</span>
                        </div>
                        <div class="col-6 col-md-3 border-end">
                            <i class="fa-regular fa-clock text-danger fs-3 mb-2"></i>
                            <h5 class="fw-bold mb-0"><?= $test['duration_minutes'] ?></h5>
                            <span class="text-muted small">Minutes</span>
                        </div>
                        <div class="col-6 col-md-3">
                            <i class="fa-solid fa-language text-secondary fs-3 mb-2"></i>
                            <h6 class="fw-bold mb-0 mt-2"><?= htmlspecialchars($test['language']) ?></h6>
                            <span class="text-muted small">Language</span>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-circle-info text-primary me-2"></i> General Instructions</h5>
                    <div class="bg-light p-4 rounded mb-4" style="font-size: 0.95rem; line-height: 1.6;">
                        <?php if(!empty($test['instructions'])): ?>
                            <?= nl2br(htmlspecialchars($test['instructions'])) ?>
                        <?php else: ?>
                            <ul class="mb-0 text-muted">
                                <li>The clock will be set at the server. The countdown timer in the top right corner of screen will display the remaining time available for you to complete the examination.</li>
                                <li>When the timer reaches zero, the examination will end by itself. You will not be required to end or submit your examination.</li>
                                <li>Each question carries specific marks. Negative marking is applicable as per test configuration (Default: <?= (float)$test['negative_marking'] ?>).</li>
                                <li>You can navigate between questions using the question palette on the right side.</li>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Panel -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm sticky-top" style="top: 100px; border-radius: 12px;">
                <div class="card-body p-4 text-center">
                    <h5 class="fw-bold mb-4">Ready to Start?</h5>
                    
                    <?php if(!isset($_SESSION['user_id'])): ?>
                        <div class="alert alert-warning mb-4">
                            You must be logged in to take this test.
                        </div>
                        <a href="login.php?redirect=mock-test-detail.php?slug=<?= urlencode($slug) ?>" class="btn btn-warning w-100 fw-bold py-2 mb-2">Login to Attempt</a>
                        <a href="register.php" class="btn btn-outline-dark w-100 py-2">Create an Account</a>
                    <?php else: ?>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Total Attempts Allowed:</span>
                                <span class="fw-bold"><?= $test['attempt_limit'] ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Your Attempts:</span>
                                <span class="fw-bold <?= $attempts_count >= $test['attempt_limit'] ? 'text-danger' : 'text-success' ?>"><?= $attempts_count ?></span>
                            </div>
                        </div>

                        <?php if(!$can_attempt): ?>
                            <div class="alert alert-danger fw-bold">
                                <?= $msg ?>
                            </div>
                            <?php if($test['is_premium']): ?>
                                <a href="free-vip.php" class="btn btn-primary w-100 py-2 fw-bold"><i class="fa-solid fa-crown"></i> Get VIP Access</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if(isset($active_attempt) && $active_attempt): ?>
                                <a href="mock-test-interface.php?test_id=<?= $test['id'] ?>" class="btn btn-warning w-100 py-3 fw-bold shadow-sm" style="font-size: 1.1rem;">
                                    <i class="fa-solid fa-play"></i> Resume Test
                                </a>
                            <?php else: ?>
                                <form action="api/start_test.php" method="POST">
                                    <input type="hidden" name="test_id" value="<?= $test['id'] ?>">
                                    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm" style="font-size: 1.1rem; background: linear-gradient(45deg, #1e3c72, #2a5298); border: none;">
                                        <i class="fa-solid fa-laptop-code me-2"></i> Start Test Now
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
