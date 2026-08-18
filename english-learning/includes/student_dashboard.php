<?php
// includes/student_dashboard.php
$is_logged_in = isset($_SESSION['user_id']);
$current_time = date('H:i:s');

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $first_name = explode(' ', trim($_SESSION['user_name']))[0];

    // Fetch today's tasks
    $stmt = $pdo->prepare("SELECT * FROM student_tasks WHERE user_id = ? AND task_date = CURRENT_DATE ORDER BY status ASC, priority DESC");
    $stmt->execute([$user_id]);
    $today_tasks = $stmt->fetchAll();

    $total_tasks = count($today_tasks);
    $completed_tasks = 0;
    foreach($today_tasks as $t) {
        if($t['status'] === 'Completed') $completed_tasks++;
    }
    $progress_pct = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

    // Fetch routines
    $stmt = $pdo->prepare("SELECT * FROM student_routines WHERE user_id = ? ORDER BY start_time ASC");
    $stmt->execute([$user_id]);
    $routines = $stmt->fetchAll();

    // Fetch goals
    $stmt = $pdo->prepare("SELECT * FROM student_goals WHERE user_id = ? AND status = 'Active' ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $goals = $stmt->fetchAll();

    // Fetch today's study time
    $stmt = $pdo->prepare("SELECT SUM(duration_minutes) as total_time FROM student_focus_sessions WHERE user_id = ? AND DATE(created_at) = CURRENT_DATE");
    $stmt->execute([$user_id]);
    $study_time_row = $stmt->fetch();
    $total_study_minutes = $study_time_row['total_time'] ? (int)$study_time_row['total_time'] : 0;
    $study_hours = floor($total_study_minutes / 60);
    $study_mins = $total_study_minutes % 60;

    // Fetch Streak
    $stmt = $pdo->prepare("SELECT current_streak FROM student_daily_stats WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    $current_streak = $stats ? (int)$stats['current_streak'] : 0;

    // Calculate Productivity Score (Max 100)
    $score_tasks = $progress_pct * 0.5;
    $score_study = min(50, ($total_study_minutes / 120) * 50);
    $productivity_score = round($score_tasks + $score_study);

    // Mock Weekly Data for Analytics
    $weekly_data = ['Mon' => 60, 'Tue' => 80, 'Wed' => 45, 'Thu' => 90, 'Fri' => $productivity_score, 'Sat' => 0, 'Sun' => 0];

} else {
    // GUEST DEMO MODE
    $first_name = "Future Achiever";
    $today_tasks = [
        ['id'=>1, 'status'=>'Pending', 'priority'=>'High', 'title'=>'Complete English Vocabulary Chapter 1', 'subject'=>'English', 'category'=>'Study', 'estimated_minutes'=>45],
        ['id'=>2, 'status'=>'Completed', 'priority'=>'Medium', 'title'=>'Revise Banking Awareness', 'subject'=>'Banking', 'category'=>'Revision', 'estimated_minutes'=>60],
        ['id'=>3, 'status'=>'Pending', 'priority'=>'High', 'title'=>'Solve 50 Reasoning Puzzles', 'subject'=>'Reasoning', 'category'=>'Practice', 'estimated_minutes'=>30]
    ];
    $total_tasks = 3;
    $completed_tasks = 1;
    $progress_pct = 33;

    $routines = [
        ['id'=>1, 'title'=>'Morning Exercise', 'category'=>'Exercise', 'start_time'=>'06:00:00', 'end_time'=>'07:00:00'],
        ['id'=>2, 'title'=>'Vocabulary Revision', 'category'=>'Study', 'start_time'=>'07:30:00', 'end_time'=>'09:00:00'],
        ['id'=>3, 'title'=>'Mock Test Session', 'category'=>'Study', 'start_time'=>'10:00:00', 'end_time'=>'12:00:00']
    ];

    $goals = [
        ['id'=>1, 'goal_name'=>'Master 2000 English Words', 'current_value'=>450, 'target_value'=>2000],
        ['id'=>2, 'goal_name'=>'Score 80+ in SBI PO Mock', 'current_value'=>65, 'target_value'=>80]
    ];

    $total_study_minutes = 150;
    $study_hours = 2;
    $study_mins = 30;
    $current_streak = 14;
    $productivity_score = 78;
    $weekly_data = ['Mon' => 70, 'Tue' => 85, 'Wed' => 60, 'Thu' => 95, 'Fri' => 78, 'Sat' => 0, 'Sun' => 0];
}
?>

<!-- Custom CSS for Dashboard -->
<style>
    .dashboard-bg { background-color: #f4f7f6; }
    .card-rounded { border-radius: 1rem; border: none; }
    .task-item { transition: all 0.2s; border-left: 4px solid transparent; }
    .task-item:hover { background-color: #f8f9fa; }
    .task-item.priority-High { border-left-color: #e74c3c; }
    .task-item.priority-Medium { border-left-color: #f39c12; }
    .task-item.priority-Low { border-left-color: #2ecc71; }
    .task-completed { opacity: 0.6; }
    .task-completed .task-title { text-decoration: line-through; }
    .progress-ring { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: conic-gradient(#3498db <?= $progress_pct ?>%, #e0e0e0 0); }
    .progress-ring-inner { width: 65px; height: 65px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }
    .form-check-input-lg { width: 1.5em; height: 1.5em; cursor: pointer; }
    
    /* Timeline styles */
    .timeline { position: relative; padding-left: 1.5rem; list-style: none; margin-bottom: 0; }
    .timeline::before { content: ''; position: absolute; top: 0; bottom: 0; left: 0.35rem; width: 2px; background: #e9ecef; }
    .timeline-item { position: relative; margin-bottom: 1.5rem; }
    .timeline-item::before { content: ''; position: absolute; left: -1.5rem; top: 0.25rem; width: 12px; height: 12px; border-radius: 50%; background: #3498db; border: 2px solid white; box-shadow: 0 0 0 1px #3498db; }
    .timeline-item.past::before { background: #2ecc71; box-shadow: 0 0 0 1px #2ecc71; }
    .timeline-item.current::before { background: #f39c12; box-shadow: 0 0 0 2px #f39c12; animation: pulse 2s infinite; }
    @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(243, 156, 18, 0.7); } 70% { box-shadow: 0 0 0 6px rgba(243, 156, 18, 0); } 100% { box-shadow: 0 0 0 0 rgba(243, 156, 18, 0); } }
</style>

<div class="dashboard-bg py-4 min-vh-100">
    <div class="container">
        
        <!-- Welcome Header -->
        <div class="row mb-4 align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bold mb-1">Good Morning, <?= escape($first_name) ?> 👋</h2>
                <p class="text-muted mb-0"><i class="far fa-calendar-alt me-1"></i> <?= date('l, d F Y') ?> &bull; "Make today count."</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <button class="btn btn-primary bg-primary-custom rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                    <i class="fas fa-plus me-1"></i> Add Task
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4 g-3">
            <div class="col-md-3 col-6">
                <div class="card card-rounded shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="progress-ring me-3">
                            <div class="progress-ring-inner text-primary-custom fs-5"><?= $progress_pct ?>%</div>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Progress</h6>
                            <h4 class="fw-bold mb-0"><?= $completed_tasks ?>/<?= $total_tasks ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card card-rounded shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-warning mb-2"><i class="fas fa-fire fa-2x"></i></div>
                        <h6 class="text-muted mb-1">Current Streak</h6>
                        <h4 class="fw-bold mb-0"><?= $current_streak ?> Days</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card card-rounded shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-info mb-2"><i class="fas fa-clock fa-2x"></i></div>
                        <h6 class="text-muted mb-1">Study Time</h6>
                        <h4 class="fw-bold mb-0"><?= $study_hours ?>h <?= $study_mins ?>m</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card card-rounded shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-success mb-2"><i class="fas fa-bullseye fa-2x"></i></div>
                        <h6 class="text-muted mb-1">Productivity Score</h6>
                        <h4 class="fw-bold mb-0"><?= $productivity_score ?>/100</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- To-Do List Panel -->
            <div class="col-lg-7 mb-4">
                <div class="card card-rounded shadow-sm h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="fas fa-list-check text-primary-custom me-2"></i> Today's To-Do</h5>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">All Tasks</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#">All</a></li>
                                <li><a class="dropdown-item" href="#">Pending</a></li>
                                <li><a class="dropdown-item" href="#">Completed</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body px-0">
                        <ul class="list-group list-group-flush" id="taskList">
                            <?php if(empty($today_tasks)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="far fa-clipboard fa-3x mb-3 opacity-50"></i>
                                    <h5>No tasks for today</h5>
                                    <p class="small">Plan your first task and start your day.</p>
                                    <button class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#addTaskModal">Add First Task</button>
                                </div>
                            <?php else: ?>
                                <?php foreach($today_tasks as $task): 
                                    $is_comp = $task['status'] === 'Completed';
                                ?>
                                <li class="list-group-item py-3 task-item priority-<?= $task['priority'] ?> <?= $is_comp ? 'task-completed' : '' ?>" id="task-<?= $task['id'] ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <input class="form-check-input form-check-input-lg task-checkbox" type="checkbox" data-id="<?= $task['id'] ?>" <?= $is_comp ? 'checked' : '' ?>>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fw-bold task-title"><?= escape($task['title']) ?></h6>
                                            <div class="d-flex align-items-center text-muted small">
                                                <span class="badge bg-light text-dark border me-2"><?= escape($task['subject'] ?: $task['category']) ?></span>
                                                <span class="me-2"><i class="far fa-clock me-1"></i><?= $task['estimated_minutes'] ?>m</span>
                                                <?php if($task['priority'] == 'High'): ?>
                                                    <span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>High</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="ms-2">
                                            <button class="btn btn-sm btn-light text-danger btn-delete-task" data-id="<?= $task['id'] ?>"><i class="fas fa-trash-alt"></i></button>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-5 mb-4">
                <div class="card card-rounded shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="far fa-clock text-info me-2"></i> My Daily Routine</h5>
                        <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#addRoutineModal"><i class="fas fa-plus"></i></button>
                    </div>
                    <div class="card-body">
                        <?php if(empty($routines)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-calendar-day fa-2x mb-2 opacity-50"></i>
                                <h6>No routine set</h6>
                                <p class="small">Create a schedule to build consistency.</p>
                            </div>
                        <?php else: ?>
                            <ul class="timeline mt-2">
                                <?php foreach($routines as $routine): 
                                    $start = date('H:i', strtotime($routine['start_time']));
                                    $end = date('H:i', strtotime($routine['end_time']));
                                    $is_past = $current_time > $routine['end_time'];
                                    $is_current = ($current_time >= $routine['start_time'] && $current_time <= $routine['end_time']);
                                    $status_class = $is_past ? 'past' : ($is_current ? 'current' : '');
                                ?>
                                <li class="timeline-item <?= $status_class ?>">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <span class="fw-bold <?= $is_current ? 'text-warning' : ($is_past ? 'text-success' : 'text-dark') ?>"><?= $start ?> - <?= $end ?></span>
                                            <p class="mb-0 text-muted"><?= escape($routine['title']) ?> <span class="badge bg-light text-dark ms-1"><?= escape($routine['category']) ?></span></p>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm text-danger p-0 ms-2 btn-delete-routine" data-id="<?= $routine['id'] ?>"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card card-rounded shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="fas fa-bullseye text-success me-2"></i> My Goals</h5>
                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addGoalModal"><i class="fas fa-plus"></i></button>
                    </div>
                    <div class="card-body">
                        <?php if(empty($goals)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-bullseye fa-2x mb-2 opacity-50"></i>
                                <h6>No active goals</h6>
                                <p class="small">Set long-term targets to stay focused.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($goals as $goal): 
                                $goal_pct = $goal['target_value'] > 0 ? min(100, round(($goal['current_value'] / $goal['target_value']) * 100)) : 0;
                            ?>
                            <div class="mb-3 position-relative" id="goal-<?= $goal['id'] ?>">
                                <div class="d-flex justify-content-between align-items-end mb-1">
                                    <span class="fw-bold small"><?= escape($goal['goal_name']) ?></span>
                                    <span class="small text-muted"><?= $goal['current_value'] ?> / <?= $goal['target_value'] ?> (<?= $goal_pct ?>%)</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $goal_pct ?>%"></div>
                                </div>
                                <button class="btn btn-sm text-danger p-0 position-absolute btn-delete-goal" style="top: -2px; right: -25px;" data-id="<?= $goal['id'] ?>"><i class="fas fa-times"></i></button>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Focus Timer Card -->
                <div class="card card-rounded shadow-sm mt-4 text-center">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="fas fa-stopwatch text-danger me-2"></i> Focus Timer</h5>
                        
                        <div class="d-flex justify-content-center gap-2 mb-4">
                            <button class="btn btn-outline-danger btn-sm timer-preset" data-time="25">25m</button>
                            <button class="btn btn-outline-danger btn-sm timer-preset" data-time="50">50m</button>
                            <button class="btn btn-outline-danger btn-sm timer-preset" data-time="90">90m</button>
                        </div>
                        
                        <div class="mb-3">
                            <select class="form-select bg-light border-0 mx-auto w-75" id="timer_task_id">
                                <option value="">Select Task (Optional)</option>
                                <?php foreach($today_tasks as $t): if($t['status'] !== 'Completed'): ?>
                                    <option value="<?= $t['id'] ?>"><?= escape($t['title']) ?></option>
                                <?php endif; endforeach; ?>
                            </select>
                        </div>

                        <div class="display-1 fw-bold text-dark mb-4 font-monospace" id="timerDisplay">25:00</div>
                        
                        <div class="d-flex justify-content-center gap-2">
                            <button class="btn btn-danger rounded-pill px-4" id="btnStartTimer">Start</button>
                            <button class="btn btn-warning rounded-pill px-4 text-dark d-none" id="btnPauseTimer">Pause</button>
                            <button class="btn btn-success rounded-pill px-4 d-none" id="btnCompleteTimer">Complete Session</button>
                        </div>
                    </div>
                </div>

                <!-- Weekly Analytics Placeholder -->
                <div class="card card-rounded shadow-sm mt-4">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold mb-0"><i class="fas fa-chart-bar text-primary-custom me-2"></i> Weekly Productivity</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-end" style="height: 120px;">
                            <?php foreach($weekly_data as $day => $score): 
                                $height = max(10, $score); // minimum height
                                $color = $score >= 80 ? 'bg-success' : ($score >= 50 ? 'bg-primary' : 'bg-secondary');
                            ?>
                            <div class="text-center w-100 px-1">
                                <div class="progress progress-bar-vertical mx-auto rounded" style="width: 15px; height: 100px; background: transparent; border-radius: 10px; display: flex; flex-direction: column-reverse;">
                                    <div class="progress-bar <?= $color ?> rounded" role="progressbar" style="height: <?= $height ?>%; width: 100%;"></div>
                                </div>
                                <small class="text-muted" style="font-size: 0.7rem;"><?= $day ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Night Mode / Daily Review -->
        <div class="row mt-2 mb-5">
            <div class="col-12">
                <div class="card card-rounded shadow-sm bg-dark text-white overflow-hidden">
                    <div class="card-body p-5 position-relative">
                        <!-- Background decoration -->
                        <i class="fas fa-moon position-absolute opacity-10" style="font-size: 15rem; top: -30px; right: 20px; color: #fff;"></i>
                        
                        <div class="row align-items-center position-relative z-index-1">
                            <div class="col-md-5 mb-4 mb-md-0">
                                <h3 class="fw-bold mb-3"><i class="fas fa-moon text-warning me-2"></i> Evening Review</h3>
                                <p class="text-light opacity-75 mb-4">Reflect on your day, log your progress, and prepare for tomorrow to maintain your streak.</p>
                                
                                <div class="d-flex gap-4 mb-3">
                                    <div>
                                        <h2 class="fw-bold mb-0 text-success"><?= $productivity_score ?>%</h2>
                                        <span class="small text-light opacity-75">Productivity</span>
                                    </div>
                                    <div>
                                        <h2 class="fw-bold mb-0 text-info"><?= $study_hours ?>h <?= $study_mins ?>m</h2>
                                        <span class="small text-light opacity-75">Study Time</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-7">
                                <div class="bg-white bg-opacity-10 p-4 rounded-3 border border-light border-opacity-10">
                                    <form id="reviewForm">
                                        <!-- Hidden Inputs for metrics -->
                                        <input type="hidden" id="rev_study_mins" value="<?= $total_study_minutes ?>">
                                        <input type="hidden" id="rev_tasks_comp" value="<?= $completed_tasks ?>">
                                        <input type="hidden" id="rev_tasks_tot" value="<?= $total_tasks ?>">
                                        <input type="hidden" id="rev_prod_score" value="<?= $productivity_score ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-bold text-light">What did you learn today?</label>
                                            <textarea class="form-control bg-dark text-white border-secondary" id="rev_learning_note" rows="2" placeholder="Write a short reflection..."></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold text-light">Tomorrow's top priority</label>
                                            <input type="text" class="form-control bg-dark text-white border-secondary" id="rev_tomorrow_priority" placeholder="E.g. Complete Mock Test">
                                        </div>
                                        <button type="submit" class="btn btn-warning fw-bold px-4 rounded-pill">Complete Day & Save Streak 🔥</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-rounded border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Add New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addTaskForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Task Name</label>
                        <input type="text" class="form-control bg-light border-0" id="task_title" required placeholder="e.g. Complete Vocabulary Chapter 1">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Subject</label>
                            <input type="text" class="form-control bg-light border-0" id="task_subject" placeholder="e.g. English">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Priority</label>
                            <select class="form-select bg-light border-0" id="task_priority">
                                <option value="High">🔴 High</option>
                                <option value="Medium" selected>🟠 Medium</option>
                                <option value="Low">🟢 Low</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-6">
                            <label class="form-label fw-bold">Estimated Time (min)</label>
                            <input type="number" class="form-control bg-light border-0" id="task_est_time" value="30" min="5" step="5">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Link to Goal</label>
                            <select class="form-select bg-light border-0" id="task_goal_id">
                                <option value="">None</option>
                                <?php foreach($goals as $g): ?>
                                    <option value="<?= $g['id'] ?>"><?= escape($g['goal_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary bg-primary-custom w-100 rounded-pill py-2 fw-bold">Save Task</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Routine Modal -->
<div class="modal fade" id="addRoutineModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-rounded border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Add Routine Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addRoutineForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Activity Name</label>
                        <input type="text" class="form-control bg-light border-0" id="routine_title" required placeholder="e.g. English Vocabulary">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Start Time</label>
                            <input type="time" class="form-control bg-light border-0" id="routine_start" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">End Time</label>
                            <input type="time" class="form-control bg-light border-0" id="routine_end" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Category</label>
                        <select class="form-select bg-light border-0" id="routine_category">
                            <option value="Study">Study</option>
                            <option value="Break">Break</option>
                            <option value="Exercise">Exercise</option>
                            <option value="Personal">Personal</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-info text-white w-100 rounded-pill py-2 fw-bold">Save Routine</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Goal Modal -->
<div class="modal fade" id="addGoalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-rounded border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Create New Goal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addGoalForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Goal Name</label>
                        <input type="text" class="form-control bg-light border-0" id="goal_name" required placeholder="e.g. Learn 2000 Words">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Target Value (Number)</label>
                            <input type="number" class="form-control bg-light border-0" id="goal_target" required min="1" value="100">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Target Date</label>
                            <input type="date" class="form-control bg-light border-0" id="goal_date" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success text-white w-100 rounded-pill py-2 fw-bold">Save Goal</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast for Notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
  <div id="dashboardToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body fw-bold" id="toastMessage">Action successful!</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const toast = new bootstrap.Toast(document.getElementById('dashboardToast'));
    
    function showToast(message, isError = false) {
        const toastEl = document.getElementById('dashboardToast');
        document.getElementById('toastMessage').innerText = message;
        toastEl.classList.remove('bg-success', 'bg-danger');
        toastEl.classList.add(isError ? 'bg-danger' : 'bg-success');
        toast.show();
    }

    // Routine Alarm Checker
    const userRoutines = <?= json_encode($routines ?? []) ?>;
    const notifiedRoutines = new Set(); 

    setInterval(() => {
        const now = new Date();
        const currentTime = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
        
        userRoutines.forEach(routine => {
            if(!routine.start_time || !routine.end_time) return;
            const startTimeStr = routine.start_time.substring(0, 5);
            const endTimeStr = routine.end_time.substring(0, 5);
            
            if (currentTime === startTimeStr && !notifiedRoutines.has(routine.id + '_start')) {
                const alarmSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
                alarmSound.play().catch(e => console.log('Audio error:', e));
                showToast(`🔔 Routine Starting: ${routine.title}`);
                notifiedRoutines.add(routine.id + '_start');
            }
            
            if (currentTime === endTimeStr && !notifiedRoutines.has(routine.id + '_end')) {
                const alarmSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
                alarmSound.play().catch(e => console.log('Audio error:', e));
                showToast(`✅ Routine Ended: ${routine.title}`);
                notifiedRoutines.add(routine.id + '_end');
                // Reload after a few seconds to update timeline styles (current vs past)
                setTimeout(() => location.reload(), 3000);
            }
        });
    }, 10000); // Check every 10 seconds

    // Add Task
    document.getElementById('addTaskForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const title = document.getElementById('task_title').value;
        const subject = document.getElementById('task_subject').value;
        const priority = document.getElementById('task_priority').value;
        const estTime = document.getElementById('task_est_time').value;
        const goalId = document.getElementById('task_goal_id').value;

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('title', title);
        formData.append('subject', subject);
        formData.append('priority', priority);
        formData.append('estimated_minutes', estTime);
        if(goalId) formData.append('goal_id', goalId);

        fetch('ajax/manage_task.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    showToast('Task added successfully!');
                    setTimeout(() => location.reload(), 1000); // Reload to calculate progress ring properly for now
                } else {
                    showToast(data.message, true);
                }
            });
    });

    // Toggle Task
    document.querySelectorAll('.task-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const taskId = this.getAttribute('data-id');
            const isCompleted = this.checked;
            const li = document.getElementById('task-' + taskId);

            if(isCompleted) li.classList.add('task-completed');
            else li.classList.remove('task-completed');

            const formData = new FormData();
            formData.append('action', 'toggle_complete');
            formData.append('task_id', taskId);
            formData.append('is_completed', isCompleted);

            fetch('ajax/manage_task.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.status === 'success') {
                        // Play a sound or show small visual cue
                        if(isCompleted) showToast('Task completed! Great job.');
                        // Reload strictly for updating circular progress calculation for phase 1
                        setTimeout(() => location.reload(), 800);
                    }
                });
        });
    });

    // Delete Task
    document.querySelectorAll('.btn-delete-task').forEach(btn => {
        btn.addEventListener('click', function() {
            if(!confirm('Delete this task?')) return;
            const taskId = this.getAttribute('data-id');
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('task_id', taskId);

            fetch('ajax/manage_task.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.status === 'success') {
                        document.getElementById('task-' + taskId).remove();
                        showToast('Task deleted.');
                        setTimeout(() => location.reload(), 800);
                    }
                });
        });
    });

    // Add Routine
    document.getElementById('addRoutineForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('title', document.getElementById('routine_title').value);
        formData.append('start_time', document.getElementById('routine_start').value);
        formData.append('end_time', document.getElementById('routine_end').value);
        formData.append('category', document.getElementById('routine_category').value);

        fetch('ajax/manage_routine.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    showToast('Routine added!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, true);
                }
            });
    });

    // Delete Routine
    document.querySelectorAll('.btn-delete-routine').forEach(btn => {
        btn.addEventListener('click', function() {
            if(!confirm('Delete this routine event?')) return;
            const routineId = this.getAttribute('data-id');
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('routine_id', routineId);

            fetch('ajax/manage_routine.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.status === 'success') {
                        showToast('Routine deleted.');
                        setTimeout(() => location.reload(), 800);
                    }
                });
        });
    });

    // Add Goal
    document.getElementById('addGoalForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('goal_name', document.getElementById('goal_name').value);
        formData.append('target_value', document.getElementById('goal_target').value);
        formData.append('target_date', document.getElementById('goal_date').value);

        fetch('ajax/manage_goal.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    showToast('Goal created!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, true);
                }
            });
    });

    // Delete Goal
    document.querySelectorAll('.btn-delete-goal').forEach(btn => {
        btn.addEventListener('click', function() {
            if(!confirm('Delete this goal?')) return;
            const goalId = this.getAttribute('data-id');
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('goal_id', goalId);

            fetch('ajax/manage_goal.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.status === 'success') {
                        showToast('Goal deleted.');
                        setTimeout(() => location.reload(), 800);
                    }
                });
        });
    });

    // Focus Timer Logic
    let timerInterval;
    let timerSeconds = 25 * 60; // default 25 mins
    let initialSessionMinutes = 25;
    let isTimerRunning = false;
    let sessionAccumulatedSeconds = 0; // track actual time spent

    const timerDisplay = document.getElementById('timerDisplay');
    const btnStart = document.getElementById('btnStartTimer');
    const btnPause = document.getElementById('btnPauseTimer');
    const btnComplete = document.getElementById('btnCompleteTimer');
    const presets = document.querySelectorAll('.timer-preset');

    function updateTimerDisplay() {
        const m = Math.floor(timerSeconds / 60);
        const s = timerSeconds % 60;
        timerDisplay.innerText = (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
    }

    presets.forEach(btn => {
        btn.addEventListener('click', function() {
            if(isTimerRunning) return;
            const mins = parseInt(this.getAttribute('data-time'));
            initialSessionMinutes = mins;
            timerSeconds = mins * 60;
            sessionAccumulatedSeconds = 0;
            updateTimerDisplay();
            
            presets.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });

    function stopTimerUI() {
        clearInterval(timerInterval);
        isTimerRunning = false;
        btnStart.classList.remove('d-none');
        btnStart.innerText = 'Resume';
        btnPause.classList.add('d-none');
        btnComplete.classList.remove('d-none');
    }

    btnStart.addEventListener('click', function() {
        if(timerSeconds <= 0) return;
        isTimerRunning = true;
        this.classList.add('d-none');
        btnPause.classList.remove('d-none');
        btnComplete.classList.remove('d-none');

        timerInterval = setInterval(() => {
            timerSeconds--;
            sessionAccumulatedSeconds++;
            updateTimerDisplay();

            if(timerSeconds <= 0) {
                stopTimerUI();
                // Play alarm sound
                const alarmSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
                alarmSound.play().catch(e => console.log('Audio play failed due to browser policy:', e));
                
                showToast('Time is up! Great focus session.');
            }
        }, 1000);
    });

    btnPause.addEventListener('click', stopTimerUI);

    btnComplete.addEventListener('click', function() {
        stopTimerUI();
        const taskId = document.getElementById('timer_task_id').value;
        const minutesSpent = Math.max(1, Math.floor(sessionAccumulatedSeconds / 60));

        if(sessionAccumulatedSeconds < 60) {
            if(!confirm('Session was less than 1 minute. Save anyway?')) return;
        }

        const formData = new FormData();
        formData.append('action', 'save_session');
        formData.append('duration_minutes', minutesSpent);
        if(taskId) formData.append('task_id', taskId);

        btnComplete.innerText = 'Saving...';
        btnComplete.disabled = true;

        fetch('ajax/manage_timer.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    showToast(`Logged ${minutesSpent} minutes of study time!`);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message, true);
                    btnComplete.innerText = 'Complete Session';
                    btnComplete.disabled = false;
                }
            });
    });

    // Submit Daily Review
    document.getElementById('reviewForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('action', 'submit_review');
        formData.append('learning_note', document.getElementById('rev_learning_note').value);
        formData.append('tomorrow_priority', document.getElementById('rev_tomorrow_priority').value);
        formData.append('study_minutes', document.getElementById('rev_study_mins').value);
        formData.append('tasks_completed', document.getElementById('rev_tasks_comp').value);
        formData.append('tasks_total', document.getElementById('rev_tasks_tot').value);
        formData.append('productivity_score', document.getElementById('rev_prod_score').value);

        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerText = 'Saving...';

        fetch('ajax/manage_review.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerText = 'Complete Day & Save Streak 🔥';
                if(data.status === 'success') {
                    showToast(data.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message, true);
                }
            });
    });

});

<?php if(!$is_logged_in): ?>
// Guest Interceptor: Force login on any interaction inside the dashboard
document.querySelector('.dashboard-bg').addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    window.location.href = 'login.php?redirect=dashboard.php&msg=login_required';
}, true); // Use capture phase to ensure it triggers before anything else
<?php endif; ?>

</script>
