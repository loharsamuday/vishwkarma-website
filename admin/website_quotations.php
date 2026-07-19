<?php
$page_title = "Website Quotations";
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once 'includes/header.php';

// Handle Delete Request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM website_quotations WHERE id = ?");
        $stmt->execute([$delete_id]);
        $success_msg = "Quotation request deleted successfully.";
    } catch (PDOException $e) {
        $error_msg = "Failed to delete: " . $e->getMessage();
    }
}

// Fetch Quotations
try {
    $stmt = $pdo->query("SELECT * FROM website_quotations ORDER BY created_at DESC");
    $quotations = $stmt->fetchAll();
} catch (PDOException $e) {
    $quotations = [];
    $error_msg = "Failed to fetch quotations: " . $e->getMessage();
}
?>

<div class="main-content" id="mainContent">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i> Website Quotations</h2>
            <p class="text-muted mb-0">Manage all website quotation requests submitted by users.</p>
        </div>
    </div>

    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <?= $success_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <?= $error_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Date</th>
                            <th>Client Info</th>
                            <th>Project Type</th>
                            <th>Budget</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($quotations)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-inbox fs-1 mb-3 opacity-50 d-block"></i>
                                    No quotation requests found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $modals_html = '';
                            foreach ($quotations as $quote): 
                            ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-secondary">#<?= $quote['id'] ?></td>
                                    <td>
                                        <div class="small text-dark fw-medium"><?= date('d M Y', strtotime($quote['created_at'])) ?></div>
                                        <div class="small text-muted"><?= date('h:i A', strtotime($quote['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($quote['name'] ?? '') ?></div>
                                        <div class="small text-muted"><i class="fa-solid fa-envelope me-1"></i><?= htmlspecialchars($quote['email'] ?? '') ?></div>
                                        <div class="small text-muted"><i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($quote['phone'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-2 py-1"><?= htmlspecialchars($quote['website_type'] ?? '') ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success"><?= htmlspecialchars($quote['budget'] ?? '') ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button type="button" class="btn btn-sm btn-light text-primary me-2" data-bs-toggle="modal" data-bs-target="#viewModal<?= $quote['id'] ?>">
                                            <i class="fa-solid fa-eye"></i> View
                                        </button>
                                        <a href="?delete=<?= $quote['id'] ?>" class="btn btn-sm btn-light text-danger" onclick="return confirm('Are you sure you want to delete this request?')">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>

                                <?php ob_start(); ?>
                                <!-- View Modal -->
                                <div class="modal fade" id="viewModal<?= $quote['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header bg-primary text-white border-0">
                                                <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice me-2"></i> Quotation Details #<?= $quote['id'] ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row g-4">
                                                    <div class="col-md-6">
                                                        <h6 class="fw-bold text-uppercase text-muted small mb-3 border-bottom pb-2">Client Information</h6>
                                                        <table class="table table-sm table-borderless">
                                                            <tr><td class="text-muted" width="100">Name</td><td class="fw-bold"><?= htmlspecialchars($quote['name'] ?? '') ?></td></tr>
                                                            <tr><td class="text-muted">Email</td><td><a href="mailto:<?= htmlspecialchars($quote['email'] ?? '') ?>"><?= htmlspecialchars($quote['email'] ?? '') ?></a></td></tr>
                                                            <tr><td class="text-muted">Phone</td><td><a href="tel:<?= htmlspecialchars($quote['phone'] ?? '') ?>"><?= htmlspecialchars($quote['phone'] ?? '') ?></a></td></tr>
                                                            <tr><td class="text-muted">Company</td><td><?= !empty($quote['company']) ? htmlspecialchars($quote['company']) : '<em class="text-muted">None</em>' ?></td></tr>
                                                        </table>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6 class="fw-bold text-uppercase text-muted small mb-3 border-bottom pb-2">Project Overview</h6>
                                                        <table class="table table-sm table-borderless">
                                                            <tr><td class="text-muted" width="120">Type</td><td><span class="badge bg-dark"><?= htmlspecialchars($quote['website_type'] ?? '') ?></span></td></tr>
                                                            <tr><td class="text-muted">Budget</td><td class="text-success fw-bold"><?= htmlspecialchars($quote['budget'] ?? '') ?></td></tr>
                                                            <tr><td class="text-muted">Domain/Host</td><td><?= htmlspecialchars($quote['domain_hosting'] ?? '') ?></td></tr>
                                                            <tr><td class="text-muted">Date</td><td><?= date('d M Y, h:i A', strtotime($quote['created_at'])) ?></td></tr>
                                                        </table>
                                                    </div>
                                                    <div class="col-12">
                                                        <h6 class="fw-bold text-uppercase text-muted small mb-2">Features Required</h6>
                                                        <div class="p-3 bg-light rounded text-dark">
                                                            <?= htmlspecialchars($quote['features'] ?? 'None selected') ?>
                                                        </div>
                                                    </div>
                                                    <?php if(!empty($quote['reference_urls'])): ?>
                                                    <div class="col-12">
                                                        <h6 class="fw-bold text-uppercase text-muted small mb-2">Reference URLs</h6>
                                                        <div class="p-3 bg-light rounded text-primary">
                                                            <?= nl2br(htmlspecialchars($quote['reference_urls'])) ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div class="col-12">
                                                        <h6 class="fw-bold text-uppercase text-muted small mb-2">Project Details</h6>
                                                        <div class="p-3 bg-light rounded text-dark" style="white-space: pre-wrap;"><?= htmlspecialchars($quote['details'] ?? '') ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light">
                                                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                                                <a href="mailto:<?= htmlspecialchars($quote['email'] ?? '') ?>" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-reply me-2"></i> Reply to Client</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php 
                                $modals_html .= ob_get_clean();
                                endforeach; 
                                ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= isset($modals_html) ? $modals_html : '' ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarCloseBtn').addEventListener('click', function() {
        document.getElementById('sidebar').classList.remove('show');
        document.getElementById('sidebarOverlay').classList.remove('show');
    });
    document.getElementById('sidebarOverlay').addEventListener('click', function() {
        document.getElementById('sidebar').classList.remove('show');
        this.classList.remove('show');
    });
</script>
</body>
</html>
