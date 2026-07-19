<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$amount = isset($input['amount']) ? (int) $input['amount'] : 0;
$description = trim($input['description'] ?? 'Premium Membership Upgrade');

$global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];
$key_id = $global_settings['razorpay_key_id'] ?? '';
$key_secret = $global_settings['razorpay_key_secret'] ?? '';

if (empty($key_id) || empty($key_secret) || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Razorpay is not configured correctly.']);
    exit;
}

$receipt = 'premium_' . $_SESSION['user_id'] . '_' . time();
$payload = json_encode([
    'amount' => $amount,
    'currency' => $global_settings['razorpay_currency'] ?? 'INR',
    'receipt' => $receipt,
    'payment_capture' => 1,
    'notes' => [
        'user_id' => $_SESSION['user_id'],
        'description' => $description,
    ],
]);

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_USERPWD, $key_id . ':' . $key_secret);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to connect to Razorpay.']);
    exit;
}

$result = json_decode($response, true);
if ($http_code >= 400 || empty($result['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $result['error']['description'] ?? 'Unable to create Razorpay order.']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO payments (user_id, amount, payment_type, transaction_id, status, payment_method, gateway_order_id, gateway_payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([
    $_SESSION['user_id'],
    $amount / 100,
    'Subscription',
    $receipt,
    'Pending',
    'razorpay',
    $result['id'],
    $response,
]);

echo json_encode([
    'success' => true,
    'order_id' => $result['id'],
    'amount' => $result['amount'],
    'currency' => $result['currency'],
    'description' => $description,
]);
