<?php
// config/database.php

//$host = 'localhost';
//$db_name = 'english_learning'; // Change to your database name
//$username = 'root'; // Change to your database username
//$password = ''; // Change to your database password

$host = 'sql112.infinityfree.com';
$db_name = 'if0_42277227_vishwkarma'; // Change to your database name
$username = 'if0_42277227'; // Change to your database username
$password = 'LiAc40aALrDAS'; // Change to your database password

    //define('DB_HOST', 'sql112.infinityfree.com');
    //define('DB_USER', 'if0_42277227');
    //define('DB_PASS', 'LiAc40aALrDAS');
    //define('DB_NAME', 'if0_42277227_vishwkarma');
    
    //define('BASE_URL', 'https://vishwkarma.great-site.net/');


try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>
