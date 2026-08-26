<?php
// forgot-password.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit();
}

$error = '';
$success = '';
$step = 1;

// Handle step 3: Password Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['otp_verified'])) {
        $error = "Session expired or unauthorized. Please try again from the beginning.";
    } else {
        $step = 3;
        $password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Update password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['reset_user_id']]);
                
                // Clear reset session
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_otp']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['otp_verified']);
                
                $success = "Password reset successfully! You can now login with your new password.";
                $step = 4;
            } catch (PDOException $e) {
                $error = "Failed to update password. Please try again.";
            }
        }
    }
}

// Handle step 2: Verify OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $entered_otp = trim($_POST['otp']);
    
    if (!isset($_SESSION['reset_otp'])) {
        $error = "OTP expired. Please request a new one.";
        $step = 1;
    } else {
        if ($entered_otp == $_SESSION['reset_otp']) {
            $_SESSION['otp_verified'] = true;
            $step = 3; // Proceed to set new password
        } else {
            $error = "Invalid OTP. Please try again.";
            $step = 2; // Stay on OTP step
        }
    }
}

// Handle step 1: Send OTP to Email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = "Email Address is required.";
    } else {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch();
            $otp = rand(100000, 999999);
            
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_otp'] = $otp;
            
            // Fetch SMTP settings
            try {
                $stmt_smtp = $pdo->query("SELECT * FROM smtp_settings ORDER BY id DESC LIMIT 1");
                $smtp = $stmt_smtp->fetch();
                
                if ($smtp && !empty($smtp['smtp_user'])) {
                    require_once 'includes/PHPMailer/Exception.php';
                    require_once 'includes/PHPMailer/PHPMailer.php';
                    require_once 'includes/PHPMailer/SMTP.php';
                    
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    
                    $mail->isSMTP();
                    $mail->Host       = $smtp['smtp_host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtp['smtp_user'];
                    $mail->Password   = $smtp['smtp_pass'];
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = $smtp['smtp_port'];
                    
                    $mail->setFrom($smtp['from_email'], $smtp['from_name']);
                    $mail->addAddress($email, $user['name']);
                    
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset OTP Request';
                    
                    // Simple OTP Email Template
                    $body = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                        <h2>Password Reset Request</h2>
                        <p>Hello {$user['name']},</p>
                        <p>We received a request to reset your password. Use the following 6-digit OTP to proceed:</p>
                        <h1 style='color: #2c3e50; font-size: 32px; letter-spacing: 5px; background: #f8f9fa; padding: 15px; border-radius: 8px; display: inline-block;'>{$otp}</h1>
                        <p>If you did not request this, please ignore this email.</p>
                        <p>Thank you.</p>
                    </div>";
                    $mail->Body = $body;
                    $mail->send();
                    
                    $step = 2; // Proceed to OTP verification step
                } else {
                    $error = "SMTP is not configured. Cannot send OTP.";
                }
            } catch (Exception $e) {
                $error = "Failed to send OTP email. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = "No account found with this Email Address.";
        }
    }
}

$page_title = 'Forgot Password';
include 'includes/header.php';
?>

<style>
/* Ultra Professional Compact Styles */
.compact-wrapper {
    min-height: calc(100vh - 76px);
    display: flex;
    align-items: center;
    background: #f8f9fa;
    padding: 2rem 0;
}
.compact-card {
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.06);
    background: #fff;
    border: none;
    max-width: 450px;
    margin: 0 auto;
}
.custom-input {
    border: 2px solid #eef2f5;
    border-radius: 12px;
    padding: 0.8rem 1rem 0.8rem 2.8rem;
    transition: all 0.2s ease;
    background-color: #fcfcfc;
    font-size: 1.05rem;
}
.custom-input:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.15);
    background-color: #fff;
}
.action-btn {
    border-radius: 12px;
    padding: 0.8rem 1.5rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}
.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(var(--bs-primary-rgb), 0.3);
}
.fade-up {
    animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.icon-container {
    width: 60px;
    height: 60px;
    background: rgba(var(--bs-primary-rgb), 0.1);
    color: var(--bs-primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
}
</style>

<div class="compact-wrapper">
    <div class="container fade-up">
        <div class="compact-card p-4 p-md-5">
            
            <div class="text-center mb-4">
                <div class="icon-container">
                    <?php if ($step === 1): ?>
                        <i class="fas fa-envelope fa-2x"></i>
                    <?php elseif ($step === 2): ?>
                        <i class="fas fa-key fa-2x"></i>
                    <?php elseif ($step === 3): ?>
                        <i class="fas fa-unlock-alt fa-2x"></i>
                    <?php elseif ($step === 4): ?>
                        <i class="fas fa-check fa-2x"></i>
                    <?php endif; ?>
                </div>
                <h3 class="fw-bold text-dark mb-2">Reset Password</h3>
                <?php if ($step === 1): ?>
                    <p class="text-muted small">Enter your registered email address to receive an OTP.</p>
                <?php elseif ($step === 2): ?>
                    <p class="text-muted small">We sent a 6-digit OTP to <strong><?= escape($_SESSION['reset_email'] ?? 'your email') ?></strong>.</p>
                <?php elseif ($step === 3): ?>
                    <p class="text-muted small">OTP Verified! Please create your new secure password below.</p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-3 small"><i class="fas fa-exclamation-circle me-2"></i><?= escape($error) ?></div>
            <?php endif; ?>
            
            <?php if ($step === 4): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-3 text-center mb-4">
                    <?= escape($success) ?>
                </div>
                <div class="d-grid">
                    <a href="login.php" class="btn btn-primary bg-primary-custom action-btn border-0 shadow-sm">
                        Back to Login <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            <?php elseif ($step === 3): ?>
                <!-- Step 3: Set New Password -->
                <form method="POST" action="">
                    <input type="hidden" name="action" value="reset_password">
                    
                    <div class="mb-3 position-relative">
                        <i class="fas fa-lock position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                        <input type="password" class="form-control form-control-lg custom-input" id="new_password" name="new_password" placeholder="New Password" required autofocus>
                    </div>
                    
                    <div class="mb-4 position-relative">
                        <i class="fas fa-lock position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                        <input type="password" class="form-control form-control-lg custom-input" id="confirm_password" name="confirm_password" placeholder="Confirm New Password" required>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary bg-primary-custom action-btn border-0 shadow-sm">
                            Update Password <i class="fas fa-check ms-2"></i>
                        </button>
                    </div>
                </form>
            <?php elseif ($step === 2): ?>
                <!-- Step 2: Verify OTP -->
                <form method="POST" action="">
                    <input type="hidden" name="action" value="verify_otp">
                    
                    <div class="mb-4 position-relative">
                        <i class="fas fa-hashtag position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                        <input type="text" class="form-control form-control-lg custom-input text-center fw-bold fs-4" id="otp" name="otp" placeholder="Enter 6-Digit OTP" required autofocus maxlength="6" style="letter-spacing: 5px;">
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary bg-primary-custom action-btn border-0 shadow-sm">
                            Verify OTP <i class="fas fa-shield-alt ms-2"></i>
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <!-- Step 1: Request OTP -->
                <form method="POST" action="">
                    <input type="hidden" name="action" value="send_otp">
                    
                    <div class="mb-4 position-relative">
                        <i class="fas fa-envelope position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                        <input type="email" class="form-control form-control-lg custom-input" id="email" name="email" placeholder="Email Address" required autofocus value="<?= isset($_POST['email']) ? escape($_POST['email']) : '' ?>">
                    </div>
                    
                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-primary bg-primary-custom action-btn border-0 shadow-sm">
                            Send OTP <i class="fas fa-paper-plane ms-2"></i>
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($step === 1 || $step === 2): ?>
            <div class="text-center pt-3 border-top mt-2">
                <p class="text-muted small mb-0">Remembered your password? <a href="login.php" class="text-primary fw-bold text-decoration-none ms-1">Log in here</a></p>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
