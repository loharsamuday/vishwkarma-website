<?php
$page_title = "Manage Gallery";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT image_path FROM user_gallery WHERE id = ?");
    $stmt->execute([$id]);
    $image = $stmt->fetch();
    
    if ($image) {
        $path = '../uploads/gallery/' . $image['image_path'];
        if (file_exists($path)) {
            unlink($path);
        }
        $pdo->prepare("DELETE FROM user_gallery WHERE id = ?")->execute([$id]);
        setFlashMessage('success', 'Image deleted successfully.');
    } else {
        setFlashMessage('danger', 'Image not found.');
    }
    header("Location: gallery.php");
    exit;
}

// Fetch all images ordered by user
$stmt = $pdo->query("
    SELECT ug.*, u.first_name, u.last_name, u.email 
    FROM user_gallery ug 
    JOIN users u ON ug.user_id = u.id 
    ORDER BY u.first_name ASC, u.last_name ASC, ug.created_at DESC
");
$images = $stmt->fetchAll();

// Group by user
$grouped_images = [];
foreach ($images as $img) {
    $user_key = $img['first_name'] . ' ' . $img['last_name'] . ' (' . $img['email'] . ')';
    if (!isset($grouped_images[$user_key])) {
        $grouped_images[$user_key] = [
            'user_id' => $img['user_id'],
            'images' => []
        ];
    }
    $grouped_images[$user_key]['images'][] = $img;
}
?>
<?php require_once 'includes/header.php'; ?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <div class="d-flex align-items-center">
            <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
            <h3 class="mb-0 text-dark"><i class="fa-solid fa-images text-primary me-2"></i> Manage Gallery</h3>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>

    <?php if (empty($grouped_images)): ?>
        <div class="alert alert-info">No images have been uploaded yet.</div>
    <?php else: ?>
        <?php foreach ($grouped_images as $user_name => $user_data): ?>
            <div class="card border-0 shadow-sm mb-4" id="user-<?= $user_data['user_id'] ?>">
                <div class="card-header bg-light fw-bold text-dark d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-user text-secondary me-2"></i> <?= htmlspecialchars($user_name) ?></span>
                    <span class="badge bg-primary rounded-pill"><?= count($user_data['images']) ?> images</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($user_data['images'] as $img): ?>
                            <div class="col-md-2 col-sm-4 col-6">
                                <div class="position-relative border rounded p-2 bg-white">
                                    <a href="../uploads/gallery/<?= htmlspecialchars($img['image_path']) ?>" target="_blank">
                                        <img src="../uploads/gallery/<?= htmlspecialchars($img['image_path']) ?>" class="img-fluid rounded w-100" style="height: 120px; object-fit: cover;" alt="User Upload">
                                    </a>
                                    <div class="mt-2 text-center">
                                        <small class="text-muted d-block mb-2" style="font-size: 0.75rem;"><?= date('d M Y', strtotime($img['created_at'])) ?></small>
                                        <a href="gallery.php?action=delete&id=<?= $img['id'] ?>" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Are you sure you want to delete this image?');"><i class="fa-solid fa-trash me-1"></i> Delete</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
