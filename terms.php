<?php
$page_title = "Terms & Conditions";
require_once 'includes/db.php';
require_once 'includes/session.php';
$page_data = getCmsContent('terms');
if ($page_data && !empty($page_data['title'])) {
    $page_title = $page_data['title'] . " - " . SITE_NAME;
}
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<?= $page_data['content'] ?? '' ?>

<?php require_once 'includes/footer.php'; ?>
