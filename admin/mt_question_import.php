<?php
$page_title = "Bulk Import Questions";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Download Sample CSV
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sample_questions.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Subject ID', 'Topic ID (Optional)', 'Question Type', 'Difficulty', 'Marks', 'Negative Marks', 'Language', 'Question Text', 'Option A', 'Option B', 'Option C', 'Option D', 'Option E', 'Correct Option', 'Explanation', 'Short Trick']);
    fputcsv($output, ['1', '', 'single_mcq', 'Moderate', '1.00', '0.25', 'English', 'What is the capital of India?', 'Mumbai', 'New Delhi', 'Kolkata', 'Chennai', '', 'B', 'New Delhi is the capital of India.', '']);
    fclose($output);
    exit;
}

$errors = [];
$success_count = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] == UPLOAD_ERR_OK) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) == 'csv') {
            if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
                $header = fgetcsv($handle, 1000, ","); // Skip header
                $row_num = 2; // Assuming row 1 is header
                
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("INSERT INTO mt_questions (subject_id, topic_id, question_type, difficulty_level, marks, negative_marks, language, question_text, option_a, option_b, option_c, option_d, option_e, correct_option, explanation, short_trick, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                    
                    while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                        // Basic validation
                        if (count($data) < 14) {
                            $errors[] = "Row $row_num: Missing required columns.";
                            $row_num++;
                            continue;
                        }
                        
                        $subject_id = (int)$data[0];
                        if ($subject_id <= 0) {
                            $errors[] = "Row $row_num: Invalid Subject ID.";
                            $row_num++;
                            continue;
                        }
                        
                        $topic_id = !empty($data[1]) ? (int)$data[1] : null;
                        $q_type = !empty($data[2]) ? $data[2] : 'single_mcq';
                        $diff = !empty($data[3]) ? $data[3] : 'Moderate';
                        $marks = !empty($data[4]) ? (float)$data[4] : 1.00;
                        $neg_marks = isset($data[5]) && $data[5] !== '' ? (float)$data[5] : 0.25;
                        $lang = !empty($data[6]) ? $data[6] : 'English';
                        
                        $q_text = trim($data[7]);
                        if (empty($q_text)) {
                            $errors[] = "Row $row_num: Question text is empty.";
                            $row_num++;
                            continue;
                        }
                        
                        $opt_a = trim($data[8]);
                        $opt_b = trim($data[9]);
                        $opt_c = trim($data[10] ?? '');
                        $opt_d = trim($data[11] ?? '');
                        $opt_e = trim($data[12] ?? '');
                        $correct = strtoupper(trim($data[13] ?? 'A'));
                        $expl = trim($data[14] ?? '');
                        $trick = trim($data[15] ?? '');
                        
                        $stmt->execute([$subject_id, $topic_id, $q_type, $diff, $marks, $neg_marks, $lang, $q_text, $opt_a, $opt_b, $opt_c, $opt_d, $opt_e, $correct, $expl, $trick]);
                        $success_count++;
                        $row_num++;
                    }
                    
                    if (count($errors) > 0) {
                        // Rollback if there are any errors to ensure clean data
                        $pdo->rollBack();
                        $success_count = 0;
                    } else {
                        $pdo->commit();
                        setFlashMessage('success', "$success_count questions imported successfully.");
                        header("Location: mt_questions.php");
                        exit;
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = "Database error: " . $e->getMessage();
                }
                fclose($handle);
            } else {
                $errors[] = "Failed to read the CSV file.";
            }
        } else {
            $errors[] = "Invalid file format. Please upload a CSV file.";
        }
    } else {
        $errors[] = "File upload error code: " . $file['error'];
    }
}
?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <h3 class="mb-0 text-dark"><i class="fa-solid fa-file-excel text-success me-2"></i> Bulk Import Questions</h3>
        <a href="mt_questions.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Bank</a>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm p-4">
                <?php if (count($errors) > 0): ?>
                    <div class="alert alert-danger">
                        <strong><i class="fa-solid fa-triangle-exclamation"></i> Import Failed. Fix the errors below and try again:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach($errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Upload CSV File *</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        <small class="text-muted mt-2 d-block">Make sure your CSV matches the template structure exactly. First row is ignored (Header).</small>
                    </div>
                    
                    <button type="submit" class="btn btn-success fw-bold px-4"><i class="fa-solid fa-upload"></i> Upload & Process</button>
                </form>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 bg-light">
                <h5 class="fw-bold"><i class="fa-solid fa-circle-info text-primary"></i> Instructions</h5>
                <p class="text-muted small mb-3">To import questions in bulk, you must use the standard CSV format. Click below to download a sample template.</p>
                <a href="?download_sample=1" class="btn btn-outline-dark mb-4"><i class="fa-solid fa-download"></i> Download Sample CSV</a>
                
                <h6 class="fw-bold">Important Rules:</h6>
                <ul class="small text-muted ps-3 mb-0">
                    <li><strong>Subject ID</strong> is required. Get it from the Subjects page.</li>
                    <li><strong>Topic ID</strong> is optional. Leave blank if not applicable.</li>
                    <li><strong>Question Type</strong> must be one of: <code>single_mcq</code>, <code>multi_mcq</code>, <code>true_false</code>.</li>
                    <li><strong>Difficulty</strong>: <code>Easy</code>, <code>Moderate</code>, or <code>Difficult</code>.</li>
                    <li>For multiple correct options, separate with commas (e.g., <code>A,B</code>).</li>
                    <li>The system will reject the entire file if any row has an error to prevent partial messy imports.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
