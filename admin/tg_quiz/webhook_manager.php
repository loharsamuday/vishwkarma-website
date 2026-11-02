<?php
// admin/tg_quiz/webhook_manager.php
// Telegram Webhook Management Dashboard
// Explicit registration, status checking, and deletion for Local & Production

$page_title = "Telegram Webhook Manager";
require_once '../../includes/db.php';
require_once '../../includes/session.php';
require_once '../../api/tg_quiz/tg_helper.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$admin_id = (int)$_SESSION['admin_id'];
$token = getActiveBotToken($pdo);

// Fetch bot details
$bot = null;
try {
    $stmt = $pdo->prepare("SELECT id, bot_name, bot_username, bot_id, is_active, last_verified_at FROM tg_bots WHERE admin_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
    $stmt->execute([$admin_id]);
    $bot = $stmt->fetch();
} catch (PDOException $e) {}

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    $reqCsrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($reqCsrf) || !hash_equals($_SESSION['csrf_token'], $reqCsrf)) {
        echo json_encode(['success' => false, 'message' => 'CSRF validation failed']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'No active bot token found. Please connect your bot in Bot & Chat settings first.']);
        exit;
    }

    $secretToken = defined('TG_WEBHOOK_SECRET') ? TG_WEBHOOK_SECRET : '';
    $allowedUpdates = ['message', 'poll_answer', 'my_chat_member'];

    switch ($action) {
        case 'status':
            $res = getTelegramWebhookInfo($token);
            if (!empty($res['ok'])) {
                $info = $res['result'] ?? [];
                if (!empty($info['last_error_date'])) {
                    $info['last_error_date_formatted'] = date('d M Y H:i:s', $info['last_error_date']);
                }
                echo json_encode(['success' => true, 'info' => $info]);
            } else {
                echo json_encode(['success' => false, 'message' => $res['description'] ?? 'Failed to get webhook info']);
            }
            exit;

        case 'register_prod':
            $prodUrl = trim($_POST['prod_url'] ?? 'https://vishwkarma.great-site.net/api/tg_quiz/webhook.php');
            if (stripos($prodUrl, 'https://') !== 0) {
                echo json_encode(['success' => false, 'message' => 'Production webhook must be an HTTPS URL']);
                exit;
            }
            $res = setTelegramWebhook($token, $prodUrl, $secretToken, $allowedUpdates);
            if (!empty($res['ok'])) {
                logActivity("Registered production Telegram webhook: {$prodUrl}", 'admin', null, $admin_id);
                echo json_encode(['success' => true, 'message' => 'Production Webhook successfully registered with Telegram!', 'url' => $prodUrl]);
            } else {
                echo json_encode(['success' => false, 'message' => $res['description'] ?? 'Registration failed']);
            }
            exit;

        case 'register_local':
            $tunnelUrl = trim($_POST['tunnel_url'] ?? '');
            if (empty($tunnelUrl) || stripos($tunnelUrl, 'https://') !== 0) {
                echo json_encode(['success' => false, 'message' => 'Please enter a valid HTTPS tunnel URL (e.g., https://xxxx.ngrok-free.app/vishwkarma/api/tg_quiz/webhook.php)']);
                exit;
            }
            // Ensure full webhook path is present
            $parsed = rtrim($tunnelUrl, '/');
            if (substr($parsed, -11) !== 'webhook.php') {
                if (strpos($parsed, '/api/tg_quiz') !== false) {
                    $parsed .= '/webhook.php';
                } else {
                    $parsed .= '/api/tg_quiz/webhook.php';
                }
            }
            $res = setTelegramWebhook($token, $parsed, $secretToken, $allowedUpdates);
            if (!empty($res['ok'])) {
                logActivity("Registered local tunnel Telegram webhook: {$parsed}", 'admin', null, $admin_id);
                echo json_encode(['success' => true, 'message' => 'Local Tunnel Webhook successfully registered with Telegram!', 'url' => $parsed]);
            } else {
                echo json_encode(['success' => false, 'message' => $res['description'] ?? 'Registration failed']);
            }
            exit;

        case 'delete':
            $dropPending = !empty($_POST['drop_pending']);
            $res = deleteTelegramWebhook($token, $dropPending);
            if (!empty($res['ok'])) {
                logActivity("Deleted Telegram webhook (drop_pending: " . ($dropPending ? '1' : '0') . ")", 'admin', null, $admin_id);
                echo json_encode(['success' => true, 'message' => 'Webhook deleted! Bot is now back in polling/updates mode.']);
            } else {
                echo json_encode(['success' => false, 'message' => $res['description'] ?? 'Failed to delete webhook']);
            }
            exit;

        case 'get_logs':
            $logFile = __DIR__ . '/../../logs/telegram_webhook.log';
            if (file_exists($logFile)) {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $recent = array_slice($lines, -40);
                echo json_encode(['success' => true, 'logs' => implode("\n", $recent)]);
            } else {
                echo json_encode(['success' => true, 'logs' => 'No webhook logs found yet.']);
            }
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
    }
}

require_once '../includes/header.php';
?>

<div class="main-content">
  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
    <div>
      <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
      <h4 class="mb-0 d-inline"><i class="fa-solid fa-link text-primary me-2"></i>Telegram Webhook Manager</h4>
    </div>
    <div>
      <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-robot me-1"></i>Bot & Chat</a>
      <a href="quiz_sets.php" class="btn btn-warning btn-sm ms-2"><i class="fa-solid fa-layer-group me-1"></i>Quiz Sets</a>
    </div>
  </div>

  <!-- Status Alert Banner -->
  <div class="row g-3 mb-4">
    <!-- Environment Info -->
    <div class="col-md-4">
      <div class="card border-0 shadow-sm p-3 h-100">
        <div class="d-flex align-items-center mb-2">
          <i class="fa-solid fa-server fa-2x text-info me-3"></i>
          <div>
            <small class="text-muted text-uppercase fw-bold">Active Environment</small>
            <h5 class="mb-0">
              <span class="badge bg-<?= (defined('ENVIRONMENT') && ENVIRONMENT === 'production') ? 'danger' : 'primary' ?>">
                <?= strtoupper(defined('ENVIRONMENT') ? ENVIRONMENT : 'UNKNOWN') ?>
              </span>
            </h5>
          </div>
        </div>
        <div class="small text-muted mt-2">
          <strong>Base URL:</strong> <code><?= defined('BASE_URL') ? htmlspecialchars(BASE_URL) : '' ?></code>
        </div>
      </div>
    </div>

    <!-- Active Bot Info -->
    <div class="col-md-4">
      <div class="card border-0 shadow-sm p-3 h-100">
        <div class="d-flex align-items-center mb-2">
          <i class="fa-solid fa-robot fa-2x text-primary me-3"></i>
          <div>
            <small class="text-muted text-uppercase fw-bold">Connected Bot</small>
            <h5 class="mb-0">
              <?= $bot ? '@' . htmlspecialchars($bot['bot_username']) : '<span class="text-warning">No Bot Connected</span>' ?>
            </h5>
          </div>
        </div>
        <div class="small text-muted mt-2">
          <strong>Token Status:</strong> <?= $token ? '<span class="text-success fw-bold">Loaded & Decrypted ✓</span>' : '<span class="text-danger fw-bold">Missing</span>' ?>
        </div>
      </div>
    </div>

    <!-- Webhook Secret Info -->
    <div class="col-md-4">
      <div class="card border-0 shadow-sm p-3 h-100">
        <div class="d-flex align-items-center mb-2">
          <i class="fa-solid fa-shield-halved fa-2x text-success me-3"></i>
          <div>
            <small class="text-muted text-uppercase fw-bold">Secret Token Validation</small>
            <h5 class="mb-0">
              <span class="badge bg-success">Configured & Enforced ✓</span>
            </h5>
          </div>
        </div>
        <div class="small text-muted mt-2">
          Header: <code>X-Telegram-Bot-Api-Secret-Token</code>
        </div>
      </div>
    </div>
  </div>

  <!-- Live Webhook Status Card -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
      <span class="fw-bold"><i class="fa-solid fa-satellite-dish me-2 text-warning"></i>Live Telegram Webhook Status (from getWebhookInfo)</span>
      <button class="btn btn-outline-light btn-sm" onclick="checkStatus()" id="btnRefresh">
        <i class="fa-solid fa-rotate me-1"></i>Refresh Status
      </button>
    </div>
    <div class="card-body p-4">
      <div id="statusLoading" class="text-center py-3 text-muted">
        <i class="fa-solid fa-spinner fa-spin fa-2x mb-2 text-primary"></i>
        <div>Fetching status from Telegram servers...</div>
      </div>
      <div id="statusContent" class="d-none">
        <div class="table-responsive">
          <table class="table table-bordered align-middle mb-0">
            <tbody>
              <tr>
                <th class="bg-light" style="width: 250px;">Webhook State</th>
                <td id="infoState">—</td>
              </tr>
              <tr>
                <th class="bg-light">Registered Webhook URL</th>
                <td id="infoUrl" class="font-monospace fw-bold text-primary">—</td>
              </tr>
              <tr>
                <th class="bg-light">Custom Certificate</th>
                <td id="infoCert">—</td>
              </tr>
              <tr>
                <th class="bg-light">Pending Update Count</th>
                <td id="infoPending">—</td>
              </tr>
              <tr>
                <th class="bg-light">Last Error Date</th>
                <td id="infoLastErrorDate">—</td>
              </tr>
              <tr>
                <th class="bg-light">Last Error Message</th>
                <td id="infoLastErrorMessage" class="text-danger fw-semibold">—</td>
              </tr>
              <tr>
                <th class="bg-light">Allowed Updates</th>
                <td id="infoAllowedUpdates">—</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div id="statusError" class="alert alert-danger d-none mt-3 mb-0"></div>
    </div>
  </div>

  <!-- Action Controls -->
  <div class="row g-4 mb-4">
    <!-- Register Production Webhook -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-danger text-white fw-bold py-3">
          <i class="fa-solid fa-cloud-arrow-up me-2"></i>Production Webhook (InfinityFree)
        </div>
        <div class="card-body p-4 d-flex flex-column justify-content-between">
          <div>
            <p class="text-muted small">
              Explicitly registers your live InfinityFree production domain with Telegram’s <code>setWebhook</code> API using your secret token.
            </p>
            <div class="mb-3">
              <label class="form-label fw-bold small">Production Webhook URL</label>
              <input type="url" class="form-control font-monospace" id="prodWebhookUrl"
                     value="https://vishwkarma.great-site.net/api/tg_quiz/webhook.php">
              <small class="text-muted">Must begin with <code>https://</code> and point to <code>webhook.php</code></small>
            </div>
          </div>
          <button class="btn btn-danger w-100 fw-bold py-2" onclick="registerProduction()" id="btnRegisterProd">
            <i class="fa-solid fa-cloud-bolt me-1"></i>Register Production Webhook
          </button>
        </div>
      </div>
    </div>

    <!-- Register Localhost Webhook -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-primary text-white fw-bold py-3">
          <i class="fa-solid fa-laptop-code me-2"></i>Localhost Webhook (XAMPP Tunnel)
        </div>
        <div class="card-body p-4 d-flex flex-column justify-content-between">
          <div>
            <p class="text-muted small">
              Telegram requires a public HTTPS URL. Start your tunnel (e.g., <code>ngrok http 80</code>) and paste the HTTPS URL below.
            </p>
            <div class="mb-3">
              <label class="form-label fw-bold small">HTTPS Tunnel URL</label>
              <input type="url" class="form-control font-monospace" id="localTunnelUrl"
                     placeholder="https://your-subdomain.ngrok-free.app/vishwkarma/api/tg_quiz/webhook.php"
                     value="<?= defined('LOCAL_TUNNEL_URL') && !empty(LOCAL_TUNNEL_URL) ? htmlspecialchars(LOCAL_TUNNEL_URL) : '' ?>">
              <small class="text-muted">Example: <code>https://xxxx.ngrok-free.app/vishwkarma/api/tg_quiz/webhook.php</code></small>
            </div>
          </div>
          <button class="btn btn-primary w-100 fw-bold py-2" onclick="registerLocal()" id="btnRegisterLocal">
            <i class="fa-solid fa-paper-plane me-1"></i>Register Localhost Tunnel Webhook
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Webhook Card -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-warning text-dark fw-bold py-3 d-flex justify-content-between align-items-center">
      <span><i class="fa-solid fa-trash-can me-2"></i>Delete Webhook / Revert to Polling</span>
      <span class="badge bg-dark text-warning">Explicit Action Only</span>
    </div>
    <div class="card-body p-4">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
          <p class="mb-1 text-muted">
            Deletes any active webhook on Telegram servers. Once deleted, the bot stops sending webhook callbacks and reverts to manual/long-polling mode (<code>getUpdates</code>).
          </p>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="dropPendingUpdates">
            <label class="form-check-label text-danger small fw-bold" for="dropPendingUpdates">
              Drop all pending updates queued on Telegram servers
            </label>
          </div>
        </div>
        <button class="btn btn-outline-danger fw-bold" onclick="deleteWebhookAction()" id="btnDeleteWebhook">
          <i class="fa-solid fa-ban me-1"></i>Delete Webhook
        </button>
      </div>
    </div>
  </div>

  <!-- Webhook Event Logs -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-light fw-bold py-3 d-flex justify-content-between align-items-center">
      <span><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Recent Webhook Logs (logs/telegram_webhook.log)</span>
      <button class="btn btn-sm btn-outline-secondary" onclick="fetchLogs()"><i class="fa-solid fa-rotate me-1"></i>Refresh Logs</button>
    </div>
    <div class="card-body p-3">
      <pre class="bg-dark text-light p-3 rounded mb-0" id="logBox" style="max-height: 250px; overflow-y: auto; font-size: 0.82rem; font-family: Consolas, monospace;">Loading logs...</pre>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';

async function postAction(action, data = {}) {
  const fd = new FormData();
  fd.append('action', action);
  fd.append('csrf_token', CSRF);
  for (const [k, v] of Object.entries(data)) {
    fd.append(k, v);
  }
  const r = await fetch('webhook_manager.php', { method: 'POST', body: fd });
  return r.json();
}

async function checkStatus() {
  const loading = document.getElementById('statusLoading');
  const content = document.getElementById('statusContent');
  const errorBox = document.getElementById('statusError');
  const btn = document.getElementById('btnRefresh');

  loading.classList.remove('d-none');
  content.classList.add('d-none');
  errorBox.classList.add('d-none');
  btn.disabled = true;

  try {
    const res = await postAction('status');
    if (res.success && res.info) {
      const info = res.info;
      const hasUrl = info.url && info.url.length > 0;

      document.getElementById('infoState').innerHTML = hasUrl 
        ? '<span class="badge bg-success fs-6"><i class="fa-solid fa-circle-check me-1"></i>ACTIVE</span>'
        : '<span class="badge bg-secondary fs-6"><i class="fa-solid fa-circle-pause me-1"></i>NOT SET (Polling Mode)</span>';

      document.getElementById('infoUrl').textContent = hasUrl ? info.url : '(None — Bot is waiting for webhook registration)';
      document.getElementById('infoCert').textContent = info.has_custom_certificate ? 'Yes (Custom SSL)' : 'No (Standard CA / Cloud SSL)';
      document.getElementById('infoPending').innerHTML = (info.pending_update_count > 0)
        ? `<span class="badge bg-warning text-dark">${info.pending_update_count} queued updates</span>`
        : '<span class="badge bg-light text-dark">0 (No queue backlog)</span>';

      document.getElementById('infoLastErrorDate').textContent = info.last_error_date_formatted || 'None';
      document.getElementById('infoLastErrorMessage').textContent = info.last_error_message || 'None (Healthy)';
      document.getElementById('infoAllowedUpdates').textContent = (info.allowed_updates && info.allowed_updates.length > 0)
        ? info.allowed_updates.join(', ') : 'All updates';

      content.classList.remove('d-none');
    } else {
      errorBox.textContent = res.message || 'Could not retrieve webhook info.';
      errorBox.classList.remove('d-none');
    }
  } catch (err) {
    errorBox.textContent = 'Network error while contacting server: ' + err.message;
    errorBox.classList.remove('d-none');
  } finally {
    loading.classList.add('d-none');
    btn.disabled = false;
  }
}

async function registerProduction() {
  const url = document.getElementById('prodWebhookUrl').value.trim();
  if (!url) return alert('Enter production webhook URL');
  if (!confirm(`Register Production Webhook with Telegram?\n\nTarget URL: ${url}\n\nNote: If a local webhook was active, it will be replaced by this production webhook.`)) return;

  const btn = document.getElementById('btnRegisterProd');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Registering with Telegram...';

  try {
    const res = await postAction('register_prod', { prod_url: url });
    alert(res.message);
    checkStatus();
    fetchLogs();
  } catch (e) {
    alert('Request failed: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-cloud-bolt me-1"></i>Register Production Webhook';
  }
}

async function registerLocal() {
  const url = document.getElementById('localTunnelUrl').value.trim();
  if (!url) {
    return alert('Please enter your HTTPS tunnel URL (e.g. from Ngrok, Cloudflare Tunnel, or Localtunnel). Telegram strictly requires HTTPS.');
  }
  if (!confirm(`Register Localhost Tunnel Webhook with Telegram?\n\nTarget URL: ${url}\n\nNote: This will point your Telegram bot to your local machine.`)) return;

  const btn = document.getElementById('btnRegisterLocal');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Registering with Telegram...';

  try {
    const res = await postAction('register_local', { tunnel_url: url });
    alert(res.message);
    checkStatus();
    fetchLogs();
  } catch (e) {
    alert('Request failed: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i>Register Localhost Tunnel Webhook';
  }
}

async function deleteWebhookAction() {
  const dropPending = document.getElementById('dropPendingUpdates').checked;
  if (!confirm(`Are you sure you want to delete the active webhook?\n\nDrop pending updates: ${dropPending ? 'YES' : 'NO'}\n\nThe bot will revert to getUpdates / manual polling mode.`)) return;

  const btn = document.getElementById('btnDeleteWebhook');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Deleting...';

  try {
    const res = await postAction('delete', { drop_pending: dropPending ? 1 : 0 });
    alert(res.message);
    checkStatus();
    fetchLogs();
  } catch (e) {
    alert('Request failed: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-ban me-1"></i>Delete Webhook';
  }
}

async function fetchLogs() {
  const box = document.getElementById('logBox');
  try {
    const res = await postAction('get_logs');
    box.textContent = res.logs || 'No logs recorded.';
    box.scrollTop = box.scrollHeight;
  } catch (e) {
    box.textContent = 'Could not load logs.';
  }
}

// Auto-load on page load
document.addEventListener('DOMContentLoaded', () => {
  checkStatus();
  fetchLogs();
});
</script>

<?php require_once '../includes/footer.php'; ?>
