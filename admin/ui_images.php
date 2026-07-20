<?php
$page_title = "Manage UI Images";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_image') {
    $image_id = (int)$_POST['image_id'];
    $external_url = trim($_POST['external_url']);
    $upload_path = null;

    // Handle File Upload
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/ui/';
        
        // Ensure directory exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($_FILES['image_file']['name']));
        $target_file = $upload_dir . $file_name;

        // Check if it's an actual image
        $check = getimagesize($_FILES['image_file']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_file)) {
                $upload_path = $file_name;
            } else {
                $_SESSION['error'] = "Sorry, there was an error uploading your file.";
            }
        } else {
            $_SESSION['error'] = "File is not an image.";
        }
    }

    if (!isset($_SESSION['error'])) {
        try {
            if ($upload_path) {
                // If a new file is uploaded, update upload_path and clear external_url
                $stmt = $pdo->prepare("UPDATE ui_images SET upload_path = ?, external_url = NULL WHERE id = ?");
                $stmt->execute([$upload_path, $image_id]);
                $_SESSION['success'] = "Image uploaded successfully.";
            } elseif (!empty($external_url)) {
                // If an external URL is provided (and no new file), clear upload_path
                $stmt = $pdo->prepare("UPDATE ui_images SET external_url = ?, upload_path = NULL WHERE id = ?");
                $stmt->execute([$external_url, $image_id]);
                $_SESSION['success'] = "Image URL updated successfully.";
            } else {
                // Both empty (clearing the image)
                $stmt = $pdo->prepare("UPDATE ui_images SET external_url = NULL, upload_path = NULL WHERE id = ?");
                $stmt->execute([$image_id]);
                $_SESSION['success'] = "Image cleared.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
    }
    header("Location: ui_images.php");
    exit;
}

// Fetch all images
$images = $pdo->query("SELECT * FROM ui_images ORDER BY page_name ASC, title ASC")->fetchAll();

require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <div class="d-flex align-items-center">
            <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
            <h3 class="mb-0">Manage UI Images</h3>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-check-circle me-2"></i> <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); endif; ?>

    <div class="accordion" id="uiImagesAccordion">
        <?php 
        // Group images by page_name
        $grouped_images = [];
        foreach ($images as $img) {
            $grouped_images[$img['page_name']][] = $img;
        }
        
        $acc_idx = 0;
        foreach ($grouped_images as $page_name => $page_images): 
            $acc_idx++;
            $collapseId = "collapsePage" . $acc_idx;
        ?>
        <div class="accordion-item border-0 mb-4 shadow-sm rounded-4 overflow-hidden">
            <h2 class="accordion-header">
                <button class="accordion-button <?= $acc_idx == 1 ? '' : 'collapsed' ?> bg-white fw-bold fs-5 text-dark border-bottom" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>">
                    <i class="fa-solid fa-folder-open text-warning me-3"></i> <?= htmlspecialchars($page_name) ?> 
                    <span class="badge bg-light text-dark border ms-3 rounded-pill"><?= count($page_images) ?> Images</span>
                </button>
            </h2>
            <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $acc_idx == 1 ? 'show' : '' ?>" data-bs-parent="#uiImagesAccordion">
                <div class="accordion-body p-0 bg-white">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Preview</th>
                                    <th>Title</th>
                                    <th>Current Source</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($page_images as $img): ?>
                                    <?php
                                        $img_src = '';
                                        $source_type = '<span class="badge bg-secondary">None</span>';
                                        if (!empty($img['upload_path'])) {
                                            $img_src = BASE_URL . 'uploads/ui/' . htmlspecialchars($img['upload_path']);
                                            $source_type = '<span class="badge bg-success">Uploaded File</span>';
                                        } elseif (!empty($img['external_url'])) {
                                            $img_src = htmlspecialchars($img['external_url']);
                                            $source_type = '<span class="badge bg-info">External Link</span>';
                                        } else {
                                            $img_src = 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?q=80&w=100&auto=format&fit=crop';
                                        }
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="rounded overflow-hidden border bg-light d-flex align-items-center justify-content-center" style="width: 80px; height: 60px;">
                                                <img src="<?= $img_src ?>" class="w-100 h-100" style="object-fit: contain;" alt="Preview">
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                                $rec_size = "Auto";
                                                if (strpos($img['image_key'], 'banner_') !== false) { $rec_size = "1920 x 400"; }
                                                elseif (strpos($img['image_key'], 'home_hero') !== false) { $rec_size = "1920 x 800"; }
                                                elseif (strpos($img['image_key'], 'about_hero') !== false) { $rec_size = "1920 x 600"; }
                                                elseif (strpos($img['image_key'], 'home_about') !== false) { $rec_size = "800 x 600"; }
                                                elseif ($img['image_key'] == 'about_community') { $rec_size = "800 x 800"; }
                                                elseif ($img['image_key'] == 'about_vision' || $img['image_key'] == 'about_mission') { $rec_size = "800 x 400"; }
                                                elseif (strpos($img['image_key'], 'about_core_') !== false) { $rec_size = "200 x 200"; }
                                                elseif (strpos($img['image_key'], 'home_testimonial_') !== false) { $rec_size = "100 x 100 (Square)"; }
                                                elseif (strpos($img['image_key'], 'gallery_static_') !== false) { $rec_size = "400 x 300"; }
                                                elseif ($img['image_key'] == 'web_services_placeholder') { $rec_size = "600 x 500"; }
                                            ?>
                                            <strong><?= htmlspecialchars($img['title']) ?></strong><br>
                                            <small class="text-muted" style="font-family: monospace;">Key: <?= htmlspecialchars($img['image_key']) ?></small><br>
                                            <small class="text-primary"><i class="fa-solid fa-crop-simple"></i> Size: <?= $rec_size ?></small>
                                        </td>
                                        <td><?= $source_type ?></td>
                                        <td class="text-end pe-4">
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $img['id'] ?>">
                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?= $img['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header bg-light border-0">
                                                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-image text-warning me-2"></i>Edit Image: <?= htmlspecialchars($img['title']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body text-start">
                                                    <div class="text-center mb-4">
                                                        <p class="text-muted small mb-2">Current Image</p>
                                                        <img src="<?= $img_src ?>" class="img-fluid rounded border shadow-sm" style="max-height: 150px; object-fit: contain;">
                                                    </div>
                                                    
                                                    <form action="ui_images.php" method="POST" enctype="multipart/form-data">
                                                        <input type="hidden" name="action" value="update_image">
                                                        <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                                        
                                                        <div class="mb-4">
                                                            <label class="form-label fw-bold">Option 1: Upload New File</label>
                                                            <input type="file" name="image_file" class="form-control" accept="image/*">
                                                            <div class="form-text text-success"><i class="fa-solid fa-circle-info me-1"></i> Uploading a file will override any external link.</div>
                                                        </div>
                                                        
                                                        <div class="position-relative text-center my-3">
                                                            <hr>
                                                            <span class="position-absolute top-50 start-50 translate-middle bg-white px-2 text-muted small fw-bold">OR</span>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Option 2: External Image URL</label>
                                                            <input type="url" name="external_url" class="form-control" value="<?= htmlspecialchars($img['external_url'] ?? '') ?>" placeholder="https://example.com/image.jpg">
                                                            <div class="form-text">Paste a direct link to an image.</div>
                                                        </div>
                                                        
                                                        <div class="d-grid mt-4">
                                                            <button type="submit" class="btn btn-warning fw-bold text-dark py-2">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('show');
        document.getElementById('sidebarOverlay').classList.toggle('show');
    });
    document.getElementById('sidebarCloseBtn').addEventListener('click', function() {
        document.getElementById('sidebar').classList.remove('show');
        document.getElementById('sidebarOverlay').classList.remove('show');
    });
    document.getElementById('sidebarOverlay').addEventListener('click', function() {
        document.getElementById('sidebar').classList.remove('show');
        this.classList.remove('show');
    });
</script>
</body>
</html>
