<?php
// study-dashboard.php — Ultra Professional App-Like Redesign
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/study_auth.php';

$condition = get_study_user_condition();
$param = get_study_user_param();
$today = date('Y-m-d');

// Total Study Hours
$stmt = $pdo->prepare("SELECT SUM(duration_minutes) FROM study_sessions WHERE $condition");
$stmt->execute([$param]);
$total_study_minutes = $stmt->fetchColumn() ?: 0;
$total_study_hours = round($total_study_minutes / 60, 1);

// Today Study
$stmt = $pdo->prepare("SELECT SUM(duration_minutes) FROM study_sessions WHERE $condition AND study_date = ?");
$stmt->execute([$param, $today]);
$today_study_minutes = $stmt->fetchColumn() ?: 0;
$today_study_hours_formatted = floor($today_study_minutes / 60) . 'h ' . ($today_study_minutes % 60) . 'm';

// This Week
$start_of_week = date('Y-m-d', strtotime('monday this week'));
$stmt = $pdo->prepare("SELECT SUM(duration_minutes) FROM study_sessions WHERE $condition AND study_date >= ?");
$stmt->execute([$param, $start_of_week]);
$week_study_minutes = $stmt->fetchColumn() ?: 0;
$week_study_hours_formatted = floor($week_study_minutes / 60) . 'h ' . ($week_study_minutes % 60) . 'm';

// This Month
$start_of_month = date('Y-m-01');
$stmt = $pdo->prepare("SELECT SUM(duration_minutes) FROM study_sessions WHERE $condition AND study_date >= ?");
$stmt->execute([$param, $start_of_month]);
$month_study_minutes = $stmt->fetchColumn() ?: 0;
$month_study_hours_formatted = floor($month_study_minutes / 60) . 'h ' . ($month_study_minutes % 60) . 'm';

// Routine Stats
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN IF(DATE(updated_at) < CURRENT_DATE AND status = 'Completed', 'Pending', status) = 'Completed' THEN 1 ELSE 0 END) as completed,
           SUM(CASE WHEN IF(DATE(updated_at) < CURRENT_DATE AND status = 'Completed', 'Pending', status) != 'Completed' THEN 1 ELSE 0 END) as pending
    FROM study_routines WHERE $condition
");
$stmt->execute([$param]);
$routine_stats = $stmt->fetch();
$routine_progress = $routine_stats['total'] > 0 ? round(($routine_stats['completed'] / $routine_stats['total']) * 100) : 0;

// Live Routines
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

// Completed / Pending Routines
$stmt = $pdo->prepare("SELECT *, IF(DATE(updated_at) < CURRENT_DATE AND status = 'Completed', 'Pending', status) as current_status FROM study_routines WHERE $condition ORDER BY start_time ASC");
$stmt->execute([$param]);
$all_routines = $stmt->fetchAll();

$completed_routines = [];
$pending_routines = [];
foreach ($all_routines as $r) {
    if ($r['current_status'] === 'Completed') $completed_routines[] = $r;
    else $pending_routines[] = $r;
}
$global_completed_tasks = count($completed_routines);
$global_pending_tasks   = count($pending_routines);

// Streak calculation (consecutive study days)
$streak = 0;
$check_date = date('Y-m-d');
for ($s = 0; $s < 365; $s++) {
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM study_sessions WHERE $condition AND study_date = ?");
    $stmt2->execute([$param, $check_date]);
    if ($stmt2->fetchColumn() > 0) {
        $streak++;
        $check_date = date('Y-m-d', strtotime($check_date . ' -1 day'));
    } else {
        break;
    }
}

$page_title = 'Study Dashboard';
include 'includes/header.php';
?>

<!-- ═══════════════════════════════════════════════════════════════════
     ULTRA PROFESSIONAL STUDY DASHBOARD — App-Like Design
     ═══════════════════════════════════════════════════════════════════ -->

<style>
/* ── Design Tokens ───────────────────────────────────────────── */
:root {
  --app-bg:         #0f0f23;
  --app-surface:    #1a1a35;
  --app-card:       #22223d;
  --app-border:     rgba(255,255,255,0.08);
  --app-text:       #e8e8f4;
  --app-muted:      #8888aa;
  --accent-blue:    #4f9cf9;
  --accent-purple:  #a855f7;
  --accent-green:   #22d3a5;
  --accent-orange:  #f97316;
  --accent-pink:    #ec4899;
  --accent-yellow:  #fbbf24;
  --glow-blue:      0 0 20px rgba(79,156,249,0.3);
  --glow-green:     0 0 20px rgba(34,211,165,0.3);
  --glow-purple:    0 0 20px rgba(168,85,247,0.3);
  --radius-xl:      20px;
  --radius-lg:      14px;
  --radius-md:      10px;
  --transition:     0.25s cubic-bezier(0.4,0,0.2,1);
}

/* ── Base ────────────────────────────────────────────────────── */
body { background: var(--app-bg); color: var(--app-text); font-family: 'Poppins', sans-serif; }

/* ── App Header ─────────────────────────────────────────────── */
.app-header {
  background: linear-gradient(135deg, #1a1a35 0%, #0f0f23 100%);
  border-bottom: 1px solid var(--app-border);
  padding: 20px 0 0;
  position: relative;
  overflow: hidden;
}
.app-header::before {
  content: '';
  position: absolute;
  top: -80px; right: -80px;
  width: 250px; height: 250px;
  background: radial-gradient(circle, rgba(79,156,249,0.15) 0%, transparent 70%);
  pointer-events: none;
}
.app-header::after {
  content: '';
  position: absolute;
  bottom: -60px; left: 20%;
  width: 200px; height: 200px;
  background: radial-gradient(circle, rgba(168,85,247,0.12) 0%, transparent 70%);
  pointer-events: none;
}

.header-greeting { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
.header-sub { font-size: 0.85rem; color: var(--app-muted); }
.live-clock-pill {
  background: rgba(79,156,249,0.15);
  border: 1px solid rgba(79,156,249,0.3);
  border-radius: 50px;
  padding: 6px 16px;
  font-family: 'Courier New', monospace;
  font-size: 1rem;
  font-weight: 700;
  color: var(--accent-blue);
  letter-spacing: 1px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.live-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--accent-green);
  box-shadow: 0 0 8px var(--accent-green);
  animation: blink 1.5s infinite;
}
@keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0.3;} }

/* Quick Action Pills */
.quick-actions { display: flex; gap: 10px; flex-wrap: wrap; padding: 16px 0 20px; }
.qa-pill {
  background: rgba(255,255,255,0.07);
  border: 1px solid var(--app-border);
  border-radius: 50px;
  padding: 8px 18px;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--app-text);
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 7px;
  transition: var(--transition);
}
.qa-pill:hover, .qa-pill.active-pill {
  background: rgba(79,156,249,0.2);
  border-color: rgba(79,156,249,0.5);
  color: var(--accent-blue);
  transform: translateY(-2px);
}
.qa-pill i { font-size: 0.9rem; }

/* ── Stat Cards (Top Row) ────────────────────────────────────── */
.stat-card {
  background: var(--app-card);
  border: 1px solid var(--app-border);
  border-radius: var(--radius-xl);
  padding: 22px 20px;
  position: relative;
  overflow: hidden;
  cursor: pointer;
  transition: var(--transition);
  text-decoration: none;
  display: block;
  color: var(--app-text);
}
.stat-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  border-radius: var(--radius-xl) var(--radius-xl) 0 0;
}
.stat-card.blue::before   { background: linear-gradient(90deg, var(--accent-blue), #818cf8); }
.stat-card.green::before  { background: linear-gradient(90deg, var(--accent-green), #34d399); }
.stat-card.orange::before { background: linear-gradient(90deg, var(--accent-orange), var(--accent-yellow)); }
.stat-card.purple::before { background: linear-gradient(90deg, var(--accent-purple), var(--accent-pink)); }

.stat-card:hover {
  transform: translateY(-5px);
  border-color: rgba(255,255,255,0.15);
  color: var(--app-text);
}
.stat-card.blue:hover   { box-shadow: var(--glow-blue); }
.stat-card.green:hover  { box-shadow: var(--glow-green); }
.stat-card.purple:hover { box-shadow: var(--glow-purple); }

.stat-icon {
  width: 44px; height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  margin-bottom: 14px;
}
.stat-icon.blue   { background: rgba(79,156,249,0.2);  color: var(--accent-blue); }
.stat-icon.green  { background: rgba(34,211,165,0.2);  color: var(--accent-green); }
.stat-icon.orange { background: rgba(249,115,22,0.2);  color: var(--accent-orange); }
.stat-icon.purple { background: rgba(168,85,247,0.2);  color: var(--accent-purple); }

.stat-value {
  font-size: 1.8rem;
  font-weight: 800;
  line-height: 1;
  margin-bottom: 4px;
}
.stat-label { font-size: 0.78rem; color: var(--app-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-change { font-size: 0.75rem; margin-top: 8px; display: flex; align-items: center; gap: 4px; }

/* ── Section Cards ───────────────────────────────────────────── */
.app-card {
  background: var(--app-card);
  border: 1px solid var(--app-border);
  border-radius: var(--radius-xl);
  overflow: hidden;
  margin-bottom: 20px;
}
.app-card-header {
  padding: 18px 22px 14px;
  border-bottom: 1px solid var(--app-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.app-card-title {
  font-size: 0.95rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
}
.card-icon {
  width: 32px; height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
}
.app-card-link {
  font-size: 0.78rem;
  color: var(--accent-blue);
  text-decoration: none;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 4px;
  transition: var(--transition);
}
.app-card-link:hover { color: #82b4ff; }
.app-card-body { padding: 20px 22px; }

/* ── Live Routine Banner ─────────────────────────────────────── */
.live-banner {
  display: none;
  background: linear-gradient(135deg, rgba(79,156,249,0.12), rgba(168,85,247,0.12));
  border: 1px solid rgba(79,156,249,0.3);
  border-radius: var(--radius-xl);
  padding: 18px 22px;
  margin-bottom: 20px;
  position: relative;
  overflow: hidden;
}
.live-banner::before {
  content: '';
  position: absolute;
  top: 0; left: 0;
  right: 0; height: 2px;
  background: linear-gradient(90deg, var(--accent-blue), var(--accent-purple));
}
.live-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(34,211,165,0.15);
  border: 1px solid rgba(34,211,165,0.4);
  color: var(--accent-green);
  padding: 3px 12px;
  border-radius: 50px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 10px;
}
.live-banner.warning-state {
  background: linear-gradient(135deg, rgba(251,191,36,0.08), rgba(249,115,22,0.08));
  border-color: rgba(251,191,36,0.3);
}
.live-banner.warning-state .live-banner-bar { background: var(--accent-yellow); }
.live-banner-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 4px; }
.live-banner-meta { font-size: 0.82rem; color: var(--app-muted); }
.live-banner-bar-track { height: 4px; background: rgba(255,255,255,0.08); border-radius: 2px; margin-top: 14px; }
.live-banner-bar { height: 4px; background: var(--accent-blue); border-radius: 2px; transition: width 1s linear; width: 0%; }

/* ── Circular Progress ───────────────────────────────────────── */
.circular-progress {
  position: relative;
  width: 120px; height: 120px;
  flex-shrink: 0;
}
.circular-progress svg { transform: rotate(-90deg); }
.circular-progress .track { fill: none; stroke: rgba(255,255,255,0.06); stroke-width: 8; }
.circular-progress .fill  { fill: none; stroke-width: 8; stroke-linecap: round; transition: stroke-dashoffset 1s ease; }
.circular-progress .value {
  position: absolute; top: 50%; left: 50%;
  transform: translate(-50%,-50%);
  text-align: center;
  line-height: 1.2;
}
.circular-progress .value .pct { font-size: 1.5rem; font-weight: 800; }
.circular-progress .value .lbl { font-size: 0.6rem; color: var(--app-muted); text-transform: uppercase; letter-spacing: 0.5px; }

/* ── Routine Summary List ────────────────────────────────────── */
.routine-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 0;
  border-bottom: 1px solid var(--app-border);
}
.routine-item:last-child { border-bottom: none; }
.routine-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.routine-dot.done { background: var(--accent-green); box-shadow: 0 0 8px rgba(34,211,165,0.5); }
.routine-dot.pending { background: var(--accent-orange); }
.routine-title { font-size: 0.88rem; font-weight: 600; }
.routine-time { font-size: 0.75rem; color: var(--app-muted); }
.routine-badge-done {
  background: rgba(34,211,165,0.15);
  color: var(--accent-green);
  border: 1px solid rgba(34,211,165,0.3);
  padding: 2px 10px;
  border-radius: 50px;
  font-size: 0.7rem;
  font-weight: 600;
  margin-left: auto;
  flex-shrink: 0;
}
.routine-badge-pending {
  background: rgba(249,115,22,0.12);
  color: var(--accent-orange);
  border: 1px solid rgba(249,115,22,0.3);
  padding: 2px 10px;
  border-radius: 50px;
  font-size: 0.7rem;
  font-weight: 600;
  margin-left: auto;
  flex-shrink: 0;
  text-decoration: none;
}
.routine-badge-pending:hover { background: rgba(249,115,22,0.2); color: var(--accent-orange); }

/* ── Time Summary Pills ──────────────────────────────────────── */
.time-pill {
  background: rgba(255,255,255,0.05);
  border: 1px solid var(--app-border);
  border-radius: var(--radius-lg);
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.time-pill .tp-label { font-size: 0.72rem; color: var(--app-muted); text-transform: uppercase; letter-spacing: 0.5px; }
.time-pill .tp-value { font-size: 1.1rem; font-weight: 800; }

/* ── Target Progress Bar ─────────────────────────────────────── */
.target-item { margin-bottom: 18px; }
.target-item:last-child { margin-bottom: 0; }
.target-label { font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: center; }
.target-label .t-pct { font-size: 0.75rem; color: var(--app-muted); }
.target-track {
  height: 8px;
  background: rgba(255,255,255,0.07);
  border-radius: 50px;
  overflow: hidden;
}
.target-fill {
  height: 100%;
  border-radius: 50px;
  transition: width 1s ease;
}

/* ── Chart Wrapper ───────────────────────────────────────────── */
.chart-wrapper { position: relative; height: 200px; }
@media (max-width: 576px) { .chart-wrapper { height: 160px; } }

/* ── Streak Badge ────────────────────────────────────────────── */
.streak-badge {
  background: linear-gradient(135deg, rgba(249,115,22,0.2), rgba(251,191,36,0.2));
  border: 1px solid rgba(251,191,36,0.35);
  border-radius: var(--radius-lg);
  padding: 10px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.streak-flame { font-size: 1.4rem; }
.streak-count { font-size: 1.5rem; font-weight: 900; color: var(--accent-yellow); line-height: 1; }
.streak-txt { font-size: 0.72rem; color: var(--app-muted); }

/* ── Modals ──────────────────────────────────────────────────── */
.app-modal .modal-content {
  background: var(--app-surface);
  border: 1px solid var(--app-border);
  border-radius: var(--radius-xl);
  color: var(--app-text);
}
.app-modal .modal-header {
  border-bottom: 1px solid var(--app-border);
  padding: 18px 22px;
}
.app-modal .modal-body { padding: 0; }
.modal-task-item {
  padding: 14px 22px;
  border-bottom: 1px solid var(--app-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.modal-task-item:last-child { border-bottom: none; }
.task-name { font-size: 0.88rem; font-weight: 600; }
.task-time { font-size: 0.75rem; color: var(--app-muted); margin-top: 2px; }

/* ── Bottom Nav Bar (Mobile App Feel) ───────────────────────── */
.app-bottom-nav {
  display: none;
  position: fixed;
  bottom: 0; left: 0; right: 0;
  background: var(--app-surface);
  border-top: 1px solid var(--app-border);
  padding: 8px 0 env(safe-area-inset-bottom, 8px);
  z-index: 1000;
  backdrop-filter: blur(20px);
}
@media (max-width: 767px) { .app-bottom-nav { display: flex; } .pb-mobile { padding-bottom: 80px; } }
.nav-tab {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  text-decoration: none;
  color: var(--app-muted);
  font-size: 0.62rem;
  font-weight: 600;
  padding: 6px 0;
  transition: var(--transition);
  position: relative;
}
.nav-tab i { font-size: 1.2rem; }
.nav-tab.active, .nav-tab:hover { color: var(--accent-blue); }
.nav-tab.active::before {
  content: '';
  position: absolute;
  top: 0; left: 50%;
  transform: translateX(-50%);
  width: 30px; height: 2px;
  background: var(--accent-blue);
  border-radius: 0 0 4px 4px;
}

/* ── Scrollbar ───────────────────────────────────────────────── */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: var(--app-surface); }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 3px; }

/* ── Responsive Tweaks ───────────────────────────────────────── */
@media (max-width: 576px) {
  .stat-value { font-size: 1.4rem; }
  .header-greeting { font-size: 1.2rem; }
  .app-card-body { padding: 16px 16px; }
  .app-card-header { padding: 14px 16px; }
}
</style>

<!-- ═══ APP HEADER ═══ -->
<div class="app-header">
  <div class="container-lg px-3 px-lg-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-2">
      <!-- Greeting -->
      <div>
        <div class="header-greeting" id="liveGreeting">
          Welcome<?= isset($_SESSION['user_name']) ? ', <span style="color:var(--accent-blue)">' . escape($_SESSION['user_name']) . '</span>' : '' ?>! 👋
        </div>
        <div class="header-sub mt-1" id="liveDate">Your Study Dashboard</div>
      </div>

      <!-- Clock & Status -->
      <div class="d-flex flex-column align-items-end gap-2">
        <div class="live-clock-pill">
          <div class="live-dot"></div>
          <span id="liveClock">--:--:-- --</span>
        </div>
        <?php if ($streak > 0): ?>
        <div class="streak-badge">
          <span class="streak-flame">🔥</span>
          <div>
            <div class="streak-count"><?= $streak ?></div>
            <div class="streak-txt">Day Streak</div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <a href="study-dashboard.php" class="qa-pill active-pill"><i class="fas fa-chart-pie"></i> Dashboard</a>
      <a href="daily-routine.php"   class="qa-pill"><i class="fas fa-calendar-day"></i> Routine</a>
      <a href="study-time.php"      class="qa-pill"><i class="fas fa-stopwatch"></i> Session</a>
      <a href="daily-target.php"    class="qa-pill"><i class="fas fa-bullseye"></i> Target</a>
      <a href="stories.php"         class="qa-pill"><i class="fas fa-book-open"></i> Stories</a>
    </div>
  </div>
</div>

<!-- ═══ MAIN CONTENT ═══ -->
<div class="container-lg px-3 px-lg-4 py-4 pb-mobile">

  <!-- ── Live Routine Banner ── -->
  <div class="live-banner" id="liveRoutineCard">
    <div class="live-badge" id="liveRoutineStatusBadge">
      <div class="live-dot"></div> RUNNING NOW
    </div>
    <div class="live-banner-title" id="liveRoutineTitle">Loading...</div>
    <div class="live-banner-meta">
      <i class="far fa-clock me-1"></i><span id="liveRoutineTime">--:--</span>
      &nbsp;·&nbsp;
      <i class="fas fa-tag me-1"></i><span id="liveRoutineCategory">--</span>
    </div>
    <div class="d-flex align-items-center justify-content-between mt-3">
      <div class="live-banner-bar-track flex-grow-1 me-3">
        <div class="live-banner-bar" id="liveRoutineProgressBar"></div>
      </div>
      <a href="daily-routine.php" class="qa-pill" style="padding:5px 14px; font-size:0.75rem;">
        View <i class="fas fa-arrow-right ms-1"></i>
      </a>
    </div>
  </div>

  <!-- ── Stats Row ── -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <a href="study-time.php" class="stat-card blue">
        <div class="stat-icon blue"><i class="fas fa-fire-flame-curved"></i></div>
        <div class="stat-value"><?= $total_study_hours ?><small style="font-size:1rem">h</small></div>
        <div class="stat-label">Total Study</div>
        <div class="stat-change" style="color:var(--accent-blue)">
          <i class="fas fa-arrow-up fa-xs"></i> All time
        </div>
      </a>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card green" style="cursor:pointer" data-bs-toggle="modal" data-bs-target="#completedModal">
        <div class="stat-icon green"><i class="fas fa-circle-check"></i></div>
        <div class="stat-value"><?= $global_completed_tasks ?></div>
        <div class="stat-label">Completed</div>
        <div class="stat-change" style="color:var(--accent-green)">
          <i class="fas fa-check fa-xs"></i> Today
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card orange" style="cursor:pointer" data-bs-toggle="modal" data-bs-target="#pendingModal">
        <div class="stat-icon orange"><i class="fas fa-list-check"></i></div>
        <div class="stat-value"><?= $global_pending_tasks ?></div>
        <div class="stat-label">Pending</div>
        <div class="stat-change" style="color:var(--accent-orange)">
          <i class="fas fa-clock fa-xs"></i> Remaining
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <a href="daily-routine.php" class="stat-card purple">
        <div class="stat-icon purple"><i class="fas fa-rocket"></i></div>
        <div class="stat-value"><?= $routine_progress ?><small style="font-size:1rem">%</small></div>
        <div class="stat-label">Progress</div>
        <div class="stat-change" style="color:var(--accent-purple)">
          <i class="fas fa-chart-line fa-xs"></i> Today's routine
        </div>
      </a>
    </div>
  </div>

  <!-- ── Main Grid ── -->
  <div class="row g-4">

    <!-- LEFT COLUMN -->
    <div class="col-lg-8">

      <!-- Chart -->
      <div class="app-card">
        <div class="app-card-header">
          <h2 class="app-card-title">
            <div class="card-icon" style="background:rgba(79,156,249,0.15);color:var(--accent-blue)">
              <i class="fas fa-chart-bar"></i>
            </div>
            7-Day Activity
          </h2>
          <a href="study-time.php" class="app-card-link">Sessions <i class="fas fa-chevron-right fa-xs"></i></a>
        </div>
        <div class="app-card-body">
          <div class="chart-wrapper">
            <canvas id="studyChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Today's Targets -->
      <div class="app-card">
        <div class="app-card-header">
          <h2 class="app-card-title">
            <div class="card-icon" style="background:rgba(168,85,247,0.15);color:var(--accent-purple)">
              <i class="fas fa-bullseye"></i>
            </div>
            Today's Targets
          </h2>
          <a href="daily-target.php" class="app-card-link">Add <i class="fas fa-plus fa-xs"></i></a>
        </div>
        <div class="app-card-body">
          <?php if (empty($targets)): ?>
          <div class="text-center py-4" style="color:var(--app-muted)">
            <i class="fas fa-bullseye fa-3x mb-3 opacity-25"></i>
            <p class="mb-3 small">No targets set for today.</p>
            <a href="daily-target.php" class="qa-pill" style="display:inline-flex">
              <i class="fas fa-plus"></i> Set a Target
            </a>
          </div>
          <?php else: ?>
            <?php foreach ($targets as $t):
              $pct   = $t['target_value'] > 0 ? min(100, round(($t['completed_value'] / $t['target_value']) * 100)) : 0;
              $color = $pct >= 100 ? 'var(--accent-green)' : ($pct >= 50 ? 'var(--accent-blue)' : 'var(--accent-orange)');
            ?>
            <div class="target-item">
              <div class="target-label">
                <span><?= escape($t['target_type']) ?>: <?= escape($t['target_description']) ?></span>
                <span class="t-pct"><?= $t['completed_value'] ?>/<?= $t['target_value'] ?> · <?= $pct ?>%</span>
              </div>
              <div class="target-track">
                <div class="target-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /LEFT -->

    <!-- RIGHT COLUMN -->
    <div class="col-lg-4">

      <!-- Routine Progress -->
      <div class="app-card">
        <div class="app-card-header">
          <h2 class="app-card-title">
            <div class="card-icon" style="background:rgba(34,211,165,0.15);color:var(--accent-green)">
              <i class="fas fa-calendar-day"></i>
            </div>
            Today's Routine
          </h2>
          <a href="daily-routine.php" class="app-card-link">Manage <i class="fas fa-chevron-right fa-xs"></i></a>
        </div>
        <div class="app-card-body">
          <!-- Circular Progress -->
          <div class="d-flex align-items-center gap-4 mb-4">
            <?php
              $circumference = 2 * M_PI * 52; // radius 52
              $dash_offset = $circumference * (1 - $routine_progress / 100);
            ?>
            <div class="circular-progress">
              <svg width="120" height="120" viewBox="0 0 120 120">
                <circle class="track" cx="60" cy="60" r="52"/>
                <circle class="fill" cx="60" cy="60" r="52"
                  stroke="url(#routineGrad)"
                  stroke-dasharray="<?= $circumference ?>"
                  stroke-dashoffset="<?= $dash_offset ?>"
                  id="routineCircle"/>
                <defs>
                  <linearGradient id="routineGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#22d3a5"/>
                    <stop offset="100%" stop-color="#4f9cf9"/>
                  </linearGradient>
                </defs>
              </svg>
              <div class="value">
                <div class="pct" style="color:var(--accent-green)"><?= $routine_progress ?>%</div>
                <div class="lbl">Done</div>
              </div>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between mb-2">
                <span style="font-size:0.82rem;color:var(--app-muted)">Total</span>
                <span style="font-size:0.9rem;font-weight:700"><?= $routine_stats['total'] ?></span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span style="font-size:0.82rem;color:var(--accent-green)">✓ Done</span>
                <span style="font-size:0.9rem;font-weight:700;color:var(--accent-green)"><?= $routine_stats['completed'] ?: 0 ?></span>
              </div>
              <div class="d-flex justify-content-between">
                <span style="font-size:0.82rem;color:var(--accent-orange)">⧗ Left</span>
                <span style="font-size:0.9rem;font-weight:700;color:var(--accent-orange)"><?= $routine_stats['pending'] ?: 0 ?></span>
              </div>
            </div>
          </div>

          <!-- Routine List (max 5) -->
          <?php
            $show_routines = array_slice($all_routines, 0, 5);
            foreach ($show_routines as $r):
              $is_done = ($r['current_status'] === 'Completed');
              $st = date('h:i A', strtotime($r['start_time']));
              $et = date('h:i A', strtotime($r['end_time']));
          ?>
          <div class="routine-item">
            <div class="routine-dot <?= $is_done ? 'done' : 'pending' ?>"></div>
            <div class="flex-grow-1 min-w-0">
              <div class="routine-title <?= $is_done ? 'text-decoration-line-through opacity-50' : '' ?>"><?= escape($r['task_title']) ?></div>
              <div class="routine-time"><?= $st ?> – <?= $et ?></div>
            </div>
            <?php if ($is_done): ?>
              <span class="routine-badge-done">Done</span>
            <?php else: ?>
              <a href="daily-routine.php" class="routine-badge-pending">Do Now</a>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>

          <?php if (count($all_routines) > 5): ?>
          <div class="text-center mt-3">
            <a href="daily-routine.php" class="app-card-link">+<?= count($all_routines)-5 ?> more tasks <i class="fas fa-chevron-right fa-xs"></i></a>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Study Time Summary -->
      <div class="app-card">
        <div class="app-card-header">
          <h2 class="app-card-title">
            <div class="card-icon" style="background:rgba(251,191,36,0.15);color:var(--accent-yellow)">
              <i class="fas fa-stopwatch"></i>
            </div>
            Study Time
          </h2>
          <a href="study-time.php" class="app-card-link">Log <i class="fas fa-plus fa-xs"></i></a>
        </div>
        <div class="app-card-body">
          <div class="row g-2">
            <div class="col-12">
              <div class="time-pill">
                <div class="tp-label">Today</div>
                <div class="tp-value" style="color:var(--app-text)"><?= $today_study_hours_formatted ?></div>
              </div>
            </div>
            <div class="col-6">
              <div class="time-pill">
                <div class="tp-label">This Week</div>
                <div class="tp-value" style="color:var(--accent-blue)"><?= $week_study_hours_formatted ?></div>
              </div>
            </div>
            <div class="col-6">
              <div class="time-pill">
                <div class="tp-label">This Month</div>
                <div class="tp-value" style="color:var(--accent-green)"><?= $month_study_hours_formatted ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /RIGHT -->
  </div>
</div>

<!-- ═══ MODALS ═══ -->

<!-- Completed Tasks -->
<div class="modal fade app-modal" id="completedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" style="font-size:0.95rem;display:flex;align-items:center;gap:10px">
          <div class="card-icon" style="background:rgba(34,211,165,0.15);color:var(--accent-green);width:32px;height:32px">
            <i class="fas fa-circle-check fa-sm"></i>
          </div>
          Completed Tasks
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if (empty($completed_routines)): ?>
          <div class="p-5 text-center" style="color:var(--app-muted)">
            <i class="fas fa-clock fa-3x mb-3 opacity-25"></i>
            <p>No tasks completed yet today. Keep going! 💪</p>
          </div>
        <?php else: ?>
          <?php foreach ($completed_routines as $r): ?>
          <div class="modal-task-item">
            <div class="routine-dot done"></div>
            <div class="flex-grow-1">
              <div class="task-name text-decoration-line-through opacity-60"><?= escape($r['task_title']) ?></div>
              <div class="task-time"><i class="far fa-clock me-1"></i><?= date('h:i A', strtotime($r['start_time'])) ?> – <?= date('h:i A', strtotime($r['end_time'])) ?></div>
            </div>
            <span class="routine-badge-done">✓</span>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Pending Tasks -->
<div class="modal fade app-modal" id="pendingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" style="font-size:0.95rem;display:flex;align-items:center;gap:10px">
          <div class="card-icon" style="background:rgba(249,115,22,0.15);color:var(--accent-orange);width:32px;height:32px">
            <i class="fas fa-list-check fa-sm"></i>
          </div>
          Pending Tasks
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if (empty($pending_routines)): ?>
          <div class="p-5 text-center" style="color:var(--accent-green)">
            <i class="fas fa-trophy fa-3x mb-3"></i>
            <p class="fw-bold">All tasks done! You're amazing! 🎉</p>
          </div>
        <?php else: ?>
          <?php foreach ($pending_routines as $r): ?>
          <div class="modal-task-item">
            <div class="routine-dot pending"></div>
            <div class="flex-grow-1">
              <div class="task-name"><?= escape($r['task_title']) ?></div>
              <div class="task-time"><i class="far fa-clock me-1"></i><?= date('h:i A', strtotime($r['start_time'])) ?> – <?= date('h:i A', strtotime($r['end_time'])) ?></div>
            </div>
            <a href="daily-routine.php" class="routine-badge-pending">Do Now</a>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MOBILE BOTTOM NAV ═══ -->
<nav class="app-bottom-nav">
  <a href="study-dashboard.php" class="nav-tab active"><i class="fas fa-chart-pie"></i>Dashboard</a>
  <a href="daily-routine.php"   class="nav-tab"><i class="fas fa-calendar-day"></i>Routine</a>
  <a href="study-time.php"      class="nav-tab" style="position:relative">
    <div style="background:var(--accent-blue);width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-top:-20px;box-shadow:0 4px 20px rgba(79,156,249,0.5)">
      <i class="fas fa-plus" style="color:white;font-size:1.1rem"></i>
    </div>
    <span style="margin-top:4px">Session</span>
  </a>
  <a href="daily-target.php"    class="nav-tab"><i class="fas fa-bullseye"></i>Targets</a>
  <a href="stories.php"         class="nav-tab"><i class="fas fa-book-open"></i>Stories</a>
</nav>

<!-- ═══ SCRIPTS ═══ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const liveRoutines = <?= json_encode($live_routines) ?>;

/* ── Live Clock ── */
function updateLiveClock() {
  const now   = new Date();
  const h24   = now.getHours();
  const m     = now.getMinutes();
  const s     = now.getSeconds();
  const ampm  = h24 >= 12 ? 'PM' : 'AM';
  const h12   = (h24 % 12) || 12;

  const pad   = n => String(n).padStart(2,'0');
  document.getElementById('liveClock').textContent = `${pad(h12)}:${pad(m)}:${pad(s)} ${ampm}`;

  // Greeting
  let emoji = '🌙';
  let greet = 'Good Evening';
  if (h24 >= 5  && h24 < 12) { emoji = '🌅'; greet = 'Good Morning'; }
  else if (h24 >= 12 && h24 < 17) { emoji = '☀️'; greet = 'Good Afternoon'; }
  else if (h24 >= 17 && h24 < 21) { emoji = '🌇'; greet = 'Good Evening'; }

  const userName = <?= isset($_SESSION['user_name']) ? "'" . escape($_SESSION['user_name']) . "'" : "''" ?>;
  const nameHtml = userName ? `, <span style="color:var(--accent-blue)">${userName}</span>` : '';
  document.getElementById('liveGreeting').innerHTML = `${greet}${nameHtml}! ${emoji}`;

  // Date
  const opts = { weekday:'long', year:'numeric', month:'long', day:'numeric' };
  document.getElementById('liveDate').textContent = now.toLocaleDateString('en-US', opts);

  updateRoutineTracker(now);
}

/* ── Routine Tracker ── */
let lastNotifiedTask = null;
function updateRoutineTracker(now) {
  const cur = now.getHours() * 60 + now.getMinutes();
  let active = null, next = null;

  for (const r of liveRoutines) {
    const sp = r.start_time.split(':');
    const ep = r.end_time.split(':');
    let sm = +sp[0]*60 + +sp[1];
    let em = +ep[0]*60 + +ep[1];
    if (em < sm) em += 1440;
    if (cur >= sm && cur < em) { active = {...r, sm, em}; break; }
    else if (cur < sm && !next) next = r;
  }

  const card = document.getElementById('liveRoutineCard');
  const fmt  = t => { let [h,m]=t.split(':'); const ap=h>=12?'PM':'AM'; h=h%12||12; return `${h}:${m} ${ap}`; };

  if (active) {
    card.style.display = 'block';
    card.classList.remove('warning-state');
    document.getElementById('liveRoutineStatusBadge').innerHTML = '<div class="live-dot"></div> RUNNING NOW';
    document.getElementById('liveRoutineTitle').textContent     = active.task_title;
    document.getElementById('liveRoutineCategory').textContent  = active.category;
    document.getElementById('liveRoutineTime').textContent      = `${fmt(active.start_time)} – ${fmt(active.end_time)}`;
    const pct = Math.min(100, ((cur - active.sm) / (active.em - active.sm)) * 100);
    document.getElementById('liveRoutineProgressBar').style.width = pct + '%';
    if (lastNotifiedTask !== active.task_title) {
      lastNotifiedTask = active.task_title;
      sendNotification('Study Time Started! 🟢', `"${active.task_title}" is running now.`);
    }
  } else if (next) {
    card.style.display = 'block';
    card.classList.add('warning-state');
    document.getElementById('liveRoutineStatusBadge').innerHTML = '⏳ UP NEXT';
    document.getElementById('liveRoutineTitle').textContent     = next.task_title;
    document.getElementById('liveRoutineCategory').textContent  = next.category;
    document.getElementById('liveRoutineTime').textContent      = `Starts at ${fmt(next.start_time)}`;
    document.getElementById('liveRoutineProgressBar').style.width = '0%';
  } else {
    card.style.display = 'none';
  }
}

/* ── Notification + Sound ── */
function sendNotification(title, body) {
  playBeep();
  if (!('Notification' in window)) return;
  if (Notification.permission === 'granted') {
    new Notification(title, { body, icon: '<?= EL_BASE_URL ?>assets/images/logo.png' });
  } else if (Notification.permission !== 'denied') {
    Notification.requestPermission().then(p => {
      if (p === 'granted') new Notification(title, { body });
    });
  }
}

function playBeep() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    [0, 300, 600].forEach(delay => {
      setTimeout(() => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.4, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.25);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(); osc.stop(ctx.currentTime + 0.25);
      }, delay);
    });
  } catch(e) {}
}

/* ── Chart ── */
document.addEventListener('DOMContentLoaded', () => {
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
  }

  updateLiveClock();
  setInterval(updateLiveClock, 1000);

  /* Animate stat values */
  document.querySelectorAll('.stat-value').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(10px)';
    setTimeout(() => {
      el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      el.style.opacity = '1';
      el.style.transform = 'translateY(0)';
    }, 100);
  });

  const labels = <?= json_encode($chart_labels) ?>;
  const data   = <?= json_encode($chart_data) ?>;

  const ctx = document.getElementById('studyChart').getContext('2d');
  const gradient = ctx.createLinearGradient(0, 0, 0, 200);
  gradient.addColorStop(0, 'rgba(79,156,249,0.4)');
  gradient.addColorStop(1, 'rgba(79,156,249,0.0)');

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Hours',
        data,
        backgroundColor: data.map((v,i) => i === data.length-1 ? 'rgba(79,156,249,0.9)' : 'rgba(79,156,249,0.3)'),
        borderColor:  'rgba(79,156,249,0.8)',
        borderWidth:  1.5,
        borderRadius: 8,
        borderSkipped: false,
        hoverBackgroundColor: 'rgba(79,156,249,0.9)',
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(22,22,45,0.95)',
          titleColor: '#e8e8f4',
          bodyColor: '#8888aa',
          borderColor: 'rgba(255,255,255,0.1)',
          borderWidth: 1,
          padding: 12,
          cornerRadius: 10,
          callbacks: {
            label: ctx => ` ${ctx.raw} hrs`
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          border: { display: false },
          ticks: { color: '#8888aa', font: { size: 11, family: 'Poppins' } }
        },
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(255,255,255,0.05)' },
          border: { display: false, dash: [4,4] },
          ticks: { color: '#8888aa', font: { size: 10, family: 'Poppins' }, stepSize: 1 }
        }
      }
    }
  });
});
</script>

<?php include 'includes/footer.php'; ?>
