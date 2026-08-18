<?php
// config/database.php

// Check if running on localhost
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');

if ($is_localhost) {
    // -----------------------------------------
    // LOCALHOST CONFIGURATION (XAMPP)
    // -----------------------------------------
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    $host = 'localhost';
    $db_name = 'english_learning';
    $username = 'root';
    $password = '';
} else {
    // -----------------------------------------
    // LIVE SERVER CONFIGURATION (InfinityFree)
    // -----------------------------------------
    // Temporarily keeping errors ON for debugging your current issue. 
    // Once it works, change to error_reporting(0) and ini_set('display_errors', 0)
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    $host = 'sql112.infinityfree.com';
    $db_name = 'if0_42277227_vishwkarma';
    $username = 'if0_42277227';
    $password = 'LiAc40aALrDAS';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    if ($is_localhost) {
        die("Database Connection failed: " . $e->getMessage());
    } else {
        // Show error on live server temporarily for debugging
        die("Live Database Connection failed: " . $e->getMessage() . "<br><br>Please check your InfinityFree database credentials.");
    }
}
?>
