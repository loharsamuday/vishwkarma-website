<?php
$page_title = "Education & Scholarship";
require_once 'includes/db.php';
require_once 'includes/session.php';

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<!-- Header Banner -->
<?php 
$banner_education = function_exists('getUiImage') ? getUiImage('banner_education', 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?q=80&w=1920&auto=format&fit=crop') : "https://images.unsplash.com/photo-1523050854058-8df90110c9f1?q=80&w=1920&auto=format&fit=crop";
?>
<div class="bg-dark text-white py-5 text-center" style="background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('<?= htmlspecialchars($banner_education) ?>') center/cover;">
    <div class="container">
        <h1 class="display-4 fw-bold text-warning">Education & Scholarships</h1>
        <p class="lead">Empowering the youth with study materials, career guidance, and financial support.</p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="card card-custom p-4 h-100">
                <h4 class="fw-bold mb-3"><i class="fa-solid fa-graduation-cap text-warning me-2"></i> Latest Scholarships</h4>
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action py-3">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1 fw-bold">Vishwakarma Medhavi Chhatra Yojana</h6>
                            <small class="text-danger">Closes in 10 Days</small>
                        </div>
                        <p class="mb-1 text-muted small">Financial support up to ₹50,000 for top-performing engineering students.</p>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action py-3">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1 fw-bold">Samaj Vidyarthi Sahayata</h6>
                            <small class="text-success">Open</small>
                        </div>
                        <p class="mb-1 text-muted small">Basic education fund for families needing financial assistance.</p>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-custom p-4 h-100">
                <h4 class="fw-bold mb-3"><i class="fa-solid fa-book-open text-warning me-2"></i> Study Materials</h4>
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action py-3">
                        <i class="fa-solid fa-file-pdf text-danger me-2"></i> UPSC Preparation Strategy Guide
                    </a>
                    <a href="#" class="list-group-item list-group-item-action py-3">
                        <i class="fa-solid fa-file-pdf text-danger me-2"></i> Engineering Entrance Previous Year Papers
                    </a>
                    <a href="#" class="list-group-item list-group-item-action py-3">
                        <i class="fa-solid fa-video text-primary me-2"></i> Career Counseling Webinar Recording
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
