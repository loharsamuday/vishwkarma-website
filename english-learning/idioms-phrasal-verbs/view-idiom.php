<?php
// idioms-phrasal-verbs/view-idiom.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// Fetch idiom
$stmt = $pdo->prepare("SELECT i.*, c.name as category_name FROM idioms i LEFT JOIN exam_categories c ON i.category_id = c.id WHERE i.slug = ? AND i.status = 'Published'");
$stmt->execute([$slug]);
$idiom = $stmt->fetch();

if (!$idiom) {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>404 Not Found</h1><p>The idiom you are looking for does not exist.</p>";
    exit;
}

// Update views
$stmt_view = $pdo->prepare("UPDATE idioms SET views = views + 1 WHERE id = ?");
$stmt_view->execute([$idiom['id']]);

// Fetch related idioms
$stmt_related = $pdo->prepare("SELECT idiom, slug FROM idioms WHERE id != ? AND category_id = ? AND status = 'Published' LIMIT 5");
$stmt_related->execute([$idiom['id'], $idiom['category_id']]);
$related_idioms = $stmt_related->fetchAll();

// SEO setup
$page_title = !empty($idiom['meta_title']) ? $idiom['meta_title'] : $idiom['idiom'] . " Meaning in Hindi | Idiom, Example & Memory Trick";
$seo_desc = !empty($idiom['meta_description']) ? $idiom['meta_description'] : "Learn " . $idiom['idiom'] . " idiom meaning in Hindi with easy explanation, examples, synonyms, antonyms and memory trick for competitive exams.";

// Check Memory Status
$user_id = $_SESSION['user_id'] ?? null;
$memory_status = null;
if ($user_id) {
    $stmt_mem = $pdo->prepare("SELECT status FROM student_memory WHERE user_id = ? AND item_type = 'idiom' AND item_id = ?");
    $stmt_mem->execute([$user_id, $idiom['id']]);
    $memory_status = $stmt_mem->fetchColumn();
}

require_once '../includes/header.php';
?>

<div class="bg-light py-4 border-bottom mb-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="../vocabulary.php">Vocabulary</a></li>
                <li class="breadcrumb-item"><a href="idioms.php">Idioms</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($idiom['idiom']) ?></li>
            </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-center mb-1">
            <h1 class="fw-bold text-primary mb-0"><?= htmlspecialchars($idiom['idiom']) ?></h1>
            <?php if($user_id): ?>
                <button id="btn-remember" class="btn btn-<?= $memory_status ? 'success' : 'outline-danger' ?> btn-sm fw-bold shadow-sm" onclick="handleMemoryAction('remember', 'idiom', <?= $idiom['id'] ?>)">
                    <?= $memory_status ? '💾 Saved in My Memory' : '❤️ Remember Me' ?>
                </button>
            <?php else: ?>
                <a href="../login.php" class="btn btn-outline-danger btn-sm fw-bold shadow-sm">❤️ Remember Me</a>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-3 text-muted small mt-2">
            <?php if($idiom['category_name']): ?>
                <span><i class="fas fa-tag"></i> <?= htmlspecialchars($idiom['category_name']) ?></span>
            <?php endif; ?>
            <span><i class="fas fa-signal"></i> <?= htmlspecialchars($idiom['difficulty']) ?></span>
            <span><i class="fas fa-eye"></i> <?= $idiom['views'] ?> Views</span>
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
                            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-brain text-primary me-2"></i> Can you remember it?</h5>
                            <div class="btn-group">
                                <button class="btn btn-success fw-bold" onclick="handleMemoryAction('know', 'idiom', <?= $idiom['id'] ?>)"><i class="fas fa-check me-1"></i> I KNOW</button>
                                <button class="btn btn-danger fw-bold" onclick="handleMemoryAction('forgot', 'idiom', <?= $idiom['id'] ?>)"><i class="fas fa-times me-1"></i> I FORGOT</button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <h4 class="fw-bold text-dark mb-3 border-bottom pb-2">Meaning</h4>
                    
                    <div class="mb-4">
                        <h5 class="text-primary"><i class="fas fa-language me-2"></i> Hindi Meaning</h5>
                        <p class="fs-5 border-start border-4 border-primary ps-3 py-2 bg-light rounded-end">
                            <?= htmlspecialchars($idiom['hindi_meaning']) ?>
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <h5 class="text-secondary"><i class="fas fa-book me-2"></i> English Meaning</h5>
                        <p class="fs-5 border-start border-4 border-secondary ps-3 py-2 bg-light rounded-end">
                            <?= htmlspecialchars($idiom['english_meaning']) ?>
                        </p>
                    </div>

                    <?php if(!empty($idiom['explanation'])): ?>
                    <div class="mb-4">
                        <h5 class="text-info"><i class="fas fa-info-circle me-2"></i> Explanation</h5>
                        <p><?= nl2br(htmlspecialchars($idiom['explanation'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <h5 class="text-success"><i class="fas fa-quote-left me-2"></i> Example Sentence</h5>
                        <div class="p-3 bg-success bg-opacity-10 rounded fst-italic">
                            "<?= nl2br(htmlspecialchars($idiom['example_sentence'])) ?>"
                        </div>
                    </div>

                    <?php if(!empty($idiom['memory_trick'])): ?>
                    <div class="mb-4">
                        <h5 class="text-warning text-dark"><i class="fas fa-lightbulb me-2"></i> Easy Memory Trick</h5>
                        <div class="p-3 bg-warning bg-opacity-10 rounded border border-warning">
                            <strong>Trick:</strong> <?= nl2br(htmlspecialchars($idiom['memory_trick'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($idiom['synonyms']) || !empty($idiom['antonyms'])): ?>
                    <div class="row mt-4">
                        <?php if(!empty($idiom['synonyms'])): ?>
                        <div class="col-md-6 mb-3">
                            <h6 class="fw-bold"><i class="fas fa-equals me-2 text-muted"></i> Synonyms</h6>
                            <p><?= htmlspecialchars($idiom['synonyms']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($idiom['antonyms'])): ?>
                        <div class="col-md-6 mb-3">
                            <h6 class="fw-bold"><i class="fas fa-not-equal me-2 text-muted"></i> Antonyms</h6>
                            <p><?= htmlspecialchars($idiom['antonyms']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>

            <!-- Navigation -->
            <div class="d-flex justify-content-between mb-4">
                <a href="idioms.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
                <a href="practice.php?type=idiom" class="btn btn-primary">Practice Idioms <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Related Content -->
            <?php if(count($related_idioms) > 0): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="fas fa-link me-2 text-primary"></i> Related Idioms
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach($related_idioms as $rel): ?>
                        <a href="view-idiom.php?slug=<?= htmlspecialchars($rel['slug']) ?>" class="list-group-item list-group-item-action">
                            <?= htmlspecialchars($rel['idiom']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- CTA -->
            <div class="card border-0 shadow-sm bg-primary text-white text-center">
                <div class="card-body py-5">
                    <i class="fas fa-graduation-cap fa-3x mb-3"></i>
                    <h4 class="fw-bold">Test Your Knowledge</h4>
                    <p>Take a quick quiz to see how well you remember idioms and phrasal verbs.</p>
                    <a href="practice.php" class="btn btn-light mt-2 fw-bold text-primary">Start Quiz</a>
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
                alert(data.message); // Could be replaced with toast
            }
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>

<?php require_once '../includes/footer.php'; ?>
