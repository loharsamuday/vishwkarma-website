<?php
$page_title = "Verify Email";
require_once 'includes/db.php';
require_once 'includes/session.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    $otp = trim($_POST['otp']);

    if (!$user_id || empty($otp)) {
        $error = "Please enter the verification code.";
    } else {
        $stmt = $pdo->prepare("SELECT ev.id, ev.otp, ev.expires_at, u.email, u.first_name FROM email_verifications ev JOIN users u ON u.id = ev.user_id WHERE ev.user_id = ? ORDER BY ev.created_at DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $verification = $stmt->fetch();

        if (!$verification) {
            $error = "Verification code not found. Please register again or contact support.";
        } elseif ($verification['otp'] !== $otp) {
            $error = "The OTP you entered is incorrect.";
        } elseif (new DateTime() > new DateTime($verification['expires_at'])) {
            $error = "This OTP has expired. Please register again to receive a new code.";
        } else {
            $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$user_id]);
            $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?")->execute([$user_id]);

            logActivity('Email verified', 'user', $user_id);
            setFlashMessage('success', 'Your email has been verified. You may now log in.');
            header('Location: login.php');
            exit;
        }
    }
}

$prefilled_user_id = intval($_GET['uid'] ?? $_POST['user_id'] ?? 0);

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container mt-5 mb-5" style="min-height: 60vh;">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h3 class="text-center mb-4 text-warning">Verify Your Email</h3>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <p class="text-muted">We have sent a 6-digit verification code to your email address. Enter it below to complete registration.</p>

                <form method="POST" action="verify-email.php">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($prefilled_user_id) ?>">
                    <div class="mb-3">
                        <label class="form-label">Verification Code</label>
                        <input type="text" name="otp" class="form-control" maxlength="6" required value="<?= htmlspecialchars($_POST['otp'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-warning w-100 fw-bold">Verify Email</button>
                </form>

                <div class="mt-3 text-center">
                    <a href="login.php" class="text-decoration-none text-warning">Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>