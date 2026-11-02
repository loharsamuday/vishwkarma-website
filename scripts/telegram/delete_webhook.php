<?php
// scripts/telegram/delete_webhook.php
// Webhook Deletion Script (via deleteWebhook)
// ONLY executed when explicitly requested by user
// Usage (CMD):   php scripts/telegram/delete_webhook.php [--drop-pending] [--token=123:ABC...]
// Usage (Web):   http://localhost/vishwkarma/scripts/telegram/delete_webhook.php?secret=YOUR_TG_WORKER_SECRET[&drop_pending=1]

require_once __DIR__ . '/_bootstrap.php';

$token = resolveScriptBotToken();

if (empty($token)) {
    outputResult([
        'success' => false,
        'error'   => 'No active bot token found in database (tg_bots) or passed via --token=...'
    ], 'Delete Telegram Webhook');
    exit(1);
}

// Check drop_pending flag
$dropPending = false;
if (php_sapi_name() === 'cli' && isset($argv) && is_array($argv)) {
    foreach ($argv as $arg) {
        if ($arg === '--drop-pending' || $arg === '-d') {
            $dropPending = true;
        }
    }
} else {
    $dropPending = !empty($_GET['drop_pending']);
}

// Execute deleteWebhook
$response = deleteTelegramWebhook($token, $dropPending);

if (!empty($response['ok'])) {
    outputResult([
        'success'              => true,
        'webhook_status'       => 'DELETED (Reverted to getUpdates / Long Polling mode)',
        'drop_pending_updates' => $dropPending ? 'YES' : 'NO',
        'telegram_message'     => $response['description'] ?? 'Webhook was deleted successfully'
    ], 'Delete Telegram Webhook');
} else {
    outputResult([
        'success'    => false,
        'error'      => $response['description'] ?? 'Failed to delete webhook',
        'error_code' => $response['error_code'] ?? 0
    ], 'Delete Telegram Webhook');
    exit(1);
}
