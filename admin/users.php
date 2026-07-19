<?php
$page_title = "Manage Members";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

if (isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($tmp_name, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ","); // Skip header
            $imported = 0;
            $updated = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($data) >= 8) {
                    $id = $data[0] ?? '';
                    $first = $data[1] ?? '';
                    $last = $data[2] ?? '';
                    $email = $data[3] ?? '';
                    $phone = $data[4] ?? '';
                    $gender = $data[5] ?? 'male';
                    $dob = $data[6] ?? '';
                    $status = $data[7] ?? 'active';

                    if (empty($email)) continue; // Email is required

                    $check = null;
                    if (!empty($id)) {
                        $check = $pdo->prepare("SELECT id FROM users WHERE id = ? OR email = ?");
                        $check->execute([$id, $email]);
                    } else {
                        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $check->execute([$email]);
                    }
                    
                    if ($check->rowCount() > 0) {
                        // Update
                        $existing = $check->fetchColumn();
                        $pdo->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, gender=?, dob=?, status=? WHERE id=?")->execute([$first, $last, $phone, $gender, $dob, $status, $existing]);
                        $updated++;
                    } else {
                        // Insert
                        $dummy_pass = password_hash('vishwkarma@123', PASSWORD_DEFAULT);
                        if (!empty($id)) {
                            $pdo->prepare("INSERT INTO users (id, first_name, last_name, email, phone, gender, dob, status, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([$id, $first, $last, $email, $phone, $gender, $dob, $status, $dummy_pass]);
                        } else {
                            $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, gender, dob, status, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([$first, $last, $email, $phone, $gender, $dob, $status, $dummy_pass]);
                        }
                        $imported++;
                    }
                }
            }
            fclose($handle);
            setFlashMessage('success', "Import complete: $imported new users added, $updated updated.");
        }
    } else {
        setFlashMessage('error', 'Please upload a valid CSV file.');
    }
    header("Location: users.php");
    exit;
}


if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    if ($action === 'block') {
        $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?")->execute([$id]);
        setFlashMessage('success', 'User has been blocked.');
    } elseif ($action === 'unblock') {
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$id]);
        setFlashMessage('success', 'User has been unblocked.');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        setFlashMessage('success', 'User has been permanently deleted.');
    } elseif ($action === 'login_as') {
        // Secure Impersonation
        $_SESSION['user_id'] = $id;
        $_SESSION['admin_impersonating'] = true;
        $_SESSION['original_admin_id'] = $_SESSION['admin_id'];
        
        $stmt = $pdo->prepare("SELECT role_id, first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            
            setFlashMessage('success', "You are now securely logged in as " . $user['first_name'] . ".");
        }
        
        header("Location: ../dashboard.php");
        exit;
    } elseif ($action === 'edit_user') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user_id = (int)$_POST['user_id'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $password = $_POST['password'];
            
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET email = ?, phone = ?, password = ? WHERE id = ?")->execute([$email, $phone, $hashed, $user_id]);
                setFlashMessage('success', 'User email, phone, and password updated successfully.');
            } else {
                $pdo->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?")->execute([$email, $phone, $user_id]);
                setFlashMessage('success', 'User email and phone updated successfully.');
            }
            header("Location: users.php");
            exit;
        }
    } elseif ($action === 'export_csv') {
        // Export Users to CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=users_backup_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Gender', 'DOB', 'Status', 'Joined Date'));
        
        $export_users = $pdo->query("SELECT id, first_name, last_name, email, phone, gender, dob, status, created_at FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($export_users as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
    header("Location: users.php");
    exit;
}

$search = trim($_GET['search'] ?? '');
$search_query = "";
$params = [];

if (!empty($search)) {
    $search_query = " WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? ";
    $term = "%" . $search . "%";
    $params = [$term, $term, $term, $term];
}

$stmt = $pdo->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM user_gallery ug WHERE ug.user_id = u.id) as image_count 
    FROM users u 
    $search_query
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <div class="d-flex align-items-center">
            <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
            <h3 class="mb-0 text-dark"><i class="fa-solid fa-users text-primary me-2"></i> Manage Registered Members</h3>
        </div>
        <div>
            <button type="button" class="btn btn-info text-white me-2" data-bs-toggle="modal" data-bs-target="#importModal" title="Upload Backup from Drive"><i class="fa-solid fa-upload"></i> Import Backup</button>
            <a href="users.php?action=export_csv" class="btn btn-success me-2" title="Download Backup to Drive"><i class="fa-solid fa-file-csv"></i> Export Backup</a>
            <a href="user-add.php" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add User</a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="users.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-upload me-2"></i> Import Users (CSV)</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">Upload the CSV file you previously exported. Existing emails will be updated, new emails will be inserted with a default password (<code>vishwkarma@123</code>).</p>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select CSV File</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="import_csv" class="btn btn-info text-white fw-bold">Start Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="card border-0 shadow-sm p-4 mb-4 bg-light">
        <form method="GET" action="users.php" class="row gx-2 gy-2 align-items-center">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, or phone..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                <?php if(!empty($search)): ?>
                    <a href="users.php" class="btn btn-secondary" title="Clear Search"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email / Phone</th>
                        <th>Status</th>
                        <th>Joined On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td>#<?= $user['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?><br><small class="text-muted"><?= htmlspecialchars($user['phone']) ?></small></td>
                        <td>
                            <?php if($user['status'] == 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Blocked</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-warning mb-1" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $user['id'] ?>"><i class="fa-solid fa-edit"></i> Edit</button>
                            <a href="users.php?action=login_as&id=<?= $user['id'] ?>" class="btn btn-sm btn-primary mb-1" onclick="return confirm('Login as this user? You will be redirected to their frontend dashboard.')"><i class="fa-solid fa-right-to-bracket"></i> Login As</a>
                            <?php if($user['image_count'] > 0): ?>
                                <a href="gallery.php#user-<?= $user['id'] ?>" class="btn btn-sm btn-info text-white mb-1"><i class="fa-solid fa-images"></i> Images (<?= $user['image_count'] ?>)</a>
                            <?php endif; ?>
                            <?php if($user['status'] == 'active'): ?>
                                <a href="users.php?action=block&id=<?= $user['id'] ?>" class="btn btn-sm btn-warning" onclick="return confirm('Block this user?')"><i class="fa-solid fa-ban"></i> Block</a>
                            <?php else: ?>
                                <a href="users.php?action=unblock&id=<?= $user['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Unblock this user?')"><i class="fa-solid fa-check"></i> Unblock</a>
                            <?php endif; ?>
                            <a href="users.php?action=delete&id=<?= $user['id'] ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('WARNING: This will permanently delete the user and all their data (Matrimony, Business, etc). Continue?')"><i class="fa-solid fa-trash"></i> Delete</a>
                            <button type="button" class="btn btn-sm btn-secondary mb-1" data-bs-toggle="modal" data-bs-target="#declarationModal<?= $user['id'] ?>"><i class="fa-solid fa-file-signature"></i> Declaration</button>
                        </td>
                    </tr>
                    
                    <!-- Edit User Modal -->
                    <div class="modal fade" id="editUserModal<?= $user['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form action="users.php?action=edit_user" method="POST">
                              <div class="modal-header bg-warning">
                                <h5 class="modal-title">Edit User Credentials - <?= htmlspecialchars($user['first_name']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                              </div>
                              <div class="modal-body">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Email (Login ID)</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Phone (Alternative Login ID)</label>
                                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">New Password</label>
                                    <input type="text" name="password" class="form-control" placeholder="Leave blank to keep current password">
                                </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-warning fw-bold">Save Changes</button>
                              </div>
                          </form>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Declaration Modal -->
                    <div class="modal fade" id="declarationModal<?= $user['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title">Declaration Details - <?= htmlspecialchars($user['first_name']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body">
                            <?php if (!empty($user['declaration_accepted'])): ?>
                                <ul class="list-group list-group-flush text-start">
                                    <li class="list-group-item"><strong>Status:</strong> <span class="badge bg-success">Accepted</span></li>
                                    <li class="list-group-item"><strong>Date:</strong> <?= date('d M Y', strtotime($user['declaration_datetime'])) ?></li>
                                    <li class="list-group-item"><strong>Time:</strong> <?= date('h:i A', strtotime($user['declaration_datetime'])) ?></li>
                                    <li class="list-group-item"><strong>Day:</strong> <?= date('l', strtotime($user['declaration_datetime'])) ?></li>
                                    <li class="list-group-item"><strong>IP Address (Location):</strong> <?= htmlspecialchars($user['declaration_ip']) ?></li>
                                </ul>
                                <div class="alert alert-info mt-3 small mb-0">
                                    <i class="fa-solid fa-check-circle me-1"></i> User has agreed to the Terms & Conditions and accepted the registration declaration.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0">
                                    No declaration record found (User may have registered before this feature was added).
                                </div>
                            <?php endif; ?>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                          </div>
                        </div>
                      </div>
                    </div>

                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php require_once 'includes/footer.php'; ?>
