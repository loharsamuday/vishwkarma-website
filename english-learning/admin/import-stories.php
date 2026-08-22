<?php
// admin/import-stories.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$page_title = "Bulk Import Dashboard";
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$result = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $import_type = $_POST['import_type']; // 'story' or 'vocabulary'
    $file = $_FILES['csv_file']['tmp_name'];

    $result = [
        'type' => $import_type,
        'success' => 0,
        'failed' => 0,
        'duplicate' => 0,
        'errors' => []
    ];

    if (empty($file)) {
        $result['errors'][] = "Please upload a CSV file.";
    } else {
        $handle = fopen($file, "r");
        if ($handle !== FALSE) {
            // Read header row
            $header = fgetcsv($handle, 10000, ",");
            
            // Clean BOM from first header column if present
            if (!empty($header)) {
                $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);
                $header = array_map('trim', $header);
            }

            $pdo->beginTransaction();
            try {
                if ($import_type == 'story') {
                    // Expected Headers: story_title, story_level, story_category, story_content, hindi_meaning, moral
                    $expected_headers = ['story_title', 'story_level', 'story_category', 'story_content', 'hindi_meaning', 'moral'];
                    
                    // Validate headers
                    $missing = array_diff($expected_headers, $header);
                    if (!empty($missing)) {
                        throw new Exception("Missing required columns: " . implode(", ", $missing));
                    }
                    
                    $header_flip = array_flip($header);
                    
                    // Prep statements
                    $stmt_check = $pdo->prepare("SELECT id FROM stories WHERE title = ?");
                    $stmt_cat = $pdo->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
                    $stmt_insert_cat = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
                    $stmt_insert_story = $pdo->prepare("INSERT INTO stories (title, slug, difficulty, category_id, content, hindi_meaning, moral, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Published')");

                    $row_num = 1;
                    while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                        $row_num++;
                        
                        $title = trim($data[$header_flip['story_title']] ?? '');
                        $level = trim($data[$header_flip['story_level']] ?? 'Beginner');
                        $category_name = trim($data[$header_flip['story_category']] ?? '');
                        $content = trim($data[$header_flip['story_content']] ?? '');
                        $hindi_meaning = trim($data[$header_flip['hindi_meaning']] ?? '');
                        $moral = trim($data[$header_flip['moral']] ?? '');
                        
                        if (empty($title) || empty($content)) {
                            $result['failed']++;
                            $result['errors'][] = "<span class='text-danger'>Row $row_num - Missing title or content.</span>";
                            continue;
                        }

                        // Check Duplicate
                        $stmt_check->execute([$title]);
                        if ($stmt_check->fetch()) {
                            $result['duplicate']++;
                            $result['errors'][] = "<span class='text-warning'>Row $row_num - Duplicate story skipped: '" . htmlspecialchars($title) . "'</span>";
                            continue;
                        }

                        // Handle Category
                        $category_id = null;
                        if (!empty($category_name)) {
                            $stmt_cat->execute([$category_name]);
                            $cat_row = $stmt_cat->fetch();
                            if ($cat_row) {
                                $category_id = $cat_row['id'];
                            } else {
                                $cat_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $category_name)));
                                $stmt_insert_cat->execute([$category_name, $cat_slug]);
                                $category_id = $pdo->lastInsertId();
                            }
                        }

                        // Handle Slug
                        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));

                        try {
                            $stmt_insert_story->execute([$title, $slug, $level, $category_id, $content, $hindi_meaning, $moral]);
                            $new_story_id = $pdo->lastInsertId();
                            $result['success']++;
                            $result['errors'][] = "<span class='text-success'>Row $row_num - Success! Story ID: <strong>$new_story_id</strong> ('" . htmlspecialchars($title) . "')</span>";
                        } catch (PDOException $e) {
                            $result['failed']++;
                            $result['errors'][] = "<span class='text-danger'>Row $row_num - Error: " . htmlspecialchars($e->getMessage()) . "</span>";
                        }
                    }

                } elseif ($import_type == 'vocabulary') {
                    // Expected Headers: story_id, word, meaning_hindi, meaning_english, part_of_speech, example_sentence, synonym, antonym
                    $expected_headers = ['story_id', 'word', 'meaning_hindi', 'meaning_english', 'part_of_speech', 'example_sentence', 'synonym', 'antonym'];
                    
                    // Validate headers
                    $missing = array_diff($expected_headers, $header);
                    if (!empty($missing)) {
                        throw new Exception("Missing required columns: " . implode(", ", $missing));
                    }
                    
                    $header_flip = array_flip($header);
                    
                    // Prep statements
                    $stmt_check_story = $pdo->prepare("SELECT id FROM stories WHERE id = ?");
                    $stmt_check_vocab = $pdo->prepare("SELECT id FROM vocabulary WHERE story_id = ? AND word = ?");
                    $stmt_insert_vocab = $pdo->prepare("INSERT INTO vocabulary (story_id, word, hindi_meaning, english_meaning, part_of_speech, example_sentence, synonym, antonym) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                    $row_num = 1;
                    while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                        $row_num++;
                        
                        $story_id = trim($data[$header_flip['story_id']] ?? '');
                        $word = trim($data[$header_flip['word']] ?? '');
                        $hindi = trim($data[$header_flip['meaning_hindi']] ?? '');
                        $english = trim($data[$header_flip['meaning_english']] ?? '');
                        $pos = trim($data[$header_flip['part_of_speech']] ?? '');
                        $example = trim($data[$header_flip['example_sentence']] ?? '');
                        $synonym = trim($data[$header_flip['synonym']] ?? '');
                        $antonym = trim($data[$header_flip['antonym']] ?? '');
                        
                        if (empty($story_id) || empty($word)) {
                            $result['failed']++;
                            $result['errors'][] = "<span class='text-danger'>Row $row_num - Missing story_id or word.</span>";
                            continue;
                        }

                        // Check if story exists
                        $stmt_check_story->execute([$story_id]);
                        if (!$stmt_check_story->fetch()) {
                            $result['failed']++;
                            $result['errors'][] = "<span class='text-danger'>Row $row_num - Invalid story_id: $story_id. Story does not exist.</span>";
                            continue;
                        }

                        // Check Duplicate
                        $stmt_check_vocab->execute([$story_id, $word]);
                        if ($stmt_check_vocab->fetch()) {
                            $result['duplicate']++;
                            $result['errors'][] = "<span class='text-warning'>Row $row_num - Duplicate vocabulary skipped: '" . htmlspecialchars($word) . "' for story $story_id</span>";
                            continue;
                        }

                        try {
                            $stmt_insert_vocab->execute([$story_id, $word, $hindi, $english, $pos, $example, $synonym, $antonym]);
                            $result['success']++;
                        } catch (PDOException $e) {
                            $result['failed']++;
                            $result['errors'][] = "<span class='text-danger'>Row $row_num - Error: " . htmlspecialchars($e->getMessage()) . "</span>";
                        }
                    }
                }
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $result['errors'][] = "Import Aborted: " . $e->getMessage();
            }
            fclose($handle);
        } else {
            $result['errors'][] = "Could not read the uploaded file.";
        }
    }
}

// Fetch Stats for display
$total_stories = $pdo->query("SELECT COUNT(*) FROM stories")->fetchColumn();
$total_vocab = $pdo->query("SELECT COUNT(*) FROM vocabulary")->fetchColumn();
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Professional Import Dashboard</h1>
        <div class="btn-toolbar mb-2 mb-md-0 gap-2">
            <a href="stories.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Stories
            </a>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card bg-primary text-white shadow-sm">
                <div class="card-body text-center">
                    <h3 class="fw-bold mb-0"><?= number_format($total_stories) ?></h3>
                    <p class="mb-0">Total Stories</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-success text-white shadow-sm">
                <div class="card-body text-center">
                    <h3 class="fw-bold mb-0"><?= number_format($total_vocab) ?></h3>
                    <p class="mb-0">Total Vocabulary</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Results -->
    <?php if ($result): ?>
        <div class="card mb-4 border-info">
            <div class="card-header bg-info text-dark fw-bold">
                <i class="fas fa-info-circle me-1"></i> Import Result (<?= ucfirst($result['type']) ?>)
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-4">
                        <div class="p-3 border rounded bg-success bg-opacity-10 text-success">
                            <h4 class="fw-bold mb-0"><?= $result['success'] ?></h4>
                            <small>Successfully Imported</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 border rounded bg-danger bg-opacity-10 text-danger">
                            <h4 class="fw-bold mb-0"><?= $result['failed'] ?></h4>
                            <small>Failed Rows</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 border rounded bg-warning bg-opacity-10 text-warning">
                            <h4 class="fw-bold mb-0"><?= $result['duplicate'] ?></h4>
                            <small>Duplicate Rows</small>
                        </div>
                    </div>
                </div>

                <?php if (!empty($result['errors'])): ?>
                    <h6 class="fw-bold text-dark mt-3">Import Log:</h6>
                    <div class="bg-light p-3 border rounded" style="max-height: 250px; overflow-y: auto;">
                        <ul class="mb-0 small" style="list-style-type: none; padding-left: 0;">
                            <?php foreach ($result['errors'] as $err): ?>
                                <li class="mb-1 border-bottom pb-1"><?= $err ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Import Sections -->
    <div class="row">
        <!-- Section 1: Story -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100 border-primary">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="fas fa-book me-1"></i> IMPORT STORY
                </div>
                <div class="card-body">
                    <p class="text-muted small">Upload a CSV file containing story data. The system will automatically generate a unique <strong>story_id</strong> for each successful import.</p>
                    <form action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="import_type" value="story">
                        <div class="mb-3">
                            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i> Upload Story</button>
                            <a href="download_story_csv.php?type=story" class="btn btn-sm btn-outline-primary"><i class="fas fa-download me-1"></i> Download Sample</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Section 2: Vocabulary -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100 border-success">
                <div class="card-header bg-success text-white fw-bold">
                    <i class="fas fa-language me-1"></i> IMPORT VOCABULARY
                </div>
                <div class="card-body">
                    <p class="text-muted small">Upload a CSV file containing vocabulary data. You must provide the valid <strong>story_id</strong> for each word to link it correctly.</p>
                    <form action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="import_type" value="vocabulary">
                        <div class="mb-3">
                            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <button type="submit" class="btn btn-success"><i class="fas fa-upload me-1"></i> Upload Vocabulary</button>
                            <a href="download_story_csv.php?type=vocab" class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i> Download Sample</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
