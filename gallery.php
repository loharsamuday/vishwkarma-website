<?php
$page_title = "Gallery";
require_once 'includes/db.php';
require_once 'includes/session.php';

$upload_dir = 'uploads/gallery/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Check how many images the current user has uploaded
$user_id = $_SESSION['user_id'] ?? null;
$user_image_count = 0;

if ($user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_gallery WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_image_count = $stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['gallery_image']) && $user_id) {
    if ($user_image_count >= 3) {
        setFlashMessage('danger', 'You can only upload a maximum of 3 images.');
    } else {
        $file = $_FILES['gallery_image'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
            // Check mime type reliably
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $file_type = finfo_file($file_info, $file['tmp_name']);
            finfo_close($file_info);
            
            if (in_array($file_type, $allowed_types)) {
                $ext = 'webp';
                $new_filename = uniqid('vkpuja_') . '.' . $ext;
                $destination = $upload_dir . $new_filename;
                
                $image = null;
                switch ($file_type) {
                    case 'image/jpeg': 
                        $image = imagecreatefromjpeg($file['tmp_name']); 
                        break;
                    case 'image/png': 
                        $image = imagecreatefrompng($file['tmp_name']); 
                        // Handle transparency for PNG to WEBP
                        imagepalettetotruecolor($image);
                        imagealphablending($image, true);
                        imagesavealpha($image, true);
                        break;
                    case 'image/webp': 
                        $image = imagecreatefromwebp($file['tmp_name']); 
                        break;
                    case 'image/avif': 
                        if(function_exists('imagecreatefromavif')) {
                            $image = imagecreatefromavif($file['tmp_name']); 
                        } else {
                            setFlashMessage('danger', 'AVIF is not supported on this server. Please upload JPG or PNG.');
                        }
                        break;
                }
                
                if ($image !== null) {
                    // Convert and save as WEBP with 80% quality
                    imagewebp($image, $destination, 80);
                    imagedestroy($image);
                    
                    // Insert into database
                    $stmt = $pdo->prepare("INSERT INTO user_gallery (user_id, image_path) VALUES (?, ?)");
                    $stmt->execute([$user_id, $new_filename]);
                    
                    logActivity('Uploaded an image to the Gallery', 'user', $user_id);
                    
                    setFlashMessage('success', 'Image uploaded successfully in WEBP format to save storage.');
                    header("Location: gallery.php");
                    exit;
                } else if(!isset($_SESSION['flash_messages'])) {
                    // Only set error if not already set by AVIF check
                    setFlashMessage('danger', 'Error processing the image.');
                }
            } else {
                setFlashMessage('danger', 'Invalid file type. Only JPG, PNG, WEBP, and AVIF are allowed.');
            }
        } else {
            setFlashMessage('danger', 'Error uploading the file. Please try a smaller image.');
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image_id']) && $user_id) {
    $delete_id = (int)$_POST['delete_image_id'];
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT image_path FROM user_gallery WHERE id = ? AND user_id = ?");
    $stmt->execute([$delete_id, $user_id]);
    $img_to_delete = $stmt->fetch();
    
    if ($img_to_delete) {
        $file_path = $upload_dir . $img_to_delete['image_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $pdo->prepare("DELETE FROM user_gallery WHERE id = ?")->execute([$delete_id]);
        logActivity('Deleted a gallery image', 'user', $user_id);
        setFlashMessage('success', 'Image deleted successfully.');
    } else {
        setFlashMessage('danger', 'Image not found or permission denied.');
    }
    header("Location: gallery.php");
    exit;
}

// Fetch all approved images
$gallery_images = [];
try {
    $gallery_images = $pdo->query("
        SELECT ug.*, u.first_name, u.last_name 
        FROM user_gallery ug 
        JOIN users u ON ug.user_id = u.id 
        WHERE ug.status = 'approved' 
        ORDER BY ug.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    // If the table doesn't exist yet (db script hasn't run), silently ignore
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<?php $banner_img = function_exists('getUiImage') ? getUiImage('banner_gallery', 'https://images.unsplash.com/photo-1452587925148-ce544e77e70d?q=80&w=1920&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1452587925148-ce544e77e70d?q=80&w=1920&auto=format&fit=crop'; ?>
<div class="page-banner mb-4">
    <img src="<?= htmlspecialchars($banner_img) ?>" class="img-fluid w-100 shadow-sm" style="max-height: 400px; object-fit: cover;">
</div>
<div class="container py-5">
    <div class="text-center mb-5" data-aos="fade-down">
        <h1 class="fw-bold text-warning">Vishwakarma Puja Gallery</h1>
        <p class="text-muted">Explore the moments of devotion and celebration. Registered members can upload up to 3 images!</p>
    </div>

    <?php displayFlashMessage(); ?>

    <?php if ($user_id): ?>
        <div class="card shadow-sm border-0 mb-5 bg-light" data-aos="zoom-in">
            <div class="card-body text-center">
                <h5 class="card-title text-primary"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Upload Your Puja Photo</h5>
                <p class="small text-muted mb-4">You have uploaded <?= $user_image_count ?>/3 images. Images are automatically converted to highly optimized WEBP format to save storage.</p>
                <?php if ($user_image_count < 3): ?>
                    <form method="POST" enctype="multipart/form-data" class="d-flex flex-column flex-md-row align-items-center justify-content-center gap-3">
                        <input class="form-control w-auto" type="file" name="gallery_image" accept="image/jpeg, image/png, image/webp, image/avif" required>
                        <button type="submit" class="btn btn-warning fw-bold px-4">Upload Photo</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info py-2 mb-0 d-inline-block">You have reached the maximum limit of 3 uploads. Thank you for contributing!</div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center mb-5 border-warning border-start border-4 shadow-sm">
            <i class="fa-solid fa-lock me-2"></i>Please <a href="login.php" class="alert-link text-decoration-none">Login</a> to upload your Vishwakarma Puja photos.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if (count($gallery_images) > 0): ?>
            <?php foreach ($gallery_images as $index => $img): ?>
                <div class="col-md-4 col-sm-6" data-aos="fade-up" data-aos-delay="<?= ($index % 3) * 100 ?>">
                    <div class="card border-0 shadow-sm h-100 overflow-hidden position-relative">
                        <img src="uploads/gallery/<?= htmlspecialchars($img['image_path']) ?>" class="card-img-top hover-scale" alt="Vishwakarma Puja" style="height: 250px; object-fit: cover; transition: transform 0.3s ease;">
                        
                        <?php if ($user_id && $user_id == $img['user_id']): ?>
                            <form method="POST" class="position-absolute top-0 end-0 p-2 m-0" onsubmit="return confirm('Are you sure you want to delete this photo?');">
                                <input type="hidden" name="delete_image_id" value="<?= $img['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger shadow" title="Delete Photo"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        <?php endif; ?>
                        
                        <div class="card-body py-2 px-3 text-center bg-white border-top">
                            <small class="text-muted fw-bold">By <?= htmlspecialchars($img['first_name'] . ' ' . $img['last_name']) ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center text-muted">
                <p class="mb-4 fs-5">No photos uploaded yet. Be the first to share your Puja moments!</p>
                <!-- Placeholders -->
                <div class="row g-4">
                    <div class="col-md-4"><img src="<?= function_exists('getUiImage') ? getUiImage('gallery_static_1', 'https://images.unsplash.com/photo-1590050752117-238cb0fb12b1?q=80&w=400&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1590050752117-238cb0fb12b1?q=80&w=400&auto=format&fit=crop' ?>" class="img-fluid rounded shadow-sm hover-scale"></div>
                    <div class="col-md-4"><img src="<?= function_exists('getUiImage') ? getUiImage('gallery_static_2', 'https://images.unsplash.com/photo-1600010996160-c447bc981249?q=80&w=400&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1600010996160-c447bc981249?q=80&w=400&auto=format&fit=crop' ?>" class="img-fluid rounded shadow-sm hover-scale"></div>
                    <div class="col-md-4"><img src="<?= function_exists('getUiImage') ? getUiImage('gallery_static_3', 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?q=80&w=400&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?q=80&w=400&auto=format&fit=crop' ?>" class="img-fluid rounded shadow-sm hover-scale"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<style>
    .hover-scale:hover {
        transform: scale(1.05);
    }
</style>
<?php require_once 'includes/footer.php'; ?>
