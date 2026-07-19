<?php
$page_title = "Events";
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<?php $banner_img = function_exists('getUiImage') ? getUiImage('banner_events', 'https://placehold.co/1920x400/2c3e50/f39c12?text=Community+Events') : 'https://placehold.co/1920x400/2c3e50/f39c12?text=Community+Events'; ?>
<div class="page-banner mb-4">
    <img src="<?= htmlspecialchars($banner_img) ?>" class="img-fluid w-100 shadow-sm" style="max-height: 400px; object-fit: cover;">
</div>
<div class="container py-5 text-center">
    <h1 class="fw-bold text-warning mb-4">Upcoming Events</h1>
    <p class="lead text-muted">Stay tuned for upcoming community gatherings, marriages, and festivals.</p>
    <div class="card card-custom p-5 mt-4">
        <i class="fa-regular fa-calendar-xmark fa-4x text-muted mb-3"></i>
        <h4>No upcoming events found.</h4>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
