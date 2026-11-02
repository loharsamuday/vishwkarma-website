<?php
// scripts/telegram/_bootstrap.php
// Common bootstrap for Telegram webhook management scripts

require_once __DIR__ . '/../../config/config.php';

// Check if token was provided via CLI flag
$cliTokenProvided = false;
if (php_sapi_name() === 'cli' && isset($argv) && is_array($argv)) {
    foreach ($argv as $arg) {
        if (strpos($arg, '--token=') === 0) {
            $cliTokenProvided = true;
            break;
        }
    }
}

// Only connect to database if token was not explicitly supplied via CLI
$pdo = null;
if (!$cliTokenProvided) {
    require_once __DIR__ . '/../../includes/db.php';
}

require_once __DIR__ . '/../../api/tg_quiz/tg_helper.php';

// If accessed via web browser, ensure only authorized admin or secret key can execute
if (php_sapi_name() !== 'cli') {
    $hasSession = isset($_SESSION['admin_id']);
    $authSecret = $_GET['secret'] ?? '';
    $expectedSecret = defined('TG_WORKER_SECRET') ? TG_WORKER_SECRET : '';

    if (!$hasSession && (empty($expectedSecret) || !hash_equals($expectedSecret, $authSecret))) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized: Please log in as Admin or provide ?secret=YOUR_TG_WORKER_SECRET'
        ]);
        exit;
    }
}

/**
 * Resolve bot token from CLI flag, GET param, Database, or constant
 */
function resolveScriptBotToken(): ?string {
    // 1. CLI flag: --token=123456:ABC...
    if (php_sapi_name() === 'cli') {
        global $argv;
        if (isset($argv) && is_array($argv)) {
            foreach ($argv as $arg) {
                if (strpos($arg, '--token=') === 0) {
                    return trim(substr($arg, 8));
                }
            }
        }
    }

    // 2. Query param ?token=...
    if (!empty($_GET['token'])) {
        return trim($_GET['token']);
    }

    // 3. Database query
    global $pdo;
    if ($pdo instanceof PDO) {
        $dbToken = getActiveBotToken($pdo);
        if (!empty($dbToken)) {
            return $dbToken;
        }
    }

    // 4. Config constant fallback
    if (defined('TG_BOT_TOKEN') && !empty(TG_BOT_TOKEN)) {
        return TG_BOT_TOKEN;
    }

    return null;
}

/**
 * Output helper supporting both CLI and Browser
 */
function outputResult(array $data, string $title = 'Telegram Webhook Tool'): void {
    if (php_sapi_name() === 'cli') {
        echo "====================================================\n";
        echo "  " . strtoupper($title) . "\n";
        echo "====================================================\n";
        foreach ($data as $k => $v) {
            if (is_array($v) || is_object($v)) {
                echo str_pad($k, 25) . ": \n" . json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            } else {
                echo str_pad($k, 25) . ": " . ($v === true ? 'true' : ($v === false ? 'false' : $v)) . "\n";
            }
        }
        echo "====================================================\n\n";
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['title' => $title], $data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
