<?php
// admin/manage-admins.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Only super admin can access this page
$stmt = $pdo->prepare("SELECT role FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id'] ?? 0]);
$current_admin = $stmt->fetch();

if (!$current_admin || $current_admin['role'] !== 'super_admin') {
    die("Access Denied: You do not have permission to view this page.");
}

$error = '';
$success = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id !== $_SESSION['admin_id']) {
        $pdo->prepare("DELETE FROM admins WHERE id = ? AND role = 'guest_admin'")->execute([$id]);
        $success = "Guest admin deleted successfully.";
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $role = 'guest_admin';
    $permissions = isset($_POST['permissions']) ? json_encode($_POST['permissions']) : json_encode([]);
    $id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;

    if (empty($username)) {
        $error = "Username is required.";
    } else {
        if ($id > 0) {
            // Edit existing
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admins SET username = ?, password = ?, permissions = ? WHERE id = ? AND role = 'guest_admin'");
                $stmt->execute([$username, $password, $permissions, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET username = ?, permissions = ? WHERE id = ? AND role = 'guest_admin'");
                $stmt->execute([$username, $permissions, $id]);
            }
            $success = "Guest admin updated successfully.";
        } else {
            // Add new
            if (empty($_POST['password'])) {
                $error = "Password is required for new admin.";
            } else {
                // Check if username exists
                $check = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
                $check->execute([$username]);
                if ($check->rowCount() > 0) {
                    $error = "Username already exists.";
                } else {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO admins (username, password, role, permissions) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $password, $role, $permissions]);
                    $success = "Guest admin created successfully.";
                }
            }
        }
    }
}

$admins = $pdo->query("SELECT * FROM admins WHERE role = 'guest_admin' ORDER BY id DESC")->fetchAll();

$page_title = 'Manage Guest Admins';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Guest Admins</h1>
    <button class="btn btn-primary bg-primary-custom" data-bs-toggle="modal" data-bs-target="#addAdminModal">
        <i class="fas fa-plus me-1"></i> Add Guest Admin
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= escape($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= escape($success) ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Permissions</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($admins) > 0): ?>
                        <?php foreach($admins as $admin): ?>
                            <?php $perms = json_decode($admin['permissions'] ?? '[]', true); ?>
                            <tr>
                                <td><i class="fas fa-user text-muted me-2"></i><strong><?= escape($admin['username']) ?></strong></td>
                                <td><span class="badge bg-info text-dark">Guest Admin</span></td>
                                <td>
                                    <?php if(empty($perms)): ?>
                                        <span class="text-muted small">No permissions</span>
                                    <?php else: ?>
                                        <?php foreach($perms as $p): ?>
                                            <span class="badge bg-secondary mb-1"><?= escape(ucfirst($p)) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($admin['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary edit-btn" 
                                            data-id="<?= $admin['id'] ?>"
                                            data-username="<?= escape($admin['username']) ?>"
                                            data-perms='<?= $admin['permissions'] ?>'
                                            data-bs-toggle="modal" data-bs-target="#addAdminModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?= $admin['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this guest admin?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4">No guest admins found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for Add/Edit -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" id="adminForm">
                <input type="hidden" name="admin_id" id="admin_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Guest Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" id="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" id="password">
                        <div class="form-text">Leave blank when editing to keep current password.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Permissions (Check what they can access)</label>
                        <div class="form-check">
                            <input class="form-check-input perm-check" type="checkbox" name="permissions[]" value="stories" id="perm_stories">
                            <label class="form-check-label" for="perm_stories">Manage Stories (Add, Edit, Review User Stories)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input perm-check" type="checkbox" name="permissions[]" value="users" id="perm_users">
                            <label class="form-check-label" for="perm_users">Manage Users (View, Block, Delete Users)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input perm-check" type="checkbox" name="permissions[]" value="newsletter" id="perm_newsletter">
                            <label class="form-check-label" for="perm_newsletter">Newsletter (View Subscribers, Send Updates)</label>
                        </div>
                    </div>
                    <div class="alert alert-warning small">Note: Guest Admins can NEVER access Settings, SMTP Settings, or Manage Admins.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary bg-primary-custom">Save Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBtns = document.querySelectorAll('.edit-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modalTitle').textContent = 'Edit Guest Admin';
            document.getElementById('admin_id').value = this.dataset.id;
            document.getElementById('username').value = this.dataset.username;
            
            // Clear all checks
            document.querySelectorAll('.perm-check').forEach(chk => chk.checked = false);
            
            // Set checks
            try {
                const perms = JSON.parse(this.dataset.perms || '[]');
                perms.forEach(p => {
                    const chk = document.getElementById('perm_' + p);
                    if (chk) chk.checked = true;
                });
            } catch(e) {}
        });
    });
    
    document.getElementById('addAdminModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('modalTitle').textContent = 'Add Guest Admin';
        document.getElementById('adminForm').reset();
        document.getElementById('admin_id').value = '0';
    });
});
</script>

<?php include 'includes/footer.php'; ?>
