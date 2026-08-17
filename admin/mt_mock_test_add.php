<?php
$page_title = "Add/Edit Mock Test";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$t = [
    'title' => '',
    'exam_id' => '',
    'test_type_id' => '',
    'duration_minutes' => 60,
    'total_marks' => 100,
    'total_questions' => 100,
    'negative_marking' => 0.25,
    'language' => 'English, Hindi',
    'instructions' => '',
    'is_premium' => 0,
    'attempt_limit' => 1,
    'result_visibility' => 'immediate',
    'status' => 'draft'
];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM mt_mock_tests WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) $t = $row;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title))) . '-' . time();
    $exam_id = (int)$_POST['exam_id'];
    $test_type_id = (int)$_POST['test_type_id'];
    $duration_minutes = (int)$_POST['duration_minutes'];
    $total_marks = (float)$_POST['total_marks'];
    $total_questions = (int)$_POST['total_questions'];
    $negative_marking = (float)$_POST['negative_marking'];
    $language = $_POST['language'];
    $instructions = trim($_POST['instructions']);
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    $attempt_limit = (int)$_POST['attempt_limit'];
    $result_visibility = $_POST['result_visibility'];
    $status = $_POST['status'];

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE mt_mock_tests SET title=?, exam_id=?, test_type_id=?, duration_minutes=?, total_marks=?, total_questions=?, negative_marking=?, language=?, instructions=?, is_premium=?, attempt_limit=?, result_visibility=?, status=? WHERE id=?");
        $stmt->execute([$title, $exam_id, $test_type_id, $duration_minutes, $total_marks, $total_questions, $negative_marking, $language, $instructions, $is_premium, $attempt_limit, $result_visibility, $status, $id]);
        setFlashMessage('success', 'Mock Test updated successfully.');
        header("Location: mt_mock_tests.php");
    } else {
        $stmt = $pdo->prepare("INSERT INTO mt_mock_tests (title, slug, exam_id, test_type_id, duration_minutes, total_marks, total_questions, negative_marking, language, instructions, is_premium, attempt_limit, result_visibility, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $slug, $exam_id, $test_type_id, $duration_minutes, $total_marks, $total_questions, $negative_marking, $language, $instructions, $is_premium, $attempt_limit, $result_visibility, $status]);
        $new_id = $pdo->lastInsertId();
        setFlashMessage('success', 'Mock Test created successfully. Now map questions to this test.');
        header("Location: mt_mock_test_questions.php?test_id=" . $new_id);
    }
    exit;
}

$exams = $pdo->query("SELECT * FROM mt_exams ORDER BY name ASC")->fetchAll();
$test_types = $pdo->query("SELECT * FROM mt_test_types ORDER BY name ASC")->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <h3 class="mb-0 text-dark">
            <i class="fa-solid <?= $id > 0 ? 'fa-edit' : 'fa-plus' ?> text-primary me-2"></i> 
            <?= $id > 0 ? 'Edit Mock Test' : 'Create Mock Test' ?>
        </h3>
        <a href="mt_mock_tests.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Tests</a>
    </div>
    
    <div class="card border-0 shadow-sm p-4 mb-5">
        <form method="POST">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Test Title *</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($t['title']) ?>" required placeholder="e.g., SBI PO Prelims Mock Test 1">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Select Exam *</label>
                    <select name="exam_id" class="form-select" required>
                        <option value="">-- Select Exam --</option>
                        <?php foreach($exams as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= $t['exam_id'] == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Test Type *</label>
                    <select name="test_type_id" class="form-select" required>
                        <option value="">-- Select Type --</option>
                        <?php foreach($test_types as $tt): ?>
                            <option value="<?= $tt['id'] ?>" <?= $t['test_type_id'] == $tt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Duration (Minutes) *</label>
                    <input type="number" name="duration_minutes" class="form-control" value="<?= $t['duration_minutes'] ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Total Questions *</label>
                    <input type="number" name="total_questions" class="form-control" value="<?= $t['total_questions'] ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Total Marks *</label>
                    <input type="number" step="0.5" name="total_marks" class="form-control" value="<?= $t['total_marks'] ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Global Negative Mark</label>
                    <input type="number" step="0.01" name="negative_marking" class="form-control" value="<?= $t['negative_marking'] ?>" required>
                    <small class="text-muted">Will be overridden by question-level negative mark if specified.</small>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Language</label>
                    <input type="text" name="language" class="form-control" value="<?= htmlspecialchars($t['language']) ?>" placeholder="e.g. English, Hindi">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Attempt Limit</label>
                    <input type="number" name="attempt_limit" class="form-control" value="<?= $t['attempt_limit'] ?>" min="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Result Visibility</label>
                    <select name="result_visibility" class="form-select">
                        <option value="immediate" <?= $t['result_visibility'] == 'immediate' ? 'selected' : '' ?>>Immediate after submission</option>
                        <option value="manual" <?= $t['result_visibility'] == 'manual' ? 'selected' : '' ?>>Manual Release by Admin</option>
                    </select>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Status</label>
                    <select name="status" class="form-select">
                        <option value="draft" <?= $t['status'] == 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= $t['status'] == 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="inactive" <?= $t['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-8 pt-4">
                    <div class="form-check form-switch fs-5">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_premium" id="is_premium" value="1" <?= $t['is_premium'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold text-danger" for="is_premium"><i class="fa-solid fa-crown"></i> Mark as Premium Test</label>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Test Instructions</label>
                <textarea name="instructions" class="form-control" rows="5" placeholder="Enter instructions for students..."><?= htmlspecialchars($t['instructions']) ?></textarea>
            </div>

            <hr>
            <div class="text-end">
                <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="fa-solid fa-save"></i> Save Test</button>
            </div>
        </form>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
