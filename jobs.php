<?php
$page_title = "Jobs Portal";
require_once 'includes/db.php';
require_once 'includes/session.php';

// Fetch Active Jobs
$stmt = $pdo->query("SELECT * FROM jobs WHERE status = 'open' ORDER BY created_at DESC");
$jobs = $stmt->fetchAll();

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<!-- Header Banner -->
<?php 
$banner_jobs = function_exists('getUiImage') ? getUiImage('banner_jobs', 'https://placehold.co/1920x400/2c3e50/f39c12?text=Jobs+Portal') : "https://placehold.co/1920x400/2c3e50/f39c12?text=Jobs+Portal";
?>
<div class="bg-dark text-white py-5 text-center" style="background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('<?= htmlspecialchars($banner_jobs) ?>') center/cover;">
    <div class="container">
        <h1 class="display-4 fw-bold text-warning">Jobs & Opportunities</h1>
        <p class="lead">Find government and private jobs, apprenticeships, and skill development programs.</p>
        <?php if(!isLoggedIn()): ?>
            <a href="login.php" class="btn btn-warning btn-lg mt-3 fw-bold">Login to Post a Job</a>
        <?php else: ?>
            <a href="job-post.php" class="btn btn-warning btn-lg mt-3 fw-bold">Post a Job</a>
        <?php endif; ?>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4">
        <?php if (empty($jobs)): ?>
            <div class="col-12 text-center text-muted">
                <div class="card card-custom p-5 border-0 shadow-sm">
                    <i class="fa-solid fa-briefcase fa-3x mb-3 text-secondary"></i>
                    <h4>No active jobs found.</h4>
                    <p>Check back later or <a href="job-post.php" class="text-warning">post a job</a> if you are an employer.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach($jobs as $job): 
                $icon = 'fa-briefcase';
                $color = 'bg-warning';
                if ($job['job_type'] == 'Government') { $icon = 'fa-building-columns'; $color = 'bg-primary'; }
                if ($job['job_type'] == 'Apprenticeship') { $icon = 'fa-user-graduate'; $color = 'bg-success'; }
                if ($job['job_type'] == 'Skill Development') { $icon = 'fa-screwdriver-wrench'; $color = 'bg-info'; }
            ?>
            <div class="col-md-6">
                <div class="card card-custom p-4 h-100 d-flex flex-row align-items-center border-0 shadow-sm hover-lift">
                    <div class="<?= $color ?> text-white rounded p-3 me-4 text-center d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                        <i class="fa-solid <?= $icon ?> fa-2x"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($job['title']) ?></h5>
                        <p class="text-muted small mb-2">
                            <?= htmlspecialchars($job['company_name'] ?? 'Confidential') ?> 
                            <?= $job['location'] ? ' | <i class="fa-solid fa-location-dot"></i> ' . htmlspecialchars($job['location']) : '' ?>
                        </p>
                        <div class="mb-2">
                            <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($job['job_type']) ?></span>
                            <?php if($job['salary_range']): ?>
                                <span class="badge bg-light text-dark border"><i class="fa-solid fa-indian-rupee-sign me-1"></i><?= htmlspecialchars($job['salary_range']) ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="<?= htmlspecialchars($job['apply_link'] ?? '#') ?>" class="text-warning text-decoration-none fw-bold small" target="_blank">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
