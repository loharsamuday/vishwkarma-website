<?php
// admin/includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header("Location: " . EL_BASE_URL . "admin/login.php");
    exit();
}

$logged_in_admin = null;
if (isset($_SESSION['admin_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $logged_in_admin = $stmt->fetch();
    
    // Default to super_admin if column not found (backwards compatibility before migration)
    if ($logged_in_admin && !isset($logged_in_admin['role'])) {
        $logged_in_admin['role'] = 'super_admin';
        $logged_in_admin['permissions'] = '[]';
    }
}

// Global Permission Check for Guest Admins
if ($logged_in_admin && $logged_in_admin['role'] === 'guest_admin') {
    $guest_perms = json_decode($logged_in_admin['permissions'] ?? '[]', true) ?: [];
    $current_file = basename($_SERVER['PHP_SELF']);
    $request_uri = $_SERVER['REQUEST_URI'];
    
    $is_allowed = false;
    if ($current_file === 'index.php' || $current_file === 'logout.php') {
        $is_allowed = true; // Everyone can access dashboard and logout
    }
    
    // Map pages to permissions
    if (in_array('stories', $guest_perms)) {
        if (in_array($current_file, ['stories.php', 'add-story.php', 'edit-story.php', 'categories.php', 'user-stories.php', 'view-user-story.php', 'process-user-story.php', 'vocabulary.php', 'add-vocabulary.php', 'edit-vocabulary.php', 'delete-story.php'])) {
            $is_allowed = true;
        }
        if (strpos($request_uri, '/idioms') !== false || strpos($request_uri, '/phrasal-verbs') !== false) {
            $is_allowed = true;
        }
    }
    
    if (in_array('users', $guest_perms)) {
        if (in_array($current_file, ['users.php', 'guest-activity.php'])) {
            $is_allowed = true;
        }
    }
    
    if (in_array('newsletter', $guest_perms)) {
        if (in_array($current_file, ['subscribers.php', 'send-updates.php'])) {
            $is_allowed = true;
        }
    }
    
    if (!$is_allowed) {
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 0; }
        .denied-card { background: #fff; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.08); padding: 3rem 2.5rem; max-width: 450px; width: 90%; text-align: center; position: relative; overflow: hidden; border-top: 6px solid #e74c3c; }
        .icon-circle { width: 90px; height: 90px; background: #fceceb; color: #e74c3c; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.8rem; margin: 0 auto 1.5rem auto; box-shadow: 0 10px 25px rgba(231, 76, 60, 0.2); }
        h2 { font-weight: 800; color: #2c3e50; margin-bottom: 1rem; font-size: 2rem; }
        p { color: #7f8c8d; font-size: 1.05rem; line-height: 1.6; margin-bottom: 2rem; }
        .btn-dashboard { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: #fff; border: none; padding: 0.8rem 2rem; border-radius: 50px; font-weight: 600; letter-spacing: 0.5px; transition: all 0.3s ease; text-decoration: none; display: inline-block; box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3); }
        .btn-dashboard:hover { transform: translateY(-3px); color: #fff; box-shadow: 0 12px 25px rgba(52, 152, 219, 0.4); }
        .bg-pattern { position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, #fceceb 10%, transparent 10%), radial-gradient(circle, #fceceb 10%, transparent 10%); background-size: 20px 20px; background-position: 0 0, 10px 10px; opacity: 0.6; z-index: 0; }
        .z-1 { z-index: 1; }
    </style>
</head>
<body>
    <div class="denied-card">
        <div class="bg-pattern"></div>
        <div class="position-relative z-1">
            <div class="icon-circle">
                <i class="fas fa-lock"></i>
            </div>
            <h2>Access Denied</h2>
            <p>You do not have permission to access this module.<br>Please contact the <strong>Super Admin</strong> if you believe this is a mistake.</p>
            <a href="'.EL_BASE_URL.'admin/index.php" class="btn-dashboard">
                <i class="fas fa-arrow-left me-2"></i> Return to Dashboard
            </a>
        </div>
    </div>
</body>
</html>';
        die($html);
    }
}

$page_title = isset($page_title) ? $page_title : 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($page_title) ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= EL_BASE_URL ?>assets/css/style.css">
    <!-- CKEditor 5 CDN (100% Free, No API Key Required) -->
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
    <style>
        /* Set minimum height for CKEditor */
        .ck-editor__editable_inline {
            min-height: 400px;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const textareas = document.querySelectorAll('.rich-editor');
            textareas.forEach(textarea => {
                ClassicEditor
                    .create(textarea, {
                        toolbar: [ 'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'insertTable', '|', 'undo', 'redo' ]
                    })
                    .catch(error => {
                        console.error(error);
                    });
            });
        });
    </script>
</head>
<body>

<?php if (isset($_SESSION['admin_id'])): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <button class="navbar-toggler d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle sidebar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand me-auto" href="<?= EL_BASE_URL ?>admin/index.php">
            <i class="fas fa-shield-alt me-2"></i>Admin Panel
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fas fa-ellipsis-v text-white opacity-75"></i>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= EL_BASE_URL ?>index.php" target="_blank"><i class="fas fa-external-link-alt me-1"></i>View Site</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= EL_BASE_URL ?>admin/logout.php"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pt-3 pb-5">
<?php endif; ?>
