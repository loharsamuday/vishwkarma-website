<?php
// login.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Both email and password are required.";
    } else {
        $stmt = $pdo->prepare("SELECT id, name, password, status FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'blocked') {
                $error = "Your account has been blocked by the administrator.";
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                
                // Redirect based on intent or default
                $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'profile.php';
                header("Location: " . $redirect);
                exit();
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}

$page_title = 'Login';
include 'includes/header.php';
?>

<div class="container my-5 pb-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-0 text-center pt-4 pb-0">
                    <h2 class="fw-bold text-primary-custom">Welcome Back</h2>
                    <p class="text-muted">Log in to your account to continue.</p>
                </div>
                <div class="card-body p-4 p-md-5 pt-3">
                    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'login_required'): ?>
                        <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Please log in to access that page.</div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= escape($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold">Email Address</label>
                            <input type="email" class="form-control form-control-lg bg-light" id="email" name="email" required autofocus value="<?= isset($_POST['email']) ? escape($_POST['email']) : '' ?>">
                        </div>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="password" class="form-label fw-bold mb-0">Password</label>
                                <a href="forgot-password.php" class="text-primary-custom text-decoration-none small fw-bold">Forgot Password?</a>
                            </div>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-lg bg-light border-end-0" id="password" name="password" required>
                                <button class="btn btn-outline-secondary bg-light border border-start-0" type="button" onclick="togglePassword('password', this)">
                                    <i class="fas fa-eye text-muted"></i>
                                </button>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary bg-primary-custom btn-lg shadow-sm">Log In</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer bg-light text-center py-3 border-0 rounded-bottom-4">
                    Don't have an account? <a href="register.php" class="text-primary-custom fw-bold text-decoration-none">Sign up here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
