<?php
$page_title = "Admin Reset Password";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// Verify Token
$valid_token = false;
if (!empty($token) && !empty($email)) {
    // Basic check for token existence. In production, check expiry (e.g. 1 hour).
    // Using created_at column for 1 hour expiry check:
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute([$email, $token]);
    if ($stmt->rowCount() > 0) {
        $valid_token = true;
    } else {
        $error = "Invalid or expired password reset link. Please request a new one.";
    }
} else {
    $error = "Missing token or email parameters.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Hash and update
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE email = ?");
        if ($stmt->execute([$hashed_password, $email])) {
            // Delete the token so it can't be reused
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);
            
            // Log it if possible, need to get admin ID
            $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE email = ?");
            $stmt->execute([$email]);
            $admin_id = $stmt->fetchColumn();
            if ($admin_id) {
                logActivity("Admin password successfully reset via email link", 'system', null, $admin_id);
            }
            
            $success = "Your password has been successfully reset! You can now login with your new password.";
            $valid_token = false; // Hide the form
        } else {
            $error = "An error occurred while updating the password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="card card-custom p-4 p-md-5 mx-3 w-100" style="max-width: 400px;">
        <div class="text-center mb-4">
            <h3 class="text-warning fw-bold">Admin Panel</h3>
            <p class="text-muted small">Set New Password</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger py-2"><?= $error ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success py-2"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if($valid_token): ?>
        <form method="POST">
            <div class="mb-3">
                <label>New Password</label>
                <div class="input-group">
                    <input type="password" name="password" class="form-control" required minlength="6">
                    <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>
            <div class="mb-4">
                <label>Confirm Password</label>
                <div class="input-group">
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>
            <button type="submit" class="btn btn-warning w-100 fw-bold mb-3">Reset Password</button>
        </form>
        <?php endif; ?>
        
        <div class="text-center mt-2">
            <a href="index.php" class="text-decoration-none text-light opacity-75 small"><i class="fa-solid fa-arrow-left me-1"></i> Back to Login</a>
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
</body>
</html>
