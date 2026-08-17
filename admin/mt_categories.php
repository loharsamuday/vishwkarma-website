<?php
$page_title = "Manage Exam Categories";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Handle Add/Edit Category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_category') {
    $id = $_POST['category_id'] ?? 0;
    $name = trim($_POST['name']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $status = $_POST['status'];

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE mt_exam_categories SET name=?, slug=?, status=? WHERE id=?");
        $stmt->execute([$name, $slug, $status, $id]);
        setFlashMessage('success', 'Category updated successfully.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO mt_exam_categories (name, slug, status) VALUES (?, ?, ?)");
        $stmt->execute([$name, $slug, $status]);
        setFlashMessage('success', 'Category added successfully.');
    }
    header("Location: mt_categories.php");
    exit;
}

// Handle Delete Category
if (isset($_GET['delete_cat'])) {
    $id = $_GET['delete_cat'];
    $pdo->prepare("DELETE FROM mt_exam_categories WHERE id=?")->execute([$id]);
    setFlashMessage('success', 'Category deleted.');
    header("Location: mt_categories.php");
    exit;
}

// Handle Add/Edit Exam
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_exam') {
    $id = $_POST['exam_id'] ?? 0;
    $category_id = $_POST['category_id'];
    $name = trim($_POST['name']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $status = $_POST['status'];

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE mt_exams SET category_id=?, name=?, slug=?, status=? WHERE id=?");
        $stmt->execute([$category_id, $name, $slug, $status, $id]);
        setFlashMessage('success', 'Exam updated successfully.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO mt_exams (category_id, name, slug, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$category_id, $name, $slug, $status]);
        setFlashMessage('success', 'Exam added successfully.');
    }
    header("Location: mt_categories.php");
    exit;
}

// Handle Delete Exam
if (isset($_GET['delete_exam'])) {
    $id = $_GET['delete_exam'];
    $pdo->prepare("DELETE FROM mt_exams WHERE id=?")->execute([$id]);
    setFlashMessage('success', 'Exam deleted.');
    header("Location: mt_categories.php");
    exit;
}

$categories = $pdo->query("SELECT * FROM mt_exam_categories ORDER BY name ASC")->fetchAll();
$exams = $pdo->query("SELECT e.*, c.name as category_name FROM mt_exams e JOIN mt_exam_categories c ON e.category_id = c.id ORDER BY e.name ASC")->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <h3 class="mb-0 text-dark"><i class="fa-solid fa-list text-primary me-2"></i> Manage Categories & Exams</h3>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="row">
        <!-- Exam Categories -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center p-3">
                    <h5 class="mb-0 fw-bold">Exam Categories</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="resetCategoryForm()"><i class="fa-solid fa-plus"></i> Add New</button>
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
                                <?php foreach($categories as $c): ?>
                                <tr>
                                    <td class="ps-3 fw-bold"><?= htmlspecialchars($c['name']) ?></td>
                                    <td>
                                        <?php if($c['status'] == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-sm btn-warning" onclick='editCategory(<?= json_encode($c) ?>)'><i class="fa-solid fa-edit"></i></button>
                                        <a href="?delete_cat=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure? This will delete all linked exams.')"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exams -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center p-3">
                    <h5 class="mb-0 fw-bold">Exams</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#examModal" onclick="resetExamForm()"><i class="fa-solid fa-plus"></i> Add New</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Exam Name</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($exams as $e): ?>
                                <tr>
                                    <td class="ps-3 fw-bold"><?= htmlspecialchars($e['name']) ?></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($e['category_name']) ?></span></td>
                                    <td>
                                        <?php if($e['status'] == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-sm btn-warning" onclick='editExam(<?= json_encode($e) ?>)'><i class="fa-solid fa-edit"></i></button>
                                        <a href="?delete_exam=<?= $e['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fa-solid fa-trash"></i></a>
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

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="catModalTitle">Add Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="save_category">
        <input type="hidden" name="category_id" id="cat_id" value="0">
        <div class="mb-3">
            <label class="form-label fw-bold">Category Name</label>
            <input type="text" name="name" id="cat_name" class="form-control" required placeholder="e.g., Banking">
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Status</label>
            <select name="status" id="cat_status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Category</button>
      </div>
      </form>
    </div>
  </div>
</div>

<!-- Exam Modal -->
<div class="modal fade" id="examModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <form method="POST">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="examModalTitle">Add Exam</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="save_exam">
        <input type="hidden" name="exam_id" id="ex_id" value="0">
        
        <div class="mb-3">
            <label class="form-label fw-bold">Select Category</label>
            <select name="category_id" id="ex_category_id" class="form-select" required>
                <option value="">-- Select Category --</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Exam Name</label>
            <input type="text" name="name" id="ex_name" class="form-control" required placeholder="e.g., SBI PO">
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Status</label>
            <select name="status" id="ex_status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Exam</button>
      </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetCategoryForm() {
    document.getElementById('catModalTitle').innerText = 'Add Category';
    document.getElementById('cat_id').value = '0';
    document.getElementById('cat_name').value = '';
    document.getElementById('cat_status').value = 'active';
}
function editCategory(cat) {
    document.getElementById('catModalTitle').innerText = 'Edit Category';
    document.getElementById('cat_id').value = cat.id;
    document.getElementById('cat_name').value = cat.name;
    document.getElementById('cat_status').value = cat.status;
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

function resetExamForm() {
    document.getElementById('examModalTitle').innerText = 'Add Exam';
    document.getElementById('ex_id').value = '0';
    document.getElementById('ex_category_id').value = '';
    document.getElementById('ex_name').value = '';
    document.getElementById('ex_status').value = 'active';
}
function editExam(exam) {
    document.getElementById('examModalTitle').innerText = 'Edit Exam';
    document.getElementById('ex_id').value = exam.id;
    document.getElementById('ex_category_id').value = exam.category_id;
    document.getElementById('ex_name').value = exam.name;
    document.getElementById('ex_status').value = exam.status;
    new bootstrap.Modal(document.getElementById('examModal')).show();
}
</script>
<?php require_once 'includes/footer.php'; ?>
