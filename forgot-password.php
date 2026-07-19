<?php
$page_title = "Forgot Password";
require_once 'includes/db.php';
require_once 'includes/session.php';

require 'vendor/PHPMailer/src/Exception.php';
require 'vendor/PHPMailer/src/PHPMailer.php';
require 'vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        $stmt = $pdo->prepare("SELECT id, first_name FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $token = bin2hex(random_bytes(32));
            
            // Delete old tokens for this email
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            
            // Insert new token
            $pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)")->execute([$email, $token]);
            
            $reset_link = BASE_URL . "reset-password.php?token=" . $token . "&email=" . urlencode($email);
            
            // Send Email
            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = $global_settings['smtp_host'] ?? '';
                $mail->SMTPAuth   = true;
                $mail->Username   = $global_settings['smtp_username'] ?? '';
                $mail->Password   = $global_settings['smtp_password'] ?? '';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $global_settings['smtp_port'] ?? 587;

                // Recipients
                $mail->setFrom($global_settings['smtp_username'] ?? 'no-reply@vishwkarma.com', SITE_NAME);
                $mail->addAddress($email, $user['first_name']);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request - ' . SITE_NAME;
                $mail->Body    = "
                    <h3>Hello {$user['first_name']},</h3>
                    <p>We received a request to reset your password. Click the link below to set a new password:</p>
                    <p><a href='{$reset_link}' style='padding: 10px 20px; background: #f39c12; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                    <p>If you didn't request this, you can safely ignore this email.</p>
                    <br>
                    <p>Regards,<br>The " . SITE_NAME . " Team</p>
                ";
                $mail->AltBody = "Click here to reset your password: {$reset_link}";

                $mail->send();
                $success = "A password reset link has been sent to your email address.";
            } catch (Exception $e) {
                $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}. Please contact the administrator.";
            }
        } else {
            // Do not reveal if the email exists or not to prevent enumeration
            $success = "If your email is registered, you will receive a reset link shortly.";
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<div class="container mt-5 mb-5" style="min-height: 50vh;">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card card-custom p-5 text-center shadow-sm border-top border-4 border-warning">
                <i class="fa-solid fa-lock fa-4x text-warning mb-4"></i>
                <h3 class="fw-bold mb-3">Forgot Your Password?</h3>
                <p class="text-muted">Enter your registered email address below and we'll send you a link to reset your password.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger text-start"><?= $error ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success text-start"><?= $success ?></div>
                <?php else: ?>
                    <form method="POST" class="text-start mt-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-lg" required>
                        </div>
                        <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold">Send Reset Link</button>
                    </form>
                <?php endif; ?>
                
                <div class="mt-4">
                    <a href="login.php" class="text-secondary text-decoration-none"><i class="fa-solid fa-arrow-left me-2"></i> Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
