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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $terms = isset($_POST['terms']);
    
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
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password, is_verified) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password, 0]);
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
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h3 class="text-center mb-4 text-warning">Create an Account</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST" action="register.php">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" pattern="\d{10}" title="Enter exactly 10 digits" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        <small class="text-muted">Enter only 10 digits, no spaces or symbols.</small>
                    </div>

                    <div class="mb-3 border border-warning p-3 rounded bg-light">
                        <label class="form-label fw-bold text-dark mb-2">Register As *</label>
                        <select name="account_type" class="form-select border-warning" required>
                            <option value="general">General Community Member</option>
                            <option value="matrimony">Matrimony User (Looking for Match)</option>
                        </select>
                        <small class="text-muted mt-1 d-block">Select "Matrimony User" to be directed to profile setup after login.</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-check mb-3 text-start">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label small text-muted" for="terms">
                            I agree to the <a href="terms.php" target="_blank" class="text-warning text-decoration-none fw-bold">Terms & Conditions</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100 fw-bold mt-2">Register</button>
                    
                    <p class="text-center mt-3">Already have an account? <a href="login.php" class="text-warning text-decoration-none">Login here</a></p>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
