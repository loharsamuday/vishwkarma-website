<?php
$page_title = "Manage Admin Members";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    if ($delete_id == $_SESSION['admin_id']) {
        $error = "You cannot delete your own account.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            $success = "Admin user deleted successfully.";
        } else {
            $error = "Failed to delete admin user.";
        }
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    if (isset($_POST['add_admin'])) {
        $password = $_POST['password'];
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if username or email exists
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = "Username or Email already exists.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, email, role) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $hashed_password, $email, $role])) {
                $success = "New admin member added successfully.";
            } else {
                $error = "Failed to add admin member.";
            }
        }
    } elseif (isset($_POST['edit_admin'])) {
        $admin_id = $_POST['admin_id'];
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $admin_id]);
        if ($stmt->fetch()) {
            $error = "Username or Email already exists.";
        } else {
            $update_sql = "UPDATE admin_users SET username = ?, email = ?, role = ? WHERE id = ?";
            $params = [$username, $email, $role, $admin_id];
            
            if (!empty($_POST['password'])) {
                $update_sql = "UPDATE admin_users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?";
                $params = [$username, $email, $role, password_hash($_POST['password'], PASSWORD_DEFAULT), $admin_id];
            }
            
            $stmt = $pdo->prepare($update_sql);
            if ($stmt->execute($params)) {
                $success = "Admin details updated successfully.";
            } else {
                $error = "Failed to update admin details.";
            }
        }
    }
}

// Fetch all admins
$stmt = $pdo->query("SELECT * FROM admin_users ORDER BY id DESC");
$admins = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="main-content" id="mainContent">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">Admin Members</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active">Admin Members</li>
                </ol>
            </nav>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
            <i class="fa-solid fa-plus me-1"></i> Add Admin
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($admins as $a): ?>
                        <tr>
                            <td class="ps-4">#<?= $a['id'] ?></td>
                            <td><span class="fw-bold"><?= htmlspecialchars($a['username']) ?></span></td>
                            <td><?= htmlspecialchars($a['email']) ?></td>
                            <td>
                                <span class="badge <?= $a['role'] == 'admin' ? 'bg-primary' : 'bg-secondary' ?>">
                                    <?= htmlspecialchars(ucfirst($a['role'])) ?>
                                </span>
                            </td>
                            <td><?= date('d M Y, h:i A', strtotime($a['created_at'])) ?></td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editAdminModal<?= $a['id'] ?>">
                                    <i class="fa-solid fa-edit"></i>
                                </button>
                                <?php if($a['id'] != $_SESSION['admin_id']): ?>
                                    <a href="?delete=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this admin?');">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" disabled title="You cannot delete yourself"><i class="fa-solid fa-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editAdminModal<?= $a['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Admin Member</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Username</label>
                                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($a['username']) ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($a['email']) ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Role</label>
                                                <select name="role" class="form-select">
                                                    <option value="admin" <?= $a['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                                    <option value="moderator" <?= $a['role'] == 'moderator' ? 'selected' : '' ?>>Moderator</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">New Password</label>
                                                <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="edit_admin" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if(empty($admins)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No admin members found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="admin">Admin</option>
                            <option value="moderator">Moderator</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_admin" class="btn btn-primary">Add Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
