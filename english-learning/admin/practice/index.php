<?php
// admin/practice/index.php
session_start();
require_once '../../config/database.php';

$page_title = "Practice Questions";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

if (isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM practice_questions WHERE id = ?");
    if ($stmt->execute([$delete_id])) {
        $success_msg = "Question deleted successfully!";
    } else {
        $error_msg = "Failed to delete question.";
    }
}

if (isset($_POST['bulk_delete'])) {
    if (!empty($_POST['question_ids'])) {
        $ids = $_POST['question_ids'];
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $stmt = $pdo->prepare("DELETE FROM practice_questions WHERE id IN ($placeholders)");
        if ($stmt->execute($ids)) {
            $success_msg = count($ids) . " questions deleted successfully!";
        } else {
            $error_msg = "Failed to delete selected questions.";
        }
    } else {
        $error_msg = "No questions selected for deletion.";
    }
}

$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

$where_clause = "";
$params = [];
if (!empty($search)) {
    $where_clause = "WHERE question LIKE ?";
    $params[] = "%$search%";
}

$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM practice_questions $where_clause");
$stmt_total->execute($params);
$total_records = $stmt_total->fetchColumn();
$total_pages = ceil($total_records / $limit);

$sql = "SELECT * FROM practice_questions $where_clause ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll();
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Practice Questions</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="import.php" class="btn btn-sm btn-outline-success me-2">
                <i class="fas fa-file-csv"></i> Bulk Upload CSV
            </a>
            <a href="add.php" class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Add New Question
            </a>
        </div>
    </div>

    <?php if (isset($success_msg) || isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success_msg ?? $_SESSION['success_msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
    
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-md-8">
            <form action="" method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search Questions..." value="<?= htmlspecialchars($search) ?>">
                <?php if (isset($_GET['limit'])): ?>
                    <input type="hidden" name="limit" value="<?= (int)$_GET['limit'] ?>">
                <?php endif; ?>
                <button type="submit" class="btn btn-outline-secondary">Search</button>
            </form>
        </div>
        <div class="col-md-4 text-end">
            <form action="" method="GET" class="d-inline-block">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <label for="limit" class="me-2 fw-bold text-muted small">Per Page:</label>
                <select name="limit" id="limit" class="form-select form-select-sm d-inline-block w-auto shadow-sm" onchange="this.form.submit()">
                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                </select>
            </form>
        </div>
    </div>

    <form action="" method="POST" id="bulkDeleteForm">
        <div class="mb-2 d-flex justify-content-between align-items-center">
            <button type="submit" name="bulk_delete" value="1" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete all selected questions?');">
                <i class="fas fa-trash-alt me-1"></i> Delete Selected
            </button>
            <span class="text-muted small">Total Questions: <?= $total_records ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th width="40"><input type="checkbox" id="selectAll" title="Select All"></th>
                        <th>ID</th>
                        <th>Question</th>
                        <th>Type</th>
                        <th>Difficulty</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($questions) > 0): ?>
                        <?php foreach ($questions as $row): ?>
                            <tr>
                                <td><input type="checkbox" name="question_ids[]" value="<?= $row['id'] ?>" class="question-checkbox"></td>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars(substr($row['question'], 0, 50)) ?>...</td>
                                <td><?= ucfirst($row['content_type']) ?></td>
                                <td><?= htmlspecialchars($row['difficulty']) ?></td>
                                <td>
                                    <?php if ($row['status'] == 'Published'): ?>
                                        <span class="badge bg-success">Published</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                    <!-- Use a button with form="" attribute to avoid nesting forms or just keep it as link, wait we can't nest forms -->
                                    <button type="submit" name="delete_id" value="<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this question?');" form="bulkDeleteForm"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No questions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
    
    <?php if ($total_pages > 0): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <!-- Previous Button -->
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= ($page <= 1) ? '#' : '?page=' . ($page - 1) . '&search=' . urlencode($search) . '&limit=' . $limit ?>" <?= ($page <= 1) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Previous</a>
            </li>
            
            <!-- Page Numbers -->
            <?php 
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            if ($end_page - $start_page < 4) {
                if ($start_page == 1) {
                    $end_page = min($total_pages, 5);
                } elseif ($end_page == $total_pages) {
                    $start_page = max(1, $total_pages - 4);
                }
            }
            ?>
            
            <?php if ($start_page > 1): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
            
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&limit=<?= $limit ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            
            <?php if ($end_page < $total_pages): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
            
            <!-- Next Button -->
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= ($page >= $total_pages) ? '#' : '?page=' . ($page + 1) . '&search=' . urlencode($search) . '&limit=' . $limit ?>" <?= ($page >= $total_pages) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.question-checkbox');
            
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(cb => {
                        cb.checked = this.checked;
                    });
                });
            }
            
            // Uncheck "Select All" if one of the items is manually unchecked
            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    if (!this.checked) {
                        selectAll.checked = false;
                    } else {
                        // Check if all are checked
                        const allChecked = Array.from(checkboxes).every(c => c.checked);
                        selectAll.checked = allChecked;
                    }
                });
            });
        });
    </script>
</main>
<?php require_once '../includes/footer.php'; ?>
