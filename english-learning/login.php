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

<style>
/* Ultra Professional Login Styles */
.login-wrapper {
    min-height: calc(100vh - 76px); /* Adjust based on navbar height */
    display: flex;
    align-items: center;
    background: #f8f9fa;
}
.login-card {
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
    transition: transform 0.3s ease;
    background: #fff;
}
.login-left {
    background: linear-gradient(135deg, var(--bs-primary) 0%, #1a237e 100%);
    color: white;
    padding: 3rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    overflow: hidden;
}
.login-left::after {
    content: '';
    position: absolute;
    bottom: -50px;
    right: -50px;
    width: 250px;
    height: 250px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}
.login-left::before {
    content: '';
    position: absolute;
    top: -50px;
    left: -50px;
    width: 150px;
    height: 150px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}
.login-right {
    padding: 3.5rem;
}
.custom-input {
    border: 2px solid #eef2f5;
    border-radius: 12px;
    padding: 0.8rem 2.5rem 0.8rem 2.8rem;
    transition: all 0.2s ease;
    background-color: #fcfcfc;
    font-size: 1.05rem;
}
.custom-input:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.15);
    background-color: #fff;
}
.login-btn {
    border-radius: 12px;
    padding: 0.8rem 1.5rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}
.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(var(--bs-primary-rgb), 0.3);
}
/* Hide native Edge/IE password reveal eye */
input::-ms-reveal,
input::-ms-clear {
    display: none;
}
.toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #6c757d;
    z-index: 5;
    padding: 10px;
}
.toggle-password:hover {
    color: var(--bs-primary);
}
.fade-up {
    animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 767.98px) {
    .login-left { display: none !important; }
    .login-right { padding: 2rem 1.5rem; }
}
</style>

<div class="login-wrapper">
    <div class="container fade-up py-4">
        <div class="row justify-content-center">
            <div class="col-xl-9 col-lg-10">
                <div class="login-card row g-0">
                    
                    <!-- Left Branding Side -->
                    <div class="col-md-5 login-left d-none d-md-flex">
                        <div class="position-relative z-1">
                            <h2 class="fw-bold mb-4 display-6">Unlock Your<br>Potential</h2>
                            <p class="lead opacity-75 mb-4" style="font-size: 1.1rem;">Log in to access your daily routines, track your study hours, and master the English language.</p>
                            
                            <div class="d-flex align-items-center mt-5">
                                <i class="fas fa-quote-left opacity-50 me-3 fa-2x"></i>
                                <p class="mb-0 fst-italic fw-medium">"Learning is a treasure that will follow its owner everywhere."</p>
                            </div>
                        </div>
                    </div>

                    <!-- Right Form Side -->
                    <div class="col-md-7 login-right bg-white">
                        <div class="mb-4">
                            <h3 class="fw-bold text-dark mb-1">Welcome Back</h3>
                            <p class="text-muted">Please enter your details to sign in.</p>
                        </div>
                        
                        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'login_required'): ?>
                            <div class="alert alert-info border-0 shadow-sm rounded-3"><i class="fas fa-info-circle me-2"></i>Please log in to access that page.</div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="fas fa-exclamation-circle me-2"></i><?= escape($error) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-4 position-relative">
                                <i class="fas fa-envelope position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                                <input type="email" class="form-control form-control-lg custom-input" id="email" name="email" placeholder="Email Address" required autofocus value="<?= isset($_POST['email']) ? escape($_POST['email']) : '' ?>">
                            </div>
                            
                            <div class="mb-4 position-relative">
                                <i class="fas fa-lock position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                                <input type="password" class="form-control form-control-lg custom-input" id="password" name="password" placeholder="Password" required>
                                <i class="fas fa-eye toggle-password" id="togglePasswordIcon" onclick="togglePassword('password', this)"></i>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input shadow-sm" type="checkbox" id="rememberMe">
                                    <label class="form-check-label text-muted small" for="rememberMe">
                                        Remember me
                                    </label>
                                </div>
                                <a href="forgot-password.php" class="text-primary text-decoration-none small fw-bold">Forgot Password?</a>
                            </div>

                            <div class="d-grid mb-4">
                                <button type="submit" class="btn btn-primary bg-primary-custom login-btn border-0 shadow-sm">
                                    Sign In <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4 pt-2 border-top">
                            <p class="text-muted mb-0">Don't have an account? <a href="register.php" class="text-primary fw-bold text-decoration-none ms-1">Create one now</a></p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, icon) {
    const input = document.getElementById(inputId);
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
