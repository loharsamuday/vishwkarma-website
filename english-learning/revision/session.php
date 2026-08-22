<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'Revision Session';

// Fetch one item that is due
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT sm.*, 
        COALESCE(i.idiom, p.phrasal_verb, v.word) as word,
        COALESCE(i.hindi_meaning, p.hindi_meaning, v.hindi_meaning) as hindi,
        COALESCE(i.english_meaning, p.english_meaning, v.english_meaning) as english_meaning,
        COALESCE(i.example_sentence, p.example_sentence) as example,
        COALESCE(i.memory_trick, p.memory_trick) as trick
    FROM student_memory sm
    LEFT JOIN idioms i ON sm.item_type = 'idiom' AND sm.item_id = i.id
    LEFT JOIN phrasal_verbs p ON sm.item_type = 'phrasal_verb' AND sm.item_id = p.id
    LEFT JOIN vocabulary v ON sm.item_type = 'vocabulary' AND sm.item_id = v.id
    WHERE sm.user_id = ? AND (sm.next_revision_date <= ? OR sm.status = 'need_revision')
    ORDER BY sm.next_revision_date ASC
    LIMIT 1
");
$stmt->execute([$user_id, $today]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: index.php?msg=done");
    exit;
}

// Check how many left
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM student_memory WHERE user_id = ? AND (next_revision_date <= ? OR status = 'need_revision')");
$stmt_count->execute([$user_id, $today]);
$left = $stmt_count->fetchColumn();

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="index.php" class="text-decoration-none text-muted"><i class="fas fa-times fa-lg"></i></a>
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold"><i class="fas fa-layer-group me-1"></i> <?= $left ?> Remaining</span>
            </div>
            
            <div class="card shadow-lg border-0 rounded-4 mb-4">
                <div class="card-body p-5 text-center">
                    <span class="badge bg-light text-dark border mb-3"><?= ucfirst(str_replace('_', ' ', $item['item_type'])) ?></span>
                    <h1 class="display-5 fw-bold text-primary mb-4"><?= escape($item['word']) ?></h1>
                    
                    <div id="answer-reveal" class="d-none">
                        <hr class="my-4">
                        <div class="mb-4">
                            <h5 class="text-success fw-bold"><i class="fas fa-language me-1"></i> <?= escape($item['hindi']) ?></h5>
                            <p class="text-muted"><?= escape($item['english_meaning']) ?></p>
                        </div>
                        
                        <?php if($item['trick']): ?>
                            <div class="p-3 bg-warning bg-opacity-10 rounded border border-warning text-start mb-4">
                                <strong><i class="fas fa-lightbulb text-warning me-1"></i> Trick:</strong> <?= escape($item['trick']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($item['example']): ?>
                            <div class="p-3 bg-light rounded text-start fst-italic">
                                "<?= escape($item['example']) ?>"
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div id="btn-show-answer" class="mt-5">
                        <button class="btn btn-outline-primary btn-lg rounded-pill px-5 fw-bold w-100" onclick="showAnswer()">Show Answer</button>
                    </div>
                </div>
            </div>
            
            <div id="action-buttons" class="row g-3 d-none">
                <div class="col-6">
                    <button class="btn btn-danger btn-lg w-100 fw-bold rounded-4 py-3 shadow-sm" onclick="submitRevision('forgot', '<?= $item['item_type'] ?>', <?= $item['item_id'] ?>)">
                        <i class="fas fa-times fa-2x mb-2 d-block"></i>
                        I FORGOT
                    </button>
                </div>
                <div class="col-6">
                    <button class="btn btn-success btn-lg w-100 fw-bold rounded-4 py-3 shadow-sm" onclick="submitRevision('know', '<?= $item['item_type'] ?>', <?= $item['item_id'] ?>)">
                        <i class="fas fa-check fa-2x mb-2 d-block"></i>
                        I KNOW
                    </button>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
function showAnswer() {
    document.getElementById('answer-reveal').classList.remove('d-none');
    document.getElementById('btn-show-answer').classList.add('d-none');
    document.getElementById('action-buttons').classList.remove('d-none');
}

function submitRevision(action, item_type, item_id) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('item_type', item_type);
    formData.append('item_id', item_id);

    fetch('/vishwkarma/english-learning/ajax/memory_action.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            window.location.reload(); // Load next item
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>

<?php require_once '../includes/footer.php'; ?>
