<?php
// scripts/telegram/register_prod_webhook.php
// Production Webhook Registration Script (via setWebhook)
// Usage (CMD):   php scripts/telegram/register_prod_webhook.php [custom_url] [--token=123:ABC...]
// Usage (Web):   https://vishwkarma.great-site.net/scripts/telegram/register_prod_webhook.php?secret=YOUR_TG_WORKER_SECRET

require_once __DIR__ . '/_bootstrap.php';

$token = resolveScriptBotToken();

if (empty($token)) {
    outputResult([
        'success' => false,
        'error'   => 'No active bot token found in database (tg_bots) or passed via --token=...'
    ], 'Register Production Webhook');
    exit(1);
}

// Determine target webhook URL
$customUrl = '';
if (php_sapi_name() === 'cli' && isset($argv) && is_array($argv)) {
    foreach ($argv as $i => $arg) {
        if ($i > 0 && strpos($arg, '--') !== 0) {
            $customUrl = trim($arg);
            break;
        }
    }
} elseif (isset($_GET['url']) && !empty(trim($_GET['url']))) {
    $customUrl = trim($_GET['url']);
}

if (!empty($customUrl)) {
    $webhookUrl = $customUrl;
} else {
    // Production default: InfinityFree domain
    $webhookUrl = 'https://vishwkarma.great-site.net/api/tg_quiz/webhook.php';
}

// Telegram strictly requires HTTPS
if (stripos($webhookUrl, 'https://') !== 0) {
    outputResult([
        'success' => false,
        'error'   => "Telegram Webhook requires HTTPS. Provided: {$webhookUrl}"
    ], 'Register Production Webhook');
    exit(1);
}

$secretToken = defined('TG_WEBHOOK_SECRET') ? TG_WEBHOOK_SECRET : '';
$allowedUpdates = ['message', 'poll_answer', 'my_chat_member'];

// Execute setWebhook
$response = setTelegramWebhook($token, $webhookUrl, $secretToken, $allowedUpdates);

if (!empty($response['ok'])) {
    outputResult([
        'success'           => true,
        'environment'       => 'production',
        'registered_url'    => $webhookUrl,
        'secret_token_used' => !empty($secretToken) ? 'YES (Protected via X-Telegram-Bot-Api-Secret-Token)' : 'NO',
        'allowed_updates'   => $allowedUpdates,
        'telegram_message'  => $response['description'] ?? 'Webhook was set successfully',
        'next_steps'        => 'Verify with scripts/telegram/check_webhook_status.php'
    ], 'Register Production Webhook');
} else {
    outputResult([
        'success'           => false,
        'target_url'        => $webhookUrl,
        'error'             => $response['description'] ?? 'Telegram rejected setWebhook request',
        'error_code'        => $response['error_code'] ?? 0
    ], 'Register Production Webhook');
    exit(1);
}
