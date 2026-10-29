<?php
// admin/tg_quiz/quiz_results.php
// Per-user score report for a Telegram quiz session

$page_title = "Quiz Results";
require_once '../../includes/db.php';
require_once '../../includes/session.php';

if (!isset($_SESSION['admin_id'])) { header("Location: ../index.php"); exit; }

$session_id = (int)($_GET['session_id'] ?? 0);
$sessionStmt = $pdo->prepare("
    SELECT id, title, chat_title, status, total_questions, questions_sent,
           correct_mark, wrong_mark, start_time, end_time
    FROM tg_quiz_sessions
    WHERE id = ? AND admin_id = ?
");
$sessionStmt->execute([$session_id, (int)$_SESSION['admin_id']]);
$session = $sessionStmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    http_response_code(404);
    $session = null;
    $results = [];
} else {
    $resultsStmt = $pdo->prepare("
        SELECT r.user_id, MAX(r.username) AS username, MAX(r.first_name) AS first_name,
               MAX(r.last_name) AS last_name,
               COUNT(*) AS answered_count,
               SUM(r.is_correct = 1) AS correct_count,
               SUM(r.is_correct = 0) AS wrong_count,
               SUM(CASE WHEN r.is_correct = 1 THEN s.correct_mark ELSE s.wrong_mark END) AS score
        FROM tg_quiz_responses r
        JOIN tg_quiz_sessions s ON s.id = r.session_id
        WHERE r.session_id = ?
        GROUP BY r.user_id
        ORDER BY score DESC, correct_count DESC, answered_count DESC, r.user_id ASC
    ");
    $resultsStmt->execute([$session_id]);
    $results = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once '../includes/header.php';
?>

<div class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
    <div>
      <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
      <h4 class="mb-0 d-inline"><i class="fa-solid fa-trophy text-warning me-2"></i>Quiz Results</h4>
    </div>
    <a href="quiz_history.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>History</a>
  </div>

  <?php if (!$session): ?>
  <div class="alert alert-danger">Quiz session not found.</div>
  <?php else: ?>
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between gap-3">
      <div>
        <h5 class="fw-bold mb-1"><?= htmlspecialchars($session['title'] ?? 'Telegram Quiz') ?></h5>
        <div class="text-muted"><?= htmlspecialchars($session['chat_title'] ?? '') ?></div>
      </div>
      <div class="text-md-end">
        <span class="badge bg-<?= $session['status'] === 'COMPLETED' ? 'success' : 'primary' ?>"><?= htmlspecialchars($session['status']) ?></span>
        <div class="small text-muted mt-2">
          Correct: +<?= number_format((float)$session['correct_mark'], 2) ?> |
          Wrong: <?= number_format((float)$session['wrong_mark'], 2) ?> |
          <?= (int)$session['total_questions'] ?> questions
        </div>
      </div>
    </div>
  </div>

  <div class="alert alert-info py-2">
    <i class="fa-solid fa-circle-info me-1"></i>
    <?= count($results) ?> participants listed. This report includes everyone who answered at least one question; Telegram does not provide the bot with a complete group-member roster.
  </div>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-dark">
          <tr>
            <th>Rank</th>
            <th>Telegram user</th>
            <th class="text-center">Answered</th>
            <th class="text-center">Correct</th>
            <th class="text-center">Wrong</th>
            <th class="text-end">Score</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$results): ?>
          <tr><td colspan="6" class="text-center text-muted py-5">
            <?= $session['status'] === 'COMPLETED' ? 'No responses were recorded for this quiz.' : 'Results will appear here as participants answer.' ?>
          </td></tr>
          <?php else: ?>
          <?php foreach ($results as $rank => $result): ?>
          <?php
            $displayName = trim(($result['first_name'] ?? '') . ' ' . ($result['last_name'] ?? ''));
            if ($displayName === '') $displayName = $result['username'] ? '@' . $result['username'] : 'Telegram user';
          ?>
          <tr>
            <td class="fw-bold"><?= $rank + 1 ?></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($displayName) ?></div>
              <?php if (!empty($result['username']) && $displayName !== '@' . $result['username']): ?>
              <small class="text-muted">@<?= htmlspecialchars($result['username']) ?></small>
              <?php endif; ?>
              <small class="text-muted d-block">ID: <?= htmlspecialchars((string)$result['user_id']) ?></small>
            </td>
            <td class="text-center"><?= (int)$result['answered_count'] ?></td>
            <td class="text-center text-success fw-semibold"><?= (int)$result['correct_count'] ?></td>
            <td class="text-center text-danger fw-semibold"><?= (int)$result['wrong_count'] ?></td>
            <td class="text-end fw-bold"><?= number_format((float)$result['score'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if ($session && in_array($session['status'], ['RUNNING', 'PAUSED'], true)): ?>
<script>setTimeout(() => window.location.reload(), 5000);</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
