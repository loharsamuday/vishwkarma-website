<?php
// admin/index.php
session_start();
$page_title = 'Dashboard';
include 'includes/header.php';

// Get statistics
$stats = [];

// Total Stories
$stmt = $pdo->query("SELECT COUNT(*) FROM stories");
$stats['total_stories'] = $stmt->fetchColumn();

// Published Stories
$stmt = $pdo->query("SELECT COUNT(*) FROM stories WHERE status = 'Published'");
$stats['published_stories'] = $stmt->fetchColumn();

// Draft Stories
$stats['draft_stories'] = $stats['total_stories'] - $stats['published_stories'];

// Total Vocabulary
$stmt = $pdo->query("SELECT COUNT(*) FROM vocabulary");
$stats['total_vocabulary'] = $stmt->fetchColumn();

// Pending User Stories
$stmt = $pdo->query("SELECT COUNT(*) FROM user_stories WHERE status = 'Pending'");
$stats['pending_user_stories'] = $stmt->fetchColumn();

// Approved User Stories
$stmt = $pdo->query("SELECT COUNT(*) FROM user_stories WHERE status = 'Approved'");
$stats['approved_user_stories'] = $stmt->fetchColumn();

// Rejected User Stories
$stmt = $pdo->query("SELECT COUNT(*) FROM user_stories WHERE status = 'Rejected'");
$stats['rejected_user_stories'] = $stmt->fetchColumn();

?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-uppercase text-white-50">Total Stories</h6>
                        <h2 class="mb-0 fw-bold"><?= $stats['total_stories'] ?></h2>
                    </div>
                    <i class="fas fa-book fa-3x text-white-50"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <small><?= $stats['published_stories'] ?> Published, <?= $stats['draft_stories'] ?> Drafts</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-white bg-success h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-uppercase text-white-50">Vocabulary Words</h6>
                        <h2 class="mb-0 fw-bold"><?= $stats['total_vocabulary'] ?></h2>
                    </div>
                    <i class="fas fa-language fa-3x text-white-50"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <a href="vocabulary.php" class="text-white text-decoration-none small">Manage Vocabulary <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-white bg-warning h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-uppercase text-white-50">Pending Stories</h6>
                        <h2 class="mb-0 fw-bold"><?= $stats['pending_user_stories'] ?></h2>
                    </div>
                    <i class="fas fa-users fa-3x text-white-50"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <a href="user-stories.php" class="text-white text-decoration-none small">Review Submissions <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-white bg-info h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-uppercase text-white-50">User Stories Stats</h6>
                        <h2 class="mb-0 fw-bold"><?= $stats['approved_user_stories'] ?></h2>
                    </div>
                    <i class="fas fa-chart-pie fa-3x text-white-50"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <small><?= $stats['approved_user_stories'] ?> Approved, <?= $stats['rejected_user_stories'] ?> Rejected</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold">
                Recent Stories
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("SELECT title, status, created_at FROM stories ORDER BY created_at DESC LIMIT 5");
                            while ($row = $stmt->fetch()):
                            ?>
                            <tr>
                                <td><?= escape($row['title']) ?></td>
                                <td>
                                    <?php if($row['status'] == 'Published'): ?>
                                        <span class="badge bg-success">Published</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($row['created_at']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-center">
                <a href="stories.php" class="text-decoration-none">View All Stories</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold">
                Recent User Submissions
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Author</th>
                                <th>Title</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("SELECT author_name, title, status FROM user_stories ORDER BY created_at DESC LIMIT 5");
                            while ($row = $stmt->fetch()):
                            ?>
                            <tr>
                                <td><?= escape($row['author_name']) ?></td>
                                <td><?= escape($row['title']) ?></td>
                                <td>
                                    <?php if($row['status'] == 'Pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif($row['status'] == 'Approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Rejected</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-center">
                <a href="user-stories.php" class="text-decoration-none">View All Submissions</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
