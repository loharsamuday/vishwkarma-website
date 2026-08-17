<?php
$page_title = "Manage Question Bank";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Handle Delete Question
if (isset($_GET['delete_q'])) {
    $id = $_GET['delete_q'];
    $pdo->prepare("DELETE FROM mt_questions WHERE id=?")->execute([$id]);
    setFlashMessage('success', 'Question deleted.');
    header("Location: mt_questions.php");
    exit;
}

// Pagination setup
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Filters
$where = [];
$params = [];
if (!empty($_GET['subject'])) {
    $where[] = "q.subject_id = ?";
    $params[] = $_GET['subject'];
}
if (!empty($_GET['difficulty'])) {
    $where[] = "q.difficulty_level = ?";
    $params[] = $_GET['difficulty'];
}
if (!empty($_GET['search'])) {
    $where[] = "q.question_text LIKE ?";
    $params[] = "%" . $_GET['search'] . "%";
}

$where_clause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// Count total
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM mt_questions q $where_clause");
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch questions
$sql = "SELECT q.*, s.name as subject_name, t.name as topic_name 
        FROM mt_questions q 
        LEFT JOIN mt_subjects s ON q.subject_id = s.id 
        LEFT JOIN mt_topics t ON q.topic_id = t.id 
        $where_clause 
        ORDER BY q.id DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll();

$subjects = $pdo->query("SELECT * FROM mt_subjects ORDER BY name ASC")->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <h3 class="mb-0 text-dark"><i class="fa-solid fa-clipboard-question text-primary me-2"></i> Question Bank</h3>
        <div>
            <a href="mt_question_add.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Question</a>
            <a href="mt_question_import.php" class="btn btn-success"><i class="fa-solid fa-file-excel"></i> Bulk Import</a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search questions..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <select name="subject" class="form-select">
                        <option value="">All Subjects</option>
                        <?php foreach($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= (isset($_GET['subject']) && $_GET['subject'] == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="difficulty" class="form-select">
                        <option value="">All Difficulties</option>
                        <option value="Easy" <?= (isset($_GET['difficulty']) && $_GET['difficulty'] == 'Easy') ? 'selected' : '' ?>>Easy</option>
                        <option value="Moderate" <?= (isset($_GET['difficulty']) && $_GET['difficulty'] == 'Moderate') ? 'selected' : '' ?>>Moderate</option>
                        <option value="Difficult" <?= (isset($_GET['difficulty']) && $_GET['difficulty'] == 'Difficult') ? 'selected' : '' ?>>Difficult</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-dark w-100"><i class="fa-solid fa-search"></i> Filter</button>
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
                        <th>Question</th>
                        <th>Subject/Topic</th>
                        <th>Difficulty</th>
                        <th>Marks</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($questions) > 0): ?>
                        <?php foreach($questions as $q): ?>
                        <tr>
                            <td class="ps-3">#<?= $q['id'] ?></td>
                            <td style="max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <?= htmlspecialchars(strip_tags($q['question_text'])) ?>
                            </td>
                            <td>
                                <span class="badge bg-info text-dark"><?= htmlspecialchars($q['subject_name'] ?? 'N/A') ?></span><br>
                                <small class="text-muted"><?= htmlspecialchars($q['topic_name'] ?? 'N/A') ?></small>
                            </td>
                            <td>
                                <?php
                                $bclass = 'secondary';
                                if($q['difficulty_level'] == 'Easy') $bclass = 'success';
                                if($q['difficulty_level'] == 'Moderate') $bclass = 'warning';
                                if($q['difficulty_level'] == 'Difficult') $bclass = 'danger';
                                ?>
                                <span class="badge bg-<?= $bclass ?>"><?= $q['difficulty_level'] ?></span>
                            </td>
                            <td>+<?= (float)$q['marks'] ?> / -<?= (float)$q['negative_marks'] ?></td>
                            <td class="text-end pe-3">
                                <a href="mt_question_add.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-warning"><i class="fa-solid fa-edit"></i></a>
                                <a href="?delete_q=<?= $q['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4">No questions found.</td></tr>
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
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($_GET['search']??'') ?>&subject=<?= urlencode($_GET['subject']??'') ?>&difficulty=<?= urlencode($_GET['difficulty']??'') ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
