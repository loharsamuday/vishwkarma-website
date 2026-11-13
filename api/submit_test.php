<?php
/**
 * api/submit_test.php
 * Evaluates mock test attempts with strict deduplication and exact mathematical validation:
 * Total Questions = Correct + Wrong + Skipped
 * Attempted = Correct + Wrong
 * Final Score = (Correct * Positive Marks) - (Wrong * Negative Marks)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if (!isset($_SESSION['user_id'])) {
    if ($is_ajax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    header("Location: ../login.php");
    exit;
}

$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : (isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : 0);
$user_id = (int)$_SESSION['user_id'];

if ($attempt_id <= 0) {
    if ($is_ajax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'error' => 'Invalid attempt_id']);
        exit;
    }
    header("Location: ../mock-tests.php");
    exit;
}

// Fetch Attempt and Test configuration
$stmt = $pdo->prepare("
    SELECT a.*, t.title, t.total_marks as test_total_marks, t.total_questions as test_total_questions, t.negative_marking 
    FROM mt_test_attempts a 
    JOIN mt_mock_tests t ON a.mock_test_id = t.id 
    WHERE a.id = ? AND a.user_id = ?
");
$stmt->execute([$attempt_id, $user_id]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    if ($is_ajax) {
        http_response_code(404);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'error' => 'Test attempt not found']);
        exit;
    }
    header("Location: ../mock-tests.php");
    exit;
}

// If already submitted, redirect to results directly
if ($attempt['status'] === 'completed' || $attempt['status'] === 'submitted') {
    if ($is_ajax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'already_submitted' => true,
            'redirect' => BASE_URL . 'mt-result.php?id=' . $attempt_id
        ]);
        exit;
    }
    header("Location: ../mt-result.php?id=" . $attempt_id);
    exit;
}

// Fetch all questions mapped to this mock test
$sql_questions = "
    SELECT q.id, q.correct_option, q.marks, q.negative_marks, q.question_type 
    FROM mt_test_questions tq 
    JOIN mt_questions q ON tq.question_id = q.id 
    WHERE tq.mock_test_id = ?
    ORDER BY tq.section_name ASC, tq.display_order ASC, q.id ASC
";
$stmt_q = $pdo->prepare($sql_questions);
$stmt_q->execute([$attempt['mock_test_id']]);
$questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

$total_questions = count($questions);
if ($total_questions === 0) {
    $total_questions = (int)$attempt['test_total_questions'];
}

// Fetch all saved student responses for this attempt
$stmt_r = $pdo->prepare("SELECT question_id, selected_option, status FROM mt_student_responses WHERE attempt_id = ?");
$stmt_r->execute([$attempt_id]);
$responses_raw = $stmt_r->fetchAll(PDO::FETCH_ASSOC);

// Map by unique question_id
$responses = [];
foreach ($responses_raw as $r) {
    $responses[$r['question_id']] = $r;
}

// Evaluation counters
$score = 0.0;
$correct = 0;
$wrong = 0;
$attempted = 0;
$skipped = 0;
$positive_marks_earned = 0.0;
$negative_marks_deducted = 0.0;

$global_negative = (float)$attempt['negative_marking'];
$default_positive = ($total_questions > 0) ? ((float)$attempt['test_total_marks'] / $total_questions) : 1.0;

// Question-by-question scoring and status determination
foreach ($questions as $q) {
    $qid = $q['id'];
    $r = isset($responses[$qid]) ? $responses[$qid] : null;
    $ans = ($r && isset($r['selected_option'])) ? trim((string)$r['selected_option']) : '';
    $raw_status = $r ? $r['status'] : 'NOT_VISITED';

    $has_answer = ($ans !== '');

    // Question marks configuration
    $q_marks = (isset($q['marks']) && (float)$q['marks'] > 0) ? (float)$q['marks'] : $default_positive;
    $q_neg = (isset($q['negative_marks']) && (float)$q['negative_marks'] > 0) ? (float)$q['negative_marks'] : $global_negative;

    if ($has_answer) {
        $attempted++;

        // Normalizing correct answers for comparison (handle comma-separated multi-mcq)
        $db_correct_arr = array_filter(array_map('trim', explode(',', $q['correct_option'])));
        sort($db_correct_arr);
        $db_correct = implode(',', $db_correct_arr);

        $student_ans_arr = array_filter(array_map('trim', explode(',', $ans)));
        sort($student_ans_arr);
        $student_ans = implode(',', $student_ans_arr);

        if (strcasecmp($student_ans, $db_correct) === 0) {
            $is_correct = 1;
            $correct++;
            $score += $q_marks;
            $positive_marks_earned += $q_marks;
            $awarded = $q_marks;
        } else {
            $is_correct = 0;
            $wrong++;
            $score -= $q_neg;
            $negative_marks_deducted += $q_neg;
            $awarded = -$q_neg;
        }

        // Upsert final response state as SUBMITTED
        $stmt_up_res = $pdo->prepare("
            INSERT INTO mt_student_responses (
                attempt_id, question_id, selected_option, status, is_correct, marks_awarded
            ) VALUES (
                ?, ?, ?, 'SUBMITTED', ?, ?
            ) ON DUPLICATE KEY UPDATE
                selected_option = VALUES(selected_option),
                status = 'SUBMITTED',
                is_correct = VALUES(is_correct),
                marks_awarded = VALUES(marks_awarded)
        ");
        $stmt_up_res->execute([$attempt_id, $qid, $ans, $is_correct, $awarded]);

    } else {
        // Skipped / unanswered question
        $skipped++;
        $unans_status = in_array(strtolower($raw_status), ['not_visited', 'unvisited']) ? 'NOT_VISITED' : 'VISITED_UNANSWERED';

        $stmt_up_res = $pdo->prepare("
            INSERT INTO mt_student_responses (
                attempt_id, question_id, selected_option, status, is_correct, marks_awarded
            ) VALUES (
                ?, ?, NULL, ?, 0, 0.00
            ) ON DUPLICATE KEY UPDATE
                selected_option = NULL,
                status = VALUES(status),
                is_correct = 0,
                marks_awarded = 0.00
        ");
        $stmt_up_res->execute([$attempt_id, $qid, $unans_status]);
    }
}

// Global Validation Formula Enforcement:
// 1. Total Questions = Correct + Wrong + Skipped
// 2. Attempted = Correct + Wrong
// 3. Skipped = Total Questions - Attempted
if ($attempted !== ($correct + $wrong)) {
    error_log("Discrepancy in attempt calculation for attempt_id $attempt_id: attempted=$attempted, correct=$correct, wrong=$wrong");
    $attempted = $correct + $wrong;
}
$expected_skipped = max(0, $total_questions - $attempted);
if ($skipped !== $expected_skipped) {
    error_log("Discrepancy in skipped calculation for attempt_id $attempt_id: skipped=$skipped, expected=$expected_skipped");
    $skipped = $expected_skipped;
}

// Calculate Accuracy and Time Taken
$accuracy = ($attempted > 0) ? round(($correct / $attempted) * 100, 2) : 0.0;
$end_time = date('Y-m-d H:i:s');
$start_ts = strtotime($attempt['start_time']);
$time_taken = max(0, time() - ($start_ts ? $start_ts : time()));

// Round score to 2 decimal places
$final_score = round($score, 2);

// Update mt_test_attempts with full reporting metrics
$stmt_up_att = $pdo->prepare("
    UPDATE mt_test_attempts 
    SET end_time = ?,
        score = ?,
        correct_answers = ?,
        wrong_answers = ?,
        unattempted = ?,
        total_correct = ?,
        total_incorrect = ?,
        total_unanswered = ?,
        attempted_questions = ?,
        skipped_questions = ?,
        accuracy = ?,
        time_taken_seconds = ?,
        status = 'completed'
    WHERE id = ?
");
$stmt_up_att->execute([
    $end_time,
    $final_score,
    $correct,
    $wrong,
    $skipped,
    $correct,
    $wrong,
    $skipped,
    $attempted,
    $skipped,
    $accuracy,
    $time_taken,
    $attempt_id
]);

// Also update mt_attempt_answers for dual compatibility if exists
try {
    $pdo->prepare("
        UPDATE mt_attempt_answers a
        JOIN mt_student_responses s ON a.attempt_id = s.attempt_id AND a.question_id = s.question_id
        SET a.is_correct = s.is_correct,
            a.marks_awarded = s.marks_awarded,
            a.status = IF(s.selected_option IS NOT NULL AND s.selected_option != '', 'answered', 'not_answered')
        WHERE a.attempt_id = ?
    ")->execute([$attempt_id]);
} catch (Exception $e) {
    // Ignore legacy sync failures
}

if ($is_ajax) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => true,
        'attempt_id' => $attempt_id,
        'total_questions' => $total_questions,
        'attempted' => $attempted,
        'correct' => $correct,
        'wrong' => $wrong,
        'skipped' => $skipped,
        'score' => $final_score,
        'accuracy' => $accuracy,
        'redirect' => BASE_URL . 'mt-result.php?id=' . $attempt_id
    ]);
    exit;
}

setFlashMessage('success', 'Test submitted successfully.');
header("Location: ../mt-result.php?id=" . $attempt_id);
exit;
