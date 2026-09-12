<?php
// admin/tg_quiz/quiz_history.php
// Quiz Session History

$page_title = "Quiz History";
require_once '../../includes/db.php';
require_once '../../includes/session.php';

if (!isset($_SESSION['admin_id'])) { header("Location: ../index.php"); exit; }

$admin_id = (int)$_SESSION['admin_id'];
$status_filter = $_GET['status'] ?? '';
$quiz_set_filter = (int)($_GET['quiz_set_id'] ?? 0);
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$sessions = [];
$total    = 0;
try {
    $where   = "WHERE s.admin_id=?";
    $params  = [$admin_id];
    if ($status_filter) { $where .= " AND s.status=?"; $params[] = $status_filter; }
    if ($quiz_set_filter) { $where .= " AND s.quiz_set_id=?"; $params[] = $quiz_set_filter; }

    $stmt = $pdo->prepare("SELECT s.id, s.title, s.chat_title, s.status, s.total_questions, s.questions_sent, s.timer_seconds, s.start_time, s.end_time, s.created_at, qs.title as quiz_set_title FROM tg_quiz_sessions s JOIN tg_quiz_sets qs ON qs.id=s.quiz_set_id {$where} ORDER BY s.created_at DESC LIMIT ? OFFSET ?");
    array_push($params, $limit, $offset);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll();

    $cParams = array_slice($params, 0, -2);
    $total = (int)$pdo->prepare("SELECT COUNT(*) FROM tg_quiz_sessions s {$where}")->execute($cParams) ? $pdo->prepare("SELECT COUNT(*) FROM tg_quiz_sessions s {$where}")->execute($cParams) : 0;
    // Recount properly
    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM tg_quiz_sessions s {$where}");
    $cStmt->execute(array_slice($params, 0, -2));
    $total = (int)$cStmt->fetchColumn();
} catch (PDOException $e) {}

$totalPages = $limit > 0 ? ceil($total / $limit) : 1;

$statusBadges = [
    'PENDING'   => 'secondary',
    'RUNNING'   => 'success',
    'PAUSED'    => 'warning',
    'COMPLETED' => 'primary',
    'STOPPED'   => 'danger',
    'FAILED'    => 'dark',
];

require_once '../includes/header.php';
?>

<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
    <div>
      <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
      <h4 class="mb-0 d-inline"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Quiz History</h4>
    </div>
    <div class="d-flex gap-2">
      <a href="quiz_live.php" class="btn btn-outline-success btn-sm"><i class="fa-solid fa-satellite-dish me-1"></i>Live Monitor</a>
      <a href="quiz_sets.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-layer-group me-1"></i>Quiz Sets</a>
    </div>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label small fw-bold">Filter by Status</label>
          <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <?php foreach (array_keys($statusBadges) as $s): ?>
            <option value="<?= $s ?>" <?= $status_filter===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-bold">Total Sessions</label>
          <div class="form-control-sm text-muted pt-1"><strong><?= $total ?></strong> records found</div>
        </div>
        <div class="col-md-4 text-end">
          <a href="quiz_history.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-rotate me-1"></i>Clear Filters
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- Table -->
  <?php if (empty($sessions)): ?>
  <div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
      <i class="fa-solid fa-clock-rotate-left fa-4x text-muted mb-3"></i>
      <h5 class="text-muted">No quiz history yet</h5>
      <p class="text-muted">Start your first quiz from Quiz Settings.</p>
      <a href="quiz_settings.php" class="btn btn-warning fw-bold px-4">
        <i class="fa-solid fa-play me-2"></i>Start a Quiz
      </a>
    </div>
  </div>
  <?php else: ?>
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Quiz Name</th>
              <th>Telegram Group</th>
              <th class="text-center">Questions</th>
              <th class="text-center">Sent</th>
              <th>Start Time</th>
              <th>End Time</th>
              <th>Duration</th>
              <th class="text-center">Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sessions as $s): ?>
            <?php
              $duration = '';
              if ($s['start_time'] && $s['end_time']) {
                  $diff = strtotime($s['end_time']) - strtotime($s['start_time']);
                  $duration = gmdate($diff >= 3600 ? 'H:i:s' : 'i:s', $diff);
              } elseif ($s['start_time'] && in_array($s['status'],['RUNNING','PAUSED'])) {
                  $diff = time() - strtotime($s['start_time']);
                  $duration = gmdate($diff >= 3600 ? 'H:i:s' : 'i:s', $diff) . ' (ongoing)';
              }
            ?>
            <tr>
              <td class="text-muted small"><?= $s['id'] ?></td>
              <td>
                <div class="fw-bold"><?= htmlspecialchars($s['title'] ?? $s['quiz_set_title']) ?></div>
                <small class="text-muted"><?= htmlspecialchars($s['quiz_set_title']) ?></small>
              </td>
              <td><small><?= htmlspecialchars($s['chat_title'] ?? '—') ?></small></td>
              <td class="text-center"><span class="badge bg-primary"><?= $s['total_questions'] ?></span></td>
              <td class="text-center">
                <span class="badge bg-<?= $s['questions_sent']==$s['total_questions']?'success':'secondary' ?>">
                  <?= $s['questions_sent'] ?>
                </span>
              </td>
              <td><small><?= $s['start_time'] ? date('d M y H:i', strtotime($s['start_time'])) : '—' ?></small></td>
              <td><small><?= $s['end_time'] ? date('d M y H:i', strtotime($s['end_time'])) : '—' ?></small></td>
              <td><small class="text-muted"><?= $duration ?: '—' ?></small></td>
              <td class="text-center">
                <span class="badge bg-<?= $statusBadges[$s['status']] ?? 'secondary' ?>">
                  <?= $s['status'] ?>
                </span>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <?php if (in_array($s['status'], ['RUNNING','PAUSED'])): ?>
                  <a href="quiz_live.php?session_id=<?= $s['id'] ?>" class="btn btn-xs btn-outline-success py-0 px-1" title="Monitor">
                    <i class="fa-solid fa-satellite-dish"></i>
                  </a>
                  <?php endif; ?>
                  <button class="btn btn-xs btn-outline-primary py-0 px-1" title="View Logs" onclick="viewLogs(<?= $s['id'] ?>)">
                    <i class="fa-solid fa-list"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-transparent">
      <nav>
        <ul class="pagination pagination-sm justify-content-center mb-0">
          <?php for ($p=1; $p<=$totalPages; $p++): ?>
          <li class="page-item <?= $p==$page?'active':'' ?>">
            <a class="page-link" href="?page=<?= $p ?>&status=<?= urlencode($status_filter) ?>"><?= $p ?></a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Logs Modal -->
<div class="modal fade" id="logsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="fa-solid fa-list me-2"></i>Question Logs</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div id="logsContent" style="max-height: 500px; overflow-y: auto;">
          <div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-muted"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let logsModal;
document.addEventListener('DOMContentLoaded', () => { logsModal = new bootstrap.Modal(document.getElementById('logsModal')); });

async function viewLogs(sessionId) {
  logsModal.show();
  document.getElementById('logsContent').innerHTML = '<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-muted"></i></div>';
  try {
    const res = await fetch(`<?= BASE_URL ?>api/tg_quiz/session.php?action=status&session_id=${sessionId}`).then(r=>r.json());
    // Then load logs from a dedicated endpoint
    const logRes = await fetch(`<?= BASE_URL ?>api/tg_quiz/questions.php?action=logs&session_id=${sessionId}`).catch(()=>null);

    const s = res.session;
    let html = `<table class="table table-sm table-hover mb-0">
      <thead class="table-dark"><tr><th>#</th><th>Status</th><th>Sent At</th><th>Error</th></tr></thead>
      <tbody id="logRows"><tr><td colspan="4" class="text-center text-muted py-3">No detailed logs available</td></tr></tbody>
    </table>`;
    document.getElementById('logsContent').innerHTML = html;

    // Load actual logs
    const dbRes = await fetch(`<?= BASE_URL ?>api/tg_quiz/log.php?session_id=${sessionId}`).then(r=>r.json()).catch(()=>null);
    if (dbRes?.logs?.length) {
      document.getElementById('logRows').innerHTML = dbRes.logs.map(l => `
        <tr>
          <td>${l.question_number}</td>
          <td><span class="badge bg-${l.status==='SENT'?'success':l.status==='FAILED'?'danger':'warning'}">${l.status}</span></td>
          <td><small>${l.sent_at || '—'}</small></td>
          <td><small class="text-danger">${l.error_message || ''}</small></td>
        </tr>`).join('');
    }
  } catch(e) {
    document.getElementById('logsContent').innerHTML = '<div class="alert alert-danger m-3">Error loading logs</div>';
  }
}
</script>

<style>.btn-xs { font-size: 0.7rem; }</style>

<?php require_once '../includes/footer.php'; ?>
