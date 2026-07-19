<?php
require_once "includes/db.php";
require_once "includes/session.php";
require_once "vendor/PHPMailer/src/Exception.php";
require_once "vendor/PHPMailer/src/PHPMailer.php";
require_once "vendor/PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}

$email = filter_var(trim($_POST["email"] ?? ""), FILTER_VALIDATE_EMAIL);
$first_name = trim($_POST["first_name"] ?? "User");
$last_name = trim($_POST["last_name"] ?? "");

if (!$email) {
    echo json_encode(["success" => false, "message" => "Please enter a valid email address."]);
    exit;
}

// Check if email already registered and verified
$stmt = $pdo->prepare("SELECT id, is_verified FROM users WHERE email = ?");
$stmt->execute([$email]);
$existing_user = $stmt->fetch();

if ($existing_user && $existing_user["is_verified"] == 1) {
    echo json_encode(["success" => false, "message" => "Email already registered and verified. Please login."]);
    exit;
}

$global_settings = function_exists("getGlobalSettings") ? getGlobalSettings() : [];

if (empty($global_settings["smtp_host"]) || empty($global_settings["smtp_username"]) || empty($global_settings["smtp_password"]) || empty($global_settings["smtp_port"])) {
    echo json_encode(["success" => false, "message" => "SMTP settings are not configured. Verification email cannot be sent."]);
    exit;
}

$otp = random_int(100000, 999999);
$_SESSION["reg_otp"] = $otp;
$_SESSION["reg_email"] = $email;
$_SESSION["reg_email_verified"] = false;

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $global_settings["smtp_host"];
    $mail->SMTPAuth   = true;
    $mail->Username   = $global_settings["smtp_username"];
    $mail->Password   = $global_settings["smtp_password"];
    $mail->SMTPSecure = !empty($global_settings["smtp_secure"]) && strtolower($global_settings["smtp_secure"]) === "ssl" ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $global_settings["smtp_port"];

    $mail->setFrom($global_settings["smtp_username"], defined("SITE_NAME") ? SITE_NAME : "Our Website");
    $mail->addAddress($email, $first_name . " " . $last_name);

    $mail->isHTML(true);
    $mail->Subject = "Verify your email address";
    $mail->Body    = "<h3>Hello " . htmlspecialchars($first_name) . ",</h3>"
        . "<p>Use the following OTP to verify your email address during registration:</p>"
        . "<p style=\"font-size:1.5rem; font-weight:bold; letter-spacing:0.2rem;\">" . $otp . "</p>"
        . "<p>If you did not sign up, please ignore this email.</p>";
    $mail->AltBody = "Your verification code is: " . $otp;

    $mail->send();
    echo json_encode(["success" => true, "message" => "OTP sent successfully."]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Failed to send OTP. Error: " . $mail->ErrorInfo]);
}

