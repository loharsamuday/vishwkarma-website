<?php
$page_title = "Manage Test Questions";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

if (!isset($_GET['test_id'])) {
    header("Location: mt_mock_tests.php");
    exit;
}
$test_id = (int)$_GET['test_id'];

// Get Test Info
$stmt = $pdo->prepare("SELECT * FROM mt_mock_tests WHERE id = ?");
$stmt->execute([$test_id]);
$test = $stmt->fetch();
if (!$test) {
    header("Location: mt_mock_tests.php");
    exit;
}

// Handle Add Question
if (isset($_GET['add_q'])) {
    $q_id = (int)$_GET['add_q'];
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO mt_test_questions (mock_test_id, question_id, section_name, display_order) VALUES (?, ?, 'General', 0)");
        $stmt->execute([$test_id, $q_id]);
        setFlashMessage('success', 'Question added to test.');
    } catch (PDOException $e) {}
    
    // Redirect to same page with search params preserved
    $qs = $_SERVER['QUERY_STRING'];
    $qs = preg_replace('/&add_q=[0-9]+/', '', $qs);
    header("Location: mt_mock_test_questions.php?$qs");
    exit;
}

// Handle Remove Question
if (isset($_GET['remove_q'])) {
    $q_id = (int)$_GET['remove_q'];
    $stmt = $pdo->prepare("DELETE FROM mt_test_questions WHERE mock_test_id = ? AND question_id = ?");
    $stmt->execute([$test_id, $q_id]);
    setFlashMessage('success', 'Question removed from test.');
    
    $qs = $_SERVER['QUERY_STRING'];
    $qs = preg_replace('/&remove_q=[0-9]+/', '', $qs);
    header("Location: mt_mock_test_questions.php?$qs");
    exit;
}

// Get mapped questions
$sql_mapped = "SELECT q.*, tq.section_name, tq.display_order 
               FROM mt_test_questions tq 
               JOIN mt_questions q ON tq.question_id = q.id 
               WHERE tq.mock_test_id = ? 
               ORDER BY tq.section_name ASC, tq.display_order ASC, q.id ASC";
$stmt = $pdo->prepare($sql_mapped);
$stmt->execute([$test_id]);
$mapped_questions = $stmt->fetchAll();
$mapped_ids = array_column($mapped_questions, 'id');

// Search bank
$search = $_GET['search'] ?? '';
$subject_id = $_GET['subject'] ?? '';
$bank_questions = [];

if (isset($_GET['search_bank'])) {
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "q.question_text LIKE ?";
        $params[] = "%$search%";
    }
    if (!empty($subject_id)) {
        $where[] = "q.subject_id = ?";
        $params[] = $subject_id;
    }
    
    $where_clause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
    $sql_bank = "SELECT q.*, s.name as subject_name 
                 FROM mt_questions q 
                 LEFT JOIN mt_subjects s ON q.subject_id = s.id 
                 $where_clause 
                 ORDER BY q.id DESC LIMIT 50";
    $stmt = $pdo->prepare($sql_bank);
    $stmt->execute($params);
    $bank_questions = $stmt->fetchAll();
}

$subjects = $pdo->query("SELECT * FROM mt_subjects ORDER BY name ASC")->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <h3 class="mb-0 text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i> Manage Test Questions</h3>
        <a href="mt_mock_tests.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Tests</a>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="alert alert-info">
        <strong>Test:</strong> <?= htmlspecialchars($test['title']) ?> <br>
        <strong>Stats:</strong> Mapped <?= count($mapped_questions) ?> / <?= $test['total_questions'] ?> Questions | Target Marks: <?= $test['total_marks'] ?>
    </div>
    
    <div class="row">
        <!-- Search Question Bank -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white p-3">
                    <h5 class="mb-0 fw-bold">Search Question Bank</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-2 mb-3">
                        <input type="hidden" name="test_id" value="<?= $test_id ?>">
                        <input type="hidden" name="search_bank" value="1">
                        <div class="col-md-5">
                            <input type="text" name="search" class="form-control" placeholder="Search keyword..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-4">
                            <select name="subject" class="form-select">
                                <option value="">All Subjects</option>
                                <?php foreach($subjects as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $subject_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-dark w-100">Search</button>
                        </div>
                    </form>
                    
                    <?php if (isset($_GET['search_bank'])): ?>
                        <div class="table-responsive" style="max-height: 500px;">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Q ID</th>
                                        <th>Question</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($bank_questions) > 0): ?>
                                        <?php foreach($bank_questions as $bq): ?>
                                            <tr>
                                                <td>#<?= $bq['id'] ?></td>
                                                <td>
                                                    <small class="badge bg-info text-dark"><?= htmlspecialchars($bq['subject_name'] ?? 'N/A') ?></small><br>
                                                    <?= htmlspecialchars(substr(strip_tags($bq['question_text']), 0, 100)) ?>...
                                                </td>
                                                <td class="text-center">
                                                    <?php if(in_array($bq['id'], $mapped_ids)): ?>
                                                        <span class="badge bg-success"><i class="fa-solid fa-check"></i> Added</span>
                                                    <?php else: ?>
                                                        <?php 
                                                            $qs = $_SERVER['QUERY_STRING'];
                                                            $qs = preg_replace('/&add_q=[0-9]+/', '', $qs);
                                                        ?>
                                                        <a href="?<?= $qs ?>&add_q=<?= $bq['id'] ?>" class="btn btn-sm btn-outline-primary fw-bold">Add</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center py-3">No questions found matching criteria.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Mapped Questions -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-success text-white p-3">
                    <h5 class="mb-0 fw-bold">Currently Mapped Questions (<?= count($mapped_questions) ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px;">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Q ID</th>
                                    <th>Question</th>
                                    <th>Marks</th>
                                    <th class="text-end pe-3">Remove</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($mapped_questions) > 0): ?>
                                    <?php foreach($mapped_questions as $mq): ?>
                                        <tr>
                                            <td>#<?= $mq['id'] ?></td>
                                            <td><?= htmlspecialchars(substr(strip_tags($mq['question_text']), 0, 80)) ?>...</td>
                                            <td><?= (float)$mq['marks'] ?></td>
                                            <td class="text-end pe-3">
                                                <?php 
                                                    $qs = $_SERVER['QUERY_STRING'];
                                                    $qs = preg_replace('/&remove_q=[0-9]+/', '', $qs);
                                                ?>
                                                <a href="?<?= $qs ?>&remove_q=<?= $mq['id'] ?>" class="text-danger" onclick="return confirm('Remove question?')"><i class="fa-solid fa-times-circle fs-5"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-4">No questions mapped yet. Search and add from the left panel.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
