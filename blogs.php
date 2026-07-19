<?php
$page_title = "Community Blogs";
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/blog_helper.php';

// Fetch all approved blogs with author name, average rating, and comment count
$query = "
    SELECT b.*, u.first_name, u.last_name,
        (SELECT COUNT(*) FROM blog_comments bc WHERE bc.blog_id = b.id) as comment_count,
        (SELECT AVG(rating) FROM blog_ratings br WHERE br.blog_id = b.id) as avg_rating
    FROM blogs b
    JOIN users u ON b.user_id = u.id
    WHERE b.status = 'approved'
    ORDER BY b.created_at DESC
";
$blogs = $pdo->query($query)->fetchAll();

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<?php $banner_img = function_exists('getUiImage') ? getUiImage('banner_blogs', 'https://placehold.co/1920x400/2c3e50/f39c12?text=Community+Blogs') : 'https://placehold.co/1920x400/2c3e50/f39c12?text=Community+Blogs'; ?>
<div class="page-banner mb-4">
    <img src="<?= htmlspecialchars($banner_img) ?>" class="img-fluid w-100 shadow-sm" style="max-height: 400px; object-fit: cover;">
</div>

<div class="container py-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
        <div>
            <h1 class="fw-bold text-warning mb-2">Community Blogs</h1>
            <p class="text-muted mb-0">Read, share, and write articles related to the Vishwakarma community.</p>
        </div>
        <div>
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="blog-create.php" class="btn btn-warning fw-bold px-4"><i class="fa-solid fa-pen-nib me-2"></i> Write a Blog</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-warning fw-bold px-4">Login to Write</a>
            <?php endif; ?>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row g-4">
        <?php if (count($blogs) > 0): ?>
            <?php foreach ($blogs as $blog): 
                $rank = getBloggerRank($blog['user_id'], $pdo);
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm hover-lift">
                        <?php if ($blog['image_path']): ?>
                            <img src="uploads/blogs/<?= htmlspecialchars($blog['image_path']) ?>" class="card-img-top" alt="Blog Image" style="height: 200px; object-fit: cover;">
                        <?php else: ?>
                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center text-muted" style="height: 200px;">
                                <i class="fa-solid fa-newspaper fa-3x"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title fw-bold text-dark mb-3">
                                <a href="blog-detail.php?id=<?= $blog['id'] ?>" class="text-decoration-none text-dark stretched-link">
                                    <?= htmlspecialchars($blog['title']) ?>
                                </a>
                            </h5>
                            <p class="card-text text-muted small mb-4 flex-grow-1">
                                <?= htmlspecialchars(mb_strimwidth(strip_tags($blog['content']), 0, 120, "...")) ?>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                                <div class="text-muted small">
                                    <div class="mb-1">
                                        <i class="fa-solid fa-user me-1 text-warning"></i> <?= htmlspecialchars($blog['first_name'] . ' ' . $blog['last_name']) ?>
                                    </div>
                                    <span class="<?= $rank['class'] ?> fw-normal" title="<?= $rank['points'] ?> Points"><i class="<?= $rank['icon'] ?> me-1"></i><?= $rank['title'] ?></span>
                                </div>
                                <div class="text-muted small">
                                    <span class="me-3"><i class="fa-solid fa-star text-warning"></i> <?= number_format($blog['avg_rating'] ?? 0, 1) ?></span>
                                    <span><i class="fa-solid fa-comment text-secondary"></i> <?= $blog['comment_count'] ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-0 pb-3 pt-0 d-flex justify-content-between align-items-center">
                            <small class="text-muted"><i class="fa-regular fa-clock me-1"></i> <?= date('d M Y', strtotime($blog['created_at'])) ?></small>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $blog['user_id']): ?>
                                <div>
                                    <a href="blog-edit.php?id=<?= $blog['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                    <form action="api/delete_blog.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this blog?');">
                                        <input type="hidden" name="blog_id" value="<?= $blog['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <i class="fa-solid fa-pen-fancy fa-4x text-muted mb-3"></i>
                <h3 class="fw-bold text-dark">No blogs found</h3>
                <p class="text-muted">Be the first one to share your thoughts with the community!</p>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="blog-create.php" class="btn btn-warning fw-bold mt-3">Write a Blog</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-warning fw-bold mt-3">Login to Write</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
