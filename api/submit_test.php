<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : (isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : 0);
$user_id = (int)$_SESSION['user_id'];

if ($attempt_id == 0) {
    header("Location: ../mock-tests.php");
    exit;
}

// Fetch Attempt
$stmt = $pdo->prepare("SELECT a.*, t.negative_marking FROM mt_test_attempts a JOIN mt_mock_tests t ON a.mock_test_id = t.id WHERE a.id = ? AND a.user_id = ? AND a.status = 'in_progress'");
$stmt->execute([$attempt_id, $user_id]);
$attempt = $stmt->fetch();

if (!$attempt) {
    // Already submitted or invalid
    header("Location: ../mt-result.php?id=" . $attempt_id);
    exit;
}

// Fetch all questions mapped to this test
$sql = "SELECT q.id, q.correct_option, q.marks, q.negative_marks 
        FROM mt_test_questions tq 
        JOIN mt_questions q ON tq.question_id = q.id 
        WHERE tq.mock_test_id = ?";
$stmt_q = $pdo->prepare($sql);
$stmt_q->execute([$attempt['mock_test_id']]);
$questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

// Fetch student responses
$stmt_r = $pdo->prepare("SELECT question_id, selected_option, status FROM mt_student_responses WHERE attempt_id = ?");
$stmt_r->execute([$attempt_id]);
$responses_raw = $stmt_r->fetchAll(PDO::FETCH_ASSOC);
$responses = [];
foreach($responses_raw as $r) {
    $responses[$r['question_id']] = $r;
}

// Evaluation
$score = 0.0;
$correct = 0;
$incorrect = 0;
$unanswered = 0;

$global_negative = (float)$attempt['negative_marking'];

foreach($questions as $q) {
    $qid = $q['id'];
    $ans = isset($responses[$qid]) ? $responses[$qid]['selected_option'] : null;
    $status = isset($responses[$qid]) ? $responses[$qid]['status'] : 'unvisited';
    
    // Only 'answered' and 'answered_marked' are considered for evaluation
    if (empty($ans) || !in_array($status, ['answered', 'answered_marked'])) {
        $unanswered++;
        
        // Update response to is_correct = 0 if it exists
        if(isset($responses[$qid])) {
            $pdo->prepare("UPDATE mt_student_responses SET is_correct = 0 WHERE attempt_id=? AND question_id=?")->execute([$attempt_id, $qid]);
        }
        continue;
    }
    
    // Check correctness
    $is_correct = 0;
    
    // For Multi MCQ, both might be comma separated and need sorting to match perfectly
    $db_correct_arr = explode(',', $q['correct_option']);
    sort($db_correct_arr);
    $db_correct = implode(',', $db_correct_arr);
    
    $student_ans_arr = explode(',', $ans);
    sort($student_ans_arr);
    $student_ans = implode(',', $student_ans_arr);
    
    if (strcasecmp($student_ans, $db_correct) == 0) {
        $is_correct = 1;
        $correct++;
        $score += (float)$q['marks'];
    } else {
        $incorrect++;
        $neg = (float)$q['negative_marks'] > 0 ? (float)$q['negative_marks'] : $global_negative;
        $score -= $neg;
    }
    
    $pdo->prepare("UPDATE mt_student_responses SET is_correct = ? WHERE attempt_id=? AND question_id=?")->execute([$is_correct, $attempt_id, $qid]);
}

$end_time = date('Y-m-d H:i:s');
$accuracy = ($correct + $incorrect > 0) ? ($correct / ($correct + $incorrect)) * 100 : 0;

$stmt_up = $pdo->prepare("UPDATE mt_test_attempts SET end_time=?, score=?, total_correct=?, total_incorrect=?, total_unanswered=?, accuracy=?, status='completed' WHERE id=?");
$stmt_up->execute([$end_time, $score, $correct, $incorrect, $unanswered, $accuracy, $attempt_id]);

setFlashMessage('success', 'Test submitted successfully.');
header("Location: ../mt-result.php?id=" . $attempt_id);
exit;
