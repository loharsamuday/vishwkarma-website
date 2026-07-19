<?php
$page_title = "Login";
require_once 'includes/db.php';
require_once 'includes/session.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Please enter email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = "<i class='fa-solid fa-user-lock me-1'></i> <strong>Access Denied:</strong> Your account has been suspended by the admin. Please contact support at <a href='mailto:emf998@gmail.com' class='text-danger text-decoration-underline fw-bold'>emf998@gmail.com</a> for further assistance.";
            } elseif (!$user['is_verified'] && (getGlobalSettings()['enable_email_verification'] ?? '1') == '1') {
                $error = "Your email address is not verified. <a href='verify-email.php?uid=".$user['id']."' class='text-danger text-decoration-underline fw-bold'>Click here to verify</a>.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                
                // log login history
                $stmt = $pdo->prepare("INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                
                logActivity('User Logged In', 'user', $user['id']);
                
                setFlashMessage('success', 'Welcome back, ' . htmlspecialchars($user['first_name']) . '!');
                $redirect_url = (isset($_POST['redirect']) && $_POST['redirect'] == 'matrimony') ? 'matrimony-register.php' : 'index.php';
                header("Location: " . $redirect_url);
                exit;
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card card-custom p-4">
                <h3 class="text-center mb-4 text-warning">Login to Your Account</h3>
                
                <?php displayFlashMessage(); ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <form method="POST" action="login.php">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect'] ?? $_POST['redirect'] ?? '') ?>">
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" name="password" class="form-control" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <input type="checkbox" id="remember"> <label for="remember">Remember me</label>
                        </div>
                        <a href="forgot-password.php" class="text-warning text-decoration-none">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100 fw-bold">Login</button>
                    
                    <p class="text-center mt-3">Don't have an account? <a href="register.php" class="text-warning text-decoration-none">Register here</a></p>
                </form>
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
