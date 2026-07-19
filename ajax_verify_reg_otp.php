<?php
require_once "includes/session.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}

$email = filter_var(trim($_POST["email"] ?? ""), FILTER_VALIDATE_EMAIL);
$otp = trim($_POST["otp"] ?? "");

if (!$email || empty($otp)) {
    echo json_encode(["success" => false, "message" => "Please provide both email and OTP."]);
    exit;
}

if (!isset($_SESSION["reg_otp"]) || !isset($_SESSION["reg_email"])) {
    echo json_encode(["success" => false, "message" => "OTP session expired. Please request a new OTP."]);
    exit;
}

if ($_SESSION["reg_email"] !== $email) {
    echo json_encode(["success" => false, "message" => "Email address mismatch."]);
    exit;
}

if ((string)$_SESSION["reg_otp"] === (string)$otp) {
    $_SESSION["reg_email_verified"] = true;
    echo json_encode(["success" => true, "message" => "Email verified successfully!"]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid OTP. Please try again."]);
}

