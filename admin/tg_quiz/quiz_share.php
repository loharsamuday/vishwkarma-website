<?php
// admin/tg_quiz/quiz_share.php
// Telegram Playable Quiz Sharing & Live Group Engine — Admin Interface
// Modes: Individual Quiz (Mode A) + Common Live Group Quiz (Mode B) + Both Modes (Mode C)
// Complete Quiz Text Sharing + Question Numbering + Real-Time Live Monitor + Leaderboard + Analytics

$page_title = "Telegram Playable Quiz Sharing";
require_once '../../includes/db.php';
require_once '../../includes/session.php';
require_once '../../api/tg_quiz/tg_helper.php';
require_once '../../api/tg_quiz/tg_playable_helper.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$admin_id = (int)$_SESSION['admin_id'];

// Initial data load for smooth SSR
$quizzes = getShareableQuizzes($pdo, $admin_id);
$promo   = getTelegramPromoSettings($pdo);

// Verified chats
$chats = [];
try {
    $stmtChats = $pdo->prepare("SELECT id, chat_id, chat_title, chat_type, can_send_polls FROM tg_chats WHERE admin_id = ? AND is_verified = 1 ORDER BY updated_at DESC");
    $stmtChats->execute([$admin_id]);
    $chats = $stmtChats->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

// Pre-selected quiz from URL parameter if passed (e.g. ?type=tg_set&id=2)
$preType = trim($_GET['type'] ?? 'tg_set');
$preId   = (int)($_GET['id'] ?? 0);

require_once '../includes/header.php';
?>

<style>
/* Custom UI Styling for Telegram Quiz Hub */
.tg-tab-btn {
  font-weight: 600;
  border-radius: 8px 8px 0 0 !important;
  padding: 10px 18px !important;
}
.tg-preview-card {
  background-color: #54a0e515;
  border: 1px solid #54a0e540;
  border-radius: 12px;
}
.tg-bubble {
  background: #ffffff;
  border-radius: 14px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.06);
  border-left: 4px solid #0088cc;
}
.tg-btn-preview {
  background: #2aabee;
  color: white !important;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 8px 14px;
  display: block;
  text-align: center;
  text-decoration: none !important;
  transition: all 0.2s ease;
  box-shadow: 0 2px 5px rgba(42, 171, 238, 0.3);
}
.tg-btn-preview:hover {
  background: #2299d4;
  transform: translateY(-1px);
}
.stat-card {
  border-radius: 12px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0,0,0,0.08) !important;
}
.timer-countdown {
  font-family: 'Courier New', Courier, monospace;
  font-size: 2.2rem;
  font-weight: bold;
}
.group-pill-check input:checked + label {
  background-color: #e8f4fd;
  border-color: #0d6efd !important;
  color: #0d6efd;
}
</style>

<div class="main-content">
  <!-- Top Navigation Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
    <div class="d-flex align-items-center mb-2 mb-md-0">
      <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
      <div>
        <h4 class="mb-0 fw-bold text-dark">
          <i class="fa-solid fa-gamepad text-primary me-2"></i>Telegram Playable Quiz Sharing
        </h4>
        <small class="text-muted">Interactive Individual Quiz (Mode A), Common Live Group Quiz (Mode B), &amp; Complete Text Sets</small>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <div id="daemonStatusPill" class="d-inline-flex align-items-center me-1">
        <span class="badge bg-secondary p-2 me-1" id="lblDaemonStatus">
          <i class="fa-solid fa-spinner fa-spin me-1"></i>Checking Bot Service...
        </span>
        <button class="btn btn-sm btn-success fw-bold d-none" id="btnStartDaemon" onclick="startDaemonNow()">
          <i class="fa-solid fa-play me-1"></i>Start Bot Service
        </button>
      </div>
      <button class="btn btn-outline-primary btn-sm fw-bold" onclick="openPromoModal()">
        <i class="fa-solid fa-bullhorn me-1"></i>Branding &amp; Promo
      </button>
      <button class="btn btn-outline-success btn-sm fw-bold" onclick="openAddChatModal()">
        <i class="fa-solid fa-plus-circle me-1"></i>Add Telegram Group
      </button>
      <a href="quiz_sets.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i>Quiz Sets
      </a>
    </div>
  </div>

  <!-- Global Alert Container -->
  <div id="alertBox"></div>

  <!-- Master Navigation Tabs -->
  <ul class="nav nav-tabs border-bottom-0 mb-3" id="mainQuizTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active tg-tab-btn" id="tab-playable-btn" data-bs-toggle="tab" data-bs-target="#tab-playable" type="button" role="tab">
        <i class="fa-solid fa-share-nodes text-primary me-2"></i>1. Share &amp; Play Quiz
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link tg-tab-btn" id="tab-live-btn" data-bs-toggle="tab" data-bs-target="#tab-live" type="button" role="tab" onclick="loadLiveSessionsList()">
        <i class="fa-solid fa-satellite-dish text-danger me-2 fa-fade"></i>2. Common Live Group Monitor
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link tg-tab-btn" id="tab-results-btn" data-bs-toggle="tab" data-bs-target="#tab-results" type="button" role="tab" onclick="loadResultsTab()">
        <i class="fa-solid fa-trophy text-warning me-2"></i>3. Results &amp; Leaderboards
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link tg-tab-btn" id="tab-analytics-btn" data-bs-toggle="tab" data-bs-target="#tab-analytics" type="button" role="tab" onclick="loadReportingStats()">
        <i class="fa-solid fa-chart-line text-info me-2"></i>4. Reporting &amp; Analytics
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link tg-tab-btn" id="tab-textquiz-btn" data-bs-toggle="tab" data-bs-target="#tab-textquiz" type="button" role="tab" onclick="onTextQuizTabOpen()">
        <i class="fa-solid fa-file-lines text-secondary me-2"></i>5. Complete Text Quiz Set
      </button>
    </li>
  </ul>

  <!-- Tab Contents -->
  <div class="tab-content" id="mainQuizTabsContent">

    <!-- ============================================================ -->
    <!-- TAB 1: SHARE & PLAY QUIZ (MODE A, MODE B, BOTH MODES)       -->
    <!-- ============================================================ -->
    <div class="tab-pane fade show active" id="tab-playable" role="tabpanel">
      <div class="row g-4">
        <!-- Configuration Column (Left) -->
        <div class="col-lg-5">
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-dark text-white fw-bold py-3 d-flex justify-content-between align-items-center">
              <span><i class="fa-solid fa-sliders text-warning me-2"></i>Playable Quiz Setup</span>
              <span class="badge bg-primary">Two Modes</span>
            </div>
            <div class="card-body p-4">

              <!-- 1. Select Existing Quiz -->
              <div class="mb-4">
                <label class="form-label fw-bold d-flex justify-content-between align-items-center">
                  <span>1. Select Existing Quiz <span class="text-danger">*</span></span>
                  <span id="playableQuizBadge" class="badge bg-secondary d-none">0 Questions</span>
                </label>
                <select class="form-select form-select-lg" id="playableQuizSelect" onchange="onPlayableQuizSelected()">
                  <option value="">-- Choose Quiz to Share --</option>
                  
                  <?php if (!empty($quizzes['tg_sets'])): ?>
                  <optgroup label="📋 Telegram Quiz Sets">
                    <?php foreach ($quizzes['tg_sets'] as $s): ?>
                    <option value="tg_set:<?= $s['id'] ?>"
                            data-type="tg_set"
                            data-id="<?= $s['id'] ?>"
                            data-title="<?= htmlspecialchars($s['title']) ?>"
                            data-questions="<?= (int)$s['total_questions'] ?>"
                            <?= ($preType === 'tg_set' && $preId === (int)$s['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($s['title']) ?> (<?= (int)$s['total_questions'] ?> Questions)
                    </option>
                    <?php endforeach; ?>
                  </optgroup>
                  <?php endif; ?>

                  <?php if (!empty($quizzes['mt_tests'])): ?>
                  <optgroup label="🎓 Website Mock Tests">
                    <?php foreach ($quizzes['mt_tests'] as $m): ?>
                    <option value="mt_mock:<?= $m['id'] ?>"
                            data-type="mt_mock"
                            data-id="<?= $m['id'] ?>"
                            data-title="<?= htmlspecialchars($m['title']) ?>"
                            data-questions="<?= (int)$m['total_questions'] ?>"
                            <?= ($preType === 'mt_mock' && $preId === (int)$m['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($m['title']) ?> (<?= (int)$m['total_questions'] ?> Questions)
                    </option>
                    <?php endforeach; ?>
                  </optgroup>
                  <?php endif; ?>
                </select>
                <small class="text-muted">Choose from Telegram sets or Website mock tests.</small>
              </div>

              <!-- 2. Select Destination Telegram Groups (Multiple Supported) -->
              <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <label class="form-label fw-bold mb-0">
                    2. Select Destination Groups <span class="text-danger">*</span>
                    <span id="selectedGroupsCount" class="badge bg-info text-dark ms-1">0 Selected</span>
                  </label>
                  <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary py-0 px-2" onclick="toggleAllGroups(true)">All</button>
                    <button type="button" class="btn btn-outline-secondary py-0 px-2" onclick="toggleAllGroups(false)">Clear</button>
                  </div>
                </div>

                <div class="border rounded p-2 bg-light group-list-container" style="max-height: 180px; overflow-y: auto;">
                  <?php if (empty($chats)): ?>
                    <div class="text-center py-3 text-muted small">
                      No verified Telegram groups found.<br>
                      <button type="button" class="btn btn-link btn-sm p-0" onclick="openAddChatModal()">Add a Group now</button>
                    </div>
                  <?php else: ?>
                    <?php foreach ($chats as $idx => $c): ?>
                      <div class="form-check group-pill-check p-1 mb-1">
                        <input class="form-check-input ms-1 me-2 group-checkbox" type="checkbox"
                               value="<?= htmlspecialchars($c['chat_id']) ?>"
                               id="grp_<?= $idx ?>"
                               data-title="<?= htmlspecialchars($c['chat_title']) ?>"
                               onchange="updateGroupSelectionCount()">
                        <label class="form-check-label w-100 p-2 border rounded bg-white text-truncate cursor-pointer small fw-semibold" for="grp_<?= $idx ?>">
                          <i class="fa-solid fa-users text-primary me-1"></i>
                          <?= htmlspecialchars($c['chat_title'] ?? 'Chat ' . $c['chat_id']) ?>
                          <span class="text-muted small ms-1">(<?= $c['chat_id'] ?>)</span>
                        </label>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
                <small class="text-muted">You can select one or multiple authorized groups simultaneously.</small>
              </div>

              <!-- 3. Select Quiz Mode -->
              <div class="mb-4 p-3 bg-light rounded border">
                <label class="form-label fw-bold mb-2">3. Quiz Playing Mode <span class="text-danger">*</span></label>
                
                <div class="form-check mb-2">
                  <input class="form-check-input" type="radio" name="quizMode" id="modeBoth" value="both" checked onchange="refreshPlayablePreview()">
                  <label class="form-check-label fw-semibold" for="modeBoth">
                    <span class="badge bg-success me-1">Recommended</span> Both Modes (Option C)
                    <div class="text-muted small fw-normal">Group members can choose between Individual Chat or Common Live Quiz.</div>
                  </label>
                </div>

                <div class="form-check mb-2">
                  <input class="form-check-input" type="radio" name="quizMode" id="modeIndividual" value="individual" onchange="refreshPlayablePreview()">
                  <label class="form-check-label fw-semibold" for="modeIndividual">
                    <i class="fa-solid fa-user text-primary me-1"></i> Mode A: Individual Quiz Only
                    <div class="text-muted small fw-normal">Participants play independently in their private Telegram chat with the bot.</div>
                  </label>
                </div>

                <div class="form-check">
                  <input class="form-check-input" type="radio" name="quizMode" id="modeLive" value="live" onchange="refreshPlayablePreview()">
                  <label class="form-check-label fw-semibold" for="modeLive">
                    <i class="fa-solid fa-users text-danger me-1"></i> Mode B: Common Live Group Quiz Only
                    <div class="text-muted small fw-normal">All group members play simultaneously in the group on a shared countdown timer.</div>
                  </label>
                </div>
              </div>

              <!-- 4. Timer & Marking Configuration -->
              <div class="row g-2 mb-4">
                <div class="col-sm-6">
                  <label class="form-label fw-bold small">⏱ Timer per Question</label>
                  <select class="form-select" id="timerSeconds" onchange="refreshPlayablePreview()">
                    <option value="15">15 Seconds</option>
                    <option value="20" selected>20 Seconds</option>
                    <option value="30">30 Seconds</option>
                    <option value="45">45 Seconds</option>
                    <option value="60">60 Seconds</option>
                  </select>
                </div>
                <div class="col-sm-3">
                  <label class="form-label fw-bold small">Correct Mark</label>
                  <input type="number" step="0.25" class="form-control" id="correctMark" value="1.00">
                </div>
                <div class="col-sm-3">
                  <label class="form-label fw-bold small">Negative Mark</label>
                  <input type="number" step="0.25" class="form-control" id="wrongMark" value="0.00">
                </div>
              </div>

              <!-- 5. Share Action Button -->
              <div class="d-grid gap-2">
                <button class="btn btn-primary btn-lg fw-bold shadow-sm py-3" id="btnSharePlayable" onclick="triggerPlayableShare()">
                  <i class="fa-brands fa-telegram fs-4 align-middle me-2"></i>Share Playable Quiz to Group(s)
                </button>
              </div>

            </div>
          </div>
        </div>

        <!-- Preview Column (Right) -->
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
              <div>
                <i class="fa-solid fa-eye text-primary me-2"></i>Telegram Invitation Preview
              </div>
              <span class="badge bg-secondary" id="previewModeBadge">Both Modes</span>
            </div>
            <div class="card-body p-4 bg-light">

              <!-- Empty State -->
              <div id="playablePreviewEmpty" class="text-center py-5">
                <i class="fa-brands fa-telegram fa-4x text-muted opacity-50 mb-3"></i>
                <h5 class="text-muted fw-bold">Select a Quiz to Preview Invitation</h5>
                <p class="text-muted">Choose a quiz to see the formatted message and interactive Telegram inline buttons.</p>
              </div>

              <!-- Live Preview Card -->
              <div id="playablePreviewCard" class="tg-preview-card p-4 d-none">
                <div class="tg-bubble p-4 mb-3">
                  <div id="playablePreviewText" style="white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 0.95rem;"></div>

                  <!-- Simulated Inline Keyboard Buttons -->
                  <div class="mt-4 pt-3 border-top" id="simulatedButtonsContainer">
                    <!-- Dynamic buttons inserted by JS -->
                  </div>
                </div>

                <div class="alert alert-info py-2 px-3 small mb-0">
                  <i class="fa-solid fa-circle-info me-1"></i>
                  When members click <strong>Play Individually</strong>, the bot opens their private chat and creates a unique session.
                  When the admin clicks <strong>Start Live Quiz</strong>, the quiz starts simultaneously in the group!
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>

      <!-- Active Shared Playable Quizzes Table -->
      <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-list-check text-primary me-2"></i>Active Shared Playable Quizzes</span>
          <button class="btn btn-outline-secondary btn-sm" onclick="loadPlayableQuizzesList()">
            <i class="fa-solid fa-rotate me-1"></i>Refresh
          </button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">ID</th>
                  <th>Quiz Title</th>
                  <th>Destination Group</th>
                  <th>Mode</th>
                  <th>Timer</th>
                  <th>Participants</th>
                  <th>Status</th>
                  <th class="pe-3 text-end">Live Controls / Actions</th>
                </tr>
              </thead>
              <tbody id="playableQuizzesTableBody">
                <tr><td colspan="8" class="text-center py-4 text-muted">Loading shared playable quizzes...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 2: COMMON LIVE GROUP QUIZ MONITOR & CONTROLS            -->
    <!-- ============================================================ -->
    <div class="tab-pane fade" id="tab-live" role="tabpanel">
      <div class="row g-4">
        <!-- Live Quiz Selector & Control Toolbar -->
        <div class="col-lg-5">
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-dark text-white fw-bold py-3">
              <i class="fa-solid fa-sliders text-warning me-2"></i>Select Live Quiz Session
            </div>
            <div class="card-body p-4">
              <div class="mb-3">
                <label class="form-label fw-bold">Active Live Session</label>
                <select class="form-select form-select-lg" id="liveSessionSelect" onchange="onLiveSessionChanged()">
                  <option value="">-- Select Active Live Session --</option>
                </select>
                <small class="text-muted">Choose an active session to monitor and control in real time.</small>
              </div>

              <!-- Live Control Buttons Panel -->
              <div class="p-3 bg-light rounded border mb-4">
                <label class="form-label fw-bold mb-3 d-block"><i class="fa-solid fa-gamepad text-primary me-1"></i>Live Session Controls</label>
                
                <div class="row g-2">
                  <div class="col-6">
                    <button class="btn btn-success w-100 fw-bold py-2" id="btnLiveStart" onclick="triggerLiveAction('start')">
                      <i class="fa-solid fa-play me-1"></i>Start Live
                    </button>
                  </div>
                  <div class="col-6">
                    <button class="btn btn-warning w-100 fw-bold py-2" id="btnLivePause" onclick="triggerLiveAction('pause')">
                      <i class="fa-solid fa-pause me-1"></i>Pause Timer
                    </button>
                  </div>
                  <div class="col-6">
                    <button class="btn btn-info w-100 fw-bold py-2 text-white" id="btnLiveResume" onclick="triggerLiveAction('resume')">
                      <i class="fa-solid fa-forward-step me-1"></i>Resume
                    </button>
                  </div>
                  <div class="col-6">
                    <button class="btn btn-primary w-100 fw-bold py-2" id="btnLiveAdvance" onclick="triggerLiveAction('advance')">
                      <i class="fa-solid fa-angles-right me-1"></i>Next Question
                    </button>
                  </div>
                  <div class="col-12 mt-2">
                    <button class="btn btn-danger w-100 fw-bold py-2" id="btnLiveStop" onclick="triggerLiveAction('stop')">
                      <i class="fa-solid fa-stop me-1"></i>End Live Quiz &amp; Publish Leaderboard
                    </button>
                  </div>
                </div>
              </div>

              <!-- Quick Status Summary -->
              <div class="card border-0 bg-white shadow-sm p-3">
                <h6 class="fw-bold mb-2">Live Status Summary</h6>
                <div class="d-flex justify-content-between mb-1 small">
                  <span class="text-muted">Group:</span>
                  <span class="fw-bold" id="liveSummaryGroup">-</span>
                </div>
                <div class="d-flex justify-content-between mb-1 small">
                  <span class="text-muted">Status:</span>
                  <span class="badge bg-secondary" id="liveSummaryStatus">IDLE</span>
                </div>
                <div class="d-flex justify-content-between mb-1 small">
                  <span class="text-muted">Current Progress:</span>
                  <span class="fw-bold" id="liveSummaryProgress">Question 0/0</span>
                </div>
                <div class="d-flex justify-content-between small">
                  <span class="text-muted">Participants Answered:</span>
                  <span class="fw-bold text-success" id="liveSummaryAnswers">0 Users</span>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- Real-Time Question & Countdown Monitor (Right) -->
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
              <div>
                <i class="fa-solid fa-satellite-dish text-danger me-2 fa-fade"></i>Live Group Monitor
              </div>
              <div class="d-flex gap-2 align-items-center">
                <span class="badge bg-danger" id="liveActiveBadge">STANDBY</span>
                <button class="btn btn-outline-secondary btn-sm" onclick="fetchLiveStatusNow()">
                  <i class="fa-solid fa-rotate"></i>
                </button>
              </div>
            </div>
            <div class="card-body p-4 bg-light">

              <!-- Big Timer & Answer Counter -->
              <div class="row g-3 mb-4">
                <div class="col-sm-6">
                  <div class="card border-0 shadow-sm text-center p-3 stat-card bg-white">
                    <small class="text-muted text-uppercase fw-bold">Time Remaining</small>
                    <div class="timer-countdown text-primary mt-1" id="liveTimerDisplay">--s</div>
                    <div class="progress mt-2" style="height: 6px;">
                      <div class="progress-bar bg-primary" id="liveTimerBar" style="width: 100%"></div>
                    </div>
                  </div>
                </div>
                <div class="col-sm-6">
                  <div class="card border-0 shadow-sm text-center p-3 stat-card bg-white">
                    <small class="text-muted text-uppercase fw-bold">Answers Submitted</small>
                    <div class="timer-countdown text-success mt-1" id="liveAnswersCount">0</div>
                    <small class="text-muted mt-2 d-block">Simultaneous Group Participants</small>
                  </div>
                </div>
              </div>

              <!-- Current Live Question Card -->
              <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center">
                  <small class="fw-bold" id="liveCardHeaderQuestion">Question 1/10</small>
                  <small class="text-warning fw-bold" id="liveCardHeaderTimer">⏱ 20s per question</small>
                </div>
                <div class="card-body p-4">
                  <h6 class="fw-bold mb-3" id="liveQuestionText">Select an active live session to display the active question.</h6>
                  <div id="liveOptionsList" class="d-grid gap-2"></div>
                </div>
              </div>

              <!-- Recent Live Answerers Stream -->
              <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-2 fw-bold small text-muted">
                  <i class="fa-solid fa-bolt text-warning me-1"></i>Recent Live Answer Submissions
                </div>
                <div class="card-body p-2" id="liveRecentAnswersList" style="max-height: 140px; overflow-y: auto;">
                  <div class="text-center text-muted small py-3">No live answers yet.</div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 3: RESULTS & GROUP LEADERBOARDS                         -->
    <!-- ============================================================ -->
    <div class="tab-pane fade" id="tab-results" role="tabpanel">
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold py-3 d-flex flex-wrap justify-content-between align-items-center">
          <div>
            <i class="fa-solid fa-trophy text-warning me-2"></i>Participant Results &amp; Live Leaderboards
          </div>
          <div class="btn-group btn-group-sm mt-2 mt-md-0" role="group">
            <input type="radio" class="btn-check" name="resultsType" id="resTypeLeaderboard" checked onchange="toggleResultsView('leaderboard')">
            <label class="btn btn-outline-primary fw-bold" for="resTypeLeaderboard"><i class="fa-solid fa-medal me-1"></i>Group Live Leaderboard</label>

            <input type="radio" class="btn-check" name="resultsType" id="resTypeIndividual" onchange="toggleResultsView('individual')">
            <label class="btn btn-outline-primary fw-bold" for="resTypeIndividual"><i class="fa-solid fa-user me-1"></i>Individual Sessions</label>
          </div>
        </div>
        <div class="card-body p-4">

          <!-- View 1: Group Live Leaderboard -->
          <div id="viewLiveLeaderboard">
            <div class="row align-items-center mb-3">
              <div class="col-md-6">
                <label class="form-label fw-bold small">Select Live Group Session:</label>
                <select class="form-select" id="leaderboardSessionSelect" onchange="loadLeaderboardData()">
                  <option value="">-- Choose Live Session --</option>
                </select>
              </div>
              <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <button class="btn btn-outline-secondary btn-sm" onclick="loadLeaderboardData()">
                  <i class="fa-solid fa-rotate me-1"></i>Refresh Leaderboard
                </button>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="ps-3" style="width: 80px;">Rank</th>
                    <th>Participant Name</th>
                    <th>Telegram Username</th>
                    <th>Total Score</th>
                    <th>Correct Answers</th>
                    <th>Total Answered</th>
                    <th>Total Time Taken</th>
                  </tr>
                </thead>
                <tbody id="leaderboardTableBody">
                  <tr><td colspan="7" class="text-center py-4 text-muted">Select a live session above to view rankings.</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- View 2: Individual Quiz Sessions -->
          <div id="viewIndividualSessions" class="d-none">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold mb-0">Mode A: Independent Private Chat Sessions</h6>
              <button class="btn btn-outline-secondary btn-sm" onclick="loadIndividualSessionsData()">
                <i class="fa-solid fa-rotate me-1"></i>Refresh
              </button>
            </div>

            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="ps-3">Session Token</th>
                    <th>Participant</th>
                    <th>Quiz Title</th>
                    <th>Origin Group</th>
                    <th>Progress</th>
                    <th>Score</th>
                    <th>Accuracy</th>
                    <th>Status</th>
                    <th class="pe-3">Completed At</th>
                  </tr>
                </thead>
                <tbody id="individualSessionsTableBody">
                  <tr><td colspan="9" class="text-center py-4 text-muted">Loading individual participants...</td></tr>
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 4: REPORTING & ANALYTICS DASHBOARD                      -->
    <!-- ============================================================ -->
    <div class="tab-pane fade" id="tab-analytics" role="tabpanel">
      <!-- KPI Stats Cards -->
      <div class="row g-4 mb-4">
        <div class="col-lg-3 col-sm-6">
          <div class="card border-0 shadow-sm p-3 stat-card bg-white">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <small class="text-muted fw-bold text-uppercase">Shared Quizzes</small>
                <h3 class="fw-bold text-dark mt-1 mb-0" id="statTotalShared">0</h3>
              </div>
              <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                <i class="fa-solid fa-share-nodes fs-4"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-sm-6">
          <div class="card border-0 shadow-sm p-3 stat-card bg-white">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <small class="text-muted fw-bold text-uppercase">Groups Reached</small>
                <h3 class="fw-bold text-success mt-1 mb-0" id="statGroupsCount">0</h3>
              </div>
              <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                <i class="fa-solid fa-users fs-4"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-sm-6">
          <div class="card border-0 shadow-sm p-3 stat-card bg-white">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <small class="text-muted fw-bold text-uppercase">Individual Users</small>
                <h3 class="fw-bold text-info mt-1 mb-0" id="statIndivUsers">0</h3>
              </div>
              <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info">
                <i class="fa-solid fa-user-check fs-4"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-sm-6">
          <div class="card border-0 shadow-sm p-3 stat-card bg-white">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <small class="text-muted fw-bold text-uppercase">Live Group Users</small>
                <h3 class="fw-bold text-danger mt-1 mb-0" id="statLiveUsers">0</h3>
              </div>
              <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                <i class="fa-solid fa-satellite-dish fs-4"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Secondary Metrics -->
      <div class="row g-4">
        <div class="col-lg-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold py-3">
              <i class="fa-solid fa-chart-pie text-primary me-2"></i>Participation &amp; Completion Metrics
            </div>
            <div class="card-body p-4">
              <div class="mb-4">
                <div class="d-flex justify-content-between mb-1">
                  <span class="fw-semibold">Average Quiz Accuracy Rate</span>
                  <span class="fw-bold text-primary" id="statAvgAccuracy">0%</span>
                </div>
                <div class="progress" style="height: 10px;">
                  <div class="progress-bar bg-primary" id="statAvgAccuracyBar" style="width: 0%"></div>
                </div>
              </div>

              <div class="mb-4">
                <div class="d-flex justify-content-between mb-1">
                  <span class="fw-semibold">Individual Completion Rate</span>
                  <span class="fw-bold text-success" id="statCompletionRate">0%</span>
                </div>
                <div class="progress" style="height: 10px;">
                  <div class="progress-bar bg-success" id="statCompletionRateBar" style="width: 0%"></div>
                </div>
              </div>

              <hr>
              <div class="row text-center mt-3">
                <div class="col-6">
                  <h4 class="fw-bold text-dark mb-0" id="statTotalAnswers">0</h4>
                  <small class="text-muted">Total Answers Processed</small>
                </div>
                <div class="col-6">
                  <h4 class="fw-bold text-dark mb-0" id="statActiveSessionsCount">0</h4>
                  <small class="text-muted">Active Sessions Now</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold py-3">
              <i class="fa-solid fa-shield-halved text-success me-2"></i>System Concurrency &amp; Health
            </div>
            <div class="card-body p-4">
              <ul class="list-unstyled mb-0">
                <li class="mb-3 d-flex align-items-center">
                  <i class="fa-solid fa-check-circle text-success fs-5 me-3"></i>
                  <div>
                    <strong>Concurrency Isolation:</strong>
                    <div class="text-muted small">Multi-group atomic execution with database optimistic locking.</div>
                  </div>
                </li>
                <li class="mb-3 d-flex align-items-center">
                  <i class="fa-solid fa-check-circle text-success fs-5 me-3"></i>
                  <div>
                    <strong>Question Numbering Integrity:</strong>
                    <div class="text-muted small">Strict sequential progress (1/N to N/N) guaranteed across all participants.</div>
                  </div>
                </li>
                <li class="mb-3 d-flex align-items-center">
                  <i class="fa-solid fa-check-circle text-success fs-5 me-3"></i>
                  <div>
                    <strong>Duplicate Answer Prevention:</strong>
                    <div class="text-muted small">Unique constraint enforcement per participant per question.</div>
                  </div>
                </li>
                <li class="d-flex align-items-center">
                  <i class="fa-solid fa-check-circle text-success fs-5 me-3"></i>
                  <div>
                    <strong>Crash &amp; Restart Recovery:</strong>
                    <div class="text-muted small">State restored automatically from database timestamps upon daemon/worker wakeup.</div>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 5: COMPLETE TEXT QUIZ SET SHARING (MODULE 2 PRESERVED)   -->
    <!-- ============================================================ -->
    <div class="tab-pane fade" id="tab-textquiz" role="tabpanel">
      <div class="row g-4">
        <!-- Configuration Panel (Left) -->
        <div class="col-lg-5">
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-dark text-white fw-bold py-3 d-flex justify-content-between align-items-center">
              <span><i class="fa-solid fa-file-lines text-warning me-2"></i>Complete Text Quiz Setup</span>
              <span class="badge bg-secondary">Formatted Text</span>
            </div>
            <div class="card-body p-4">

              <!-- 1. Select Quiz -->
              <div class="mb-4">
                <label class="form-label fw-bold d-flex justify-content-between align-items-center">
                  <span>1. Select Quiz / Category <span class="text-danger">*</span></span>
                  <span id="quizBadge" class="badge bg-secondary d-none">0 Questions</span>
                </label>
                <select class="form-select form-select-lg" id="quizSelect" onchange="onQuizSelected()">
                  <option value="">-- Choose Quiz to Share --</option>
                  
                  <?php if (!empty($quizzes['tg_sets'])): ?>
                  <optgroup label="📋 Telegram Quiz Sets">
                    <?php foreach ($quizzes['tg_sets'] as $s): ?>
                    <option value="tg_set:<?= $s['id'] ?>"
                            data-type="tg_set"
                            data-id="<?= $s['id'] ?>"
                            data-title="<?= htmlspecialchars($s['title']) ?>"
                            data-questions="<?= (int)$s['total_questions'] ?>"
                            <?= ($preType === 'tg_set' && $preId === (int)$s['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($s['title']) ?> (<?= (int)$s['total_questions'] ?> Questions)
                    </option>
                    <?php endforeach; ?>
                  </optgroup>
                  <?php endif; ?>

                  <?php if (!empty($quizzes['mt_tests'])): ?>
                  <optgroup label="🎓 Website Mock Tests">
                    <?php foreach ($quizzes['mt_tests'] as $m): ?>
                    <option value="mt_mock:<?= $m['id'] ?>"
                            data-type="mt_mock"
                            data-id="<?= $m['id'] ?>"
                            data-title="<?= htmlspecialchars($m['title']) ?>"
                            data-questions="<?= (int)$m['total_questions'] ?>"
                            <?= ($preType === 'mt_mock' && $preId === (int)$m['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($m['title']) ?> (<?= (int)$m['total_questions'] ?> Questions)
                    </option>
                    <?php endforeach; ?>
                  </optgroup>
                  <?php endif; ?>
                </select>
                <small class="text-muted">Choose any quiz set or mock test from your database.</small>
              </div>

              <!-- 2. Select Destination Group -->
              <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="form-label fw-bold mb-0">2. Destination Telegram Group <span class="text-danger">*</span></label>
                  <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="openAddChatModal()">
                    <i class="fa-solid fa-plus-circle me-1"></i>Add New Group
                  </button>
                </div>
                <select class="form-select form-select-lg" id="chatSelect">
                  <option value="">-- Select Verified Group --</option>
                  <?php foreach ($chats as $c): ?>
                  <option value="<?= $c['chat_id'] ?>">
                    <?= htmlspecialchars($c['chat_title'] ?? 'Chat ' . $c['chat_id']) ?> (<?= $c['chat_id'] ?>)
                  </option>
                  <?php endforeach; ?>
                </select>
                <small class="text-muted">Group must have your bot added as a member or administrator.</small>
              </div>

              <!-- 3. Formatting & Explanations -->
              <div class="mb-4 p-3 bg-light rounded border">
                <label class="form-label fw-bold mb-2"><i class="fa-solid fa-lightbulb text-warning me-1"></i>Options &amp; Explanations</label>
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" id="chkExplanation" checked onchange="refreshPreview()">
                  <label class="form-check-label fw-semibold" for="chkExplanation">Include Explanations (if available)</label>
                </div>
                <div id="explPosWrapper" class="mt-2 ps-3">
                  <label class="form-label text-muted small fw-bold">Explanation Position:</label>
                  <div class="form-check mb-1">
                    <input class="form-check-input" type="radio" name="explPos" id="explPosKey" value="answer_key" checked onchange="refreshPreview()">
                    <label class="form-check-label small" for="explPosKey">In Answer Key at the end (Recommended)</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="explPos" id="explPosBelow" value="below_question" onchange="refreshPreview()">
                    <label class="form-check-label small" for="explPosBelow">Immediately below each question</label>
                  </div>
                </div>
              </div>

              <!-- 4. Action Buttons -->
              <div class="d-grid gap-2">
                <button class="btn btn-warning btn-lg fw-bold text-dark shadow-sm py-3" id="btnShare" onclick="triggerShare(false)">
                  <i class="fa-brands fa-telegram fs-4 align-middle me-2"></i>Post Complete Quiz on Telegram
                </button>
                <button class="btn btn-outline-secondary" onclick="refreshPreview()">
                  <i class="fa-solid fa-rotate me-1"></i>Refresh Preview
                </button>
              </div>

            </div>
          </div>
        </div>

        <!-- Preview Panel (Right) -->
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold py-3 d-flex flex-wrap justify-content-between align-items-center">
              <div>
                <i class="fa-solid fa-eye text-primary me-2"></i>Live Telegram Message Preview
              </div>
              <div id="previewMeta" class="d-flex gap-2 align-items-center mt-2 mt-md-0">
                <span class="badge bg-secondary" id="metaParts">0 Parts</span>
                <span class="badge bg-primary" id="metaQuestions">0 Questions</span>
              </div>
            </div>
            <div class="card-body p-4 bg-light">

              <!-- Preview Loading Spinner -->
              <div id="previewLoading" class="text-center py-5 d-none">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2">Generating Telegram quiz preview...</p>
              </div>

              <!-- Empty State -->
              <div id="previewEmpty" class="text-center py-5">
                <i class="fa-brands fa-telegram fa-4x text-muted opacity-50 mb-3"></i>
                <h5 class="text-muted fw-bold">Select a Quiz to Preview</h5>
                <p class="text-muted">Choose a quiz set from the left panel to preview the formatted Telegram messages.</p>
              </div>

              <!-- Preview Tabs & Content -->
              <div id="previewContainer" class="d-none">
                <ul class="nav nav-pills mb-3" id="partsTab" role="tablist"></ul>
                <div class="tab-content" id="partsTabContent"></div>
              </div>

            </div>
          </div>
        </div>
      </div>

      <!-- Sharing History Section -->
      <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-clock-rotate-left text-secondary me-2"></i>Quiz Text Sharing History</span>
          <button class="btn btn-outline-secondary btn-sm" onclick="loadHistory()">
            <i class="fa-solid fa-rotate me-1"></i>Refresh History
          </button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Date &amp; Time</th>
                  <th>Quiz Title</th>
                  <th>Destination Group</th>
                  <th>Questions</th>
                  <th>Parts</th>
                  <th>Status</th>
                  <th class="pe-3 text-end">Action</th>
                </tr>
              </thead>
              <tbody id="historyTableBody">
                <tr><td colspan="7" class="text-center py-4 text-muted">Loading history...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Promotional Branding Settings                         -->
<!-- ============================================================ -->
<div class="modal fade" id="promoModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fa-solid fa-bullhorn me-2"></i>Telegram Promotion &amp; Branding</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">
          These details are automatically attached to every shared quiz invitation and completion card.
        </p>

        <div class="mb-3">
          <label class="form-label fw-bold">Telegram Group Name *</label>
          <input type="text" class="form-control" id="txtGroupName" value="<?= htmlspecialchars($promo['group_name']) ?>" placeholder="e.g. Bankers Lifes❤️">
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Telegram Group Invite Link *</label>
          <input type="url" class="form-control" id="txtGroupLink" value="<?= htmlspecialchars($promo['group_link']) ?>" placeholder="e.g. https://t.me/yourgroup">
          <small class="text-muted">Must be a valid Telegram link (https://t.me/...)</small>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-sm-6">
            <label class="form-label fw-bold">Personal Telegram Username *</label>
            <div class="input-group">
              <span class="input-group-text">@</span>
              <input type="text" class="form-control" id="txtAdminUsername" value="<?= htmlspecialchars(ltrim($promo['admin_username'], '@')) ?>" placeholder="Abhijeet_Mishra">
            </div>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-bold">Personal User ID <small class="text-muted">(Optional)</small></label>
            <input type="text" class="form-control" id="txtAdminId" value="<?= htmlspecialchars($promo['admin_id']) ?>" placeholder="e.g. 9272349548">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Promotional Footer Message</label>
          <textarea class="form-control" id="txtFooterMsg" rows="2"><?= htmlspecialchars($promo['footer_message']) ?></textarea>
        </div>

        <div id="promoModalAlert"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary fw-bold" onclick="savePromoSettings()">
          <i class="fa-solid fa-save me-1"></i>Save Branding Details
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Add New Telegram Group                                -->
<!-- ============================================================ -->
<div class="modal fade" id="addChatModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="fa-solid fa-plus-circle text-warning me-2"></i>Add Telegram Group by Chat ID</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info small mb-3">
          <i class="fa-solid fa-circle-info me-1"></i>
          <strong>Important:</strong> Ensure your bot is added to the group first with permission to send messages.
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Telegram Chat ID *</label>
          <input type="text" class="form-control font-monospace" id="newChatId" placeholder="-1004457706126">
          <small class="text-muted">Group Chat IDs usually begin with a minus sign (e.g. <code>-100...</code>).</small>
        </div>

        <div id="addChatAlert"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success fw-bold" id="btnVerifyChat" onclick="verifyAndAddChat()">
          <i class="fa-solid fa-check-circle me-1"></i>Verify &amp; Add Group
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================ -->
<!-- MODAL: Duplicate Share Confirmation (Text Quiz)             -->
<!-- ============================================================ -->
<div class="modal fade" id="duplicateModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i>Duplicate Share Warning</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="duplicateWarningText" class="mb-3"></p>
        <p class="text-muted small mb-0">Are you sure you want to share this quiz to the same group again?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning fw-bold text-dark" onclick="triggerShare(true)">
          <i class="fa-solid fa-share me-1"></i>Yes, Share Again
        </button>
      </div>
    </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<!-- Custom JavaScript for Telegram Playable Quiz Sharing System -->
<script>
const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';
let liveMonitorInterval = null;
let currentActiveSessionId = 0;

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
  updateGroupSelectionCount();
  loadPlayableQuizzesList();
  checkDaemonStatus();
  setInterval(checkDaemonStatus, 10000);
  
  // Auto-select if URL parameters were passed
  if (document.getElementById('playableQuizSelect').value) {
    onPlayableQuizSelected();
  }
});

function checkDaemonStatus() {
  fetch('../../api/tg_quiz/share.php?action=daemon_status')
    .then(r => r.json())
    .then(data => {
      const lbl = document.getElementById('lblDaemonStatus');
      const btn = document.getElementById('btnStartDaemon');
      if (!lbl || !btn) return;
      if (data.is_active) {
        lbl.className = 'badge bg-success p-2 text-white';
        lbl.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i>Bot Service: Active';
        btn.classList.add('d-none');
      } else {
        lbl.className = 'badge bg-warning p-2 text-dark';
        lbl.innerHTML = '<i class="fa-solid fa-circle-pause me-1"></i>Bot Service: Stopped';
        btn.classList.remove('d-none');
      }
    })
    .catch(() => {});
}

function startDaemonNow() {
  const btn = document.getElementById('btnStartDaemon');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Starting...';
  }

  const formData = new URLSearchParams();
  formData.append('csrf_token', CSRF_TOKEN);

  fetch('../../api/tg_quiz/share.php?action=start_daemon', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: formData.toString()
  })
    .then(r => r.json())
    .then(data => {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-play me-1"></i>Start Bot Service';
      }
      if (data.success) {
        showAlert('success', data.message);
        checkDaemonStatus();
      } else {
        showAlert('info', 'Please double-click <code>start_quiz_bot.bat</code> in the website root directory to launch the Telegram service.');
      }
    })
    .catch(() => {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-play me-1"></i>Start Bot Service';
      }
    });
}

function showAlert(type, message) {
  const box = document.getElementById('alertBox');
  box.innerHTML = `
    <div class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert">
      <i class="fa-solid fa-${type === 'success' ? 'circle-check' : 'triangle-exclamation'} me-2"></i>
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  `;
}

// ─────────────────────────────────────────────────────────────
// 1. PLAYABLE QUIZ SELECTION & MULTI-GROUP MANAGEMENT
// ─────────────────────────────────────────────────────────────
function onPlayableQuizSelected() {
  const sel = document.getElementById('playableQuizSelect');
  const val = sel.value;
  const badge = document.getElementById('playableQuizBadge');

  if (!val) {
    badge.classList.add('d-none');
    document.getElementById('playablePreviewEmpty').classList.remove('d-none');
    document.getElementById('playablePreviewCard').classList.add('d-none');
    return;
  }

  const opt = sel.options[sel.selectedIndex];
  const qCount = opt.getAttribute('data-questions') || 0;
  badge.textContent = `${qCount} Questions`;
  badge.classList.remove('d-none');

  refreshPlayablePreview();
}

function updateGroupSelectionCount() {
  const checked = document.querySelectorAll('.group-checkbox:checked');
  const countEl = document.getElementById('selectedGroupsCount');
  if (countEl) {
    countEl.textContent = `${checked.length} Selected`;
    countEl.className = checked.length > 0 ? 'badge bg-success ms-1' : 'badge bg-secondary ms-1';
  }
}

function toggleAllGroups(check) {
  document.querySelectorAll('.group-checkbox').forEach(cb => {
    cb.checked = check;
  });
  updateGroupSelectionCount();
}

function refreshPlayablePreview() {
  const sel = document.getElementById('playableQuizSelect').value;
  if (!sel) return;

  const [quizType, quizId] = sel.split(':');
  const mode = document.querySelector('input[name="quizMode"]:checked')?.value || 'both';
  const timerSec = document.getElementById('timerSeconds').value || '20';

  document.getElementById('previewModeBadge').textContent = 
    mode === 'both' ? 'Both Modes (Recommended)' : (mode === 'live' ? 'Common Live Quiz Only' : 'Individual Quiz Only');

  fetch(`../../api/tg_quiz/share.php?action=preview_playable&quiz_type=${encodeURIComponent(quizType)}&quiz_id=${encodeURIComponent(quizId)}&mode=${encodeURIComponent(mode)}&timer_seconds=${encodeURIComponent(timerSec)}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        showAlert('danger', data.message || 'Failed to generate invitation preview');
        return;
      }

      document.getElementById('playablePreviewEmpty').classList.add('d-none');
      document.getElementById('playablePreviewCard').classList.remove('d-none');

      // Set text
      const previewTextEl = document.getElementById('playablePreviewText');
      previewTextEl.innerHTML = formatTelegramHtmlForWeb(data.text);

      // Render simulated Telegram inline buttons
      const btnContainer = document.getElementById('simulatedButtonsContainer');
      btnContainer.innerHTML = '';

      if (data.buttons && data.buttons.length > 0) {
        data.buttons.forEach(row => {
          const rowDiv = document.createElement('div');
          rowDiv.className = 'd-flex flex-wrap gap-2 mb-2';
          row.forEach(b => {
            const btnEl = document.createElement('a');
            btnEl.className = 'tg-btn-preview flex-grow-1';
            btnEl.href = '#';
            btnEl.onclick = (e) => { e.preventDefault(); };
            btnEl.innerHTML = b.text;
            rowDiv.appendChild(btnEl);
          });
          btnContainer.appendChild(rowDiv);
        });
      }
    })
    .catch(err => {
      showAlert('danger', 'Error loading playable preview: ' + err.message);
    });
}

function formatTelegramHtmlForWeb(html) {
  // Safe HTML parser for Telegram basic tags
  return html
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/&lt;b&gt;/g, '<b>')
    .replace(/&lt;\/b&gt;/g, '</b>')
    .replace(/&lt;i&gt;/g, '<i>')
    .replace(/&lt;\/i&gt;/g, '</i>')
    .replace(/&lt;code&gt;/g, '<code>')
    .replace(/&lt;\/code&gt;/g, '</code>')
    .replace(/&lt;a href="([^"]+)"&gt;/g, '<a href="$1" target="_blank">')
    .replace(/&lt;\/a&gt;/g, '</a>');
}

// ─────────────────────────────────────────────────────────────
// 2. TRIGGER PLAYABLE QUIZ SHARE
// ─────────────────────────────────────────────────────────────
function triggerPlayableShare() {
  const sel = document.getElementById('playableQuizSelect').value;
  if (!sel) {
    showAlert('warning', 'Please select a quiz to share.');
    document.getElementById('playableQuizSelect').focus();
    return;
  }

  const checkedGroups = Array.from(document.querySelectorAll('.group-checkbox:checked')).map(cb => cb.value);
  if (checkedGroups.length === 0) {
    showAlert('warning', 'Please select at least one destination Telegram group.');
    return;
  }

  const [quizType, quizId] = sel.split(':');
  const mode = document.querySelector('input[name="quizMode"]:checked')?.value || 'both';
  const timerSec = document.getElementById('timerSeconds').value || '20';
  const correctMark = document.getElementById('correctMark').value || '1.0';
  const wrongMark = document.getElementById('wrongMark').value || '0.0';

  const btn = document.getElementById('btnSharePlayable');
  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sharing to Telegram...';

  const formData = new URLSearchParams();
  formData.append('csrf_token', CSRF_TOKEN);
  formData.append('quiz_type', quizType);
  formData.append('quiz_id', quizId);
  formData.append('mode', mode);
  formData.append('timer_seconds', timerSec);
  formData.append('correct_mark', correctMark);
  formData.append('wrong_mark', wrongMark);
  checkedGroups.forEach(cid => formData.append('chat_ids[]', cid));

  fetch('../../api/tg_quiz/share.php?action=share_playable', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: formData.toString()
  })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = origHtml;

      if (!data.success) {
        showAlert('danger', data.message || 'Failed to share playable quiz.');
        return;
      }

      showAlert('success', data.message || 'Playable quiz shared successfully!');
      loadPlayableQuizzesList();
      loadLiveSessionsList();
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = origHtml;
      showAlert('danger', 'Network error sharing playable quiz: ' + err.message);
    });
}

// ─────────────────────────────────────────────────────────────
// 3. LOAD PLAYABLE QUIZZES LIST
// ─────────────────────────────────────────────────────────────
function loadPlayableQuizzesList() {
  fetch('../../api/tg_quiz/share.php?action=playable_quizzes_list')
    .then(r => r.json())
    .then(data => {
      const tbody = document.getElementById('playableQuizzesTableBody');
      if (!data.success || !data.data || data.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No playable quizzes shared yet. Share your first quiz above!</td></tr>';
        return;
      }

      tbody.innerHTML = data.data.map(row => {
        const hasLiveActive = row.active_group_session_id && ['waiting', 'active', 'paused'].includes(row.group_session_status);
        const liveBtnText = hasLiveActive ? 'Open Live Monitor' : 'Start Common Live';
        const liveBtnClass = hasLiveActive ? 'btn-danger' : 'btn-outline-success';

        let modeBadge = '<span class="badge bg-secondary">Both</span>';
        if (row.mode === 'individual') modeBadge = '<span class="badge bg-primary">Individual</span>';
        if (row.mode === 'live') modeBadge = '<span class="badge bg-warning text-dark">Live Group</span>';

        return `
          <tr>
            <td class="ps-3 font-monospace small">#${row.id}</td>
            <td>
              <strong class="text-dark">${escapeHtmlForDisplay(row.quiz_title)}</strong>
              <div class="text-muted small">${row.total_questions} Questions &bull; Token: <code>${row.share_token}</code></div>
            </td>
            <td>
              <i class="fa-solid fa-users text-primary me-1"></i>
              ${escapeHtmlForDisplay(row.chat_title || ('Chat ' + row.chat_id))}
            </td>
            <td>${modeBadge}</td>
            <td><span class="badge bg-light text-dark border">${row.timer_seconds}s</span></td>
            <td>
              <div class="small">
                <span class="badge bg-info text-dark">${row.individual_participants || 0} Individual</span>
                <span class="badge bg-danger ms-1">${row.live_participants || 0} Live</span>
              </div>
            </td>
            <td>
              <span class="badge ${row.status === 'ACTIVE' ? 'bg-success' : 'bg-secondary'}">${row.status}</span>
            </td>
            <td class="pe-3 text-end">
              <div class="btn-group btn-group-sm">
                ${row.mode !== 'individual' ? `
                  <button class="btn ${liveBtnClass} fw-bold" onclick="jumpToLiveSession(${row.id}, ${row.active_group_session_id || 0})">
                    <i class="fa-solid fa-satellite-dish me-1"></i>${liveBtnText}
                  </button>
                ` : ''}
                <button class="btn btn-outline-primary" onclick="viewSharedQuizResults(${row.id})">
                  <i class="fa-solid fa-trophy"></i>
                </button>
                <button class="btn btn-outline-danger" onclick="deleteSharedQuizRecord(${row.id})">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    })
    .catch(() => {
      document.getElementById('playableQuizzesTableBody').innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">Failed to load playable quizzes.</td></tr>';
    });
}

function deleteSharedQuizRecord(sharedQuizId) {
  if (!confirm('Are you sure you want to delete this shared quiz record?')) return;

  const formData = new URLSearchParams();
  formData.append('csrf_token', CSRF_TOKEN);
  formData.append('shared_quiz_id', sharedQuizId);

  fetch('../../api/tg_quiz/share.php?action=delete_shared_quiz', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: formData.toString()
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showAlert('success', 'Shared quiz record removed.');
        loadPlayableQuizzesList();
      } else {
        showAlert('danger', data.message || 'Failed to delete record.');
      }
    });
}

// ─────────────────────────────────────────────────────────────
// 4. COMMON LIVE GROUP QUIZ MONITOR & CONTROLS
// ─────────────────────────────────────────────────────────────
function jumpToLiveSession(sharedQuizId, activeGroupId) {
  // Switch to Tab 2
  const tabEl = document.getElementById('tab-live-btn');
  const tab = new bootstrap.Tab(tabEl);
  tab.show();

  loadLiveSessionsList(activeGroupId || sharedQuizId);
}

function loadLiveSessionsList(preselectId = 0) {
  fetch('../../api/tg_quiz/share.php?action=playable_quizzes_list')
    .then(r => r.json())
    .then(data => {
      const sel = document.getElementById('liveSessionSelect');
      const leadSel = document.getElementById('leaderboardSessionSelect');
      sel.innerHTML = '<option value="">-- Select Active Live Session --</option>';
      leadSel.innerHTML = '<option value="">-- Choose Live Session --</option>';

      if (data.success && data.data) {
        data.data.forEach(q => {
          if (q.mode === 'individual') return;
          const label = `${q.quiz_title} [Group: ${q.chat_title || q.chat_id}]`;
          
          const opt1 = new Option(label, q.id);
          opt1.setAttribute('data-shared-id', q.id);
          opt1.setAttribute('data-session-id', q.active_group_session_id || 0);
          sel.add(opt1);

          const opt2 = new Option(label, q.active_group_session_id || q.id);
          leadSel.add(opt2);
        });

        if (preselectId > 0) {
          sel.value = preselectId;
          onLiveSessionChanged();
        } else if (sel.options.length > 1) {
          sel.selectedIndex = 1;
          onLiveSessionChanged();
        }
      }
    });
}

function onLiveSessionChanged() {
  const sel = document.getElementById('liveSessionSelect');
  const val = sel.value;
  if (!val) {
    clearLiveMonitor();
    return;
  }

  fetchLiveStatusNow();

  // Start polling every 3 seconds for live countdown
  if (liveMonitorInterval) clearInterval(liveMonitorInterval);
  liveMonitorInterval = setInterval(fetchLiveStatusNow, 3000);
}

function clearLiveMonitor() {
  if (liveMonitorInterval) clearInterval(liveMonitorInterval);
  document.getElementById('liveActiveBadge').textContent = 'STANDBY';
  document.getElementById('liveActiveBadge').className = 'badge bg-secondary';
  document.getElementById('liveTimerDisplay').textContent = '--s';
  document.getElementById('liveAnswersCount').textContent = '0';
  document.getElementById('liveQuestionText').textContent = 'Select an active live session to monitor.';
  document.getElementById('liveOptionsList').innerHTML = '';
  document.getElementById('liveSummaryStatus').textContent = 'IDLE';
}

function fetchLiveStatusNow() {
  const sel = document.getElementById('liveSessionSelect');
  const sharedQuizId = sel.value;
  if (!sharedQuizId) return;

  fetch(`../../api/tg_quiz/share.php?action=live_status&shared_quiz_id=${encodeURIComponent(sharedQuizId)}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success || !data.session) {
        document.getElementById('liveSummaryStatus').textContent = 'NOT STARTED';
        document.getElementById('liveActiveBadge').textContent = 'WAITING TO START';
        document.getElementById('liveActiveBadge').className = 'badge bg-warning text-dark';
        document.getElementById('liveQuestionText').textContent = 'Live Quiz has not been started yet. Click "Start Live" below!';
        document.getElementById('liveOptionsList').innerHTML = '';
        return;
      }

      const s = data.session;
      currentActiveSessionId = s.id;

      // Update Header & Status
      document.getElementById('liveSummaryGroup').textContent = s.chat_title || 'Group ' + s.chat_id;
      document.getElementById('liveSummaryStatus').textContent = s.status.toUpperCase();
      document.getElementById('liveSummaryProgress').textContent = `Question ${s.current_question_index} of ${s.total_questions}`;
      document.getElementById('liveSummaryAnswers').textContent = `${s.answers_count || 0} Submissions`;

      const badge = document.getElementById('liveActiveBadge');
      badge.textContent = s.status.toUpperCase();
      if (s.status === 'active') badge.className = 'badge bg-success';
      else if (s.status === 'paused') badge.className = 'badge bg-warning text-dark';
      else if (s.status === 'completed') badge.className = 'badge bg-info text-dark';
      else badge.className = 'badge bg-secondary';

      // Timer & Answers count
      const timeRem = data.time_remaining !== undefined ? data.time_remaining : 0;
      document.getElementById('liveTimerDisplay').textContent = `${timeRem}s`;
      document.getElementById('liveAnswersCount').textContent = s.answers_count || 0;

      // Current Question details
      document.getElementById('liveCardHeaderQuestion').textContent = `📝 Question ${s.current_question_index}/${s.total_questions}`;
      document.getElementById('liveCardHeaderTimer').textContent = `⏱ ${s.timer_seconds}s per question`;

      // Render Question and options
      if (s.current_question_data) {
        try {
          const qData = typeof s.current_question_data === 'string' ? JSON.parse(s.current_question_data) : s.current_question_data;
          document.getElementById('liveQuestionText').textContent = qData.question || 'Live Question';
          
          const optDiv = document.getElementById('liveOptionsList');
          optDiv.innerHTML = '';
          const labels = ['A', 'B', 'C', 'D'];
          (qData.options || []).forEach((opt, idx) => {
            const row = document.createElement('div');
            row.className = 'p-2 border rounded bg-white small fw-semibold';
            row.innerHTML = `<span class="badge bg-secondary me-2">${labels[idx] || (idx+1)}</span> ${escapeHtmlForDisplay(opt)}`;
            optDiv.appendChild(row);
          });
        } catch (e) {}
      }

      // Recent Live Answer Submissions
      const recList = document.getElementById('liveRecentAnswersList');
      if (data.recent_answers && data.recent_answers.length > 0) {
        recList.innerHTML = data.recent_answers.map(a => `
          <div class="d-flex justify-content-between align-items-center py-1 border-bottom small">
            <div>
              <i class="fa-solid fa-user-check text-success me-1"></i>
              <strong>${escapeHtmlForDisplay(a.user_first_name || 'Participant')}</strong>
              ${a.username ? `<span class="text-muted">(@${a.username})</span>` : ''}
            </div>
            <span class="text-muted small">${a.created_at ? a.created_at.split(' ')[1] : ''}</span>
          </div>
        `).join('');
      } else {
        recList.innerHTML = '<div class="text-center text-muted small py-3">No live answers yet.</div>';
      }
    });
}

function triggerLiveAction(action) {
  const sel = document.getElementById('liveSessionSelect');
  const sharedQuizId = sel.value;
  if (!sharedQuizId) {
    showAlert('warning', 'Please select a live session first.');
    return;
  }

  const formData = new URLSearchParams();
  formData.append('csrf_token', CSRF_TOKEN);
  formData.append('shared_quiz_id', sharedQuizId);
  if (currentActiveSessionId > 0) {
    formData.append('group_session_id', currentActiveSessionId);
  }

  let endpoint = '';
  if (action === 'start') endpoint = 'start_live';
  if (action === 'pause') endpoint = 'pause_live';
  if (action === 'resume') endpoint = 'resume_live';
  if (action === 'advance') endpoint = 'advance_live';
  if (action === 'stop') endpoint = 'stop_live';

  fetch(`../../api/tg_quiz/share.php?action=${endpoint}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: formData.toString()
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showAlert('success', data.message || `Live quiz ${action} executed successfully!`);
        fetchLiveStatusNow();
        loadPlayableQuizzesList();
      } else {
        showAlert('danger', data.message || `Failed to execute ${action}`);
      }
    })
    .catch(err => {
      showAlert('danger', 'Error executing live action: ' + err.message);
    });
}

// ─────────────────────────────────────────────────────────────
// 5. RESULTS & LEADERBOARD VIEWS
// ─────────────────────────────────────────────────────────────
function loadResultsTab() {
  loadLeaderboardData();
}

function toggleResultsView(view) {
  const vLive = document.getElementById('viewLiveLeaderboard');
  const vIndiv = document.getElementById('viewIndividualSessions');

  if (view === 'leaderboard') {
    vLive.classList.remove('d-none');
    vIndiv.classList.add('d-none');
    loadLeaderboardData();
  } else {
    vLive.classList.add('d-none');
    vIndiv.classList.remove('d-none');
    loadIndividualSessionsData();
  }
}

function viewSharedQuizResults(sharedQuizId) {
  const tabEl = document.getElementById('tab-results-btn');
  const tab = new bootstrap.Tab(tabEl);
  tab.show();

  // Load individual sessions for this shared quiz
  document.getElementById('resTypeIndividual').checked = true;
  toggleResultsView('individual');
  loadIndividualSessionsData(sharedQuizId);
}

function loadLeaderboardData() {
  const sel = document.getElementById('leaderboardSessionSelect');
  const sessionId = sel.value || currentActiveSessionId;
  const tbody = document.getElementById('leaderboardTableBody');

  if (!sessionId) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Please select a live group session above.</td></tr>';
    return;
  }

  fetch(`../../api/tg_quiz/share.php?action=leaderboard&group_session_id=${encodeURIComponent(sessionId)}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success || !data.leaderboard || data.leaderboard.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No answers recorded yet for this session.</td></tr>';
        return;
      }

      const medals = ['🥇', '🥈', '🥉'];
      tbody.innerHTML = data.leaderboard.map((r, i) => `
        <tr>
          <td class="ps-3 fw-bold fs-5">${medals[i] || (r.rank + 'th')}</td>
          <td>
            <strong>${escapeHtmlForDisplay(r.name)}</strong>
            <div class="text-muted small">ID: <code>${r.user_id}</code></div>
          </td>
          <td>${r.username ? `<span class="badge bg-light text-dark border">@${escapeHtmlForDisplay(r.username)}</span>` : '<span class="text-muted">-</span>'}</td>
          <td><span class="badge bg-primary fs-6">${r.score} pts</span></td>
          <td><span class="badge bg-success">${r.correct_count} Correct</span></td>
          <td>${r.total_answered} Qs</td>
          <td><span class="badge bg-secondary font-monospace">${r.total_time}s</span></td>
        </tr>
      `).join('');
    })
    .catch(() => {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Failed to load leaderboard.</td></tr>';
    });
}

function loadIndividualSessionsData(sharedQuizId = 0) {
  const tbody = document.getElementById('individualSessionsTableBody');
  tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Loading individual participants...</td></tr>';

  let url = '../../api/tg_quiz/share.php?action=individual_sessions';
  if (sharedQuizId > 0) url += `&shared_quiz_id=${encodeURIComponent(sharedQuizId)}`;

  fetch(url)
    .then(r => r.json())
    .then(data => {
      if (!data.success || !data.sessions || data.sessions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No individual quiz participants recorded yet.</td></tr>';
        return;
      }

      tbody.innerHTML = data.sessions.map(s => {
        const accuracy = (s.total_questions > 0) ? Math.round((s.correct_answers / s.total_questions) * 100) : 0;
        return `
          <tr>
            <td class="ps-3 font-monospace small"><code>${s.session_token.substring(0, 12)}...</code></td>
            <td>
              <strong>${escapeHtmlForDisplay(s.user_first_name || 'User')}</strong>
              ${s.username ? `<div class="text-muted small">@${s.username}</div>` : ''}
            </td>
            <td>${escapeHtmlForDisplay(s.quiz_title)}</td>
            <td>${escapeHtmlForDisplay(s.chat_title || ('Chat ' + s.origin_chat_id))}</td>
            <td><span class="badge bg-secondary">Q ${s.current_question}/${s.total_questions}</span></td>
            <td><strong>${s.score}</strong></td>
            <td><span class="badge ${accuracy >= 70 ? 'bg-success' : (accuracy >= 40 ? 'bg-warning text-dark' : 'bg-danger')}">${accuracy}%</span></td>
            <td><span class="badge ${s.status === 'completed' ? 'bg-success' : 'bg-primary'}">${s.status}</span></td>
            <td class="pe-3 small text-muted">${s.completed_at || '-'}</td>
          </tr>
        `;
      }).join('');
    })
    .catch(() => {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Failed to load individual sessions.</td></tr>';
    });
}

// ─────────────────────────────────────────────────────────────
// 6. REPORTING & ANALYTICS DASHBOARD
// ─────────────────────────────────────────────────────────────
function loadReportingStats() {
  fetch('../../api/tg_quiz/share.php?action=reporting_stats')
    .then(r => r.json())
    .then(data => {
      if (!data.success || !data.stats) return;
      const s = data.stats;

      document.getElementById('statTotalShared').textContent = s.total_playable_shares || 0;
      document.getElementById('statGroupsCount').textContent = s.authorized_groups_count || 0;
      document.getElementById('statIndivUsers').textContent = s.individual_participants_count || 0;
      document.getElementById('statLiveUsers').textContent = s.live_participants_count || 0;

      const accuracy = s.average_accuracy || 0;
      document.getElementById('statAvgAccuracy').textContent = `${accuracy}%`;
      document.getElementById('statAvgAccuracyBar').style.width = `${accuracy}%`;

      const compRate = s.individual_completion_rate || 0;
      document.getElementById('statCompletionRate').textContent = `${compRate}%`;
      document.getElementById('statCompletionRateBar').style.width = `${compRate}%`;

      document.getElementById('statTotalAnswers').textContent = s.total_answers_recorded || 0;
      document.getElementById('statActiveSessionsCount').textContent = s.active_group_sessions || 0;
    });
}

// ─────────────────────────────────────────────────────────────
// 7. COMPLETE TEXT QUIZ SET SHARING (PRESERVED FROM MODULE 2)
// ─────────────────────────────────────────────────────────────
let currentPreviewData = null;

function onTextQuizTabOpen() {
  loadHistory();
  if (document.getElementById('quizSelect').value) {
    onQuizSelected();
  }
}

function onQuizSelected() {
  const sel = document.getElementById('quizSelect');
  const val = sel.value;
  const badge = document.getElementById('quizBadge');

  if (!val) {
    badge.classList.add('d-none');
    document.getElementById('previewEmpty').classList.remove('d-none');
    document.getElementById('previewContainer').classList.add('d-none');
    document.getElementById('metaParts').textContent = '0 Parts';
    document.getElementById('metaQuestions').textContent = '0 Questions';
    return;
  }

  const opt = sel.options[sel.selectedIndex];
  const qCount = opt.getAttribute('data-questions') || 0;
  badge.textContent = `${qCount} Questions`;
  badge.classList.remove('d-none');

  refreshPreview();
}

function refreshPreview() {
  const sel = document.getElementById('quizSelect');
  const val = sel.value;
  if (!val) return;

  const [quizType, quizId] = sel.split(':');
  const incExpl = document.getElementById('chkExplanation').checked;
  const explPos = document.querySelector('input[name="explPos"]:checked')?.value || 'answer_key';

  document.getElementById('explPosWrapper').style.opacity = incExpl ? '1' : '0.5';

  document.getElementById('previewEmpty').classList.add('d-none');
  document.getElementById('previewContainer').classList.add('d-none');
  document.getElementById('previewLoading').classList.remove('d-none');

  fetch(`../../api/tg_quiz/share.php?action=preview&quiz_type=${encodeURIComponent(quizType)}&quiz_id=${encodeURIComponent(quizId)}&include_explanation=${incExpl}&explanation_position=${encodeURIComponent(explPos)}`)
    .then(r => r.json())
    .then(data => {
      document.getElementById('previewLoading').classList.add('d-none');
      if (!data.success) {
        showAlert('danger', data.message || 'Failed to generate preview');
        return;
      }
      renderPreview(data);
    })
    .catch(err => {
      document.getElementById('previewLoading').classList.add('d-none');
      showAlert('danger', 'Error loading preview: ' + err.message);
    });
}

function renderPreview(data) {
  currentPreviewData = data;
  const preview = data.preview;
  const totalParts = preview.total_parts || 0;
  const totalQ = preview.total_questions || 0;

  document.getElementById('metaParts').textContent = `${totalParts} Part${totalParts > 1 ? 's' : ''}`;
  document.getElementById('metaQuestions').textContent = `${totalQ} Question${totalQ > 1 ? 's' : ''}`;

  const tabs = document.getElementById('partsTab');
  const contents = document.getElementById('partsTabContent');
  tabs.innerHTML = '';
  contents.innerHTML = '';

  if (totalParts === 0) {
    document.getElementById('previewEmpty').classList.remove('d-none');
    return;
  }

  preview.messages.forEach((msg, idx) => {
    const partNum = idx + 1;
    const charLen = msg.length;
    const isOver = charLen > 4096;

    const li = document.createElement('li');
    li.className = 'nav-item';
    li.innerHTML = `
      <button class="nav-link ${idx === 0 ? 'active' : ''} fw-bold" id="part-tab-${partNum}" data-bs-toggle="pill" data-bs-target="#part-content-${partNum}" type="button">
        Part ${partNum}/${totalParts}
        <span class="badge ${isOver ? 'bg-danger' : 'bg-secondary'} ms-1">${charLen} chars</span>
      </button>
    `;
    tabs.appendChild(li);

    const div = document.createElement('div');
    div.className = `tab-pane fade ${idx === 0 ? 'show active' : ''}`;
    div.id = `part-content-${partNum}`;
    div.innerHTML = `
      <div class="card bg-white border shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2">
          <small class="fw-bold"><i class="fa-brands fa-telegram text-info me-1"></i>Telegram Message — Part ${partNum} of ${totalParts}</small>
          <small class="${isOver ? 'text-danger fw-bold' : 'text-light'}">${charLen} / 4096 characters</small>
        </div>
        <div class="card-body p-3 font-monospace" style="white-space: pre-wrap; font-size: 0.9rem; max-height: 500px; overflow-y: auto; background-color: #fcfcfc;">${escapeHtmlForDisplay(msg)}</div>
      </div>
    `;
    contents.appendChild(div);
  });

  document.getElementById('previewContainer').classList.remove('d-none');
}

function triggerShare(confirmDuplicate = false) {
  const sel = document.getElementById('quizSelect').value;
  const chat = document.getElementById('chatSelect').value;

  if (!sel) {
    showAlert('warning', 'Please select a quiz to share.');
    document.getElementById('quizSelect').focus();
    return;
  }
  if (!chat) {
    showAlert('warning', 'Please select a destination Telegram group.');
    document.getElementById('chatSelect').focus();
    return;
  }

  const [quizType, quizId] = sel.split(':');
  const incExpl = document.getElementById('chkExplanation').checked;
  const explPos = document.querySelector('input[name="explPos"]:checked')?.value || 'answer_key';

  const btn = document.getElementById('btnShare');
  const origText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Posting to Telegram...';

  const dupModalEl = document.getElementById('duplicateModal');
  const dupModal = bootstrap.Modal.getInstance(dupModalEl);
  if (dupModal) dupModal.hide();

  const formData = new URLSearchParams();
  formData.append('csrf_token', CSRF_TOKEN);
  formData.append('quiz_type', quizType);
  formData.append('quiz_id', quizId);
  formData.append('chat_id', chat);
  formData.append('confirm_duplicate', confirmDuplicate ? '1' : '0');
  formData.append('include_explanation', incExpl ? '1' : '0');
  formData.append('explanation_position', explPos);

  fetch('../../api/tg_quiz/share.php?action=share', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: formData.toString()
  })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = origText;

      if (!data.success) {
        if (data.is_duplicate_warning) {
          document.getElementById('duplicateWarningText').textContent = data.message;
          new bootstrap.Modal(document.getElementById('duplicateModal')).show();
        } else {
          showAlert('danger', data.message || 'Failed to share quiz');
        }
        return;
      }

      showAlert('success', data.message);
      loadHistory();
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = origText;
      showAlert('danger', 'Network error sharing quiz: ' + err.message);
    });
}

function loadHistory() {
  fetch('../../api/tg_quiz/share.php?action=history')
    .then(r => r.json())
    .then(data => {
      const tbody = document.getElementById('historyTableBody');
      if (!data.success || !data.history || data.history.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No complete quizzes shared yet.</td></tr>';
        return;
      }

      tbody.innerHTML = data.history.map(row => {
        const isSuccess = row.status === 'SUCCESS';
        const dateStr = row.created_at ? new Date(row.created_at.replace(' ', 'T')).toLocaleString() : '-';
        return `
          <tr>
            <td class="ps-3 small text-muted">${dateStr}</td>
            <td class="fw-semibold">
              ${escapeHtmlForDisplay(row.quiz_title)}
              <span class="badge ${row.quiz_type === 'tg_set' ? 'bg-primary' : 'bg-info text-dark'} ms-1" style="font-size: 0.7rem;">
                ${row.quiz_type === 'tg_set' ? 'TG Set' : 'Mock Test'}
              </span>
            </td>
            <td>${escapeHtmlForDisplay(row.chat_title || ('Chat ' + row.chat_id))}</td>
            <td><span class="badge bg-secondary">${row.total_questions} Qs</span></td>
            <td><span class="badge bg-light text-dark border">${row.parts_count} Part${row.parts_count > 1 ? 's' : ''}</span></td>
            <td>
              <span class="badge ${isSuccess ? 'bg-success' : 'bg-danger'}">
                <i class="fa-solid fa-${isSuccess ? 'check' : 'times'} me-1"></i>${row.status}
              </span>
              ${row.error_message ? `<div class="text-danger small" style="max-width:200px;">${escapeHtmlForDisplay(row.error_message)}</div>` : ''}
            </td>
            <td class="pe-3 text-end">
              <button class="btn btn-outline-primary btn-sm" onclick="reShareFromHistory('${row.quiz_type}', ${row.quiz_id}, '${row.chat_id}')">
                <i class="fa-solid fa-redo me-1"></i>Share Again
              </button>
            </td>
          </tr>
        `;
      }).join('');
    })
    .catch(() => {
      document.getElementById('historyTableBody').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Failed to load history.</td></tr>';
    });
}

function reShareFromHistory(type, id, chatId) {
  const selVal = `${type}:${id}`;
  const quizSel = document.getElementById('quizSelect');
  quizSel.value = selVal;
  onQuizSelected();

  const chatSel = document.getElementById('chatSelect');
  chatSel.value = chatId;

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ─────────────────────────────────────────────────────────────
// 8. BRANDING MODAL & ADD GROUP MODAL
// ─────────────────────────────────────────────────────────────
function openPromoModal() {
  document.getElementById('promoModalAlert').innerHTML = '';
  new bootstrap.Modal(document.getElementById('promoModal')).show();
}

function savePromoSettings() {
  const gName = document.getElementById('txtGroupName').value.trim();
  const gLink = document.getElementById('txtGroupLink').value.trim();
  const aUser = document.getElementById('txtAdminUsername').value.trim();
  const aId   = document.getElementById('txtAdminId').value.trim();
  const fMsg  = document.getElementById('txtFooterMsg').value.trim();

  if (!gName || !gLink) {
    document.getElementById('promoModalAlert').innerHTML = `
      <div class="alert alert-danger small mb-0 mt-3">Group Name and Invite Link are required.</div>
    `;
    return;
  }

  const formData = new URLSearchParams();
  formData.append('csrf_token', CSRF_TOKEN);
  formData.append('group_name', gName);
  formData.append('group_link', gLink);
  formData.append('admin_username', aUser);
  formData.append('admin_id', aId);
  formData.append('footer_message', fMsg);

  fetch('../../api/tg_quiz/share.php?action=save_promo_settings', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: formData.toString()
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const modal = bootstrap.Modal.getInstance(document.getElementById('promoModal'));
        if (modal) modal.hide();
        showAlert('success', 'Promotion & branding details updated successfully!');
        refreshPlayablePreview();
      } else {
        document.getElementById('promoModalAlert').innerHTML = `
          <div class="alert alert-danger small mb-0 mt-3">${data.message || 'Failed to save settings'}</div>
        `;
      }
    })
    .catch(err => {
      document.getElementById('promoModalAlert').innerHTML = `
        <div class="alert alert-danger small mb-0 mt-3">Error: ${err.message}</div>
      `;
    });
}

function openAddChatModal() {
  document.getElementById('newChatId').value = '';
  document.getElementById('addChatAlert').innerHTML = '';
  new bootstrap.Modal(document.getElementById('addChatModal')).show();
}

function verifyAndAddChat() {
  const chatId = document.getElementById('newChatId').value.trim();
  if (!chatId) {
    document.getElementById('addChatAlert').innerHTML = `
      <div class="alert alert-danger small mb-0 mt-3">Please enter a Chat ID.</div>
    `;
    return;
  }

  const btn = document.getElementById('btnVerifyChat');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Verifying...';

  const formData = new URLSearchParams();
  formData.append('csrf_token', CSRF_TOKEN);
  formData.append('chat_id', chatId);

  fetch('../../api/tg_quiz/share.php?action=verify_chat', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: formData.toString()
  })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i>Verify &amp; Add Group';

      if (!data.success) {
        document.getElementById('addChatAlert').innerHTML = `
          <div class="alert alert-danger small mb-0 mt-3">${data.message || 'Verification failed'}</div>
        `;
        return;
      }

      // Add to select dropdown and select it
      const select = document.getElementById('chatSelect');
      if (select) {
        const opt = new Option(`${data.chat_title} (${data.chat_id})`, data.chat_id, true, true);
        select.add(opt);
      }

      const modal = bootstrap.Modal.getInstance(document.getElementById('addChatModal'));
      if (modal) modal.hide();
      showAlert('success', data.message);
      setTimeout(() => location.reload(), 1000);
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i>Verify &amp; Add Group';
      document.getElementById('addChatAlert').innerHTML = `
        <div class="alert alert-danger small mb-0 mt-3">Error: ${err.message}</div>
      `;
    });
}

function escapeHtmlForDisplay(text) {
  if (!text) return '';
  const d = document.createElement('div');
  d.textContent = text;
  return d.innerHTML;
}
</script>
