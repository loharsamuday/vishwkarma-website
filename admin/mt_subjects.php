<?php
$page_title = "Manage Subjects & Topics";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Handle Add/Edit Subject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_subject') {
    $id = $_POST['subject_id'] ?? 0;
    $name = trim($_POST['name']);
    $status = $_POST['status'];

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE mt_subjects SET name=?, status=? WHERE id=?");
        $stmt->execute([$name, $status, $id]);
        setFlashMessage('success', 'Subject updated successfully.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO mt_subjects (name, status) VALUES (?, ?)");
        $stmt->execute([$name, $status]);
        setFlashMessage('success', 'Subject added successfully.');
    }
    header("Location: mt_subjects.php");
    exit;
}

// Handle Delete Subject
if (isset($_GET['delete_sub'])) {
    $id = $_GET['delete_sub'];
    $pdo->prepare("DELETE FROM mt_subjects WHERE id=?")->execute([$id]);
    setFlashMessage('success', 'Subject deleted.');
    header("Location: mt_subjects.php");
    exit;
}

// Handle Add/Edit Topic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_topic') {
    $id = $_POST['topic_id'] ?? 0;
    $subject_id = $_POST['subject_id'];
    $name = trim($_POST['name']);
    $status = $_POST['status'];

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE mt_topics SET subject_id=?, name=?, status=? WHERE id=?");
        $stmt->execute([$subject_id, $name, $status, $id]);
        setFlashMessage('success', 'Topic updated successfully.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO mt_topics (subject_id, name, status) VALUES (?, ?, ?)");
        $stmt->execute([$subject_id, $name, $status]);
        setFlashMessage('success', 'Topic added successfully.');
    }
    header("Location: mt_subjects.php");
    exit;
}

// Handle Delete Topic
if (isset($_GET['delete_topic'])) {
    $id = $_GET['delete_topic'];
    $pdo->prepare("DELETE FROM mt_topics WHERE id=?")->execute([$id]);
    setFlashMessage('success', 'Topic deleted.');
    header("Location: mt_subjects.php");
    exit;
}

$subjects = $pdo->query("SELECT * FROM mt_subjects ORDER BY name ASC")->fetchAll();
$topics = $pdo->query("SELECT t.*, s.name as subject_name FROM mt_topics t JOIN mt_subjects s ON t.subject_id = s.id ORDER BY t.name ASC")->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <h3 class="mb-0 text-dark"><i class="fa-solid fa-book text-primary me-2"></i> Manage Subjects & Topics</h3>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="row">
        <!-- Subjects -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center p-3">
                    <h5 class="mb-0 fw-bold">Subjects</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#subjectModal" onclick="resetSubjectForm()"><i class="fa-solid fa-plus"></i> Add New</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Name</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($subjects as $s): ?>
                                <tr>
                                    <td class="ps-3 fw-bold"><?= htmlspecialchars($s['name']) ?></td>
                                    <td>
                                        <?php if($s['status'] == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-sm btn-warning" onclick='editSubject(<?= json_encode($s) ?>)'><i class="fa-solid fa-edit"></i></button>
                                        <a href="?delete_sub=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure? This will delete all linked topics.')"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Topics -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center p-3">
                    <h5 class="mb-0 fw-bold">Topics</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#topicModal" onclick="resetTopicForm()"><i class="fa-solid fa-plus"></i> Add New</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Topic Name</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($topics as $t): ?>
                                <tr>
                                    <td class="ps-3 fw-bold"><?= htmlspecialchars($t['name']) ?></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($t['subject_name']) ?></span></td>
                                    <td>
                                        <?php if($t['status'] == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-sm btn-warning" onclick='editTopic(<?= json_encode($t) ?>)'><i class="fa-solid fa-edit"></i></button>
                                        <a href="?delete_topic=<?= $t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Subject Modal -->
<div class="modal fade" id="subjectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="subModalTitle">Add Subject</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="save_subject">
        <input type="hidden" name="subject_id" id="sub_id" value="0">
        <div class="mb-3">
            <label class="form-label fw-bold">Subject Name</label>
            <input type="text" name="name" id="sub_name" class="form-control" required placeholder="e.g., Reasoning">
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Status</label>
            <select name="status" id="sub_status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Subject</button>
      </div>
      </form>
    </div>
  </div>
</div>

<!-- Topic Modal -->
<div class="modal fade" id="topicModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="topicModalTitle">Add Topic</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="save_topic">
        <input type="hidden" name="topic_id" id="top_id" value="0">
        
        <div class="mb-3">
            <label class="form-label fw-bold">Select Subject</label>
            <select name="subject_id" id="top_subject_id" class="form-select" required>
                <option value="">-- Select Subject --</option>
                <?php foreach($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Topic Name</label>
            <input type="text" name="name" id="top_name" class="form-control" required placeholder="e.g., Syllogism">
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Status</label>
            <select name="status" id="top_status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Topic</button>
      </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetSubjectForm() {
    document.getElementById('subModalTitle').innerText = 'Add Subject';
    document.getElementById('sub_id').value = '0';
    document.getElementById('sub_name').value = '';
    document.getElementById('sub_status').value = 'active';
}
function editSubject(sub) {
    document.getElementById('subModalTitle').innerText = 'Edit Subject';
    document.getElementById('sub_id').value = sub.id;
    document.getElementById('sub_name').value = sub.name;
    document.getElementById('sub_status').value = sub.status;
    new bootstrap.Modal(document.getElementById('subjectModal')).show();
}

function resetTopicForm() {
    document.getElementById('topicModalTitle').innerText = 'Add Topic';
    document.getElementById('top_id').value = '0';
    document.getElementById('top_subject_id').value = '';
    document.getElementById('top_name').value = '';
    document.getElementById('top_status').value = 'active';
}
function editTopic(top) {
    document.getElementById('topicModalTitle').innerText = 'Edit Topic';
    document.getElementById('top_id').value = top.id;
    document.getElementById('top_subject_id').value = top.subject_id;
    document.getElementById('top_name').value = top.name;
    document.getElementById('top_status').value = top.status;
    new bootstrap.Modal(document.getElementById('topicModal')).show();
}
</script>
<?php require_once 'includes/footer.php'; ?>
