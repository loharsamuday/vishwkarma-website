<?php
// api/tg_quiz/log.php
// Per-session question log viewer (admin only)

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$session_id = (int)($_GET['session_id'] ?? 0);
$admin_id   = (int)$_SESSION['admin_id'];

if (!$session_id) {
    echo json_encode(['success' => false, 'message' => 'session_id required']);
    exit;
}

// Verify ownership
try {
    $check = $pdo->prepare("SELECT id FROM tg_quiz_sessions WHERE id=? AND admin_id=?");
    $check->execute([$session_id, $admin_id]);
    if (!$check->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Session not found']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT l.*, qq.question_text FROM tg_quiz_logs l LEFT JOIN tg_quiz_questions qq ON qq.id=l.question_id WHERE l.session_id=? ORDER BY l.question_number ASC LIMIT 200");
    $stmt->execute([$session_id]);
    $logs = $stmt->fetchAll();

    echo json_encode(['success' => true, 'logs' => $logs]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error', 'logs' => []]);
}
