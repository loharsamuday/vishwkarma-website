<?php
// admin/view-user-story.php
session_start();
$page_title = 'Review User Story';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT us.*, c.name as category_name FROM user_stories us LEFT JOIN categories c ON us.category_id = c.id WHERE us.id = ?");
$stmt->execute([$id]);
$story = $stmt->fetch();

if (!$story) {
    header("Location: user-stories.php");
    exit();
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Review Submission: <?= escape($story['title']) ?></h1>
    <a href="user-stories.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><?= escape($story['title']) ?></h5>
            </div>
            <div class="card-body">
                <div class="story-content border p-3 rounded bg-light mb-3" style="max-height: 500px; overflow-y: auto;">
                    <?= nl2br(escape($story['content'])) ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">Author Info</div>
            <div class="card-body">
                <p><strong>Name:</strong> <?= escape($story['author_name']) ?></p>
                <p><strong>Email:</strong> <?= escape($story['email'] ?: 'Not provided') ?></p>
                <p><strong>Category:</strong> <?= escape($story['category_name'] ?: 'None') ?></p>
                <p><strong>Difficulty:</strong> <?= escape($story['difficulty']) ?></p>
                <p><strong>Submitted:</strong> <?= formatDate($story['created_at']) ?></p>
            </div>
        </div>
        
        <div class="card shadow-sm mb-4 border-<?= $story['status'] == 'Approved' ? 'success' : ($story['status'] == 'Rejected' ? 'danger' : 'warning') ?>">
            <div class="card-header bg-white fw-bold">Current Status: <?= $story['status'] ?></div>
            <div class="card-body">
                <form action="process-user-story.php" method="POST">
                    <input type="hidden" name="id" value="<?= $story['id'] ?>">
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Change Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="Pending" <?= $story['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Approved" <?= $story['status'] == 'Approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="Rejected" <?= $story['status'] == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_note" class="form-label">Admin Note (Internal)</label>
                        <textarea class="form-control" id="admin_note" name="admin_note" rows="3"><?= escape($story['admin_note']) ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary bg-primary-custom">Update Status</button>
                        
                        <?php if($story['status'] == 'Approved'): ?>
                            <button type="submit" name="publish_to_main" value="1" class="btn btn-success" onclick="return confirm('This will copy the story to the main stories list and publish it. Proceed?');">Publish to Main Site</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
