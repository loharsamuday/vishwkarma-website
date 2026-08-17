<?php
// admin/subscribers.php
session_start();
$page_title = 'Newsletter Subscribers';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Handle deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM subscribers WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: subscribers.php?msg=deleted");
    exit();
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->query("SELECT COUNT(*) FROM subscribers");
$total_subscribers = $stmt->fetchColumn();
$total_pages = ceil($total_subscribers / $per_page);

$stmt = $pdo->prepare("SELECT * FROM subscribers ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$subscribers = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Newsletter Subscribers</h1>
    <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-1"></i> Print / Export</button>
</div>

<?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
    <div class="alert alert-success">Subscriber removed successfully.</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Email Address</th>
                        <th>Subscribed Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($subscribers) > 0): ?>
                        <?php foreach ($subscribers as $sub): ?>
                        <tr>
                            <td><?= $sub['id'] ?></td>
                            <td><strong><?= escape($sub['email']) ?></strong></td>
                            <td><?= formatDate($sub['created_at']) ?></td>
                            <td class="text-end">
                                <a href="subscribers.php?delete=<?= $sub['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this subscriber?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-4">No subscribers found.</td></tr>
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
