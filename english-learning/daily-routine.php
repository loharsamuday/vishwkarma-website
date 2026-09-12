<?php
// daily-routine.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/study_auth.php';

$condition = get_study_user_condition();
$param = get_study_user_param();
$today = date('Y-m-d');
$filter_date = isset($_GET['date']) ? $_GET['date'] : $today;

// Handle Add/Edit/Delete/Complete
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_routine'])) {
        $date = $_POST['routine_date'];
        $title = $_POST['task_title'];
        $category = $_POST['category'];
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        $priority = $_POST['priority'];
        $notes = $_POST['notes'];

        if ($is_guest) {
            $stmt = $pdo->prepare("INSERT INTO study_routines (guest_id, routine_date, task_title, category, start_time, end_time, priority, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        } else {
            $stmt = $pdo->prepare("INSERT INTO study_routines (user_id, routine_date, task_title, category, start_time, end_time, priority, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        }
        
        if ($stmt->execute([$param, $date, $title, $category, $start, $end, $priority, $notes])) {
            $msg = 'Routine added successfully.';
        }
    } elseif (isset($_POST['mark_completed'])) {
        $id = (int)$_POST['routine_id'];
        $stmt = $pdo->prepare("UPDATE study_routines SET status = 'Completed', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND $condition");
        $stmt->execute([$id, $param]);
    } elseif (isset($_POST['delete_routine'])) {
        $id = (int)$_POST['routine_id'];
        $stmt = $pdo->prepare("DELETE FROM study_routines WHERE id = ? AND $condition");
        $stmt->execute([$id, $param]);
        $msg = 'Routine deleted.';
    }
}

// Fetch Routines (Master Daily Schedule)
$stmt = $pdo->prepare("SELECT *, IF(DATE(updated_at) < CURRENT_DATE AND status = 'Completed', 'Pending', status) as current_status FROM study_routines WHERE $condition ORDER BY start_time ASC");
$stmt->execute([$param]);
$routines = $stmt->fetchAll();

// Stats
$total     = count($routines);
$completed = count(array_filter($routines, fn($r) => $r['current_status'] === 'Completed'));
$pending   = $total - $completed;

$page_title = 'Daily Routine Schedule';
include 'includes/header.php';
?>

<style>
  :root {
    --app-bg: #0f0f23;
    --app-surface: #1a1a35;
    --app-card: #22223d;
    --app-border: rgba(255,255,255,0.08);
    --app-text: #e8e8f4;
    --app-muted: #8888aa;
    --accent-blue: #4f9cf9;
    --accent-green: #22d3a5;
    --accent-orange: #f97316;
    --accent-purple: #a855f7;
    --accent-yellow: #fbbf24;
    --accent-pink: #ec4899;
    --radius-xl: 20px;
    --radius-lg: 14px;
    --transition: 0.25s cubic-bezier(0.4,0,0.2,1);
  }

  body {
    background: var(--app-bg) !important;
    color: var(--app-text) !important;
    font-family: 'Poppins', sans-serif !important;
  }

  /* APP HEADER */
  .app-header {
    background: linear-gradient(135deg, #1a1a35, #0f0f23);
    border-bottom: 1px solid var(--app-border);
    padding: 20px 0 0;
  }

  /* NAV PILLS */
  .nav-pill-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    color: var(--app-muted);
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.07);
    transition: var(--transition);
    white-space: nowrap;
  }
  .nav-pill-link:hover {
    color: var(--app-text);
    background: rgba(255,255,255,0.08);
    border-color: rgba(255,255,255,0.14);
  }
  .nav-pill-link.active {
    color: var(--accent-blue);
    background: rgba(79,156,249,0.12);
    border-color: rgba(79,156,249,0.35);
  }

  /* STATS PILLS */
  .stats-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 700;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--app-border);
    color: var(--app-text);
  }
  .stats-pill .pill-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  /* TASK CARDS */
  .task-card {
    background: var(--app-card);
    border: 1px solid var(--app-border);
    border-radius: var(--radius-lg);
    padding: 16px 18px;
    margin-bottom: 12px;
    border-left-width: 4px;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
  }
  .task-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.35);
  }
  .task-card.priority-high   { border-left-color: #ef4444; }
  .task-card.priority-medium { border-left-color: #fbbf24; }
  .task-card.priority-low    { border-left-color: #4f9cf9; }
  .task-card.is-completed    { opacity: 0.6; }

  .task-card .card-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 7px;
    margin-bottom: 10px;
  }

  /* Badges */
  .badge-dark-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 700;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.09);
    color: var(--app-muted);
    white-space: nowrap;
  }
  .badge-category {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 700;
    background: rgba(168,85,247,0.14);
    border: 1px solid rgba(168,85,247,0.28);
    color: #c084fc;
    white-space: nowrap;
  }
  .badge-priority-high {
    display: inline-flex; align-items: center; padding: 4px 10px;
    border-radius: 50px; font-size: 0.72rem; font-weight: 700;
    background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3);
    color: #f87171;
  }
  .badge-priority-medium {
    display: inline-flex; align-items: center; padding: 4px 10px;
    border-radius: 50px; font-size: 0.72rem; font-weight: 700;
    background: rgba(251,191,36,0.15); border: 1px solid rgba(251,191,36,0.3);
    color: #fde68a;
  }
  .badge-priority-low {
    display: inline-flex; align-items: center; padding: 4px 10px;
    border-radius: 50px; font-size: 0.72rem; font-weight: 700;
    background: rgba(79,156,249,0.15); border: 1px solid rgba(79,156,249,0.3);
    color: #93c5fd;
  }
  .badge-done {
    display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px;
    border-radius: 50px; font-size: 0.72rem; font-weight: 700;
    background: rgba(34,211,165,0.15); border: 1px solid rgba(34,211,165,0.3);
    color: #34d399;
  }
  .badge-pending {
    display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px;
    border-radius: 50px; font-size: 0.72rem; font-weight: 700;
    background: rgba(249,115,22,0.13); border: 1px solid rgba(249,115,22,0.28);
    color: #fb923c;
  }

  /* Task title */
  .task-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--app-text);
    margin: 6px 0 4px;
    line-height: 1.35;
  }
  .task-title.done {
    text-decoration: line-through;
    color: var(--app-muted);
  }
  .task-notes {
    font-size: 0.78rem;
    color: var(--app-muted);
    margin-bottom: 12px;
    line-height: 1.4;
  }

  /* Action buttons */
  .btn-mark-done {
    background: rgba(34,211,165,0.13);
    border: 1px solid rgba(34,211,165,0.3);
    color: #22d3a5;
    border-radius: 8px;
    padding: 6px 14px;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .btn-mark-done:hover {
    background: rgba(34,211,165,0.25);
    transform: translateY(-1px);
  }
  .btn-delete {
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.25);
    color: #f87171;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .btn-delete:hover {
    background: rgba(239,68,68,0.22);
    transform: translateY(-1px);
  }

  /* Flash message */
  .flash-msg {
    background: var(--app-card);
    border: 1px solid rgba(34,211,165,0.25);
    border-left: 4px solid var(--accent-green);
    border-radius: var(--radius-lg);
    padding: 14px 20px;
    color: #22d3a5;
    font-size: 0.88rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
  }

  /* Empty state */
  .empty-state {
    background: var(--app-card);
    border: 1px solid var(--app-border);
    border-radius: var(--radius-xl);
    padding: 60px 30px;
    text-align: center;
  }
  .empty-state .empty-icon {
    font-size: 3.5rem;
    margin-bottom: 16px;
    opacity: 0.35;
  }
  .empty-state h5 {
    font-weight: 800;
    color: var(--app-text);
    margin-bottom: 8px;
  }
  .empty-state p {
    color: var(--app-muted);
    font-size: 0.88rem;
    margin-bottom: 20px;
  }
  .btn-empty-add {
    background: rgba(34,211,165,0.13);
    border: 1px solid rgba(34,211,165,0.3);
    color: #22d3a5;
    border-radius: 50px;
    padding: 10px 26px;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
  }
  .btn-empty-add:hover { background: rgba(34,211,165,0.25); }

  /* DARK MODAL */
  #addRoutineModal .modal-content {
    background: var(--app-surface);
    border: 1px solid var(--app-border);
    border-radius: var(--radius-xl);
    color: var(--app-text);
  }
  #addRoutineModal .modal-header {
    background: rgba(255,255,255,0.03);
    border-bottom: 1px solid var(--app-border);
    padding: 18px 22px;
  }
  #addRoutineModal .modal-title {
    font-weight: 800;
    font-size: 1rem;
    color: var(--accent-green);
  }
  #addRoutineModal .modal-body  { padding: 22px; }
  #addRoutineModal .modal-footer {
    background: rgba(255,255,255,0.02);
    border-top: 1px solid var(--app-border);
    padding: 14px 22px;
  }
  #addRoutineModal .form-label {
    color: var(--app-muted);
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 6px;
  }
  #addRoutineModal .form-control,
  #addRoutineModal .form-select {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    color: var(--app-text);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 0.88rem;
    transition: var(--transition);
  }
  #addRoutineModal .form-control:focus,
  #addRoutineModal .form-select:focus {
    background: rgba(255,255,255,0.08);
    border-color: rgba(79,156,249,0.5);
    color: var(--app-text);
    box-shadow: 0 0 0 3px rgba(79,156,249,0.12);
    outline: none;
  }
  #addRoutineModal .form-control::placeholder { color: rgba(136,136,170,0.6); }
  #addRoutineModal .form-select option { background: #1a1a35; color: var(--app-text); }
  #addRoutineModal .btn-close { filter: invert(1) opacity(0.6); }

  .btn-modal-cancel {
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--app-border);
    color: var(--app-muted);
    border-radius: 10px;
    padding: 8px 20px;
    font-size: 0.85rem;
    font-weight: 600;
    transition: var(--transition);
  }
  .btn-modal-cancel:hover { background: rgba(255,255,255,0.1); color: var(--app-text); }
  .btn-modal-save {
    background: rgba(34,211,165,0.18);
    border: 1px solid rgba(34,211,165,0.4);
    color: #22d3a5;
    border-radius: 10px;
    padding: 8px 24px;
    font-size: 0.85rem;
    font-weight: 700;
    transition: var(--transition);
  }
  .btn-modal-save:hover { background: rgba(34,211,165,0.3); }

  /* MOBILE BOTTOM NAV */
  .mobile-nav { display: none; }
  @media (max-width: 767px) {
    .mobile-nav {
      display: flex !important;
      position: fixed;
      bottom: 0; left: 0; right: 0;
      background: #1a1a35;
      border-top: 1px solid rgba(255,255,255,0.08);
      padding: 6px 0 10px;
      z-index: 1050;
      align-items: stretch;
      justify-content: space-around;
    }
    .pb-mobile { padding-bottom: 80px; }
  }
  .mnav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    flex: 1;
    text-decoration: none;
    color: var(--app-muted);
    font-size: 0.62rem;
    font-weight: 600;
    padding: 4px 0;
    transition: var(--transition);
    min-width: 0;
  }
  .mnav-item i { font-size: 1.15rem; }
  .mnav-item:hover { color: var(--app-text); }
  .mnav-item.active { color: var(--accent-blue); }
  .mnav-fab {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    flex: 1;
    text-decoration: none;
    color: #22d3a5;
    font-size: 0.62rem;
    font-weight: 700;
    position: relative;
  }
  .mnav-fab .fab-circle {
    width: 46px; height: 46px;
    border-radius: 50%;
    background: linear-gradient(135deg, #22d3a5, #4f9cf9);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    color: #0f0f23;
    margin-top: -18px;
    box-shadow: 0 4px 16px rgba(34,211,165,0.4);
    transition: var(--transition);
  }
  .mnav-fab:hover .fab-circle { transform: scale(1.1); }

  /* Section label */
  .section-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--app-muted);
    margin-bottom: 14px;
  }
</style>

<!-- APP HEADER -->
<div class="app-header">
  <div class="container-lg px-3">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
      <div>
        <h1 style="font-size:1.5rem;font-weight:800;margin:0;color:var(--app-text)">&#128197; Daily Schedule</h1>
        <p style="font-size:0.82rem;color:#8888aa;margin:4px 0 0">Repeats every day automatically &middot; Resets at midnight</p>
      </div>
      <button class="btn" style="background:rgba(34,211,165,0.15);border:1px solid rgba(34,211,165,0.35);color:#22d3a5;border-radius:50px;padding:8px 22px;font-weight:700;font-size:0.85rem" data-bs-toggle="modal" data-bs-target="#addRoutineModal">
        <i class="fas fa-plus me-2"></i>Add Task
      </button>
    </div>

    <!-- Quick nav pills -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;padding:16px 0 20px">
      <a href="study-dashboard.php" class="nav-pill-link">&#128202; Dashboard</a>
      <a href="daily-routine.php"   class="nav-pill-link active">&#128197; Routine</a>
      <a href="study-time.php"      class="nav-pill-link">&#9201; Session</a>
      <a href="daily-target.php"    class="nav-pill-link">&#127919; Target</a>
      <a href="stories.php"         class="nav-pill-link">&#128218; Stories</a>
    </div>

  </div>
</div>

<!-- MAIN CONTENT -->
<div class="container-lg px-3 py-4 pb-mobile">

  <!-- Flash message -->
  <?php if ($msg): ?>
    <div class="flash-msg">
      <i class="fas fa-check-circle"></i>
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <!-- Stats row -->
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px">
    <div class="stats-pill">
      <span class="pill-dot" style="background:#4f9cf9"></span>
      <span style="color:var(--app-muted);font-weight:600">Total</span>
      <span style="color:var(--app-text)"><?= $total ?></span>
    </div>
    <div class="stats-pill">
      <span class="pill-dot" style="background:#22d3a5"></span>
      <span style="color:var(--app-muted);font-weight:600">Completed</span>
      <span style="color:#22d3a5"><?= $completed ?></span>
    </div>
    <div class="stats-pill">
      <span class="pill-dot" style="background:#f97316"></span>
      <span style="color:var(--app-muted);font-weight:600">Pending</span>
      <span style="color:#fb923c"><?= $pending ?></span>
    </div>
  </div>

  <!-- Section label -->
  <div class="section-label">&#128336; Today's Task List</div>

  <?php if (empty($routines)): ?>
    <!-- Empty state -->
    <div class="empty-state">
      <div class="empty-icon">&#128203;</div>
      <h5>No tasks in your daily schedule yet.</h5>
      <p>Add your fixed study times &mdash; they'll reset every day automatically!</p>
      <button class="btn-empty-add" data-bs-toggle="modal" data-bs-target="#addRoutineModal">
        <i class="fas fa-plus me-2"></i>Create a Task
      </button>
    </div>

  <?php else: ?>

    <?php
    $cat_emojis = [
      'Vocabulary'    => '&#128218;',
      'Grammar'       => '&#9998;',
      'English Story' => '&#128218;',
      'Reading'       => '&#128240;',
      'Practice'      => '&#9997;',
      'Mock Test'     => '&#128221;',
      'Revision'      => '&#128260;',
      'Other'         => '&#128204;',
    ];
    foreach ($routines as $r):
      $is_completed = $r['current_status'] === 'Completed';
      $priority_lc  = strtolower($r['priority']);
      $cat_emoji    = $cat_emojis[$r['category']] ?? '&#128204;';
      $start_fmt    = date('h:i A', strtotime($r['start_time']));
      $end_fmt      = date('h:i A', strtotime($r['end_time']));
    ?>
    <div class="task-card priority-<?= $priority_lc ?><?= $is_completed ? ' is-completed' : '' ?>">

      <!-- Top row: badges -->
      <div class="card-row">
        <span class="badge-dark-pill"><i class="fas fa-clock" style="font-size:0.65rem"></i>&nbsp;<?= $start_fmt ?> &ndash; <?= $end_fmt ?></span>
        <span class="badge-category"><?= $cat_emoji ?> <?= htmlspecialchars($r['category']) ?></span>
        <?php if ($r['priority'] === 'High'): ?>
          <span class="badge-priority-high">&#128308; High</span>
        <?php elseif ($r['priority'] === 'Medium'): ?>
          <span class="badge-priority-medium">&#128993; Medium</span>
        <?php else: ?>
          <span class="badge-priority-low">&#128309; Low</span>
        <?php endif; ?>
        <?php if ($is_completed): ?>
          <span class="badge-done"><i class="fas fa-check-circle"></i> Done &#10003;</span>
        <?php else: ?>
          <span class="badge-pending"><i class="fas fa-hourglass-half"></i> Pending</span>
        <?php endif; ?>
      </div>

      <!-- Task title -->
      <div class="task-title<?= $is_completed ? ' done' : '' ?>">
        <?= htmlspecialchars($r['task_title']) ?>
      </div>

      <!-- Notes -->
      <?php if (!empty($r['notes'])): ?>
        <div class="task-notes"><i class="fas fa-sticky-note me-1" style="font-size:0.7rem"></i><?= htmlspecialchars($r['notes']) ?></div>
      <?php else: ?>
        <div style="margin-bottom:12px"></div>
      <?php endif; ?>

      <!-- Action buttons -->
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <?php if (!$is_completed): ?>
          <form action="" method="POST" class="d-inline m-0 p-0">
            <input type="hidden" name="routine_id" value="<?= $r['id'] ?>">
            <button type="submit" name="mark_completed" class="btn-mark-done">
              <i class="fas fa-check"></i> Mark Done
            </button>
          </form>
        <?php endif; ?>
        <form action="" method="POST" class="d-inline m-0 p-0" onsubmit="return confirm('Delete this task from your master schedule?');">
          <input type="hidden" name="routine_id" value="<?= $r['id'] ?>">
          <button type="submit" name="delete_routine" class="btn-delete">
            <i class="fas fa-trash"></i> Delete
          </button>
        </form>
      </div>

    </div>
    <?php endforeach; ?>

  <?php endif; ?>

</div><!-- /container -->

<!-- ADD TASK MODAL (Dark) -->
<div class="modal fade" id="addRoutineModal" tabindex="-1" aria-labelledby="addRoutineModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form action="" method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addRoutineModalLabel"><i class="fas fa-plus-circle me-2"></i>Add Daily Task</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="routine_date" value="<?= date('Y-m-d') ?>">

        <div class="mb-3">
          <label class="form-label">Task Title</label>
          <input type="text" name="task_title" class="form-control" placeholder="e.g. Read Chapter 1" required>
        </div>

        <div class="row mb-3">
          <div class="col-6">
            <label class="form-label">Start Time</label>
            <input type="time" name="start_time" class="form-control" required>
          </div>
          <div class="col-6">
            <label class="form-label">End Time</label>
            <input type="time" name="end_time" class="form-control" required>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-6">
            <label class="form-label">Category</label>
            <select name="category" class="form-select" required>
              <option value="Vocabulary">Vocabulary</option>
              <option value="Grammar">Grammar</option>
              <option value="English Story">English Story</option>
              <option value="Reading">Reading</option>
              <option value="Practice">Practice</option>
              <option value="Mock Test">Mock Test</option>
              <option value="Revision">Revision</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-select" required>
              <option value="High">High</option>
              <option value="Medium" selected>Medium</option>
              <option value="Low">Low</option>
            </select>
          </div>
        </div>

        <div class="mb-1">
          <label class="form-label">Notes <span style="opacity:0.5;font-weight:400;text-transform:none">(Optional)</span></label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Any extra details..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_routine" class="btn-modal-save"><i class="fas fa-save me-1"></i>Save Task</button>
      </div>
    </form>
  </div>
</div>

<!-- MOBILE BOTTOM NAV -->
<nav class="mobile-nav" aria-label="Mobile navigation">
  <a href="study-dashboard.php" class="mnav-item">
    <i class="fas fa-chart-bar"></i>
    <span>Dashboard</span>
  </a>
  <a href="daily-routine.php" class="mnav-item active">
    <i class="fas fa-calendar-day"></i>
    <span>Routine</span>
  </a>
  <a href="study-time.php" class="mnav-fab">
    <div class="fab-circle"><i class="fas fa-stopwatch"></i></div>
    <span>Session</span>
  </a>
  <a href="daily-target.php" class="mnav-item">
    <i class="fas fa-bullseye"></i>
    <span>Target</span>
  </a>
  <a href="stories.php" class="mnav-item">
    <i class="fas fa-book-open"></i>
    <span>Stories</span>
  </a>
</nav>

<?php include 'includes/footer.php'; ?>
