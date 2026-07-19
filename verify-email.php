<?php
$page_title = "Verify Email";
require_once 'includes/db.php';
require_once 'includes/session.php';

require_once 'vendor/PHPMailer/src/Exception.php';
require_once 'vendor/PHPMailer/src/PHPMailer.php';
require_once 'vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    
    if (isset($_POST['action']) && $_POST['action'] === 'resend_otp') {
        if (!$user_id) {
            $error = "Invalid user for OTP resend.";
        } else {
            $stmt = $pdo->prepare("SELECT email, first_name, last_name, is_verified FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error = "User not found.";
            } elseif ($user['is_verified']) {
                $error = "User is already verified.";
            } else {
                try {
                    $otp = random_int(100000, 999999);
                    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    
                    $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?")->execute([$user_id]);
                    $pdo->prepare("INSERT INTO email_verifications (user_id, otp, expires_at) VALUES (?, ?, ?)")->execute([$user_id, $otp, $expires_at]);
                    
                    $global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];
                    
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
                    $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);
                    
                    $mail->isHTML(true);
                    $mail->Subject = 'New OTP for ' . SITE_NAME;
                    $mail->Body    = "<h3>Hello " . htmlspecialchars($user['first_name']) . ",</h3>"
                        . "<p>You requested a new verification code. Use the following OTP to verify your email address:</p>"
                        . "<p style='font-size:1.5rem; font-weight:bold; letter-spacing:0.2rem;'>" . $otp . "</p>"
                        . "<p>This code will expire in 15 minutes.</p>"
                        . "<br><p>Regards,<br>" . SITE_NAME . " Team</p>";
                    $mail->AltBody = "Your new verification code is: " . $otp . ". It expires in 15 minutes.";
                    
                    $mail->send();
                    setFlashMessage('success', 'A new OTP has been sent to your email.');
                    header("Location: verify-email.php?uid=" . $user_id);
                    exit;
                } catch (Exception $e) {
                    $error = "Failed to resend OTP: " . $e->getMessage();
                }
            }
        }
    } else {
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

                <form method="POST" action="verify-email.php" class="mt-3">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($prefilled_user_id) ?>">
                    <input type="hidden" name="action" value="resend_otp">
                    <p class="text-muted text-center mb-2" style="font-size: 0.9rem;">Didn't receive the OTP?</p>
                    <button type="submit" class="btn btn-outline-secondary w-100 btn-sm">Resend OTP</button>
                </form>

                <div class="mt-4 text-center">
                    <a href="login.php" class="text-decoration-none text-warning fw-bold"><i class="fa-solid fa-arrow-left me-1"></i> Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>