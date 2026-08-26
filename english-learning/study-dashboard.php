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

// Today's Routine Stats (Master Schedule)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total, 
           SUM(CASE WHEN IF(DATE(updated_at) < CURRENT_DATE AND status = 'Completed', 'Pending', status) = 'Completed' THEN 1 ELSE 0 END) as completed, 
           SUM(CASE WHEN IF(DATE(updated_at) < CURRENT_DATE AND status = 'Completed', 'Pending', status) != 'Completed' THEN 1 ELSE 0 END) as pending 
    FROM study_routines WHERE $condition
");
$stmt->execute([$param]);
$routine_stats = $stmt->fetch();
$routine_progress = $routine_stats['total'] > 0 ? round(($routine_stats['completed'] / $routine_stats['total']) * 100) : 0;

// Live Tracker Routines (Master Schedule)
$stmt = $pdo->prepare("SELECT task_title, start_time, end_time, category FROM study_routines WHERE $condition AND IF(DATE(updated_at) < CURRENT_DATE AND status = 'Completed', 'Pending', status) != 'Completed' ORDER BY start_time ASC");
$stmt->execute([$param]);
$live_routines = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Fetch All Routines for Modals
$stmt = $pdo->prepare("SELECT *, IF(DATE(updated_at) < CURRENT_DATE AND status = 'Completed', 'Pending', status) as current_status FROM study_routines WHERE $condition ORDER BY start_time ASC");
$stmt->execute([$param]);
$all_routines = $stmt->fetchAll();

$completed_routines = [];
$pending_routines = [];
foreach($all_routines as $r) {
    if($r['current_status'] === 'Completed') {
        $completed_routines[] = $r;
    } else {
        $pending_routines[] = $r;
    }
}

$global_completed_tasks = count($completed_routines);
$global_pending_tasks = count($pending_routines);

$page_title = 'Study Dashboard';
include 'includes/header.php';
?>

<div class="bg-light py-4 mb-5 border-bottom">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1" id="liveGreeting">
                Welcome<?= isset($_SESSION['user_name']) ? ' back, <span class="text-primary">' . escape($_SESSION['user_name']) . '</span>' : ' to your Study Dashboard' ?>!
            </h4>
            <p class="text-muted small fw-medium mb-0" id="liveDate">Here is your daily study overview.</p>
        </div>
        <div class="bg-white px-3 py-2 rounded-pill shadow-sm border d-flex align-items-center gap-3">
            <div class="text-end">
                <span class="fw-bold text-primary fs-5" id="liveClock" style="font-family: monospace; letter-spacing: 0.5px;">--:--:-- --</span>
                <div id="liveStatus" style="font-size: 0.7rem; margin-top: -3px;" class="fw-bold text-muted text-uppercase text-end">UPDATING...</div>
            </div>
        </div>
        <div class="d-flex gap-2 mt-2 mt-md-0 w-100 w-md-auto justify-content-end">
            <a href="daily-routine.php" class="btn btn-primary btn-sm px-3 shadow-sm"><i class="fas fa-plus me-1"></i> Routine</a>
            <a href="study-time.php" class="btn btn-warning btn-sm px-3 shadow-sm"><i class="fas fa-plus me-1"></i> Session</a>
        </div>
    </div>
</div>

<div class="container pb-5">
    
    <!-- Top Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-6">
            <a href="study-time.php" class="text-decoration-none">
                <div class="card bg-primary text-white border-0 shadow-sm h-100 hover-lift">
                    <div class="card-body text-center py-4">
                        <i class="fas fa-clock fa-2x mb-2 opacity-75"></i>
                        <h3 class="fw-bold mb-0"><?= $total_study_hours ?>h</h3>
                        <p class="small mb-0">Total Study Time</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-6">
            <div class="card bg-success text-white border-0 shadow-sm h-100 hover-lift" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#completedModal">
                <div class="card-body text-center py-4">
                    <i class="fas fa-check-circle fa-2x mb-2 opacity-75"></i>
                    <h3 class="fw-bold mb-0"><?= $global_completed_tasks ?></h3>
                    <p class="small mb-0">Completed Tasks</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card bg-warning border-0 shadow-sm h-100 hover-lift" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#pendingModal">
                <div class="card-body text-center py-4">
                    <i class="fas fa-tasks fa-2x mb-2 opacity-75 text-dark"></i>
                    <h3 class="fw-bold mb-0 text-dark"><?= $global_pending_tasks ?></h3>
                    <p class="small mb-0 text-dark">Pending Tasks</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <a href="daily-routine.php" class="text-decoration-none">
                <div class="card bg-info text-white border-0 shadow-sm h-100 hover-lift">
                    <div class="card-body text-center py-4">
                        <i class="fas fa-chart-line fa-2x mb-2 opacity-75"></i>
                        <h3 class="fw-bold mb-0"><?= $routine_progress ?>%</h3>
                        <p class="small mb-0">Today's Progress</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Column -->
        <div class="col-lg-8">
            
            <!-- Live Routine Tracker -->
            <div class="card border-0 shadow-sm mb-4 border-start border-primary border-4" id="liveRoutineCard" style="display: none;">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center p-4 gap-3">
                    <div>
                        <span class="badge bg-success mb-2 pulse-animation" id="liveRoutineStatusBadge">🟢 RUNNING NOW</span>
                        <h4 class="fw-bold mb-1" id="liveRoutineTitle">Loading...</h4>
                        <p class="text-muted mb-0">
                            <i class="far fa-clock me-1"></i> <span id="liveRoutineTime">--:--</span> | 
                            <i class="fas fa-tag me-1"></i> <span id="liveRoutineCategory">--</span>
                        </p>
                    </div>
                    <div>
                        <a href="daily-routine.php" class="btn btn-outline-primary rounded-pill">View Routine</a>
                    </div>
                </div>
                <div class="progress" style="height: 4px; border-radius: 0 0 4px 0;">
                    <div class="progress-bar bg-success" id="liveRoutineProgressBar" role="progressbar" style="width: 0%; transition: width 1s linear;"></div>
                </div>
            </div>

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

<!-- Completed Tasks Modal -->
<div class="modal fade" id="completedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i class="fas fa-check-circle me-2"></i> Completed Tasks Today</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <?php if(empty($completed_routines)): ?>
            <div class="p-4 text-center text-muted">You haven't completed any tasks today yet. Keep going!</div>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach($completed_routines as $r): ?>
                <li class="list-group-item p-3 list-group-item-success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 fw-bold text-decoration-line-through"><?= escape($r['task_title']) ?></h6>
                            <small class="text-muted"><i class="far fa-clock"></i> <?= date('h:i A', strtotime($r['start_time'])) ?> - <?= date('h:i A', strtotime($r['end_time'])) ?></small>
                        </div>
                        <span class="badge bg-success"><i class="fas fa-check"></i> Done</span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Pending Tasks Modal -->
<div class="modal fade" id="pendingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-warning">
        <h5 class="modal-title fw-bold text-dark"><i class="fas fa-tasks me-2"></i> Pending Tasks Today</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <?php if(empty($pending_routines)): ?>
            <div class="p-4 text-center text-muted">Wow! You've completed all your tasks for today. Great job!</div>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach($pending_routines as $r): ?>
                <li class="list-group-item p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 fw-bold"><?= escape($r['task_title']) ?></h6>
                            <small class="text-muted"><i class="far fa-clock"></i> <?= date('h:i A', strtotime($r['start_time'])) ?> - <?= date('h:i A', strtotime($r['end_time'])) ?></small>
                        </div>
                        <a href="daily-routine.php" class="btn btn-sm btn-outline-warning text-dark fw-bold">Do Now</a>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<style>
.hover-lift {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-lift:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.15) !important;
}
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}
.pulse-animation {
    animation: pulse 2s infinite;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Live Routines Data from PHP
const liveRoutines = <?= json_encode($live_routines) ?>;

function updateLiveClock() {
    const now = new Date();
    
    // Time Formatting
    let hours = now.getHours();
    let minutes = now.getMinutes();
    let seconds = now.getSeconds();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    
    // Greeting & Day/Night Logic
    let greeting = 'Good Evening 🌙';
    if (hours >= 5 && hours < 12) greeting = 'Good Morning 🌅';
    else if (hours >= 12 && hours < 17) greeting = 'Good Afternoon ☀️';
    else if (hours >= 17 && hours < 21) greeting = 'Good Evening 🌇';
    
    const userName = <?= isset($_SESSION['user_name']) ? "'" . escape($_SESSION['user_name']) . "'" : "''" ?>;
    document.getElementById('liveGreeting').innerHTML = userName ? `${greeting}, <span class="text-primary">${userName}</span>!` : `${greeting}!`;
    
    // Date Formatting
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('liveDate').innerText = now.toLocaleDateString('en-US', options);

    // Clock Formatting
    hours = hours % 12;
    hours = hours ? hours : 12; // the hour '0' should be '12'
    const strTime = (hours < 10 ? '0'+hours : hours) + ':' + (minutes < 10 ? '0'+minutes : minutes) + ':' + (seconds < 10 ? '0'+seconds : seconds) + ' ' + ampm;
    document.getElementById('liveClock').innerText = strTime;
    document.getElementById('liveStatus').innerHTML = '<span class="text-success">● LIVE</span>';

    // Live Routine Tracker Logic
    updateRoutineTracker(now);
}

// Variable to track the currently notified task to avoid spamming
let lastNotifiedTask = null;

function updateRoutineTracker(now) {
    const currentTotalMinutes = now.getHours() * 60 + now.getMinutes();
    
    let activeRoutine = null;
    let nextRoutine = null;

    for (let r of liveRoutines) {
        // Parse start time (HH:MM:SS)
        const startParts = r.start_time.split(':');
        const startTotalMinutes = parseInt(startParts[0]) * 60 + parseInt(startParts[1]);
        
        // Parse end time (HH:MM:SS)
        const endParts = r.end_time.split(':');
        let endTotalMinutes = parseInt(endParts[0]) * 60 + parseInt(endParts[1]);
        if (endTotalMinutes < startTotalMinutes) endTotalMinutes += 24 * 60; // handles overnight tasks

        if (currentTotalMinutes >= startTotalMinutes && currentTotalMinutes < endTotalMinutes) {
            activeRoutine = { ...r, startMins: startTotalMinutes, endMins: endTotalMinutes };
            break;
        } else if (currentTotalMinutes < startTotalMinutes) {
            if (!nextRoutine) nextRoutine = r;
        }
    }

    const card = document.getElementById('liveRoutineCard');
    
    if (activeRoutine) {
        card.style.display = 'block';
        card.classList.remove('border-warning');
        card.classList.add('border-primary');
        
        document.getElementById('liveRoutineStatusBadge').className = 'badge bg-success mb-2';
        document.getElementById('liveRoutineStatusBadge').innerText = '🟢 RUNNING NOW';
        document.getElementById('liveRoutineTitle').innerText = activeRoutine.task_title;
        document.getElementById('liveRoutineCategory').innerText = activeRoutine.category;
        
        const formatTime = (timeString) => {
            let [h, m] = timeString.split(':');
            let ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return `${h}:${m} ${ampm}`;
        };
        document.getElementById('liveRoutineTime').innerText = `${formatTime(activeRoutine.start_time)} - ${formatTime(activeRoutine.end_time)}`;
        
        // Calculate Progress
        const totalDuration = activeRoutine.endMins - activeRoutine.startMins;
        const elapsed = currentTotalMinutes - activeRoutine.startMins;
        const progressPct = Math.min(100, Math.max(0, (elapsed / totalDuration) * 100));
        document.getElementById('liveRoutineProgressBar').style.width = progressPct + '%';

        // --- BACKGROUND NOTIFICATION LOGIC ---
        // If this is a new active routine we haven't notified about yet
        if (lastNotifiedTask !== activeRoutine.task_title) {
            lastNotifiedTask = activeRoutine.task_title;
            sendBackgroundNotification('Study Time Started! 🟢', `Your task "${activeRoutine.task_title}" is running now. Let's focus!`);
        }

    } else if (nextRoutine) {
        card.style.display = 'block';
        card.classList.remove('border-primary');
        card.classList.add('border-warning');
        
        document.getElementById('liveRoutineStatusBadge').className = 'badge bg-warning text-dark mb-2';
        document.getElementById('liveRoutineStatusBadge').innerText = '⏳ UP NEXT';
        document.getElementById('liveRoutineTitle').innerText = nextRoutine.task_title;
        document.getElementById('liveRoutineCategory').innerText = nextRoutine.category;
        
        const formatTime = (timeString) => {
            let [h, m] = timeString.split(':');
            let ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return `${h}:${m} ${ampm}`;
        };
        document.getElementById('liveRoutineTime').innerText = `Starts at ${formatTime(nextRoutine.start_time)}`;
        document.getElementById('liveRoutineProgressBar').style.width = '0%';
    } else {
        card.style.display = 'none';
    }
}

// Function to trigger system notifications (works even if tab is minimized)
function sendBackgroundNotification(title, body) {
    playAlarmSound(); // Play audio alarm
    
    if (!("Notification" in window)) return;
    
    if (Notification.permission === "granted") {
        new Notification(title, { body: body, icon: '<?= EL_BASE_URL ?>assets/images/logo.png' });
    } else if (Notification.permission !== "denied") {
        Notification.requestPermission().then(permission => {
            if (permission === "granted") {
                new Notification(title, { body: body, icon: '<?= EL_BASE_URL ?>assets/images/logo.png' });
            }
        });
    }
}

// Function to generate a professional notification beep using Web Audio API
function playAlarmSound() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        // Play 3 quick pleasant beeps
        for(let i=0; i<3; i++) {
            setTimeout(() => {
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(880, audioCtx.currentTime); // High pitch A5 note
                
                gainNode.gain.setValueAtTime(0.5, audioCtx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.3);
                
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                
                oscillator.start();
                oscillator.stop(audioCtx.currentTime + 0.3);
            }, i * 300);
        }
    } catch(e) {
        console.log("Audio API not supported or autoplay blocked");
    }
}

document.addEventListener("DOMContentLoaded", function() {
    // Request Notification Permission for Background Alarms
    if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
    }

    // Start Live Clock
    updateLiveClock();
    setInterval(updateLiveClock, 1000);

    // Chart Code
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
