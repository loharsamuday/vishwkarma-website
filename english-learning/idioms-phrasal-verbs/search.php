<?php
// idioms-phrasal-verbs/search.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$page_title = "Search Results for '" . htmlspecialchars($q) . "'";
$seo_desc = "Search results for idioms and phrasal verbs.";
require_once '../includes/header.php';

$results = [];

if (!empty($q)) {
    // Search idioms
    $stmt = $pdo->prepare("SELECT id, idiom as title, slug, hindi_meaning, english_meaning, difficulty, 'Idiom' as type, featured FROM idioms WHERE status = 'Published' AND (idiom LIKE ? OR hindi_meaning LIKE ? OR english_meaning LIKE ?)");
    $stmt->execute(["%$q%", "%$q%", "%$q%"]);
    $idioms = $stmt->fetchAll();

    // Search phrasal verbs
    $stmt = $pdo->prepare("SELECT id, phrasal_verb as title, slug, hindi_meaning, english_meaning, difficulty, 'Phrasal Verb' as type, featured FROM phrasal_verbs WHERE status = 'Published' AND (phrasal_verb LIKE ? OR hindi_meaning LIKE ? OR english_meaning LIKE ?)");
    $stmt->execute(["%$q%", "%$q%", "%$q%"]);
    $phrasal_verbs = $stmt->fetchAll();
    
    // Merge and sort results (can be improved, but simple merge for now)
    $results = array_merge($idioms, $phrasal_verbs);
}
?>

<div class="bg-light py-4 border-bottom mb-4">
    <div class="container">
        <h1 class="fw-bold">Search Results</h1>
        <p class="text-muted mb-0">Showing results for: <strong><?= htmlspecialchars($q) ?></strong> (<?= count($results) ?> found)</p>
    </div>
</div>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form action="search.php" method="GET" class="d-flex mb-5">
                <input type="text" name="q" class="form-control form-control-lg me-2 shadow-sm" value="<?= htmlspecialchars($q) ?>" required>
                <button type="submit" class="btn btn-primary btn-lg shadow-sm"><i class="fas fa-search"></i></button>
            </form>

            <?php if (empty($q)): ?>
                <div class="alert alert-info">Please enter a search term.</div>
            <?php elseif (count($results) > 0): ?>
                <div class="list-group shadow-sm">
                    <?php foreach($results as $item): 
                        $link = $item['type'] == 'Idiom' ? '../idioms/' . $item['slug'] : '../phrasal-verbs/' . $item['slug'];
                    ?>
                        <a href="<?= $link ?>" class="list-group-item list-group-item-action p-4">
                            <div class="d-flex w-100 justify-content-between mb-2">
                                <h5 class="mb-1 fw-bold <?= $item['type'] == 'Idiom' ? 'text-primary' : 'text-success' ?>">
                                    <?= htmlspecialchars($item['title']) ?>
                                </h5>
                                <small class="badge <?= $item['type'] == 'Idiom' ? 'bg-primary' : 'bg-success' ?>"><?= $item['type'] ?></small>
                            </div>
                            <p class="mb-1 text-muted"><i class="fas fa-language me-1"></i> <?= htmlspecialchars($item['hindi_meaning']) ?></p>
                            <small><?= htmlspecialchars(substr($item['english_meaning'], 0, 150)) ?>...</small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning text-center py-5">
                    <i class="fas fa-search fa-3x mb-3 text-muted"></i>
                    <h4>No results found for "<?= htmlspecialchars($q) ?>"</h4>
                    <p>Try searching with different keywords.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
