<?php
// profile.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=login_required");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $pdo->prepare("
    SELECT u.name, u.email, u.created_at, u.profile_photo, sp.target_exam_id, te.name as target_exam_name
    FROM users u 
    LEFT JOIN student_preferences sp ON u.id = sp.user_id 
    LEFT JOIN target_exams te ON sp.target_exam_id = te.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$upload_error = '';

// Handle Profile Photo Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $file = $_FILES['profile_photo'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        if (in_array($file['type'], $allowedTypes)) {
            $uploadDir = 'uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $sourceFile = $file['tmp_name'];
            $imageInfo = getimagesize($sourceFile);
            
            if ($imageInfo !== false) {
                if (!extension_loaded('gd')) {
                    $upload_error = "Error: PHP GD extension is not enabled in XAMPP. Cannot resize or convert to webp.";
                } else {
                    $mime = $imageInfo['mime'];
                    $image = null;
                    
                    if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
                        $image = imagecreatefromjpeg($sourceFile);
                    } elseif ($mime == 'image/png') {
                        $image = imagecreatefrompng($sourceFile);
                    } elseif ($mime == 'image/webp') {
                        $image = imagecreatefromwebp($sourceFile);
                    }
                    
                    if ($image) {
                    $width = imagesx($image);
                    $height = imagesy($image);
                    $minSize = min($width, $height);
                    $cropX = ($width - $minSize) / 2;
                    $cropY = ($height - $minSize) / 2;
                    
                    $resizedImage = imagecreatetruecolor(200, 200);
                    imagealphablending($resizedImage, false);
                    imagesavealpha($resizedImage, true);
                    $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                    imagefilledrectangle($resizedImage, 0, 0, 200, 200, $transparent);
                    
                    imagecopyresampled($resizedImage, $image, 0, 0, $cropX, $cropY, 200, 200, $minSize, $minSize);
                    
                    $filename = 'user_' . $user_id . '_' . time() . '.webp';
                    $destPath = $uploadDir . $filename;
                    
                    imagewebp($resizedImage, $destPath, 85);
                    imagedestroy($image);
                    imagedestroy($resizedImage);
                    
                    // Remove old photo if exists
                    if (!empty($user['profile_photo']) && file_exists($user['profile_photo'])) {
                        unlink($user['profile_photo']);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                    $stmt->execute([$destPath, $user_id]);
                    
                    header("Location: profile.php?msg=photo_updated");
                    exit();
                } else {
                    $upload_error = "Could not process image.";
                }
                } // End of extension_loaded else
            } else {
                $upload_error = "Invalid image file.";
            }
        } else {
            $upload_error = "Please upload a valid image (JPG, PNG, WEBP).";
        }
    } else {
        $upload_error = "Error uploading file.";
    }
}

// Update Target Exam logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_target_exam'])) {
    $new_target = $_POST['target_exam_id'];
    
    // Check if preference already exists
    $stmt_check = $pdo->prepare("SELECT id FROM student_preferences WHERE user_id = ?");
    $stmt_check->execute([$user_id]);
    if ($stmt_check->rowCount() > 0) {
        $stmt_upd = $pdo->prepare("UPDATE student_preferences SET target_exam_id = ? WHERE user_id = ?");
        $stmt_upd->execute([$new_target, $user_id]);
    } else {
        $stmt_ins = $pdo->prepare("INSERT INTO student_preferences (user_id, target_exam_id) VALUES (?, ?)");
        $stmt_ins->execute([$user_id, $new_target]);
    }
    
    // Refresh user data
    header("Location: profile.php?msg=exam_updated");
    exit();
}

$exams = $pdo->query("SELECT id, name FROM target_exams WHERE status = 'active' ORDER BY id")->fetchAll();


if (!$user) {
    // Session exists but user deleted?
    session_destroy();
    header("Location: index.php");
    exit();
}

// Fetch user's submitted stories
$stmt = $pdo->prepare("
    SELECT us.*, c.name as category_name 
    FROM user_stories us 
    LEFT JOIN categories c ON us.category_id = c.id 
    WHERE us.user_id = ? 
    ORDER BY us.created_at DESC
");
$stmt->execute([$user_id]);
$my_stories = $stmt->fetchAll();

$page_title = 'My Account';
include 'includes/header.php';
?>

<div class="py-5 text-center text-white position-relative" style="background: linear-gradient(135deg, var(--primary-color) 0%, #1a5f91 100%); margin-bottom: 50px;">
    <div class="container position-relative" style="z-index: 2; padding-bottom: 30px;">
        <h1 class="fw-bold mb-2 display-5">My Dashboard</h1>
        <p class="lead" style="opacity: 0.9;">Manage your profile and track your submitted stories</p>
    </div>
    <!-- Decorative shape divider -->
    <div class="position-absolute bottom-0 start-0 w-100 overflow-hidden" style="line-height: 0;">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none" style="position: relative; display: block; width: calc(137% + 1.3px); height: 50px;">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" style="fill: #f8f9fa;"></path>
        </svg>
    </div>
</div>

<div class="container mb-5 pb-5 mt-n5 position-relative" style="z-index: 10;">
    <div class="row">
        <!-- Profile Info -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-lg border-0 rounded-4 mb-4">
                <div class="card-body p-4 text-center">
                    
                    <?php if($upload_error): ?>
                        <div class="alert alert-danger small py-2"><?= escape($upload_error) ?></div>
                    <?php endif; ?>
                    
                    <div class="position-relative d-inline-block mb-3 mt-n4 group-hover-show">
                        <?php if (!empty($user['profile_photo']) && file_exists($user['profile_photo'])): ?>
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm text-white fw-bold overflow-hidden" style="width: 100px; height: 100px; border: 5px solid #fff; background-color: #f8f9fa;">
                                <img src="<?= escape($user['profile_photo']) ?>" alt="<?= escape($user['name']) ?>" class="w-100 h-100 object-fit-cover">
                            </div>
                        <?php else: ?>
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm text-white fw-bold" style="width: 100px; height: 100px; font-size: 2.5rem; background: linear-gradient(135deg, #0b3b60 0%, #3498db 100%); border: 5px solid #fff;">
                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="position-absolute bottom-0 end-0 bg-success rounded-circle border border-white" style="width: 20px; height: 20px; border-width: 3px !important; z-index: 2;" title="Active User"></div>
                        
                        <form action="" method="POST" enctype="multipart/form-data" id="photoForm">
                            <label for="profile_photo" class="position-absolute bg-white rounded-circle shadow-sm d-flex align-items-center justify-content-center text-primary-custom" style="width: 32px; height: 32px; bottom: 0; left: 0; cursor: pointer; border: 2px solid #fff; z-index: 3; transition: 0.2s;" title="Upload Photo">
                                <i class="fas fa-camera small"></i>
                            </label>
                            <input type="file" id="profile_photo" name="profile_photo" accept="image/*" class="d-none" onchange="document.getElementById('photoForm').submit();">
                        </form>
                    </div>
                    
                    <h4 class="fw-bold mb-1 text-dark"><?= escape($user['name']) ?></h4>
                    <p class="text-muted mb-3 d-flex align-items-center justify-content-center"><i class="fas fa-envelope me-2 opacity-50"></i> <?= escape($user['email']) ?></p>
                    
                    <div class="bg-light rounded-3 p-3 mb-4 text-start">
                        <p class="small text-muted mb-1 text-uppercase fw-bold" style="letter-spacing: 0.5px;">Account Details</p>
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-calendar-alt text-primary-custom me-2" style="width: 20px;"></i>
                            <span class="fs-6">Joined <?= date('M Y', strtotime($user['created_at'])) ?></span>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file-alt text-primary-custom me-2" style="width: 20px;"></i>
                            <span class="fs-6"><?= count($my_stories) ?> Stories Submitted</span>
                        </div>
                    </div>
                    
                    <form method="POST" action="" class="mb-4 text-start bg-white border rounded-3 p-3 shadow-sm">
                        <label class="form-label fw-bold text-dark mb-2"><i class="fas fa-bullseye text-danger me-1"></i> My Target Exam</label>
                        <p class="small text-muted mb-2">Personalize your learning path.</p>
                        <div class="input-group">
                            <select class="form-select form-select-sm" name="target_exam_id" style="border-radius: 6px 0 0 6px;">
                                <option value="">None Selected</option>
                                <?php foreach($exams as $ex): ?>
                                    <option value="<?= $ex['id'] ?>" <?= ($user['target_exam_id'] == $ex['id']) ? 'selected' : '' ?>><?= escape($ex['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="update_target_exam" class="btn btn-sm bg-primary-custom text-white fw-bold px-3" style="border-radius: 0 6px 6px 0;">Save</button>
                        </div>
                    </form>

                    <div class="d-grid mt-2">
                        <a href="logout.php" class="btn btn-light text-danger fw-bold rounded-pill hover-danger"><i class="fas fa-sign-out-alt me-2"></i>Secure Log Out</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- User Stories -->
        <div class="col-md-8">
            <div class="card shadow-lg border-0 rounded-4 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center">
                    <h4 class="fw-bold text-dark mb-0"><i class="fas fa-pen-nib text-primary-custom me-2"></i>My Stories</h4>
                    <a href="write-story.php" class="btn btn-sm bg-primary-custom text-white rounded-pill px-3 fw-bold shadow-sm"><i class="fas fa-plus me-1"></i>Write New</a>
                </div>
                <div class="card-body p-0">
                    <?php if (count($my_stories) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="border-collapse: separate; border-spacing: 0;">
                                <thead class="bg-light text-muted small text-uppercase" style="letter-spacing: 0.5px;">
                                    <tr>
                                        <th class="ps-4 py-3 fw-bold border-0 rounded-start">Story Title</th>
                                        <th class="py-3 fw-bold border-0">Category</th>
                                        <th class="py-3 fw-bold border-0">Status</th>
                                        <th class="pe-4 py-3 fw-bold border-0 text-end rounded-end">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_stories as $story): ?>
                                    <tr>
                                        <td class="ps-4 py-3 border-bottom">
                                            <div class="fw-bold text-dark"><?= escape($story['title']) ?></div>
                                            <?php if($story['status'] == 'Rejected' && !empty($story['admin_note'])): ?>
                                                <div class="small text-danger mt-1"><i class="fas fa-info-circle me-1"></i>Reason: <?= escape($story['admin_note']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 border-bottom">
                                            <span class="badge bg-light text-dark border"><i class="fas fa-tag me-1 text-muted"></i><?= escape($story['category_name']) ?></span>
                                        </td>
                                        <td class="py-3 border-bottom">
                                            <?php if($story['status'] == 'Pending'): ?>
                                                <span class="badge bg-warning text-dark rounded-pill px-3"><i class="fas fa-clock me-1"></i>Pending review</span>
                                            <?php elseif($story['status'] == 'Approved'): ?>
                                                <span class="badge bg-success rounded-pill px-3"><i class="fas fa-check me-1"></i>Published</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger rounded-pill px-3"><i class="fas fa-times me-1"></i>Needs revision</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="pe-4 py-3 border-bottom text-end text-muted small">
                                            <?= date('M d, Y', strtotime($story['created_at'])) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 my-4">
                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px;">
                                <i class="fas fa-book-open fa-3x text-muted opacity-50"></i>
                            </div>
                            <h4 class="fw-bold text-dark">No stories yet</h4>
                            <p class="text-muted mb-4 max-w-500 mx-auto">Practice your English vocabulary by writing your first story today. Get feedback from admins and share with others!</p>
                            <a href="write-story.php" class="btn btn-primary bg-primary-custom px-4 py-2 rounded-pill fw-bold shadow-sm">Start Writing Now</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
