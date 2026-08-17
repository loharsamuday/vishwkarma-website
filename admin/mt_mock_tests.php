<?php
$page_title = "Manage Mock Tests";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Handle Delete Mock Test
if (isset($_GET['delete_test'])) {
    $id = $_GET['delete_test'];
    $pdo->prepare("DELETE FROM mt_mock_tests WHERE id=?")->execute([$id]);
    setFlashMessage('success', 'Mock Test deleted successfully.');
    header("Location: mt_mock_tests.php");
    exit;
}

// Fetch all mock tests
$sql = "SELECT t.*, e.name as exam_name, tt.name as type_name,
        (SELECT COUNT(*) FROM mt_test_questions WHERE mock_test_id = t.id) as mapped_questions 
        FROM mt_mock_tests t
        JOIN mt_exams e ON t.exam_id = e.id
        JOIN mt_test_types tt ON t.test_type_id = tt.id
        ORDER BY t.id DESC";
$tests = $pdo->query($sql)->fetchAll();

?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <h3 class="mb-0 text-dark"><i class="fa-solid fa-file-signature text-primary me-2"></i> Manage Mock Tests</h3>
        <a href="mt_mock_test_add.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create New Test</a>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="card border-0 shadow-sm p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Test Title</th>
                        <th>Exam / Type</th>
                        <th>Questions / Marks</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($tests) > 0): ?>
                        <?php foreach($tests as $t): ?>
                        <tr>
                            <td class="ps-3 fw-bold">
                                <?= htmlspecialchars($t['title']) ?><br>
                                <small class="text-muted"><i class="fa-regular fa-clock"></i> <?= $t['duration_minutes'] ?> mins | <?= $t['language'] ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info text-dark"><?= htmlspecialchars($t['exam_name']) ?></span><br>
                                <small class="text-muted"><?= htmlspecialchars($t['type_name']) ?></small>
                            </td>
                            <td>
                                Mapped: <strong class="<?= $t['mapped_questions'] < $t['total_questions'] ? 'text-danger' : 'text-success' ?>"><?= $t['mapped_questions'] ?></strong> / <?= $t['total_questions'] ?><br>
                                <small class="text-muted">Total Marks: <?= (float)$t['total_marks'] ?></small>
                            </td>
                            <td>
                                <?php
                                $bclass = 'secondary';
                                if($t['status'] == 'published') $bclass = 'success';
                                if($t['status'] == 'draft') $bclass = 'warning';
                                if($t['status'] == 'inactive') $bclass = 'danger';
                                ?>
                                <span class="badge bg-<?= $bclass ?>"><?= ucfirst($t['status']) ?></span><br>
                                <?php if($t['is_premium']): ?>
                                    <span class="badge bg-primary mt-1"><i class="fa-solid fa-crown text-warning"></i> Premium</span>
                                <?php else: ?>
                                    <span class="badge bg-success mt-1">Free</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <a href="mt_mock_test_questions.php?test_id=<?= $t['id'] ?>" class="btn btn-sm btn-dark" title="Manage Questions"><i class="fa-solid fa-list-check"></i> Manage Q's</a>
                                <a href="mt_mock_test_add.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-warning"><i class="fa-solid fa-edit"></i></a>
                                <a href="?delete_test=<?= $t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure? This will delete the test and all student attempts.')"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4">No Mock Tests found. Click 'Create New Test' to get started.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
