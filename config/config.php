<?php
// config/config.php

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

// Other global configuration settings
define('SITE_NAME', 'Vishwakarma');
define('ADMIN_EMAIL', 'admin@vishwkarma.local');

// Secure Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
// Optional: If you ever move to HTTPS, uncomment the next line
// ini_set('session.cookie_secure', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
