<?php
$page_title = "Test Result & Analytics";
require_once 'includes/db.php';
require_once 'includes/session.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: mock-tests.php");
    exit;
}

$attempt_id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user_id'];

// Check attempt ownership or admin
$is_admin = false;
$role_stmt = $pdo->prepare("SELECT role_id FROM users WHERE id = ?");
$role_stmt->execute([$user_id]);
if ($role_stmt->fetchColumn() == 1) $is_admin = true;

// Fetch Attempt Details
$stmt = $pdo->prepare("SELECT a.*, t.title, t.total_marks, t.total_questions, t.result_visibility, e.name as exam_name 
                       FROM mt_test_attempts a
                       JOIN mt_mock_tests t ON a.mock_test_id = t.id
                       JOIN mt_exams e ON t.exam_id = e.id
                       WHERE a.id = ?");
$stmt->execute([$attempt_id]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt || ($attempt['user_id'] != $user_id && !$is_admin)) {
    die("Result not found or access denied.");
}

if ($attempt['status'] != 'completed' && $attempt['status'] != 'submitted') {
    die("Test is not completed yet.");
}

// Calculate rank if visibility allows
$rank = 0;
if ($attempt['result_visibility'] == 'immediate') {
    $rank_stmt = $pdo->prepare("SELECT COUNT(*) + 1 FROM mt_test_attempts WHERE mock_test_id = ? AND status IN ('completed', 'submitted') AND score > ?");
    $rank_stmt->execute([$attempt['mock_test_id'], $attempt['score']]);
    $rank = $rank_stmt->fetchColumn();
}

// Fetch all questions and responses
$sql = "SELECT q.*, r.selected_option, r.is_correct, r.status as q_status 
        FROM mt_test_questions tq 
        JOIN mt_questions q ON tq.question_id = q.id 
        LEFT JOIN mt_student_responses r ON q.id = r.question_id AND r.attempt_id = ?
        WHERE tq.mock_test_id = ? 
        ORDER BY tq.section_name ASC, tq.display_order ASC, q.id ASC";
$stmt_qr = $pdo->prepare($sql);
$stmt_qr->execute([$attempt_id, $attempt['mock_test_id']]);
$q_data = $stmt_qr->fetchAll(PDO::FETCH_ASSOC);

$total_q = count($q_data);
if ($total_q === 0) {
    $total_q = (int)$attempt['total_questions'];
}

// Derive counts accurately with fallback support
$correct_count = isset($attempt['total_correct']) ? (int)$attempt['total_correct'] : (int)$attempt['correct_answers'];
$wrong_count = isset($attempt['total_incorrect']) ? (int)$attempt['total_incorrect'] : (int)$attempt['wrong_answers'];
$attempted_count = $correct_count + $wrong_count;
$skipped_count = max(0, $total_q - $attempted_count);

?>
<?php require_once 'includes/header.php'; ?>
<?php require_once 'includes/navbar.php'; ?>

<style>
    .result-card { border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border: none; }
    .stat-box { padding: 20px; border-radius: 12px; text-align: center; color: #fff; }
    .stat-box h3 { font-size: 2.5rem; font-weight: bold; margin-bottom: 5px; }
    .bg-score { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); }
    .bg-rank { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
    .bg-accuracy { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    
    .sol-card { border-radius: 10px; border: 1px solid #eee; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
    .sol-header { background: #f8f9fa; padding: 12px 18px; border-bottom: 1px solid #eee; font-weight: bold; display: flex; justify-content: space-between; align-items: center; border-radius: 10px 10px 0 0;}
    .sol-body { padding: 20px; }
    .opt-correct { background: #d1e7dd !important; border-color: #badbcc !important; color: #0f5132 !important; }
    .opt-wrong { background: #f8d7da !important; border-color: #f5c2c7 !important; color: #842029 !important; }
    .sol-explanation { background: #e9ecef; padding: 15px; border-left: 4px solid #0d6efd; border-radius: 4px; margin-top: 15px; }
    .sol-trick { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; border-radius: 4px; margin-top: 15px; }
</style>

<div class="container py-5">
    <?php displayFlashMessage(); ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Result: <?= htmlspecialchars($attempt['title']) ?></h2>
            <p class="text-muted mb-0"><i class="fa-solid fa-graduation-cap me-1"></i> Exam: <?= htmlspecialchars($attempt['exam_name']) ?></p>
        </div>
        <a href="mock-tests.php" class="btn btn-outline-dark"><i class="fa-solid fa-arrow-left me-1"></i> Back to Tests</a>
    </div>

    <?php if($attempt['result_visibility'] != 'immediate' && !$is_admin): ?>
        <div class="alert alert-warning text-center py-5">
            <i class="fa-solid fa-lock fs-1 text-muted mb-3"></i>
            <h4 class="fw-bold">Results are currently hidden.</h4>
            <p class="mb-0">The administrator will release the results manually at a later date. Please check your dashboard later.</p>
        </div>
    <?php else: ?>
        <!-- Analytics Overview -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-box bg-score">
                    <h3><?= (float)$attempt['score'] ?> <span class="fs-5 text-white-50">/ <?= (float)$attempt['total_marks'] ?></span></h3>
                    <div class="text-uppercase fw-bold ls-1 small">Total Score</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box bg-rank">
                    <h3>#<?= $rank ?></h3>
                    <div class="text-uppercase fw-bold ls-1 small">Estimated Rank</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box bg-accuracy">
                    <h3><?= number_format($attempt['accuracy'], 1) ?>%</h3>
                    <div class="text-uppercase fw-bold ls-1 small">Accuracy</div>
                </div>
            </div>
        </div>

        <!-- Detailed Question Stats -->
        <div class="card result-card mb-4">
            <div class="card-body p-0">
                <div class="row g-0 text-center">
                    <div class="col-6 col-md-2 p-3 border-end border-bottom">
                        <i class="fa-solid fa-list-ol text-primary fs-3 mb-1"></i>
                        <h4 class="fw-bold mb-0"><?= $total_q ?></h4>
                        <span class="text-muted small">Total Questions</span>
                    </div>
                    <div class="col-6 col-md-2 p-3 border-end border-bottom">
                        <i class="fa-solid fa-pencil text-info fs-3 mb-1"></i>
                        <h4 class="fw-bold mb-0"><?= $attempted_count ?></h4>
                        <span class="text-muted small">Attempted</span>
                    </div>
                    <div class="col-6 col-md-2 p-3 border-end border-bottom">
                        <i class="fa-solid fa-check-circle text-success fs-3 mb-1"></i>
                        <h4 class="fw-bold mb-0"><?= $correct_count ?></h4>
                        <span class="text-muted small">Correct Answers</span>
                    </div>
                    <div class="col-6 col-md-2 p-3 border-end border-bottom">
                        <i class="fa-solid fa-times-circle text-danger fs-3 mb-1"></i>
                        <h4 class="fw-bold mb-0"><?= $wrong_count ?></h4>
                        <span class="text-muted small">Wrong Answers</span>
                    </div>
                    <div class="col-6 col-md-2 p-3 border-end border-bottom">
                        <i class="fa-solid fa-forward text-warning fs-3 mb-1"></i>
                        <h4 class="fw-bold mb-0"><?= $skipped_count ?></h4>
                        <span class="text-muted small">Skipped Questions</span>
                    </div>
                    <div class="col-6 col-md-2 p-3">
                        <i class="fa-solid fa-stopwatch text-secondary fs-3 mb-1"></i>
                        <h4 class="fw-bold mb-0">
                            <?php 
                                $s_time = strtotime($attempt['start_time']);
                                $e_time = strtotime($attempt['end_time']);
                                $diff = max(0, $e_time - $s_time);
                                echo floor($diff / 60) . "m " . ($diff % 60) . "s";
                            ?>
                        </h4>
                        <span class="text-muted small">Time Taken</span>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light text-center py-2 text-muted small">
                <i class="fa-solid fa-check-double text-success me-1"></i>
                <strong>Audit Validation:</strong> Total Questions (<?= $total_q ?>) = Correct (<?= $correct_count ?>) + Wrong (<?= $wrong_count ?>) + Skipped (<?= $skipped_count ?>)
            </div>
        </div>

        <h3 class="fw-bold mb-4"><i class="fa-solid fa-file-lines me-2 text-primary"></i> Solutions & Explanations</h3>
        
        <?php foreach($q_data as $idx => $q): 
            $has_ans = !empty($q['selected_option']);
            if ($has_ans) {
                if ($q['is_correct']) {
                    $status_icon = '<span class="badge bg-success px-3 py-2"><i class="fa-solid fa-check me-1"></i> Correct (+' . (float)$q['marks'] . ')</span>';
                } else {
                    $neg = (float)$q['negative_marks'] > 0 ? (float)$q['negative_marks'] : (float)$attempt['negative_marking'];
                    $status_icon = '<span class="badge bg-danger px-3 py-2"><i class="fa-solid fa-times me-1"></i> Incorrect (-' . (float)$neg . ')</span>';
                }
            } else {
                $status_icon = '<span class="badge bg-secondary px-3 py-2"><i class="fa-solid fa-forward me-1"></i> Skipped / Not Attempted (0)</span>';
            }
            
            $db_correct_arr = array_filter(array_map('trim', explode(',', $q['correct_option'])));
            $student_ans_arr = $has_ans ? array_filter(array_map('trim', explode(',', $q['selected_option']))) : [];
        ?>
            <div class="sol-card bg-white">
                <div class="sol-header">
                    <span>Question <?= $idx + 1 ?> of <?= $total_q ?> &bull; <span class="fw-normal text-muted"><?= htmlspecialchars($q['question_type'] == 'multi_mcq' ? 'Multiple Correct' : 'Single Correct') ?></span></span>
                    <span><?= $status_icon ?></span>
                </div>
                <div class="sol-body">
                    <div class="mb-4" style="font-size: 1.15rem; line-height: 1.6;"><?= $q['question_text'] ?></div>
                    
                    <div class="row g-2 mb-3">
                        <?php 
                        $opts = ['A'=>$q['option_a'], 'B'=>$q['option_b'], 'C'=>$q['option_c'], 'D'=>$q['option_d'], 'E'=>$q['option_e']];
                        foreach($opts as $k => $v): 
                            if(empty(trim((string)$v))) continue;
                            
                            $is_correct_opt = in_array($k, $db_correct_arr);
                            $is_selected_opt = in_array($k, $student_ans_arr);
                            
                            $class = "border p-2 rounded";
                            if ($is_correct_opt) {
                                $class .= " opt-correct fw-bold";
                            } elseif ($is_selected_opt && !$is_correct_opt) {
                                $class .= " opt-wrong text-decoration-line-through";
                            }
                        ?>
                            <div class="col-12">
                                <div class="<?= $class ?>">
                                    <span class="fw-bold me-2"><?= $k ?>.</span> <?= htmlspecialchars($v) ?>
                                    <?php if($is_selected_opt): ?> <span class="badge bg-secondary ms-2">Your Answer</span> <?php endif; ?>
                                    <?php if($is_correct_opt): ?> <span class="badge bg-success ms-2"><i class="fa-solid fa-check"></i> Correct</span> <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if(!empty(trim((string)$q['explanation']))): ?>
                        <div class="sol-explanation">
                            <h6 class="fw-bold text-primary mb-2"><i class="fa-solid fa-lightbulb"></i> Explanation:</h6>
                            <?= nl2br(htmlspecialchars($q['explanation'])) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty(trim((string)$q['short_trick']))): ?>
                        <div class="sol-trick">
                            <h6 class="fw-bold text-warning-emphasis mb-2"><i class="fa-solid fa-bolt"></i> Short Trick / Tip:</h6>
                            <?= nl2br(htmlspecialchars($q['short_trick'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
