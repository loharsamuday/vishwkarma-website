<?php
// config/config.php

// 1. Initialize Error & Crash Handling FIRST
require_once __DIR__ . '/../includes/error_handler.php';
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'Vishwkarma');

define('BASE_URL', 'http://localhost/vishwkarma/');

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
