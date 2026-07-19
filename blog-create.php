<?php
$page_title = "Write a Blog";
require_once 'includes/db.php';
require_once 'includes/session.php';

if (!isset($_SESSION['user_id'])) {
    setFlashMessage('warning', 'You must be logged in to write a blog.');
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $user_id = $_SESSION['user_id'];
    
    if (empty($title) || empty($content)) {
        setFlashMessage('danger', 'Title and content are required.');
    } else {
        $image_filename = null;
        
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
                $image_filename = uniqid('blog_') . '.' . $ext;
                $destination = $upload_dir . $image_filename;
                
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
                } else {
                    $image_filename = null; // failed to process image
                }
            }
        }
        
        // As per the plan, blogs are auto-approved.
        $stmt = $pdo->prepare("INSERT INTO blogs (user_id, title, content, image_path, status) VALUES (?, ?, ?, ?, 'approved')");
        if ($stmt->execute([$user_id, $title, $content, $image_filename])) {
            $blog_id = $pdo->lastInsertId();
            logActivity("Published a new blog: $title", 'user', $user_id);
            
            setFlashMessage('success', 'Your blog has been published successfully!');
            header("Location: blogs.php");
            exit;
        } else {
            setFlashMessage('danger', 'Failed to publish blog. Please try again.');
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
                    <h4 class="mb-0"><i class="fa-solid fa-pen-nib me-2"></i> Write a New Blog</h4>
                </div>
                <div class="card-body p-4">
                    <?php displayFlashMessage(); ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Blog Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control form-control-lg" required placeholder="Enter a catchy title...">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Cover Image <small class="text-muted">(Optional)</small></label>
                            <input type="file" name="blog_image" class="form-control" accept="image/jpeg, image/png, image/webp, image/avif">
                            <small class="text-muted">A featured image makes your blog stand out. It will be compressed automatically.</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Content <span class="text-danger">*</span></label>
                            <textarea name="content" id="blogContent" class="form-control" rows="12" placeholder="Write your thoughts, stories, or knowledge here..."></textarea>
                            <small class="text-muted">Please follow community guidelines. Any inappropriate content will be removed by admins.</small>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="blogs.php" class="btn btn-outline-secondary px-4">Cancel</a>
                            <button type="submit" class="btn btn-warning fw-bold px-4">Publish Blog</button>
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
