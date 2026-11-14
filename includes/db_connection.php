<?php
require_once __DIR__ . '/../config/config.php';

if (!function_exists('createDatabasePdo')) {
    function createDatabasePdo(): PDO {
        $connection = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $connection->exec("SET time_zone = '+05:30'");
        try {
            $connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            error_log('Database charset setup failed: ' . $e->getMessage());
        }
        return $connection;
    }
}

try {
    $pdo = createDatabasePdo();
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Database connection failed.\n");
        exit(1);
    }
    include_once __DIR__ . '/../maintenance.php';
    exit();
}
