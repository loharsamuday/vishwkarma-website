<?php
// admin/tg_quiz/quiz_sets.php
// All Quiz Sets — List, Create, Edit, Delete, Duplicate, Start

$page_title = "Quiz Sets";
require_once '../../includes/db.php';
require_once '../../includes/session.php';

if (!isset($_SESSION['admin_id'])) { header("Location: ../index.php"); exit; }

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$quiz_sets = [];
try {
    $stmt = $pdo->prepare("SELECT qs.*, (SELECT COUNT(*) FROM tg_quiz_sessions s WHERE s.quiz_set_id=qs.id AND s.status='RUNNING') as is_running FROM tg_quiz_sets qs WHERE qs.admin_id=? ORDER BY qs.created_at DESC");
    $stmt->execute([$_SESSION['admin_id']]);
    $quiz_sets = $stmt->fetchAll();
} catch (PDOException $e) {}

require_once '../includes/header.php';
?>

<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
    <div>
      <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
      <h4 class="mb-0 d-inline"><i class="fa-brands fa-telegram text-primary me-2"></i>Quiz Sets</h4>
    </div>
    <div class="d-flex gap-2">
      <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Dashboard</a>
      <button class="btn btn-success btn-sm fw-bold" onclick="openCreateModal()">
        <i class="fa-solid fa-plus me-1"></i>New Quiz Set
      </button>
    </div>
  </div>

  <?php if (empty($quiz_sets)): ?>
  <div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
      <i class="fa-solid fa-layer-group fa-4x text-muted mb-3"></i>
      <h5 class="text-muted">No Quiz Sets Yet</h5>
      <p class="text-muted">Create your first quiz set to get started.</p>
      <button class="btn btn-warning fw-bold px-4" onclick="openCreateModal()">
        <i class="fa-solid fa-plus me-2"></i>Create Quiz Set
      </button>
    </div>
  </div>
  <?php else: ?>
  <div class="row g-3">
    <?php foreach ($quiz_sets as $set): ?>
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <h6 class="fw-bold mb-1">
                <?= htmlspecialchars($set['title']) ?>
                <?php if ($set['is_running']): ?>
                  <span class="badge bg-success ms-1"><i class="fa-solid fa-circle fa-fade me-1"></i>LIVE</span>
                <?php endif; ?>
              </h6>
              <?php if ($set['description']): ?>
                <small class="text-muted"><?= htmlspecialchars($set['description']) ?></small>
              <?php endif; ?>
            </div>
          </div>
          <div class="d-flex gap-3 mb-3">
            <span class="badge bg-primary fs-6 px-3 py-2">
              <i class="fa-solid fa-clipboard-question me-1"></i><?= $set['total_questions'] ?> Questions
            </span>
            <span class="badge bg-secondary fs-6 px-3 py-2">
              <i class="fa-regular fa-clock me-1"></i><?= $set['default_timer'] ?>s/Question
            </span>
          </div>
          <small class="text-muted">Created: <?= date('d M Y', strtotime($set['created_at'])) ?></small>
        </div>
        <div class="card-footer bg-transparent border-0 pt-0">
          <div class="d-flex flex-wrap gap-1">
            <a href="quiz_create.php?id=<?= $set['id'] ?>" class="btn btn-outline-primary btn-sm">
              <i class="fa-solid fa-pen me-1"></i>Questions
            </a>
            <button class="btn btn-outline-secondary btn-sm" onclick="editSet(<?= $set['id'] ?>, '<?= addslashes(htmlspecialchars($set['title'])) ?>', '<?= addslashes(htmlspecialchars($set['description'] ?? '')) ?>', <?= $set['default_timer'] ?>)">
              <i class="fa-solid fa-pencil me-1"></i>Edit
            </button>
            <button class="btn btn-outline-info btn-sm" onclick="duplicateSet(<?= $set['id'] ?>)">
              <i class="fa-solid fa-copy me-1"></i>Duplicate
            </button>
            <?php if ($set['total_questions'] > 0): ?>
            <a href="quiz_settings.php?id=<?= $set['id'] ?>" class="btn btn-success btn-sm fw-bold">
              <i class="fa-solid fa-play me-1"></i>Start
            </a>
            <?php endif; ?>
            <a href="quiz_history.php?quiz_set_id=<?= $set['id'] ?>" class="btn btn-outline-dark btn-sm">
              <i class="fa-solid fa-clock-rotate-left me-1"></i>Logs
            </a>
            <?php if (!$set['is_running']): ?>
            <button class="btn btn-outline-danger btn-sm" onclick="deleteSet(<?= $set['id'] ?>, '<?= addslashes($set['title']) ?>')">
              <i class="fa-solid fa-trash me-1"></i>Delete
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Create / Edit Modal -->
<div class="modal fade" id="setModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="setModalTitle"><i class="fa-solid fa-plus me-2"></i>Create Quiz Set</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="setId" value="0">
        <div class="mb-3">
          <label class="form-label fw-bold">Quiz Title *</label>
          <input type="text" class="form-control" id="setTitle" placeholder="e.g. Banking Awareness Quiz - Set 1">
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Description</label>
          <textarea class="form-control" id="setDesc" rows="2" placeholder="Optional description"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Default Timer (seconds per question)</label>
          <select class="form-select" id="setTimer">
            <option value="5">5 seconds</option>
            <option value="10">10 seconds</option>
            <option value="15" selected>15 seconds</option>
            <option value="20">20 seconds</option>
            <option value="30">30 seconds</option>
            <option value="60">60 seconds</option>
          </select>
        </div>
        <div id="setModalResult"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary fw-bold" onclick="saveSet()">
          <i class="fa-solid fa-save me-1"></i>Save Quiz Set
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
const API  = '<?= BASE_URL ?>api/tg_quiz/session.php';
let setModal;
document.addEventListener('DOMContentLoaded', () => { setModal = new bootstrap.Modal(document.getElementById('setModal')); });

function openCreateModal() {
  document.getElementById('setModalTitle').innerHTML = '<i class="fa-solid fa-plus me-2"></i>Create Quiz Set';
  document.getElementById('setId').value = '0';
  document.getElementById('setTitle').value = '';
  document.getElementById('setDesc').value = '';
  document.getElementById('setTimer').value = '15';
  document.getElementById('setModalResult').innerHTML = '';
  setModal.show();
}

function editSet(id, title, desc, timer) {
  document.getElementById('setModalTitle').innerHTML = '<i class="fa-solid fa-pencil me-2"></i>Edit Quiz Set';
  document.getElementById('setId').value = id;
  document.getElementById('setTitle').value = title;
  document.getElementById('setDesc').value = desc;
  document.getElementById('setTimer').value = timer;
  document.getElementById('setModalResult').innerHTML = '';
  setModal.show();
}

async function saveSet() {
  const id    = document.getElementById('setId').value;
  const title = document.getElementById('setTitle').value.trim();
  const desc  = document.getElementById('setDesc').value.trim();
  const timer = document.getElementById('setTimer').value;
  if (!title) return alert('Quiz title required');

  const action = id == '0' ? 'create_set' : 'update_set';
  const fd = new FormData();
  fd.append('action', action);
  fd.append('csrf_token', CSRF);
  fd.append('title', title);
  fd.append('description', desc);
  fd.append('default_timer', timer);
  if (id != '0') fd.append('id', id);

  const res = await fetch(API, {method:'POST', body: fd}).then(r=>r.json());
  const el = document.getElementById('setModalResult');
  el.innerHTML = `<div class="alert alert-${res.success?'success':'danger'} py-2">${res.message}</div>`;
  if (res.success) setTimeout(() => location.reload(), 1000);
}

async function duplicateSet(id) {
  if (!confirm('Duplicate this quiz set with all questions?')) return;
  const fd = new FormData();
  fd.append('action', 'duplicate_set');
  fd.append('csrf_token', CSRF);
  fd.append('id', id);
  const res = await fetch(API, {method:'POST', body: fd}).then(r=>r.json());
  alert(res.message);
  if (res.success) location.reload();
}

async function deleteSet(id, title) {
  if (!confirm(`Delete quiz set "${title}" and ALL its questions? This cannot be undone.`)) return;
  const fd = new FormData();
  fd.append('action', 'delete_set');
  fd.append('csrf_token', CSRF);
  fd.append('id', id);
  const res = await fetch(API, {method:'POST', body: fd}).then(r=>r.json());
  alert(res.message);
  if (res.success) location.reload();
}
</script>

<?php require_once '../includes/footer.php'; ?>
