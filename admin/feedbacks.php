<?php
$page_title = "User Feedbacks";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Handle Delete
if (isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM feedbacks WHERE id = ?");
        $stmt->execute([$delete_id]);
        setFlashMessage('success', 'Feedback deleted successfully.');
        header("Location: feedbacks.php");
        exit;
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error deleting feedback.');
    }
}

// Fetch all feedbacks
$stmt = $pdo->query("
    SELECT f.*, u.first_name, u.last_name, u.email 
    FROM feedbacks f
    LEFT JOIN users u ON f.user_id = u.id
    ORDER BY f.created_at DESC
");
$feedbacks = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <div class="d-flex align-items-center">
            <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
            <h2 class="text-warning fw-bold mb-0"><i class="fa-solid fa-comments me-2"></i> User Feedbacks</h2>
        </div>
    </div>

<?php displayFlashMessage(); ?>

<div class="card card-custom p-4 shadow-sm border-top border-4 border-warning">
    <div class="table-responsive">
        <table class="table table-dark table-hover table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Type</th>
                    <th>Rating</th>
                    <th>Message</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($feedbacks)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No feedbacks received yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($feedbacks as $fb): ?>
                        <tr>
                            <td class="text-nowrap"><?= date('d M Y h:i A', strtotime($fb['created_at'])) ?></td>
                            <td>
                                <?php if ($fb['user_id']): ?>
                                    <span class="fw-bold text-warning"><?= htmlspecialchars($fb['first_name'] . ' ' . $fb['last_name']) ?></span><br>
                                    <small class="text-muted"><?= htmlspecialchars($fb['email']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Guest</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $badge = 'secondary';
                                if ($fb['feedback_type'] == 'Bug') $badge = 'danger';
                                if ($fb['feedback_type'] == 'Suggestion') $badge = 'info';
                                if ($fb['feedback_type'] == 'Compliment') $badge = 'success';
                                ?>
                                <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($fb['feedback_type']) ?></span>
                            </td>
                            <td class="text-warning">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="fa-solid fa-star <?= $i <= $fb['rating'] ? 'text-warning' : 'text-secondary' ?>"></i>
                                <?php endfor; ?>
                            </td>
                            <td class="text-wrap" style="min-width: 250px;">
                                <?= nl2br(htmlspecialchars($fb['message'])) ?>
                            </td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                                    <input type="hidden" name="delete_id" value="<?= $fb['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
<?php require_once 'includes/footer.php'; ?>
