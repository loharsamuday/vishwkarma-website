<?php
// profile.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=login_required");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $pdo->prepare("SELECT name, email, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    // Session exists but user deleted?
    session_destroy();
    header("Location: index.php");
    exit();
}

// Fetch user's submitted stories
$stmt = $pdo->prepare("
    SELECT us.*, c.name as category_name 
    FROM user_stories us 
    LEFT JOIN categories c ON us.category_id = c.id 
    WHERE us.user_id = ? 
    ORDER BY us.created_at DESC
");
$stmt->execute([$user_id]);
$my_stories = $stmt->fetchAll();

$page_title = 'My Account';
include 'includes/header.php';
?>

<div class="bg-light py-4 mb-5 border-bottom">
    <div class="container">
        <h1 class="fw-bold text-primary-custom mb-0">My Account</h1>
    </div>
</div>

<div class="container mb-5 pb-5">
    <div class="row">
        <!-- Profile Info -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4 text-center">
                    <div class="bg-primary-custom text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <h4 class="fw-bold mb-1"><?= escape($user['name']) ?></h4>
                    <p class="text-muted mb-3"><?= escape($user['email']) ?></p>
                    <hr>
                    <p class="small text-muted mb-0">Member since: <?= formatDate($user['created_at']) ?></p>
                    <div class="d-grid mt-4">
                        <a href="logout.php" class="btn btn-outline-danger">Log Out</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- User Stories -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                    <h4 class="fw-bold text-primary-custom mb-0">My Submitted Stories</h4>
                    <a href="write-story.php" class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i>Write New</a>
                </div>
                <div class="card-body p-4">
                    <?php if (count($my_stories) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_stories as $story): ?>
                                    <tr>
                                        <td><strong><?= escape($story['title']) ?></strong></td>
                                        <td><?= escape($story['category_name']) ?></td>
                                        <td>
                                            <?php if($story['status'] == 'Pending'): ?>
                                                <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pending</span>
                                            <?php elseif($story['status'] == 'Approved'): ?>
                                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Approved</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= formatDate($story['created_at']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-book-open fa-3x text-muted mb-3 opacity-25"></i>
                            <h5 class="text-muted">You haven't submitted any stories yet.</h5>
                            <p class="text-muted">Practice your English by writing a story!</p>
                            <a href="write-story.php" class="btn btn-primary bg-primary-custom mt-2">Start Writing</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
