<?php
// scripts/telegram/register_local_webhook.php
// Localhost (Tunnel) Webhook Registration Script (via setWebhook)
// Usage (CMD):   php scripts/telegram/register_local_webhook.php https://YOUR_TUNNEL_SUBDOMAIN.ngrok-free.app/vishwkarma/ [--token=123:ABC...]
// Usage (Web):   http://localhost/vishwkarma/scripts/telegram/register_local_webhook.php?tunnel_url=https://YOUR_TUNNEL_SUBDOMAIN.ngrok-free.app/vishwkarma/&secret=YOUR_TG_WORKER_SECRET

require_once __DIR__ . '/_bootstrap.php';

$token = resolveScriptBotToken();

if (empty($token)) {
    outputResult([
        'success' => false,
        'error'   => 'No active bot token found in database (tg_bots) or passed via --token=...'
    ], 'Register Localhost Webhook');
    exit(1);
}

// 1. Resolve tunnel URL from CLI, GET, or config
$tunnelUrl = '';
if (php_sapi_name() === 'cli' && isset($argv) && is_array($argv)) {
    foreach ($argv as $i => $arg) {
        if ($i > 0 && strpos($arg, '--') !== 0) {
            $tunnelUrl = trim($arg);
            break;
        }
    }
} elseif (isset($_GET['tunnel_url']) && !empty(trim($_GET['tunnel_url']))) {
    $tunnelUrl = trim($_GET['tunnel_url']);
} elseif (defined('LOCAL_TUNNEL_URL') && !empty(LOCAL_TUNNEL_URL)) {
    $tunnelUrl = LOCAL_TUNNEL_URL;
}

// 2. Validate tunnel URL
if (empty($tunnelUrl)) {
    outputResult([
        'success' => false,
        'error'   => 'Missing tunnel URL. Telegram Webhooks require a public HTTPS URL (e.g. from Ngrok, Cloudflare Tunnel, or Localtunnel).',
        'usage'   => [
            'CMD'    => 'php scripts/telegram/register_local_webhook.php https://your-subdomain.ngrok-free.app/vishwkarma/ [--token=...]',
            'Web'    => 'http://localhost/vishwkarma/scripts/telegram/register_local_webhook.php?tunnel_url=https://your-subdomain.ngrok-free.app/vishwkarma/&secret=' . (defined('TG_WORKER_SECRET') ? TG_WORKER_SECRET : ''),
            'Config' => "Or define('LOCAL_TUNNEL_URL', 'https://your-subdomain.ngrok-free.app/vishwkarma/') in config/config.php"
        ]
    ], 'Register Localhost Webhook');
    exit(1);
}

if (stripos($tunnelUrl, 'https://') !== 0) {
    outputResult([
        'success' => false,
        'error'   => "Telegram Webhook requires HTTPS. Provided: {$tunnelUrl}. Please use an HTTPS tunnel URL."
    ], 'Register Localhost Webhook');
    exit(1);
}

// 3. Ensure full endpoint path is appended
$parsed = rtrim($tunnelUrl, '/');
if (substr($parsed, -11) !== 'webhook.php') {
    if (strpos($parsed, '/api/tg_quiz') !== false) {
        $webhookUrl = $parsed . '/webhook.php';
    } else {
        $webhookUrl = $parsed . '/api/tg_quiz/webhook.php';
    }
} else {
    $webhookUrl = $parsed;
}

$secretToken = defined('TG_WEBHOOK_SECRET') ? TG_WEBHOOK_SECRET : '';
$allowedUpdates = ['message', 'poll_answer', 'my_chat_member'];

// 4. Execute setWebhook
$response = setTelegramWebhook($token, $webhookUrl, $secretToken, $allowedUpdates);

if (!empty($response['ok'])) {
    outputResult([
        'success'           => true,
        'environment'       => 'local (tunnel)',
        'registered_url'    => $webhookUrl,
        'secret_token_used' => !empty($secretToken) ? 'YES (Protected via X-Telegram-Bot-Api-Secret-Token)' : 'NO',
        'allowed_updates'   => $allowedUpdates,
        'telegram_message'  => $response['description'] ?? 'Webhook was set successfully',
        'notice'            => 'NOTE: Setting local webhook deactivated production webhook. A Telegram bot can have only ONE active webhook at a time.'
    ], 'Register Localhost Webhook');
} else {
    outputResult([
        'success'           => false,
        'target_url'        => $webhookUrl,
        'error'             => $response['description'] ?? 'Telegram rejected setWebhook request',
        'error_code'        => $response['error_code'] ?? 0
    ], 'Register Localhost Webhook');
    exit(1);
}
