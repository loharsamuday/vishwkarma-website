<?php
require_once 'includes/db.php';

$about_content = file_get_contents('about.php');
$start_pos = strpos($about_content, '<!-- Header Banner -->');
$end_pos = strpos($about_content, '<?php require_once \'includes/footer.php\'; ?>');
$about_html = substr($about_content, $start_pos, $end_pos - $start_pos);

$terms_content = file_get_contents('terms.php');
$start_pos = strpos($terms_content, '<!-- Header Banner -->');
$end_pos = strpos($terms_content, '<?php require_once \'includes/footer.php\'; ?>');
$terms_html = substr($terms_content, $start_pos, $end_pos - $start_pos);

$pdo->prepare("UPDATE pages SET content = ? WHERE slug = 'about'")->execute([trim($about_html)]);
$pdo->prepare("UPDATE pages SET content = ? WHERE slug = 'terms'")->execute([trim($terms_html)]);

echo "Migration successful.";
