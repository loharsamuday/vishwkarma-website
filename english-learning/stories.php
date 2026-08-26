<?php
// stories.php
session_start();
require_once 'config/database.php';

$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : null;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 9;
$offset = ($page - 1) * $per_page;

$where = ["s.status = 'Published'"];
$params = [];

if ($category_filter) {
    $where[] = "s.category_id = ?";
    $params[] = $category_filter;
}
if ($difficulty_filter) {
    $where[] = "s.difficulty = ?";
    $params[] = $difficulty_filter;
}

$where_clause = implode(' AND ', $where);

// Count total
$count_sql = "SELECT COUNT(*) FROM stories s WHERE $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_stories = $stmt->fetchColumn();
$total_pages = ceil($total_stories / $per_page);

// Fetch stories
$sql = "
    SELECT s.*, c.name as category_name 
    FROM stories s 
    LEFT JOIN categories c ON s.category_id = c.id 
    WHERE $where_clause 
    ORDER BY s.created_at DESC 
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stories = $stmt->fetchAll();

// Fetch categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$page_title = 'English Stories';
include 'includes/header.php';
?>

<div class="bg-light py-4 mb-5 border-bottom">
    <div class="container">
        <h1 class="fw-bold text-primary-custom mb-2">English Stories</h1>
        <p class="text-muted lead mb-0">Browse our collection of stories to improve your reading and vocabulary.</p>
    </div>
</div>

<div class="container">
    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 mb-4">
            <div class="card shadow-sm border-0 sticky-top" style="top: 20px; z-index: 10;">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="fas fa-filter me-2 text-accent"></i> Filters
                </div>
                <div class="card-body">
                    <form action="stories.php" method="GET">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase">Category</label>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>><?= escape($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase">Difficulty Level</label>
                            <select name="difficulty" class="form-select">
                                <option value="">All Levels</option>
                                <option value="Beginner" <?= $difficulty_filter == 'Beginner' ? 'selected' : '' ?>>Beginner</option>
                                <option value="Intermediate" <?= $difficulty_filter == 'Intermediate' ? 'selected' : '' ?>>Intermediate</option>
                                <option value="Advanced" <?= $difficulty_filter == 'Advanced' ? 'selected' : '' ?>>Advanced</option>
                            </select>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary bg-primary-custom">Apply Filters</button>
                            <a href="stories.php" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Stories Grid -->
        <div class="col-lg-9">
            <?php if(count($stories) > 0): ?>
                <div class="row g-4 mb-4">
                    <?php foreach($stories as $story): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card story-card rounded-3 overflow-hidden">
                            <div class="card-body p-4 d-flex flex-column">
                                <div class="mb-2 d-flex justify-content-between align-items-center">
                                    <span class="badge bg-secondary"><?= escape($story['category_name'] ?: 'Uncategorized') ?></span>
                                    <?php if($story['difficulty'] == 'Beginner'): ?>
                                        <span class="badge badge-beginner">Beginner</span>
                                    <?php elseif($story['difficulty'] == 'Intermediate'): ?>
                                        <span class="badge badge-intermediate">Intermediate</span>
                                    <?php else: ?>
                                        <span class="badge badge-advanced">Advanced</span>
                                    <?php endif; ?>
                                </div>
                                <h5 class="card-title fw-bold mt-3 mb-3">
                                    <a href="story.php?id=<?= $story['id'] ?>" class="text-dark stretched-link"><?= escape($story['title']) ?></a>
                                </h5>
                                <p class="card-text text-muted mb-4 small"><?= escape($story['short_description']) ?></p>
                                <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                    <small class="text-muted"><i class="far fa-clock me-1"></i> <?= $story['reading_time'] ?> min</small>
                                    <span class="text-primary-custom fw-bold small">Read <i class="fas fa-chevron-right ms-1"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <?php
                    $query_params = "";
                    if($category_filter) $query_params .= "&category=" . $category_filter;
                    if($difficulty_filter) $query_params .= "&difficulty=" . $difficulty_filter;
                ?>
                <nav aria-label="Page navigation" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <!-- Previous Button -->
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?><?= $query_params ?>">Previous</a>
                        </li>

                        <?php
                        $range = 2; // Pages to show before and after current
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
                    <i class="fas fa-info-circle fa-3x mb-3 text-info"></i>
                    <h4>No stories found</h4>
                    <p class="mb-0">Try adjusting your filters or check back later for new content.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
