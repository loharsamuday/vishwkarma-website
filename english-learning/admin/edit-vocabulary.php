<?php
// admin/edit-vocabulary.php
session_start();
$page_title = 'Edit Vocabulary';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM vocabulary WHERE id = ?");
$stmt->execute([$id]);
$vocab = $stmt->fetch();

if (!$vocab) {
    header("Location: vocabulary.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $word = trim($_POST['word']);
    $story_id = !empty($_POST['story_id']) ? $_POST['story_id'] : null;
    $part_of_speech = trim($_POST['part_of_speech']);
    $hindi_meaning = trim($_POST['hindi_meaning']);
    $english_meaning = trim($_POST['english_meaning']);
    $synonym = trim($_POST['synonym']);
    $antonym = trim($_POST['antonym']);
    $example_sentence = trim($_POST['example_sentence']);
    
    if (empty($word)) {
        $error = "Word is required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE vocabulary SET 
                story_id = ?, word = ?, part_of_speech = ?, hindi_meaning = ?, 
                english_meaning = ?, synonym = ?, antonym = ?, example_sentence = ?
                WHERE id = ?
            ");
            $stmt->execute([$story_id, $word, $part_of_speech, $hindi_meaning, $english_meaning, $synonym, $antonym, $example_sentence, $id]);
            
            header("Location: vocabulary.php?msg=updated");
            exit();
        } catch(PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch stories
$stories = $pdo->query("SELECT id, title FROM stories ORDER BY title ASC")->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Vocabulary Word</h1>
    <a href="vocabulary.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= escape($error) ?></div>
<?php endif; ?>

<form method="POST" action="">
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="word" class="form-label fw-bold">Word *</label>
                            <input type="text" class="form-control" id="word" name="word" required value="<?= escape($vocab['word']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="part_of_speech" class="form-label">Part of Speech</label>
                            <input type="text" class="form-control" id="part_of_speech" name="part_of_speech" value="<?= escape($vocab['part_of_speech']) ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="hindi_meaning" class="form-label fw-bold text-success">Hindi Meaning</label>
                            <input type="text" class="form-control border-success" id="hindi_meaning" name="hindi_meaning" value="<?= escape($vocab['hindi_meaning']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="english_meaning" class="form-label">English Meaning</label>
                            <input type="text" class="form-control" id="english_meaning" name="english_meaning" value="<?= escape($vocab['english_meaning']) ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="synonym" class="form-label">Synonym</label>
                            <input type="text" class="form-control" id="synonym" name="synonym" value="<?= escape($vocab['synonym']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="antonym" class="form-label">Antonym</label>
                            <input type="text" class="form-control" id="antonym" name="antonym" value="<?= escape($vocab['antonym']) ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="example_sentence" class="form-label">Example Sentence</label>
                        <textarea class="form-control" id="example_sentence" name="example_sentence" rows="2"><?= escape($vocab['example_sentence']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Link to Story</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="story_id" class="form-label">Select Story (Optional)</label>
                        <select class="form-select" id="story_id" name="story_id">
                            <option value="">No Story</option>
                            <?php foreach ($stories as $story): ?>
                                <option value="<?= $story['id'] ?>" <?= $vocab['story_id'] == $story['id'] ? 'selected' : '' ?>><?= escape($story['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary bg-primary-custom">Update Word</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
