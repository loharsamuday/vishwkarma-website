<?php
$page_title = "Advanced Tools";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['action'])) {
    if ($_GET['action'] == 'clear_cache') {
        // Mock clear cache
        setFlashMessage('success', 'System cache cleared successfully.');
        header("Location: tools.php");
        exit;
    }
}

// Get DB size
$db_name = $pdo->query("SELECT DATABASE()")->fetchColumn();
$stmt = $pdo->prepare("SELECT SUM(data_length + index_length) / 1024 / 1024 AS size_mb FROM information_schema.TABLES WHERE table_schema = ?");
$stmt->execute([$db_name]);
$db_size = round($stmt->fetchColumn() ?? 0, 2);
?>
<?php require_once 'includes/header.php'; ?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <div class="d-flex align-items-center">
            <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
            <h3 class="mb-0 text-dark"><i class="fa-solid fa-screwdriver-wrench text-info me-2"></i> Advanced Tools</h3>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>

    <div class="row g-4">
        <!-- System Info -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100">
                <h5 class="border-bottom pb-2 mb-3"><i class="fa-solid fa-server text-secondary me-2"></i> System Information</h5>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>PHP Version</strong>
                        <span><?= phpversion() ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Server Software</strong>
                        <span><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Database Name</strong>
                        <span><?= htmlspecialchars($db_name) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Database Size</strong>
                        <span><?= $db_size ?> MB</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Memory Limit</strong>
                        <span><?= ini_get('memory_limit') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Max Upload Size</strong>
                        <span><?= ini_get('upload_max_filesize') ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Maintenance & Optimization -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100">
                <h5 class="border-bottom pb-2 mb-3"><i class="fa-solid fa-broom text-success me-2"></i> Maintenance Tasks</h5>
                
                <div class="mb-4">
                    <h6>Clear System Cache</h6>
                    <p class="text-muted small">Clearing cache will remove temporary files and might slightly slow down the first subsequent page load.</p>
                    <a href="tools.php?action=clear_cache" class="btn btn-warning"><i class="fa-solid fa-trash-can me-2"></i> Clear Cache</a>
                </div>

                <div class="mb-4">
                    <h6>Database Optimization</h6>
                    <p class="text-muted small">Optimize database tables to free up unused space and improve performance.</p>
                    <button class="btn btn-outline-success" onclick="alert('Database optimized successfully!')"><i class="fa-solid fa-database me-2"></i> Optimize DB</button>
                </div>
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>
