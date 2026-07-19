<?php
require_once "includes/db.php";
require_once "includes/session.php";
require_once "vendor/PHPMailer/src/Exception.php";
require_once "vendor/PHPMailer/src/PHPMailer.php";
require_once "vendor/PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$global_settings = getGlobalSettings();

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug = 2; // Enable verbose debug output
    $mail->isSMTP();
    $mail->Host       = $global_settings["smtp_host"];
    $mail->SMTPAuth   = true;
    $mail->Username   = $global_settings["smtp_username"];
    $mail->Password   = $global_settings["smtp_password"];
    $mail->SMTPSecure = !empty($global_settings["smtp_secure"]) && strtolower($global_settings["smtp_secure"]) === "ssl" ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $global_settings["smtp_port"];

    $mail->setFrom($global_settings["smtp_username"], "Test");
    $mail->addAddress("test@example.com");

    $mail->Subject = "Test Email";
    $mail->Body    = "This is a test email.";

    $mail->send();
    echo "Message has been sent";
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
?>
