<?php
// admin/tg_quiz/quiz_create.php
// Create/Edit Quiz Set — Question Manager (Add/Upload/Edit/Delete)

$page_title = "Manage Questions";
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

require_once '../includes/header.php';
?>

<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
    <div>
      <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
      <h4 class="mb-0 d-inline">
        <i class="fa-solid fa-clipboard-question text-primary me-2"></i>
        <?= $quiz_set ? htmlspecialchars($quiz_set['title']) : 'Question Manager' ?>
      </h4>
    </div>
    <div class="d-flex gap-2">
      <a href="quiz_sets.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
      <?php if ($quiz_set): ?>
      <a href="quiz_settings.php?id=<?= $quiz_set_id ?>" class="btn btn-success btn-sm fw-bold">
        <i class="fa-solid fa-play me-1"></i>Start Quiz
      </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$quiz_set): ?>
  <div class="alert alert-danger">Quiz set not found. <a href="quiz_sets.php">Back to Quiz Sets</a></div>
  <?php require_once '../includes/footer.php'; exit; ?>
  <?php endif; ?>

  <!-- Question count badge -->
  <div class="mb-3">
    <span class="badge bg-primary fs-6 px-3 py-2">
      <i class="fa-solid fa-clipboard-question me-2"></i>
      <span id="qCount"><?= $quiz_set['total_questions'] ?></span> Questions
    </span>
    <span class="badge bg-secondary fs-6 px-3 py-2 ms-2">
      <i class="fa-regular fa-clock me-2"></i><?= $quiz_set['default_timer'] ?>s / Question
    </span>
  </div>

  <!-- Tab Navigation -->
  <ul class="nav nav-tabs mb-0" id="questionTabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabList">
      <i class="fa-solid fa-table me-1"></i>Question List
    </a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabAdd">
      <i class="fa-solid fa-plus me-1"></i>Add Question
    </a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabUpload">
      <i class="fa-solid fa-file-arrow-up me-1"></i>CSV / XLSX Upload
    </a></li>
  </ul>

  <div class="tab-content card border-0 shadow-sm border-top-0 rounded-0 rounded-bottom p-4">

    <!-- TAB 1: Question List -->
    <div class="tab-pane fade show active" id="tabList">
      <!-- Search -->
      <div class="row mb-3">
        <div class="col-md-6">
          <div class="input-group">
            <input type="text" class="form-control" id="searchInput" placeholder="Search questions...">
            <button class="btn btn-outline-primary" onclick="loadQuestions()"><i class="fa-solid fa-search"></i></button>
          </div>
        </div>
        <div class="col-md-6 d-flex align-items-center justify-content-end gap-2">
          <select class="form-select form-select-sm w-auto" id="limitSelect" onchange="loadQuestions()">
            <option value="20">20 per page</option>
            <option value="50" selected>50 per page</option>
            <option value="100">100 per page</option>
          </select>
        </div>
      </div>

      <div id="questionsTableWrapper">
        <div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-muted"></i></div>
      </div>
      <div id="paginationWrapper" class="mt-3 d-flex justify-content-center"></div>
    </div>

    <!-- TAB 2: Add Single Question -->
    <div class="tab-pane fade" id="tabAdd">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <h5 class="fw-bold mb-3">Add New Question</h5>
          <div class="mb-3">
            <label class="form-label fw-bold">Question *</label>
            <textarea class="form-control" id="addQ" rows="3" placeholder="Enter question text..."></textarea>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Option A *</label>
              <input type="text" class="form-control" id="addA" placeholder="Option A">
            </div>
            <div class="col-md-6">
              <label class="form-label">Option B *</label>
              <input type="text" class="form-control" id="addB" placeholder="Option B">
            </div>
            <div class="col-md-6">
              <label class="form-label">Option C</label>
              <input type="text" class="form-control" id="addC" placeholder="Option C">
            </div>
            <div class="col-md-6">
              <label class="form-label">Option D</label>
              <input type="text" class="form-control" id="addD" placeholder="Option D">
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label fw-bold">Correct Answer *</label>
              <select class="form-select" id="addCorrect">
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Explanation</label>
            <textarea class="form-control" id="addExpl" rows="2" placeholder="Optional explanation..."></textarea>
          </div>
          <div id="addResult" class="mb-3"></div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary fw-bold px-4" onclick="addQuestion(false)">
              <i class="fa-solid fa-plus me-2"></i>Add Question
            </button>
            <button class="btn btn-outline-primary" onclick="addQuestion(true)">
              <i class="fa-solid fa-plus me-1"></i>Add & Continue
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 3: CSV/XLSX Upload -->
    <div class="tab-pane fade" id="tabUpload">
      <div class="row justify-content-center">
        <div class="col-lg-9">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Bulk Upload Questions</h5>
            <!-- Download Sample Format Buttons -->
            <div class="d-flex gap-2">
              <a href="<?= BASE_URL ?>api/tg_quiz/download_sample.php?format=csv"
                 class="btn btn-outline-success btn-sm fw-bold" download>
                <i class="fa-solid fa-file-csv me-1"></i>Download Sample CSV
              </a>
              <a href="<?= BASE_URL ?>api/tg_quiz/download_sample.php?format=xlsx"
                 class="btn btn-outline-primary btn-sm fw-bold" download>
                <i class="fa-solid fa-file-excel me-1"></i>Download Sample XLSX
              </a>
            </div>
          </div>

          <!-- Format guide -->
          <div class="alert alert-info mb-3 py-2">
            <div class="d-flex align-items-start gap-2">
              <i class="fa-solid fa-circle-info mt-1 flex-shrink-0"></i>
              <div>
                <strong>Required Columns (with or without header row):</strong><br>
                <code class="small">question_number | question | option_a | option_b | option_c | option_d | correct_answer | explanation</code><br>
                <small class="text-muted">
                  • <strong>correct_answer</strong> must be A, B, C, or D &nbsp;•&nbsp;
                  option_c, option_d और explanation optional हैं &nbsp;•&nbsp;
                  Supports <strong>UTF-8 Hindi</strong> text
                </small>
              </div>
            </div>
          </div>

          <!-- Sample preview -->
          <div class="mb-3">
            <small class="text-muted fw-bold d-block mb-1">Example rows:</small>
            <div class="bg-dark text-success rounded p-2 small font-monospace" style="overflow-x:auto;white-space:nowrap">
              <div>question_number,question,option_a,option_b,option_c,option_d,correct_answer,explanation</div>
              <div>1,भारत की राजधानी क्या है?,मुंबई,नई दिल्ली,पटना,जयपुर,B,भारत की राजधानी नई दिल्ली है।</div>
              <div>2,RBI की स्थापना कब हुई?,1930,1935,1940,1947,B,RBI की स्थापना 1 अप्रैल 1935 को हुई।</div>
            </div>
          </div>

          <form id="uploadForm" enctype="multipart/form-data">
            <!-- Drag & Drop Upload Zone -->
            <div id="dropZone" class="border border-2 border-dashed rounded text-center p-4 mb-3"
                 style="border-color:#dee2e6; cursor:pointer; transition:all 0.2s"
                 ondragover="handleDragOver(event)"
                 ondragleave="handleDragLeave(event)"
                 ondrop="handleDrop(event)"
                 onclick="document.getElementById('uploadFile').click()">
              <i class="fa-solid fa-cloud-arrow-up fa-3x text-muted mb-2"></i>
              <p class="mb-1 fw-bold">Click to select or drag & drop file here</p>
              <p class="text-muted small mb-0">Supports CSV and Excel XLSX • Max 10MB • Hindi text supported</p>
              <div id="selectedFileName" class="mt-2 text-success fw-bold d-none"></div>
            </div>
            <input type="file" class="d-none" id="uploadFile" name="file" accept=".csv,.xlsx"
                   onchange="showFileName(this)">

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label fw-bold small">Import Mode</label>
                <div class="d-flex gap-3">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="uploadMode" id="modeAppend" value="append" checked>
                    <label class="form-check-label" for="modeAppend">
                      <i class="fa-solid fa-plus text-success me-1"></i>Append (existing questions रहेंगे)
                    </label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="uploadMode" id="modeReplace" value="replace">
                    <label class="form-check-label" for="modeReplace">
                      <i class="fa-solid fa-rotate text-warning me-1"></i>Replace (सब delete होंगे)
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Upload Progress -->
            <div id="uploadProgress" class="d-none mb-3">
              <div class="d-flex justify-content-between mb-1">
                <small class="fw-bold">Uploading & Parsing...</small>
                <small id="uploadPct">0%</small>
              </div>
              <div class="progress" style="height:10px">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning"
                     id="uploadProgressBar" style="width:0%"></div>
              </div>
            </div>

            <div id="uploadResult" class="mb-3"></div>

            <div class="d-flex gap-2">
              <button type="button" class="btn btn-warning fw-bold px-4" onclick="uploadFile()">
                <i class="fa-solid fa-file-arrow-up me-2"></i>Upload & Import
              </button>
              <button type="button" class="btn btn-outline-secondary" onclick="clearUpload()">
                <i class="fa-solid fa-rotate-left me-1"></i>Clear
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>


  </div>
</div>

<!-- Edit Question Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fa-solid fa-pencil me-2"></i>Edit Question</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editId">
        <div class="mb-3">
          <label class="form-label fw-bold">Question *</label>
          <textarea class="form-control" id="editQ" rows="3"></textarea>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6"><label class="form-label">Option A *</label><input type="text" class="form-control" id="editA"></div>
          <div class="col-md-6"><label class="form-label">Option B *</label><input type="text" class="form-control" id="editB"></div>
          <div class="col-md-6"><label class="form-label">Option C</label><input type="text" class="form-control" id="editC"></div>
          <div class="col-md-6"><label class="form-label">Option D</label><input type="text" class="form-control" id="editD"></div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label fw-bold">Correct Answer *</label>
            <select class="form-select" id="editCorrect">
              <option>A</option><option>B</option><option>C</option><option>D</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Explanation</label>
          <textarea class="form-control" id="editExpl" rows="2"></textarea>
        </div>
        <div id="editResult"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary fw-bold" onclick="saveEdit()">
          <i class="fa-solid fa-save me-1"></i>Save Changes
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const CSRF   = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';
const QS_ID  = <?= $quiz_set_id ?>;
const API_Q  = '<?= BASE_URL ?>api/tg_quiz/questions.php';
let currentPage = 1;
let editModal;

document.addEventListener('DOMContentLoaded', () => {
  editModal = new bootstrap.Modal(document.getElementById('editModal'));
  loadQuestions();
  document.getElementById('searchInput').addEventListener('keyup', e => { if(e.key==='Enter') loadQuestions(); });
});

async function loadQuestions(page=1) {
  currentPage = page;
  const search = document.getElementById('searchInput').value;
  const limit  = document.getElementById('limitSelect').value;
  const wrapper = document.getElementById('questionsTableWrapper');
  wrapper.innerHTML = '<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-muted"></i></div>';
  const url = `${API_Q}?action=list&quiz_set_id=${QS_ID}&page=${page}&limit=${limit}&search=${encodeURIComponent(search)}`;
  const res = await fetch(url).then(r=>r.json());
  if (!res.success) { wrapper.innerHTML = `<div class="alert alert-danger">${res.message}</div>`; return; }

  document.getElementById('qCount').textContent = res.total;
  if (!res.questions.length) {
    wrapper.innerHTML = '<div class="text-center py-5 text-muted"><i class="fa-solid fa-inbox fa-3x mb-3"></i><p>No questions found.</p></div>';
    return;
  }

  wrapper.innerHTML = `
    <div class="table-responsive">
    <table class="table table-hover align-middle small">
      <thead class="table-dark">
        <tr>
          <th style="width:50px">#</th>
          <th>Question</th>
          <th>A</th><th>B</th><th>C</th><th>D</th>
          <th class="text-center">Correct</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        ${res.questions.map(q => `
        <tr id="qrow-${q.id}">
          <td class="fw-bold text-muted">${q.question_number}</td>
          <td style="max-width:200px"><div class="text-truncate" title="${escHtml(q.question_text)}">${escHtml(q.question_text)}</div></td>
          <td class="text-truncate" style="max-width:100px">${escHtml(q.option_a)}</td>
          <td class="text-truncate" style="max-width:100px">${escHtml(q.option_b)}</td>
          <td class="text-truncate" style="max-width:100px">${escHtml(q.option_c||'-')}</td>
          <td class="text-truncate" style="max-width:100px">${escHtml(q.option_d||'-')}</td>
          <td class="text-center"><span class="badge bg-success fs-6">${q.correct_answer}</span></td>
          <td>
            <div class="d-flex gap-1">
              <button class="btn btn-xs btn-outline-primary py-0 px-1" onclick="openEdit(${JSON.stringify(q).replace(/"/g,'&quot;')})" title="Edit"><i class="fa-solid fa-pencil"></i></button>
              <button class="btn btn-xs btn-outline-secondary py-0 px-1" onclick="dupQ(${q.id})" title="Duplicate"><i class="fa-solid fa-copy"></i></button>
              <button class="btn btn-xs btn-outline-dark py-0 px-1" onclick="reorderQ(${q.id},'up')" title="Move Up"><i class="fa-solid fa-arrow-up"></i></button>
              <button class="btn btn-xs btn-outline-dark py-0 px-1" onclick="reorderQ(${q.id},'down')" title="Move Down"><i class="fa-solid fa-arrow-down"></i></button>
              <button class="btn btn-xs btn-outline-danger py-0 px-1" onclick="delQ(${q.id})" title="Delete"><i class="fa-solid fa-trash"></i></button>
            </div>
          </td>
        </tr>`).join('')}
      </tbody>
    </table>
    </div>`;

  // Pagination
  const totalPages = Math.ceil(res.total / limit);
  let pgHtml = '';
  if (totalPages > 1) {
    pgHtml = '<nav><ul class="pagination pagination-sm justify-content-center">';
    for (let p=1; p<=totalPages; p++) {
      pgHtml += `<li class="page-item ${p==page?'active':''}"><a class="page-link" href="#" onclick="loadQuestions(${p});return false">${p}</a></li>`;
    }
    pgHtml += '</ul></nav>';
  }
  document.getElementById('paginationWrapper').innerHTML = pgHtml;
}

function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function openEdit(q) {
  document.getElementById('editId').value      = q.id;
  document.getElementById('editQ').value       = q.question_text;
  document.getElementById('editA').value       = q.option_a;
  document.getElementById('editB').value       = q.option_b;
  document.getElementById('editC').value       = q.option_c || '';
  document.getElementById('editD').value       = q.option_d || '';
  document.getElementById('editCorrect').value = q.correct_answer;
  document.getElementById('editExpl').value    = q.explanation || '';
  document.getElementById('editResult').innerHTML = '';
  editModal.show();
}

async function saveEdit() {
  const fd = new FormData();
  fd.append('action','update'); fd.append('csrf_token',CSRF);
  fd.append('id', document.getElementById('editId').value);
  fd.append('quiz_set_id', QS_ID);
  fd.append('question_text', document.getElementById('editQ').value);
  fd.append('option_a', document.getElementById('editA').value);
  fd.append('option_b', document.getElementById('editB').value);
  fd.append('option_c', document.getElementById('editC').value);
  fd.append('option_d', document.getElementById('editD').value);
  fd.append('correct_answer', document.getElementById('editCorrect').value);
  fd.append('explanation', document.getElementById('editExpl').value);
  const res = await fetch(API_Q+'?action=update', {method:'POST',body:fd}).then(r=>r.json());
  document.getElementById('editResult').innerHTML = `<div class="alert alert-${res.success?'success':'danger'} py-2">${res.message}</div>`;
  if (res.success) { setTimeout(() => { editModal.hide(); loadQuestions(currentPage); }, 800); }
}

async function addQuestion(continueAdding) {
  const fd = new FormData();
  fd.append('action','add'); fd.append('csrf_token',CSRF);
  fd.append('quiz_set_id', QS_ID);
  fd.append('question_text', document.getElementById('addQ').value);
  fd.append('option_a', document.getElementById('addA').value);
  fd.append('option_b', document.getElementById('addB').value);
  fd.append('option_c', document.getElementById('addC').value);
  fd.append('option_d', document.getElementById('addD').value);
  fd.append('correct_answer', document.getElementById('addCorrect').value);
  fd.append('explanation', document.getElementById('addExpl').value);
  const res = await fetch(API_Q+'?action=add', {method:'POST',body:fd}).then(r=>r.json());
  document.getElementById('addResult').innerHTML = `<div class="alert alert-${res.success?'success':'danger'} py-2">${res.message}</div>`;
  if (res.success) {
    document.getElementById('qCount').textContent = parseInt(document.getElementById('qCount').textContent)+1;
    if (continueAdding) {
      ['addQ','addA','addB','addC','addD','addExpl'].forEach(id => document.getElementById(id).value='');
      document.getElementById('addCorrect').value='A';
    } else {
      loadQuestions(currentPage);
    }
  }
}

async function delQ(id) {
  if (!confirm('Delete this question?')) return;
  const fd = new FormData();
  fd.append('action','delete'); fd.append('csrf_token',CSRF); fd.append('id',id);
  const res = await fetch(API_Q+'?action=delete', {method:'POST',body:fd}).then(r=>r.json());
  if (res.success) { document.getElementById('qCount').textContent = Math.max(0, parseInt(document.getElementById('qCount').textContent)-1); loadQuestions(1); }
  else alert(res.message);
}

async function dupQ(id) {
  const fd = new FormData();
  fd.append('action','duplicate'); fd.append('csrf_token',CSRF); fd.append('id',id);
  const res = await fetch(API_Q+'?action=duplicate', {method:'POST',body:fd}).then(r=>r.json());
  if (res.success) { document.getElementById('qCount').textContent = parseInt(document.getElementById('qCount').textContent)+1; loadQuestions(currentPage); }
  else alert(res.message);
}

async function reorderQ(id, direction) {
  const fd = new FormData();
  fd.append('action','reorder'); fd.append('csrf_token',CSRF); fd.append('id',id); fd.append('direction',direction);
  await fetch(API_Q+'?action=reorder', {method:'POST',body:fd}).then(r=>r.json());
  loadQuestions(currentPage);
}

function showFileName(input) {
  const el = document.getElementById('selectedFileName');
  if (input.files[0]) {
    el.textContent = '📎 ' + input.files[0].name + ' (' + (input.files[0].size/1024).toFixed(1) + ' KB)';
    el.classList.remove('d-none');
    document.getElementById('dropZone').style.borderColor = '#28a745';
  }
}

function handleDragOver(e) {
  e.preventDefault();
  document.getElementById('dropZone').style.borderColor = '#0d6efd';
  document.getElementById('dropZone').style.background  = '#f0f7ff';
}

function handleDragLeave(e) {
  document.getElementById('dropZone').style.borderColor = '#dee2e6';
  document.getElementById('dropZone').style.background  = '';
}

function handleDrop(e) {
  e.preventDefault();
  handleDragLeave(e);
  const files = e.dataTransfer.files;
  if (files.length) {
    const fileInput = document.getElementById('uploadFile');
    const dt = new DataTransfer();
    dt.items.add(files[0]);
    fileInput.files = dt.files;
    showFileName(fileInput);
  }
}

function clearUpload() {
  document.getElementById('uploadFile').value = '';
  document.getElementById('selectedFileName').classList.add('d-none');
  document.getElementById('dropZone').style.borderColor = '#dee2e6';
  document.getElementById('dropZone').style.background  = '';
  document.getElementById('uploadResult').innerHTML = '';
  document.getElementById('uploadProgress').classList.add('d-none');
}

async function uploadFile() {
  const file = document.getElementById('uploadFile').files[0];
  if (!file) { alert('Please select a CSV or XLSX file first'); return; }

  // Validate extension client-side
  const ext = file.name.split('.').pop().toLowerCase();
  if (!['csv','xlsx'].includes(ext)) {
    document.getElementById('uploadResult').innerHTML = '<div class="alert alert-danger py-2">❌ Only CSV and XLSX files allowed</div>';
    return;
  }

  // File size check (10MB)
  if (file.size > 10 * 1024 * 1024) {
    document.getElementById('uploadResult').innerHTML = '<div class="alert alert-danger py-2">❌ File too large. Max 10MB allowed.</div>';
    return;
  }

  const resultEl   = document.getElementById('uploadResult');
  const progressEl = document.getElementById('uploadProgress');
  const barEl      = document.getElementById('uploadProgressBar');
  const pctEl      = document.getElementById('uploadPct');

  resultEl.innerHTML = '';
  progressEl.classList.remove('d-none');
  barEl.style.width  = '10%';
  pctEl.textContent  = 'Uploading...';

  const fd = new FormData();
  fd.append('action','upload'); fd.append('csrf_token',CSRF);
  fd.append('quiz_set_id', QS_ID); fd.append('file', file);

  // Use XMLHttpRequest for progress tracking
  const xhr = new XMLHttpRequest();
  xhr.upload.addEventListener('progress', (e) => {
    if (e.lengthComputable) {
      const pct = Math.round((e.loaded / e.total) * 70); // 0-70% for upload
      barEl.style.width = pct + '%';
      pctEl.textContent = pct + '%';
    }
  });

  xhr.onload = function() {
    barEl.style.width = '100%';
    pctEl.textContent = '100%';
    setTimeout(() => progressEl.classList.add('d-none'), 1000);

    let res;
    try { res = JSON.parse(xhr.responseText); }
    catch(e) { resultEl.innerHTML = '<div class="alert alert-danger py-2">❌ Server error. Check PHP logs.</div>'; return; }

    let html = `<div class="alert alert-${res.success?'success':'danger'} py-2">
      <i class="fa-solid fa-${res.success?'circle-check':'circle-xmark'} me-2"></i>${res.message}
    </div>`;

    if (res.errors && res.errors.length) {
      html += `<div class="alert alert-warning py-2">
        <strong><i class="fa-solid fa-triangle-exclamation me-2"></i>Warnings (${res.errors.length}):</strong>
        <ul class="mb-0 mt-1 small">${res.errors.map(e=>`<li>${e}</li>`).join('')}</ul>
      </div>`;
    }
    resultEl.innerHTML = html;

    if (res.success) {
      const newCount = parseInt(document.getElementById('qCount').textContent) + (res.imported||0);
      document.getElementById('qCount').textContent = newCount;
      clearUpload();
      setTimeout(() => loadQuestions(1), 1200);
    }
  };

  xhr.onerror = function() {
    progressEl.classList.add('d-none');
    resultEl.innerHTML = '<div class="alert alert-danger py-2">❌ Network error. Please try again.</div>';
  };

  xhr.open('POST', API_Q + '?action=upload');
  xhr.send(fd);
}
</script>

<style>
.btn-xs { font-size: 0.7rem; }
.nav-tabs .nav-link { color: #495057; }
.tab-content { border: 1px solid #dee2e6; border-top: none; }
#dropZone:hover { border-color: #0d6efd !important; background: #f0f7ff; }
</style>


<?php require_once '../includes/footer.php'; ?>
