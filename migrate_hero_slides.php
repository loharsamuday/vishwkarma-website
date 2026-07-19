<?php
require_once 'includes/db.php';

$new_images = [
    ['home_hero_2', 'Home Page - Hero Slide 2', 'Home Page'],
    ['home_hero_3', 'Home Page - Hero Slide 3', 'Home Page']
];

foreach ($new_images as $img) {
    $stmt = $pdo->prepare("SELECT id FROM ui_images WHERE image_key = ?");
    $stmt->execute([$img[0]]);
    if (!$stmt->fetch()) {
        $insert = $pdo->prepare("INSERT INTO ui_images (image_key, title, page_name) VALUES (?, ?, ?)");
        $insert->execute($img);
        echo "Inserted {$img[0]}\n";
    } else {
        echo "{$img[0]} already exists\n";
    }
}
echo "Migration complete.\n";
?>
