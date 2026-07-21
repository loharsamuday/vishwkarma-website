<?php
$page_title = "Register";
require_once 'includes/db.php';
require_once 'includes/session.php';

require 'vendor/PHPMailer/src/Exception.php';
require 'vendor/PHPMailer/src/PHPMailer.php';
require 'vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

// CSRF token for form submissions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF validation
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid form submission. Please try again.';
    }

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    // keep only digits for phone
    $phone = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms = isset($_POST['terms']);
    $account_type = in_array($_POST['account_type'] ?? 'general', ['general', 'matrimony']) ? $_POST['account_type'] : 'general';
    
    if (empty($error)) {
        if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password)) {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
            $error = "Phone number must contain exactly 10 digits.";
        } elseif (!$terms) {
            $error = "You must agree to the Terms & Conditions.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = "Password must be at least 8 characters and include letters and numbers.";
        } else {
        // Check if email or phone exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$email, $phone]);
        if ($stmt->fetch()) {
            $error = "Email or phone already registered.";
        } else {
            try {
                $pdo->beginTransaction();

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password, is_verified, account_type) VALUES (?, ?, ?, ?, ?, ?, ?) ");
                $stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password, 0, $account_type]);
                $new_user_id = $pdo->lastInsertId();

                $otp = random_int(100000, 999999);
                $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?")->execute([$new_user_id]);
                $pdo->prepare("INSERT INTO email_verifications (user_id, otp, expires_at) VALUES (?, ?, ?)")->execute([$new_user_id, $otp, $expires_at]);

                if (empty($global_settings['smtp_host']) || empty($global_settings['smtp_username']) || empty($global_settings['smtp_password']) || empty($global_settings['smtp_port'])) {
                    throw new Exception('SMTP settings are not configured. Verification email cannot be sent.');
                }

                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $global_settings['smtp_host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $global_settings['smtp_username'];
                $mail->Password   = $global_settings['smtp_password'];
                $mail->SMTPSecure = !empty($global_settings['smtp_secure']) && strtolower($global_settings['smtp_secure']) === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $global_settings['smtp_port'];

                $mail->setFrom($global_settings['smtp_username'], SITE_NAME);
                $mail->addAddress($email, $first_name . ' ' . $last_name);

                $mail->isHTML(true);
                $mail->Subject = 'Verify your email address for ' . SITE_NAME;
                $mail->Body    = "<h3>Hello " . htmlspecialchars($first_name) . ",</h3>"
                    . "<p>Thank you for registering with " . SITE_NAME . ". Use the following OTP to verify your email address:</p>"
                    . "<p style='font-size:1.5rem; font-weight:bold; letter-spacing:0.2rem;'>" . $otp . "</p>"
                    . "<p>This code will expire in 15 minutes.</p>"
                    . "<p>If you did not sign up, please ignore this email.</p>"
                    . "<br><p>Regards,<br>" . SITE_NAME . " Team</p>";
                $mail->AltBody = "Your verification code is: " . $otp . ". It expires in 15 minutes.";

                $mail->send();

                $pdo->commit();

                logActivity('Registered new account', 'user', $new_user_id);
                setFlashMessage('success', 'Registration successful! Enter the OTP sent to your email to verify your account.');
                header("Location: verify-email.php?uid=" . $new_user_id);
                exit;
            } catch (\Exception $e) {
                $pdo->rollBack();
                $error = "Registration failed. Please try again later.";
            }
        }
    }
}
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .register-wrapper {
        min-height: calc(100vh - 250px);
        display: flex;
        align-items: center;
        background: linear-gradient(135deg, #f3f4f6 0%, #e2e8f0 100%);
        padding: 50px 0;
    }
    .register-card {
        border: none;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        background: #fff;
    }
    .register-left {
        background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
        color: #fff;
        padding: 60px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
    }
    .register-left::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: url('https://www.transparenttextures.com/patterns/cubes.png') repeat;
        opacity: 0.1;
    }
    .register-left * {
        position: relative;
        z-index: 1;
    }
    .register-right {
        padding: 50px 60px;
    }
    .register-right .form-control, .register-right .form-select {
        background-color: #f8f9fa;
        border: 1px solid transparent;
        transition: all 0.3s ease;
    }
    .register-right .form-control:focus, .register-right .form-select:focus {
        background-color: #fff;
        border-color: #6a11cb;
        box-shadow: 0 0 0 0.25rem rgba(106, 17, 203, 0.25);
    }
    .btn-register {
        background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
        border: none;
        color: #fff;
        font-weight: 600;
        transition: all 0.3s ease;
        letter-spacing: 0.5px;
    }
    .btn-register:hover {
        background: linear-gradient(135deg, #5c0eba 0%, #1c62d4 100%);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(37, 117, 252, 0.4);
        color: #fff;
    }
    .text-theme {
        color: #6a11cb;
    }
    .bg-theme-soft {
        background-color: rgba(106, 17, 203, 0.08);
    }
    @media (max-width: 991.98px) {
        .register-right { padding: 40px 30px; }
    }
</style>

<div class="register-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-11">
                <div class="row g-0 register-card">
                    
                    <!-- Left Side Graphic -->
                    <div class="col-lg-5 d-none d-lg-flex register-left text-center">
                        <div class="mb-4">
                            <i class="fa-solid fa-user-plus fa-4x mb-4 text-white" style="filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));"></i>
                            <h2 class="fw-bold mb-3">Join Our Community</h2>
                            <p class="fs-6 opacity-75 px-3">Create an account to connect with people, find matches, and access exclusive features.</p>
                        </div>
                        <div class="mt-auto">
                            <p class="small mb-2 opacity-75">Already have an account?</p>
                            <a href="login.php" class="btn btn-outline-light fw-bold px-4 rounded-pill" style="transition: all 0.3s;">Sign In Now</a>
                        </div>
                    </div>
                    
                    <!-- Right Side Form -->
                    <div class="col-12 col-lg-7 register-right">
                        <div class="text-center mb-4 d-lg-none">
                            <div class="d-inline-flex align-items-center justify-content-center bg-theme-soft text-theme rounded-circle mb-3" style="width: 60px; height: 60px;">
                                <i class="fa-solid fa-user-plus fa-2x"></i>
                            </div>
                            <h3 class="fw-bold mb-1">Create an Account</h3>
                            <p class="text-muted small">Join our community today</p>
                        </div>
                        
                        <div class="d-none d-lg-block mb-4 pb-2">
                            <h3 class="fw-bold text-dark mb-1">Create an Account</h3>
                            <p class="text-muted small">Please fill in the details below to register</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger d-flex align-items-center rounded-3 small">
                                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                                <div><?= htmlspecialchars($error) ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="register.php" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" name="first_name" class="form-control rounded-3" id="floatingFirstName" placeholder="First Name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                                        <label for="floatingFirstName" class="text-muted"><i class="fa-regular fa-user me-1"></i> First Name</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" name="last_name" class="form-control rounded-3" id="floatingLastName" placeholder="Last Name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                                        <label for="floatingLastName" class="text-muted"><i class="fa-regular fa-user me-1"></i> Last Name</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="email" name="email" class="form-control rounded-3" id="floatingEmail" placeholder="name@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                        <label for="floatingEmail" class="text-muted"><i class="fa-regular fa-envelope me-1"></i> Email Address</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" name="phone" class="form-control rounded-3" id="floatingPhone" placeholder="Phone Number" pattern="\d{10}" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '');" title="Enter exactly 10 digits" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                        <label for="floatingPhone" class="text-muted"><i class="fa-solid fa-phone me-1"></i> Phone Number</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4 border p-3 rounded-4 bg-theme-soft" style="border-color: rgba(106, 17, 203, 0.15) !important;">
                                <label class="form-label fw-bold text-dark mb-2"><i class="fa-solid fa-users me-1 text-theme"></i> Register As *</label>
                                <select name="account_type" class="form-select rounded-3 py-2 border-0 shadow-sm" required>
                                    <option value="general" <?= (($_POST['account_type'] ?? '') === 'general') ? 'selected' : '' ?>>General Community Member</option>
                                    <option value="matrimony" <?= (($_POST['account_type'] ?? '') === 'matrimony') ? 'selected' : '' ?>>Matrimony User (Looking for Match)</option>
                                </select>
                                <small class="text-muted mt-2 d-block"><i class="fa-solid fa-arrow-right text-theme me-1"></i> Select "Matrimony User" to be directed to profile setup after login.</small>
                            </div>
                            
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="password" name="password" class="form-control rounded-3" id="floatingPassword" placeholder="Password" required aria-describedby="pwdHelp">
                                        <label for="floatingPassword" class="text-muted"><i class="fa-solid fa-lock me-1"></i> Password</label>
                                    </div>
                                    <div id="pwdHelp" class="form-text small text-muted mt-1 px-1">Min 8 chars, include letters & numbers.</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="password" name="confirm_password" class="form-control rounded-3" id="floatingConfirmPassword" placeholder="Confirm Password" required>
                                        <label for="floatingConfirmPassword" class="text-muted"><i class="fa-solid fa-lock me-1"></i> Confirm Password</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input shadow-none" type="checkbox" id="terms" name="terms" required style="cursor: pointer;">
                                <label class="form-check-label small text-muted user-select-none" for="terms" style="cursor: pointer;">
                                    I agree to the <a href="terms.php" target="_blank" class="text-theme text-decoration-none fw-bold">Terms & Conditions</a>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-register w-100 py-3 rounded-pill shadow-sm mb-3">
                                <i class="fa-solid fa-user-check me-2"></i> Create Account
                            </button>
                            
                            <p class="text-center mb-0 text-muted small d-lg-none mt-3">
                                Already have an account? <a href="login.php" class="text-theme text-decoration-none fw-bold">Login here</a>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
