<?php
// admin/phrasal-verbs/add.php
session_start();
require_once '../../config/database.php';



$page_title = "Add Phrasal Verb";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$stmt = $pdo->query("SELECT id, name FROM exam_categories WHERE status='active' ORDER BY name ASC");
$categories = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phrasal_verb = trim($_POST['phrasal_verb']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['slug'])));
    $english_meaning = trim($_POST['english_meaning']);
    $hindi_meaning = trim($_POST['hindi_meaning']);
    $explanation = trim($_POST['explanation']);
    $example_sentence = trim($_POST['example_sentence']);
    $memory_trick = trim($_POST['memory_trick']);
    $synonyms = trim($_POST['synonyms']);
    $antonyms = trim($_POST['antonyms']);
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    $difficulty = $_POST['difficulty'];
    $exam_type = trim($_POST['exam_type']);
    $meta_title = trim($_POST['meta_title']);
    $meta_description = trim($_POST['meta_description']);
    $featured = isset($_POST['featured']) ? 1 : 0;
    $status = $_POST['status'];

    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $phrasal_verb)));
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO phrasal_verbs (phrasal_verb, slug, english_meaning, hindi_meaning, explanation, example_sentence, memory_trick, synonyms, antonyms, category_id, difficulty, exam_type, meta_title, meta_description, featured, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$phrasal_verb, $slug, $english_meaning, $hindi_meaning, $explanation, $example_sentence, $memory_trick, $synonyms, $antonyms, $category_id, $difficulty, $exam_type, $meta_title, $meta_description, $featured, $status]);
        
        $_SESSION['success_msg'] = "Phrasal verb added successfully!";
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Add Phrasal Verb</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="../import/index.php" class="btn btn-sm btn-outline-success">
                <i class="fas fa-file-csv"></i> Bulk Import CSV
            </a>
        </div>
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
                            <label for="phrasal_verb" class="form-label">Phrasal Verb *</label>
                            <input type="text" class="form-control" id="phrasal_verb" name="phrasal_verb" required>
                        </div>
                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug (URL)</label>
                            <input type="text" class="form-control" id="slug" name="slug">
                        </div>
                        <div class="mb-3">
                            <label for="hindi_meaning" class="form-label">Hindi Meaning *</label>
                            <input type="text" class="form-control" id="hindi_meaning" name="hindi_meaning" required>
                        </div>
                        <div class="mb-3">
                            <label for="english_meaning" class="form-label">English Meaning *</label>
                            <textarea class="form-control" id="english_meaning" name="english_meaning" rows="2" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="explanation" class="form-label">Explanation</label>
                            <textarea class="form-control" id="explanation" name="explanation" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="example_sentence" class="form-label">Example Sentence *</label>
                            <textarea class="form-control" id="example_sentence" name="example_sentence" rows="2" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="memory_trick" class="form-label">Memory Trick</label>
                            <textarea class="form-control" id="memory_trick" name="memory_trick" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="synonyms" class="form-label">Synonyms</label>
                                <input type="text" class="form-control" id="synonyms" name="synonyms">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="antonyms" class="form-label">Antonyms</label>
                                <input type="text" class="form-control" id="antonyms" name="antonyms">
                            </div>
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
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-select" id="category_id" name="category_id">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
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
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="featured" name="featured" value="1">
                            <label class="form-check-label" for="featured">Featured</label>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">SEO Settings</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="meta_title" class="form-label">Meta Title</label>
                            <input type="text" class="form-control" id="meta_title" name="meta_title">
                        </div>
                        <div class="mb-3">
                            <label for="meta_description" class="form-label">Meta Description</label>
                            <textarea class="form-control" id="meta_description" name="meta_description" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">Save Phrasal Verb</button>
                    <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</main>
<?php require_once '../includes/footer.php'; ?>

