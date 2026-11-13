<?php
/**
 * api/save_test_progress.php
 * Real-time, reliable autosave endpoint for mock test answers and question status.
 * Prevents duplicate attempts, normalizes statuses, and responds with JSON confirmation.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Support both form-urlencoded/FormData and application/json bodies
$input_data = $_POST;
$raw_input = file_get_contents('php://input');
if (!empty($raw_input)) {
    $json = json_decode($raw_input, true);
    if (is_array($json)) {
        $input_data = array_merge($input_data, $json);
    }
}

$attempt_id = isset($input_data['attempt_id']) ? (int)$input_data['attempt_id'] : 0;
$question_id = isset($input_data['question_id']) ? (int)$input_data['question_id'] : 0;
$selected_option = isset($input_data['selected_option']) ? trim((string)$input_data['selected_option']) : null;
$raw_status = isset($input_data['status']) ? trim((string)$input_data['status']) : '';
$time_spent = isset($input_data['time_spent_seconds']) ? max(0, (int)$input_data['time_spent_seconds']) : 0;
$user_id = (int)$_SESSION['user_id'];

if ($attempt_id <= 0 || $question_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid attempt_id or question_id']);
    exit;
}

// Validate attempt ownership and that test is actively in progress
$stmt = $pdo->prepare("SELECT id, status FROM mt_test_attempts WHERE id = ? AND user_id = ?");
$stmt->execute([$attempt_id, $user_id]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Test attempt not found']);
    exit;
}

if ($attempt['status'] !== 'in_progress') {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Test attempt is already completed or expired', 'test_status' => $attempt['status']]);
    exit;
}

// Treat empty string option as null (cleared response)
if ($selected_option === '' || $selected_option === null) {
    $selected_option = null;
}

// Normalize question status:
// Standard 4 states: NOT_VISITED, VISITED_UNANSWERED, ANSWERED, SUBMITTED
// With compatibility for marked review: marked, answered_marked
if (!empty($selected_option)) {
    if (in_array(strtolower($raw_status), ['marked', 'answered_marked', 'marked_review', 'answered_marked_review'])) {
        $normalized_status = 'answered_marked';
    } else {
        $normalized_status = 'ANSWERED';
    }
} else {
    if (in_array(strtolower($raw_status), ['marked', 'marked_review'])) {
        $normalized_status = 'marked';
    } elseif (in_array(strtolower($raw_status), ['not_visited', 'unvisited'])) {
        $normalized_status = 'NOT_VISITED';
    } else {
        $normalized_status = 'VISITED_UNANSWERED';
    }
}

try {
    // 1. Atomic upsert into mt_student_responses (unique key attempt_id + question_id prevents duplicates)
    $stmt_upsert = $pdo->prepare("
        INSERT INTO mt_student_responses (
            attempt_id, question_id, selected_option, status, time_spent_seconds
        ) VALUES (
            :attempt_id, :question_id, :selected_option, :status, :time_spent
        ) ON DUPLICATE KEY UPDATE
            selected_option = VALUES(selected_option),
            status = VALUES(status),
            time_spent_seconds = time_spent_seconds + VALUES(time_spent_seconds),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt_upsert->execute([
        ':attempt_id' => $attempt_id,
        ':question_id' => $question_id,
        ':selected_option' => $selected_option,
        ':status' => $normalized_status,
        ':time_spent' => $time_spent
    ]);

    // 2. Dual-compatibility: also update mt_attempt_answers if the table exists
    try {
        $legacy_status = ($normalized_status === 'ANSWERED') ? 'answered' :
            (($normalized_status === 'answered_marked') ? 'answered_marked_review' :
            (($normalized_status === 'marked') ? 'marked_review' :
            (($normalized_status === 'NOT_VISITED') ? 'not_visited' : 'not_answered')));

        $stmt_legacy = $pdo->prepare("
            INSERT INTO mt_attempt_answers (
                attempt_id, question_id, user_answer, status, time_spent_seconds
            ) VALUES (
                :attempt_id, :question_id, :user_answer, :status, :time_spent
            ) ON DUPLICATE KEY UPDATE
                user_answer = VALUES(user_answer),
                status = VALUES(status),
                time_spent_seconds = time_spent_seconds + VALUES(time_spent_seconds)
        ");
        $stmt_legacy->execute([
            ':attempt_id' => $attempt_id,
            ':question_id' => $question_id,
            ':user_answer' => $selected_option,
            ':status' => $legacy_status,
            ':time_spent' => $time_spent
        ]);
    } catch (Exception $ex) {
        // Legacy table sync is best-effort
    }

    echo json_encode([
        'success' => true,
        'attempt_id' => $attempt_id,
        'question_id' => $question_id,
        'selected_option' => $selected_option,
        'status' => $normalized_status,
        'is_answered' => !empty($selected_option),
        'saved_at' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save progress: ' . $e->getMessage()
    ]);
}
