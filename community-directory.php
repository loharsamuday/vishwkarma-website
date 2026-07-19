<?php
$page_title = "Community Directory";
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<?php $banner_img = function_exists('getUiImage') ? getUiImage('banner_community', 'https://placehold.co/1920x400/2c3e50/f39c12?text=Community+Directory') : 'https://placehold.co/1920x400/2c3e50/f39c12?text=Community+Directory'; ?>
<div class="page-banner mb-4">
    <img src="<?= htmlspecialchars($banner_img) ?>" class="img-fluid w-100 shadow-sm" style="max-height: 400px; object-fit: cover;">
</div>
<div class="container py-5 text-center">
    <h1 class="fw-bold text-warning mb-4">Community Directory</h1>
    <p class="lead text-muted">Connect with members of the Vishwakarma Samaj.</p>
    <form class="row justify-content-center g-3 mt-4 mb-5">
        <div class="col-md-6">
            <input type="text" class="form-control form-control-lg" placeholder="Search by name, city, or profession...">
        </div>
        <div class="col-md-2">
            <button class="btn btn-warning btn-lg w-100 fw-bold">Search</button>
        </div>
    </form>
</div>
<?php require_once 'includes/footer.php'; ?>
