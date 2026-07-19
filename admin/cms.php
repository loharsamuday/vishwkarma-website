<?php
$page_title = "Manage CMS Pages";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$pages = $pdo->query("SELECT * FROM pages ORDER BY title ASC")->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <h3 class="mb-0 text-dark"><i class="fa-solid fa-file-alt text-primary me-2"></i> Content Management System (CMS)</h3>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="card border-0 shadow-sm p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Page/Module Title</th>
                        <th>Slug</th>
                        <th>Last Updated</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pages as $p): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($p['title']) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($p['slug']) ?></span></td>
                        <td><?= date('d M Y, h:i A', strtotime($p['updated_at'])) ?></td>
                        <td>
                            <a href="cms-edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning fw-bold"><i class="fa-solid fa-edit"></i> Edit Content</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php require_once 'includes/footer.php'; ?>
