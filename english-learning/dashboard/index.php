<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?msg=login_required");
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'Smart Dashboard';

// Fetch Target Exam
$stmt_exam = $pdo->prepare("SELECT te.name FROM student_preferences sp JOIN target_exams te ON sp.target_exam_id = te.id WHERE sp.user_id = ?");
$stmt_exam->execute([$user_id]);
$target_exam = $stmt_exam->fetchColumn() ?: 'None Selected';

// Fetch Memory Stats
$today = date('Y-m-d');
$stmt_mem = $pdo->prepare("
    SELECT 
        COUNT(*) as total_saved,
        SUM(CASE WHEN next_revision_date <= ? OR status = 'need_revision' THEN 1 ELSE 0 END) as revision_due,
        SUM(CASE WHEN status = 'mastered' THEN 1 ELSE 0 END) as mastered
    FROM student_memory WHERE user_id = ?
");
$stmt_mem->execute([$today, $user_id]);
$mem_stats = $stmt_mem->fetch();

// Fetch Streak
$stmt_streak = $pdo->prepare("SELECT current_streak FROM student_streaks WHERE user_id = ?");
$stmt_streak->execute([$user_id]);
$streak = $stmt_streak->fetchColumn() ?: 0;

// Fetch Points
$stmt_points = $pdo->prepare("SELECT SUM(points_earned) FROM student_activity WHERE user_id = ?");
$stmt_points->execute([$user_id]);
$total_points = $stmt_points->fetchColumn() ?: 0;

// Calculate Level
$stmt_levels = $pdo->query("SELECT level_name, min_points FROM levels ORDER BY min_points DESC");
$levels = $stmt_levels->fetchAll();
$current_level = 'Beginner';
foreach($levels as $l) {
    if($total_points >= $l['min_points']) {
        $current_level = $l['level_name'];
        break;
    }
}

// Fetch Accuracy
$stmt_acc = $pdo->prepare("SELECT AVG(accuracy) FROM battle_results WHERE user_id = ?");
$stmt_acc->execute([$user_id]);
$accuracy = $stmt_acc->fetchColumn();
$accuracy_disp = $accuracy ? round($accuracy) . '%' : 'N/A';

require_once '../includes/header.php';
?>

<div class="bg-light py-4 mb-5 border-bottom">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-dark mb-1">Good <?= (date('H') < 12) ? 'Morning' : ((date('H') < 17) ? 'Afternoon' : 'Evening') ?> 👋</h2>
            <p class="text-muted mb-0"><i class="fas fa-bullseye text-danger me-1"></i> Target Goal: <strong><?= escape($target_exam) ?></strong> <a href="<?= EL_BASE_URL ?>profile.php" class="small ms-2"><i class="fas fa-pen"></i></a></p>
        </div>
        <div class="text-end d-none d-md-block">
            <div class="d-inline-block text-center me-4">
                <h3 class="fw-bold text-warning mb-0"><i class="fas fa-fire"></i> <?= $streak ?></h3>
                <span class="small text-muted text-uppercase fw-bold">Day Streak</span>
            </div>
            <div class="d-inline-block text-center">
                <h3 class="fw-bold text-primary mb-0"><i class="fas fa-star"></i> <?= number_format($total_points) ?></h3>
                <span class="small text-muted text-uppercase fw-bold">Points</span>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    
    <!-- Mobile stats (hidden on md+) -->
    <div class="row g-3 mb-4 d-md-none">
        <div class="col-6">
            <div class="card bg-warning bg-opacity-10 border-warning border-opacity-50">
                <div class="card-body text-center p-3">
                    <h3 class="fw-bold text-warning mb-0"><i class="fas fa-fire"></i> <?= $streak ?></h3>
                    <span class="small text-dark fw-bold">Day Streak</span>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card bg-primary bg-opacity-10 border-primary border-opacity-25">
                <div class="card-body text-center p-3">
                    <h3 class="fw-bold text-primary mb-0"><i class="fas fa-star"></i> <?= number_format($total_points) ?></h3>
                    <span class="small text-dark fw-bold">Points</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions grid -->
    <div class="row g-4 mb-5">
        
        <div class="col-md-8">
            <h4 class="fw-bold mb-3">Today's Goals</h4>
            <div class="row g-3">
                <div class="col-sm-6">
                    <a href="<?= EL_BASE_URL ?>revision/" class="text-decoration-none">
                        <div class="card shadow-sm border-0 h-100 <?= ($mem_stats['revision_due'] > 0) ? 'bg-danger text-white' : 'bg-light' ?>">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <i class="fas fa-sync fa-2x"></i>
                                    <h3 class="fw-bold mb-0"><?= $mem_stats['revision_due'] ?? 0 ?></h3>
                                </div>
                                <h5 class="fw-bold mb-1">Revision Due</h5>
                                <p class="small mb-0 <?= ($mem_stats['revision_due'] > 0) ? 'text-white-50' : 'text-muted' ?>">Items waiting for review</p>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-sm-6">
                    <a href="<?= EL_BASE_URL ?>5-minute-english/" class="text-decoration-none">
                        <div class="card shadow-sm border-0 h-100 bg-primary text-white">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <i class="fas fa-bolt fa-2x text-warning"></i>
                                </div>
                                <h5 class="fw-bold mb-1">5-Minute English</h5>
                                <p class="small text-white-50 mb-0">Daily 10 activities quick practice</p>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-sm-6">
                    <a href="<?= EL_BASE_URL ?>battle/" class="text-decoration-none">
                        <div class="card shadow-sm border-0 h-100 bg-dark text-white">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <i class="fas fa-khanda fa-2x text-danger"></i>
                                </div>
                                <h5 class="fw-bold mb-1">60-Second Battle</h5>
                                <p class="small text-white-50 mb-0">Beat the clock, test your limits</p>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-sm-6">
                    <a href="<?= EL_BASE_URL ?>idioms-phrasal-verbs/" class="text-decoration-none">
                        <div class="card shadow-sm border-0 h-100 bg-success text-white">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <i class="fas fa-book-open fa-2x"></i>
                                </div>
                                <h5 class="fw-bold mb-1">Learn New Words</h5>
                                <p class="small text-white-50 mb-0">Explore dictionary & save items</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <h4 class="fw-bold mb-3">Your Progress</h4>
            
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted fw-bold">Level</span>
                        <span class="badge bg-primary"><?= $current_level ?></span>
                    </div>
                </div>
            </div>
            
            <a href="<?= EL_BASE_URL ?>my-memory/" class="text-decoration-none">
                <div class="card shadow-sm border-0 mb-3 border-start border-primary border-4">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><i class="fas fa-brain text-primary me-2"></i>My Memory</h5>
                            <span class="small text-muted"><?= $mem_stats['total_saved'] ?? 0 ?> items saved</span>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </div>
            </a>
            
            <div class="card shadow-sm border-0 mb-3 border-start border-success border-4">
                <div class="card-body">
                    <h5 class="fw-bold text-dark mb-1"><i class="fas fa-check-circle text-success me-2"></i>Mastered</h5>
                    <span class="small text-muted"><?= $mem_stats['mastered'] ?? 0 ?> items learned completely</span>
                </div>
            </div>
            
            <a href="<?= EL_BASE_URL ?>my-mistakes/" class="text-decoration-none">
                <div class="card shadow-sm border-0 mb-3 border-start border-danger border-4">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><i class="fas fa-book-dead text-danger me-2"></i>Mistake Book</h5>
                            <span class="small text-muted">Review your past errors</span>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                </div>
            </a>
            
            <div class="card shadow-sm border-0 border-start border-info border-4">
                <div class="card-body">
                    <h5 class="fw-bold text-dark mb-1"><i class="fas fa-bullseye text-info me-2"></i>Accuracy</h5>
                    <span class="small text-muted"><?= $accuracy_disp ?> overall</span>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
