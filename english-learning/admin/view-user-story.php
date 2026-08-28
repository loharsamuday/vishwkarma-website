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
                        <label for="admin_note" class="form-label fw-bold" id="admin_note_label">Admin Note</label>
                        <div id="email_info" class="alert alert-info py-2 small mb-2" style="display: none;">
                            <i class="fas fa-envelope me-1"></i> This message will be emailed directly to: <strong><?= !empty($story['email']) ? escape($story['email']) : 'No email provided by user!' ?></strong>
                        </div>
                        
                        <div id="quick_reasons" class="mb-2" style="display: none;">
                            <p class="small text-muted mb-1 fw-bold"><i class="fas fa-bolt text-warning"></i> Auto Default Reasons (Click to select):</p>
                            <div class="d-flex flex-wrap gap-1">
                                <span class="badge bg-outline-secondary border text-dark reason-btn" style="cursor: pointer; background: #f8f9fa;" data-text="Your story is too short or lacks sufficient detail. Please add more content and try again.">Too Short</span>
                                <span class="badge bg-outline-secondary border text-dark reason-btn" style="cursor: pointer; background: #f8f9fa;" data-text="There are many grammatical errors. Please proofread your story and submit it again.">Grammar Issues</span>
                                <span class="badge bg-outline-secondary border text-dark reason-btn" style="cursor: pointer; background: #f8f9fa;" data-text="The content is not appropriate for our English learning platform.">Inappropriate Content</span>
                                <span class="badge bg-outline-secondary border text-dark reason-btn" style="cursor: pointer; background: #f8f9fa;" data-text="This story seems to be copied from elsewhere. We only accept original writing.">Copied Content</span>
                                <span class="badge bg-outline-secondary border text-dark reason-btn" style="cursor: pointer; background: #f8f9fa;" data-text="The story doesn't fit the selected category. Please choose a more relevant category.">Wrong Category</span>
                            </div>
                        </div>

                        <textarea class="form-control" id="admin_note" name="admin_note" rows="4" placeholder="Type your message or reason here..."><?= escape($story['admin_note']) ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary bg-primary-custom">Update Status</button>
                        
                        <?php if($story['status'] == 'Approved'): ?>
                            <hr class="my-1">
                            <div class="alert alert-success small py-2 mb-1">
                                <i class="fas fa-check-circle"></i> This story is approved. You can now publish it to the public website.
                            </div>
                            <button type="submit" name="publish_to_main" value="1" class="btn btn-success" onclick="return confirm('This will copy the story to the main public stories list. Proceed?');">
                                <i class="fas fa-globe me-1"></i> Publish to Main Site
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('status');
    const adminNoteLabel = document.getElementById('admin_note_label');
    const emailInfo = document.getElementById('email_info');
    const adminNote = document.getElementById('admin_note');
    const quickReasons = document.getElementById('quick_reasons');
    const hasEmail = <?= !empty($story['email']) ? 'true' : 'false' ?>;
    
    function updateUI() {
        if (statusSelect.value === 'Rejected') {
            adminNoteLabel.innerHTML = 'Type Rejection Message for User <span class="text-danger">*</span>';
            emailInfo.style.display = 'block';
            quickReasons.style.display = 'block';
            adminNote.setAttribute('required', 'required');
            
            if (!hasEmail) {
                emailInfo.className = 'alert alert-warning py-2 small mb-2';
            } else {
                emailInfo.className = 'alert alert-info py-2 small mb-2';
            }
        } else {
            adminNoteLabel.innerHTML = 'Admin Note (Internal only)';
            emailInfo.style.display = 'none';
            quickReasons.style.display = 'none';
            adminNote.removeAttribute('required');
        }
    }
    
    statusSelect.addEventListener('change', updateUI);
    updateUI(); // Run on load
    
    // Quick Reasons click handlers
    const reasonBtns = document.querySelectorAll('.reason-btn');
    reasonBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const textToInsert = this.getAttribute('data-text');
            // Visual feedback on click
            this.style.backgroundColor = '#e9ecef';
            setTimeout(() => { this.style.backgroundColor = '#f8f9fa'; }, 200);
            
            if (adminNote.value.trim() === '') {
                adminNote.value = textToInsert;
            } else {
                // If there's already text, append it on a new line
                adminNote.value = adminNote.value.trim() + '\n\n' + textToInsert;
            }
            
            // Focus on textarea so admin can continue typing or just submit
            adminNote.focus();
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
