<?php
$page_title = "Admin Forgot Password";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = $_SESSION['reset_error'] ?? '';
$success = $_SESSION['reset_success'] ?? '';
unset($_SESSION['reset_error'], $_SESSION['reset_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    // Check if email exists in admin_users
    $stmt = $pdo->prepare("SELECT id, username FROM admin_users WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        $token = bin2hex(random_bytes(32));
        
        // Remove old tokens
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);
        
        // Insert new token
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)");
        $stmt->execute([$email, $token]);
        
        // Send Email
        require_once "../vendor/PHPMailer/src/Exception.php";
        require_once "../vendor/PHPMailer/src/PHPMailer.php";
        require_once "../vendor/PHPMailer/src/SMTP.php";
        
        $global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            if (!empty($global_settings["smtp_host"]) && !empty($global_settings["smtp_username"])) {
                $mail->isSMTP();
                $mail->Host       = $global_settings["smtp_host"];
                $mail->SMTPAuth   = true;
                $mail->Username   = $global_settings["smtp_username"];
                $mail->Password   = $global_settings["smtp_password"];
                $mail->SMTPSecure = (!empty($global_settings["smtp_secure"]) && strtolower($global_settings["smtp_secure"]) === "ssl") ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $global_settings["smtp_port"];
                $mail->setFrom($global_settings["smtp_username"], "Admin Portal");
            } else {
                $mail->isMail();
                $mail->setFrom("noreply@".$_SERVER['HTTP_HOST'], "Admin Portal");
            }
            
            $mail->addAddress($email);
            $mail->isHTML(true);
            
            // Build reset link
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $reset_link = "$protocol://$host$dir/reset-password.php?token=$token&email=" . urlencode($email);
            
            $mail->Subject = "Admin Password Reset Request";
            $mail->Body    = "
                <h3>Password Reset Request</h3>
                <p>Hello {$admin['username']},</p>
                <p>You recently requested to reset your admin panel password. Click the link below to set a new password:</p>
                <p><a href='$reset_link' style='display:inline-block; padding:10px 20px; background:#f39c12; color:#fff; text-decoration:none; border-radius:5px;'>Reset Password</a></p>
                <p>If you did not request this, you can safely ignore this email.</p>
                <p><br>Thanks,<br>Admin Team</p>
            ";
            
            $mail->send();
            $_SESSION['reset_success'] = "A password reset link has been sent to your email address.";
            logActivity("Admin password reset requested", 'system', null, $admin['id']);
        } catch (Exception $e) {
            $_SESSION['reset_error'] = "Could not send reset email. Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        // For security, do not reveal if the email exists or not. Just show success.
        $_SESSION['reset_success'] = "If the email exists in our system, a password reset link has been sent.";
    }
    header("Location: forgot-password.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="card card-custom p-4 p-md-5 mx-3 w-100" style="max-width: 400px;">
        <div class="text-center mb-4">
            <h3 class="text-warning fw-bold">Admin Panel</h3>
            <p class="text-muted small">Forgot Password</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger py-2"><?= $error ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success py-2"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if(!$success || $error): ?>
        <form method="POST">
            <div class="mb-4">
                <label>Registered Admin Email</label>
                <input type="email" name="email" class="form-control" required placeholder="admin@example.com">
                <small class="text-muted d-block mt-2">We will send a reset link to this email.</small>
            </div>
            <button type="submit" class="btn btn-warning w-100 fw-bold mb-3">Send Reset Link</button>
        </form>
        <?php endif; ?>
        
        <div class="text-center">
            <a href="index.php" class="text-decoration-none text-light opacity-75 small"><i class="fa-solid fa-arrow-left me-1"></i> Back to Login</a>
        </div>
    </div>
</body>
</html>
