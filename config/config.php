<?php
// config/config.php
date_default_timezone_set('Asia/Kolkata');

// 1. Initialize Error & Crash Handling FIRST
require_once __DIR__ . '/../includes/error_handler.php';
// Check hostname to determine environment
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if ($host == 'localhost') {
    // Local environment settings
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'Vishwkarma');
    
    define('BASE_URL', 'http://localhost/vishwkarma/');
} else {
    // Main server settings
    define('DB_HOST', 'sql112.infinityfree.com');
    define('DB_USER', 'if0_42277227');
    define('DB_PASS', 'LiAc40aALrDAS');
    define('DB_NAME', 'if0_42277227_vishwkarma');
    
    define('BASE_URL', 'https://vishwkarma.great-site.net/');
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
