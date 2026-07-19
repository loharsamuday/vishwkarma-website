<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

require_once '../vendor/PHPMailer/src/Exception.php';
require_once '../vendor/PHPMailer/src/PHPMailer.php';
require_once '../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$smtp_host = $_POST['smtp_host'] ?? '';
$smtp_port = $_POST['smtp_port'] ?? '';
$smtp_username = $_POST['smtp_username'] ?? '';
$smtp_password = $_POST['smtp_password'] ?? '';
$test_email = $_POST['test_email'] ?? $smtp_username;
$test_type = $_POST['test_type'] ?? 'smtp';

if (empty($smtp_host) || empty($smtp_port) || empty($smtp_username) || empty($smtp_password)) {
    echo json_encode(['success' => false, 'message' => 'Please provide all SMTP details (Host, Port, Username, Password).']);
    exit;
}

if (empty($test_email)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a test email address.']);
    exit;
}

try {
    $mail = new PHPMailer(true);
    // $mail->SMTPDebug = 2; // Enable verbose debug output if needed, but it breaks JSON response.
    
    $mail->isSMTP();
    $mail->Host       = $smtp_host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_username;
    $mail->Password   = $smtp_password;
    
    // Assuming global_settings logic or simple fallback. We don't have smtp_secure in the form, 
    // but payments.php uses global_settings['smtp_secure']. Let's use STARTTLS or SMTPS based on port.
    if ($smtp_port == 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    
    $mail->Port       = $smtp_port;

    $mail->setFrom($smtp_username, 'Test User');
    $mail->addAddress($test_email);
    
    $mail->isHTML(true);
    $mail->isHTML(true);
    
    if ($test_type === 'verification') {
        $otp = random_int(100000, 999999);
        $mail->Subject = 'Verify your email address for ' . (defined('SITE_NAME') ? SITE_NAME : 'Our Website');
        $mail->Body    = "<h3>Hello Test User,</h3>"
            . "<p>Thank you for registering with " . (defined('SITE_NAME') ? SITE_NAME : 'Our Website') . ". Use the following OTP to verify your email address:</p>"
            . "<p style='font-size:1.5rem; font-weight:bold; letter-spacing:0.2rem;'>" . $otp . "</p>"
            . "<p>This code will expire in 15 minutes.</p>"
            . "<p>If you did not sign up, please ignore this email.</p>"
            . "<br><p>Regards,<br>" . (defined('SITE_NAME') ? SITE_NAME : 'Team') . "</p>";
        $mail->AltBody = "Your verification code is: " . $otp . ". It expires in 15 minutes.";
    } else {
        $mail->Subject = 'SMTP Test Message';
        $mail->Body    = 'This is a test email to verify SMTP settings. If you receive this, your SMTP configuration is working correctly.';
        $mail->AltBody = 'This is a test email to verify SMTP settings. If you receive this, your SMTP configuration is working correctly.';
    }

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Test email sent successfully!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"]);
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => "An error occurred: " . $e->getMessage()]);
}
