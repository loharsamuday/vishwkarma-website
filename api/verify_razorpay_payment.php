<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$razorpay_order_id = trim($input['razorpay_order_id'] ?? '');
$razorpay_payment_id = trim($input['razorpay_payment_id'] ?? '');
$razorpay_signature = trim($input['razorpay_signature'] ?? '');
$amount = isset($input['amount']) ? (int) $input['amount'] : 0;

if (empty($razorpay_order_id) || empty($razorpay_payment_id) || empty($razorpay_signature) || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payment verification data is missing.']);
    exit;
}

$global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];
$key_secret = $global_settings['razorpay_key_secret'] ?? '';

if (empty($key_secret)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Razorpay secret is not configured.']);
    exit;
}

$payload = $razorpay_order_id . '|' . $razorpay_payment_id;
$expected_signature = hash_hmac('sha256', $payload, $key_secret);

if (!hash_equals($expected_signature, $razorpay_signature)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Razorpay signature.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, user_id, amount FROM payments WHERE gateway_order_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$razorpay_order_id, $_SESSION['user_id']]);
$payment = $stmt->fetch();

if (!$payment) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Payment record not found.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE payments SET status = 'Success', payment_method = 'razorpay', gateway_payment_id = ?, gateway_signature = ?, gateway_status = 'captured' WHERE id = ?");
    $stmt->execute([$razorpay_payment_id, $razorpay_signature, $payment['id']]);
    $stmt = $pdo->prepare("UPDATE matrimony_profiles SET is_premium = 1 WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'redirect_url' => BASE_URL . 'dashboard.php',
        'message' => 'Payment successful.'
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Payment could not be completed.']);
}
