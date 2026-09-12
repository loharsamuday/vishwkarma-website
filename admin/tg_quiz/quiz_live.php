<?php
// admin/tg_quiz/quiz_live.php
// Live Quiz Monitor — Real-time status, control buttons

$page_title = "Live Quiz Monitor";
require_once '../../includes/db.php';
require_once '../../includes/session.php';

if (!isset($_SESSION['admin_id'])) { header("Location: ../index.php"); exit; }

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$session_id = (int)($_GET['session_id'] ?? 0);
$session = null;
if ($session_id) {
    try {
        $stmt = $pdo->prepare("SELECT s.*, qs.title as quiz_set_title FROM tg_quiz_sessions s JOIN tg_quiz_sets qs ON qs.id=s.quiz_set_id WHERE s.id=? AND s.admin_id=?");
        $stmt->execute([$session_id, $_SESSION['admin_id']]);
        $session = $stmt->fetch();
    } catch (PDOException $e) {}
}

// Fetch all active sessions if no specific session
$active_sessions = [];
if (!$session) {
    try {
        $stmt2 = $pdo->prepare("SELECT s.id, s.title, s.chat_title, s.status, s.total_questions, s.questions_sent, s.timer_seconds FROM tg_quiz_sessions s WHERE s.admin_id=? AND s.status IN ('RUNNING','PAUSED') ORDER BY s.updated_at DESC");
        $stmt2->execute([$_SESSION['admin_id']]);
        $active_sessions = $stmt2->fetchAll();
    } catch (PDOException $e) {}
}

require_once '../includes/header.php';
?>

<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
    <div>
      <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
      <h4 class="mb-0 d-inline">
        <i class="fa-solid fa-satellite-dish fa-fade text-success me-2"></i>Live Quiz Monitor
      </h4>
    </div>
    <div class="d-flex gap-2">
      <a href="quiz_sets.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-layer-group me-1"></i>Quiz Sets</a>
      <a href="quiz_history.php" class="btn btn-outline-dark btn-sm"><i class="fa-solid fa-clock-rotate-left me-1"></i>History</a>
    </div>
  </div>

  <?php if ($session): ?>
  <!-- Single Session Monitor -->
  <div class="row justify-content-center">
    <div class="col-lg-8">

      <!-- Status Card -->
      <div class="card border-0 shadow mb-4" id="statusCard">
        <div class="card-header py-3 d-flex justify-content-between align-items-center" id="statusHeader">
          <div>
            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($session['title'] ?? $session['quiz_set_title']) ?></h5>
            <small class="opacity-75"><i class="fa-solid fa-users me-1"></i><?= htmlspecialchars($session['chat_title'] ?? $session['chat_id']) ?></small>
          </div>
          <span class="badge fs-6 px-3 py-2" id="statusBadge">—</span>
        </div>

        <div class="card-body p-4">
          <!-- Progress -->
          <div class="mb-4">
            <div class="d-flex justify-content-between mb-2">
              <span class="fw-bold">Progress</span>
              <span class="fw-bold" id="progressText">—</span>
            </div>
            <div class="progress" style="height: 20px; border-radius: 10px;">
              <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                   id="progressBar" role="progressbar" style="width: 0%"></div>
            </div>
          </div>

          <!-- Current Info Grid -->
          <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
              <div class="text-center bg-light rounded p-3">
                <div class="fs-2 fw-bold text-primary" id="currentQ">—</div>
                <small class="text-muted">Current Question</small>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="text-center bg-light rounded p-3">
                <div class="fs-2 fw-bold text-success" id="totalQ">—</div>
                <small class="text-muted">Total Questions</small>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="text-center bg-light rounded p-3">
                <div class="fs-2 fw-bold text-warning" id="timerDisplay">—</div>
                <small class="text-muted">Seconds Until Next</small>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="text-center bg-light rounded p-3">
                <div class="fs-2 fw-bold text-info" id="timerSet">—</div>
                <small class="text-muted">Timer per Q</small>
              </div>
            </div>
          </div>

          <!-- Timer Progress Ring -->
          <div class="text-center mb-4">
            <div class="position-relative d-inline-block">
              <svg width="150" height="150" viewBox="0 0 150 150">
                <circle cx="75" cy="75" r="65" fill="none" stroke="#e9ecef" stroke-width="12"/>
                <circle cx="75" cy="75" r="65" fill="none" stroke="#28a745" stroke-width="12"
                        stroke-linecap="round" stroke-dasharray="408" stroke-dashoffset="408"
                        id="timerRing" transform="rotate(-90 75 75)"/>
              </svg>
              <div class="position-absolute top-50 start-50 translate-middle text-center">
                <div class="fs-1 fw-bold" id="timerRingText">—</div>
                <small class="text-muted">sec</small>
              </div>
            </div>
          </div>

          <!-- Error display -->
          <div id="errorBox" class="alert alert-warning d-none py-2 mb-3"></div>

        </div>

        <!-- Control Buttons -->
        <div class="card-footer bg-transparent border-0 p-4">
          <div class="d-flex flex-wrap gap-2 justify-content-center">
            <button class="btn btn-warning btn-lg fw-bold px-4" id="btnPause" onclick="sessionAction('pause')" disabled>
              <i class="fa-solid fa-pause me-2"></i>PAUSE
            </button>
            <button class="btn btn-success btn-lg fw-bold px-4" id="btnResume" onclick="sessionAction('resume')" disabled>
              <i class="fa-solid fa-play me-2"></i>RESUME
            </button>
            <button class="btn btn-danger btn-lg fw-bold px-4" id="btnStop" onclick="sessionAction('stop')">
              <i class="fa-solid fa-stop me-2"></i>STOP
            </button>
            <button class="btn btn-outline-secondary btn-lg fw-bold px-4" id="btnRestart" onclick="sessionAction('restart')" style="display:none">
              <i class="fa-solid fa-rotate-right me-2"></i>RESTART
            </button>
          </div>
          <div id="actionResult" class="mt-3"></div>
        </div>
      </div>

      <!-- Recent Logs -->
      <div class="card border-0 shadow-sm">
        <div class="card-header fw-bold bg-dark text-white py-2">
          <i class="fa-solid fa-list me-2"></i>Recent Activity
        </div>
        <div class="card-body p-0">
          <div id="logsContainer" style="max-height: 250px; overflow-y: auto;">
            <div class="text-center py-3 text-muted small">Loading logs...</div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <?php else: ?>

  <!-- Active Sessions List -->
  <?php if (empty($active_sessions)): ?>
  <div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
      <i class="fa-solid fa-circle-play fa-4x text-muted mb-3"></i>
      <h5 class="text-muted">No Active Quiz Sessions</h5>
      <p class="text-muted">Start a quiz from the Quiz Settings page.</p>
      <a href="quiz_settings.php" class="btn btn-warning fw-bold px-4">
        <i class="fa-solid fa-play me-2"></i>Start a Quiz
      </a>
    </div>
  </div>
  <?php else: ?>
  <div class="row g-3">
    <?php foreach ($active_sessions as $s): ?>
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between mb-2">
            <h6 class="fw-bold"><?= htmlspecialchars($s['title'] ?? 'Quiz') ?></h6>
            <span class="badge bg-<?= $s['status']==='RUNNING' ? 'success' : 'warning' ?>">
              <?= $s['status'] ?>
            </span>
          </div>
          <small class="text-muted d-block mb-2"><i class="fa-solid fa-users me-1"></i><?= htmlspecialchars($s['chat_title'] ?? '') ?></small>
          <div class="progress mb-2" style="height:8px">
            <?php $pct = $s['total_questions'] ? round($s['questions_sent']/$s['total_questions']*100) : 0; ?>
            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
          </div>
          <small><?= $s['questions_sent'] ?> / <?= $s['total_questions'] ?> Questions | <?= $s['timer_seconds'] ?>s/Q</small>
        </div>
        <div class="card-footer bg-transparent border-0">
          <a href="quiz_live.php?session_id=<?= $s['id'] ?>" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-satellite-dish me-1"></i>Monitor
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div>

<?php if ($session): ?>
<script>
const CSRF      = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
const API       = '<?= BASE_URL ?>api/tg_quiz/session.php';
const SESSION_ID = <?= $session_id ?>;
const TOTAL_Q   = <?= (int)$session['total_questions'] ?>;
let pollInterval;
let logs = [];

const statusColors = {
  'RUNNING':   {bg:'bg-success',    text:'🟢 RUNNING'},
  'PAUSED':    {bg:'bg-warning',    text:'⏸ PAUSED'},
  'COMPLETED': {bg:'bg-primary',    text:'✅ COMPLETED'},
  'STOPPED':   {bg:'bg-danger',     text:'🔴 STOPPED'},
  'FAILED':    {bg:'bg-dark',       text:'❌ FAILED'},
  'PENDING':   {bg:'bg-secondary',  text:'⏳ PENDING'},
};

function updateUI(data) {
  const s = data.session;
  const secs = data.seconds_remaining || 0;
  const pct  = data.progress_pct || 0;

  // Status badge & card header
  const sc = statusColors[s.status] || {bg:'bg-secondary', text: s.status};
  document.getElementById('statusBadge').className = `badge fs-6 px-3 py-2 ${sc.bg}`;
  document.getElementById('statusBadge').textContent = sc.text;
  document.getElementById('statusHeader').className = `card-header py-3 d-flex justify-content-between align-items-center ${s.status==='RUNNING' ? 'bg-success text-white' : s.status==='PAUSED' ? 'bg-warning' : 'bg-secondary text-white'}`;

  // Progress
  document.getElementById('progressBar').style.width = pct + '%';
  document.getElementById('progressText').textContent = `${s.questions_sent} / ${s.total_questions} (${pct}%)`;

  // Numbers
  document.getElementById('currentQ').textContent = s.questions_sent > 0 ? s.questions_sent : (s.current_question || 0);
  document.getElementById('totalQ').textContent   = s.total_questions;
  document.getElementById('timerSet').textContent = s.timer_seconds + 's';
  document.getElementById('timerDisplay').textContent = secs + 's';
  document.getElementById('timerRingText').textContent = secs;

  // Ring progress
  const maxSecs = s.timer_seconds || 15;
  const circumference = 2 * Math.PI * 65; // ~408
  const dashOffset = circumference * (1 - (secs / maxSecs));
  document.getElementById('timerRing').style.strokeDashoffset = Math.max(0, dashOffset);
  document.getElementById('timerRing').style.stroke = secs > 5 ? '#28a745' : '#dc3545';

  // Error
  if (s.error_message) {
    document.getElementById('errorBox').textContent = '⚠ ' + s.error_message;
    document.getElementById('errorBox').classList.remove('d-none');
  }

  // Buttons
  document.getElementById('btnPause').disabled  = s.status !== 'RUNNING';
  document.getElementById('btnResume').disabled = s.status !== 'PAUSED';
  document.getElementById('btnStop').style.display   = ['RUNNING','PAUSED'].includes(s.status) ? '' : 'none';
  document.getElementById('btnRestart').style.display = ['COMPLETED','STOPPED','FAILED'].includes(s.status) ? '' : 'none';

  // Stop polling when done
  if (['COMPLETED','STOPPED','FAILED'].includes(s.status)) {
    clearInterval(pollInterval);
  }
}

function addLog(msg, type='info') {
  const now = new Date().toLocaleTimeString();
  logs.unshift({msg, type, time: now});
  if (logs.length > 50) logs.pop();
  const html = logs.map(l =>
    `<div class="px-3 py-1 border-bottom small text-${l.type==='error'?'danger':l.type==='success'?'success':'muted'}">
      <span class="text-muted me-2">${l.time}</span>${l.msg}
    </div>`
  ).join('');
  document.getElementById('logsContainer').innerHTML = html;
}

async function pollStatus() {
  try {
    const res = await fetch(`${API}?action=status&session_id=${SESSION_ID}`).then(r=>r.json());
    if (res.success) updateUI(res);
  } catch(e) { /* network error, keep trying */ }
}

async function sessionAction(action) {
  const confirmMsgs = {
    stop:    'Stop the quiz permanently?',
    restart: 'Restart from the beginning?',
  };
  if (confirmMsgs[action] && !confirm(confirmMsgs[action])) return;

  const fd = new FormData();
  fd.append('action', action); fd.append('csrf_token', CSRF); fd.append('session_id', SESSION_ID);
  const res = await fetch(API, {method:'POST', body:fd}).then(r=>r.json());
  document.getElementById('actionResult').innerHTML = `<div class="alert alert-${res.success?'success':'danger'} py-2 text-center">${res.message}</div>`;
  addLog(res.message, res.success ? 'success' : 'error');
  if (res.success) pollStatus();
}

// Start polling every 3 seconds
document.addEventListener('DOMContentLoaded', () => {
  pollStatus();
  pollInterval = setInterval(pollStatus, 3000);
  addLog('Monitor started — polling every 3s', 'info');
});

// Clean up on page leave
window.addEventListener('beforeunload', () => clearInterval(pollInterval));
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
