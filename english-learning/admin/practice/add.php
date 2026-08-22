<?php
// admin/practice/add.php
session_start();
require_once '../../config/database.php';

$page_title = "Add Practice Question";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $content_type = $_POST['content_type'];
    $content_id = !empty($_POST['content_id']) ? $_POST['content_id'] : null;
    $question = trim($_POST['question']);
    $option_a = trim($_POST['option_a']);
    $option_b = trim($_POST['option_b']);
    $option_c = trim($_POST['option_c']);
    $option_d = trim($_POST['option_d']);
    $correct_answer = $_POST['correct_answer'];
    $explanation = trim($_POST['explanation']);
    $hindi_explanation = trim($_POST['hindi_explanation']);
    $difficulty = $_POST['difficulty'];
    $exam_type = trim($_POST['exam_type']);
    $status = $_POST['status'];

    try {
        $stmt = $pdo->prepare("INSERT INTO practice_questions (content_type, content_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, hindi_explanation, difficulty, exam_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$content_type, $content_id, $question, $option_a, $option_b, $option_c, $option_d, $correct_answer, $explanation, $hindi_explanation, $difficulty, $exam_type, $status]);
        
        $_SESSION['success_msg'] = "Question added successfully!";
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Add Practice Question</h1>
    </div>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <form action="" method="POST" class="needs-validation mb-5" novalidate>
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="question" class="form-label">Question *</label>
                            <textarea class="form-control" id="question" name="question" rows="3" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="option_a" class="form-label">Option A *</label>
                                <input type="text" class="form-control" id="option_a" name="option_a" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="option_b" class="form-label">Option B *</label>
                                <input type="text" class="form-control" id="option_b" name="option_b" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="option_c" class="form-label">Option C *</label>
                                <input type="text" class="form-control" id="option_c" name="option_c" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="option_d" class="form-label">Option D *</label>
                                <input type="text" class="form-control" id="option_d" name="option_d" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="correct_answer" class="form-label">Correct Answer *</label>
                            <select class="form-select" id="correct_answer" name="correct_answer" required>
                                <option value="">Select Correct Option</option>
                                <option value="A">Option A</option>
                                <option value="B">Option B</option>
                                <option value="C">Option C</option>
                                <option value="D">Option D</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="explanation" class="form-label">English Explanation</label>
                            <textarea class="form-control" id="explanation" name="explanation" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="hindi_explanation" class="form-label">Hindi Explanation</label>
                            <textarea class="form-control" id="hindi_explanation" name="hindi_explanation" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="Published">Published</option>
                                <option value="Draft">Draft</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="content_type" class="form-label">Related To</label>
                            <select class="form-select" id="content_type" name="content_type">
                                <option value="general">General / Mix</option>
                                <option value="idiom">Idiom</option>
                                <option value="phrasal_verb">Phrasal Verb</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="content_id" class="form-label">Content ID (Optional)</label>
                            <input type="number" class="form-control" id="content_id" name="content_id" placeholder="ID of related Idiom/Phrasal Verb">
                        </div>
                        <div class="mb-3">
                            <label for="difficulty" class="form-label">Difficulty</label>
                            <select class="form-select" id="difficulty" name="difficulty">
                                <option value="Easy">Easy</option>
                                <option value="Moderate" selected>Moderate</option>
                                <option value="Hard">Hard</option>
                                <option value="Very Important">Very Important</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="exam_type" class="form-label">Exam Type</label>
                            <input type="text" class="form-control" id="exam_type" name="exam_type">
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">Save Question</button>
                    <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</main>
<?php require_once '../includes/footer.php'; ?>
