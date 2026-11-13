<?php
$page_title = "Student Results & Analytics";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch all tests for filter
$tests = $pdo->query("SELECT id, title FROM mt_mock_tests ORDER BY id DESC")->fetchAll();

// Pagination setup
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Filters
$where = [];
$params = [];
if (!empty($_GET['test_id'])) {
    $where[] = "a.mock_test_id = ?";
    $params[] = $_GET['test_id'];
}
if (!empty($_GET['status'])) {
    $where[] = "a.status = ?";
    $params[] = $_GET['status'];
}

$where_clause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// Count total
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM mt_test_attempts a $where_clause");
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch attempts
$sql = "SELECT a.*, t.title as test_title, t.total_questions as test_total_questions, t.total_marks as test_total_marks, u.first_name, u.last_name, u.email 
        FROM mt_test_attempts a
        JOIN mt_mock_tests t ON a.mock_test_id = t.id
        JOIN users u ON a.user_id = u.id
        $where_clause 
        ORDER BY a.id DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <h3 class="mb-0 text-dark"><i class="fa-solid fa-chart-bar text-primary me-2"></i> Student Results & Analytics</h3>
    </div>
    
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <select name="test_id" class="form-select">
                        <option value="">All Tests</option>
                        <?php foreach($tests as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= (isset($_GET['test_id']) && $_GET['test_id'] == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="completed" <?= (isset($_GET['status']) && $_GET['status'] == 'completed') ? 'selected' : '' ?>>Completed</option>
                        <option value="in_progress" <?= (isset($_GET['status']) && $_GET['status'] == 'in_progress') ? 'selected' : '' ?>>In Progress</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-dark w-100"><i class="fa-solid fa-search me-1"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Student</th>
                        <th>Mock Test</th>
                        <th>Questions Breakdown</th>
                        <th>Score & Accuracy</th>
                        <th>Status / Date</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($attempts) > 0): ?>
                        <?php foreach($attempts as $a): 
                            $correct = isset($a['total_correct']) ? (int)$a['total_correct'] : (int)$a['correct_answers'];
                            $wrong = isset($a['total_incorrect']) ? (int)$a['total_incorrect'] : (int)$a['wrong_answers'];
                            $skipped = isset($a['total_unanswered']) ? (int)$a['total_unanswered'] : (int)$a['unattempted'];
                            $total_q = (int)$a['test_total_questions'];
                            if ($total_q <= 0) $total_q = $correct + $wrong + $skipped;
                            $attempted = $correct + $wrong;
                        ?>
                        <tr>
                            <td class="ps-3">#<?= $a['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($a['email']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($a['test_title']) ?></td>
                            <td>
                                <?php if($a['status'] == 'completed' || $a['status'] == 'submitted'): ?>
                                    <div class="d-flex gap-1 flex-wrap mb-1">
                                        <span class="badge bg-primary" title="Attempted"><?= $attempted ?>/<?= $total_q ?> Att</span>
                                        <span class="badge bg-success" title="Correct"><i class="fa-solid fa-check"></i> <?= $correct ?></span>
                                        <span class="badge bg-danger" title="Wrong"><i class="fa-solid fa-times"></i> <?= $wrong ?></span>
                                        <span class="badge bg-secondary" title="Skipped"><i class="fa-solid fa-forward"></i> <?= $skipped ?></span>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.75rem;">Total (<?= $total_q ?>) = <?= $correct ?>C + <?= $wrong ?>W + <?= $skipped ?>S</small>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border">Test in progress</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($a['status'] == 'completed' || $a['status'] == 'submitted'): ?>
                                    <strong><?= (float)$a['score'] ?></strong> / <?= (float)$a['test_total_marks'] ?><br>
                                    <small class="text-success fw-bold"><?= number_format($a['accuracy'], 1) ?>% Acc</small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($a['status'] == 'completed' || $a['status'] == 'submitted'): ?>
                                    <span class="badge bg-success">Completed</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">In Progress</span>
                                <?php endif; ?><br>
                                <small class="text-muted"><?= date('d M Y H:i', strtotime($a['start_time'])) ?></small>
                            </td>
                            <td class="text-end pe-3">
                                <?php if($a['status'] == 'completed' || $a['status'] == 'submitted'): ?>
                                    <a href="../mt-result.php?id=<?= $a['id'] ?>" target="_blank" class="btn btn-sm btn-info text-white">
                                        <i class="fa-solid fa-eye me-1"></i> View Result
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-4">No results found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="card-footer bg-white p-3">
            <nav>
                <ul class="pagination mb-0 justify-content-center">
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&test_id=<?= urlencode($_GET['test_id']??'') ?>&status=<?= urlencode($_GET['status']??'') ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
