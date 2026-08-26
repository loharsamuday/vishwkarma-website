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

// Handle step 2: Password Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    if (!isset($_SESSION['reset_user_id'])) {
        $error = "Session expired. Please try again.";
    } else {
        $step = 2;
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
                
                $success = "Password reset successfully! You can now login with your new password.";
                $step = 3;
            } catch (PDOException $e) {
                $error = "Failed to update password. Please try again.";
            }
        }
    }
}

// Handle step 1: Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile']);

    if (empty($email) || empty($mobile)) {
        $error = "Both Email and Mobile Number are required.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND mobile = ?");
        $stmt->execute([$email, $mobile]);
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch();
            $_SESSION['reset_user_id'] = $user['id'];
            $step = 2;
        } else {
            $error = "No account found with this Email and Mobile Number combination.";
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
                    <i class="fas <?= $step === 3 ? 'fa-check' : 'fa-unlock-alt' ?> fa-2x"></i>
                </div>
                <h3 class="fw-bold text-dark mb-2">Reset Password</h3>
                <?php if ($step === 1): ?>
                    <p class="text-muted small">Enter your registered email and mobile number to verify your identity.</p>
                <?php elseif ($step === 2): ?>
                    <p class="text-muted small">Identity verified. Please create your new secure password below.</p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-3 small"><i class="fas fa-exclamation-circle me-2"></i><?= escape($error) ?></div>
            <?php endif; ?>
            
            <?php if ($step === 3): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-3 text-center mb-4">
                    <?= escape($success) ?>
                </div>
                <div class="d-grid">
                    <a href="login.php" class="btn btn-primary bg-primary-custom action-btn border-0 shadow-sm">
                        Back to Login <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            <?php elseif ($step === 2): ?>
                <!-- Step 2: Set New Password -->
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
            <?php else: ?>
                <!-- Step 1: Verify Email & Mobile -->
                <form method="POST" action="">
                    <input type="hidden" name="action" value="verify">
                    
                    <div class="mb-3 position-relative">
                        <i class="fas fa-envelope position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                        <input type="email" class="form-control form-control-lg custom-input" id="email" name="email" placeholder="Email Address" required autofocus value="<?= isset($_POST['email']) ? escape($_POST['email']) : '' ?>">
                    </div>
                    
                    <div class="mb-4 position-relative">
                        <i class="fas fa-phone position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                        <input type="text" class="form-control form-control-lg custom-input" id="mobile" name="mobile" placeholder="Mobile Number" required value="<?= isset($_POST['mobile']) ? escape($_POST['mobile']) : '' ?>">
                    </div>
                    
                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-primary bg-primary-custom action-btn border-0 shadow-sm">
                            Verify Identity <i class="fas fa-shield-alt ms-2"></i>
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($step === 1): ?>
            <div class="text-center pt-3 border-top mt-2">
                <p class="text-muted small mb-0">Remembered your password? <a href="login.php" class="text-primary fw-bold text-decoration-none ms-1">Log in here</a></p>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
