<?php
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/blog_helper.php';

$blog_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$blog_id) {
    header("Location: blogs.php");
    exit;
}

// Fetch blog data
$stmt = $pdo->prepare("
    SELECT b.*, u.first_name, u.last_name, u.email 
    FROM blogs b 
    JOIN users u ON b.user_id = u.id 
    WHERE b.id = ? AND b.status = 'approved'
");
$stmt->execute([$blog_id]);
$blog = $stmt->fetch();

if (!$blog) {
    setFlashMessage('danger', 'Blog not found or is pending approval.');
    header("Location: blogs.php");
    exit;
}

$page_title = htmlspecialchars($blog['title']);
$author_rank = getBloggerRank($blog['user_id'], $pdo);

// Handle Comment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment']) && isset($_SESSION['user_id'])) {
    $comment_text = trim($_POST['comment']);
    if (!empty($comment_text)) {
        $stmt = $pdo->prepare("INSERT INTO blog_comments (blog_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->execute([$blog_id, $_SESSION['user_id'], $comment_text]);
        setFlashMessage('success', 'Your comment has been added.');
        header("Location: blog-detail.php?id=" . $blog_id);
        exit;
    }
}

// Handle Rating Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating']) && isset($_SESSION['user_id'])) {
    $rating_val = (int)$_POST['rating'];
    if ($rating_val >= 1 && $rating_val <= 5) {
        // Insert or update rating using ON DUPLICATE KEY UPDATE logic
        $stmt = $pdo->prepare("
            INSERT INTO blog_ratings (blog_id, user_id, rating) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE rating = VALUES(rating)
        ");
        $stmt->execute([$blog_id, $_SESSION['user_id'], $rating_val]);
        setFlashMessage('success', 'Thanks for your rating!');
        header("Location: blog-detail.php?id=" . $blog_id);
        exit;
    }
}

// Fetch comments
$comments_stmt = $pdo->prepare("
    SELECT bc.*, u.first_name, u.last_name 
    FROM blog_comments bc 
    JOIN users u ON bc.user_id = u.id 
    WHERE bc.blog_id = ? 
    ORDER BY bc.created_at DESC
");
$comments_stmt->execute([$blog_id]);
$comments = $comments_stmt->fetchAll();

// Fetch average rating
$rating_stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM blog_ratings WHERE blog_id = ?");
$rating_stmt->execute([$blog_id]);
$rating_data = $rating_stmt->fetch();
$avg_rating = $rating_data['avg_rating'] ? number_format($rating_data['avg_rating'], 1) : 'No ratings yet';
$total_ratings = $rating_data['total_ratings'];

// Fetch user's own rating if logged in
$user_rating = 0;
if (isset($_SESSION['user_id'])) {
    $usr_rating_stmt = $pdo->prepare("SELECT rating FROM blog_ratings WHERE blog_id = ? AND user_id = ?");
    $usr_rating_stmt->execute([$blog_id, $_SESSION['user_id']]);
    $user_rating = $usr_rating_stmt->fetchColumn() ?: 0;
}

$current_url = urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
$share_title = urlencode($blog['title']);

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Blog Content -->
            <?php displayFlashMessage(); ?>
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <a href="blogs.php" class="text-decoration-none text-muted"><i class="fa-solid fa-arrow-left me-2"></i> Back to Blogs</a>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $blog['user_id']): ?>
                    <div>
                        <a href="blog-edit.php?id=<?= $blog['id'] ?>" class="btn btn-sm btn-outline-primary me-2"><i class="fa-solid fa-pen me-1"></i> Edit</a>
                        <form action="api/delete_blog.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this blog?');">
                            <input type="hidden" name="blog_id" value="<?= $blog['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash me-1"></i> Delete</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <h1 class="fw-bold mb-3"><?= htmlspecialchars($blog['title']) ?></h1>
            <div class="d-flex align-items-center text-muted mb-4 pb-3 border-bottom flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <i class="fa-solid fa-user text-warning me-2"></i> 
                    <span class="me-2"><?= htmlspecialchars($blog['first_name'] . ' ' . $blog['last_name']) ?></span>
                    <span class="<?= $author_rank['class'] ?> fw-normal" title="<?= $author_rank['points'] ?> Points"><i class="<?= $author_rank['icon'] ?> me-1"></i><?= $author_rank['title'] ?></span>
                </div>
                <div><i class="fa-regular fa-clock text-secondary me-2"></i> <?= date('d M Y', strtotime($blog['created_at'])) ?></div>
                <div><i class="fa-solid fa-star text-warning me-1"></i> <?= $avg_rating ?> (<?= $total_ratings ?>)</div>
            </div>

            <?php if ($blog['image_path']): ?>
                <div class="mb-4 text-center">
                    <img src="uploads/blogs/<?= htmlspecialchars($blog['image_path']) ?>" class="img-fluid rounded shadow-sm w-100" style="max-height: 400px; object-fit: cover;" alt="<?= htmlspecialchars($blog['title']) ?>">
                </div>
            <?php endif; ?>

            <div class="blog-content mb-5" style="font-size: 1.1rem; line-height: 1.8; color: #444;">
                <?= $blog['content'] ?>
            </div>

            <!-- Share Buttons -->
            <div class="d-flex align-items-center mb-5 bg-light p-3 rounded">
                <strong class="me-3">Share this blog:</strong>
                <a href="https://api.whatsapp.com/send?text=<?= $share_title ?>%20<?= $current_url ?>" target="_blank" class="btn btn-sm btn-success me-2"><i class="fa-brands fa-whatsapp me-1"></i> WhatsApp</a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $current_url ?>" target="_blank" class="btn btn-sm btn-primary me-2"><i class="fa-brands fa-facebook me-1"></i> Facebook</a>
                <a href="https://twitter.com/intent/tweet?text=<?= $share_title ?>&url=<?= $current_url ?>" target="_blank" class="btn btn-sm btn-info text-white"><i class="fa-brands fa-twitter me-1"></i> Twitter</a>
            </div>

            <!-- Rating Section -->
            <div class="card border-0 shadow-sm mb-5">
                <div class="card-body text-center py-4">
                    <h5 class="fw-bold mb-3">Rate this Article</h5>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form method="POST" class="d-inline-block">
                            <div class="rating-stars mb-3">
                                <?php for($i=5; $i>=1; $i--): ?>
                                    <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" <?= ($user_rating == $i) ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <label for="star<?= $i ?>" title="<?= $i ?> stars"><i class="fa-solid fa-star fs-3"></i></label>
                                <?php endfor; ?>
                            </div>
                            <?php if ($user_rating): ?>
                                <p class="text-success small mb-0"><i class="fa-solid fa-circle-check me-1"></i> You rated this <?= $user_rating ?> stars</p>
                            <?php endif; ?>
                        </form>
                        <style>
                            .rating-stars { display: flex; flex-direction: row-reverse; justify-content: center; }
                            .rating-stars input { display: none; }
                            .rating-stars label { color: #ddd; cursor: pointer; padding: 0 5px; transition: color 0.2s; }
                            .rating-stars input:checked ~ label,
                            .rating-stars label:hover,
                            .rating-stars label:hover ~ label { color: #f39c12; }
                        </style>
                    <?php else: ?>
                        <p class="text-muted"><a href="login.php" class="text-warning fw-bold text-decoration-none">Login</a> to rate this article.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="comments-section">
                <h4 class="fw-bold mb-4 border-bottom pb-2">Comments (<?= count($comments) ?>)</h4>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="POST" class="mb-5">
                        <div class="mb-3">
                            <textarea name="comment" class="form-control" rows="3" placeholder="Leave a meaningful comment..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning fw-bold px-4">Post Comment</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning mb-5">
                        <i class="fa-solid fa-lock me-2"></i>Please <a href="login.php" class="alert-link">Login</a> to leave a comment.
                    </div>
                <?php endif; ?>

                <?php if (count($comments) > 0): ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($comments as $comment): ?>
                            <div class="card border-0 bg-light">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <strong class="text-dark"><i class="fa-solid fa-circle-user text-secondary me-2"></i> <?= htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']) ?></strong>
                                        <small class="text-muted"><?= date('d M Y, h:i A', strtotime($comment['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-0 text-dark"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4 bg-light rounded">No comments yet. Be the first to start the discussion!</p>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
