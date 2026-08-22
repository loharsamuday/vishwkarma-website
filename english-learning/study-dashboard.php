<?php
// study-dashboard.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/study_auth.php';

$condition = get_study_user_condition();
$param = get_study_user_param();
$today = date('Y-m-d');

// Statistics Calculation
// Total Study Hours
$stmt = $pdo->prepare("SELECT SUM(duration_minutes) FROM study_sessions WHERE $condition");
$stmt->execute([$param]);
$total_study_minutes = $stmt->fetchColumn() ?: 0;
$total_study_hours = round($total_study_minutes / 60, 1);

// Today Study Hours
$stmt = $pdo->prepare("SELECT SUM(duration_minutes) FROM study_sessions WHERE $condition AND study_date = ?");
$stmt->execute([$param, $today]);
$today_study_minutes = $stmt->fetchColumn() ?: 0;
$today_study_hours_formatted = floor($today_study_minutes / 60) . 'h ' . ($today_study_minutes % 60) . 'm';

// This Week Study Hours
$start_of_week = date('Y-m-d', strtotime('monday this week'));
$stmt = $pdo->prepare("SELECT SUM(duration_minutes) FROM study_sessions WHERE $condition AND study_date >= ?");
$stmt->execute([$param, $start_of_week]);
$week_study_minutes = $stmt->fetchColumn() ?: 0;
$week_study_hours_formatted = floor($week_study_minutes / 60) . 'h ' . ($week_study_minutes % 60) . 'm';

// This Month Study Hours
$start_of_month = date('Y-m-01');
$stmt = $pdo->prepare("SELECT SUM(duration_minutes) FROM study_sessions WHERE $condition AND study_date >= ?");
$stmt->execute([$param, $start_of_month]);
$month_study_minutes = $stmt->fetchColumn() ?: 0;
$month_study_hours_formatted = floor($month_study_minutes / 60) . 'h ' . ($month_study_minutes % 60) . 'm';

// Today's Routine
$stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status != 'Completed' THEN 1 ELSE 0 END) as pending FROM study_routines WHERE $condition AND routine_date = ?");
$stmt->execute([$param, $today]);
$routine_stats = $stmt->fetch();
$routine_progress = $routine_stats['total'] > 0 ? round(($routine_stats['completed'] / $routine_stats['total']) * 100) : 0;

// Top Priority Pending Task
$stmt = $pdo->prepare("SELECT * FROM study_routines WHERE $condition AND routine_date = ? AND status != 'Completed' ORDER BY FIELD(priority, 'High', 'Medium', 'Low'), start_time ASC LIMIT 1");
$stmt->execute([$param, $today]);
$top_task = $stmt->fetch();

// Today's Targets
$stmt = $pdo->prepare("SELECT * FROM daily_targets WHERE $condition AND target_date = ?");
$stmt->execute([$param, $today]);
$targets = $stmt->fetchAll();

// 7-Day Chart Data
$chart_data = [];
$chart_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date));
    
    $stmt = $pdo->prepare("SELECT SUM(duration_minutes) FROM study_sessions WHERE $condition AND study_date = ?");
    $stmt->execute([$param, $date]);
    $mins = $stmt->fetchColumn() ?: 0;
    $chart_data[] = round($mins / 60, 2);
}

// Global Total Completed Tasks
$stmt = $pdo->prepare("SELECT COUNT(*) FROM study_routines WHERE $condition AND status = 'Completed'");
$stmt->execute([$param]);
$global_completed_tasks = $stmt->fetchColumn();

// Global Total Pending Tasks
$stmt = $pdo->prepare("SELECT COUNT(*) FROM study_routines WHERE $condition AND status != 'Completed'");
$stmt->execute([$param]);
$global_pending_tasks = $stmt->fetchColumn();

$page_title = 'Study Dashboard';
include 'includes/header.php';
?>

<div class="bg-light py-4 mb-5 border-bottom">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-dark mb-1">Welcome<?= isset($_SESSION['user_name']) ? ' back, ' . escape($_SESSION['user_name']) : ' to your Study Dashboard' ?>! 🎓</h2>
            <p class="text-muted mb-0">Here is your daily study overview.</p>
        </div>
        <div>
            <a href="daily-routine.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add Routine</a>
            <a href="study-time.php" class="btn btn-warning"><i class="fas fa-plus me-1"></i> Add Session</a>
        </div>
    </div>
</div>

<div class="container pb-5">
    
    <!-- Top Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-6">
            <div class="card bg-primary text-white border-0 shadow-sm h-100">
                <div class="card-body text-center py-4">
                    <i class="fas fa-clock fa-2x mb-2 opacity-75"></i>
                    <h3 class="fw-bold mb-0"><?= $total_study_hours ?>h</h3>
                    <p class="small mb-0">Total Study Time</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card bg-success text-white border-0 shadow-sm h-100">
                <div class="card-body text-center py-4">
                    <i class="fas fa-check-circle fa-2x mb-2 opacity-75"></i>
                    <h3 class="fw-bold mb-0"><?= $global_completed_tasks ?></h3>
                    <p class="small mb-0">Completed Tasks</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card bg-warning border-0 shadow-sm h-100">
                <div class="card-body text-center py-4">
                    <i class="fas fa-tasks fa-2x mb-2 opacity-75"></i>
                    <h3 class="fw-bold mb-0"><?= $global_pending_tasks ?></h3>
                    <p class="small mb-0">Pending Tasks</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card bg-info text-white border-0 shadow-sm h-100">
                <div class="card-body text-center py-4">
                    <i class="fas fa-chart-line fa-2x mb-2 opacity-75"></i>
                    <h3 class="fw-bold mb-0"><?= $routine_progress ?>%</h3>
                    <p class="small mb-0">Today's Progress</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Column -->
        <div class="col-lg-8">
            
            <?php if($top_task): ?>
            <div class="card border-0 shadow-sm mb-4 border-start border-danger border-4">
                <div class="card-body d-flex justify-content-between align-items-center p-4">
                    <div>
                        <span class="badge bg-danger mb-2">TODAY'S FOCUS</span>
                        <h4 class="fw-bold mb-1"><?= escape($top_task['task_title']) ?></h4>
                        <p class="text-muted mb-0"><i class="far fa-clock me-1"></i> <?= date('h:i A', strtotime($top_task['start_time'])) ?> - <?= date('h:i A', strtotime($top_task['end_time'])) ?> | <i class="fas fa-tag me-1"></i> <?= escape($top_task['category']) ?></p>
                    </div>
                    <div>
                        <a href="daily-routine.php" class="btn btn-outline-primary rounded-pill">View Routine</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Study Time Chart -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between">
                    <h5 class="fw-bold"><i class="fas fa-chart-bar text-primary me-2"></i> 7-Day Study Activity (Hours)</h5>
                    <a href="study-time.php" class="small text-decoration-none">Manage Sessions</a>
                </div>
                <div class="card-body p-4">
                    <canvas id="studyChart" height="100"></canvas>
                </div>
            </div>

            <!-- Daily Targets -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between">
                    <h5 class="fw-bold"><i class="fas fa-bullseye text-danger me-2"></i> Today's Study Targets</h5>
                    <a href="daily-target.php" class="small text-decoration-none">Add Target</a>
                </div>
                <div class="card-body p-4">
                    <?php if(empty($targets)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-bullseye fa-2x mb-2 opacity-50"></i>
                            <p>No targets set for today.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($targets as $t): 
                            $pct = $t['target_value'] > 0 ? min(100, round(($t['completed_value'] / $t['target_value']) * 100)) : 0;
                            $color = $pct >= 100 ? 'bg-success' : ($pct >= 50 ? 'bg-primary' : 'bg-warning');
                        ?>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-bold"><?= escape($t['target_type']) ?>: <?= escape($t['target_description']) ?></span>
                                <span class="small text-muted"><?= $t['completed_value'] ?> / <?= $t['target_value'] ?> (<?= $pct ?>%)</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar <?= $color ?>" role="progressbar" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Sidebar Column -->
        <div class="col-lg-4">
            
            <!-- Routine Summary -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h5 class="fw-bold"><i class="fas fa-calendar-day text-success me-2"></i> Today's Routine</h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Total Tasks</span>
                        <span class="badge bg-secondary rounded-pill"><?= $routine_stats['total'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Completed</span>
                        <span class="badge bg-success rounded-pill"><?= $routine_stats['completed'] ?: 0 ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Pending</span>
                        <span class="badge bg-warning rounded-pill"><?= $routine_stats['pending'] ?: 0 ?></span>
                    </div>
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $routine_progress ?>%;"><?= $routine_progress ?>%</div>
                    </div>
                    <div class="d-grid mt-4">
                        <a href="daily-routine.php" class="btn btn-outline-success">Manage Routine</a>
                    </div>
                </div>
            </div>

            <!-- Time Summary -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h5 class="fw-bold"><i class="fas fa-stopwatch text-warning me-2"></i> Study Time</h5>
                </div>
                <div class="card-body p-4">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Today
                            <span class="fw-bold"><?= $today_study_hours_formatted ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            This Week
                            <span class="fw-bold text-primary"><?= $week_study_hours_formatted ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-0">
                            This Month
                            <span class="fw-bold text-success"><?= $month_study_hours_formatted ?></span>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('studyChart').getContext('2d');
    const studyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Study Hours',
                data: <?= json_encode($chart_data) ?>,
                backgroundColor: 'rgba(52, 152, 219, 0.6)',
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Hours' }
                }
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
