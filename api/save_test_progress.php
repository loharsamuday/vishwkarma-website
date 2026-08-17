<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $attempt_id = (int)$_POST['attempt_id'];
    $question_id = (int)$_POST['question_id'];
    $selected_option = isset($_POST['selected_option']) ? $_POST['selected_option'] : null;
    $status = $_POST['status']; // answered, marked, answered_marked, not_answered
    $user_id = (int)$_SESSION['user_id'];

    // Validate attempt ownership
    $stmt = $pdo->prepare("SELECT id FROM mt_test_attempts WHERE id = ? AND user_id = ? AND status = 'in_progress'");
    $stmt->execute([$attempt_id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        die("Invalid attempt or test already submitted.");
    }

    if (empty($selected_option)) {
        $selected_option = null;
    }

    // Insert or update response
    $stmt = $pdo->prepare("SELECT id FROM mt_student_responses WHERE attempt_id = ? AND question_id = ?");
    $stmt->execute([$attempt_id, $question_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $up = $pdo->prepare("UPDATE mt_student_responses SET selected_option = ?, status = ? WHERE id = ?");
        $up->execute([$selected_option, $status, $existing['id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO mt_student_responses (attempt_id, question_id, selected_option, status) VALUES (?, ?, ?, ?)");
        $ins->execute([$attempt_id, $question_id, $selected_option, $status]);
    }

    echo json_encode(['success' => true]);
}
