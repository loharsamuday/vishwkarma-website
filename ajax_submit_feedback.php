<?php
require_once "includes/db.php";
require_once "includes/session.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}

$rating = (int)($_POST['rating'] ?? 5);
$feedback_type = trim($_POST['feedback_type'] ?? '');
$message = trim($_POST['message'] ?? '');
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (empty($feedback_type) || empty($message)) {
    echo json_encode(["success" => false, "message" => "Feedback type and message are required."]);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(["success" => false, "message" => "Invalid rating value."]);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO feedbacks (user_id, rating, feedback_type, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $rating, $feedback_type, $message]);
    
    echo json_encode(["success" => true, "message" => "Thank you! Your feedback has been submitted successfully."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "A system error occurred. Please try again."]);
}
