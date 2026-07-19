<?php
require 'includes/db.php';
$stmt = $pdo->query("SELECT content FROM cms_pages WHERE page_slug = 'about'");
echo $stmt->fetchColumn();
?>
