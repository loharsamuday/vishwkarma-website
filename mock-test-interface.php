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
$test = $stmt->fetch();

if (!$test) {
    die("Invalid Test");
}

// Fetch Attempt Data
$stmt_att = $pdo->prepare("SELECT * FROM mt_test_attempts WHERE user_id = ? AND mock_test_id = ? AND status = 'in_progress' ORDER BY id DESC LIMIT 1");
$stmt_att->execute([$user_id, $test_id]);
$attempt = $stmt_att->fetch();

if (!$attempt) {
    // No active attempt found, redirect back
    header("Location: mock-test-detail.php?slug=" . $test['slug']);
    exit;
}
$attempt_id = $attempt['id'];

// Calculate remaining time
$start_time = strtotime($attempt['start_time']);
$duration_seconds = $test['duration_minutes'] * 60;
$elapsed_seconds = time() - $start_time;
$remaining_seconds = $duration_seconds - $elapsed_seconds;

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

if(count($questions) == 0) {
    die("No questions found in this test.");
}

// Fetch existing responses for this attempt (if resuming)
$stmt_res = $pdo->prepare("SELECT question_id, selected_option, status FROM mt_student_responses WHERE attempt_id = ?");
$stmt_res->execute([$attempt_id]);
$existing_responses = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

$response_map = [];
$status_map = [];
foreach($existing_responses as $res) {
    $response_map[$res['question_id']] = $res['selected_option'];
    $status_map[$res['question_id']] = $res['status'];
}

// Prepare data for JS
$js_questions = [];
foreach($questions as $idx => $q) {
    $qid = $q['id'];
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
        'ans' => isset($response_map[$qid]) ? $response_map[$qid] : null,
        'status' => isset($status_map[$qid]) ? $status_map[$qid] : 'unvisited' // unvisited, answered, marked, answered_marked, not_answered
    ];
}
$js_questions_json = json_encode($js_questions);

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
        .palette-area { width: 320px; background: #f8f9fa; border-left: 1px solid #dee2e6; display: flex; flex-direction: column; }
        
        .question-text { font-size: 1.1rem; line-height: 1.6; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .option-label { display: flex; align-items: flex-start; padding: 12px 15px; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 12px; cursor: pointer; transition: all 0.2s; }
        .option-label:hover { background: #f8f9fa; border-color: #adb5bd; }
        .option-input { margin-top: 4px; margin-right: 15px; transform: scale(1.2); }
        .option-label.selected { border-color: #0d6efd; background-color: #e9f2ff; }
        
        .palette-header { padding: 15px; border-bottom: 1px solid #dee2e6; background: #fff; }
        .palette-grid { padding: 15px; overflow-y: auto; flex: 1; display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; align-content: start; }
        
        /* Palette Button States */
        .q-btn { width: 40px; height: 40px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative; transition: all 0.1s; }
        .q-btn:hover { transform: scale(1.05); }
        
        .st-unvisited { background: #e9ecef; color: #495057; border: 1px solid #ced4da; } /* Grey */
        .st-not_answered { background: #dc3545; color: #fff; border: 1px solid #b02a37; clip-path: polygon(0 0, 100% 0, 100% 80%, 80% 100%, 0 100%); } /* Red */
        .st-answered { background: #198754; color: #fff; border: 1px solid #146c43; clip-path: polygon(0 0, 100% 0, 100% 80%, 80% 100%, 0 100%); } /* Green */
        .st-marked { background: #6f42c1; color: #fff; border: 1px solid #59339d; border-radius: 50%; } /* Purple Circle */
        .st-answered_marked { background: #6f42c1; color: #fff; border: 1px solid #59339d; border-radius: 50%; position: relative; } /* Purple Circle + Green Dot */
        .st-answered_marked::after { content: ''; position: absolute; bottom: -2px; right: -2px; width: 12px; height: 12px; background: #198754; border-radius: 50%; border: 2px solid #fff; }
        
        .q-btn.active-q { border: 2px solid #000; box-shadow: 0 0 0 2px rgba(0,0,0,0.2); }
        
        .action-bar { margin-top: auto; padding: 20px 0 0 0; border-top: 1px solid #eee; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        
        /* Legends */
        .legend-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 15px; font-size: 0.8rem; background: #fff; border-top: 1px solid #dee2e6; }
        .legend-item { display: flex; align-items: center; gap: 8px; }
        .l-box { width: 24px; height: 24px; display: inline-block; }
        
        /* Mobile handling */
        @media (max-width: 991px) {
            .main-container { flex-direction: column; }
            .palette-area { width: 100%; border-left: none; border-top: 1px solid #dee2e6; flex: none; height: 300px; }
        }
    </style>
</head>
<body oncontextmenu="return false;"> <!-- Disable right click -->

<div class="exam-header d-flex justify-content-between align-items-center">
    <div class="fw-bold fs-5 text-truncate" style="max-width: 60%;">
        <?= htmlspecialchars($test['title']) ?>
    </div>
    <div class="d-flex align-items-center gap-4">
        <div>
            <span class="d-none d-md-inline me-2 text-white-50">Time Left:</span>
            <span class="timer-box" id="timerDisplay">--:--:--</span>
        </div>
    </div>
</div>

<div class="main-container">
    
    <!-- Left: Question Area -->
    <div class="question-area">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <h4 class="fw-bold text-primary mb-0" id="qNumberDisplay">Question 1</h4>
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
                <button class="btn btn-outline-secondary fw-bold px-4" onclick="markForReview()">Mark for Review & Next</button>
                <button class="btn btn-outline-danger fw-bold px-4" onclick="clearResponse()">Clear Response</button>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary fw-bold px-5" onclick="saveAndNext()">Save & Next <i class="fa-solid fa-arrow-right ms-2"></i></button>
            </div>
        </div>
    </div>
    
    <!-- Right: Palette Area -->
    <div class="palette-area">
        <div class="palette-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-grip me-2"></i> Question Palette</h6>
            <div class="d-flex gap-2">
                <img src="https://placehold.co/30x30/0d6efd/white?text=<?= strtoupper(substr($_SESSION['first_name'],0,1)) ?>" class="rounded-circle border border-2 border-white shadow-sm">
            </div>
        </div>
        
        <div class="palette-grid" id="paletteGrid">
            <!-- Buttons generated by JS -->
        </div>
        
        <div class="legend-grid">
            <div class="legend-item"><span class="l-box st-answered text-center text-white lh-base small">1</span> Answered</div>
            <div class="legend-item"><span class="l-box st-not_answered text-center text-white lh-base small">2</span> Not Answered</div>
            <div class="legend-item"><span class="l-box st-unvisited text-center text-dark lh-base small border">3</span> Not Visited</div>
            <div class="legend-item"><span class="l-box st-marked text-center text-white lh-base small">4</span> Marked</div>
            <div class="legend-item" style="grid-column: span 2;"><span class="l-box st-answered_marked text-center text-white lh-base small" style="width:28px; height:28px; line-height:28px;">5</span> Answered & Marked for Review (will be considered for evaluation)</div>
        </div>
        
        <div class="p-3 bg-white border-top">
            <form action="api/submit_test.php" method="POST" id="submitForm">
                <input type="hidden" name="attempt_id" value="<?= $attempt_id ?>">
                <button type="button" class="btn btn-success w-100 fw-bold fs-5 py-2 shadow-sm" onclick="confirmSubmit()">Submit Test</button>
            </form>
        </div>
    </div>
    
</div>

<!-- Submit Confirmation Modal -->
<div class="modal fade" id="submitModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-clipboard-check me-2"></i> Confirm Submission</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-4">
        <h4 class="mb-4">Are you sure you want to submit?</h4>
        <div class="row g-3 mb-4">
            <div class="col-6"><div class="p-3 bg-light rounded"><h3 class="text-success mb-0" id="summaryAns">0</h3><small>Answered</small></div></div>
            <div class="col-6"><div class="p-3 bg-light rounded"><h3 class="text-danger mb-0" id="summaryNotAns">0</h3><small>Not Answered</small></div></div>
            <div class="col-6"><div class="p-3 bg-light rounded"><h3 class="text-secondary mb-0" id="summaryUnv">0</h3><small>Not Visited</small></div></div>
            <div class="col-6"><div class="p-3 bg-light rounded"><h3 class="text-purple mb-0" id="summaryMark">0</h3><small>Marked (incl. Answered)</small></div></div>
        </div>
        <p class="text-danger small mb-0"><i class="fa-solid fa-triangle-exclamation"></i> You cannot change your answers after submission.</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pb-4">
        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success px-5 fw-bold" onclick="document.getElementById('submitForm').submit();">Yes, Submit Test</button>
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
    
    let currentIdx = 0; // 0-indexed current question
    
    // Prevent accidentally leaving the page
    window.onbeforeunload = function() {
        return "You have an active test. Are you sure you want to leave?";
    };

    // Timer Logic
    const timerDisplay = document.getElementById('timerDisplay');
    const timerInterval = setInterval(updateTimer, 1000);
    
    function updateTimer() {
        if(timeRemaining <= 0) {
            clearInterval(timerInterval);
            window.onbeforeunload = null;
            document.getElementById('submitForm').submit();
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
        
        if(timeRemaining <= 300) { // last 5 mins
            timerDisplay.classList.add('timer-warning');
        }
        
        timeRemaining--;
    }

    // UI Rendering
    function renderPalette() {
        const grid = document.getElementById('paletteGrid');
        grid.innerHTML = '';
        
        questions.forEach((q, idx) => {
            let btn = document.createElement('button');
            btn.className = `q-btn st-${q.status}`;
            if(idx === currentIdx) btn.classList.add('active-q');
            btn.innerText = q.q_num;
            btn.onclick = () => loadQuestion(idx);
            grid.appendChild(btn);
        });
    }

    function loadQuestion(idx) {
        // If current question is unvisited, and we are moving away, mark it as not_answered
        if (questions[currentIdx].status === 'unvisited') {
            updateStatus(currentIdx, 'not_answered');
        }
        
        currentIdx = idx;
        let q = questions[currentIdx];
        
        // If the newly loaded question is unvisited, mark it as not_answered immediately because we visited it
        if (q.status === 'unvisited') {
            updateStatus(currentIdx, 'not_answered', false); // don't sync to server just for viewing yet
        }
        
        document.getElementById('qNumberDisplay').innerText = `Question ${q.q_num}`;
        document.getElementById('qTypeDisplay').innerText = (q.type === 'multi_mcq') ? 'Multiple Correct' : 'Single Correct';
        document.getElementById('qTextDisplay').innerHTML = q.text;
        
        const optsContainer = document.getElementById('optionsContainer');
        optsContainer.innerHTML = '';
        
        let selectedAnswers = q.ans ? q.ans.split(',') : [];
        
        const labels = ['A', 'B', 'C', 'D', 'E'];
        labels.forEach(k => {
            if(q.opts[k] && q.opts[k].trim() !== '') {
                let lbl = document.createElement('label');
                lbl.className = `option-label ${selectedAnswers.includes(k) ? 'selected' : ''}`;
                
                let inp = document.createElement('input');
                inp.className = 'option-input';
                inp.type = (q.type === 'multi_mcq') ? 'checkbox' : 'radio';
                inp.name = `opt_q${q.id}`;
                inp.value = k;
                if(selectedAnswers.includes(k)) inp.checked = true;
                
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
        
        // Scroll palette into view
        const activeBtn = document.querySelector('.active-q');
        if(activeBtn) {
            activeBtn.scrollIntoView({behavior: "smooth", block: "center"});
        }
    }
    
    function handleSelectionChange(val, isChecked) {
        let q = questions[currentIdx];
        if (q.type === 'multi_mcq') {
            let currentAns = q.ans ? q.ans.split(',') : [];
            if(isChecked) {
                if(!currentAns.includes(val)) currentAns.push(val);
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
    }

    function saveAndNext() {
        let q = questions[currentIdx];
        if (q.ans && q.ans !== '') {
            updateStatus(currentIdx, 'answered');
        } else {
            updateStatus(currentIdx, 'not_answered');
        }
        moveToNext();
    }

    function markForReview() {
        let q = questions[currentIdx];
        if (q.ans && q.ans !== '') {
            updateStatus(currentIdx, 'answered_marked');
        } else {
            updateStatus(currentIdx, 'marked');
        }
        moveToNext();
    }

    function clearResponse() {
        let q = questions[currentIdx];
        q.ans = null;
        updateStatus(currentIdx, 'not_answered');
        
        const inputs = document.querySelectorAll('.option-input');
        inputs.forEach(inp => {
            inp.checked = false;
            inp.parentElement.classList.remove('selected');
        });
    }

    function moveToNext() {
        if(currentIdx < questions.length - 1) {
            loadQuestion(currentIdx + 1);
        } else {
            renderPalette();
        }
    }

    function updateStatus(idx, statusStr, sync = true) {
        questions[idx].status = statusStr;
        if(sync) {
            syncResponse(questions[idx]);
        }
    }

    // AJAX Sync
    function syncResponse(q) {
        const formData = new URLSearchParams();
        formData.append('attempt_id', attemptId);
        formData.append('question_id', q.id);
        formData.append('selected_option', q.ans || '');
        formData.append('status', q.status);

        fetch('api/save_test_progress.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        }).catch(err => console.error('Sync Error:', err));
    }

    // Submit Modal Logic
    function confirmSubmit() {
        let ans = 0, notAns = 0, unv = 0, mark = 0;
        questions.forEach(q => {
            if(q.status === 'answered') ans++;
            else if(q.status === 'not_answered') notAns++;
            else if(q.status === 'unvisited') unv++;
            else if(q.status === 'marked' || q.status === 'answered_marked') {
                mark++;
                if(q.status === 'answered_marked') ans++; // Count as answered for logic summary
            }
        });
        
        document.getElementById('summaryAns').innerText = ans;
        document.getElementById('summaryNotAns').innerText = notAns;
        document.getElementById('summaryUnv').innerText = unv;
        document.getElementById('summaryMark').innerText = mark;
        
        new bootstrap.Modal(document.getElementById('submitModal')).show();
    }

    // Disable copy/paste/keys to prevent cheating
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F5' || (e.ctrlKey && e.key === 'r') || (e.ctrlKey && e.key === 'c') || (e.ctrlKey && e.key === 'v')) {
            e.preventDefault();
        }
    });

    // Initialize
    loadQuestion(0);
</script>

</body>
</html>
