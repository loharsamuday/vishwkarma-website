<?php
// admin/tg_quiz/index.php
// Telegram Quiz Manager — Bot & Chat Dashboard

$page_title = "Telegram Quiz Manager";
require_once '../../includes/db.php';
require_once '../../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Add TG_ENCRYPTION_KEY if not defined in config
if (!defined('TG_ENCRYPTION_KEY')) {
    define('TG_ENCRYPTION_KEY', 'default-32-char-key-change-this!!');
}

// Fetch existing bot info
$bot = null;
try {
    $stmt = $pdo->prepare("SELECT id, bot_name, bot_username, bot_id, is_active, last_verified_at FROM tg_bots WHERE admin_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$_SESSION['admin_id']]);
    $bot = $stmt->fetch();
} catch (PDOException $e) { /* tables may not exist yet */ }

// Fetch verified chats
$chats = [];
try {
    $stmt2 = $pdo->prepare("SELECT * FROM tg_chats WHERE admin_id = ? ORDER BY created_at DESC");
    $stmt2->execute([$_SESSION['admin_id']]);
    $chats = $stmt2->fetchAll();
} catch (PDOException $e) {}

// Quick stats
$total_sets = $total_sessions = $running = 0;
$active_sessions = [];
try {
    $total_sets     = $pdo->prepare("SELECT COUNT(*) FROM tg_quiz_sets WHERE admin_id=?")->execute([$_SESSION['admin_id']]) ? $pdo->query("SELECT COUNT(*) FROM tg_quiz_sets WHERE admin_id={$_SESSION['admin_id']}")->fetchColumn() : 0;
    $total_sessions = $pdo->query("SELECT COUNT(*) FROM tg_quiz_sessions WHERE admin_id={$_SESSION['admin_id']}")->fetchColumn();
    $running        = $pdo->query("SELECT COUNT(*) FROM tg_quiz_sessions WHERE admin_id={$_SESSION['admin_id']} AND status='RUNNING'")->fetchColumn();
    $activeStmt = $pdo->prepare("
        SELECT id, title, chat_title, status, questions_sent, total_questions, timer_seconds
        FROM tg_quiz_sessions
        WHERE admin_id = ? AND status IN ('RUNNING', 'PAUSED')
        ORDER BY updated_at DESC
    ");
    $activeStmt->execute([$_SESSION['admin_id']]);
    $active_sessions = $activeStmt->fetchAll();
} catch (PDOException $e) {}

require_once '../includes/header.php';
?>

<div class="main-content">
  <!-- Page Header -->
  <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
    <div>
      <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
      <h4 class="mb-0 d-inline"><i class="fa-brands fa-telegram text-primary me-2"></i>Telegram Quiz Manager</h4>
    </div>
    <div>
      <a href="webhook_manager.php" class="btn btn-outline-primary btn-sm fw-bold">
        <i class="fa-solid fa-link me-1"></i>Webhook Manager
      </a>
      <a href="quiz_sets.php" class="btn btn-warning btn-sm fw-bold ms-2">
        <i class="fa-solid fa-list me-1"></i>Quiz Sets
      </a>
      <a href="quiz_history.php" class="btn btn-outline-secondary btn-sm ms-2">
        <i class="fa-solid fa-clock-rotate-left me-1"></i>History
      </a>
    </div>
  </div>

  <!-- Stats Row -->
  <div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm text-center p-3">
        <i class="fa-solid fa-robot fa-2x text-primary mb-2"></i>
        <h5 class="mb-0"><?= $bot ? '1' : '0' ?></h5>
        <small class="text-muted">Bot Connected</small>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm text-center p-3">
        <i class="fa-solid fa-layer-group fa-2x text-success mb-2"></i>
        <h5 class="mb-0"><?= $total_sets ?></h5>
        <small class="text-muted">Quiz Sets</small>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm text-center p-3">
        <i class="fa-solid fa-circle-play fa-2x text-info mb-2"></i>
        <h5 class="mb-0"><?= $total_sessions ?></h5>
        <small class="text-muted">Total Sessions</small>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm text-center p-3">
        <i class="fa-solid fa-satellite-dish fa-2x <?= $running > 0 ? 'text-success fa-fade' : 'text-secondary' ?> mb-2"></i>
        <h5 class="mb-0"><?= $running ?></h5>
        <small class="text-muted">Running Now</small>
      </div>
    </div>
  </div>

  <?php if (!empty($active_sessions)): ?>
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-success text-white fw-bold">
      <i class="fa-solid fa-circle-play me-2"></i>Running Quizzes
    </div>
    <div class="list-group list-group-flush">
      <?php foreach ($active_sessions as $active): ?>
      <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2" id="active-session-<?= (int)$active['id'] ?>">
        <div>
          <div class="fw-bold"><?= htmlspecialchars($active['title'] ?? 'Telegram Quiz') ?></div>
          <small class="text-muted">
            <?= htmlspecialchars($active['chat_title'] ?? '') ?> ·
            <?= (int)$active['questions_sent'] ?>/<?= (int)$active['total_questions'] ?> questions ·
            <?= (int)$active['timer_seconds'] ?> sec/question ·
            <span class="badge bg-<?= $active['status'] === 'RUNNING' ? 'success' : 'warning' ?>"><?= htmlspecialchars($active['status']) ?></span>
          </small>
        </div>
        <div class="d-flex gap-2">
          <a href="quiz_live.php?session_id=<?= (int)$active['id'] ?>" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-satellite-dish me-1"></i>Monitor
          </a>
          <button type="button" class="btn btn-danger btn-sm" onclick="stopQuiz(<?= (int)$active['id'] ?>, this)">
            <i class="fa-solid fa-stop me-1"></i>Stop Quiz
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div id="stopQuizResult" class="px-3 pt-3"></div>
  </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- BOT SETUP CARD -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white fw-bold">
          <i class="fa-solid fa-robot me-2"></i>Telegram Bot
        </div>
        <div class="card-body">
          <!-- Current bot status -->
          <?php if ($bot && $bot['is_active']): ?>
          <div class="alert alert-success d-flex align-items-center mb-3">
            <i class="fa-solid fa-circle-check me-2 fs-5"></i>
            <div>
              <strong><?= htmlspecialchars($bot['bot_name']) ?></strong>
              <span class="text-muted ms-2">@<?= htmlspecialchars($bot['bot_username']) ?></span><br>
              <small>Last verified: <?= $bot['last_verified_at'] ? date('d M Y H:i', strtotime($bot['last_verified_at'])) : 'Never' ?></small>
            </div>
          </div>
          <div class="d-flex gap-2 mb-3">
            <button class="btn btn-outline-success btn-sm" onclick="testBot()"><i class="fa-solid fa-plug me-1"></i>Test Connection</button>
            <button class="btn btn-outline-danger btn-sm" onclick="disconnectBot()"><i class="fa-solid fa-unlink me-1"></i>Disconnect</button>
          </div>
          <?php else: ?>
          <div class="alert alert-warning mb-3">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>No bot connected.
          </div>
          <?php endif; ?>

          <!-- Connect new bot -->
          <div class="border rounded p-3 bg-light">
            <label class="form-label fw-bold small">Bot Token</label>
            <div class="input-group">
              <input type="password" class="form-control" id="botTokenInput"
                     placeholder="123456:ABCdef..." autocomplete="off">
              <button class="btn btn-primary" onclick="connectBot()">
                <i class="fa-solid fa-link me-1"></i><?= $bot ? 'Update Bot' : 'Connect Bot' ?>
              </button>
            </div>
            <small class="text-muted">
              Get token from <a href="https://t.me/BotFather" target="_blank">@BotFather</a>.
              Token is stored encrypted — never visible in plain text.
            </small>
          </div>
          <div id="botResult" class="mt-3"></div>
        </div>
      </div>
    </div>

    <!-- CHAT SETUP CARD -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-info text-white fw-bold">
          <i class="fa-solid fa-users me-2"></i>Telegram Group / Channel
        </div>
        <div class="card-body">
          <div class="border rounded p-3 bg-light mb-3">
            <label class="form-label fw-bold small">Chat ID</label>
            <div class="input-group">
              <input type="text" class="form-control" id="chatIdInput" placeholder="-1001234567890">
              <button class="btn btn-info text-white" onclick="verifyChat()">
                <i class="fa-solid fa-shield-check me-1"></i>Verify Chat
              </button>
            </div>
            <small class="text-muted">
              Add bot to group → Send any message → Use
              <code>@userinfobot</code> to get Chat ID.
            </small>
          </div>
          <div id="chatResult" class="mb-3"></div>

          <!-- Verified chats list -->
          <?php if (!empty($chats)): ?>
          <h6 class="fw-bold mt-3">Verified Chats</h6>
          <div class="list-group">
            <?php foreach ($chats as $chat): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center py-2">
              <div>
                <i class="fa-solid fa-<?= $chat['chat_type'] === 'channel' ? 'bullhorn' : 'users' ?> text-info me-2"></i>
                <strong><?= htmlspecialchars($chat['chat_title'] ?? 'Unknown') ?></strong>
                <small class="text-muted ms-2"><?= $chat['chat_id'] ?></small>
                <?php if ($chat['can_send_polls']): ?>
                  <span class="badge bg-success ms-1">✓ Polls OK</span>
                <?php else: ?>
                  <span class="badge bg-warning ms-1">⚠ Check Perms</span>
                <?php endif; ?>
              </div>
              <div class="d-flex gap-1">
                <button class="btn btn-outline-primary btn-sm" title="Send a test quiz poll to this group"
                  onclick="sendTestPoll('<?= $chat['chat_id'] ?>', this)">
                  <i class="fa-solid fa-paper-plane me-1"></i>Test Poll
                </button>
                <button class="btn btn-outline-danger btn-sm" title="Remove Chat"
                  onclick="deleteChat(<?= $chat['id'] ?>)">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Bot Permission Guide -->
  <div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-dark text-white fw-bold">
      <i class="fa-solid fa-circle-info me-2"></i>Setup Guide
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <div class="p-3 border rounded">
            <h6 class="text-primary fw-bold">Step 1 — Create Bot</h6>
            <ol class="small mb-0">
              <li>Open Telegram → Search <strong>@BotFather</strong></li>
              <li>Send <code>/newbot</code></li>
              <li>Follow instructions</li>
              <li>Copy the <strong>Bot Token</strong></li>
              <li>Paste token above → Click Connect</li>
            </ol>
          </div>
        </div>
        <div class="col-md-4">
          <div class="p-3 border rounded">
            <h6 class="text-success fw-bold">Step 2 — Add Bot to Group</h6>
            <ol class="small mb-0">
              <li>Open your Telegram Group</li>
              <li>Go to Group Settings → Members</li>
              <li>Search for your bot username</li>
              <li>Add as <strong>Administrator</strong></li>
              <li>Enable: Send Messages, Send Polls</li>
            </ol>
          </div>
        </div>
        <div class="col-md-4">
          <div class="p-3 border rounded">
            <h6 class="text-warning fw-bold">Step 3 — Get Chat ID</h6>
            <ol class="small mb-0">
              <li>Add <strong>@userinfobot</strong> to group</li>
              <li>Or forward a group message to the bot</li>
              <li>Copy the <strong>Chat ID</strong> (negative number)</li>
              <li>Paste above → Click Verify Chat</li>
              <li>Remove @userinfobot after</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
const CSRF = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
const API_BOT  = '<?= BASE_URL ?>api/tg_quiz/bot.php';
const API_CHAT = '<?= BASE_URL ?>api/tg_quiz/chat.php';
const API_SESSION = '<?= BASE_URL ?>api/tg_quiz/session.php';

async function apiPost(url, data) {
  data.csrf_token = CSRF;
  const fd = new FormData();
  for (const [k,v] of Object.entries(data)) fd.append(k, v);
  const r = await fetch(url, {method:'POST', body: fd});
  return r.json();
}

function showResult(elId, res) {
  const el = document.getElementById(elId);
  el.innerHTML = `<div class="alert alert-${res.success ? 'success' : 'danger'} py-2">${res.message || JSON.stringify(res)}</div>`;
}

async function stopQuiz(sessionId, button) {
  if (!confirm('Stop this quiz now? No more questions will be sent.')) return;

  const originalHtml = button.innerHTML;
  button.disabled = true;
  button.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Stopping...';

  const result = document.getElementById('stopQuizResult');
  const data = new FormData();
  data.append('action', 'stop');
  data.append('session_id', sessionId);
  data.append('csrf_token', CSRF);

  try {
    const response = await fetch(API_SESSION, {method: 'POST', body: data});
    const res = await response.json();
    result.innerHTML = `<div class="alert alert-${res.success ? 'success' : 'danger'} py-2">${res.message || 'Unable to stop quiz.'}</div>`;
    if (res.success) {
      document.getElementById(`active-session-${sessionId}`)?.remove();
      setTimeout(() => location.reload(), 1000);
    } else {
      button.disabled = false;
      button.innerHTML = originalHtml;
    }
  } catch (error) {
    result.innerHTML = '<div class="alert alert-danger py-2">Could not contact the server. Please try again.</div>';
    button.disabled = false;
    button.innerHTML = originalHtml;
  }
}

async function connectBot() {
  const token = document.getElementById('botTokenInput').value.trim();
  if (!token) return alert('Enter bot token');
  document.getElementById('botResult').innerHTML = '<div class="alert alert-secondary py-2"><i class="fa-solid fa-spinner fa-spin me-2"></i>Connecting...</div>';
  const res = await apiPost(API_BOT + '?action=connect', {bot_token: token, action:'connect'});
  showResult('botResult', res);
  if (res.success) setTimeout(() => location.reload(), 1500);
}

async function testBot() {
  const res = await apiPost(API_BOT + '?action=test', {action:'test'});
  showResult('botResult', res);
}

async function disconnectBot() {
  if (!confirm('Disconnect bot?')) return;
  const res = await apiPost(API_BOT + '?action=disconnect', {action:'disconnect'});
  showResult('botResult', res);
  if (res.success) setTimeout(() => location.reload(), 1000);
}

async function verifyChat() {
  const chatId = document.getElementById('chatIdInput').value.trim();
  if (!chatId) return alert('Enter Chat ID');
  document.getElementById('chatResult').innerHTML = '<div class="alert alert-secondary py-2"><i class="fa-solid fa-spinner fa-spin me-2"></i>Verifying...</div>';
  const res = await apiPost(API_CHAT + '?action=verify', {action:'verify', chat_id: chatId});
  const el = document.getElementById('chatResult');
  if (res.success) {
    const checks = res.checks || {};
    el.innerHTML = `
      <div class="alert alert-success py-2">
        <strong>${res.chat_title}</strong> (${res.chat_type})<br>
        ${Object.entries(checks).map(([k,v]) => `<span class="me-3">${v ? '✅' : '❌'} ${k.replace(/_/g,' ')}</span>`).join('')}
      </div>`;
    setTimeout(() => location.reload(), 2000);
  } else {
    el.innerHTML = `<div class="alert alert-danger py-2">${res.message}</div>`;
  }
}

async function sendTestPoll(chatId, btn) {
  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
  try {
    const res = await apiPost(API_CHAT + '?action=test_send', {action:'test_send', chat_id: chatId});
    const el = document.getElementById('chatResult');
    if (res.success) {
      el.innerHTML = `<div class="alert alert-success py-2"><i class="fa-solid fa-circle-check me-2"></i>${res.message}</div>`;
    } else {
      el.innerHTML = `<div class="alert alert-danger py-2"><i class="fa-solid fa-circle-exclamation me-2"></i>${res.message}</div>`;
    }
  } catch(e) {
    alert('Request failed');
  } finally {
    btn.disabled = false;
    btn.innerHTML = origHtml;
  }
}

async function deleteChat(id) {
  if (!confirm('Remove this chat?')) return;
  const res = await apiPost(API_CHAT + '?action=delete', {action:'delete', id});
  if (res.success) location.reload();
}
</script>

<?php require_once '../includes/footer.php'; ?>
