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

<div class="container pb-5">
    <div class="row g-4">
        <?php foreach($categories as $cat): ?>
        <div class="col-md-4 col-sm-6">
            <a href="stories.php?category=<?= $cat['id'] ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm hover-shadow transition">
                    <div class="card-body p-5 text-center">
                        <div class="mb-4">
                            <div class="d-inline-block p-4 rounded-circle bg-primary-custom bg-opacity-10 text-primary-custom">
                                <i class="fas fa-folder-open fa-3x"></i>
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
