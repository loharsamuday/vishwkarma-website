<?php
// admin/tg_quiz/quiz_results.php
// Per-user score report for a Telegram quiz session

$page_title = "Quiz Results";
require_once '../../includes/db.php';
require_once '../../includes/session.php';
require_once '../../api/tg_quiz/tg_results.php';

if (!isset($_SESSION['admin_id'])) { header("Location: ../index.php"); exit; }

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$session_id = (int)($_GET['session_id'] ?? 0);
$admin_id   = (int)$_SESSION['admin_id'];

$sessionStmt = $pdo->prepare("
    SELECT id, title, chat_title, chat_id, status, total_questions, current_question, questions_sent,
           correct_mark, wrong_mark, start_time, end_time, next_question_at,
           results_announced_at, results_claimed_at, error_message
    FROM tg_quiz_sessions
    WHERE id = ? AND admin_id = ?
");
$sessionStmt->execute([$session_id, $admin_id]);
$session = $sessionStmt->fetch(PDO::FETCH_ASSOC);

$publishMsg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_telegram') {
    $token = $_POST['csrf_token'] ?? '';
    if (!empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $res = finalizeTelegramQuiz($pdo, $session_id, true);
        $publishMsg = $res;
        $sessionStmt->execute([$session_id, $admin_id]);
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $publishMsg = ['success' => false, 'message' => 'CSRF validation failed'];
    }
}

// Auto-finalize if quiz has finished its timer but results are not yet announced
if (
    $session
    && empty($session['results_announced_at'])
    && (int)$session['current_question'] >= (int)$session['total_questions']
    && (empty($session['next_question_at']) || strtotime($session['next_question_at']) <= time())
) {
    finalizeTelegramQuiz($pdo, $session_id);
    $sessionStmt->execute([$session_id, $admin_id]);
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
}

if (!$session) {
    http_response_code(404);
    $session = null;
    $results = [];
} else {
    // If session is recent or running, sync any latest poll votes from Telegram
    if (in_array($session['status'], ['RUNNING', 'PAUSED', 'COMPLETED'], true) && function_exists('syncSessionPollAnswers')) {
        try {
            $token = function_exists('getActiveBotToken') ? getActiveBotToken($pdo) : '';
            if (!empty($token)) {
                $botStmt = $pdo->prepare("SELECT id, last_update_id FROM tg_bots WHERE admin_id = ? AND is_active = 1 LIMIT 1");
                $botStmt->execute([$admin_id]);
                $botRow = $botStmt->fetch(PDO::FETCH_ASSOC);
                if ($botRow) {
                    syncSessionPollAnswers($pdo, $session, $botRow, $token);
                }
            }
        } catch (\Throwable $e) {}
    }

    $resultsStmt = $pdo->prepare("
        SELECT r.user_id,
               COALESCE(MAX(r.username), '') AS username,
               COALESCE(MAX(r.first_name), '') AS first_name,
               COALESCE(MAX(r.last_name), '') AS last_name,
               COUNT(*) AS answered_count,
               SUM(r.is_correct = 1) AS correct_count,
               SUM(r.is_correct = 0) AS wrong_count,
               ROUND(
                   (SUM(r.is_correct = 1) * ABS(COALESCE(s.correct_mark, 1.00))) -
                   (SUM(r.is_correct = 0) * ABS(COALESCE(s.wrong_mark, 0.25))),
                   2
               ) AS score
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
    <div class="d-flex gap-2">
      <?php if ($session && in_array($session['status'], ['RUNNING', 'PAUSED'], true)): ?>
      <a href="quiz_live.php?session_id=<?= (int)$session['id'] ?>" class="btn btn-outline-success btn-sm">
        <i class="fa-solid fa-satellite-dish me-1"></i>Live Monitor
      </a>
      <?php endif; ?>
      <a href="quiz_history.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>History</a>
    </div>
  </div>

  <?php if ($publishMsg): ?>
  <div class="alert alert-<?= !empty($publishMsg['success']) ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
    <i class="fa-solid <?= !empty($publishMsg['success']) ? 'fa-circle-check' : 'fa-triangle-exclamation' ?> me-2"></i>
    <?= htmlspecialchars($publishMsg['message'] ?? '') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <?php if (!$session): ?>
  <div class="alert alert-danger">Quiz session not found.</div>
  <?php else: ?>
  <?php
    $totalQ = max(1, (int)($session['total_questions'] ?? 0));
    $posMark = abs((float)($session['correct_mark'] ?? 1.00));
    $negMark = abs((float)($session['wrong_mark'] ?? 0.25));
    $maxPossibleMarks = round($totalQ * $posMark, 2);

    $totalParticipants = count($results);
    $topScore = $results ? null : 0;
    $sumScore = 0;
    $sumAccuracy = 0;

    $processedResults = [];
    foreach ($results as $index => $r) {
        $m = calculateCandidateQuizScore(
            (int)$r['correct_count'],
            (int)$r['wrong_count'],
            $totalQ,
            $posMark,
            $negMark
        );
        if ($topScore === null || $m['final_score'] > $topScore) {
            $topScore = $m['final_score'];
        }
        $sumScore += $m['final_score'];
        $sumAccuracy += $m['accuracy'];
        $processedResults[] = array_merge($r, $m);
    }
    $avgScore = $totalParticipants > 0 ? round($sumScore / $totalParticipants, 2) : 0;
    $avgAccuracy = $totalParticipants > 0 ? round($sumAccuracy / $totalParticipants, 1) : 0;
  ?>

  <!-- Session Header Card -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <h5 class="fw-bold mb-1"><?= htmlspecialchars($session['title'] ?? 'Telegram Quiz') ?></h5>
        <div class="text-muted"><i class="fa-solid fa-users me-1"></i><?= htmlspecialchars($session['chat_title'] ?? ('Chat ' . $session['chat_id'])) ?></div>
        <div class="small text-muted mt-1">
          <span class="badge bg-success-subtle text-success border border-success me-1">Correct: +<?= number_format($posMark, 2) ?></span>
          <span class="badge bg-danger-subtle text-danger border border-danger me-1">Wrong: -<?= number_format($negMark, 2) ?></span>
          <span class="badge bg-secondary-subtle text-secondary border me-1">Skipped: 0.00</span>
          <span class="badge bg-primary-subtle text-primary border">Total: <?= $totalQ ?> Q (Max: <?= number_format($maxPossibleMarks, 2) ?> Marks)</span>
        </div>
      </div>
      <div class="text-md-end d-flex flex-column align-items-md-end gap-2">
        <div>
          <span class="badge bg-<?= $session['status'] === 'COMPLETED' ? 'success' : ($session['status'] === 'RUNNING' ? 'primary' : 'warning') ?> fs-6">
            <?= htmlspecialchars($session['status']) ?>
          </span>
        </div>
        <div>
          <?php if (!empty($session['results_announced_at'])): ?>
            <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
              <i class="fa-solid fa-circle-check me-1"></i>Published to Telegram on <?= date('d M Y, H:i', strtotime($session['results_announced_at'])) ?>
            </span>
          <?php else: ?>
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2">
              <i class="fa-solid fa-clock me-1"></i>Results not yet published to Telegram
            </span>
          <?php endif; ?>
        </div>
        <form method="POST" class="d-inline">
          <input type="hidden" name="action" value="publish_telegram">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <button type="submit" class="btn btn-outline-primary btn-sm fw-bold shadow-sm" onclick="return confirm('Send / publish final quiz results to the Telegram group now?')">
            <i class="fa-solid fa-paper-plane me-1"></i><?= !empty($session['results_announced_at']) ? 'Re-publish to Telegram' : 'Publish to Telegram Now' ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Summary Statistics Grid -->
  <div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm bg-white p-3 text-center">
        <div class="text-muted small fw-bold">TOTAL PARTICIPANTS</div>
        <div class="fs-3 fw-bold text-dark mt-1"><?= $totalParticipants ?></div>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm bg-white p-3 text-center">
        <div class="text-muted small fw-bold">HIGHEST SCORE</div>
        <div class="fs-3 fw-bold text-success mt-1"><?= $topScore !== null ? number_format($topScore, 2) : '—' ?> <small class="fs-6 text-muted">/ <?= number_format($maxPossibleMarks, 2) ?></small></div>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm bg-white p-3 text-center">
        <div class="text-muted small fw-bold">AVERAGE SCORE</div>
        <div class="fs-3 fw-bold text-primary mt-1"><?= number_format($avgScore, 2) ?></div>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <div class="card border-0 shadow-sm bg-white p-3 text-center">
        <div class="text-muted small fw-bold">AVERAGE ACCURACY</div>
        <div class="fs-3 fw-bold text-info mt-1"><?= number_format($avgAccuracy, 1) ?>%</div>
      </div>
    </div>
  </div>

  <?php if (!empty($session['error_message'])): ?>
  <div class="alert alert-warning py-2">
    <i class="fa-solid fa-circle-exclamation me-1"></i>
    <strong>Last Error:</strong> <?= htmlspecialchars($session['error_message']) ?>
  </div>
  <?php endif; ?>

  <!-- Leaderboard Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
      <h6 class="mb-0 fw-bold"><i class="fa-solid fa-ranking-star me-2"></i>Official Scorecard & Leaderboard</h6>
      <small class="text-white-50">Formula: (Correct × <?= number_format($posMark, 2) ?>) - (Wrong × <?= number_format($negMark, 2) ?>)</small>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small text-uppercase fw-bold">
          <tr>
            <th style="width: 70px;">Rank</th>
            <th>Candidate</th>
            <th class="text-center" style="width: 100px;">Attempted</th>
            <th class="text-center" style="width: 90px;">Correct</th>
            <th class="text-center" style="width: 90px;">Wrong</th>
            <th class="text-center" style="width: 90px;">Skipped</th>
            <th class="text-center" style="width: 110px;">Accuracy</th>
            <th class="text-end" style="width: 130px;">Final Score</th>
            <th class="text-center" style="width: 100px;">Percentage</th>
            <th class="text-center" style="width: 100px;">Breakdown</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($processedResults)): ?>
          <tr><td colspan="10" class="text-center text-muted py-5">
            <?= $session['status'] === 'COMPLETED' ? 'No participant answers were recorded for this quiz.' : 'Results will appear here in real time as participants vote in Telegram.' ?>
          </td></tr>
          <?php else: ?>
          <?php
          $medals = ['🥇', '🥈', '🥉'];
          foreach ($processedResults as $rank => $r):
            $displayName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            if ($displayName === '') $displayName = !empty($r['username']) ? '@' . $r['username'] : 'Telegram user';
            $rankDisplay = $medals[$rank] ?? ('#' . ($rank + 1));
            $modalId = 'modal_user_' . (int)$r['user_id'];
          ?>
          <tr>
            <td class="fw-bold fs-5"><?= $rankDisplay ?></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($displayName) ?></div>
              <?php if (!empty($r['username']) && $displayName !== '@' . $r['username']): ?>
              <small class="text-muted">@<?= htmlspecialchars($r['username']) ?></small>
              <?php endif; ?>
              <small class="text-muted d-block font-monospace">ID: <?= htmlspecialchars((string)$r['user_id']) ?></small>
            </td>
            <td class="text-center fw-semibold"><?= $r['attempted'] ?> <small class="text-muted">/ <?= $r['total_questions'] ?></small></td>
            <td class="text-center text-success fw-bold"><i class="fa-solid fa-check me-1"></i><?= $r['correct'] ?></td>
            <td class="text-center text-danger fw-bold"><i class="fa-solid fa-xmark me-1"></i><?= $r['wrong'] ?></td>
            <td class="text-center text-secondary"><?= $r['skipped'] ?></td>
            <td class="text-center">
              <span class="badge bg-<?= $r['accuracy'] >= 75 ? 'success' : ($r['accuracy'] >= 50 ? 'primary' : 'warning text-dark') ?>">
                <?= number_format($r['accuracy'], 1) ?>%
              </span>
            </td>
            <td class="text-end fw-bold fs-5 <?= $r['final_score'] >= 0 ? 'text-dark' : 'text-danger' ?>">
              <?= number_format($r['final_score'], 2) ?>
            </td>
            <td class="text-center fw-semibold"><?= number_format($r['percentage'], 1) ?>%</td>
            <td class="text-center">
              <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>">
                <i class="fa-solid fa-receipt me-1"></i>Report
              </button>

              <!-- Detailed Score Modal -->
              <div class="modal fade text-start" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <div class="modal-header bg-light">
                      <h6 class="modal-title fw-bold">
                        <i class="fa-solid fa-user-graduate me-2 text-primary"></i>Score Report: <?= htmlspecialchars($displayName) ?>
                      </h6>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <div class="mb-3 p-3 bg-light rounded text-center border">
                        <small class="text-muted text-uppercase fw-bold d-block">Final Calculated Score</small>
                        <div class="display-6 fw-bold <?= $r['final_score'] >= 0 ? 'text-success' : 'text-danger' ?>">
                          <?= number_format($r['final_score'], 2) ?> <small class="fs-6 text-muted">/ <?= number_format($r['max_marks'], 2) ?></small>
                        </div>
                        <div class="small text-muted mt-1">Percentage: <strong><?= number_format($r['percentage'], 2) ?>%</strong> | Accuracy: <strong><?= number_format($r['accuracy'], 2) ?>%</strong></div>
                      </div>

                      <table class="table table-bordered table-sm mb-0">
                        <tbody>
                          <tr>
                            <th>Total Questions</th>
                            <td class="text-end fw-bold"><?= $r['total_questions'] ?></td>
                          </tr>
                          <tr>
                            <th>Attempted Questions</th>
                            <td class="text-end fw-bold"><?= $r['attempted'] ?></td>
                          </tr>
                          <tr class="table-success">
                            <th>Correct Answers (<?= $r['correct'] ?> × +<?= number_format($r['pos_mark'], 2) ?>)</th>
                            <td class="text-end fw-bold text-success">+<?= number_format($r['positive_marks'], 2) ?></td>
                          </tr>
                          <tr class="table-danger">
                            <th>Wrong Answers (<?= $r['wrong'] ?> × -<?= number_format($r['neg_mark'], 2) ?>)</th>
                            <td class="text-end fw-bold text-danger">-<?= number_format($r['negative_marks'], 2) ?></td>
                          </tr>
                          <tr>
                            <th>Skipped Questions (<?= $r['skipped'] ?> × 0.00)</th>
                            <td class="text-end text-muted">0.00</td>
                          </tr>
                          <tr class="table-dark fw-bold">
                            <th>Final Score: (<?= number_format($r['positive_marks'], 2) ?> - <?= number_format($r['negative_marks'], 2) ?>)</th>
                            <td class="text-end fs-6"><?= number_format($r['final_score'], 2) ?></td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div class="modal-footer py-2">
                      <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                  </div>
                </div>
              </div>
            </td>
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
<script>setTimeout(() => window.location.reload(), 4000);</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
