<?php
// admin/vocabulary.php
session_start();
require_once __DIR__ . '/../config/database.php'; // Required for early POST processing

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete_by_date'])) {
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';

    if (!empty($start_date) && !empty($end_date)) {
        $stmt = $pdo->prepare("DELETE FROM vocabulary WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?");
        $stmt->execute([$start_date, $end_date]);
        $deleted_count = $stmt->rowCount();
        header("Location: vocabulary.php?msg=bulk_deleted&count=" . $deleted_count);
        exit();
    } else {
        $error_msg = "Please select both start and end dates.";
    }
}

$page_title = 'Manage Vocabulary';
include 'includes/header.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
$search = $_GET['search'] ?? '';
$offset = ($page - 1) * $limit;

$where_clause = "";
$params = [];
if (!empty($search)) {
    $where_clause = "WHERE v.word LIKE ? OR v.hindi_meaning LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM vocabulary v $where_clause");
$stmt_total->execute($params);
$total_vocab = $stmt_total->fetchColumn();
$total_pages = ceil($total_vocab / $limit);

$stmt = $pdo->prepare("
    SELECT v.*, s.title as story_title 
    FROM vocabulary v 
    LEFT JOIN stories s ON v.story_id = s.id 
    $where_clause
    ORDER BY v.created_at DESC 
    LIMIT ? OFFSET ?
");
foreach($params as $i => $p) {
    $stmt->bindValue($i + 1, $p);
}
$stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$vocabularies = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Vocabulary</h1>
    <div>
        <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#bulkDeleteModal">
            <i class="fas fa-trash-alt me-1"></i> Bulk Delete
        </button>
        <a href="add-vocabulary.php" class="btn btn-success"><i class="fas fa-plus me-1"></i> Add Word</a>
    </div>
</div>

<?php if(isset($error_msg)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
<?php endif; ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'added'): ?>
    <div class="alert alert-success">Word added successfully.</div>
<?php endif; ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
    <div class="alert alert-success">Word updated successfully.</div>
<?php endif; ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
    <div class="alert alert-success">Word deleted successfully.</div>
<?php endif; ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'bulk_deleted'): ?>
    <div class="alert alert-success"><?= isset($_GET['count']) ? (int)$_GET['count'] : 0 ?> vocabulary words deleted successfully.</div>
<?php endif; ?>

<div class="row mb-3">
    <div class="col-md-8">
        <form action="" method="GET" class="d-flex">
            <input type="text" name="search" class="form-control me-2" placeholder="Search Vocabulary..." value="<?= htmlspecialchars($search) ?>">
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
                <option value="15" <?= $limit == 15 ? 'selected' : '' ?>>15</option>
                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
            </select>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Word</th>
                        <th>Hindi Meaning</th>
                        <th>Story</th>
                        <th>Part of Speech</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($vocabularies) > 0): ?>
                        <?php foreach ($vocabularies as $vocab): ?>
                        <tr>
                            <td><strong><?= escape($vocab['word']) ?></strong></td>
                            <td><?= escape($vocab['hindi_meaning']) ?></td>
                            <td>
                                <?php if($vocab['story_id']): ?>
                                    <a href="edit-story.php?id=<?= $vocab['story_id'] ?>"><?= escape($vocab['story_title']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">No Story</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary"><?= escape($vocab['part_of_speech']) ?></span></td>
                            <td class="text-end">
                                <a href="edit-vocabulary.php?id=<?= $vocab['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete-vocabulary.php?id=<?= $vocab['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this vocabulary word?');" title="Delete"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4">No vocabulary found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($total_pages > 0): ?>
<nav class="mt-4" aria-label="Page navigation">
    <ul class="pagination justify-content-center">
        <!-- Previous Button -->
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= ($page <= 1) ? '#' : '?page=' . ($page - 1) . '&search=' . urlencode($search) . '&limit=' . $limit ?>" <?= ($page <= 1) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Previous</a>
        </li>
        
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
            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
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

<!-- Bulk Delete Modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="vocabulary.php" method="POST">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="bulkDeleteModalLabel"><i class="fas fa-trash-alt me-2"></i>Bulk Delete Vocabulary by Date</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">Delete vocabulary words that were added within a specific date range.</p>
          <div class="mb-3">
            <label for="start_date" class="form-label fw-bold">Start Date</label>
            <input type="date" class="form-control" id="start_date" name="start_date" required>
          </div>
          <div class="mb-3">
            <label for="end_date" class="form-label fw-bold">End Date</label>
            <input type="date" class="form-control" id="end_date" name="end_date" required>
          </div>
          <div class="alert alert-warning small mb-0">
             <i class="fas fa-exclamation-triangle me-1"></i> <strong>Warning:</strong> This action cannot be undone. All vocabulary words created between these dates will be permanently deleted.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="bulk_delete_by_date" class="btn btn-danger" onclick="return confirm('Are you absolutely sure you want to permanently delete these vocabulary words?');">Confirm Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
