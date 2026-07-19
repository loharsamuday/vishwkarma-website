<?php
$page_title = "Manage Matrimony";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM matrimony_profiles WHERE id = ?")->execute([$id]);
        setFlashMessage('success', 'Matrimony profile deleted successfully.');
    }
    header("Location: matrimony.php");
    exit;
}

$profiles = $pdo->query("
    SELECT m.id, m.user_id, m.profile_for, m.gender, m.is_premium, m.created_at, u.first_name, u.last_name, u.status 
    FROM matrimony_profiles m 
    JOIN users u ON m.user_id = u.id 
    ORDER BY m.created_at DESC
")->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <h3 class="mb-0 text-dark"><i class="fa-solid fa-heart text-danger me-2"></i> Manage Matrimony Profiles</h3>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="card border-0 shadow-sm p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Profile ID</th>
                        <th>Name</th>
                        <th>Profile For</th>
                        <th>Gender</th>
                        <th>Membership</th>
                        <th>User Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($profiles as $ap): ?>
                    <tr>
                        <td>VISH-<?= str_pad($ap['id'], 5, '0', STR_PAD_LEFT) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($ap['first_name'] . ' ' . $ap['last_name']) ?></td>
                        <td><?= htmlspecialchars($ap['profile_for']) ?></td>
                        <td><?= htmlspecialchars($ap['gender']) ?></td>
                        <td>
                            <?php if($ap['is_premium']): ?>
                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-crown"></i> Premium</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Free</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($ap['status'] == 'banned'): ?>
                                <span class="badge bg-danger">Blocked</span>
                            <?php else: ?>
                                <span class="badge bg-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="../profile.php?id=<?= $ap['id'] ?>" target="_blank" class="btn btn-sm btn-info text-white"><i class="fa-solid fa-eye"></i> View</a>
                            <a href="matrimony.php?action=delete&id=<?= $ap['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to permanently delete this matrimony profile?')"><i class="fa-solid fa-trash"></i> Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($profiles)): ?>
                        <tr><td colspan="7" class="text-center text-muted">No matrimony profiles found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php require_once 'includes/footer.php'; ?>
