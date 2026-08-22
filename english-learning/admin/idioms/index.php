<?php
// admin/idioms/index.php
session_start();
require_once '../../config/database.php';



$page_title = "Idioms";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle Delete
if (isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM idioms WHERE id = ?");
    if ($stmt->execute([$delete_id])) {
        $success_msg = "Idiom deleted successfully!";
    } else {
        $error_msg = "Failed to delete idiom.";
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
    $where_clause = "WHERE idiom LIKE ? OR hindi_meaning LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Total records
$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM idioms $where_clause");
$stmt_total->execute($params);
$total_records = $stmt_total->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch records
$sql = "SELECT i.*, c.name as category_name FROM idioms i LEFT JOIN exam_categories c ON i.category_id = c.id $where_clause ORDER BY i.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$idioms = $stmt->fetchAll();
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Idioms</h1>
        <div class="btn-toolbar mb-2 mb-md-0 gap-2">
            <a href="add.php" class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Add New Idiom
            </a>
            <a href="../import/index.php" class="btn btn-sm btn-outline-success">
                <i class="fas fa-file-csv"></i> Bulk Import CSV
            </a>
        </div>
    </div>

    <?php if (isset($success_msg) || isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success_msg ?? $_SESSION['success_msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-md-6">
            <form action="" method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search Idioms..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-outline-secondary">Search</button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Idiom</th>
                    <th>Hindi Meaning</th>
                    <th>Category</th>
                    <th>Difficulty</th>
                    <th>Status</th>
                    <th>Featured</th>
                    <th>Views</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($idioms) > 0): ?>
                    <?php foreach ($idioms as $row): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['idiom']) ?></td>
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
                                <?= $row['featured'] ? '<span class="badge bg-warning text-dark"><i class="fas fa-star"></i></span>' : '' ?>
                            </td>
                            <td><?= $row['views'] ?></td>
                            <td>
                                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <form action="" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this?');">
                                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">No idioms found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>

