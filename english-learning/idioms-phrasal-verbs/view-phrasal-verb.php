<?php
// idioms-phrasal-verbs/view-phrasal-verb.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include configuration and functions
require_once '../config/database.php';
require_once '../includes/functions.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

$stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM phrasal_verbs p LEFT JOIN exam_categories c ON p.category_id = c.id WHERE p.slug = ? AND p.status = 'Published'");
$stmt->execute([$slug]);
$phrasal = $stmt->fetch();

if (!$phrasal) {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>404 Not Found</h1><p>The phrasal verb you are looking for does not exist.</p>";
    exit;
}

$stmt_view = $pdo->prepare("UPDATE phrasal_verbs SET views = views + 1 WHERE id = ?");
$stmt_view->execute([$phrasal['id']]);

$stmt_related = $pdo->prepare("SELECT phrasal_verb, slug FROM phrasal_verbs WHERE id != ? AND category_id = ? AND status = 'Published' LIMIT 5");
$stmt_related->execute([$phrasal['id'], $phrasal['category_id']]);
$related_phrasal = $stmt_related->fetchAll();

$page_title = !empty($phrasal['meta_title']) ? $phrasal['meta_title'] : $phrasal['phrasal_verb'] . " Meaning in Hindi | Example & Memory Trick";
$seo_desc = !empty($phrasal['meta_description']) ? $phrasal['meta_description'] : "Learn " . $phrasal['phrasal_verb'] . " meaning in Hindi with easy explanation, examples, synonyms, antonyms and memory trick for competitive exams.";

// Check Memory Status
$user_id = $_SESSION['user_id'] ?? null;
$memory_status = null;
if ($user_id) {
    $stmt_mem = $pdo->prepare("SELECT status FROM student_memory WHERE user_id = ? AND item_type = 'phrasal_verb' AND item_id = ?");
    $stmt_mem->execute([$user_id, $phrasal['id']]);
    $memory_status = $stmt_mem->fetchColumn();
}

require_once '../includes/header.php';
?>

<div class="bg-light py-4 border-bottom mb-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="../vocabulary.php">Vocabulary</a></li>
                <li class="breadcrumb-item"><a href="phrasal-verbs.php">Phrasal Verbs</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($phrasal['phrasal_verb']) ?></li>
            </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-center mb-1">
            <h1 class="fw-bold text-success mb-0"><?= htmlspecialchars($phrasal['phrasal_verb']) ?></h1>
            <?php if($user_id): ?>
                <button id="btn-remember" class="btn btn-<?= $memory_status ? 'success' : 'outline-danger' ?> btn-sm fw-bold shadow-sm" onclick="handleMemoryAction('remember', 'phrasal_verb', <?= $phrasal['id'] ?>)">
                    <?= $memory_status ? '💾 Saved in My Memory' : '❤️ Remember Me' ?>
                </button>
            <?php else: ?>
                <a href="../login.php" class="btn btn-outline-danger btn-sm fw-bold shadow-sm">❤️ Remember Me</a>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-3 text-muted small mt-2">
            <?php if($phrasal['category_name']): ?>
                <span><i class="fas fa-tag"></i> <?= htmlspecialchars($phrasal['category_name']) ?></span>
            <?php endif; ?>
            <span><i class="fas fa-signal"></i> <?= htmlspecialchars($phrasal['difficulty']) ?></span>
            <span><i class="fas fa-eye"></i> <?= $phrasal['views'] ?> Views</span>
        </div>
    </div>
</div>

<div class="container pb-5">
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <?php if($user_id): ?>
                        <div class="bg-light p-3 rounded mb-4 border d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-brain text-success me-2"></i> Can you remember it?</h5>
                            <div class="btn-group">
                                <button class="btn btn-success fw-bold" onclick="handleMemoryAction('know', 'phrasal_verb', <?= $phrasal['id'] ?>)"><i class="fas fa-check me-1"></i> I KNOW</button>
                                <button class="btn btn-danger fw-bold" onclick="handleMemoryAction('forgot', 'phrasal_verb', <?= $phrasal['id'] ?>)"><i class="fas fa-times me-1"></i> I FORGOT</button>
                            </div>
                        </div>
                    <?php endif; ?>
                    <h4 class="fw-bold text-dark mb-3 border-bottom pb-2">Meaning</h4>
                    
                    <div class="mb-4">
                        <h5 class="text-success"><i class="fas fa-language me-2"></i> Hindi Meaning</h5>
                        <p class="fs-5 border-start border-4 border-success ps-3 py-2 bg-light rounded-end">
                            <?= htmlspecialchars($phrasal['hindi_meaning']) ?>
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <h5 class="text-secondary"><i class="fas fa-book me-2"></i> English Meaning</h5>
                        <p class="fs-5 border-start border-4 border-secondary ps-3 py-2 bg-light rounded-end">
                            <?= htmlspecialchars($phrasal['english_meaning']) ?>
                        </p>
                    </div>

                    <?php if(!empty($phrasal['explanation'])): ?>
                    <div class="mb-4">
                        <h5 class="text-info"><i class="fas fa-info-circle me-2"></i> Explanation</h5>
                        <p><?= nl2br(htmlspecialchars($phrasal['explanation'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <h5 class="text-success"><i class="fas fa-quote-left me-2"></i> Example Sentence</h5>
                        <div class="p-3 bg-success bg-opacity-10 rounded fst-italic border border-success border-opacity-25">
                            "<?= nl2br(htmlspecialchars($phrasal['example_sentence'])) ?>"
                        </div>
                    </div>

                    <?php if(!empty($phrasal['memory_trick'])): ?>
                    <div class="mb-4">
                        <h5 class="text-warning text-dark"><i class="fas fa-lightbulb me-2"></i> Easy Memory Trick</h5>
                        <div class="p-3 bg-warning bg-opacity-10 rounded border border-warning">
                            <strong>Trick:</strong> <?= nl2br(htmlspecialchars($phrasal['memory_trick'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($phrasal['synonyms']) || !empty($phrasal['antonyms'])): ?>
                    <div class="row mt-4">
                        <?php if(!empty($phrasal['synonyms'])): ?>
                        <div class="col-md-6 mb-3">
                            <h6 class="fw-bold"><i class="fas fa-equals me-2 text-muted"></i> Synonyms</h6>
                            <p><?= htmlspecialchars($phrasal['synonyms']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($phrasal['antonyms'])): ?>
                        <div class="col-md-6 mb-3">
                            <h6 class="fw-bold"><i class="fas fa-not-equal me-2 text-muted"></i> Antonyms</h6>
                            <p><?= htmlspecialchars($phrasal['antonyms']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>

            <!-- Navigation -->
            <div class="d-flex justify-content-between mb-4">
                <a href="<?= EL_BASE_URL ?>idioms-phrasal-verbs/phrasal-verbs.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
                <a href="<?= EL_BASE_URL ?>idioms-phrasal-verbs/practice.php?type=phrasal_verb" class="btn btn-success">Practice Phrasal Verbs <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Related Content -->
            <?php if(count($related_phrasal) > 0): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="fas fa-link me-2 text-success"></i> Related Phrasal Verbs
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach($related_phrasal as $rel): ?>
                        <a href="view-phrasal-verb.php?slug=<?= htmlspecialchars($rel['slug']) ?>" class="list-group-item list-group-item-action text-success fw-semibold">
                            <?= htmlspecialchars($rel['phrasal_verb']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- CTA -->
            <div class="card border-0 shadow-sm bg-success text-white text-center">
                <div class="card-body py-5">
                    <i class="fas fa-graduation-cap fa-3x mb-3"></i>
                    <h4 class="fw-bold">Test Your Knowledge</h4>
                    <p>Take a quick quiz to see how well you remember idioms and phrasal verbs.</p>
                    <a href="<?= EL_BASE_URL ?>idioms-phrasal-verbs/practice.php" class="btn btn-light mt-2 fw-bold text-success">Start Quiz</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function handleMemoryAction(action, item_type, item_id) {
    const btnRemember = document.getElementById('btn-remember');
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append('item_type', item_type);
    formData.append('item_id', item_id);

    fetch('../ajax/memory_action.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            if(action === 'remember') {
                btnRemember.className = 'btn btn-success btn-sm fw-bold shadow-sm';
                btnRemember.innerHTML = data.button_text;
            } else {
                alert(data.message);
            }
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>

<?php require_once '../includes/footer.php'; ?>
