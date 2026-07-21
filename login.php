<?php
$page_title = "Login";
require_once 'includes/db.php';
require_once 'includes/session.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];
$google_client_id = trim($global_settings['google_client_id'] ?? '') ?: (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '');
$google_enabled = $google_client_id && $google_client_id !== 'YOUR_GOOGLE_OAUTH_CLIENT_ID';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $google_id_token = trim($_POST['google_id_token'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect_url = (isset($_POST['redirect']) && $_POST['redirect'] == 'matrimony') ? 'matrimony-register.php' : 'index.php';

    if (!empty($google_id_token)) {
        $payload = verifyGoogleIdToken($google_id_token);
        if (!$payload) {
            $error = "Google authentication failed. Please try again.";
        } else {
            $email = $payload['email'] ?? $email;
            $first_name = $payload['given_name'] ?? '';
            $last_name = $payload['family_name'] ?? '';
            $profile_pic = $payload['picture'] ?? null;
            $provider = GOOGLE_OAUTH_PROVIDER;
            $provider_id = $payload['sub'] ?? null;

            if (empty($email)) {
                $error = "Google did not provide an email address. Please use a different login method.";
            } else {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && $user['status'] !== 'active') {
                    $error = "Your account is currently disabled. Please contact support.";
                } elseif ($user && $user['provider'] && $user['provider'] !== GOOGLE_OAUTH_PROVIDER) {
                    $error = "This email is already registered with a different login method.";
                } elseif ($user) {
                    $stmt = $pdo->prepare("UPDATE users SET provider = ?, provider_id = ?, profile_pic = ?, is_verified = 1, first_name = ?, last_name = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$provider, $provider_id, $profile_pic, $first_name, $last_name, $user['id']]);
                    $user['provider'] = $provider;
                    $user['provider_id'] = $provider_id;
                    $user['profile_pic'] = $profile_pic;
                    $user['first_name'] = $first_name;
                    $user['last_name'] = $last_name;
                    completeUserLogin($user);
                    header("Location: " . $redirect_url);
                    exit;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role_id, is_verified, provider, provider_id, profile_pic, declaration_accepted, declaration_datetime, declaration_ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $first_name,
                        $last_name,
                        $email,
                        null,
                        null,
                        2,
                        1,
                        $provider,
                        $provider_id,
                        $profile_pic,
                        1,
                        date('Y-m-d H:i:s'),
                        $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
                    ]);
                    $userId = $pdo->lastInsertId();
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $newUser = $stmt->fetch();
                    completeUserLogin($newUser);
                    header("Location: " . $redirect_url);
                    exit;
                }
            }
        }
    } else {
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
                    
                    $stmt = $pdo->prepare("INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
                    $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                    
                    logActivity('User Logged In', 'user', $user['id']);
                    
                    setFlashMessage('success', 'Welcome back, ' . htmlspecialchars($user['first_name']) . '!');
                    header("Location: " . $redirect_url);
                    exit;
                }
            } else {
                $error = "Invalid email or password.";
            }
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .login-wrapper {
        min-height: calc(100vh - 250px);
        display: flex;
        align-items: center;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 50px 0;
    }
    .login-card {
        border: none;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        background: #fff;
    }
    .login-left {
        background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        color: #fff;
        padding: 60px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
    }
    .login-left::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: url('https://www.transparenttextures.com/patterns/cubes.png') repeat;
        opacity: 0.1;
    }
    .login-left * {
        position: relative;
        z-index: 1;
    }
    .login-right {
        padding: 60px;
    }
    .login-right .form-control {
        background-color: #f8f9fa;
        border: 1px solid transparent;
        transition: all 0.3s ease;
    }
    .login-right .form-control:focus {
        background-color: #fff;
        border-color: #ffc107;
        box-shadow: 0 0 0 0.25rem rgba(255, 193, 7, 0.25);
    }
    .btn-login {
        background: #ffc107;
        border: none;
        color: #000;
        font-weight: 600;
        transition: all 0.3s ease;
        letter-spacing: 0.5px;
    }
    .btn-login:hover {
        background: #ffb300;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(255, 193, 7, 0.4);
    }
    .social-login-text {
        position: relative;
        text-align: center;
        margin: 25px 0;
    }
    .social-login-text::before, .social-login-text::after {
        content: "";
        position: absolute;
        top: 50%;
        width: 35%;
        height: 1px;
        background-color: #e0e0e0;
    }
    .social-login-text::before { left: 0; }
    .social-login-text::after { right: 0; }
    
    @media (max-width: 991.98px) {
        .login-right { padding: 40px 30px; }
    }
</style>

<div class="login-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-10 col-xl-9">
                <div class="row g-0 login-card">
                    
                    <!-- Left Side Graphic -->
                    <div class="col-lg-5 d-none d-lg-flex login-left text-center">
                        <div class="mb-4">
                            <i class="fa-solid fa-users-rays fa-4x mb-4 text-white" style="filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));"></i>
                            <h2 class="fw-bold mb-3">Welcome Back!</h2>
                            <p class="fs-6 opacity-75 px-3">Connect with your community, find matches, and explore new opportunities tailored just for you.</p>
                        </div>
                        <div class="mt-auto">
                            <p class="small mb-2 opacity-75">Don't have an account?</p>
                            <a href="register.php" class="btn btn-outline-light fw-bold px-4 rounded-pill" style="transition: all 0.3s;">Create Account</a>
                        </div>
                    </div>
                    
                    <!-- Right Side Form -->
                    <div class="col-12 col-lg-7 login-right">
                        <div class="text-center mb-4 d-lg-none">
                            <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle mb-3" style="width: 60px; height: 60px;">
                                <i class="fa-solid fa-user fa-2x"></i>
                            </div>
                            <h3 class="fw-bold mb-1">Welcome Back!</h3>
                            <p class="text-muted small">Please login to your account</p>
                        </div>
                        
                        <div class="d-none d-lg-block mb-4 pb-2">
                            <h3 class="fw-bold text-dark mb-1">Sign In</h3>
                            <p class="text-muted small">Enter your credentials to access your account</p>
                        </div>

                        <?php displayFlashMessage(); ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger d-flex align-items-center rounded-3 small">
                                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                                <div><?= $error ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="login.php" id="loginForm">
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect'] ?? $_POST['redirect'] ?? '') ?>">
                            <input type="hidden" name="google_id_token" id="google_id_token" value="">
        
                            <div class="form-floating mb-3">
                                <input type="email" name="email" class="form-control rounded-3" id="floatingEmail" placeholder="name@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                <label for="floatingEmail" class="text-muted"><i class="fa-regular fa-envelope me-1"></i> Email address</label>
                            </div>
                            
                            <div class="form-floating mb-3 position-relative">
                                <input type="password" name="password" class="form-control rounded-3" id="floatingPassword" placeholder="Password" required>
                                <label for="floatingPassword" class="text-muted"><i class="fa-solid fa-lock me-1"></i> Password</label>
                                <button class="btn border-0 position-absolute top-50 end-0 translate-middle-y toggle-password text-muted" type="button" tabindex="-1" style="z-index: 10;">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-4 pb-1">
                                <div class="form-check">
                                    <input class="form-check-input shadow-none" type="checkbox" id="remember"> 
                                    <label class="form-check-label text-muted small user-select-none" for="remember">Remember me</label>
                                </div>
                                <a href="forgot-password.php" class="text-warning text-decoration-none small fw-bold">Forgot Password?</a>
                            </div>
                            
                            <button type="submit" class="btn btn-login w-100 py-3 rounded-pill mb-3">
                                <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Login
                            </button>

                            <?php if ($google_enabled): ?>
                                <div class="social-login-text text-muted small fw-bold">OR SIGN IN WITH</div>
                                <div class="mb-3 d-flex justify-content-center">
                                    <div id="googleLoginButton"></div>
                                </div>
                            <?php else: ?>
                                <div class="mt-4 mb-2 text-center">
                                    <div class="alert alert-secondary py-2 rounded-3 small mb-0 border-0 bg-light text-muted">
                                        <i class="fa-solid fa-info-circle me-1"></i> Google login config missing.
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <p class="text-center mb-0 text-muted small d-lg-none mt-4">
                                Don't have an account? <a href="register.php" class="text-warning text-decoration-none fw-bold">Register here</a>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const input = this.parentElement.querySelector('input');
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

<?php if ($google_enabled): ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
function decodeJwtResponse(token) {
    const base64Url = token.split('.')[1];
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
    const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
        return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
    }).join(''));
    return JSON.parse(jsonPayload);
}

window.handleGoogleCredentialResponse = function(response) {
    if (!response || !response.credential) {
        return;
    }
    const payload = decodeJwtResponse(response.credential);
    document.getElementById('google_id_token').value = response.credential;
    document.querySelector('input[name="email"]').value = payload.email || '';

    document.getElementById('loginForm').submit();
};

window.onload = function() {
    google.accounts.id.initialize({
        client_id: '<?= htmlspecialchars($google_client_id) ?>',
        callback: handleGoogleCredentialResponse,
        ux_mode: 'popup'
    });
    google.accounts.id.renderButton(
        document.getElementById('googleLoginButton'),
        {
            theme: 'outline',
            size: 'large',
            type: 'standard',
            shape: 'rectangular',
            text: 'signin_with',
            logo_alignment: 'left'
        }
    );
};
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
