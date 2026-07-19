<?php
$page_title = "Contact Messages";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Handle Mark as Read or Delete
if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    if ($action === 'read') {
        $pdo->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?")->execute([$id]);
        setFlashMessage('success', 'Message marked as read.');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
        setFlashMessage('success', 'Message deleted successfully.');
    }
    header("Location: contact_messages.php");
    exit;
}

// Fetch all messages
$stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
$messages = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <h3 class="mb-0 text-dark"><i class="fa-solid fa-envelope text-warning me-2"></i> Contact Messages</h3>
    </div>
    
    <?php displayFlashMessage(); ?>

    <div class="card border-0 shadow-sm p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No messages found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($messages as $m): ?>
                        <tr class="<?= $m['status'] == 'unread' ? 'table-warning' : '' ?>">
                            <td>#<?= $m['id'] ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($m['name']) ?></td>
                            <td><a href="mailto:<?= htmlspecialchars($m['email']) ?>" class="text-decoration-none"><?= htmlspecialchars($m['email']) ?></a></td>
                            <td><?= htmlspecialchars($m['subject']) ?></td>
                            <td><small><?= date('d M, Y h:i A', strtotime($m['created_at'])) ?></small></td>
                            <td>
                                <?php if($m['status'] === 'unread'): ?>
                                    <span class="badge bg-danger">New</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Read</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#msgModal<?= $m['id'] ?>"><i class="fa-solid fa-eye"></i> View</button>
                                <?php if($m['status'] === 'unread'): ?>
                                    <a href="contact_messages.php?action=read&id=<?= $m['id'] ?>" class="btn btn-sm btn-success"><i class="fa-solid fa-check"></i></a>
                                <?php endif; ?>
                                <a href="contact_messages.php?action=delete&id=<?= $m['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this message permanently?');"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        </tr>

                        <!-- Message Modal -->
                        <div class="modal fade" id="msgModal<?= $m['id'] ?>" tabindex="-1" aria-labelledby="msgModalLabel<?= $m['id'] ?>" aria-hidden="true">
                          <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                              <div class="modal-header bg-warning">
                                <h5 class="modal-title text-dark fw-bold" id="msgModalLabel<?= $m['id'] ?>">Message from <?= htmlspecialchars($m['name']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                              </div>
                              <div class="modal-body p-4">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="mb-1 text-muted small">Name</p>
                                        <h6 class="fw-bold"><?= htmlspecialchars($m['name']) ?></h6>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1 text-muted small">Email Address</p>
                                        <h6><a href="mailto:<?= htmlspecialchars($m['email']) ?>"><?= htmlspecialchars($m['email']) ?></a></h6>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <p class="mb-1 text-muted small">Subject</p>
                                    <h6 class="fw-bold text-dark"><?= htmlspecialchars($m['subject']) ?></h6>
                                </div>
                                <hr>
                                <div class="mb-3">
                                    <p class="mb-2 text-muted small">Full Message</p>
                                    <div class="p-3 bg-light border rounded" style="white-space: pre-wrap;"><?= htmlspecialchars($m['message']) ?></div>
                                </div>
                                <div class="text-end text-muted small">
                                    Received: <?= date('d M, Y h:i A', strtotime($m['created_at'])) ?>
                                </div>
                              </div>
                              <div class="modal-footer">
                                <?php if($m['status'] === 'unread'): ?>
                                    <a href="contact_messages.php?action=read&id=<?= $m['id'] ?>" class="btn btn-success fw-bold">Mark as Read</a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                              </div>
                            </div>
                          </div>
                        </div>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
