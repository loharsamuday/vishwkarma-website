<?php
// admin/import/index.php
session_start();
require_once '../../config/database.php';



$page_title = "Bulk Import";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$success_count = 0;
$error_count = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $import_type = $_POST['import_type'];
    $file = $_FILES['csv_file']['tmp_name'];

    if (empty($file)) {
        $errors[] = "Please upload a CSV file.";
    } else {
        $handle = fopen($file, "r");
        if ($handle !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");
            
            $pdo->beginTransaction();
            try {
                if ($import_type == 'idioms') {
                    $stmt = $pdo->prepare("INSERT INTO idioms (idiom, slug, english_meaning, hindi_meaning, explanation, example_sentence, memory_trick, synonyms, antonyms, difficulty, exam_type, meta_title, meta_description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) >= 14) {
                            $slug = !empty($data[1]) ? $data[1] : strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data[0])));
                            try {
                                $stmt->execute([$data[0], $slug, $data[2], $data[3], $data[4], $data[5], $data[6], $data[7], $data[8], $data[9], $data[10], $data[11], $data[12], $data[13]]);
                                $success_count++;
                            } catch (PDOException $e) {
                                $error_count++;
                                $errors[] = "Row error ($data[0]): " . $e->getMessage();
                            }
                        }
                    }
                } elseif ($import_type == 'phrasal_verbs') {
                    $stmt = $pdo->prepare("INSERT INTO phrasal_verbs (phrasal_verb, slug, english_meaning, hindi_meaning, explanation, example_sentence, memory_trick, synonyms, antonyms, difficulty, exam_type, meta_title, meta_description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) >= 14) {
                            $slug = !empty($data[1]) ? $data[1] : strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data[0])));
                            try {
                                $stmt->execute([$data[0], $slug, $data[2], $data[3], $data[4], $data[5], $data[6], $data[7], $data[8], $data[9], $data[10], $data[11], $data[12], $data[13]]);
                                $success_count++;
                            } catch (PDOException $e) {
                                $error_count++;
                                $errors[] = "Row error ($data[0]): " . $e->getMessage();
                            }
                        }
                    }
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Transaction failed: " . $e->getMessage();
            }
            fclose($handle);
        }
    }
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Bulk Import CSV</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="download_sample.php" class="btn btn-sm btn-outline-info">
                <i class="fas fa-download"></i> Download Sample Format
            </a>
        </div>
    </div>

    <?php if ($success_count > 0): ?>
        <div class="alert alert-success">Successfully imported <?= $success_count ?> records.</div>
    <?php endif; ?>

    <?php if (count($errors) > 0): ?>
        <div class="alert alert-danger">
            Failed to import <?= $error_count ?> records.<br>
            <ul>
                <?php foreach(array_slice($errors, 0, 10) as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
                <?php if(count($errors) > 10) echo "<li>...and " . (count($errors) - 10) . " more errors.</li>"; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="import_type" class="form-label">Import Type</label>
                    <select class="form-select" id="import_type" name="import_type" required>
                        <option value="idioms">Idioms</option>
                        <option value="phrasal_verbs">Phrasal Verbs</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="csv_file" class="form-label">CSV File</label>
                    <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                </div>
                <button type="submit" class="btn btn-primary">Import Data</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">CSV Format Expected (Columns in Order)</div>
        <div class="card-body">
            <ol>
                <li>idiom / phrasal_verb</li>
                <li>slug (optional)</li>
                <li>english_meaning</li>
                <li>hindi_meaning</li>
                <li>explanation</li>
                <li>example_sentence</li>
                <li>memory_trick</li>
                <li>synonyms</li>
                <li>antonyms</li>
                <li>difficulty (Easy, Moderate, Hard, Very Important)</li>
                <li>exam_type</li>
                <li>meta_title</li>
                <li>meta_description</li>
                <li>status (Draft, Published)</li>
            </ol>
            <p class="text-muted">Note: category_id is not supported in simple CSV import currently. It defaults to NULL.</p>
        </div>
    </div>
</main>
<?php require_once '../includes/footer.php'; ?>

