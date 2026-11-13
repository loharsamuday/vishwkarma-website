<?php
// DO NOT include full header/footer to prevent user from navigating away easily.
// Custom minimal layout for exam interface.
require_once 'includes/db.php';
require_once 'includes/session.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['test_id'])) {
    header("Location: mock-tests.php");
    exit;
}

$test_id = (int)$_GET['test_id'];
$user_id = (int)$_SESSION['user_id'];

// Fetch Test Data
$stmt = $pdo->prepare("SELECT * FROM mt_mock_tests WHERE id = ?");
$stmt->execute([$test_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    die("Invalid Test");
}

// Fetch Attempt Data
$stmt_att = $pdo->prepare("SELECT * FROM mt_test_attempts WHERE user_id = ? AND mock_test_id = ? AND status = 'in_progress' ORDER BY id DESC LIMIT 1");
$stmt_att->execute([$user_id, $test_id]);
$attempt = $stmt_att->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    // No active attempt found, redirect back
    header("Location: mock-test-detail.php?slug=" . $test['slug']);
    exit;
}
$attempt_id = (int)$attempt['id'];

// Calculate remaining time
$start_time = strtotime($attempt['start_time']);
$duration_seconds = (int)$test['duration_minutes'] * 60;
$elapsed_seconds = time() - $start_time;
$remaining_seconds = max(0, $duration_seconds - $elapsed_seconds);

if ($remaining_seconds <= 0) {
    // Auto-submit if time is up
    header("Location: api/submit_test.php?attempt_id=" . $attempt_id);
    exit;
}

// Fetch mapped questions
$sql_q = "SELECT q.id, q.question_type, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.option_e, tq.section_name 
          FROM mt_test_questions tq
          JOIN mt_questions q ON tq.question_id = q.id
          WHERE tq.mock_test_id = ?
          ORDER BY tq.section_name ASC, tq.display_order ASC, q.id ASC";
$stmt_q = $pdo->prepare($sql_q);
$stmt_q->execute([$test_id]);
$questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

if (count($questions) == 0) {
    die("No questions found in this test.");
}

// Fetch existing responses for this attempt (preserves state on refresh / resume)
$stmt_res = $pdo->prepare("SELECT question_id, selected_option, status FROM mt_student_responses WHERE attempt_id = ?");
$stmt_res->execute([$attempt_id]);
$existing_responses = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

$response_map = [];
$status_map = [];
foreach ($existing_responses as $res) {
    $response_map[$res['question_id']] = $res['selected_option'];
    $status_map[$res['question_id']] = $res['status'];
}

// Prepare data for JS
$js_questions = [];
foreach ($questions as $idx => $q) {
    $qid = $q['id'];
    $saved_ans = isset($response_map[$qid]) ? trim((string)$response_map[$qid]) : null;
    if ($saved_ans === '') $saved_ans = null;

    $saved_status = isset($status_map[$qid]) ? trim((string)$status_map[$qid]) : 'NOT_VISITED';

    // Normalize initial status:
    // If an answer was saved, question MUST be ANSWERED (or answered_marked if marked)
    if (!empty($saved_ans)) {
        if (in_array(strtolower($saved_status), ['marked', 'answered_marked', 'marked_review', 'answered_marked_review'])) {
            $norm_status = 'answered_marked';
        } else {
            $norm_status = 'ANSWERED';
        }
    } else {
        if (in_array(strtolower($saved_status), ['marked', 'marked_review'])) {
            $norm_status = 'marked';
        } elseif (in_array(strtolower($saved_status), ['visited_unanswered', 'not_answered'])) {
            $norm_status = 'VISITED_UNANSWERED';
        } else {
            $norm_status = 'NOT_VISITED';
        }
    }

    $js_questions[] = [
        'q_num' => $idx + 1,
        'id' => $qid,
        'type' => $q['question_type'],
        'text' => $q['question_text'],
        'opts' => [
            'A' => $q['option_a'],
            'B' => $q['option_b'],
            'C' => $q['option_c'],
            'D' => $q['option_d'],
            'E' => $q['option_e']
        ],
        'section' => $q['section_name'],
        'ans' => $saved_ans,
        'status' => $norm_status
    ];
}
$js_questions_json = json_encode($js_questions, JSON_UNESCAPED_UNICODE);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($test['title']) ?> - Exam Interface</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; user-select: none; }
        .exam-header { background: #1e3c72; color: #fff; padding: 10px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .timer-box { font-family: monospace; font-size: 1.5rem; font-weight: bold; background: rgba(0,0,0,0.2); padding: 5px 15px; border-radius: 5px; }
        .timer-warning { color: #ff4d4d; animation: blink 1s infinite; }
        @keyframes blink { 50% { opacity: 0.5; } }
        
        .main-container { display: flex; height: calc(100vh - 60px); }
        .question-area { flex: 1; padding: 20px; overflow-y: auto; background: #fff; display: flex; flex-direction: column; }
        .palette-area { width: 340px; background: #f8f9fa; border-left: 1px solid #dee2e6; display: flex; flex-direction: column; }
        
        .question-text { font-size: 1.15rem; line-height: 1.6; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 20px; color: #212529; }
        .option-label { display: flex; align-items: flex-start; padding: 12px 15px; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 12px; cursor: pointer; transition: all 0.15s ease-in-out; background-color: #fff; }
        .option-label:hover { background: #f8f9fa; border-color: #adb5bd; }
        .option-input { margin-top: 4px; margin-right: 15px; transform: scale(1.2); }
        .option-label.selected { border-color: #0d6efd; background-color: #e9f2ff; font-weight: 500; }
        
        .palette-header { padding: 15px; border-bottom: 1px solid #dee2e6; background: #fff; }
        .palette-grid { padding: 15px; overflow-y: auto; flex: 1; display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; align-content: start; }
        
        /* Palette Button States (Standard 4 statuses + Review) */
        .q-btn { width: 42px; height: 42px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative; transition: all 0.1s; font-size: 0.95rem; }
        .q-btn:hover { transform: scale(1.06); }
        
        .st-NOT_VISITED, .st-unvisited { background: #e9ecef; color: #495057; border: 1px solid #ced4da; } /* Grey */
        .st-VISITED_UNANSWERED, .st-not_answered { background: #dc3545; color: #fff; border: 1px solid #b02a37; clip-path: polygon(0 0, 100% 0, 100% 80%, 80% 100%, 0 100%); } /* Red */
        .st-ANSWERED, .st-answered { background: #198754; color: #fff; border: 1px solid #146c43; clip-path: polygon(0 0, 100% 0, 100% 80%, 80% 100%, 0 100%); } /* Green */
        .st-SUBMITTED, .st-submitted { background: #0d6efd; color: #fff; border: 1px solid #0a58ca; }
        .st-marked { background: #6f42c1; color: #fff; border: 1px solid #59339d; border-radius: 50%; } /* Purple Circle */
        .st-answered_marked { background: #6f42c1; color: #fff; border: 1px solid #59339d; border-radius: 50%; position: relative; } /* Purple Circle + Green Dot */
        .st-answered_marked::after { content: ''; position: absolute; bottom: -2px; right: -2px; width: 12px; height: 12px; background: #198754; border-radius: 50%; border: 2px solid #fff; }
        
        .q-btn.active-q { border: 2.5px solid #000 !important; box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.4); }
        
        .action-bar { margin-top: auto; padding: 20px 0 0 0; border-top: 1px solid #eee; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        
        /* Legends */
        .legend-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 12px 15px; font-size: 0.78rem; background: #fff; border-top: 1px solid #dee2e6; }
        .legend-item { display: flex; align-items: center; gap: 8px; }
        .l-box { width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; border-radius: 4px; }

        /* Sync Status Badge */
        #syncIndicator { font-size: 0.85rem; padding: 4px 10px; border-radius: 20px; transition: all 0.3s; }
        .sync-saved { background: #d1e7dd; color: #0f5132; }
        .sync-saving { background: #fff3cd; color: #664d03; }
        .sync-retry { background: #f8d7da; color: #842029; }
        
        /* Mobile handling */
        @media (max-width: 991px) {
            .main-container { flex-direction: column; height: auto; }
            .palette-area { width: 100%; border-left: none; border-top: 1px solid #dee2e6; height: 320px; }
        }
    </style>
</head>
<body oncontextmenu="return false;"> <!-- Disable right click -->

<div class="exam-header d-flex justify-content-between align-items-center">
    <div class="fw-bold fs-5 text-truncate" style="max-width: 50%;">
        <?= htmlspecialchars($test['title']) ?>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span id="syncIndicator" class="sync-saved"><i class="fa-solid fa-cloud-check me-1"></i> Saved</span>
        <div class="d-flex align-items-center">
            <span class="d-none d-md-inline me-2 text-white-50">Time Left:</span>
            <span class="timer-box" id="timerDisplay">--:--:--</span>
        </div>
    </div>
</div>

<div class="main-container">
    
    <!-- Left: Question Area -->
    <div class="question-area">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <h4 class="fw-bold text-primary mb-0" id="qNumberDisplay">Question 1</h4>
                <small class="text-muted" id="qSectionDisplay"></small>
            </div>
            <div>
                <span class="badge bg-secondary me-2">Marks: +<?= (float)$test['total_marks'] / $test['total_questions'] ?> / -<?= (float)$test['negative_marking'] ?></span>
                <span class="badge bg-info text-dark" id="qTypeDisplay">Single MCQ</span>
            </div>
        </div>
        
        <div class="question-text" id="qTextDisplay">
            <!-- Question text goes here -->
        </div>
        
        <div class="options-container" id="optionsContainer">
            <!-- Options go here -->
        </div>
        
        <div class="action-bar mt-auto">
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" onclick="movePrevious()" id="prevBtn"><i class="fa-solid fa-arrow-left me-1"></i> Previous</button>
                <button class="btn btn-outline-warning text-dark fw-bold" onclick="markForReview()"><i class="fa-solid fa-bookmark me-1"></i> Mark Review & Next</button>
                <button class="btn btn-outline-danger fw-bold" onclick="clearResponse()"><i class="fa-solid fa-eraser me-1"></i> Clear Response</button>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary fw-bold px-4" onclick="saveAndNext()">Save & Next <i class="fa-solid fa-arrow-right ms-2"></i></button>
            </div>
        </div>
    </div>
    
    <!-- Right: Palette Area -->
    <div class="palette-area">
        <div class="palette-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-grip me-2"></i> Question Palette</h6>
            <span class="badge bg-light text-dark border"><?= count($questions) ?> Questions</span>
        </div>
        
        <div class="palette-grid" id="paletteGrid">
            <!-- Buttons generated by JS -->
        </div>
        
        <div class="legend-grid">
            <div class="legend-item"><span class="l-box st-ANSWERED text-white small">A</span> Answered</div>
            <div class="legend-item"><span class="l-box st-VISITED_UNANSWERED text-white small">U</span> Not Answered</div>
            <div class="legend-item"><span class="l-box st-NOT_VISITED text-dark small border">N</span> Not Visited</div>
            <div class="legend-item"><span class="l-box st-marked text-white small">M</span> Marked</div>
            <div class="legend-item" style="grid-column: span 2;"><span class="l-box st-answered_marked text-white small">AM</span> Answered & Marked Review</div>
        </div>
        
        <div class="p-3 bg-white border-top">
            <form action="api/submit_test.php" method="POST" id="submitForm">
                <input type="hidden" name="attempt_id" value="<?= $attempt_id ?>">
                <button type="button" class="btn btn-success w-100 fw-bold fs-5 py-2 shadow-sm" onclick="confirmSubmit()">
                    <i class="fa-solid fa-paper-plane me-2"></i> Submit Test
                </button>
            </form>
        </div>
    </div>
    
</div>

<!-- Submit Confirmation Modal -->
<div class="modal fade" id="submitModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-clipboard-check me-2"></i> Confirm Test Submission</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-4">
        <h5 class="mb-3">Are you sure you want to finish and submit your test?</h5>
        <div class="row g-2 mb-3">
            <div class="col-6"><div class="p-3 bg-light rounded"><h3 class="text-success fw-bold mb-0" id="summaryAns">0</h3><small class="text-muted">Attempted</small></div></div>
            <div class="col-6"><div class="p-3 bg-light rounded"><h3 class="text-danger fw-bold mb-0" id="summaryNotAns">0</h3><small class="text-muted">Skipped / Unanswered</small></div></div>
            <div class="col-6"><div class="p-3 bg-light rounded"><h3 class="text-secondary fw-bold mb-0" id="summaryUnv">0</h3><small class="text-muted">Not Visited</small></div></div>
            <div class="col-6"><div class="p-3 bg-light rounded"><h3 class="text-purple fw-bold mb-0" id="summaryMark">0</h3><small class="text-muted">Marked for Review</small></div></div>
        </div>
        <div class="alert alert-info py-2 small mb-0">
            <strong>Formula:</strong> Total Questions (<span id="summaryTotal">0</span>) = Attempted (<span id="summaryAns2">0</span>) + Skipped (<span id="summarySkip2">0</span>)
        </div>
      </div>
      <div class="modal-footer justify-content-center border-0 pb-4">
        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Continue Test</button>
        <button type="button" class="btn btn-success px-5 fw-bold" id="finalSubmitBtn" onclick="finalizeSubmission()">
            Yes, Final Submit
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const questions = <?= $js_questions_json ?>;
    const attemptId = <?= $attempt_id ?>;
    const totalDurationSeconds = <?= $remaining_seconds ?>;
    let timeRemaining = totalDurationSeconds;
    let currentIdx = 0;

    // Sync Queue State
    let syncQueue = [];
    let isSyncing = false;
    let pendingRequest = null;

    // Prevent accidentally leaving the page
    window.onbeforeunload = function() {
        return "You have an active test. Are you sure you want to leave?";
    };

    // Timer Logic
    const timerDisplay = document.getElementById('timerDisplay');
    const timerInterval = setInterval(updateTimer, 1000);
    
    function updateTimer() {
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            window.onbeforeunload = null;
            finalizeSubmission();
            return;
        }
        
        let h = Math.floor(timeRemaining / 3600);
        let m = Math.floor((timeRemaining % 3600) / 60);
        let s = timeRemaining % 60;
        
        let formatted = 
            (h > 0 ? (h < 10 ? "0" + h : h) + ":" : "") + 
            (m < 10 ? "0" + m : m) + ":" + 
            (s < 10 ? "0" + s : s);
            
        timerDisplay.innerText = formatted;
        
        if (timeRemaining <= 300) { // last 5 mins
            timerDisplay.classList.add('timer-warning');
        }
        timeRemaining--;
    }

    // Sync Status Indicator Helper
    function updateSyncIndicator(state) {
        const el = document.getElementById('syncIndicator');
        if (!el) return;
        if (state === 'saved') {
            el.className = 'sync-saved';
            el.innerHTML = '<i class="fa-solid fa-cloud-check me-1"></i> Saved';
        } else if (state === 'saving') {
            el.className = 'sync-saving';
            el.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';
        } else if (state === 'retrying') {
            el.className = 'sync-retry';
            el.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Retrying...';
        }
    }

    // Reliable AJAX Autosave Queue with Deduplication & Auto-Retry
    function enqueueSync(questionId, selectedOption, status) {
        const item = {
            question_id: questionId,
            selected_option: selectedOption !== null && selectedOption !== undefined ? selectedOption : '',
            status: status,
            retries: 0
        };

        // Deduplicate in queue: replace any pending update for the same question
        const existingIdx = syncQueue.findIndex(q => q.question_id === questionId);
        if (existingIdx >= 0) {
            syncQueue[existingIdx] = item;
        } else {
            syncQueue.push(item);
        }

        updateSyncIndicator('saving');
        processSyncQueue();
    }

    async function processSyncQueue() {
        if (isSyncing || syncQueue.length === 0) return;
        isSyncing = true;

        const currentItem = syncQueue[0];
        const formData = new URLSearchParams();
        formData.append('attempt_id', attemptId);
        formData.append('question_id', currentItem.question_id);
        formData.append('selected_option', currentItem.selected_option);
        formData.append('status', currentItem.status);

        try {
            const response = await fetch('api/save_test_progress.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    syncQueue.shift(); // Remove acknowledged item
                    if (syncQueue.length === 0) {
                        updateSyncIndicator('saved');
                    }
                } else {
                    throw new Error(data.error || 'Server rejected save');
                }
            } else {
                throw new Error('HTTP ' + response.status);
            }
        } catch (err) {
            console.warn('Answer autosave error, retrying:', err);
            updateSyncIndicator('retrying');
            currentItem.retries = (currentItem.retries || 0) + 1;
            await new Promise(r => setTimeout(r, 1200));
        } finally {
            isSyncing = false;
            if (syncQueue.length > 0) {
                processSyncQueue();
            }
        }
    }

    // UI Rendering
    function renderPalette() {
        const grid = document.getElementById('paletteGrid');
        grid.innerHTML = '';
        
        questions.forEach((q, idx) => {
            let btn = document.createElement('button');
            btn.className = `q-btn st-${q.status}`;
            if (idx === currentIdx) btn.classList.add('active-q');
            btn.innerText = q.q_num;
            btn.onclick = () => loadQuestion(idx);
            grid.appendChild(btn);
        });
    }

    function loadQuestion(idx) {
        if (idx < 0 || idx >= questions.length) return;

        // Current question status transition on navigating away:
        // If it was NOT_VISITED and has no answer, it now becomes VISITED_UNANSWERED
        const prevQ = questions[currentIdx];
        if (prevQ && prevQ.status === 'NOT_VISITED' && (!prevQ.ans || prevQ.ans === '')) {
            prevQ.status = 'VISITED_UNANSWERED';
            enqueueSync(prevQ.id, '', 'VISITED_UNANSWERED');
        }

        currentIdx = idx;
        let q = questions[currentIdx];
        
        // If the newly loaded question has never been visited and has no answer, mark as visited
        if (q.status === 'NOT_VISITED' && (!q.ans || q.ans === '')) {
            q.status = 'VISITED_UNANSWERED';
            // Save visited state in background
            enqueueSync(q.id, '', 'VISITED_UNANSWERED');
        }
        
        document.getElementById('qNumberDisplay').innerText = `Question ${q.q_num} of ${questions.length}`;
        document.getElementById('qSectionDisplay').innerText = q.section ? `Section: ${q.section}` : '';
        document.getElementById('qTypeDisplay').innerText = (q.type === 'multi_mcq') ? 'Multiple Correct' : 'Single Correct';
        document.getElementById('qTextDisplay').innerHTML = q.text;
        
        // Update Previous Button State
        document.getElementById('prevBtn').disabled = (currentIdx === 0);

        const optsContainer = document.getElementById('optionsContainer');
        optsContainer.innerHTML = '';
        
        let selectedAnswers = q.ans ? q.ans.split(',') : [];
        
        const labels = ['A', 'B', 'C', 'D', 'E'];
        labels.forEach(k => {
            if (q.opts[k] && q.opts[k].trim() !== '') {
                let lbl = document.createElement('label');
                lbl.className = `option-label ${selectedAnswers.includes(k) ? 'selected' : ''}`;
                
                let inp = document.createElement('input');
                inp.className = 'option-input';
                inp.type = (q.type === 'multi_mcq') ? 'checkbox' : 'radio';
                inp.name = `opt_q${q.id}`;
                inp.value = k;
                if (selectedAnswers.includes(k)) inp.checked = true;
                
                inp.onchange = function() {
                    handleSelectionChange(k, inp.checked);
                };
                
                let txt = document.createElement('div');
                txt.innerHTML = `<strong>${k}.</strong> ${q.opts[k]}`;
                
                lbl.appendChild(inp);
                lbl.appendChild(txt);
                optsContainer.appendChild(lbl);
            }
        });
        
        renderPalette();
        
        // Scroll active button into view
        const activeBtn = document.querySelector('.active-q');
        if (activeBtn) {
            activeBtn.scrollIntoView({ behavior: "smooth", block: "center" });
        }
    }
    
    // Autosave immediately upon clicking an answer option
    function handleSelectionChange(val, isChecked) {
        let q = questions[currentIdx];
        if (q.type === 'multi_mcq') {
            let currentAns = q.ans ? q.ans.split(',') : [];
            if (isChecked) {
                if (!currentAns.includes(val)) currentAns.push(val);
            } else {
                currentAns = currentAns.filter(item => item !== val);
            }
            currentAns.sort();
            q.ans = currentAns.length > 0 ? currentAns.join(',') : null;
        } else {
            q.ans = val;
        }
        
        // Update styling of options
        const inputs = document.querySelectorAll('.option-input');
        inputs.forEach(inp => {
            inp.parentElement.classList.toggle('selected', inp.checked);
        });

        // Set status accurately:
        if (q.ans && q.ans !== '') {
            q.status = (q.status === 'marked' || q.status === 'answered_marked') ? 'answered_marked' : 'ANSWERED';
        } else {
            q.status = (q.status === 'marked' || q.status === 'answered_marked') ? 'marked' : 'VISITED_UNANSWERED';
        }

        // Real-time palette update & immediate background autosave
        renderPalette();
        enqueueSync(q.id, q.ans, q.status);
    }

    function saveAndNext() {
        let q = questions[currentIdx];
        if (q.ans && q.ans !== '') {
            q.status = 'ANSWERED';
        } else {
            q.status = 'VISITED_UNANSWERED';
        }
        enqueueSync(q.id, q.ans, q.status);
        moveToNext();
    }

    function markForReview() {
        let q = questions[currentIdx];
        if (q.ans && q.ans !== '') {
            q.status = 'answered_marked';
        } else {
            q.status = 'marked';
        }
        enqueueSync(q.id, q.ans, q.status);
        moveToNext();
    }

    function clearResponse() {
        let q = questions[currentIdx];
        q.ans = null;
        q.status = (q.status === 'answered_marked' || q.status === 'marked') ? 'marked' : 'VISITED_UNANSWERED';
        
        const inputs = document.querySelectorAll('.option-input');
        inputs.forEach(inp => {
            inp.checked = false;
            inp.parentElement.classList.remove('selected');
        });

        renderPalette();
        enqueueSync(q.id, null, q.status);
    }

    function movePrevious() {
        if (currentIdx > 0) {
            loadQuestion(currentIdx - 1);
        }
    }

    function moveToNext() {
        if (currentIdx < questions.length - 1) {
            loadQuestion(currentIdx + 1);
        } else {
            renderPalette();
        }
    }

    // Submit Modal Logic
    function confirmSubmit() {
        let ans = 0, notAns = 0, unv = 0, mark = 0;
        questions.forEach(q => {
            const hasAns = (q.ans !== null && q.ans !== undefined && q.ans !== '');
            if (hasAns) {
                ans++;
            } else if (q.status === 'VISITED_UNANSWERED' || q.status === 'not_answered') {
                notAns++;
            } else if (q.status === 'NOT_VISITED' || q.status === 'unvisited') {
                unv++;
            }
            if (q.status === 'marked' || q.status === 'answered_marked') {
                mark++;
            }
        });

        const total = questions.length;
        const skipped = total - ans;
        
        document.getElementById('summaryAns').innerText = ans;
        document.getElementById('summaryNotAns').innerText = notAns;
        document.getElementById('summaryUnv').innerText = unv;
        document.getElementById('summaryMark').innerText = mark;
        document.getElementById('summaryTotal').innerText = total;
        document.getElementById('summaryAns2').innerText = ans;
        document.getElementById('summarySkip2').innerText = skipped;
        
        new bootstrap.Modal(document.getElementById('submitModal')).show();
    }

    // Finalize submission ensuring all pending syncs are completed first
    async function finalizeSubmission() {
        const btn = document.getElementById('finalSubmitBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Finalizing submission...';
        }

        // Wait up to 5 seconds for any pending network sync to finish
        let waitAttempts = 0;
        while (syncQueue.length > 0 && waitAttempts < 15) {
            await new Promise(r => setTimeout(r, 300));
            waitAttempts++;
        }

        window.onbeforeunload = null;
        document.getElementById('submitForm').submit();
    }

    // Disable cheat shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
            // Allow standard reload if user confirms beforeunload
        }
    });

    // Initialize first question
    loadQuestion(0);
</script>

</body>
</html>
