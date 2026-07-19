<?php
$page_title = "Edit Blog";
require_once 'includes/db.php';
require_once 'includes/session.php';

if (!isset($_SESSION['user_id'])) {
    setFlashMessage('warning', 'You must be logged in to edit a blog.');
    header("Location: login.php");
    exit;
}

$blog_id = $_GET['id'] ?? null;
if (!$blog_id) {
    header("Location: blogs.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch existing blog
$stmt = $pdo->prepare("SELECT * FROM blogs WHERE id = ? AND user_id = ?");
$stmt->execute([$blog_id, $user_id]);
$blog = $stmt->fetch();

if (!$blog) {
    setFlashMessage('danger', 'Blog not found or you do not have permission to edit it.');
    header("Location: blogs.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    
    if (empty($title) || empty($content)) {
        setFlashMessage('danger', 'Title and content are required.');
    } else {
        $image_filename = $blog['image_path'];
        
        // Handle optional image upload
        if (isset($_FILES['blog_image']) && $_FILES['blog_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/blogs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file = $_FILES['blog_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $file_type = finfo_file($file_info, $file['tmp_name']);
            finfo_close($file_info);
            
            if (in_array($file_type, $allowed_types)) {
                $ext = 'webp';
                $new_image_filename = uniqid('blog_') . '.' . $ext;
                $destination = $upload_dir . $new_image_filename;
                
                $image = null;
                switch ($file_type) {
                    case 'image/jpeg': $image = @imagecreatefromjpeg($file['tmp_name']); break;
                    case 'image/png': 
                        $image = @imagecreatefrompng($file['tmp_name']); 
                        if ($image) {
                            imagepalettetotruecolor($image);
                            imagealphablending($image, true);
                            imagesavealpha($image, true);
                        }
                        break;
                    case 'image/webp': $image = @imagecreatefromwebp($file['tmp_name']); break;
                    case 'image/avif': 
                        if(function_exists('imagecreatefromavif')) {
                            $image = @imagecreatefromavif($file['tmp_name']); 
                        }
                        break;
                }
                
                if ($image !== null) {
                    imagewebp($image, $destination, 80);
                    imagedestroy($image);
                    
                    // Remove old image
                    if (!empty($image_filename) && file_exists($upload_dir . $image_filename)) {
                        unlink($upload_dir . $image_filename);
                    }
                    $image_filename = $new_image_filename;
                }
            }
        }
        
        $stmt = $pdo->prepare("UPDATE blogs SET title = ?, content = ?, image_path = ? WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$title, $content, $image_filename, $blog_id, $user_id])) {
            logActivity("Edited blog: $title", 'user', $user_id);
            setFlashMessage('success', 'Your blog has been updated successfully!');
            header("Location: blog-detail.php?id=" . $blog_id);
            exit;
        } else {
            setFlashMessage('danger', 'Failed to update blog. Please try again.');
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning text-dark fw-bold py-3">
                    <h4 class="mb-0"><i class="fa-solid fa-pen-to-square me-2"></i> Edit Blog</h4>
                </div>
                <div class="card-body p-4">
                    <?php displayFlashMessage(); ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Blog Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control form-control-lg" required value="<?= htmlspecialchars($blog['title']) ?>">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Cover Image <small class="text-muted">(Optional)</small></label>
                            <?php if (!empty($blog['image_path'])): ?>
                                <div class="mb-2">
                                    <img src="uploads/blogs/<?= htmlspecialchars($blog['image_path']) ?>" alt="Current Image" style="max-height: 150px; border-radius: 5px;">
                                    <p class="small text-muted mt-1">Current Cover Image</p>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="blog_image" class="form-control" accept="image/jpeg, image/png, image/webp, image/avif">
                            <small class="text-muted">Upload a new image to replace the current one.</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Content <span class="text-danger">*</span></label>
                            <textarea name="content" id="blogContent" class="form-control" rows="12"><?= htmlspecialchars($blog['content']) ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="blog-detail.php?id=<?= $blog['id'] ?>" class="btn btn-outline-secondary px-4">Cancel</a>
                            <button type="submit" class="btn btn-warning fw-bold px-4">Update Blog</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<!-- TinyMCE WYSIWYG Editor -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
<script>
  tinymce.init({
    selector: '#blogContent',
    plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount emoticons',
    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor emoticons | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
    height: 500,
    branding: false,
    promotion: false
  });
</script>
