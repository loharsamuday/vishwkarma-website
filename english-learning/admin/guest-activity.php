<?php
// admin/guest-activity.php
session_start();
$page_title = 'Guest Study Activity';
include 'includes/header.php';

// Fetch Guest Routines
$stmt_routines = $pdo->query("SELECT * FROM study_routines WHERE guest_id IS NOT NULL ORDER BY created_at DESC LIMIT 50");
$guest_routines = $stmt_routines->fetchAll();

// Fetch Guest Sessions
$stmt_sessions = $pdo->query("SELECT * FROM study_sessions WHERE guest_id IS NOT NULL ORDER BY created_at DESC LIMIT 50");
$guest_sessions = $stmt_sessions->fetchAll();

// Fetch Guest Targets
$stmt_targets = $pdo->query("SELECT * FROM daily_targets WHERE guest_id IS NOT NULL ORDER BY created_at DESC LIMIT 50");
$guest_targets = $stmt_targets->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-user-secret me-2"></i>Guest Study Activity</h1>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">Back to Dashboard</a>
</div>

<p class="text-muted">This page shows the recent activity of unregistered users (guests) using the Study Management tools.</p>

<div class="row">
    <!-- Guest Routines -->
    <div class="col-lg-12 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white fw-bold">
                <i class="fas fa-calendar-day me-2"></i> Recent Guest Routines (Tasks)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Guest ID</th>
                                <th>Date</th>
                                <th>Task Title</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($guest_routines)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No guest routines found.</td></tr>
                            <?php else: ?>
                                <?php foreach($guest_routines as $row): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= substr($row['guest_id'], 0, 15) ?>...</span></td>
                                    <td><?= date('Y-m-d', strtotime($row['routine_date'])) ?></td>
                                    <td><?= escape($row['task_title']) ?></td>
                                    <td><?= escape($row['category']) ?></td>
                                    <td>
                                        <?php if($row['status'] == 'Completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><?= escape($row['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= $row['created_at'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Guest Sessions -->
    <div class="col-lg-12 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light border-bottom fw-bold text-dark">
                <i class="fas fa-stopwatch me-2"></i> Recent Guest Study Sessions
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Guest ID</th>
                                <th>Date</th>
                                <th>Subject</th>
                                <th>Duration</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($guest_sessions)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No guest study sessions found.</td></tr>
                            <?php else: ?>
                                <?php foreach($guest_sessions as $row): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= substr($row['guest_id'], 0, 15) ?>...</span></td>
                                    <td><?= date('Y-m-d', strtotime($row['study_date'])) ?></td>
                                    <td><?= escape($row['subject']) ?></td>
                                    <td><strong><?= $row['duration_minutes'] ?> mins</strong></td>
                                    <td class="small text-muted"><?= $row['created_at'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Guest Targets -->
    <div class="col-lg-12 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-danger text-white fw-bold">
                <i class="fas fa-bullseye me-2"></i> Recent Guest Targets
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Guest ID</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($guest_targets)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No guest targets found.</td></tr>
                            <?php else: ?>
                                <?php foreach($guest_targets as $row): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= substr($row['guest_id'], 0, 15) ?>...</span></td>
                                    <td><?= date('Y-m-d', strtotime($row['target_date'])) ?></td>
                                    <td><?= escape($row['target_type']) ?></td>
                                    <td><?= escape($row['target_description']) ?></td>
                                    <td><?= $row['completed_value'] ?> / <?= $row['target_value'] ?></td>
                                    <td>
                                        <?php if($row['status'] == 'Completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary"><?= escape($row['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
