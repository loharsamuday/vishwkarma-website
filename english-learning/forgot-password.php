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

<div class="container my-5 pb-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-0 text-center pt-4 pb-0">
                    <h2 class="fw-bold text-primary-custom">Reset Password</h2>
                    <?php if ($step === 1): ?>
                        <p class="text-muted">Enter your registered email and mobile number to verify your identity.</p>
                    <?php elseif ($step === 2): ?>
                        <p class="text-muted">Identity verified. Please enter your new password below.</p>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4 p-md-5 pt-3">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= escape($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($step === 3): ?>
                        <div class="alert alert-success text-center">
                            <i class="fas fa-check-circle fa-3x mb-3"></i><br>
                            <?= escape($success) ?>
                        </div>
                        <div class="d-grid mt-4">
                            <a href="login.php" class="btn btn-primary bg-primary-custom btn-lg shadow-sm">Go to Login</a>
                        </div>
                    <?php elseif ($step === 2): ?>
                        <!-- Step 2: Set New Password -->
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="reset_password">
                            <div class="mb-3">
                                <label for="new_password" class="form-label fw-bold">New Password</label>
                                <input type="password" class="form-control form-control-lg bg-light" id="new_password" name="new_password" required>
                            </div>
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-bold">Confirm New Password</label>
                                <input type="password" class="form-control form-control-lg bg-light" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary bg-primary-custom btn-lg shadow-sm">Update Password</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Step 1: Verify Email & Mobile -->
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="verify">
                            <div class="mb-3">
                                <label for="email" class="form-label fw-bold">Registered Email Address</label>
                                <input type="email" class="form-control form-control-lg bg-light" id="email" name="email" required value="<?= isset($_POST['email']) ? escape($_POST['email']) : '' ?>">
                            </div>
                            <div class="mb-4">
                                <label for="mobile" class="form-label fw-bold">Registered Mobile Number</label>
                                <input type="text" class="form-control form-control-lg bg-light" id="mobile" name="mobile" required value="<?= isset($_POST['mobile']) ? escape($_POST['mobile']) : '' ?>" placeholder="10-digit mobile number">
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary bg-primary-custom btn-lg shadow-sm">Verify Identity</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                
                <?php if ($step === 1): ?>
                <div class="card-footer bg-light text-center py-3 border-0 rounded-bottom-4">
                    Remembered your password? <a href="login.php" class="text-primary-custom fw-bold text-decoration-none">Log in here</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
