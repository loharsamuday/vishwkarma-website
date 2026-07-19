<?php
$page_title = "Admin Dashboard";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Get stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_matrimony = $pdo->query("SELECT COUNT(*) FROM matrimony_profiles")->fetchColumn();
$total_business = $pdo->query("SELECT COUNT(*) FROM business_directory")->fetchColumn();
$total_visitors = $pdo->query("SELECT COUNT(*) FROM site_visitors")->fetchColumn();
$today_visitors = $pdo->query("SELECT COUNT(*) FROM site_visitors WHERE visit_date = CURDATE()")->fetchColumn();
?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <h3 class="mb-0">Dashboard Overview</h3>
        <div>
            <span class="text-muted me-3">Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
            <a href="../" target="_blank" class="btn btn-sm btn-outline-primary">View Website</a>
        </div>
    </div>
    
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 text-center">
                <i class="fa-solid fa-users fa-3x text-primary mb-3"></i>
                <h3><?= $total_users ?></h3>
                <p class="text-muted mb-0">Total Registered Users</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 text-center">
                <i class="fa-solid fa-heart fa-3x text-danger mb-3"></i>
                <h3><?= $total_matrimony ?></h3>
                <p class="text-muted mb-0">Matrimony Profiles</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 text-center">
                <i class="fa-solid fa-briefcase fa-3x text-success mb-3"></i>
                <h3><?= $total_business ?></h3>
                <p class="text-muted mb-0">Listed Businesses</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 text-center">
                <i class="fa-solid fa-eye fa-3x text-info mb-3"></i>
                <h3><?= $total_visitors ?></h3>
                <p class="text-muted mb-0">Total Site Visitors</p>
                <small class="text-success fw-bold">+<?= $today_visitors ?> Today</small>
            </div>
        </div>
    </div>
<?php require_once 'includes/footer.php'; ?>
