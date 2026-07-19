<?php
$page_title = "Reset Password";
require_once 'includes/db.php';
require_once 'includes/session.php';

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (empty($token) || empty($email)) {
    die("Invalid reset link.");
}

// Verify token
$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ?");
$stmt->execute([$email, $token]);
$reset = $stmt->fetch();

if (!$reset) {
    die("Invalid or expired reset link.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update user password
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashed_password, $email]);
        
        // Delete token
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        
        $success = "Your password has been successfully reset. You can now login.";
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<div class="container mt-5 mb-5" style="min-height: 50vh;">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card card-custom p-5 shadow-sm border-top border-4 border-success">
                <div class="text-center">
                    <i class="fa-solid fa-key fa-4x text-success mb-4"></i>
                    <h3 class="fw-bold mb-3">Create New Password</h3>
                    <p class="text-muted">Enter your new password below.</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success text-center">
                        <p class="mb-3"><?= $success ?></p>
                        <a href="login.php" class="btn btn-success fw-bold">Go to Login</a>
                    </div>
                <?php else: ?>
                    <form method="POST" class="mt-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">New Password</label>
                            <div class="input-group">
                                <input type="password" name="password" class="form-control form-control-lg" required minlength="6">
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" class="form-control form-control-lg" required minlength="6">
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success btn-lg w-100 fw-bold">Update Password</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
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
<?php require_once 'includes/footer.php'; ?>
