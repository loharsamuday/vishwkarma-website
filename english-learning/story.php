<?php
// story.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch story
$stmt = $pdo->prepare("
    SELECT s.*, c.name as category_name 
    FROM stories s 
    LEFT JOIN categories c ON s.category_id = c.id 
    WHERE s.id = ? AND s.status = 'Published'
");
$stmt->execute([$id]);
$story = $stmt->fetch();

if (!$story) {
    header("Location: stories.php");
    exit();
}

// Fetch vocabulary for this story
$stmt = $pdo->prepare("SELECT * FROM vocabulary WHERE story_id = ? ORDER BY word ASC");
$stmt->execute([$id]);
$vocabularies = $stmt->fetchAll();

// Process story content to highlight vocabulary words
$content = $story['content'];

// We need to replace words in content with highlighted spans.
// We should do this carefully to avoid replacing HTML tags.
// A robust way in PHP is using DOMDocument or regex with negative lookahead for HTML tags.
// For simplicity and speed in this context, we'll use regex.

foreach ($vocabularies as $vocab) {
    $word = preg_quote($vocab['word'], '/');
    // \b is word boundary. (?![^<]*>) ensures we don't replace inside HTML tags like <a href="...">
    // i flag for case-insensitive
    $pattern = "/\b(" . $word . ")\b(?![^<]*>)/i";
    
    // Replacement: Highlighted span with data attribute pointing to vocab ID
    $replacement = "<span class='vocab-word-highlight' data-vocab-id='{$vocab['id']}' data-bs-toggle='tooltip' title='Click to see meaning'>$1</span>";
    
    $content = preg_replace($pattern, $replacement, $content);
}

$page_title = escape($story['seo_title'] ?: $story['title']);
$seo_desc = escape($story['seo_description'] ?: $story['short_description']);
include 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="stories.php">Stories</a></li>
                    <li class="breadcrumb-item"><a href="stories.php?category=<?= $story['category_id'] ?>"><?= escape($story['category_name']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= escape($story['title']) ?></li>
                </ol>
            </nav>

            <!-- Story Header -->
            <div class="text-center mb-5">
                <div class="mb-3">
                    <?php if($story['difficulty'] == 'Beginner'): ?>
                        <span class="badge badge-beginner fs-6 px-3 py-2">Difficulty: Beginner</span>
                    <?php elseif($story['difficulty'] == 'Intermediate'): ?>
                        <span class="badge badge-intermediate fs-6 px-3 py-2">Difficulty: Intermediate</span>
                    <?php else: ?>
                        <span class="badge badge-advanced fs-6 px-3 py-2">Difficulty: Advanced</span>
                    <?php endif; ?>
                    <span class="badge bg-light text-dark border fs-6 px-3 py-2 ms-2"><i class="far fa-clock me-1"></i> Reading Time: <?= $story['reading_time'] ?> min</span>
                </div>
                
                <h1 class="fw-bold display-5 text-primary-custom mb-3"><?= escape($story['title']) ?></h1>
                <?php if($story['short_description']): ?>
                    <p class="lead text-muted"><?= escape($story['short_description']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Story Content -->
            <div class="story-content mb-5">
                <?= $content ?>
            </div>

            <!-- Vocabulary Section -->
            <?php if (count($vocabularies) > 0): ?>
            <div class="mt-5 pt-4 border-top">
                <h3 class="fw-bold mb-4 text-primary-custom"><i class="fas fa-language me-2"></i>Vocabulary from this story</h3>
                
                <div class="row">
                    <?php foreach ($vocabularies as $vocab): ?>
                    <div class="col-12 mb-3">
                        <div class="card vocab-card border-0 p-3" id="vocab-<?= $vocab['id'] ?>">
                            <div class="row align-items-center">
                                <div class="col-md-3 border-end-md">
                                    <h5 class="fw-bold text-dark mb-1"><?= escape($vocab['word']) ?></h5>
                                    <?php if($vocab['part_of_speech']): ?>
                                        <span class="badge bg-light text-secondary border"><?= escape($vocab['part_of_speech']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9 ps-md-4 mt-3 mt-md-0">
                                    <?php if($vocab['hindi_meaning']): ?>
                                    <div class="mb-2">
                                        <strong class="text-success">Hindi Meaning:</strong> <span class="fs-5"><?= escape($vocab['hindi_meaning']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if($vocab['english_meaning']): ?>
                                    <div class="mb-2">
                                        <strong>English Meaning:</strong> <?= escape($vocab['english_meaning']) ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if($vocab['synonym'] || $vocab['antonym']): ?>
                                    <div class="mb-2 text-muted small">
                                        <?php if($vocab['synonym']): ?>
                                            <span class="me-3"><strong>Synonym:</strong> <?= escape($vocab['synonym']) ?></span>
                                        <?php endif; ?>
                                        <?php if($vocab['antonym']): ?>
                                            <span><strong>Antonym:</strong> <?= escape($vocab['antonym']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if($vocab['example_sentence']): ?>
                                    <div class="bg-light p-2 rounded mt-2 border-start border-3 border-primary fst-italic text-muted">
                                        "<?= escape($vocab['example_sentence']) ?>"
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- CTA -->
            <div class="card bg-primary-custom text-white border-0 rounded-4 p-4 mt-5 text-center shadow">
                <div class="card-body">
                    <h3 class="fw-bold mb-3">Inspired by this story?</h3>
                    <p class="mb-4">Use the new vocabulary you've learned and write your own English story.</p>
                    <a href="write-story.php" class="btn btn-light btn-lg text-primary-custom fw-bold px-4">Write Your Own Story</a>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
