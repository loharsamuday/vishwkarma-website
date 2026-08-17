<?php
// admin/vocabulary.php
session_start();
$page_title = 'Manage Vocabulary';
include 'includes/header.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->query("SELECT COUNT(*) FROM vocabulary");
$total_vocab = $stmt->fetchColumn();
$total_pages = ceil($total_vocab / $per_page);

$stmt = $pdo->prepare("
    SELECT v.*, s.title as story_title 
    FROM vocabulary v 
    LEFT JOIN stories s ON v.story_id = s.id 
    ORDER BY v.created_at DESC 
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$vocabularies = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Vocabulary</h1>
    <a href="add-vocabulary.php" class="btn btn-success"><i class="fas fa-plus me-1"></i> Add Word</a>
</div>

<?php if(isset($_GET['msg']) && $_GET['msg'] == 'added'): ?>
    <div class="alert alert-success">Word added successfully.</div>
<?php endif; ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
    <div class="alert alert-success">Word updated successfully.</div>
<?php endif; ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
    <div class="alert alert-success">Word deleted successfully.</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Word</th>
                        <th>Hindi Meaning</th>
                        <th>Story</th>
                        <th>Part of Speech</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($vocabularies) > 0): ?>
                        <?php foreach ($vocabularies as $vocab): ?>
                        <tr>
                            <td><strong><?= escape($vocab['word']) ?></strong></td>
                            <td><?= escape($vocab['hindi_meaning']) ?></td>
                            <td>
                                <?php if($vocab['story_id']): ?>
                                    <a href="edit-story.php?id=<?= $vocab['story_id'] ?>"><?= escape($vocab['story_title']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">No Story</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary"><?= escape($vocab['part_of_speech']) ?></span></td>
                            <td class="text-end">
                                <a href="edit-vocabulary.php?id=<?= $vocab['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete-vocabulary.php?id=<?= $vocab['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this vocabulary word?');" title="Delete"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4">No vocabulary found.</td></tr>
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
