<?php
// admin/user-stories.php
session_start();
$page_title = 'Review User Stories';
include 'includes/header.php';

$stmt = $pdo->query("
    SELECT us.*, c.name as category_name 
    FROM user_stories us 
    LEFT JOIN categories c ON us.category_id = c.id 
    ORDER BY us.created_at DESC
");
$user_stories = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Review User Submissions</h1>
</div>

<?php if(isset($_GET['msg']) && $_GET['msg'] == 'processed'): ?>
    <div class="alert alert-success">Story has been processed successfully.</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Author</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($user_stories) > 0): ?>
                        <?php foreach ($user_stories as $story): ?>
                        <tr>
                            <td>
                                <strong><?= escape($story['author_name']) ?></strong><br>
                                <small class="text-muted"><?= escape($story['email']) ?></small>
                            </td>
                            <td><?= escape($story['title']) ?></td>
                            <td><?= escape($story['category_name']) ?></td>
                            <td>
                                <?php if($story['status'] == 'Pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php elseif($story['status'] == 'Approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDate($story['created_at']) ?></td>
                            <td class="text-end">
                                <a href="view-user-story.php?id=<?= $story['id'] ?>" class="btn btn-sm btn-primary">Review</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4">No user submissions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
