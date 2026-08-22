<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?msg=login_required");
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'My English Memory';

// Fetch stats
$stmt_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_saved,
        SUM(CASE WHEN status = 'need_revision' THEN 1 ELSE 0 END) as need_revision,
        SUM(CASE WHEN status = 'mastered' THEN 1 ELSE 0 END) as mastered,
        SUM(CASE WHEN item_type = 'idiom' THEN 1 ELSE 0 END) as total_idioms,
        SUM(CASE WHEN item_type = 'phrasal_verb' THEN 1 ELSE 0 END) as total_phrasal
    FROM student_memory 
    WHERE user_id = ?
");
$stmt_stats->execute([$user_id]);
$stats = $stmt_stats->fetch();

$filter = $_GET['filter'] ?? 'all';
$query = "SELECT sm.*, 
            COALESCE(i.idiom, p.phrasal_verb, v.word) as word,
            COALESCE(i.hindi_meaning, p.hindi_meaning, v.hindi_meaning) as hindi,
            COALESCE(i.memory_trick, p.memory_trick) as trick,
            COALESCE(i.slug, p.slug) as slug
          FROM student_memory sm
          LEFT JOIN idioms i ON sm.item_type = 'idiom' AND sm.item_id = i.id
          LEFT JOIN phrasal_verbs p ON sm.item_type = 'phrasal_verb' AND sm.item_id = p.id
          LEFT JOIN vocabulary v ON sm.item_type = 'vocabulary' AND sm.item_id = v.id
          WHERE sm.user_id = ?";

$params = [$user_id];
if ($filter === 'need_revision') {
    $query .= " AND sm.status = 'need_revision'";
} elseif ($filter === 'mastered') {
    $query .= " AND sm.status = 'mastered'";
} elseif ($filter === 'learning') {
    $query .= " AND sm.status = 'learning'";
}
$query .= " ORDER BY sm.updated_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="bg-primary-custom py-4 mb-5 border-bottom text-white">
    <div class="container">
        <h1 class="fw-bold mb-0"><i class="fas fa-brain me-2"></i>My English Memory</h1>
        <p class="mb-0 mt-2 opacity-75">Your personal vault of words, idioms, and phrases.</p>
    </div>
</div>

<div class="container pb-5">
    
    <!-- Stats Row -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-4">
                    <h1 class="text-primary fw-bold mb-0"><?= $stats['total_saved'] ?? 0 ?></h1>
                    <p class="text-muted small text-uppercase fw-bold">Total Saved</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-4">
                    <h1 class="text-danger fw-bold mb-0"><?= $stats['need_revision'] ?? 0 ?></h1>
                    <p class="text-muted small text-uppercase fw-bold">Need Revision</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-4">
                    <h1 class="text-success fw-bold mb-0"><?= $stats['mastered'] ?? 0 ?></h1>
                    <p class="text-muted small text-uppercase fw-bold">Mastered</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-4">
                    <h1 class="text-warning fw-bold mb-0"><?= $stats['total_idioms'] ?? 0 ?></h1>
                    <p class="text-muted small text-uppercase fw-bold">Idioms</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $filter == 'all' ? 'active bg-primary-custom' : '' ?>" href="?filter=all">All Saved</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $filter == 'need_revision' ? 'active bg-danger' : 'text-danger' ?>" href="?filter=need_revision">Need Revision</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $filter == 'learning' ? 'active bg-info' : 'text-info' ?>" href="?filter=learning">Learning</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $filter == 'mastered' ? 'active bg-success' : 'text-success' ?>" href="?filter=mastered">Mastered</a>
        </li>
    </ul>

    <!-- Items List -->
    <?php if(count($items) > 0): ?>
        <div class="row g-4">
            <?php foreach($items as $item): ?>
                <?php 
                    $badge_class = 'bg-secondary';
                    if($item['status'] == 'need_revision') $badge_class = 'bg-danger';
                    if($item['status'] == 'mastered') $badge_class = 'bg-success';
                    if($item['status'] == 'learning') $badge_class = 'bg-info';
                    
                    $link = "#";
                    if($item['item_type'] == 'idiom') $link = EL_BASE_URL . "idioms/" . $item['slug'];
                    if($item['item_type'] == 'phrasal_verb') $link = EL_BASE_URL . "phrasal-verbs/" . $item['slug'];
                ?>
                <div class="col-md-6 col-lg-4" id="mem-card-<?= $item['id'] ?>">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="badge bg-light text-dark border"><?= ucfirst(str_replace('_', ' ', $item['item_type'])) ?></span>
                                <span class="badge <?= $badge_class ?>"><?= ucfirst(str_replace('_', ' ', $item['status'])) ?></span>
                            </div>
                            <h4 class="fw-bold mb-1"><a href="<?= $link ?>" class="text-decoration-none text-dark"><?= escape($item['word']) ?></a></h4>
                            <p class="text-muted mb-2"><i class="fas fa-language me-1 text-primary"></i> <?= escape($item['hindi']) ?></p>
                            
                            <?php if($item['trick']): ?>
                                <div class="p-2 bg-warning bg-opacity-10 rounded small mb-3">
                                    <i class="fas fa-lightbulb text-warning"></i> <?= escape($item['trick']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                                <span class="small text-muted">Mastery: <?= $item['mastery_score'] ?>/100</span>
                                <button class="btn btn-sm btn-outline-danger" onclick="removeFromMemory(<?= $item['id'] ?>)"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5 bg-light rounded">
            <i class="fas fa-box-open fa-3x text-muted mb-3 opacity-50"></i>
            <h4 class="text-muted">No items found</h4>
            <p>Start learning and click "Remember Me" to save items here.</p>
            <a href="<?= EL_BASE_URL ?>idioms-phrasal-verbs/" class="btn btn-primary mt-2">Explore Idioms</a>
        </div>
    <?php endif; ?>

</div>

<script>
function removeFromMemory(mem_id) {
    if(confirm('Remove this item from your memory?')) {
        const formData = new FormData();
        formData.append('mem_id', mem_id);
        formData.append('action', 'remove');
        
        fetch('memory_remove.php', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(res => {
            if(res.status == 'success') {
                document.getElementById('mem-card-'+mem_id).remove();
            }
        });
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
