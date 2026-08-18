<?php
// admin/add-vocabulary.php
session_start();
$page_title = 'Add Vocabulary';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

$pre_story_id = isset($_GET['story_id']) ? (int)$_GET['story_id'] : '';

// --- SINGLE UPLOAD LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_single'])) {
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
                INSERT INTO vocabulary (story_id, word, part_of_speech, hindi_meaning, english_meaning, synonym, antonym, example_sentence) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$story_id, $word, $part_of_speech, $hindi_meaning, $english_meaning, $synonym, $antonym, $example_sentence]);
            
            if(isset($_POST['save_and_add_another'])) {
                header("Location: add-vocabulary.php?story_id=" . $story_id . "&msg=added");
            } else {
                header("Location: vocabulary.php?msg=added");
            }
            exit();
        } catch(PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// --- BULK UPLOAD LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv'])) {
    $story_id_bulk = !empty($_POST['story_id_bulk']) ? $_POST['story_id_bulk'] : null;
    
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file_tmp = $_FILES['csv_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        
        if ($file_ext === 'csv') {
            $handle = fopen($file_tmp, "r");
            if ($handle !== FALSE) {
                // Skip the first row (headers)
                fgetcsv($handle, 1000, ",");
                
                $imported = 0;
                $stmt = $pdo->prepare("
                    INSERT INTO vocabulary (story_id, word, part_of_speech, hindi_meaning, english_meaning, synonym, antonym, example_sentence) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    // Ensure we have data
                    $word = isset($data[0]) ? trim($data[0]) : '';
                    if (empty($word)) continue;
                    
                    $part_of_speech = isset($data[1]) ? trim($data[1]) : '';
                    $hindi_meaning = isset($data[2]) ? trim($data[2]) : '';
                    $english_meaning = isset($data[3]) ? trim($data[3]) : '';
                    $synonym = isset($data[4]) ? trim($data[4]) : '';
                    $antonym = isset($data[5]) ? trim($data[5]) : '';
                    $example_sentence = isset($data[6]) ? trim($data[6]) : '';
                    
                    try {
                        $stmt->execute([$story_id_bulk, $word, $part_of_speech, $hindi_meaning, $english_meaning, $synonym, $antonym, $example_sentence]);
                        $imported++;
                    } catch(PDOException $e) {
                        // Skip failed rows (e.g. constraints)
                    }
                }
                fclose($handle);
                $_SESSION['success_msg'] = "Successfully imported $imported vocabulary words!";
                header("Location: add-vocabulary.php");
                exit();
            } else {
                $error = "Failed to open the uploaded file.";
            }
        } else {
            $error = "Please upload a valid CSV file.";
        }
    } else {
        $error = "Please select a file to upload.";
    }
}

// Fetch stories
$stories = $pdo->query("SELECT id, title FROM stories ORDER BY title ASC")->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Add Vocabulary Word</h1>
    <a href="vocabulary.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= escape($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= escape($success) ?></div>
<?php endif; ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'added'): ?>
    <div class="alert alert-success">Previous word added successfully. You can add another one.</div>
<?php endif; ?>

<!-- Nav tabs -->
<ul class="nav nav-tabs mb-4" id="vocabTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab" aria-controls="single" aria-selected="true"><i class="fas fa-plus me-1"></i> Add Single Word</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulk" type="button" role="tab" aria-controls="bulk" aria-selected="false"><i class="fas fa-file-csv me-1"></i> Bulk Upload (CSV)</button>
    </li>
</ul>

<!-- Tab panes -->
<div class="tab-content" id="vocabTabContent">
    
    <!-- SINGLE UPLOAD TAB -->
    <div class="tab-pane fade show active" id="single" role="tabpanel" aria-labelledby="single-tab">
        <form method="POST" action="">
            <input type="hidden" name="save_single" value="1">
            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="word" class="form-label fw-bold">Word *</label>
                                    <input type="text" class="form-control" id="word" name="word" required autofocus>
                                </div>
                                <div class="col-md-6">
                                    <label for="part_of_speech" class="form-label">Part of Speech</label>
                                    <input type="text" class="form-control" id="part_of_speech" name="part_of_speech" placeholder="e.g. Noun, Verb, Adjective">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="hindi_meaning" class="form-label fw-bold text-success">Hindi Meaning</label>
                                    <input type="text" class="form-control border-success" id="hindi_meaning" name="hindi_meaning">
                                </div>
                                <div class="col-md-6">
                                    <label for="english_meaning" class="form-label">English Meaning</label>
                                    <input type="text" class="form-control" id="english_meaning" name="english_meaning">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="synonym" class="form-label">Synonym</label>
                                    <input type="text" class="form-control" id="synonym" name="synonym">
                                </div>
                                <div class="col-md-6">
                                    <label for="antonym" class="form-label">Antonym</label>
                                    <input type="text" class="form-control" id="antonym" name="antonym">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="example_sentence" class="form-label">Example Sentence</label>
                                <textarea class="form-control" id="example_sentence" name="example_sentence" rows="2"></textarea>
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
                                        <option value="<?= $story['id'] ?>" <?= $pre_story_id == $story['id'] ? 'selected' : '' ?>><?= escape($story['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Linking a word to a story will show it on that story's page.</small>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="save" class="btn btn-primary bg-primary-custom">Save Word</button>
                                <button type="submit" name="save_and_add_another" class="btn btn-outline-primary" value="1">Save & Add Another</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- BULK UPLOAD TAB -->
    <div class="tab-pane fade" id="bulk" role="tabpanel" aria-labelledby="bulk-tab">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary-custom text-white fw-bold py-3">
                        <i class="fas fa-file-upload me-2"></i>Bulk Upload Vocabulary via CSV
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-info border-0 shadow-sm mb-4">
                            <h5 class="alert-heading"><i class="fas fa-info-circle me-2"></i>How to upload:</h5>
                            <ol class="mb-0">
                                <li>Download the sample CSV file.</li>
                                <li>Open it in Excel, Google Sheets, or any spreadsheet software.</li>
                                <li>Add your words row by row (Do not change the header row).</li>
                                <li>Save the file as CSV (Comma delimited) (*.csv).</li>
                                <li>Upload the file below.</li>
                            </ol>
                            <div class="mt-3">
                                <a href="download_sample_csv.php" class="btn btn-info bg-white text-info border-info fw-bold"><i class="fas fa-download me-1"></i> Download Sample CSV</a>
                            </div>
                        </div>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="upload_csv" value="1">
                            
                            <div class="mb-4">
                                <label for="story_id_bulk" class="form-label fw-bold">Assign to Story (Optional)</label>
                                <select class="form-select form-select-lg" id="story_id_bulk" name="story_id_bulk">
                                    <option value="">Do not assign to any story</option>
                                    <?php foreach ($stories as $story): ?>
                                        <option value="<?= $story['id'] ?>" <?= $pre_story_id == $story['id'] ? 'selected' : '' ?>><?= escape($story['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">All words in the CSV file will be linked to this story.</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="csv_file" class="form-label fw-bold">Select CSV File</label>
                                <input class="form-control form-control-lg" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-cloud-upload-alt me-2"></i>Upload and Process File</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<?php include 'includes/footer.php'; ?>
