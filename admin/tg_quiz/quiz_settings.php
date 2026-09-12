<?php
// admin/tg_quiz/quiz_settings.php
// Quiz Settings & Launch

$page_title = "Quiz Settings & Launch";
require_once '../../includes/db.php';
require_once '../../includes/session.php';

if (!isset($_SESSION['admin_id'])) { header("Location: ../index.php"); exit; }

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$quiz_set_id = (int)($_GET['id'] ?? 0);
$quiz_set = null;
if ($quiz_set_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM tg_quiz_sets WHERE id=? AND admin_id=?");
        $stmt->execute([$quiz_set_id, $_SESSION['admin_id']]);
        $quiz_set = $stmt->fetch();
    } catch (PDOException $e) {}
}

// Fetch all quiz sets for selector
$all_sets = [];
try {
    $stmt2 = $pdo->prepare("SELECT id, title, total_questions, default_timer FROM tg_quiz_sets WHERE admin_id=? AND is_active=1 ORDER BY created_at DESC");
    $stmt2->execute([$_SESSION['admin_id']]);
    $all_sets = $stmt2->fetchAll();
} catch (PDOException $e) {}

// Fetch verified chats
$chats = [];
try {
    $stmt3 = $pdo->prepare("SELECT * FROM tg_chats WHERE admin_id=? AND is_verified=1 ORDER BY created_at DESC");
    $stmt3->execute([$_SESSION['admin_id']]);
    $chats = $stmt3->fetchAll();
} catch (PDOException $e) {}

require_once '../includes/header.php';
?>

<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
    <div>
      <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
      <h4 class="mb-0 d-inline"><i class="fa-solid fa-gear text-warning me-2"></i>Quiz Settings & Launch</h4>
    </div>
    <div>
      <a href="quiz_sets.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
    </div>
  </div>

  <?php if (empty($all_sets)): ?>
  <div class="alert alert-warning">
    No quiz sets found. <a href="quiz_sets.php" class="fw-bold">Create a quiz set first.</a>
  </div>
  <?php elseif (empty($chats)): ?>
  <div class="alert alert-warning">
    No verified chats found. <a href="index.php" class="fw-bold">Verify a Telegram chat first.</a>
  </div>
  <?php else: ?>

  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-dark text-white fw-bold py-3">
          <i class="fa-solid fa-rocket me-2"></i>Configure & Start Quiz
        </div>
        <div class="card-body p-4">

          <!-- Quiz Title -->
          <div class="mb-4">
            <label class="form-label fw-bold">Quiz Session Title</label>
            <input type="text" class="form-control form-control-lg" id="quizTitle"
                   placeholder="e.g. Banking Awareness Quiz - Session 1">
            <small class="text-muted">Custom title for this session (optional)</small>
          </div>

          <!-- Quiz Set -->
          <div class="mb-4">
            <label class="form-label fw-bold">Select Quiz Set *</label>
            <select class="form-select form-select-lg" id="quizSetSelect" onchange="updateSetInfo()">
              <option value="">-- Select Quiz Set --</option>
              <?php foreach ($all_sets as $s): ?>
              <option value="<?= $s['id'] ?>"
                data-q="<?= $s['total_questions'] ?>"
                data-timer="<?= $s['default_timer'] ?>"
                data-title="<?= htmlspecialchars($s['title']) ?>"
                <?= $quiz_set_id == $s['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['title']) ?>
                (<?= $s['total_questions'] ?> Questions)
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Quiz set info -->
          <div id="setInfoBox" class="alert alert-info d-none mb-4">
            <i class="fa-solid fa-circle-info me-2"></i>
            <span id="setInfoText"></span>
          </div>

          <!-- Telegram Chat -->
          <div class="mb-4">
            <label class="form-label fw-bold">Telegram Group / Channel *</label>
            <select class="form-select form-select-lg" id="chatSelect">
              <option value="">-- Select Verified Chat --</option>
              <?php foreach ($chats as $c): ?>
              <option value="<?= $c['chat_id'] ?>">
                <?= htmlspecialchars($c['chat_title'] ?? 'Chat ' . $c['chat_id']) ?>
                (<?= $c['chat_id'] ?>)
                <?= $c['can_send_polls'] ? '✓' : '⚠' ?>
              </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Chat not listed? <a href="index.php">Verify it first</a>.</small>
          </div>

          <div class="row g-3 mb-4">
            <!-- Question Order -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Question Order</label>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="qOrder" id="orderOriginal" value="original" checked>
                  <label class="form-check-label" for="orderOriginal">Original Order</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="qOrder" id="orderRandom" value="random">
                  <label class="form-check-label" for="orderRandom">Random Order</label>
                </div>
              </div>
            </div>

            <!-- Timer -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Timer per Question</label>
              <div class="d-flex gap-2 align-items-center">
                <select class="form-select" id="timerSelect" onchange="checkCustomTimer()">
                  <option value="5">5 seconds</option>
                  <option value="10">10 seconds</option>
                  <option value="15" selected>15 seconds</option>
                  <option value="20">20 seconds</option>
                  <option value="30">30 seconds</option>
                  <option value="60">60 seconds</option>
                  <option value="custom">Custom...</option>
                </select>
                <input type="number" class="form-control d-none" id="customTimer" min="5" max="300" placeholder="sec" style="width:80px">
              </div>
            </div>
          </div>

          <!-- Question Range -->
          <div class="mb-4">
            <label class="form-label fw-bold">Questions to Send</label>
            <div class="mb-2">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="qRange" id="rangeAll" value="all" checked onchange="toggleRange()">
                <label class="form-check-label" for="rangeAll">All Questions</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="qRange" id="rangeCustom" value="custom" onchange="toggleRange()">
                <label class="form-check-label" for="rangeCustom">Custom Range</label>
              </div>
            </div>
            <div id="rangeInputs" class="d-none">
              <div class="d-flex align-items-center gap-2">
                <div>
                  <label class="form-label small">From Question #</label>
                  <input type="number" class="form-control" id="rangeStart" value="1" min="1">
                </div>
                <div class="mt-4 pt-2">to</div>
                <div>
                  <label class="form-label small">To Question #</label>
                  <input type="number" class="form-control" id="rangeEnd" min="1" placeholder="e.g. 100">
                </div>
              </div>
            </div>
          </div>

          <!-- Launch Preview -->
          <div id="launchPreview" class="alert alert-warning d-none mb-4">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <strong>Ready to Launch:</strong> <span id="previewText"></span>
          </div>

          <div id="launchResult" class="mb-3"></div>

          <!-- Start Button -->
          <div class="d-grid">
            <button class="btn btn-success btn-lg fw-bold py-3" id="startBtn" onclick="startQuiz()">
              <i class="fa-solid fa-play me-2"></i>START QUIZ
            </button>
          </div>

        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
const CSRF   = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
const API    = '<?= BASE_URL ?>api/tg_quiz/session.php';

function updateSetInfo() {
  const sel = document.getElementById('quizSetSelect');
  const opt = sel.selectedOptions[0];
  if (!opt || !opt.value) { document.getElementById('setInfoBox').classList.add('d-none'); return; }
  const q = opt.dataset.q;
  const timer = opt.dataset.timer;
  document.getElementById('timerSelect').value = [5,10,15,20,30,60].includes(parseInt(timer)) ? timer : 'custom';
  if (document.getElementById('timerSelect').value === 'custom') {
    document.getElementById('customTimer').classList.remove('d-none');
    document.getElementById('customTimer').value = timer;
  }
  document.getElementById('setInfoText').textContent = `${q} questions available`;
  document.getElementById('rangeEnd').placeholder = `max ${q}`;
  document.getElementById('setInfoBox').classList.remove('d-none');
  updatePreview();
}

function checkCustomTimer() {
  const v = document.getElementById('timerSelect').value;
  document.getElementById('customTimer').classList.toggle('d-none', v !== 'custom');
}

function toggleRange() {
  const custom = document.querySelector('input[name="qRange"]:checked').value === 'custom';
  document.getElementById('rangeInputs').classList.toggle('d-none', !custom);
}

function getTimer() {
  const v = document.getElementById('timerSelect').value;
  return v === 'custom' ? (parseInt(document.getElementById('customTimer').value) || 15) : parseInt(v);
}

function updatePreview() {
  const setOpt = document.getElementById('quizSetSelect').selectedOptions[0];
  const chatOpt = document.getElementById('chatSelect').selectedOptions[0];
  if (!setOpt?.value || !chatOpt?.value) return;
  const timer = getTimer();
  const q = setOpt.dataset.q;
  const mins = Math.ceil(q * timer / 60);
  document.getElementById('previewText').textContent =
    `${setOpt.text.split('(')[0].trim()} → ${chatOpt.text.split('(')[0].trim()} | Timer: ${timer}s/Q | Est. ${mins} min`;
  document.getElementById('launchPreview').classList.remove('d-none');
}

document.getElementById('quizSetSelect')?.addEventListener('change', updatePreview);
document.getElementById('chatSelect')?.addEventListener('change', updatePreview);
document.getElementById('timerSelect')?.addEventListener('change', updatePreview);

async function startQuiz() {
  const quiz_set_id = document.getElementById('quizSetSelect').value;
  const chat_id     = document.getElementById('chatSelect').value;
  const title       = document.getElementById('quizTitle').value;
  const order       = document.querySelector('input[name="qOrder"]:checked').value;
  const timer       = getTimer();
  const rangeType   = document.querySelector('input[name="qRange"]:checked').value;
  const start_q     = rangeType === 'custom' ? (parseInt(document.getElementById('rangeStart').value)||1) : 1;
  const end_q       = rangeType === 'custom' ? (parseInt(document.getElementById('rangeEnd').value)||0) : 0;

  if (!quiz_set_id) return alert('Please select a quiz set');
  if (!chat_id)     return alert('Please select a Telegram chat');
  if (timer < 5)    return alert('Timer must be at least 5 seconds');

  const btn = document.getElementById('startBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Starting...';

  const fd = new FormData();
  fd.append('action','start'); fd.append('csrf_token',CSRF);
  fd.append('quiz_set_id', quiz_set_id); fd.append('chat_id', chat_id);
  fd.append('title', title); fd.append('timer', timer);
  fd.append('question_order', order);
  fd.append('start_question', start_q); fd.append('end_question', end_q);

  const res = await fetch(API, {method:'POST', body:fd}).then(r=>r.json());
  const el = document.getElementById('launchResult');
  el.innerHTML = `<div class="alert alert-${res.success?'success':'danger'} py-2">${res.message}</div>`;

  if (res.success) {
    btn.innerHTML = '<i class="fa-solid fa-check me-2"></i>Quiz Started!';
    setTimeout(() => {
      window.location.href = `quiz_live.php?session_id=${res.session_id}`;
    }, 1000);
  } else {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-play me-2"></i>START QUIZ';
  }
}

// Init
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('quizSetSelect').value) updateSetInfo();
});
</script>

<?php require_once '../includes/footer.php'; ?>
