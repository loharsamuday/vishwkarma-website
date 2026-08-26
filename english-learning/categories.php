<?php
// categories.php
session_start();
require_once 'config/database.php';
$page_title = 'Categories';
include 'includes/header.php';

// Fetch categories with story counts
$sql = "
    SELECT c.*, COUNT(s.id) as story_count 
    FROM categories c 
    LEFT JOIN stories s ON c.id = s.category_id AND s.status = 'Published' 
    GROUP BY c.id 
    ORDER BY c.name ASC
";
$stmt = $pdo->query($sql);
$categories = $stmt->fetchAll();
?>

<div class="bg-light py-5 mb-5 border-bottom">
    <div class="container text-center">
        <h1 class="fw-bold text-primary-custom mb-2">Browse Categories</h1>
        <p class="text-muted lead mb-0">Find stories in your favorite genres.</p>
    </div>
</div>

<?php
// Helper functions for dynamic logos and colors
function getCategoryIcon($name) {
    $name = strtolower($name);
    if (strpos($name, 'moral') !== false) return 'fa-balance-scale';
    if (strpos($name, 'kid') !== false || strpos($name, 'child') !== false) return 'fa-child';
    if (strpos($name, 'animal') !== false) return 'fa-paw';
    if (strpos($name, 'fairy') !== false || strpos($name, 'magic') !== false) return 'fa-wand-magic-sparkles';
    if (strpos($name, 'funny') !== false || strpos($name, 'comedy') !== false) return 'fa-face-laugh-squint';
    if (strpos($name, 'horror') !== false || strpos($name, 'scary') !== false || strpos($name, 'ghost') !== false) return 'fa-ghost';
    if (strpos($name, 'mystery') !== false || strpos($name, 'detective') !== false) return 'fa-user-secret';
    if (strpos($name, 'adventure') !== false) return 'fa-mountain';
    if (strpos($name, 'sci-fi') !== false || strpos($name, 'space') !== false) return 'fa-rocket';
    if (strpos($name, 'history') !== false) return 'fa-monument';
    if (strpos($name, 'love') !== false || strpos($name, 'romance') !== false) return 'fa-heart';
    if (strpos($name, 'nature') !== false) return 'fa-leaf';
    if (strpos($name, 'myth') !== false) return 'fa-dragon';
    if (strpos($name, 'folk') !== false || strpos($name, 'culture') !== false) return 'fa-landmark';
    if (strpos($name, 'inspire') !== false || strpos($name, 'motivat') !== false) return 'fa-lightbulb';
    
    return 'fa-book-open';
}

function getCategoryColor($name) {
    // Array of nice bootstrap colors
    $colors = ['primary', 'success', 'warning', 'danger', 'info', 'secondary'];
    // Generate a consistent index based on the string
    $index = crc32($name) % count($colors);
    return $colors[$index];
}
?>

<div class="container pb-5">
    <div class="row g-4">
        <?php foreach($categories as $cat): ?>
        <?php 
            $icon = getCategoryIcon($cat['name']);
            $color = getCategoryColor($cat['name']);
        ?>
        <div class="col-md-4 col-sm-6">
            <a href="stories.php?category=<?= $cat['id'] ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm hover-shadow transition">
                    <div class="card-body p-5 text-center">
                        <div class="mb-4">
                            <div class="d-inline-block p-4 rounded-circle bg-<?= $color ?> bg-opacity-10 text-<?= $color ?>">
                                <i class="fas <?= $icon ?> fa-3x"></i>
                            </div>
                        </div>
                        <h4 class="fw-bold text-dark mb-2"><?= escape($cat['name']) ?></h4>
                        <span class="badge bg-light text-secondary border px-3 py-2 fs-6"><?= $cat['story_count'] ?> Stories</span>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
        
        <?php if(empty($categories)): ?>
            <div class="col-12 text-center">
                <div class="alert alert-info">No categories available.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
