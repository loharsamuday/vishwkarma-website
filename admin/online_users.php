<?php
$page_title = "Live Users";
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once 'includes/header.php';

// Define online threshold (e.g., active within last 5 minutes)
$threshold_minutes = 5;

$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, email, phone, last_active, last_page_url 
    FROM users 
    WHERE last_active >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ORDER BY last_active DESC
");
$stmt->execute([$threshold_minutes]);
$online_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <div class="d-flex align-items-center">
            <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
            <h3 class="mb-0 text-dark"><i class="fa-solid fa-satellite-dish text-success me-2 fa-fade"></i> Live Users Tracking</h3>
        </div>
        <div>
            <a href="online_users.php" class="btn btn-primary fw-bold"><i class="fa-solid fa-rotate-right me-1"></i> Refresh</a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-success text-white border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="display-4 me-3"><i class="fa-solid fa-globe"></i></div>
                    <div>
                        <h6 class="text-uppercase mb-0 fw-bold">Currently Online</h6>
                        <h2 class="mb-0 fw-bold"><?= count($online_users) ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Status</th>
                            <th>User Name</th>
                            <th>Contact Info</th>
                            <th>Current Page</th>
                            <th>Last Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($online_users) > 0): ?>
                            <?php foreach ($online_users as $u): ?>
                                <tr>
                                    <td>
                                        <div class="spinner-grow text-success spinner-grow-sm" role="status">
                                          <span class="visually-hidden">Online</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-warning text-dark fw-bold rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 40px; height: 40px; font-size: 1.2rem;">
                                                <?= strtoupper(substr($u['first_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <strong class="text-dark d-block"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></strong>
                                                <span class="badge bg-secondary">ID: <?= $u['id'] ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small"><i class="fa-solid fa-envelope text-muted me-1"></i> <?= htmlspecialchars($u['email']) ?></div>
                                        <div class="small"><i class="fa-solid fa-phone text-muted me-1"></i> <?= htmlspecialchars($u['phone']) ?></div>
                                    </td>
                                    <td>
                                        <?php 
                                        $url = htmlspecialchars($u['last_page_url']);
                                        $basename = basename($url);
                                        if (empty($basename) || $basename == '/') $basename = 'Home Page';
                                        ?>
                                        <a href="<?= $url ?>" target="_blank" class="text-decoration-none fw-bold" title="<?= $url ?>">
                                            <i class="fa-solid fa-link me-1"></i> <?= $basename ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success">Just Now</span><br>
                                        <small class="text-muted"><?= date('h:i:s A', strtotime($u['last_active'])) ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted mb-3"><i class="fa-solid fa-users-slash fa-3x"></i></div>
                                    <h5>No users are currently online.</h5>
                                    <p class="text-muted small">This list shows users who have been active in the last <?= $threshold_minutes ?> minutes.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
