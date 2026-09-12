<?php
// api/tg_quiz/session.php
// Quiz Session Control API — Start / Pause / Resume / Stop + Status

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function validateCsrfToken() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
        exit;
    }
}

$action   = $_GET['action'] ?? $_POST['action'] ?? '';
$admin_id = (int)$_SESSION['admin_id'];
global $pdo;

switch ($action) {

    // --------------------------------------------------------
    // POST start — create a new quiz session and mark RUNNING
    // --------------------------------------------------------
    case 'start':
        validateCsrfToken();
        $quiz_set_id    = (int)($_POST['quiz_set_id'] ?? 0);
        $chat_id        = trim($_POST['chat_id'] ?? '');
        $timer          = max(5, min(120, (int)($_POST['timer'] ?? 15)));
        $order          = ($_POST['question_order'] ?? 'original') === 'random' ? 'random' : 'original';
        $start_q        = max(1, (int)($_POST['start_question'] ?? 1));
        $end_q          = max(0, (int)($_POST['end_question'] ?? 0));
        $custom_title   = strip_tags(trim($_POST['title'] ?? ''));

        if (!$quiz_set_id || !$chat_id) {
            echo json_encode(['success' => false, 'message' => 'Quiz set and chat ID required']);
            break;
        }

        // Verify quiz set belongs to admin
        $setStmt = $pdo->prepare("SELECT * FROM tg_quiz_sets WHERE id=? AND admin_id=? AND is_active=1");
        $setStmt->execute([$quiz_set_id, $admin_id]);
        $quiz_set = $setStmt->fetch();
        if (!$quiz_set) { echo json_encode(['success'=>false,'message'=>'Quiz set not found']); break; }

        // Get questions in order
        $qStmt = $pdo->prepare("SELECT id FROM tg_quiz_questions WHERE quiz_set_id=? AND is_active=1 ORDER BY question_number ASC");
        $qStmt->execute([$quiz_set_id]);
        $all_ids = $qStmt->fetchAll(PDO::FETCH_COLUMN);

        // Apply range filter
        $start_idx = $start_q - 1;
        $end_idx   = $end_q > 0 ? min($end_q - 1, count($all_ids) - 1) : count($all_ids) - 1;
        $filtered  = array_slice($all_ids, $start_idx, $end_idx - $start_idx + 1);

        if (empty($filtered)) { echo json_encode(['success'=>false,'message'=>'No questions found in the specified range']); break; }

        if ($order === 'random') shuffle($filtered);

        // Check for already running session for this quiz set
        $runCheck = $pdo->prepare("SELECT id FROM tg_quiz_sessions WHERE quiz_set_id=? AND status IN ('RUNNING','PAUSED') LIMIT 1");
        $runCheck->execute([$quiz_set_id]);
        if ($runCheck->fetchColumn()) {
            echo json_encode(['success'=>false,'message'=>'A session for this quiz set is already running or paused. Stop it first.']);
            break;
        }

        // Verify chat
        $chatStmt = $pdo->prepare("SELECT chat_title FROM tg_chats WHERE admin_id=? AND chat_id=? AND is_verified=1");
        $chatStmt->execute([$admin_id, $chat_id]);
        $chatRow = $chatStmt->fetch();
        if (!$chatRow) { echo json_encode(['success'=>false,'message'=>'Chat not verified. Verify the chat ID first.']); break; }

        $title = $custom_title ?: $quiz_set['title'];
        $total = count($filtered);

        // next_question_at = NOW() + 3 seconds (small delay to let worker pick up)
        $next_at = date('Y-m-d H:i:s', time() + 3);

        $ins = $pdo->prepare("INSERT INTO tg_quiz_sessions (quiz_set_id, admin_id, chat_id, chat_title, title, status, total_questions, current_question, questions_sent, question_order, question_ids_json, timer_seconds, start_question, end_question, start_time, next_question_at) VALUES (?,?,?,?,?,'RUNNING',?,0,0,?,?,?,?,?,NOW(),?)");
        $ins->execute([
            $quiz_set_id, $admin_id, $chat_id, $chatRow['chat_title'], $title,
            $total, $order, json_encode(array_values($filtered)), $timer,
            $start_q, $end_q, $next_at
        ]);
        $session_id = $pdo->lastInsertId();

        logActivity("Started quiz session #{$session_id}: {$title}", 'admin', null, $admin_id);
        echo json_encode(['success'=>true, 'message'=>'Quiz session started! Worker will begin sending questions.', 'session_id'=>(int)$session_id]);
        break;

    // --------------------------------------------------------
    // POST pause — pause the quiz
    // --------------------------------------------------------
    case 'pause':
        validateCsrfToken();
        $session_id = (int)($_POST['session_id'] ?? 0);
        $check = $pdo->prepare("SELECT id FROM tg_quiz_sessions WHERE id=? AND admin_id=? AND status='RUNNING'");
        $check->execute([$session_id, $admin_id]);
        if (!$check->fetchColumn()) { echo json_encode(['success'=>false,'message'=>'Session not found or not running']); break; }

        $pdo->prepare("UPDATE tg_quiz_sessions SET status='PAUSED', updated_at=NOW() WHERE id=?")->execute([$session_id]);
        logActivity("Paused quiz session #{$session_id}", 'admin', null, $admin_id);
        echo json_encode(['success'=>true, 'message'=>'Quiz paused. No more questions will be sent.']);
        break;

    // --------------------------------------------------------
    // POST resume — resume a paused quiz
    // --------------------------------------------------------
    case 'resume':
        validateCsrfToken();
        $session_id = (int)($_POST['session_id'] ?? 0);
        $check = $pdo->prepare("SELECT id, timer_seconds FROM tg_quiz_sessions WHERE id=? AND admin_id=? AND status='PAUSED'");
        $check->execute([$session_id, $admin_id]);
        $sess = $check->fetch();
        if (!$sess) { echo json_encode(['success'=>false,'message'=>'Session not found or not paused']); break; }

        // Set next_question_at to 5 seconds from now (grace period)
        $next_at = date('Y-m-d H:i:s', time() + 5);
        $pdo->prepare("UPDATE tg_quiz_sessions SET status='RUNNING', next_question_at=?, updated_at=NOW() WHERE id=?")->execute([$next_at, $session_id]);
        logActivity("Resumed quiz session #{$session_id}", 'admin', null, $admin_id);
        echo json_encode(['success'=>true, 'message'=>'Quiz resumed!']);
        break;

    // --------------------------------------------------------
    // POST stop — permanently stop the quiz
    // --------------------------------------------------------
    case 'stop':
        validateCsrfToken();
        $session_id = (int)($_POST['session_id'] ?? 0);
        $check = $pdo->prepare("SELECT id FROM tg_quiz_sessions WHERE id=? AND admin_id=? AND status IN ('RUNNING','PAUSED')");
        $check->execute([$session_id, $admin_id]);
        if (!$check->fetchColumn()) { echo json_encode(['success'=>false,'message'=>'Session not found or already stopped']); break; }

        $pdo->prepare("UPDATE tg_quiz_sessions SET status='STOPPED', end_time=NOW(), updated_at=NOW() WHERE id=?")->execute([$session_id]);
        logActivity("Stopped quiz session #{$session_id}", 'admin', null, $admin_id);
        echo json_encode(['success'=>true, 'message'=>'Quiz stopped permanently.']);
        break;

    // --------------------------------------------------------
    // POST restart — restart a completed/stopped session
    // --------------------------------------------------------
    case 'restart':
        validateCsrfToken();
        $session_id = (int)($_POST['session_id'] ?? 0);
        $check = $pdo->prepare("SELECT * FROM tg_quiz_sessions WHERE id=? AND admin_id=?");
        $check->execute([$session_id, $admin_id]);
        $sess = $check->fetch();
        if (!$sess) { echo json_encode(['success'=>false,'message'=>'Session not found']); break; }

        $next_at = date('Y-m-d H:i:s', time() + 3);
        $pdo->prepare("UPDATE tg_quiz_sessions SET status='RUNNING', current_question=0, questions_sent=0, start_time=NOW(), end_time=NULL, next_question_at=?, error_message=NULL, retry_count=0, updated_at=NOW() WHERE id=?")->execute([$next_at, $session_id]);
        // Clear logs for this session
        $pdo->prepare("DELETE FROM tg_quiz_logs WHERE session_id=?")->execute([$session_id]);
        logActivity("Restarted quiz session #{$session_id}", 'admin', null, $admin_id);
        echo json_encode(['success'=>true, 'message'=>'Quiz restarted from beginning!']);
        break;

    // --------------------------------------------------------
    // GET status — real-time status for live dashboard
    // --------------------------------------------------------
    case 'status':
        $session_id = (int)($_GET['session_id'] ?? 0);
        if (!$session_id) {
            // Return all active sessions for this admin
            $stmt = $pdo->prepare("SELECT id, title, chat_title, chat_id, status, total_questions, current_question, questions_sent, timer_seconds, start_time, next_question_at, error_message FROM tg_quiz_sessions WHERE admin_id=? AND status IN ('RUNNING','PAUSED') ORDER BY updated_at DESC");
            $stmt->execute([$admin_id]);
            echo json_encode(['success'=>true, 'sessions'=>$stmt->fetchAll()]);
            break;
        }

        $stmt = $pdo->prepare("SELECT s.*, qs.title as quiz_set_title FROM tg_quiz_sessions s JOIN tg_quiz_sets qs ON qs.id=s.quiz_set_id WHERE s.id=? AND s.admin_id=?");
        $stmt->execute([$session_id, $admin_id]);
        $session = $stmt->fetch();
        if (!$session) { echo json_encode(['success'=>false,'message'=>'Session not found']); break; }

        // Calculate time remaining for current question
        $seconds_remaining = 0;
        if ($session['status'] === 'RUNNING' && $session['next_question_at']) {
            $next_ts = strtotime($session['next_question_at']);
            $seconds_remaining = max(0, $next_ts - time());
        }

        echo json_encode([
            'success'           => true,
            'session'           => $session,
            'seconds_remaining' => $seconds_remaining,
            'progress_pct'      => $session['total_questions'] > 0 ? round(($session['questions_sent'] / $session['total_questions']) * 100, 1) : 0,
        ]);
        break;

    // --------------------------------------------------------
    // GET list — all sessions (for history page)
    // --------------------------------------------------------
    case 'list':
        $status_filter = $_GET['status'] ?? '';
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $where = "WHERE s.admin_id=?";
        $params = [$admin_id];
        if ($status_filter) { $where .= " AND s.status=?"; $params[] = $status_filter; }

        $stmt = $pdo->prepare("SELECT s.id, s.title, s.chat_title, s.status, s.total_questions, s.questions_sent, s.timer_seconds, s.start_time, s.end_time, qs.title as quiz_set_title FROM tg_quiz_sessions s JOIN tg_quiz_sets qs ON qs.id=s.quiz_set_id {$where} ORDER BY s.created_at DESC LIMIT ? OFFSET ?");
        array_push($params, $limit, $offset);
        $stmt->execute($params);
        $sessions = $stmt->fetchAll();

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tg_quiz_sessions s {$where}");
        $countStmt->execute(array_slice($params, 0, count($params) - 2));
        $total = (int)$countStmt->fetchColumn();

        echo json_encode(['success'=>true, 'sessions'=>$sessions, 'total'=>$total, 'page'=>$page]);
        break;

    // --------------------------------------------------------
    // GET quiz_sets — for dropdown
    // --------------------------------------------------------
    case 'quiz_sets':
        $stmt = $pdo->prepare("SELECT id, title, total_questions, default_timer FROM tg_quiz_sets WHERE admin_id=? AND is_active=1 ORDER BY created_at DESC");
        $stmt->execute([$admin_id]);
        echo json_encode(['success'=>true, 'quiz_sets'=>$stmt->fetchAll()]);
        break;

    // --------------------------------------------------------
    // POST create_set — create a new quiz set
    // --------------------------------------------------------
    case 'create_set':
        validateCsrfToken();
        $title = strip_tags(trim($_POST['title'] ?? ''));
        $desc  = strip_tags(trim($_POST['description'] ?? ''));
        $timer = max(5, min(120, (int)($_POST['default_timer'] ?? 15)));
        if (empty($title)) { echo json_encode(['success'=>false,'message'=>'Quiz title required']); break; }

        $ins = $pdo->prepare("INSERT INTO tg_quiz_sets (admin_id, title, description, default_timer) VALUES (?,?,?,?)");
        $ins->execute([$admin_id, $title, $desc, $timer]);
        $new_id = $pdo->lastInsertId();
        logActivity("Created quiz set: {$title}", 'admin', null, $admin_id);
        echo json_encode(['success'=>true, 'message'=>'Quiz set created!', 'id'=>(int)$new_id]);
        break;

    // --------------------------------------------------------
    // POST update_set — update quiz set details
    // --------------------------------------------------------
    case 'update_set':
        validateCsrfToken();
        $id    = (int)($_POST['id'] ?? 0);
        $title = strip_tags(trim($_POST['title'] ?? ''));
        $desc  = strip_tags(trim($_POST['description'] ?? ''));
        $timer = max(5, min(120, (int)($_POST['default_timer'] ?? 15)));
        if (empty($title)) { echo json_encode(['success'=>false,'message'=>'Quiz title required']); break; }

        $check = $pdo->prepare("SELECT id FROM tg_quiz_sets WHERE id=? AND admin_id=?");
        $check->execute([$id, $admin_id]);
        if (!$check->fetchColumn()) { echo json_encode(['success'=>false,'message'=>'Not found']); break; }

        $pdo->prepare("UPDATE tg_quiz_sets SET title=?, description=?, default_timer=?, updated_at=NOW() WHERE id=?")->execute([$title, $desc, $timer, $id]);
        echo json_encode(['success'=>true, 'message'=>'Quiz set updated!']);
        break;

    // --------------------------------------------------------
    // POST delete_set — delete quiz set and all questions
    // --------------------------------------------------------
    case 'delete_set':
        validateCsrfToken();
        $id = (int)($_POST['id'] ?? 0);
        $check = $pdo->prepare("SELECT id FROM tg_quiz_sets WHERE id=? AND admin_id=?");
        $check->execute([$id, $admin_id]);
        if (!$check->fetchColumn()) { echo json_encode(['success'=>false,'message'=>'Not found']); break; }

        // Check for active sessions
        $active = $pdo->prepare("SELECT COUNT(*) FROM tg_quiz_sessions WHERE quiz_set_id=? AND status IN ('RUNNING','PAUSED')");
        $active->execute([$id]);
        if ($active->fetchColumn() > 0) { echo json_encode(['success'=>false,'message'=>'Cannot delete — quiz has active sessions. Stop them first.']); break; }

        $pdo->prepare("DELETE FROM tg_quiz_questions WHERE quiz_set_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM tg_quiz_sets WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true, 'message'=>'Quiz set deleted']);
        break;

    // --------------------------------------------------------
    // POST duplicate_set — clone a quiz set with all questions
    // --------------------------------------------------------
    case 'duplicate_set':
        validateCsrfToken();
        $id = (int)($_POST['id'] ?? 0);
        $check = $pdo->prepare("SELECT * FROM tg_quiz_sets WHERE id=? AND admin_id=?");
        $check->execute([$id, $admin_id]);
        $orig = $check->fetch();
        if (!$orig) { echo json_encode(['success'=>false,'message'=>'Not found']); break; }

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("INSERT INTO tg_quiz_sets (admin_id, title, description, default_timer) VALUES (?,?,?,?)");
            $ins->execute([$admin_id, $orig['title'].' (Copy)', $orig['description'], $orig['default_timer']]);
            $new_id = $pdo->lastInsertId();

            // Copy all questions
            $qStmt = $pdo->prepare("SELECT * FROM tg_quiz_questions WHERE quiz_set_id=? ORDER BY question_number");
            $qStmt->execute([$id]);
            $questions = $qStmt->fetchAll();

            $qIns = $pdo->prepare("INSERT INTO tg_quiz_questions (quiz_set_id, question_number, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES (?,?,?,?,?,?,?,?,?)");
            foreach ($questions as $q) {
                $qIns->execute([$new_id, $q['question_number'], $q['question_text'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_answer'], $q['explanation']]);
            }
            $pdo->prepare("UPDATE tg_quiz_sets SET total_questions=? WHERE id=?")->execute([count($questions), $new_id]);
            $pdo->commit();
            echo json_encode(['success'=>true, 'message'=>'Quiz set duplicated!', 'new_id'=>(int)$new_id]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success'=>false,'message'=>'Database error']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Unknown action']);
}
