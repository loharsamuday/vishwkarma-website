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
        $stmt2 = $pdo->prepare("SELECT s.id, s.title, s.chat_title, s.status, s.total_questions, s.questions_sent, s.timer_seconds, s.start_question, s.end_question FROM tg_quiz_sessions s WHERE s.admin_id=? AND s.status IN ('RUNNING','PAUSED','COUNTDOWN') ORDER BY s.updated_at DESC");
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
      <?php if ($session): ?>
      <a href="quiz_results.php?session_id=<?= (int)$session['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-trophy me-1"></i>Results</a>
      <?php endif; ?>
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
            <!-- Telegram Synced Progress Bar -->
            <div class="mt-2 text-center">
              <span class="badge bg-dark font-monospace px-3 py-2 fs-6 shadow-sm" id="asciiProgressBar">[░░░░░░░░░░░░░░░░░░░░] 0%</span>
            </div>
          </div>

          <!-- Synchronization & Announcement Info Bar -->
          <div class="d-flex flex-wrap justify-content-between align-items-center bg-light p-2 px-3 rounded-3 mb-4 border">
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-secondary" id="announcementBadge"><i class="fa-solid fa-bullhorn me-1"></i>Announcement: Not Sent</span>
              <span class="badge bg-info text-dark" id="syncStatusBadge"><i class="fa-solid fa-satellite-dish me-1"></i>Telegram Live Sync: Active</span>
            </div>
            <div class="small text-muted" id="lastTgSyncTime">
              <i class="fa-solid fa-clock-rotate-left me-1"></i>Last Synced: —
            </div>
          </div>

          <!-- Engine Mode Notice -->
          <div class="alert alert-success py-2 px-3 small d-flex align-items-center mb-4 rounded-3 shadow-sm border-0" id="engineAlertBox">
            <i class="fa-solid fa-server me-2 fs-5 text-success" id="engineIcon"></i>
            <div>
              <strong>Active Background Engine:</strong> <span id="engineStatusDesc">Server-side PHP Daemon is managing countdown, question dispatching, and Telegram syncing in the background.</span>
            </div>
          </div>

          <!-- Current Info Grid -->
          <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
              <div class="text-center bg-light rounded p-3 shadow-sm">
                <div class="fs-2 fw-bold text-primary" id="totalQ">—</div>
                <small class="text-muted fw-semibold">Total Questions</small>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="text-center bg-light rounded p-3 shadow-sm">
                <div class="fs-2 fw-bold text-success" id="sentQ">—</div>
                <small class="text-muted fw-semibold">Sent Questions</small>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="text-center bg-light rounded p-3 shadow-sm">
                <div class="fs-2 fw-bold text-danger" id="remainingQ">—</div>
                <small class="text-muted fw-semibold">Remaining Questions</small>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="text-center bg-light rounded p-3 shadow-sm">
                <div class="fs-2 fw-bold text-info" id="timerSet">—</div>
                <small class="text-muted fw-semibold">Timer per Q</small>
              </div>
            </div>
          </div>

          <!-- Timer Progress Ring & Countdown -->
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
                <small class="text-muted" id="timerRingLabel">sec left</small>
              </div>
            </div>
            <div class="small fw-semibold mt-2" id="timerSubtitle">Next question countdown: <span id="timerDisplay" class="fw-bold text-dark">—</span></div>
          </div>

          <!-- Telegram Live Message Sync & Error Box -->
          <div class="card border-0 bg-light p-3 mb-3 rounded-3 shadow-sm border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <small class="text-muted text-uppercase fw-bold d-block">Telegram Live Message Sync</small>
                <div id="tgStatusText" class="fw-bold text-success mt-1">
                  <i class="fa-solid fa-circle-check me-1"></i>Connected & Operational
                </div>
              </div>
              <span class="badge bg-white text-dark border px-2 py-1 small" id="tgMsgIdBadge">Msg ID: —</span>
            </div>
            <div class="mt-2 bg-white rounded p-2 border font-monospace small text-secondary" style="white-space: pre-wrap; max-height: 140px; overflow-y: auto;" id="tgLiveMessagePreview">Waiting for Telegram status sync...</div>
            <div id="errorBox" class="alert alert-danger py-2 mt-2 mb-0 d-none"></div>
          </div>

        </div>

        <!-- Control Buttons -->
        <div class="card-footer bg-transparent border-0 p-4">
          <div class="d-flex flex-wrap gap-2 justify-content-center">
            <button class="btn btn-outline-info btn-lg fw-bold px-3" id="btnAnnounce" onclick="sessionAction('announce')" title="Send pre-quiz upcoming alert to Telegram group">
              <i class="fa-solid fa-bullhorn me-1"></i>ANNOUNCE
            </button>
            <button class="btn btn-warning btn-lg fw-bold px-4" id="btnPause" onclick="sessionAction('pause')" disabled>
              <i class="fa-solid fa-pause me-2"></i>PAUSE
            </button>
            <button class="btn btn-success btn-lg fw-bold px-4" id="btnResume" onclick="sessionAction('resume')" disabled>
              <i class="fa-solid fa-play me-2"></i>RESUME
            </button>
            <button class="btn btn-primary btn-lg fw-bold px-4" id="btnDispatch" onclick="sessionAction('dispatch')" title="Send next question immediately without waiting for timer" disabled>
              <i class="fa-solid fa-forward-step me-2"></i>SEND NEXT NOW
            </button>
            <button class="btn btn-danger btn-lg fw-bold px-4" id="btnStop" onclick="sessionAction('stop')">
              <i class="fa-solid fa-stop me-2"></i>STOP
            </button>
            <button class="btn btn-primary btn-lg fw-bold px-4" id="btnPublishFinal" onclick="sessionAction('finalize')" style="display:none">
              <i class="fa-solid fa-paper-plane me-2"></i>PUBLISH RESULTS
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
            <span class="badge bg-<?= $s['status']==='RUNNING' ? 'success' : ($s['status']==='COUNTDOWN' ? 'info text-dark' : 'warning text-dark') ?>">
              <?= $s['status'] ?>
            </span>
          </div>
          <small class="text-muted d-block mb-2"><i class="fa-solid fa-users me-1"></i><?= htmlspecialchars($s['chat_title'] ?? '') ?></small>
          <div class="progress mb-2" style="height:8px">
            <?php $pct = $s['total_questions'] ? round($s['questions_sent']/$s['total_questions']*100) : 0; ?>
            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
          </div>
          <?php
            $startNum = (int)($s['start_question'] ?? 1);
            $endNum   = (int)($s['end_question'] ?? 0);
            $totNum   = (int)$s['total_questions'];
            $qRangeTxt = ($startNum > 1 || ($endNum > 0 && $endNum !== $totNum))
                ? "Q#{$startNum} - #{$endNum} ({$totNum} Qs)"
                : "{$totNum} Questions";
          ?>
          <small><?= $s['questions_sent'] ?> / <?= $totNum ?> Sent | <?= $qRangeTxt ?> | <?= $s['timer_seconds'] ?>s/Q</small>
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
let lastSentCount = -1;
let lastStatus = null;

const statusColors = {
  'COUNTDOWN': {bg:'bg-info text-dark',     text:'🚀 COUNTDOWN'},
  'RUNNING':   {bg:'bg-success',            text:'🟢 RUNNING'},
  'PAUSED':    {bg:'bg-warning text-dark',  text:'⏸ PAUSED'},
  'COMPLETED': {bg:'bg-primary',            text:'✅ COMPLETED'},
  'STOPPED':   {bg:'bg-danger',             text:'🔴 STOPPED'},
  'FAILED':    {bg:'bg-dark',               text:'❌ FAILED'},
  'PENDING':   {bg:'bg-secondary',          text:'⏳ PENDING'},
};

function updateUI(data) {
  const s = data.session;
  const pct  = data.progress_pct || 0;
  const isCountdown = s.status === 'COUNTDOWN';
  const isPaused = s.status === 'PAUSED';
  const isRunning = s.status === 'RUNNING';

  // Determine seconds to display on ring & display
  let secs = 0;
  let maxSecs = 15;
  if (isCountdown) {
    secs = data.countdown_remaining !== undefined ? data.countdown_remaining : (parseInt(s.countdown_seconds) || 30);
    maxSecs = parseInt(s.countdown_seconds) || 30;
  } else if (isRunning) {
    secs = data.seconds_remaining || 0;
    maxSecs = parseInt(s.timer_seconds) || 15;
  } else if (isPaused) {
    secs = data.seconds_remaining || (parseInt(s.remaining_time) || 0);
    maxSecs = parseInt(s.timer_seconds) || 15;
  }

  // Background engine status indicator
  const engineAlert = document.getElementById('engineAlertBox');
  const engineDesc  = document.getElementById('engineStatusDesc');
  const engineIcon  = document.getElementById('engineIcon');
  if (engineAlert && engineDesc) {
    if (data.daemon_active) {
      engineAlert.className = 'alert alert-success py-2 px-3 small d-flex align-items-center mb-4 rounded-3 shadow-sm border-0';
      engineIcon.className = 'fa-solid fa-server me-2 fs-5 text-success';
      engineDesc.innerHTML = '<strong>Server Daemon Active (XAMPP PHP):</strong> Quiz is executing autonomously in the background. It will continue running even if this tab is closed, minimized, or screen locked.';
    } else {
      engineAlert.className = 'alert alert-info py-2 px-3 small d-flex align-items-center mb-4 rounded-3 shadow-sm border-0';
      engineIcon.className = 'fa-solid fa-circle-nodes me-2 fs-5 text-primary';
      engineDesc.innerHTML = '<strong>Background Engine:</strong> Standby. Starts automatically when quiz begins.';
    }
  }

  // Status badge & card header
  const sc = statusColors[s.status] || {bg:'bg-secondary', text: s.status};
  document.getElementById('statusBadge').className = `badge fs-6 px-3 py-2 ${sc.bg}`;
  document.getElementById('statusBadge').textContent = sc.text;

  let headerClass = 'card-header py-3 d-flex justify-content-between align-items-center ';
  if (isCountdown) headerClass += 'bg-info text-dark';
  else if (isRunning) headerClass += 'bg-success text-white';
  else if (isPaused) headerClass += 'bg-warning text-dark';
  else if (s.status === 'COMPLETED') headerClass += 'bg-primary text-white';
  else headerClass += 'bg-secondary text-white';
  document.getElementById('statusHeader').className = headerClass;

  // Progress Bar & ASCII bar
  const progressBar = document.getElementById('progressBar');
  if (isCountdown) {
    progressBar.style.width = '100%';
    progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
    document.getElementById('progressText').textContent = `Countdown Active: Starting in ${secs}s`;
  } else {
    progressBar.style.width = pct + '%';
    progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-success';
    document.getElementById('progressText').textContent = `${s.questions_sent} / ${s.total_questions} (${pct}%)`;
  }

  if (document.getElementById('asciiProgressBar')) {
    document.getElementById('asciiProgressBar').textContent = data.progress_bar || '[░░░░░░░░░░░░░░░░░░░░] 0%';
  }

  // Pre-Quiz Announcement & Sync Badges
  const annBadge = document.getElementById('announcementBadge');
  if (annBadge) {
    if (s.announcement_sent_at) {
      const annTime = s.announcement_sent_at.includes(' ') ? s.announcement_sent_at.split(' ')[1] : s.announcement_sent_at;
      annBadge.className = 'badge bg-success';
      annBadge.innerHTML = `<i class="fa-solid fa-bullhorn me-1"></i>Announcement: Sent (${annTime})`;
    } else {
      annBadge.className = 'badge bg-secondary';
      annBadge.innerHTML = '<i class="fa-solid fa-bullhorn me-1"></i>Announcement: Not Sent';
    }
  }

  const syncBadge = document.getElementById('syncStatusBadge');
  if (syncBadge) {
    syncBadge.className = ['COUNTDOWN','RUNNING','PAUSED'].includes(s.status) ? 'badge bg-info text-dark' : 'badge bg-secondary';
    syncBadge.innerHTML = ['COUNTDOWN','RUNNING','PAUSED'].includes(s.status)
      ? '<i class="fa-solid fa-satellite-dish me-1"></i>Telegram Live Sync: Active'
      : '<i class="fa-solid fa-check me-1"></i>Telegram Sync: Idle';
  }

  const lastTgSync = document.getElementById('lastTgSyncTime');
  if (lastTgSync) {
    lastTgSync.innerHTML = s.last_telegram_update
      ? `<i class="fa-solid fa-clock-rotate-left me-1"></i>Last Synced: ${s.last_telegram_update}`
      : '<i class="fa-solid fa-clock-rotate-left me-1"></i>Last Synced: —';
  }

  // Numbers
  const sent = parseInt(s.questions_sent) || 0;
  const total = parseInt(s.total_questions) || 0;
  const remaining = Math.max(0, total - sent);

  document.getElementById('totalQ').textContent       = total;
  document.getElementById('sentQ').textContent        = sent;
  document.getElementById('remainingQ').textContent   = remaining;
  document.getElementById('timerSet').textContent     = (s.timer_seconds || 15) + 's';
  document.getElementById('timerDisplay').textContent = secs + 's';
  document.getElementById('timerRingText').textContent = secs;

  // Ring progress & subtitles
  const ringLabel = document.getElementById('timerRingLabel');
  const ringSubtitle = document.getElementById('timerSubtitle');
  const timerRing = document.getElementById('timerRing');

  if (isCountdown) {
    if (ringLabel) ringLabel.textContent = 'starts in';
    if (ringSubtitle) ringSubtitle.innerHTML = `🚀 <span class="text-primary fw-bold">Live Pre-Quiz Countdown</span> synced with Telegram!`;
    timerRing.style.stroke = '#0dcaf0';
  } else if (isPaused) {
    if (ringLabel) ringLabel.textContent = 'paused';
    if (ringSubtitle) ringSubtitle.innerHTML = `⏸ <span class="text-warning fw-bold">Quiz Paused</span> — Telegram status updated to PAUSED`;
    timerRing.style.stroke = '#ffc107';
  } else {
    if (ringLabel) ringLabel.textContent = 'sec left';
    if (ringSubtitle) ringSubtitle.innerHTML = `Next question countdown: <span id="timerDisplay" class="fw-bold text-dark">${secs}s</span>`;
    timerRing.style.stroke = secs > 5 ? '#28a745' : '#dc3545';
  }

  const circumference = 2 * Math.PI * 65; // ~408
  const safeMax = Math.max(1, maxSecs);
  const dashOffset = circumference * (1 - (Math.min(secs, safeMax) / safeMax));
  timerRing.style.strokeDashoffset = Math.max(0, dashOffset);

  // Telegram status, message ID, & preview
  const tgStatus = document.getElementById('tgStatusText');
  const errorBox = document.getElementById('errorBox');
  const tgMsgIdBadge = document.getElementById('tgMsgIdBadge');
  const previewBox = document.getElementById('tgLiveMessagePreview');

  if (tgMsgIdBadge) {
    if (s.telegram_status_message_id) {
      tgMsgIdBadge.className = 'badge bg-primary text-white border px-2 py-1 small';
      tgMsgIdBadge.innerHTML = `<i class="fa-solid fa-message me-1"></i>Msg ID: #${s.telegram_status_message_id}`;
    } else {
      tgMsgIdBadge.className = 'badge bg-light text-muted border px-2 py-1 small';
      tgMsgIdBadge.textContent = 'Msg ID: Pending';
    }
  }

  if (previewBox && s.last_telegram_status_text) {
    previewBox.textContent = s.last_telegram_status_text;
  }

  if (s.error_message) {
    tgStatus.className = 'fw-bold text-danger mt-1';
    tgStatus.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i>Telegram API Error Reported';
    errorBox.textContent = 'Last Telegram API Error: ' + s.error_message;
    errorBox.classList.remove('d-none');
  } else {
    tgStatus.className = 'fw-bold text-success mt-1';
    tgStatus.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i>Connected & Operational (Polls & Status Syncing)';
    errorBox.classList.add('d-none');
  }

  // Buttons
  const btnAnnounce = document.getElementById('btnAnnounce');
  if (btnAnnounce) {
    btnAnnounce.disabled = ['COMPLETED','STOPPED','FAILED'].includes(s.status);
    if (s.announcement_sent_at) {
      btnAnnounce.innerHTML = '<i class="fa-solid fa-bullhorn me-1"></i>RE-ANNOUNCE';
      btnAnnounce.className = 'btn btn-outline-secondary btn-lg fw-bold px-3';
    } else {
      btnAnnounce.innerHTML = '<i class="fa-solid fa-bullhorn me-1"></i>ANNOUNCE';
      btnAnnounce.className = 'btn btn-outline-info btn-lg fw-bold px-3';
    }
  }

  document.getElementById('btnPause').disabled  = s.status !== 'RUNNING';
  document.getElementById('btnResume').disabled = s.status !== 'PAUSED';
  if (document.getElementById('btnDispatch')) {
    // If in COUNTDOWN, admin can click to skip countdown and send first question immediately
    const startQ = parseInt(s.start_question) || 1;
    document.getElementById('btnDispatch').disabled = (!['RUNNING','COUNTDOWN'].includes(s.status) || remaining <= 0);
    document.getElementById('btnDispatch').innerHTML = isCountdown
      ? `<i class="fa-solid fa-bolt me-2"></i>SKIP TO QUESTION #${startQ}`
      : '<i class="fa-solid fa-forward-step me-2"></i>SEND NEXT NOW';
  }
  document.getElementById('btnStop').style.display   = ['COUNTDOWN','RUNNING','PAUSED'].includes(s.status) ? '' : 'none';
  document.getElementById('btnRestart').style.display = ['COMPLETED','STOPPED','FAILED'].includes(s.status) ? '' : 'none';

  const btnPublish = document.getElementById('btnPublishFinal');
  if (btnPublish) {
    btnPublish.style.display = (s.status === 'COMPLETED' && !s.results_announced_at) ? '' : 'none';
  }

  // Completion alert
  if (s.status === 'COMPLETED') {
    const actionBox = document.getElementById('actionResult');
    if (s.results_announced_at) {
      actionBox.innerHTML = `<div class="alert alert-success py-2 text-center fw-bold shadow-sm mb-0"><i class="fa-solid fa-trophy text-warning me-2 fs-5"></i>Quiz Finished! Results published to Telegram on ${s.results_announced_at}. <a href="quiz_results.php?session_id=${SESSION_ID}" class="btn btn-sm btn-success ms-2">View Scores</a></div>`;
    } else {
      actionBox.innerHTML = `<div class="alert alert-warning py-2 text-center fw-bold shadow-sm mb-0"><i class="fa-solid fa-clock me-2"></i>Quiz Finished! Finalizing and publishing results to Telegram... <button class="btn btn-sm btn-primary ms-2" onclick="sessionAction('finalize')"><i class="fa-solid fa-paper-plane me-1"></i>Publish Now</button></div>`;
    }
  }

  // Stop polling when done and announced
  if ((s.status === 'COMPLETED' && s.results_announced_at) || ['STOPPED','FAILED'].includes(s.status)) {
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
    if (res.success) {
      updateUI(res);
      const startQ = parseInt(res.session.start_question) || 1;
      const curSent = parseInt(res.session.questions_sent) || 0;
      if (lastSentCount !== -1 && curSent > lastSentCount) {
        const curQNum = (startQ > 1) ? (startQ + curSent - 1) : curSent;
        addLog(`🎯 Question #${curQNum} dispatched to Telegram group!`, 'success');
      }
      lastSentCount = curSent;

      if (lastStatus === 'COUNTDOWN' && res.session.status === 'RUNNING') {
        addLog(`🟢 Countdown ended! Quiz has started and Question #${startQ} is live!`, 'success');
      }
      lastStatus = res.session.status;

      if (res.finalization && res.finalization.completed && !res.finalization.already_announced) {
        addLog(`🏆 Quiz finished! Final results successfully published to Telegram!`, 'success');
      }
    }
  } catch(e) { /* network error, keep trying */ }
}

async function sessionAction(action) {
  const confirmMsgs = {
    stop:     'Stop the quiz permanently?',
    restart:  'Restart from the beginning?',
    finalize: 'Publish final results to Telegram group now?',
    announce: 'Send Pre-Quiz Announcement alert to the Telegram group now?',
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

// Immediately re-sync when tab becomes visible, window restored from minimize, or screen unlocked
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) {
    pollStatus();
  }
});
window.addEventListener('focus', () => {
  pollStatus();
});

// Clean up on page leave
window.addEventListener('beforeunload', () => clearInterval(pollInterval));
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
