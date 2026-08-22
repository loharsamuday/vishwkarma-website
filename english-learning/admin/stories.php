<?php
// admin/stories.php
session_start();
$page_title = 'Manage Stories';
include 'includes/header.php';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total stories
$stmt = $pdo->query("SELECT COUNT(*) FROM stories");
$total_stories = $stmt->fetchColumn();
$total_pages = ceil($total_stories / $per_page);

// Fetch stories
$stmt = $pdo->prepare("
    SELECT s.id, s.title, s.difficulty, s.status, s.created_at, c.name as category_name 
    FROM stories s 
    LEFT JOIN categories c ON s.category_id = c.id 
    ORDER BY s.created_at DESC 
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$stories = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Stories</h1>
    <div class="btn-toolbar mb-2 mb-md-0 gap-2">
        <a href="add-story.php" class="btn btn-success"><i class="fas fa-plus me-1"></i> Add New Story</a>
        <a href="import-stories.php" class="btn btn-outline-success"><i class="fas fa-file-csv"></i> Bulk Import Stories</a>
    </div>
</div>

<?php if(isset($_GET['msg']) && $_GET['msg'] == 'added'): ?>
    <div class="alert alert-success">Story added successfully.</div>
<?php endif; ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
    <div class="alert alert-success">Story updated successfully.</div>
<?php endif; ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
    <div class="alert alert-success">Story deleted successfully.</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Difficulty</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($stories) > 0): ?>
                        <?php foreach ($stories as $story): ?>
                        <tr>
                            <td><strong><?= escape($story['title']) ?></strong></td>
                            <td><?= $story['category_name'] ? escape($story['category_name']) : 'Uncategorized' ?></td>
                            <td>
                                <?php if($story['difficulty'] == 'Beginner'): ?>
                                    <span class="badge badge-beginner">Beginner</span>
                                <?php elseif($story['difficulty'] == 'Intermediate'): ?>
                                    <span class="badge badge-intermediate">Intermediate</span>
                                <?php else: ?>
                                    <span class="badge badge-advanced">Advanced</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($story['status'] == 'Published'): ?>
                                    <span class="badge bg-success">Published</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDate($story['created_at']) ?></td>
                            <td class="text-end">
                                <a href="../story.php?id=<?= $story['id'] ?>" target="_blank" class="btn btn-sm btn-outline-info" title="View"><i class="fas fa-eye"></i></a>
                                <a href="edit-story.php?id=<?= $story['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete-story.php?id=<?= $story['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this story?');" title="Delete"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4">No stories found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($total_pages > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
