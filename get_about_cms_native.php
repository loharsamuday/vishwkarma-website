<?php
// bypass error handler
define('DEBUG_MODE', true);
try {
    $pdo = new PDO("mysql:host=localhost;dbname=Vishwkarma;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    $stmt = $pdo->query("SELECT content FROM pages WHERE slug = 'about'");
    echo $stmt->fetchColumn();
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
