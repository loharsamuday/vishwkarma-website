<?php
require_once 'includes/db.php';

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO ui_images (image_key, page_name, title, upload_path, external_url) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['about_hero', 'About Us', 'Hero Banner Background', null, 'https://images.unsplash.com/photo-1590050752117-238cb0fb12b1?q=80&w=1920&auto=format&fit=crop']);
    echo "Successfully added about_hero to ui_images table.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
