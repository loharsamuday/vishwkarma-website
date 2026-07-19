<?php
require_once 'includes/db.php';

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO ui_images (image_key, page_name, title, upload_path, external_url) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['about_hero', 'About Us', 'Hero Banner Background', null, 'https://placehold.co/1920x600/1a1a1a/ffc107?text=Vishwakarma+Samaj']);
    echo "Successfully added about_hero to ui_images table.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
