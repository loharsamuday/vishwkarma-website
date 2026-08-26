<?php
// study-time.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/study_auth.php';

$condition = get_study_user_condition();
$param = get_study_user_param();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_session'])) {
        $date = $_POST['study_date'];
        $subject = $_POST['subject'];
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        $notes = $_POST['notes'];

        // Calculate duration in minutes
        $start_ts = strtotime($start);
        $end_ts = strtotime($end);
        
        // Handle case where study goes past midnight
        if ($end_ts < $start_ts) {
            $end_ts += 86400; // Add 24 hours
        }
        
        $duration_minutes = round(abs($end_ts - $start_ts) / 60, 0);

        if ($is_guest) {
            $stmt = $pdo->prepare("INSERT INTO study_sessions (guest_id, study_date, subject, start_time, end_time, duration_minutes, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        } else {
            $stmt = $pdo->prepare("INSERT INTO study_sessions (user_id, study_date, subject, start_time, end_time, duration_minutes, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        }
        
        if ($stmt->execute([$param, $date, $subject, $start, $end, $duration_minutes, $notes])) {
            $msg = 'Study session recorded successfully!';
        }
    } elseif (isset($_POST['delete_session'])) {
        $id = (int)$_POST['session_id'];
        $stmt = $pdo->prepare("DELETE FROM study_sessions WHERE id = ? AND $condition");
        $stmt->execute([$id, $param]);
        $msg = 'Session deleted.';
    }
}

// Fetch all sessions
$stmt = $pdo->prepare("SELECT * FROM study_sessions WHERE $condition ORDER BY study_date DESC, start_time DESC LIMIT 50");
$stmt->execute([$param]);
$sessions = $stmt->fetchAll();

$page_title = 'Study Time Manager';
include 'includes/header.php';
?>

<div class="bg-light py-4 mb-5 border-bottom">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-warning mb-1"><i class="fas fa-stopwatch me-2 text-dark"></i>Study Time</h2>
            <p class="text-muted mb-0">Record and track your learning hours.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <button class="btn btn-warning btn-sm px-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addSessionModal"><i class="fas fa-plus me-1"></i> Session</button>
            <a href="study-dashboard.php" class="btn btn-outline-secondary btn-sm px-3 shadow-sm">Dashboard</a>
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

    <div class="row">
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h4 class="fw-bold">Recent Study Sessions</h4>
                </div>
                <div class="card-body p-4">
                    <?php if(empty($sessions)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-history fa-3x mb-3 opacity-25"></i>
                            <h5>No study sessions recorded yet.</h5>
                            <button class="btn btn-sm btn-outline-warning mt-2 fw-bold" data-bs-toggle="modal" data-bs-target="#addSessionModal">Log Your First Session</button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Subject</th>
                                        <th>Time</th>
                                        <th>Duration</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($sessions as $s): 
                                        $h = floor($s['duration_minutes'] / 60);
                                        $m = $s['duration_minutes'] % 60;
                                        $duration_str = ($h > 0 ? $h . ' Hour' . ($h > 1 ? 's ' : ' ') : '') . ($m > 0 ? $m . ' Min' : '');
                                        if ($duration_str == '') $duration_str = '0 Min';
                                    ?>
                                    <tr>
                                        <td><strong><?= date('M d, Y', strtotime($s['study_date'])) ?></strong></td>
                                        <td>
                                            <?= escape($s['subject']) ?>
                                            <?php if($s['notes']): ?><br><small class="text-muted"><?= escape($s['notes']) ?></small><?php endif; ?>
                                        </td>
                                        <td><?= date('h:i A', strtotime($s['start_time'])) ?> - <?= date('h:i A', strtotime($s['end_time'])) ?></td>
                                        <td><span class="badge bg-info fw-bold fs-6 text-dark"><?= $duration_str ?></span></td>
                                        <td class="text-end">
                                            <form action="" method="POST" onsubmit="return confirm('Delete this session?');">
                                                <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                                <button type="submit" name="delete_session" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Session Modal -->
<div class="modal fade" id="addSessionModal" tabindex="-1" aria-labelledby="addSessionModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="" method="POST" class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title fw-bold" id="addSessionModalLabel"><i class="fas fa-plus me-2"></i> Record Study Session</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
          <div class="mb-3">
              <label class="form-label fw-bold">Date</label>
              <input type="date" name="study_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="mb-3">
              <label class="form-label fw-bold">Subject / Topic</label>
              <input type="text" name="subject" class="form-control" placeholder="e.g. Grammar Rules" required>
          </div>
          <div class="row mb-3">
              <div class="col-md-6">
                  <label class="form-label fw-bold">Start Time</label>
                  <input type="time" name="start_time" class="form-control" id="start_time" required>
              </div>
              <div class="col-md-6">
                  <label class="form-label fw-bold">End Time</label>
                  <input type="time" name="end_time" class="form-control" id="end_time" required>
              </div>
          </div>
          <div class="mb-3">
              <label class="form-label fw-bold">Calculated Duration</label>
              <input type="text" id="calculated_duration" class="form-control-plaintext text-success fw-bold" value="0 Mins" readonly>
          </div>
          <div class="mb-3">
              <label class="form-label fw-bold">Notes (Optional)</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" name="add_session" class="btn btn-warning fw-bold">Save Session</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startInput = document.getElementById('start_time');
    const endInput = document.getElementById('end_time');
    const durationDisplay = document.getElementById('calculated_duration');

    function calculateDuration() {
        if(startInput.value && endInput.value) {
            let start = new Date("1970-01-01T" + startInput.value + "Z");
            let end = new Date("1970-01-01T" + endInput.value + "Z");
            
            if(end < start) {
                end.setDate(end.getDate() + 1); // Cross midnight
            }
            
            let diffMins = Math.round((end - start) / 60000);
            let h = Math.floor(diffMins / 60);
            let m = diffMins % 60;
            
            let str = '';
            if(h > 0) { str += h + ' Hour' + (h>1?'s':'') + ' '; }
            if(m > 0) { str += m + ' Min' + (m>1?'s':''); }
            if(str == '') str = '0 Mins';
            
            durationDisplay.value = str;
        } else {
            durationDisplay.value = '0 Mins';
        }
    }

    startInput.addEventListener('change', calculateDuration);
    endInput.addEventListener('change', calculateDuration);
});
</script>

<?php include 'includes/footer.php'; ?>
