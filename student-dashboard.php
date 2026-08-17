<?php
$page_title = "My Mock Tests Dashboard";
require_once 'includes/db.php';
require_once 'includes/session.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Get Overall Stats
$stmt_stats = $pdo->prepare("SELECT COUNT(*) as total_tests, SUM(score) as total_score, AVG(accuracy) as avg_acc 
                             FROM mt_test_attempts 
                             WHERE user_id = ? AND status = 'completed'");
$stmt_stats->execute([$user_id]);
$stats = $stmt_stats->fetch();

// Get Recent Attempts
$stmt_att = $pdo->prepare("SELECT a.*, t.title as test_title, t.slug as test_slug, t.total_marks, t.result_visibility
                           FROM mt_test_attempts a
                           JOIN mt_mock_tests t ON a.mock_test_id = t.id
                           WHERE a.user_id = ?
                           ORDER BY a.id DESC LIMIT 10");
$stmt_att->execute([$user_id]);
$attempts = $stmt_att->fetchAll();

?>
<?php require_once 'includes/header.php'; ?>
<?php require_once 'includes/navbar.php'; ?>

<style>
    .dash-box { padding: 30px; border-radius: 12px; color: white; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); display: flex; align-items: center; }
    .dash-box i { font-size: 3rem; margin-right: 20px; opacity: 0.8; }
    .dash-box h2 { font-size: 2.5rem; font-weight: bold; margin: 0; }
    .dash-box p { margin: 0; font-size: 1rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; }
</style>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h2 class="fw-bold"><i class="fa-solid fa-gauge-high text-primary me-2"></i> My Exam Dashboard</h2>
        <a href="mock-tests.php" class="btn btn-primary fw-bold px-4 rounded-pill"><i class="fa-solid fa-magnifying-glass me-1"></i> Browse New Tests</a>
    </div>

    <!-- Stats -->
    <div class="row mb-5">
        <div class="col-md-4">
            <div class="dash-box" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <i class="fa-solid fa-clipboard-check"></i>
                <div>
                    <h2><?= (int)$stats['total_tests'] ?></h2>
                    <p>Tests Completed</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-box" style="background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);">
                <i class="fa-solid fa-star"></i>
                <div>
                    <h2><?= number_format((float)$stats['total_score'], 1) ?></h2>
                    <p>Total Score Earned</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-box" style="background: linear-gradient(135deg, #8E2DE2 0%, #4A00E0 100%);">
                <i class="fa-solid fa-bullseye"></i>
                <div>
                    <h2><?= number_format((float)$stats['avg_acc'], 1) ?>%</h2>
                    <p>Average Accuracy</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Attempts -->
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-white p-4 border-bottom-0">
            <h5 class="mb-0 fw-bold">Recent Test Attempts</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Test Title</th>
                            <th>Status</th>
                            <th>Score</th>
                            <th>Accuracy</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($attempts) > 0): ?>
                            <?php foreach($attempts as $a): ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= date('d M Y, h:i A', strtotime($a['start_time'])) ?></td>
                                    <td class="fw-bold">
                                        <a href="mock-test-detail.php?slug=<?= $a['test_slug'] ?>" class="text-dark text-decoration-none hover-primary">
                                            <?= htmlspecialchars($a['test_title']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if($a['status'] == 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">In Progress</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($a['status'] == 'completed'): ?>
                                            <?php if($a['result_visibility'] == 'immediate'): ?>
                                                <strong><?= (float)$a['score'] ?></strong> / <?= (float)$a['total_marks'] ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Hidden</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($a['status'] == 'completed' && $a['result_visibility'] == 'immediate'): ?>
                                            <?= number_format($a['accuracy'], 1) ?>%
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if($a['status'] == 'completed'): ?>
                                            <a href="mt-result.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary fw-bold">View Result</a>
                                        <?php else: ?>
                                            <a href="mock-test-interface.php?test_id=<?= $a['mock_test_id'] ?>" class="btn btn-sm btn-warning fw-bold">Resume Test</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">You haven't attempted any mock tests yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
