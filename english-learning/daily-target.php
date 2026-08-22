<?php
// daily-target.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/study_auth.php';

$condition = get_study_user_condition();
$param = get_study_user_param();
$msg = '';
$today = date('Y-m-d');
$filter_date = isset($_GET['date']) ? $_GET['date'] : $today;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_target'])) {
        $date = $_POST['target_date'];
        $type = $_POST['target_type'];
        $desc = $_POST['target_description'];
        $value = (int)$_POST['target_value'];

        if ($is_guest) {
            $stmt = $pdo->prepare("INSERT INTO daily_targets (guest_id, target_date, target_type, target_description, target_value) VALUES (?, ?, ?, ?, ?)");
        } else {
            $stmt = $pdo->prepare("INSERT INTO daily_targets (user_id, target_date, target_type, target_description, target_value) VALUES (?, ?, ?, ?, ?)");
        }
        
        if ($stmt->execute([$param, $date, $type, $desc, $value])) {
            $msg = 'Target created successfully!';
        }
    } elseif (isset($_POST['update_progress'])) {
        $id = (int)$_POST['target_id'];
        $completed = (int)$_POST['completed_value'];
        
        $stmt = $pdo->prepare("SELECT target_value FROM daily_targets WHERE id = ? AND $condition");
        $stmt->execute([$id, $param]);
        $target_val = $stmt->fetchColumn();
        
        if ($target_val) {
            $status = ($completed >= $target_val) ? 'Completed' : ($completed > 0 ? 'In Progress' : 'Pending');
            $stmt = $pdo->prepare("UPDATE daily_targets SET completed_value = ?, status = ? WHERE id = ? AND $condition");
            $stmt->execute([$completed, $status, $id, $param]);
            $msg = 'Progress updated!';
        }
    } elseif (isset($_POST['delete_target'])) {
        $id = (int)$_POST['target_id'];
        $stmt = $pdo->prepare("DELETE FROM daily_targets WHERE id = ? AND $condition");
        $stmt->execute([$id, $param]);
        $msg = 'Target deleted.';
    }
}

// Fetch all targets for selected date
$stmt = $pdo->prepare("SELECT * FROM daily_targets WHERE $condition AND target_date = ? ORDER BY id DESC");
$stmt->execute([$param, $filter_date]);
$targets = $stmt->fetchAll();

$page_title = 'Daily Targets';
include 'includes/header.php';
?>

<div class="bg-light py-4 mb-5 border-bottom">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-danger mb-1"><i class="fas fa-bullseye me-2"></i>Daily Targets</h2>
            <p class="text-muted mb-0">Set goals and track your daily progress.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#addTargetModal"><i class="fas fa-plus me-1"></i> New Target</button>
            <a href="study-dashboard.php" class="btn btn-outline-secondary">Dashboard</a>
        </div>
    </div>
</div>

<div class="container pb-5">
    
    <?php if($msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-6 offset-md-3">
            <form action="" method="GET" class="d-flex bg-white p-3 rounded-pill shadow-sm">
                <input type="date" name="date" class="form-control border-0 bg-transparent" value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()">
                <button type="submit" class="btn btn-primary rounded-pill px-4">View</button>
            </form>
        </div>
    </div>

    <div class="row">
        <?php if(empty($targets)): ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-bullseye fa-4x mb-3 text-muted opacity-25"></i>
                <h4 class="text-muted">No targets set for <?= date('F d, Y', strtotime($filter_date)) ?></h4>
                <button class="btn btn-danger mt-3" data-bs-toggle="modal" data-bs-target="#addTargetModal">Set Your First Target</button>
            </div>
        <?php else: ?>
            <?php foreach($targets as $t): 
                $pct = $t['target_value'] > 0 ? min(100, round(($t['completed_value'] / $t['target_value']) * 100)) : 0;
                $is_completed = $pct >= 100;
                $card_class = $is_completed ? 'border-success bg-light' : 'border-0 shadow-sm';
                $bar_color = $is_completed ? 'bg-success' : ($pct >= 50 ? 'bg-primary' : 'bg-warning');
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 <?= $card_class ?>">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="badge bg-secondary"><?= escape($t['target_type']) ?></span>
                            <?php if($is_completed): ?>
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Completed</span>
                            <?php else: ?>
                                <span class="badge bg-primary"><?= $t['status'] ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <h5 class="fw-bold mb-2"><?= escape($t['target_description']) ?></h5>
                        
                        <div class="mb-4 mt-4">
                            <div class="d-flex justify-content-between mb-1 small fw-bold">
                                <span>Progress</span>
                                <span><?= $t['completed_value'] ?> / <?= $t['target_value'] ?> (<?= $pct ?>%)</span>
                            </div>
                            <div class="progress" style="height: 12px;">
                                <div class="progress-bar <?= $bar_color ?>" role="progressbar" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>

                        <?php if(!$is_completed): ?>
                            <form action="" method="POST" class="mt-auto border-top pt-3">
                                <input type="hidden" name="target_id" value="<?= $t['id'] ?>">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white">Update</span>
                                    <input type="number" name="completed_value" class="form-control" value="<?= $t['completed_value'] ?>" min="0" max="<?= $t['target_value'] ?>" required>
                                    <button class="btn btn-outline-primary" type="submit" name="update_progress">Save</button>
                                </div>
                            </form>
                        <?php endif; ?>
                        
                        <!-- Delete Form -->
                        <form action="" method="POST" class="position-absolute" style="top: 15px; right: 15px;" onsubmit="return confirm('Delete this target?');">
                            <input type="hidden" name="target_id" value="<?= $t['id'] ?>">
                            <button type="submit" name="delete_target" class="btn btn-link text-danger p-0 border-0"><i class="fas fa-times"></i></button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Target Modal -->
<div class="modal fade" id="addTargetModal" tabindex="-1" aria-labelledby="addTargetModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="" method="POST" class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="addTargetModalLabel"><i class="fas fa-plus me-2"></i> Set New Target</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
          <div class="mb-3">
              <label class="form-label fw-bold">Date</label>
              <input type="date" name="target_date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>" required>
          </div>
          <div class="mb-3">
              <label class="form-label fw-bold">Target Type</label>
              <select name="target_type" class="form-select" required>
                  <option value="Vocabulary">Vocabulary</option>
                  <option value="Reading">Reading</option>
                  <option value="Grammar">Grammar</option>
                  <option value="English Story">English Story</option>
                  <option value="Mock Test">Mock Test</option>
                  <option value="Study Time">Study Time</option>
                  <option value="Other">Other</option>
              </select>
          </div>
          <div class="mb-3">
              <label class="form-label fw-bold">Target Description</label>
              <input type="text" name="target_description" class="form-control" placeholder="e.g. Learn 30 New Words" required>
          </div>
          <div class="mb-3">
              <label class="form-label fw-bold">Target Value (Number)</label>
              <input type="number" name="target_value" class="form-control" placeholder="e.g. 30" min="1" required>
              <small class="text-muted">Enter a number to track your progress (e.g. 30 for words, 60 for minutes).</small>
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" name="add_target" class="btn btn-danger">Save Target</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
