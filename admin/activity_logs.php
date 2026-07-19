<?php
$page_title = "Activity Logs";
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once 'includes/header.php';

// Pagination setup
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch logs with user/admin details
$query = "
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.email as user_email,
           ad.username AS admin_name
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN admin_users ad ON a.admin_id = ad.id
    ORDER BY a.created_at DESC
    LIMIT $limit OFFSET $offset
";
$logs = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Count total logs for pagination
$total_logs = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$total_pages = ceil($total_logs / $limit);
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <h3 class="mb-0 text-dark"><i class="fa-solid fa-clipboard-list text-primary me-2"></i> Activity Logs</h3>
        <span class="badge bg-secondary">Total: <?= $total_logs ?> Records</span>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Date / Time</th>
                            <th>Role</th>
                            <th>Actor (Name / Email)</th>
                            <th>Action</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= $log['id'] ?></td>
                                    <td><?= date('d M Y, h:i A', strtotime($log['created_at'])) ?></td>
                                    <td>
                                        <?php if ($log['role'] === 'admin'): ?>
                                            <span class="badge bg-danger"><i class="fa-solid fa-shield"></i> Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="fa-solid fa-user"></i> User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['role'] === 'admin'): ?>
                                            <strong class="text-danger"><?= htmlspecialchars($log['admin_name'] ?? 'Unknown Admin') ?></strong>
                                        <?php else: ?>
                                            <strong class="text-dark"><?= htmlspecialchars($log['user_name'] ?? 'Unknown User') ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($log['user_email'] ?? '') ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-medium text-primary"><?= htmlspecialchars($log['action']) ?></span>
                                    </td>
                                    <td><small class="font-monospace bg-light border p-1 rounded"><?= htmlspecialchars($log['ip_address'] ?? 'Unknown') ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4">No activity logs found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
            </li>
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
