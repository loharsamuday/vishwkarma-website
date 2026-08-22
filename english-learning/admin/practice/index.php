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

    <div class="row mb-3">
        <div class="col-md-6">
            <form action="" method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search Questions..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-outline-secondary">Search</button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
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
                                <form action="" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this question?');">
                                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No questions found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <nav>
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
