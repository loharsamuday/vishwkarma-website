<?php
// daily-routine.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/study_auth.php';

$condition = get_study_user_condition();
$param = get_study_user_param();
$today = date('Y-m-d');
$filter_date = isset($_GET['date']) ? $_GET['date'] : $today;

// Handle Add/Edit/Delete/Complete
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_routine'])) {
        $date = $_POST['routine_date'];
        $title = $_POST['task_title'];
        $category = $_POST['category'];
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        $priority = $_POST['priority'];
        $notes = $_POST['notes'];

        if ($is_guest) {
            $stmt = $pdo->prepare("INSERT INTO study_routines (guest_id, routine_date, task_title, category, start_time, end_time, priority, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        } else {
            $stmt = $pdo->prepare("INSERT INTO study_routines (user_id, routine_date, task_title, category, start_time, end_time, priority, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        }
        
        if ($stmt->execute([$param, $date, $title, $category, $start, $end, $priority, $notes])) {
            $msg = 'Routine added successfully.';
        }
    } elseif (isset($_POST['mark_completed'])) {
        $id = (int)$_POST['routine_id'];
        $stmt = $pdo->prepare("UPDATE study_routines SET status = 'Completed', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND $condition");
        $stmt->execute([$id, $param]);
    } elseif (isset($_POST['delete_routine'])) {
        $id = (int)$_POST['routine_id'];
        $stmt = $pdo->prepare("DELETE FROM study_routines WHERE id = ? AND $condition");
        $stmt->execute([$id, $param]);
        $msg = 'Routine deleted.';
    }
}

// Fetch Routines (Master Daily Schedule)
$stmt = $pdo->prepare("SELECT *, IF(DATE(updated_at) < CURRENT_DATE AND status = 'Completed', 'Pending', status) as current_status FROM study_routines WHERE $condition ORDER BY start_time ASC");
$stmt->execute([$param]);
$routines = $stmt->fetchAll();

$page_title = 'Daily Routine Schedule';
include 'includes/header.php';
?>

<div class="bg-light py-4 mb-5 border-bottom">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-success mb-1"><i class="fas fa-calendar-day me-2"></i>My Daily Schedule</h2>
            <p class="text-muted mb-0">This is your fixed master routine. It repeats every day automatically.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <button class="btn btn-success btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addRoutineModal"><i class="fas fa-plus me-1"></i> Task</button>
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
                <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                    <h4 class="fw-bold"><i class="far fa-clock text-primary me-2"></i> Daily Time Table</h4>
                    <span class="badge bg-primary rounded-pill">Repeats Daily</span>
                </div>
                <div class="card-body p-4">
                    <?php if(empty($routines)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-tasks fa-3x mb-3 opacity-25"></i>
                            <h5>No tasks in your daily schedule yet.</h5>
                            <p>Add your fixed study times, and they will reset every day automatically!</p>
                            <button class="btn btn-sm btn-outline-success mt-2" data-bs-toggle="modal" data-bs-target="#addRoutineModal">Create a Task</button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Time</th>
                                        <th>Task</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Today's Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($routines as $r): 
                                        $is_completed = $r['current_status'] == 'Completed';
                                    ?>
                                    <tr class="<?= $is_completed ? 'table-light text-muted' : '' ?>">
                                        <td class="text-nowrap fw-bold small">
                                            <?= date('h:i A', strtotime($r['start_time'])) ?> - <br>
                                            <?= date('h:i A', strtotime($r['end_time'])) ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold <?= $is_completed ? 'text-decoration-line-through' : '' ?>"><?= escape($r['task_title']) ?></div>
                                            <?php if($r['notes']): ?><small class="text-muted"><?= escape($r['notes']) ?></small><?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-secondary"><?= escape($r['category']) ?></span></td>
                                        <td>
                                            <?php if($r['priority'] == 'High'): ?> <span class="badge bg-danger">High</span>
                                            <?php elseif($r['priority'] == 'Medium'): ?> <span class="badge bg-warning text-dark">Medium</span>
                                            <?php else: ?> <span class="badge bg-info">Low</span> <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($is_completed): ?> <span class="badge bg-success"><i class="fas fa-check"></i> Done Today</span>
                                            <?php else: ?> <span class="badge bg-secondary">Pending</span> <?php endif; ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <?php if(!$is_completed): ?>
                                                <form action="" method="POST" class="d-inline">
                                                    <input type="hidden" name="routine_id" value="<?= $r['id'] ?>">
                                                    <button type="submit" name="mark_completed" class="btn btn-sm btn-success" title="Mark as Completed"><i class="fas fa-check"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('Delete this task from your master schedule?');">
                                                <input type="hidden" name="routine_id" value="<?= $r['id'] ?>">
                                                <button type="submit" name="delete_routine" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
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

<!-- Add Routine Modal -->
<div class="modal fade" id="addRoutineModal" tabindex="-1" aria-labelledby="addRoutineModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="" method="POST" class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="addRoutineModalLabel"><i class="fas fa-plus me-2"></i> Add Daily Task</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
          <input type="hidden" name="routine_date" value="<?= date('Y-m-d') ?>">
          <div class="mb-3">
              <label class="form-label fw-bold">Task Title</label>
              <input type="text" name="task_title" class="form-control" placeholder="e.g. Read Chapter 1" required>
          </div>
          <div class="row mb-3">
              <div class="col-md-6">
                  <label class="form-label fw-bold">Start Time</label>
                  <input type="time" name="start_time" class="form-control" required>
              </div>
              <div class="col-md-6">
                  <label class="form-label fw-bold">End Time</label>
                  <input type="time" name="end_time" class="form-control" required>
              </div>
          </div>
          <div class="row mb-3">
              <div class="col-md-6">
                  <label class="form-label fw-bold">Category</label>
                  <select name="category" class="form-select" required>
                      <option value="Vocabulary">Vocabulary</option>
                      <option value="Grammar">Grammar</option>
                      <option value="English Story">English Story</option>
                      <option value="Reading">Reading</option>
                      <option value="Practice">Practice</option>
                      <option value="Mock Test">Mock Test</option>
                      <option value="Revision">Revision</option>
                      <option value="Other">Other</option>
                  </select>
              </div>
              <div class="col-md-6">
                  <label class="form-label fw-bold">Priority</label>
                  <select name="priority" class="form-select" required>
                      <option value="High">High</option>
                      <option value="Medium" selected>Medium</option>
                      <option value="Low">Low</option>
                  </select>
              </div>
          </div>
          <div class="mb-3">
              <label class="form-label fw-bold">Notes (Optional)</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" name="add_routine" class="btn btn-success">Save Task</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
