<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?msg=login_required");
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'Smart Revision';

$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT sm.*, 
        COALESCE(i.idiom, p.phrasal_verb, v.word) as word,
        COALESCE(i.hindi_meaning, p.hindi_meaning, v.hindi_meaning) as hindi,
        COALESCE(i.memory_trick, p.memory_trick) as trick,
        COALESCE(i.slug, p.slug) as slug
    FROM student_memory sm
    LEFT JOIN idioms i ON sm.item_type = 'idiom' AND sm.item_id = i.id
    LEFT JOIN phrasal_verbs p ON sm.item_type = 'phrasal_verb' AND sm.item_id = p.id
    LEFT JOIN vocabulary v ON sm.item_type = 'vocabulary' AND sm.item_id = v.id
    WHERE sm.user_id = ? AND (sm.next_revision_date <= ? OR sm.status = 'need_revision')
    ORDER BY sm.next_revision_date ASC
");
$stmt->execute([$user_id, $today]);
$due_items = $stmt->fetchAll();

$idioms_count = 0;
$phrasal_count = 0;
foreach($due_items as $item) {
    if($item['item_type'] == 'idiom') $idioms_count++;
    if($item['item_type'] == 'phrasal_verb') $phrasal_count++;
}

require_once '../includes/header.php';
?>

<div class="bg-warning bg-opacity-10 py-4 mb-5 border-bottom border-warning">
    <div class="container">
        <h1 class="fw-bold text-dark mb-0"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Smart Revision</h1>
        <p class="mb-0 mt-2 text-muted">Spaced repetition to help you remember what you forget.</p>
    </div>
</div>

<div class="container pb-5">
    <?php if(count($due_items) > 0): ?>
        <div class="row align-items-center mb-5">
            <div class="col-md-6">
                <h3 class="fw-bold mb-3">⚠️ Revision Due: <?= count($due_items) ?> items</h3>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><i class="fas fa-comment-dots text-primary me-2"></i> Idioms: <strong><?= $idioms_count ?></strong></li>
                    <li><i class="fas fa-layer-group text-success me-2"></i> Phrasal Verbs: <strong><?= $phrasal_count ?></strong></li>
                </ul>
            </div>
            <div class="col-md-6 text-md-end mt-4 mt-md-0">
                <a href="session.php" class="btn btn-warning btn-lg fw-bold shadow-sm px-5 py-3 rounded-pill text-dark">
                    <i class="fas fa-play-circle me-2"></i>Start Revision
                </a>
            </div>
        </div>
        
        <hr class="mb-5">
        
        <h4 class="fw-bold mb-4">Items waiting for your review</h4>
        <div class="row g-4">
            <?php foreach($due_items as $item): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm border-0 h-100 border-start border-warning border-4">
                        <div class="card-body">
                            <span class="badge bg-light text-dark border mb-2"><?= ucfirst(str_replace('_', ' ', $item['item_type'])) ?></span>
                            <h5 class="fw-bold mb-1"><?= escape($item['word']) ?></h5>
                            <p class="text-muted small mb-0"><i class="fas fa-clock text-warning me-1"></i> Due since: <?= $item['next_revision_date'] ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
    <?php else: ?>
        <div class="text-center py-5 bg-light rounded shadow-sm">
            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
            <h3 class="fw-bold">All Caught Up!</h3>
            <p class="text-muted">You have no items due for revision today. Great job!</p>
            <a href="<?= EL_BASE_URL ?>idioms-phrasal-verbs/" class="btn btn-primary mt-3">Learn Something New</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
