<?php
// tools/telegram_proxy/php/tg_proxy.php
// Lightweight Reverse Proxy for Telegram Bot API
// Deploy this single file on any server that has direct access to api.telegram.org

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Telegram-Bot-Api-Secret-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$requestUri = $_SERVER['REQUEST_URI'];
// Extract /bot<token>/<method>...
if (!preg_match('#/(bot[^?]+)#', $requestUri, $matches)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'description' => 'Proxy error: missing /bot<token>/<method> path']);
    exit;
}

$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
$telegramUrl = "https://api.telegram.org/" . $matches[1] . $queryString;

$ch = curl_init($telegramUrl);
$rawBody = file_get_contents('php://input');

$headers = [
    'Accept: application/json',
];
if (!empty($_SERVER['CONTENT_TYPE'])) {
    $headers[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => $_SERVER['REQUEST_METHOD'],
    CURLOPT_POSTFIELDS     => $rawBody,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error    = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(502);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'description' => 'Proxy upstream cURL error: ' . $error]);
    exit;
}

http_response_code($httpCode ?: 200);
header('Content-Type: ' . ($contentType ?: 'application/json; charset=UTF-8'));
echo $response;
