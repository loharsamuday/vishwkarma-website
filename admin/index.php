<?php
$page_title = "Admin Login";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        
        logActivity('Admin Logged In', 'admin', null, $admin['id']);
        
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid admin credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - Vishwakarma Samaj</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="card card-custom p-4 p-md-5 mx-3 w-100" style="max-width: 400px;">
        <div class="text-center mb-4">
            <h3 class="text-warning fw-bold">Admin Panel</h3>
            <p class="text-muted small">Vishwakarma Samaj</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger py-2"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label>Username or Email</label>
                <input type="text" name="username" class="form-control" required value="admin">
            </div>
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="mb-0">Password</label>
                    <a href="forgot-password.php" class="small text-warning text-decoration-none">Forgot Password?</a>
                </div>
                <div class="input-group">
                    <input type="password" name="password" class="form-control" required value="password">
                    <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>
            <button type="submit" class="btn btn-warning w-100 fw-bold">Login</button>
        </form>
    </div>
    
    <script>
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    </script>
</body>
</html>
