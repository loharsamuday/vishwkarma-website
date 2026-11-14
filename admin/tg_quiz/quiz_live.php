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

<style>
  .quiz-monitor-page {
    --monitor-ink: #172033;
    --monitor-muted: #718096;
    --monitor-line: #e7ebf3;
    --monitor-surface: #fff;
    --monitor-shadow: 0 18px 48px rgba(29, 42, 68, .09);
    padding: clamp(16px, 2.4vw, 36px);
    background:
      radial-gradient(circle at 85% 0%, rgba(99, 102, 241, .09), transparent 25rem),
      linear-gradient(145deg, #f7f9fd, #eef2f8);
    color: var(--monitor-ink);
  }

  .quiz-monitor-page .monitor-topbar {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    overflow: hidden;
    margin-bottom: 26px;
    padding: clamp(20px, 3vw, 34px);
    border: 1px solid rgba(255, 255, 255, .16);
    border-radius: 24px;
    background: linear-gradient(118deg, #111827 0%, #1e293b 58%, #3730a3 100%);
    box-shadow: 0 20px 46px rgba(23, 32, 51, .2);
    color: #fff;
  }

  .quiz-monitor-page .monitor-topbar::after {
    position: absolute;
    top: -110px;
    right: 12%;
    width: 280px;
    height: 280px;
    border: 1px solid rgba(255, 255, 255, .1);
    border-radius: 50%;
    box-shadow: 0 0 0 34px rgba(255, 255, 255, .035), 0 0 0 72px rgba(255, 255, 255, .025);
    content: "";
    pointer-events: none;
  }

  .quiz-monitor-page .monitor-title-group,
  .quiz-monitor-page .monitor-nav,
  .quiz-monitor-page .monitor-heading-copy {
    position: relative;
    z-index: 1;
  }

  .quiz-monitor-page .monitor-title-group {
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 0;
  }

  .quiz-monitor-page .monitor-brand-icon {
    display: grid;
    flex: 0 0 58px;
    width: 58px;
    height: 58px;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, .24);
    border-radius: 18px;
    background: rgba(255, 255, 255, .12);
    color: #a5f3fc;
    font-size: 24px;
    backdrop-filter: blur(12px);
  }

  .quiz-monitor-page .monitor-kicker {
    margin: 0 0 5px;
    color: #a5b4fc;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .16em;
    text-transform: uppercase;
  }

  .quiz-monitor-page .monitor-title {
    margin: 0;
    color: #fff;
    font-size: clamp(1.25rem, 2vw, 1.75rem);
    font-weight: 800;
    letter-spacing: -.035em;
  }

  .quiz-monitor-page .monitor-subtitle {
    margin: 5px 0 0;
    color: #cbd5e1;
    font-size: .88rem;
  }

  .quiz-monitor-page .monitor-nav {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 9px;
    flex-wrap: wrap;
  }

  .quiz-monitor-page .monitor-nav .btn {
    border-color: rgba(255, 255, 255, .2);
    border-radius: 11px;
    background: rgba(255, 255, 255, .09);
    color: #fff;
    font-size: .82rem;
    font-weight: 700;
    backdrop-filter: blur(10px);
    transition: background .2s ease, transform .2s ease;
  }

  .quiz-monitor-page .monitor-nav .btn:hover {
    transform: translateY(-2px);
    background: rgba(255, 255, 255, .18);
  }

  .quiz-monitor-page .monitor-connection {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-right: 4px;
    padding: 8px 11px;
    border: 1px solid rgba(255, 255, 255, .15);
    border-radius: 999px;
    background: rgba(15, 23, 42, .3);
    color: #e2e8f0;
    font-size: .76rem;
    font-weight: 700;
    white-space: nowrap;
  }

  .quiz-monitor-page .connection-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #fbbf24;
    box-shadow: 0 0 0 4px rgba(251, 191, 36, .14);
  }

  .quiz-monitor-page .monitor-connection.is-online .connection-dot {
    background: #34d399;
    box-shadow: 0 0 0 4px rgba(52, 211, 153, .15);
  }

  .quiz-monitor-page .monitor-connection.is-offline .connection-dot {
    background: #fb7185;
    box-shadow: 0 0 0 4px rgba(251, 113, 133, .15);
  }

  .quiz-monitor-page .monitor-layout {
    max-width: 1440px;
    margin: 0 auto;
  }

  .quiz-monitor-page #statusCard,
  .quiz-monitor-page .monitor-activity-card,
  .quiz-monitor-page .monitor-session-card,
  .quiz-monitor-page .monitor-empty-state {
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, .9) !important;
    border-radius: 22px;
    background: var(--monitor-surface);
    box-shadow: var(--monitor-shadow) !important;
  }

  .quiz-monitor-page #statusHeader {
    min-height: 104px;
    padding: 23px 26px !important;
    border: 0;
    background: linear-gradient(115deg, #0f766e, #059669) !important;
    color: #fff !important;
  }

  .quiz-monitor-page #statusHeader.bg-info {
    background: linear-gradient(115deg, #0e7490, #06b6d4) !important;
    color: #fff !important;
  }

  .quiz-monitor-page #statusHeader.bg-warning {
    background: linear-gradient(115deg, #b45309, #f59e0b) !important;
    color: #fff !important;
  }

  .quiz-monitor-page #statusHeader.bg-primary {
    background: linear-gradient(115deg, #4338ca, #6366f1) !important;
    color: #fff !important;
  }

  .quiz-monitor-page #statusHeader.bg-secondary {
    background: linear-gradient(115deg, #334155, #64748b) !important;
    color: #fff !important;
  }

  .quiz-monitor-page #statusHeader h5 {
    color: inherit;
    font-size: 1.1rem;
    letter-spacing: -.02em;
  }

  .quiz-monitor-page #statusHeader small {
    display: inline-block;
    margin-top: 7px;
    color: inherit;
    opacity: .8;
  }

  .quiz-monitor-page #headerRangeBadge {
    border: 1px solid rgba(255, 255, 255, .38) !important;
    background: rgba(255, 255, 255, .15) !important;
    color: #fff !important;
    font-size: .72rem;
  }

  .quiz-monitor-page #statusBadge {
    border: 1px solid rgba(255, 255, 255, .28);
    border-radius: 999px;
    background: rgba(255, 255, 255, .16);
    color: #fff;
    font-size: .75rem !important;
    letter-spacing: .035em;
    white-space: nowrap;
  }

  .quiz-monitor-page #statusCard > .card-body {
    padding: clamp(18px, 3vw, 30px) !important;
  }

  .quiz-monitor-page #progressText {
    color: #334155;
    font-size: .88rem;
  }

  .quiz-monitor-page .progress {
    overflow: hidden;
    height: 13px !important;
    border-radius: 999px !important;
    background: #e9eef6;
  }

  .quiz-monitor-page .progress-bar {
    border-radius: 999px;
    transition: width .65s cubic-bezier(.22, 1, .36, 1);
  }

  .quiz-monitor-page #asciiProgressBar {
    border-radius: 999px;
    background: #172033 !important;
    font-size: .72rem !important;
    letter-spacing: .05em;
  }

  .quiz-monitor-page .monitor-stat-card {
    position: relative;
    height: 100%;
    overflow: hidden;
    padding: 18px 15px !important;
    border: 1px solid var(--monitor-line);
    border-radius: 17px;
    background: linear-gradient(145deg, #fff, #f8faff) !important;
    box-shadow: 0 7px 18px rgba(29, 42, 68, .045) !important;
    transition: transform .2s ease, box-shadow .2s ease;
  }

  .quiz-monitor-page .monitor-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px rgba(29, 42, 68, .09) !important;
  }

  .quiz-monitor-page .monitor-stat-card .fs-2 {
    margin-bottom: 3px;
    font-size: clamp(1.55rem, 2.7vw, 2rem) !important;
    letter-spacing: -.05em;
  }

  .quiz-monitor-page .monitor-stat-card small {
    color: var(--monitor-muted) !important;
    font-size: .72rem;
    letter-spacing: .045em;
    text-transform: uppercase;
  }

  .quiz-monitor-page .monitor-timer-stage {
    width: min(100%, 290px);
    margin: 0 auto;
    padding: 16px;
    border: 1px solid #e8ebf5;
    border-radius: 24px;
    background:
      radial-gradient(circle, #fff 0%, #f8faff 55%, #eef2ff 100%);
  }

  .quiz-monitor-page .monitor-timer-stage svg {
    width: min(100%, 190px);
    height: auto;
    filter: drop-shadow(0 10px 12px rgba(30, 41, 59, .08));
  }

  .quiz-monitor-page #timerRingText {
    color: #172033;
    font-variant-numeric: tabular-nums;
    letter-spacing: -.06em;
  }

  .quiz-monitor-page #timerRingLabel {
    color: var(--monitor-muted) !important;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
  }

  .quiz-monitor-page #timerSubtitle {
    margin-top: 10px !important;
    color: var(--monitor-muted);
    font-size: .83rem;
  }

  .quiz-monitor-page .monitor-sync-strip {
    gap: 12px;
    padding: 12px 14px !important;
    border: 1px solid var(--monitor-line) !important;
    border-radius: 15px !important;
    background: #f8faff !important;
  }

  .quiz-monitor-page .monitor-sync-strip .badge {
    padding: 7px 10px;
    border-radius: 999px;
    font-size: .7rem;
  }

  .quiz-monitor-page #engineAlertBox {
    border: 1px solid #f4dca6 !important;
    border-left: 4px solid #f59e0b !important;
    border-radius: 14px !important;
    background: #fffbeb !important;
    color: #744210;
    line-height: 1.55;
  }

  .quiz-monitor-page .monitor-telegram-card {
    border: 1px solid var(--monitor-line) !important;
    border-radius: 17px !important;
    background: #f8faff !important;
  }

  .quiz-monitor-page #tgStatusText {
    font-size: .86rem;
  }

  .quiz-monitor-page #tgLiveMessagePreview {
    border-color: #e4e9f2 !important;
    border-radius: 11px !important;
    color: #475569 !important;
    line-height: 1.55;
  }

  .quiz-monitor-page #errorBox {
    border-radius: 11px;
    font-size: .82rem;
  }

  .quiz-monitor-page .monitor-controls {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  .quiz-monitor-page .monitor-controls .btn {
    min-height: 45px;
    border-radius: 12px;
    font-size: .78rem;
    letter-spacing: .025em;
    box-shadow: 0 5px 12px rgba(29, 42, 68, .08);
    transition: transform .18s ease, box-shadow .18s ease;
  }

  .quiz-monitor-page .monitor-controls .btn:not(:disabled):hover {
    transform: translateY(-2px);
    box-shadow: 0 9px 18px rgba(29, 42, 68, .14);
  }

  .quiz-monitor-page .monitor-controls .btn:disabled {
    cursor: not-allowed;
    opacity: .46;
    box-shadow: none;
  }

  .quiz-monitor-page #actionResult .alert {
    margin-bottom: 0;
    border-radius: 12px;
  }

  .quiz-monitor-page .monitor-activity-card .card-header {
    padding: 16px 20px !important;
    border: 0;
    background: #172033 !important;
    letter-spacing: .015em;
  }

  .quiz-monitor-page #logsContainer {
    max-height: 290px !important;
  }

  .quiz-monitor-page #logsContainer > div {
    padding: 11px 18px !important;
    border-color: #edf0f5 !important;
  }

  .quiz-monitor-page .monitor-session-card {
    height: 100%;
    transition: transform .2s ease, box-shadow .2s ease;
  }

  .quiz-monitor-page .monitor-session-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 22px 50px rgba(29, 42, 68, .13) !important;
  }

  .quiz-monitor-page .monitor-empty-state {
    padding: 22px;
  }

  @media (max-width: 767.98px) {
    .quiz-monitor-page .monitor-topbar {
      align-items: flex-start;
      flex-direction: column;
      gap: 18px;
      border-radius: 19px;
    }

    .quiz-monitor-page .monitor-nav {
      justify-content: flex-start;
      width: 100%;
    }

    .quiz-monitor-page .monitor-nav .btn {
      padding: 8px 10px;
      font-size: .74rem;
    }

    .quiz-monitor-page #statusHeader {
      align-items: flex-start !important;
      flex-direction: column;
      gap: 12px;
      padding: 19px !important;
    }

    .quiz-monitor-page #statusHeader > div {
      max-width: 100%;
    }

    .quiz-monitor-page #statusBadge {
      align-self: flex-start;
    }

    .quiz-monitor-page .monitor-stat-card {
      padding: 14px 8px !important;
    }

    .quiz-monitor-page .monitor-controls .btn {
      flex: 1 1 calc(50% - 10px);
      padding-right: 10px;
      padding-left: 10px;
      font-size: .7rem;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .quiz-monitor-page *,
    .quiz-monitor-page *::before,
    .quiz-monitor-page *::after {
      scroll-behavior: auto !important;
      animation-duration: .01ms !important;
      animation-iteration-count: 1 !important;
      transition-duration: .01ms !important;
    }
  }
</style>

<div class="main-content quiz-monitor-page">
  <div class="monitor-topbar">
    <div class="monitor-title-group">
      <button class="btn btn-dark d-md-none" id="sidebarToggle" aria-label="Open navigation"><i class="fa-solid fa-bars"></i></button>
      <div class="monitor-brand-icon"><i class="fa-solid fa-satellite-dish"></i></div>
      <div class="monitor-heading-copy">
        <p class="monitor-kicker">Telegram Quiz / Control Room</p>
        <h1 class="monitor-title">Live Quiz Monitor</h1>
        <p class="monitor-subtitle">Track progress, timing and delivery in real time.</p>
      </div>
    </div>
    <div class="monitor-nav">
      <?php if ($session): ?>
      <span class="monitor-connection is-connecting" id="monitorConnection" role="status" aria-live="polite">
        <span class="connection-dot"></span>
        <span id="monitorConnectionLabel">Connecting</span>
      </span>
      <a href="quiz_results.php?session_id=<?= (int)$session['id'] ?>" class="btn btn-sm"><i class="fa-solid fa-trophy me-1"></i>Results</a>
      <?php endif; ?>
      <a href="quiz_sets.php" class="btn btn-sm"><i class="fa-solid fa-layer-group me-1"></i>Quiz Sets</a>
      <a href="quiz_history.php" class="btn btn-sm"><i class="fa-solid fa-clock-rotate-left me-1"></i>History</a>
    </div>
  </div>

  <?php if ($session): ?>
  <!-- Single Session Monitor -->
  <div class="row justify-content-center monitor-layout">
    <div class="col-xl-10 col-xxl-9">

      <!-- Status Card -->
      <div class="card border-0 shadow mb-4 monitor-session-card" id="statusCard">
        <div class="card-header py-3 d-flex justify-content-between align-items-center" id="statusHeader">
          <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <h5 class="mb-0 fw-bold"><?= htmlspecialchars($session['title'] ?? $session['quiz_set_title']) ?></h5>
              <?php
                $sStart = (int)($session['start_question'] ?? 1);
                $sEnd   = (int)($session['end_question'] ?? 0);
                $sTot   = (int)$session['total_questions'];
                $sRangeBadge = ($sStart > 1 || ($sEnd > 0 && $sEnd !== $sTot))
                    ? "Custom: Q#{$sStart} - Q#{$sEnd} ({$sTot} Qs)"
                    : "Full Set ({$sTot} Qs)";
              ?>
              <span id="headerRangeBadge" class="badge bg-light text-dark border"><?= $sRangeBadge ?></span>
            </div>
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
          <div class="d-flex flex-wrap justify-content-between align-items-center bg-light p-2 px-3 rounded-3 mb-4 border monitor-sync-strip">
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
              <div class="text-center bg-light rounded p-3 shadow-sm monitor-stat-card">
                <i class="fa-solid fa-layer-group text-primary mb-2"></i>
                <div class="fs-2 fw-bold text-primary" id="totalQ">—</div>
                <small class="text-muted fw-semibold">Total Questions</small>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="text-center bg-light rounded p-3 shadow-sm monitor-stat-card">
                <i class="fa-solid fa-paper-plane text-success mb-2"></i>
                <div class="fs-2 fw-bold text-success" id="sentQ">—</div>
                <small class="text-muted fw-semibold">Sent Questions</small>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="text-center bg-light rounded p-3 shadow-sm monitor-stat-card">
                <i class="fa-solid fa-hourglass-half text-danger mb-2"></i>
                <div class="fs-2 fw-bold text-danger" id="remainingQ">—</div>
                <small class="text-muted fw-semibold">Remaining Questions</small>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="text-center bg-light rounded p-3 shadow-sm monitor-stat-card">
                <i class="fa-solid fa-stopwatch text-info mb-2"></i>
                <div class="fs-2 fw-bold text-info" id="timerSet">—</div>
                <small class="text-muted fw-semibold">Timer per Q</small>
              </div>
            </div>
          </div>

          <!-- Timer Progress Ring & Countdown -->
          <div class="text-center mb-4 monitor-timer-stage">
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
          <div class="card border-0 bg-light p-3 mb-3 rounded-3 shadow-sm border-start border-4 border-info monitor-telegram-card">
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
          <div class="monitor-controls">
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
      <div class="card border-0 shadow-sm monitor-activity-card">
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
  <div class="card border-0 shadow-sm monitor-empty-state">
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
  <div class="row g-3 monitor-session-grid">
    <?php foreach ($active_sessions as $s): ?>
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm monitor-session-card">
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
const CSRF       = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
const API        = '<?= BASE_URL ?>api/tg_quiz/session.php';
const SESSION_ID = <?= $session_id ?>;
const TOTAL_Q    = <?= (int)$session['total_questions'] ?>;

let pollInterval = null;
let tickInterval = null;
let isPolling    = false;
let logs         = [];
let lastSentCount = -1;
let lastStatus   = null;

let currentSecs    = 0;
let currentMaxSecs = 15;
let currentStatus  = null;
let serverOffsetSec = 0;

const statusColors = {
  'COUNTDOWN': {bg:'bg-info text-dark',     text:'🚀 COUNTDOWN'},
  'RUNNING':   {bg:'bg-success',            text:'🟢 RUNNING'},
  'PAUSED':    {bg:'bg-warning text-dark',  text:'⏸ PAUSED'},
  'COMPLETED': {bg:'bg-primary',            text:'✅ COMPLETED'},
  'STOPPED':   {bg:'bg-danger',             text:'🔴 STOPPED'},
  'FAILED':    {bg:'bg-dark',               text:'❌ FAILED'},
  'PENDING':   {bg:'bg-secondary',          text:'⏳ PENDING'},
};

function renderRing(secs, maxSecs, status) {
  const timerRing     = document.getElementById('timerRing');
  const timerRingText = document.getElementById('timerRingText');
  const timerDisplay  = document.getElementById('timerDisplay');
  const ringLabel     = document.getElementById('timerRingLabel');
  const ringSubtitle  = document.getElementById('timerSubtitle');
  if (!timerRing) return;

  const displaySecs = Math.max(0, secs);
  if (timerRingText) timerRingText.textContent = displaySecs;
  if (timerDisplay)  timerDisplay.textContent  = displaySecs + 's';

  const circumference = 2 * Math.PI * 65; // ~408.4
  const safeMax = Math.max(1, maxSecs || 15);
  const dashOffset = circumference * (1 - (Math.min(displaySecs, safeMax) / safeMax));
  timerRing.style.strokeDashoffset = Math.max(0, Math.min(circumference, dashOffset));

  if (status === 'COUNTDOWN') {
    if (ringLabel) ringLabel.textContent = 'starts in';
    if (ringSubtitle) ringSubtitle.innerHTML = `🚀 <span class="text-primary fw-bold">Live Pre-Quiz Countdown</span> synced with Telegram!`;
    timerRing.style.stroke = '#0dcaf0';
  } else if (status === 'PAUSED') {
    if (ringLabel) ringLabel.textContent = 'paused';
    if (ringSubtitle) ringSubtitle.innerHTML = `⏸ <span class="text-warning fw-bold">Quiz Paused</span> — Telegram status updated to PAUSED`;
    timerRing.style.stroke = '#ffc107';
  } else if (status === 'RUNNING') {
    if (ringLabel) ringLabel.textContent = 'sec left';
    if (ringSubtitle) ringSubtitle.innerHTML = `Next question countdown: <span class="fw-bold text-dark">${displaySecs}s</span>`;
    timerRing.style.stroke = displaySecs > 5 ? '#28a745' : '#dc3545';
  } else {
    if (ringLabel) ringLabel.textContent = 'status';
    if (ringSubtitle) ringSubtitle.innerHTML = `Session status: <span class="fw-bold text-dark">${status || '—'}</span>`;
    timerRing.style.stroke = '#6c757d';
  }
}

function tickRealtime() {
  if (['RUNNING', 'COUNTDOWN'].includes(currentStatus)) {
    if (currentSecs > 0) {
      currentSecs--;
      renderRing(currentSecs, currentMaxSecs, currentStatus);
    } else {
      // Timer reached 0! Trigger immediate status poll to dispatch / start without waiting
      if (!isPolling) {
        pollStatus();
      }
    }
  }
}

function updateUI(data) {
  const s = data.session;
  const pct  = data.progress_pct || 0;
  const isCountdown = s.status === 'COUNTDOWN';
  const isPaused = s.status === 'PAUSED';
  const isRunning = s.status === 'RUNNING';

  currentStatus = s.status;

  if (data.server_time) {
    serverOffsetSec = data.server_time - Math.floor(Date.now() / 1000);
  }

  // Determine seconds to display on ring & display
  let secs = 0;
  let maxSecs = 15;
  if (isCountdown) {
    secs = data.countdown_remaining !== undefined ? parseInt(data.countdown_remaining) : (parseInt(s.countdown_seconds) || 30);
    maxSecs = parseInt(s.countdown_seconds) || 30;
  } else if (isRunning) {
    secs = data.seconds_remaining !== undefined ? parseInt(data.seconds_remaining) : 0;
    maxSecs = parseInt(s.timer_seconds) || 15;
  } else if (isPaused) {
    secs = data.seconds_remaining !== undefined ? parseInt(data.seconds_remaining) : (parseInt(s.remaining_time) || 0);
    maxSecs = parseInt(s.timer_seconds) || 15;
  }

  currentSecs = Math.max(0, secs);
  currentMaxSecs = maxSecs;

  // Background engine status indicator
  const engineAlert = document.getElementById('engineAlertBox');
  const engineDesc  = document.getElementById('engineStatusDesc');
  const engineIcon  = document.getElementById('engineIcon');
  if (engineAlert && engineDesc) {
    if (data.daemon_active) {
      engineAlert.className = 'alert alert-success py-2 px-3 small d-flex align-items-center mb-4 rounded-3 shadow-sm border-0';
      engineIcon.className = 'fa-solid fa-server me-2 fs-5 text-success';
      engineDesc.innerHTML = '<strong>Server Daemon Active (PHP Background Engine):</strong> Quiz is executing autonomously in the background. It will continue running even if this tab is closed, minimized, or screen locked.';
    } else {
      engineAlert.className = 'alert alert-warning py-2 px-3 small d-flex align-items-center mb-4 rounded-3 shadow-sm border-0';
      engineIcon.className = 'fa-solid fa-triangle-exclamation me-2 fs-5 text-warning';
      engineDesc.innerHTML = '<strong>PHP background daemon is inactive.</strong> If your hosting worker or cron is not running, keep this Live Monitor tab open; browser polling is the fallback scheduler.';
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

  // Numbers & Range Info
  const sent = parseInt(s.questions_sent) || 0;
  const total = parseInt(s.total_questions) || 0;
  const remaining = Math.max(0, total - sent);
  const startNum = parseInt(s.start_question) || 1;
  const endNum = parseInt(s.end_question) || 0;

  // Range Badge in Header
  const headerRangeBadge = document.getElementById('headerRangeBadge');
  if (headerRangeBadge) {
    if (startNum > 1 || (endNum > 0 && endNum !== total)) {
      headerRangeBadge.textContent = `Custom Range: Q#${startNum} - Q#${endNum} (${total} Qs)`;
      headerRangeBadge.className = 'badge bg-warning text-dark border';
    } else {
      headerRangeBadge.textContent = `Full Set: ${total} Questions`;
      headerRangeBadge.className = 'badge bg-light text-dark border';
    }
  }

  // Progress Bar & ASCII bar
  const progressBar = document.getElementById('progressBar');
  if (isCountdown) {
    progressBar.style.width = '100%';
    progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
    document.getElementById('progressText').textContent = `Countdown Active: Starting in ${currentSecs}s`;
  } else {
    progressBar.style.width = pct + '%';
    progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-success';
    if (startNum > 1 || (endNum > 0 && endNum !== total)) {
      const curQDisplay = sent > 0 ? (startNum + sent - 1) : startNum;
      document.getElementById('progressText').textContent = `${sent} / ${total} (${pct}%) — Current: Q#${curQDisplay}`;
    } else {
      document.getElementById('progressText').textContent = `${sent} / ${total} (${pct}%)`;
    }
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

  document.getElementById('totalQ').textContent       = total;
  document.getElementById('sentQ').textContent        = sent;
  document.getElementById('remainingQ').textContent   = remaining;
  document.getElementById('timerSet').textContent     = (s.timer_seconds || 15) + 's';

  // Render timer ring with accurate values
  renderRing(currentSecs, currentMaxSecs, currentStatus);

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
    document.getElementById('btnDispatch').disabled = (!['RUNNING','COUNTDOWN'].includes(s.status) || remaining <= 0);
    document.getElementById('btnDispatch').innerHTML = isCountdown
      ? `<i class="fa-solid fa-bolt me-2"></i>SKIP TO QUESTION #${startNum}`
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

  // Stop polling and ticking when done and announced
  if ((s.status === 'COMPLETED' && s.results_announced_at) || ['STOPPED','FAILED'].includes(s.status)) {
    if (pollInterval) clearInterval(pollInterval);
    if (tickInterval) clearInterval(tickInterval);
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

function setMonitorConnection(state, label) {
  const indicator = document.getElementById('monitorConnection');
  const labelNode = document.getElementById('monitorConnectionLabel');
  if (!indicator || !labelNode) return;
  indicator.className = `monitor-connection is-${state}`;
  labelNode.textContent = label;
}

async function pollStatus() {
  if (isPolling) return;
  isPolling = true;

  try {
    const response = await fetch(`${API}?action=status&session_id=${SESSION_ID}&_nocache=${Date.now()}`);
    if (!response.ok) throw new Error(`Status request failed (${response.status})`);
    const res = await response.json();
    if (res.success) {
      setMonitorConnection('online', 'Monitor connected');
      updateUI(res);
      const startQ = parseInt(res.session.start_question) || 1;
      const curSent = parseInt(res.session.questions_sent) || 0;
      if (lastSentCount !== -1 && curSent > lastSentCount) {
        const curQNum = (startQ > 1) ? (startQ + curSent - 1) : curSent;
        addLog(`🎯 Question #${curQNum} dispatched to Telegram group! (${curSent}/${res.session.total_questions})`, 'success');
      }
      lastSentCount = curSent;

      if (lastStatus === 'COUNTDOWN' && res.session.status === 'RUNNING') {
        addLog(`🟢 Countdown ended! Quiz has started and Question #${startQ} is live!`, 'success');
      }
      lastStatus = res.session.status;

      if (res.finalization && res.finalization.completed && !res.finalization.already_announced) {
        addLog(`🏆 Quiz finished! Final results successfully published to Telegram!`, 'success');
      }
    } else {
      setMonitorConnection('offline', 'Session unavailable');
    }
  } catch(e) {
    setMonitorConnection('offline', 'Reconnecting…');
  } finally {
    isPolling = false;
  }
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
  try {
    const res = await fetch(API, {method:'POST', body:fd}).then(r=>r.json());
    document.getElementById('actionResult').innerHTML = `<div class="alert alert-${res.success?'success':'danger'} py-2 text-center">${res.message}</div>`;
    addLog(res.message, res.success ? 'success' : 'error');
    if (res.success) pollStatus();
  } catch(e) {
    document.getElementById('actionResult').innerHTML = `<div class="alert alert-danger py-2 text-center">Network error executing ${action}</div>`;
  }
}

// Start polling every 3 seconds and real-time ticker every 1 second
document.addEventListener('DOMContentLoaded', () => {
  pollStatus();
  pollInterval = setInterval(pollStatus, 3000);
  tickInterval = setInterval(tickRealtime, 1000);
  addLog('Monitor started — real-time 1s timer & 3s sync active', 'info');
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
window.addEventListener('beforeunload', () => {
  if (pollInterval) clearInterval(pollInterval);
  if (tickInterval) clearInterval(tickInterval);
});
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
