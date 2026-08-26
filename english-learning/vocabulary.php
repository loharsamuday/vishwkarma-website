<?php
// vocabulary.php
session_start();
require_once 'config/database.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "1=1";
$params = [];

if ($search) {
    $where .= " AND (word LIKE ? OR hindi_meaning LIKE ? OR english_meaning LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

// Count total
$count_sql = "SELECT COUNT(*) FROM vocabulary WHERE $where";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_vocab = $stmt->fetchColumn();
$total_pages = ceil($total_vocab / $per_page);

// Fetch vocabulary
$sql = "
    SELECT v.*, s.title as story_title, s.id as story_id 
    FROM vocabulary v 
    LEFT JOIN stories s ON v.story_id = s.id 
    WHERE $where 
    ORDER BY v.word ASC 
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vocabularies = $stmt->fetchAll();

$page_title = 'Vocabulary Dictionary';
include 'includes/header.php';
?>

<div class="bg-primary-custom text-white py-4 mb-5 shadow-sm">
    <div class="container text-center">
        <h1 class="fw-bold mb-2"><i class="fas fa-book-open me-2"></i>Vocabulary Dictionary</h1>
        <p class="lead mb-4">Learn new words, their Hindi meanings, and how to use them.</p>
        
        <div class="row justify-content-center">
            <div class="col-md-6">
                <form action="vocabulary.php" method="GET" class="d-flex bg-white rounded p-1 shadow-sm">
                    <input type="text" name="search" class="form-control border-0 shadow-none" placeholder="Search words, meanings..." value="<?= escape($search) ?>">
                    <button type="submit" class="btn btn-success px-4">Search</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <?php if ($search): ?>
        <h4 class="mb-4">Search Results for: "<?= escape($search) ?>" (<?= $total_vocab ?> found)</h4>
    <?php endif; ?>

    <?php if(count($vocabularies) > 0): ?>
        <div class="row">
            <?php foreach ($vocabularies as $vocab): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 vocab-card border-0 shadow-sm transition hover-shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h4 class="fw-bold text-primary-custom mb-0"><?= escape($vocab['word']) ?></h4>
                            <?php if($vocab['part_of_speech']): ?>
                                <span class="badge bg-light text-secondary border"><?= escape($vocab['part_of_speech']) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($vocab['hindi_meaning']): ?>
                        <div class="mb-2">
                            <strong class="text-success">Hindi:</strong> <span class="fs-5"><?= escape($vocab['hindi_meaning']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($vocab['english_meaning']): ?>
                        <div class="mb-3 text-muted small">
                            <?= escape($vocab['english_meaning']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($vocab['example_sentence']): ?>
                        <div class="bg-light p-2 rounded small border-start border-2 border-primary fst-italic text-muted mt-auto mb-3">
                            "<?= escape($vocab['example_sentence']) ?>"
                        </div>
                        <?php endif; ?>
                        
                        <?php if($vocab['story_id']): ?>
                        <div class="mt-auto border-top pt-2">
                            <small class="text-muted">Found in: <a href="story.php?id=<?= $vocab['story_id'] ?>" class="text-decoration-none"><?= escape($vocab['story_title']) ?></a></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <?php
            $query_params = "";
            if($search) $query_params .= "&search=" . urlencode($search);
        ?>
        <nav aria-label="Page navigation" class="mt-5">
            <ul class="pagination justify-content-center">
                <!-- Previous Button -->
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?><?= $query_params ?>">Previous</a>
                </li>

                <?php
                $range = 2;
                $start = max(1, $page - $range);
                $end = min($total_pages, $page + $range);

                if ($start > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1'.$query_params.'">1</a></li>';
                    if ($start > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                for ($i = $start; $i <= $end; $i++) {
                    $active = ($page == $i) ? 'active' : '';
                    echo '<li class="page-item '.$active.'"><a class="page-link" href="?page='.$i.$query_params.'">'.$i.'</a></li>';
                }

                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.$query_params.'">'.$total_pages.'</a></li>';
                }
                ?>

                <!-- Next Button -->
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?><?= $query_params ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-info text-center py-5">
            <i class="fas fa-search fa-3x mb-3 text-info"></i>
            <h4>No vocabulary words found</h4>
            <p class="mb-0">Try a different search term or check back later.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
