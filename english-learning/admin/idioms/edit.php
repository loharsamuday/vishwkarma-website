<?php
// admin/idioms/edit.php
session_start();
require_once '../../config/database.php';



$page_title = "Edit Idiom";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM idioms WHERE id = ?");
$stmt->execute([$id]);
$idiom_data = $stmt->fetch();

if (!$idiom_data) {
    header("Location: index.php");
    exit;
}

$stmt_cat = $pdo->query("SELECT id, name FROM exam_categories WHERE status='active' ORDER BY name ASC");
$categories = $stmt_cat->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idiom = trim($_POST['idiom']);
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
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $idiom)));
    }

    try {
        $stmt = $pdo->prepare("UPDATE idioms SET idiom=?, slug=?, english_meaning=?, hindi_meaning=?, explanation=?, example_sentence=?, memory_trick=?, synonyms=?, antonyms=?, category_id=?, difficulty=?, exam_type=?, meta_title=?, meta_description=?, featured=?, status=? WHERE id=?");
        $stmt->execute([$idiom, $slug, $english_meaning, $hindi_meaning, $explanation, $example_sentence, $memory_trick, $synonyms, $antonyms, $category_id, $difficulty, $exam_type, $meta_title, $meta_description, $featured, $status, $id]);
        
        $_SESSION['success_msg'] = "Idiom updated successfully!";
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Edit Idiom</h1>
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
                            <label for="idiom" class="form-label">Idiom *</label>
                            <input type="text" class="form-control" id="idiom" name="idiom" value="<?= htmlspecialchars($idiom_data['idiom']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug (URL)</label>
                            <input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars($idiom_data['slug']) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="hindi_meaning" class="form-label">Hindi Meaning *</label>
                            <input type="text" class="form-control" id="hindi_meaning" name="hindi_meaning" value="<?= htmlspecialchars($idiom_data['hindi_meaning']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="english_meaning" class="form-label">English Meaning *</label>
                            <textarea class="form-control" id="english_meaning" name="english_meaning" rows="2" required><?= htmlspecialchars($idiom_data['english_meaning']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="explanation" class="form-label">Simple Explanation</label>
                            <textarea class="form-control" id="explanation" name="explanation" rows="2"><?= htmlspecialchars($idiom_data['explanation']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="example_sentence" class="form-label">Example Sentence *</label>
                            <textarea class="form-control" id="example_sentence" name="example_sentence" rows="2" required><?= htmlspecialchars($idiom_data['example_sentence']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="memory_trick" class="form-label">Memory Trick</label>
                            <textarea class="form-control" id="memory_trick" name="memory_trick" rows="2"><?= htmlspecialchars($idiom_data['memory_trick']) ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="synonyms" class="form-label">Synonyms</label>
                                <input type="text" class="form-control" id="synonyms" name="synonyms" value="<?= htmlspecialchars($idiom_data['synonyms']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="antonyms" class="form-label">Antonyms</label>
                                <input type="text" class="form-control" id="antonyms" name="antonyms" value="<?= htmlspecialchars($idiom_data['antonyms']) ?>">
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
                                <option value="Published" <?= $idiom_data['status'] == 'Published' ? 'selected' : '' ?>>Published</option>
                                <option value="Draft" <?= $idiom_data['status'] == 'Draft' ? 'selected' : '' ?>>Draft</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-select" id="category_id" name="category_id">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $idiom_data['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="difficulty" class="form-label">Difficulty</label>
                            <select class="form-select" id="difficulty" name="difficulty">
                                <option value="Easy" <?= $idiom_data['difficulty'] == 'Easy' ? 'selected' : '' ?>>Easy</option>
                                <option value="Moderate" <?= $idiom_data['difficulty'] == 'Moderate' ? 'selected' : '' ?>>Moderate</option>
                                <option value="Hard" <?= $idiom_data['difficulty'] == 'Hard' ? 'selected' : '' ?>>Hard</option>
                                <option value="Very Important" <?= $idiom_data['difficulty'] == 'Very Important' ? 'selected' : '' ?>>Very Important</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="exam_type" class="form-label">Exam Type</label>
                            <input type="text" class="form-control" id="exam_type" name="exam_type" value="<?= htmlspecialchars($idiom_data['exam_type']) ?>">
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="featured" name="featured" value="1" <?= $idiom_data['featured'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="featured">Featured Idiom</label>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">SEO Settings</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="meta_title" class="form-label">Meta Title</label>
                            <input type="text" class="form-control" id="meta_title" name="meta_title" value="<?= htmlspecialchars($idiom_data['meta_title']) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="meta_description" class="form-label">Meta Description</label>
                            <textarea class="form-control" id="meta_description" name="meta_description" rows="2"><?= htmlspecialchars($idiom_data['meta_description']) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">Update Idiom</button>
                    <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</main>
<?php require_once '../includes/footer.php'; ?>

