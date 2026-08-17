<?php
// subscribe.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only accept AJAX requests for simple response formatting
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Email is required.']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address.']);
        exit;
    }
    
    try {
        // Check if already subscribed
        $stmt = $pdo->prepare("SELECT id FROM subscribers WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'You are already subscribed!']);
        } else {
            // Add subscriber
            $stmt = $pdo->prepare("INSERT INTO subscribers (email) VALUES (?)");
            $stmt->execute([$email]);
            echo json_encode(['status' => 'success', 'message' => 'Thank you for subscribing!']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again later.']);
    }
    exit;
}

// Direct access not allowed
header("Location: index.php");
exit();
?>
