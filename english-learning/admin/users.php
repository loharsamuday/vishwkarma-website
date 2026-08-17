<?php
// admin/users.php
session_start();
$page_title = 'Manage Registered Users';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
$success = '';
$error = '';

if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'])) {
    $action = $_POST['action'];
    $user_id = (int)$_POST['user_id'];
    
    try {
        if ($action === 'block') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'blocked' WHERE id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['success_msg'] = "User blocked successfully.";
        } elseif ($action === 'unblock') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['success_msg'] = "User unblocked successfully.";
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['success_msg'] = "User deleted successfully.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error processing action: " . $e->getMessage();
    }
    header("Location: users.php");
    exit();
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

$stmt = $pdo->prepare("SELECT id, name, email, status, created_at FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Registered Users</h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= escape($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= escape($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Registered Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><strong><?= escape($user['name']) ?></strong></td>
                            <td><?= escape($user['email']) ?></td>
                            <td><?= formatDate($user['created_at']) ?></td>
                            <td>
                                <?php if($user['status'] == 'blocked'): ?>
                                    <span class="badge bg-danger">Blocked</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to change this user\'s status?');">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <?php if($user['status'] == 'blocked'): ?>
                                            <input type="hidden" name="action" value="unblock">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Unblock User"><i class="fas fa-unlock"></i> Unblock</button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="block">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Block User"><i class="fas fa-ban"></i> Block</button>
                                        <?php endif; ?>
                                    </form>
                                    <form method="POST" action="" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this user?');">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete User"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4">No registered users found.</td></tr>
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
