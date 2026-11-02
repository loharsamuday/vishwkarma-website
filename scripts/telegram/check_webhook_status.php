<?php
// scripts/telegram/check_webhook_status.php
// Webhook Status Checking Script (via getWebhookInfo)
// Usage (CMD):   php scripts/telegram/check_webhook_status.php [--token=123:ABC...]
// Usage (Web):   http://localhost/vishwkarma/scripts/telegram/check_webhook_status.php?secret=YOUR_TG_WORKER_SECRET

require_once __DIR__ . '/_bootstrap.php';

$token = resolveScriptBotToken();

if (empty($token)) {
    outputResult([
        'success' => false,
        'error'   => 'No active bot token found in database (tg_bots) or passed via --token=...'
    ], 'Telegram Webhook Status');
    exit(1);
}

// Fetch bot profile info
$me = callTelegramAPI($token, 'getMe');
$botUsername = !empty($me['ok']) ? '@' . ($me['result']['username'] ?? '') : 'Unknown';

// Fetch Webhook Info
$info = getTelegramWebhookInfo($token);

if (empty($info['ok'])) {
    outputResult([
        'success'     => false,
        'bot'         => $botUsername,
        'error'       => $info['description'] ?? 'Failed to retrieve webhook info'
    ], 'Telegram Webhook Status');
    exit(1);
}

$res = $info['result'] ?? [];
$activeUrl = $res['url'] ?? '';
$lastErrorDate = !empty($res['last_error_date']) ? date('Y-m-d H:i:s', $res['last_error_date']) : null;

outputResult([
    'success'               => true,
    'environment'           => defined('ENVIRONMENT') ? ENVIRONMENT : 'undefined',
    'bot_username'          => $botUsername,
    'webhook_status'        => !empty($activeUrl) ? 'ACTIVE' : 'NOT_SET (Polling Mode)',
    'webhook_url'           => !empty($activeUrl) ? $activeUrl : '(none)',
    'has_custom_certificate'=> $res['has_custom_certificate'] ?? false,
    'pending_update_count'  => $res['pending_update_count'] ?? 0,
    'last_error_date'       => $lastErrorDate,
    'last_error_message'    => $res['last_error_message'] ?? null,
    'max_connections'       => $res['max_connections'] ?? 40,
    'allowed_updates'       => $res['allowed_updates'] ?? ['all'],
], 'Telegram Webhook Status');
