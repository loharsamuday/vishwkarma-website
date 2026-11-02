<?php
// config/config.php
date_default_timezone_set('Asia/Kolkata');

// 1. Initialize Error & Crash Handling FIRST
require_once __DIR__ . '/../includes/error_handler.php';
// Check hostname / environment variable to determine environment ('local' or 'production')
if (!defined('ENVIRONMENT')) {
    $env_from_sys = getenv('ENVIRONMENT') ?: getenv('APP_ENV');
    if ($env_from_sys) {
        define('ENVIRONMENT', strtolower(trim($env_from_sys)));
    } else {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $is_local = ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, 'localhost:') === 0 || strpos($host, '127.0.0.1:') === 0);
        define('ENVIRONMENT', $is_local ? 'local' : 'production');
    }
}

if (ENVIRONMENT === 'local') {
    // Local environment settings (XAMPP Windows)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'Vishwkarma');
    
    define('BASE_URL', 'http://localhost/vishwkarma/');
    // For local Telegram webhook testing via ngrok/localtunnel (Telegram requires HTTPS)
    define('LOCAL_TUNNEL_URL', ''); // e.g. 'https://abc123.ngrok-free.app/vishwkarma/'
} else {
    // Main server settings (InfinityFree)
    define('DB_HOST', 'sql112.infinityfree.com');
    define('DB_USER', 'if0_42277227');
    define('DB_PASS', 'LiAc40aALrDAS');
    define('DB_NAME', 'if0_42277227_vishwkarma');
    
    define('BASE_URL', 'https://vishwkarma.great-site.net/');
}

// Telegram Webhook Secret Token for X-Telegram-Bot-Api-Secret-Token validation
// Telegram sends this header in every webhook update to verify authenticity
if (!defined('TG_WEBHOOK_SECRET')) {
    define('TG_WEBHOOK_SECRET', 'V1shwk4rma_TG_w3bh00k_S3cr3t_2026_Sec99');
}

define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_OAUTH_CLIENT_ID');
define('GOOGLE_OAUTH_PROVIDER', 'google');

// Telegram Quiz Manager — Bot Token Encryption Key
// CHANGE THIS to a unique 32+ character random string before use
// Must match the TG_ENCRYPTION_KEY in python/.env
define('TG_ENCRYPTION_KEY', 'change-this-to-32-char-random-key!');

// Telegram Quiz Worker — Sync API Secret Key
// Used to authenticate the external Python worker's HTTPS API requests
// Must match WORKER_SECRET in python/.env
define('TG_WORKER_SECRET', 'wk_V1shwk4rma_s3cR3t_2026_X9pLm7nQz8');

// Other global configuration settings
define('SITE_NAME', 'Vishwakarma');
define('ADMIN_EMAIL', 'admin@vishwkarma.local');

// Secure Session Configuration (Only start session for web requests, not CLI)
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', 1);
    @ini_set('session.use_only_cookies', 1);
    @ini_set('session.use_strict_mode', 1);
    session_start();
}
?>
