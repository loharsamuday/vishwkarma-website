<?php
// admin/phrasal-verbs/index.php
session_start();
require_once '../../config/database.php';



$page_title = "Phrasal Verbs";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle Delete
if (isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM phrasal_verbs WHERE id = ?");
    if ($stmt->execute([$delete_id])) {
        $success_msg = "Phrasal verb deleted successfully!";
    } else {
        $error_msg = "Failed to delete phrasal verb.";
    }
}

// Search and Pagination
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

$where_clause = "";
$params = [];
if (!empty($search)) {
    $where_clause = "WHERE phrasal_verb LIKE ? OR hindi_meaning LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM phrasal_verbs $where_clause");
$stmt_total->execute($params);
$total_records = $stmt_total->fetchColumn();
$total_pages = ceil($total_records / $limit);

$sql = "SELECT p.*, c.name as category_name FROM phrasal_verbs p LEFT JOIN exam_categories c ON p.category_id = c.id $where_clause ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$phrasal_verbs = $stmt->fetchAll();
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Phrasal Verbs</h1>
        <div class="btn-toolbar mb-2 mb-md-0 gap-2">
            <a href="add.php" class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Add New
            </a>
            <a href="../import/index.php" class="btn btn-sm btn-outline-success">
                <i class="fas fa-file-csv"></i> Bulk Import CSV
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

    <div class="row mb-3 align-items-center">
        <div class="col-md-6 mb-2 mb-md-0">
            <form action="" method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                <?php if($limit != 10): ?><input type="hidden" name="limit" value="<?= $limit ?>"><?php endif; ?>
                <button type="submit" class="btn btn-outline-secondary">Search</button>
                <?php if(!empty($search)): ?>
                    <a href="index.php" class="btn btn-outline-danger ms-2" title="Clear Search"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>
        <div class="col-md-6 d-flex justify-content-md-end">
            <form action="" method="GET" class="d-flex align-items-center">
                <label class="me-2 fw-bold text-secondary text-nowrap">Show:</label>
                <?php if(!empty($search)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                <select name="limit" class="form-select w-auto fw-bold" onchange="this.form.submit()">
                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10 Records</option>
                    <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20 Records</option>
                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 Records</option>
                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100 Records</option>
                </select>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Phrasal Verb</th>
                    <th>Hindi Meaning</th>
                    <th>Category</th>
                    <th>Difficulty</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($phrasal_verbs) > 0): ?>
                    <?php foreach ($phrasal_verbs as $row): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['phrasal_verb']) ?></td>
                            <td><?= htmlspecialchars($row['hindi_meaning']) ?></td>
                            <td><?= htmlspecialchars($row['category_name'] ?? 'N/A') ?></td>
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
                                <form action="" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this?');">
                                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No phrasal verbs found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center flex-wrap shadow-sm">
            <!-- Previous Button -->
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>&limit=<?= $limit ?>">&laquo; Prev</a>
            </li>
            
            <?php
            $adjacents = 2;
            $start = max(1, $page - $adjacents);
            $end = min($total_pages, $page + $adjacents);
            
            if ($start > 1) {
                echo '<li class="page-item"><a class="page-link" href="?page=1&search='.urlencode($search).'&limit='.$limit.'">1</a></li>';
                if ($start > 2) {
                    echo '<li class="page-item disabled"><span class="page-link text-muted">...</span></li>';
                }
            }
            
            for ($i = $start; $i <= $end; $i++) {
                $active = ($i == $page) ? 'active' : '';
                echo '<li class="page-item '.$active.'"><a class="page-link" href="?page='.$i.'&search='.urlencode($search).'&limit='.$limit.'">'.$i.'</a></li>';
            }
            
            if ($end < $total_pages) {
                if ($end < $total_pages - 1) {
                    echo '<li class="page-item disabled"><span class="page-link text-muted">...</span></li>';
                }
                echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'&search='.urlencode($search).'&limit='.$limit.'">'.$total_pages.'</a></li>';
            }
            ?>
            
            <!-- Next Button -->
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search) ?>&limit=<?= $limit ?>">Next &raquo;</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</main>
<?php require_once '../includes/footer.php'; ?>

