<?php
// search.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$page_title = 'Search Results';
include 'includes/header.php';

$stories = [];
$vocabularies = [];

if ($query) {
    $search_param = "%$query%";
    
    // Search Stories
    $stmt = $pdo->prepare("
        SELECT id, title, short_description, difficulty, reading_time, 'story' as type 
        FROM stories 
        WHERE status = 'Published' AND (title LIKE ? OR content LIKE ? OR short_description LIKE ?)
        LIMIT 10
    ");
    $stmt->execute([$search_param, $search_param, $search_param]);
    $stories = $stmt->fetchAll();
    
    // Search Vocabulary
    $stmt = $pdo->prepare("
        SELECT v.*, s.title as story_title 
        FROM vocabulary v 
        LEFT JOIN stories s ON v.story_id = s.id
        WHERE v.word LIKE ? OR v.hindi_meaning LIKE ? OR v.synonym LIKE ?
        LIMIT 15
    ");
    $stmt->execute([$search_param, $search_param, $search_param]);
    $vocabularies = $stmt->fetchAll();
}
?>

<div class="container py-5 min-vh-100">
    <h2 class="mb-4">Search Results for <span class="text-primary-custom">"<?= escape($query) ?>"</span></h2>
    
    <?php if(empty($query)): ?>
        <div class="alert alert-warning">Please enter a search term.</div>
    <?php else: ?>
        
        <!-- Stories Results -->
        <h4 class="mb-3 border-bottom pb-2">Stories (<?= count($stories) ?>)</h4>
        <?php if(count($stories) > 0): ?>
            <div class="list-group mb-5 shadow-sm">
                <?php foreach($stories as $story): ?>
                    <a href="story.php?id=<?= $story['id'] ?>" class="list-group-item list-group-item-action p-4 border-0 border-bottom">
                        <div class="d-flex w-100 justify-content-between mb-2">
                            <h5 class="mb-1 fw-bold text-primary-custom"><?= escape($story['title']) ?></h5>
                            <small class="text-muted"><i class="far fa-clock me-1"></i><?= $story['reading_time'] ?> min</small>
                        </div>
                        <p class="mb-2 text-muted"><?= escape($story['short_description']) ?></p>
                        <span class="badge bg-light text-dark border"><?= escape($story['difficulty']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-light border mb-5">No stories found matching your search.</div>
        <?php endif; ?>
        
        <!-- Vocabulary Results -->
        <h4 class="mb-3 border-bottom pb-2">Vocabulary (<?= count($vocabularies) ?>)</h4>
        <?php if(count($vocabularies) > 0): ?>
            <div class="row g-4">
                <?php foreach($vocabularies as $vocab): ?>
                <div class="col-md-6">
                    <div class="card h-100 border-0 shadow-sm vocab-card p-2">
                        <div class="card-body">
                            <h5 class="fw-bold mb-2"><?= escape($vocab['word']) ?></h5>
                            <p class="mb-1"><strong class="text-success">Hindi:</strong> <?= escape($vocab['hindi_meaning']) ?></p>
                            <?php if($vocab['synonym']): ?>
                                <p class="mb-1 text-muted small"><strong>Synonym:</strong> <?= escape($vocab['synonym']) ?></p>
                            <?php endif; ?>
                            <?php if($vocab['story_id']): ?>
                                <p class="mb-0 mt-2 text-muted small"><i class="fas fa-book-reader me-1"></i> Found in: <a href="story.php?id=<?= $vocab['story_id'] ?>"><?= escape($vocab['story_title']) ?></a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-light border">No vocabulary words found matching your search.</div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
