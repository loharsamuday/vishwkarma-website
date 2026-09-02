<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$page_title = 'Bulk Upload Vocabulary Questions';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';
$skipped = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Your session has expired. Please try again.';
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a valid CSV file.';
    } elseif (strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $error = 'Only CSV files are accepted.';
    } else {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if ($handle === false) {
            $error = 'The uploaded CSV could not be opened.';
        } else {
            // Required column order: question, option_a, option_b, option_c, option_d, correct_answer.
            fgetcsv($handle);
            $insert = $pdo->prepare('INSERT INTO practice_questions
                (content_type, content_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, hindi_explanation, difficulty, exam_type, status)
                VALUES (\'vocabulary\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $imported = 0;
            $row = 1;

            try {
                $pdo->beginTransaction();
                while (($data = fgetcsv($handle)) !== false) {
                    $row++;
                    $data = array_pad($data, 12, '');
                    $question = trim($data[0]);
                    $options = array_map('trim', array_slice($data, 1, 4));
                    
                    // Extract first character of answer in case user wrote "A." or " A "
                    $answerInput = strtoupper(trim($data[5]));
                    $answer = in_array($answerInput, ['A', 'B', 'C', 'D']) ? $answerInput : (isset($answerInput[0]) && in_array($answerInput[0], ['A', 'B', 'C', 'D']) ? $answerInput[0] : $answerInput);
                    
                    $explanation = trim($data[6]);
                    $hindiExplanation = trim($data[7]);
                    
                    $difficultyInput = strtolower(trim($data[8]));
                    if ($difficultyInput === 'easy') $difficulty = 'Easy';
                    elseif ($difficultyInput === 'hard') $difficulty = 'Hard';
                    elseif (strpos($difficultyInput, 'very') !== false) $difficulty = 'Very Important';
                    else $difficulty = 'Moderate';
                    
                    $examType = trim($data[9]);
                    
                    $statusInput = strtolower(trim($data[10]));
                    $status = ($statusInput === 'draft') ? 'Draft' : 'Published';
                    
                    $contentId = trim($data[11]) !== '' ? (int) $data[11] : null;

                    if ($question === '' || in_array('', $options, true) || !in_array($answer, ['A', 'B', 'C', 'D'], true)) {
                        $skipped++;
                        continue;
                    }

                    $insert->execute([$contentId, $question, $options[0], $options[1], $options[2], $options[3], $answer, $explanation, $hindiExplanation, $difficulty, $examType, $status]);
                    $imported++;
                }
                $pdo->commit();
                $success = "$imported vocabulary question(s) uploaded successfully." . ($skipped ? " $skipped invalid row(s) were skipped." : '');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Could not import the CSV. Please verify the file format and try again.';
            }
            fclose($handle);
        }
    }
}

$csrfToken = generateCsrfToken();
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2 mb-1">Bulk Upload Vocabulary Questions</h1>
        <p class="text-muted mb-0">Import English vocabulary MCQs in one CSV file.</p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Questions</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= escape($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= escape($success) ?></div><?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                    <label for="csv_file" class="form-label fw-bold">Vocabulary Questions CSV</label>
                    <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
                    <div class="form-text">The first row must be the heading row. Maximum server upload size applies.</div>
                    <div class="mt-3">
                        <a href="sample-vocabulary-questions.csv" class="btn btn-outline-primary btn-sm" download>
                            <i class="fas fa-download me-1"></i> Download Sample CSV
                        </a>
                    </div>
                    <button class="btn btn-success mt-4" type="submit"><i class="fas fa-upload me-2"></i>Upload Questions</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-primary shadow-sm">
            <div class="card-header bg-primary text-white fw-bold"><i class="fas fa-table me-2"></i>CSV Format</div>
            <div class="card-body small">
                <p>Use these columns in exactly this order:</p>
                <code class="d-block text-wrap">question,option_a,option_b,option_c,option_d,correct_answer,explanation,hindi_explanation,difficulty,exam_type,status,content_id</code>
                <hr>
                <p class="mb-1"><strong>Required:</strong> first 6 columns</p>
                <p class="mb-1"><strong>correct_answer:</strong> A, B, C or D</p>
                <p class="mb-1"><strong>difficulty:</strong> Easy, Moderate, Hard, Very Important</p>
                <p class="mb-0"><strong>status:</strong> Published or Draft</p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
