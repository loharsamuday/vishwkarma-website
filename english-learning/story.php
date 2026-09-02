<?php
// story.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch story
$stmt = $pdo->prepare("
    SELECT s.*, c.name as category_name 
    FROM stories s 
    LEFT JOIN categories c ON s.category_id = c.id 
    WHERE s.id = ? AND s.status = 'Published'
");
$stmt->execute([$id]);
$story = $stmt->fetch();

if (!$story) {
    header("Location: stories.php");
    exit();
}

// Fetch vocabulary for this story
$stmt = $pdo->prepare("SELECT * FROM vocabulary WHERE story_id = ? ORDER BY word ASC");
$stmt->execute([$id]);
$vocabularies = $stmt->fetchAll();

// Process story content to highlight vocabulary words
$content = trim($story['content']);

// Only format newlines if the content is raw text (doesn't already have HTML tags like <p> or <br>)
if (stripos($content, '<p') === false && stripos($content, '<br') === false) {
    // Convert double newlines into paragraphs to give reading gaps
    $content = '<p>' . preg_replace("/\n\s*\n/", "</p>\n<p>", $content) . '</p>';
    // Convert remaining single newlines to line breaks
    $content = nl2br($content);
}


// We need to replace words in content with highlighted spans.
// We should do this carefully to avoid replacing HTML tags.
// A robust way in PHP is using DOMDocument or regex with negative lookahead for HTML tags.
// For simplicity and speed in this context, we'll use regex.

foreach ($vocabularies as $vocab) {
    $word = preg_quote($vocab['word'], '/');
    // \b is word boundary. (?![^<]*>) ensures we don't replace inside HTML tags like <a href="...">
    // i flag for case-insensitive
    $pattern = "/\b(" . $word . ")\b(?![^<]*>)/i";
    
    // Replacement: Highlighted span with data attribute pointing to vocab ID
    $replacement = "<span class='vocab-word-highlight' data-vocab-id='{$vocab['id']}' data-bs-toggle='tooltip' title='Click to see meaning'>$1</span>";
    
    $content = preg_replace($pattern, $replacement, $content);
}

$page_title = escape($story['seo_title'] ?: $story['title']);
$seo_desc = escape($story['seo_description'] ?: $story['short_description']);
include 'includes/header.php';
?>

<style>
    .reader-progress { position: fixed; top: 56px; left: 0; width: 100%; height: 3px; background: rgba(11,59,96,.08); z-index: 1031; }
    .reader-progress span { display: block; width: 0; height: 100%; background: linear-gradient(90deg, #3498db, #2ecc71); transition: width .12s linear; }
    .reader-page { max-width: 1180px; }
    .reader-crumbs { font-size: .88rem; }
    .reader-crumbs .breadcrumb-item + .breadcrumb-item::before { color: #8ba0b3; }
    .reader-hero { position: relative; overflow: hidden; padding: 2.15rem 2rem; border-radius: 1.5rem; background: radial-gradient(circle at 85% 15%, rgba(52,152,219,.3), transparent 28%), linear-gradient(130deg, #072c49, #0b3b60 58%, #145b74); box-shadow: 0 18px 40px rgba(8,43,73,.18); }
    .reader-hero::after { content: ''; position: absolute; width: 260px; height: 260px; border: 1px solid rgba(255,255,255,.14); border-radius: 50%; right: -95px; bottom: -145px; }
    .reader-category { display: inline-flex; align-items: center; gap: .45rem; padding: .4rem .75rem; border: 1px solid rgba(255,255,255,.25); border-radius: 99px; background: rgba(255,255,255,.1); font-size: .78rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .reader-hero h1 { max-width: 850px; font-size: clamp(2rem, 5vw, 3.7rem); line-height: 1.12; letter-spacing: -.035em; }
    .reader-summary { max-width: 760px; color: rgba(255,255,255,.79); font-size: 1.07rem; }
    .reader-meta { display: flex; flex-wrap: wrap; gap: .65rem; margin-top: 1.7rem; }
    .reader-meta span { display: inline-flex; align-items: center; gap: .42rem; padding: .54rem .75rem; background: rgba(255,255,255,.11); border: 1px solid rgba(255,255,255,.13); border-radius: .65rem; color: #fff; font-size: .86rem; }
    .reader-paper { background: #fff; border: 1px solid #e5edf3; border-radius: 1.2rem; box-shadow: 0 16px 38px rgba(23,55,79,.08); padding: clamp(1.4rem, 5vw, 4rem); }
    .reader-prose { color: #172b3a; font-family: Lora, Georgia, serif; font-size: clamp(1.06rem, 2.7vw, 1.22rem); line-height: 1.95; }
    .reader-prose p { margin-bottom: 1.6rem; }
    .reader-prose p:last-child { margin-bottom: 0; }
    .reader-paper .vocab-word-highlight { color: #075d87; background: #e0f4fb; border-bottom: 2px solid #32a1c8; padding: .05em .2em; }
    .reader-paper .vocab-word-highlight:hover { background: #caecf8; }
    .reader-panel { border: 1px solid #e5edf3; border-radius: 1.05rem; padding: clamp(1.25rem, 4vw, 2rem); box-shadow: 0 8px 24px rgba(23,55,79,.05); }
    .reader-panel-title { display: flex; align-items: center; gap: .7rem; font-size: 1.1rem; }
    .reader-panel-title i { width: 36px; height: 36px; display: inline-flex; justify-content: center; align-items: center; border-radius: .7rem; }
    .reader-hindi { background: #f4fbf7; border-left: 4px solid #2ecc71; }
    .reader-hindi .reader-panel-title i { color: #198754; background: #dff4e6; }
    .reader-moral { background: #fffaf0; border-left: 4px solid #f0b429; }
    .reader-moral .reader-panel-title i { color: #b7791f; background: #fff0c9; }
    .reader-vocab-heading { display: flex; align-items: center; gap: .7rem; }
    .reader-vocab-heading i { width: 40px; height: 40px; display: inline-flex; justify-content: center; align-items: center; color: #08759d; background: #e2f4fa; border-radius: .75rem; }
    .reader-vocab-card { border: 1px solid #e5edf3 !important; border-left: 4px solid #3498db !important; border-radius: 1rem; box-shadow: 0 7px 18px rgba(23,55,79,.05); transition: transform .2s ease, box-shadow .2s ease; }
    .reader-vocab-card:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(23,55,79,.1); }
    .reader-cta { background: radial-gradient(circle at 85% 20%, rgba(255,255,255,.16), transparent 26%), linear-gradient(130deg, #0b3b60, #087a78) !important; }
    @media (max-width: 767.98px) {
        .reader-progress { top: 56px; }
        .reader-page { margin-top: 1.4rem !important; }
        .reader-crumbs { white-space: nowrap; overflow: auto; padding-bottom: .3rem; }
        .reader-hero { padding: 1.65rem 1.3rem 1.8rem; border-radius: 1.1rem; }
        .reader-summary { font-size: .98rem; }
        .reader-meta span { font-size: .78rem; }
        .reader-paper { border-radius: 1rem; }
        .reader-prose { line-height: 1.82; }
        .social-action-btn { flex: 1 1 auto; justify-content: center; padding: .65rem 1rem !important; font-size: .9rem !important; }
    }
</style>

<div class="reader-progress" aria-hidden="true"><span id="readerProgressBar"></span></div>
<div class="container reader-page my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <nav aria-label="breadcrumb" class="reader-crumbs mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="stories.php">Stories</a></li>
                    <li class="breadcrumb-item"><a href="stories.php?category=<?= $story['category_id'] ?>"><?= escape($story['category_name']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= escape($story['title']) ?></li>
                </ol>
            </nav>

            <header class="reader-hero text-white mb-4">
                <span class="reader-category"><i class="fas fa-bookmark"></i><?= escape($story['category_name'] ?: 'English Story') ?></span>
                <h1 class="fw-bold mt-3 mb-3"><?= escape($story['title']) ?></h1>
                <?php if($story['short_description']): ?>
                    <p class="reader-summary mb-0"><?= escape($story['short_description']) ?></p>
                <?php endif; ?>
                <div class="reader-meta">
                    <span><i class="far fa-clock"></i><?= (int)$story['reading_time'] ?> min read</span>
                    <span><i class="fas fa-signal"></i><?= escape($story['difficulty']) ?> level</span>
                    <?php if ($vocabularies): ?><span><i class="fas fa-language"></i><?= count($vocabularies) ?> vocabulary words</span><?php endif; ?>
                </div>
            </header>

            <!-- Story Content -->
            <div class="story-content reader-paper mb-4">
                <div class="story-text-gradient reader-prose">
                    <?= $content ?>
                </div>
            </div>

            <?php if (!empty($story['hindi_meaning'])): ?>
            <!-- Hindi Meaning -->
            <div class="reader-panel reader-hindi mb-4">
                <h4 class="reader-panel-title fw-bold text-success mb-3"><i class="fas fa-language"></i>Hindi Translation</h4>
                <div class="story-hindi-meaning">
                    <?= nl2br(escape($story['hindi_meaning'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($story['moral'])): ?>
            <!-- Moral -->
            <div class="reader-panel reader-moral mb-4">
                <h4 class="reader-panel-title fw-bold text-warning-emphasis mb-3"><i class="fas fa-lightbulb"></i>Moral of the Story</h4>
                <p class="mb-0 fs-5 text-dark fw-medium fst-italic">"<?= escape($story['moral']) ?>"</p>
            </div>
            <?php endif; ?>

            <!-- Social Actions -->
            <style>
            .social-action-btn {
                border-radius: 50px;
                padding: 0.7rem 1.8rem;
                font-weight: 600;
                font-size: 1rem;
                transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                display: inline-flex;
                align-items: center;
                border: 2px solid transparent;
                background: #fff;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
                gap: 8px;
            }

            .btn-like { color: #007bff; border-color: #e6f2ff; }
            .btn-like:hover { background: #007bff; color: #fff; border-color: #007bff; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0, 123, 255, 0.25); }

            .btn-comment { color: #6f42c1; border-color: #f3e8ff; }
            .btn-comment:hover { background: #6f42c1; color: #fff; border-color: #6f42c1; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(111, 66, 193, 0.25); }

            .btn-share { color: #28a745; border-color: #e6f9ec; }
            .btn-share:hover { background: #28a745; color: #fff; border-color: #28a745; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(40, 167, 69, 0.25); }

            .social-action-btn i { font-size: 1.15rem; transition: transform 0.3s ease; }
            .btn-like:hover i { transform: scale(1.2) rotate(-15deg); }
            .btn-comment:hover i { transform: scale(1.1); }
            .btn-share:hover i { transform: translateX(3px) scale(1.1); }
            </style>
            
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-5 py-4 border-top border-bottom gap-3">
                <div class="d-flex flex-wrap gap-3">
                    <button class="social-action-btn btn-like" onclick="alert('Liked!')">
                        <i class="far fa-thumbs-up"></i> Like
                    </button>
                    <button class="social-action-btn btn-comment" onclick="document.getElementById('comments-section').scrollIntoView({behavior: 'smooth'})">
                        <i class="far fa-comment-dots"></i> Comment
                    </button>
                </div>
                <div>
                    <button class="social-action-btn btn-share" onclick="navigator.clipboard.writeText(window.location.href); alert('Link copied to clipboard!');">
                        <i class="fas fa-share-nodes"></i> Share
                    </button>
                </div>
            </div>

            <!-- Comments Section -->
            <div id="comments-section" class="mb-5">
                <h4 class="fw-bold mb-4">Comments</h4>
                <div class="card border-0 bg-light rounded-3 p-4 mb-4">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label for="commentText" class="form-label">Leave a comment</label>
                            <textarea class="form-control" id="commentText" name="comment" rows="3" placeholder="What are your thoughts on this story?" required></textarea>
                        </div>
                        <button type="button" class="btn btn-primary px-4" onclick="alert('Comment submitted! (Functionality can be implemented later)')">Post Comment</button>
                    </form>
                </div>
            </div>

            <!-- Vocabulary Section -->
            <?php if (count($vocabularies) > 0): ?>
            <div class="mt-5 pt-4 border-top">
                <h3 class="reader-vocab-heading fw-bold mb-4 text-primary-custom"><i class="fas fa-language"></i>Vocabulary from this story</h3>
                
                <div class="row">
                    <?php foreach ($vocabularies as $vocab): ?>
                    <div class="col-12 mb-3">
                        <div class="card vocab-card reader-vocab-card border-0 p-3" id="vocab-<?= $vocab['id'] ?>">
                            <div class="row align-items-center">
                                <div class="col-md-3 border-end-md">
                                    <h5 class="fw-bold text-dark mb-1"><?= escape($vocab['word']) ?></h5>
                                    <?php if($vocab['part_of_speech']): ?>
                                        <span class="badge bg-light text-secondary border"><?= escape($vocab['part_of_speech']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9 ps-md-4 mt-3 mt-md-0">
                                    <?php if($vocab['hindi_meaning']): ?>
                                    <div class="mb-2">
                                        <strong class="text-success">Hindi Meaning:</strong> <span class="fs-5"><?= escape($vocab['hindi_meaning']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if($vocab['english_meaning']): ?>
                                    <div class="mb-2">
                                        <strong>English Meaning:</strong> <?= escape($vocab['english_meaning']) ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if($vocab['synonym'] || $vocab['antonym']): ?>
                                    <div class="mb-2 text-muted small">
                                        <?php if($vocab['synonym']): ?>
                                            <span class="me-3"><strong>Synonym:</strong> <?= escape($vocab['synonym']) ?></span>
                                        <?php endif; ?>
                                        <?php if($vocab['antonym']): ?>
                                            <span><strong>Antonym:</strong> <?= escape($vocab['antonym']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if($vocab['example_sentence']): ?>
                                    <div class="bg-light p-2 rounded mt-2 border-start border-3 border-primary fst-italic text-muted">
                                        "<?= escape($vocab['example_sentence']) ?>"
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- CTA -->
            <div class="card reader-cta bg-primary-custom text-white border-0 rounded-4 p-4 mt-5 text-center shadow">
                <div class="card-body">
                    <h3 class="fw-bold mb-3">Inspired by this story?</h3>
                    <p class="mb-4">Use the new vocabulary you've learned and write your own English story.</p>
                    <a href="write-story.php" class="btn btn-light btn-lg text-primary-custom fw-bold px-4">Write Your Own Story</a>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const progress = document.getElementById('readerProgressBar');
    const readingArea = document.querySelector('.story-content');
    if (!progress || !readingArea) return;

    function updateReadingProgress() {
        const rect = readingArea.getBoundingClientRect();
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        const total = Math.max(readingArea.offsetHeight - viewportHeight * 0.45, 1);
        const completed = Math.min(Math.max(-rect.top + viewportHeight * 0.45, 0), total);
        progress.style.width = (completed / total * 100) + '%';
    }
    window.addEventListener('scroll', updateReadingProgress, { passive: true });
    updateReadingProgress();
});
</script>

<?php if (!isset($_SESSION['user_id'])): ?>
<!-- Registration Prompt Modal -->
<div class="modal fade" id="registerPromptModal" tabindex="-1" aria-labelledby="registerPromptModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-success text-white border-0">
        <h5 class="modal-title fw-bold" id="registerPromptModalLabel"><i class="fas fa-gift me-2"></i>Enjoying the stories?</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="mb-4 text-success">
            <i class="fas fa-book-reader fa-4x opacity-75"></i>
        </div>
        <h4 class="fw-bold mb-3">Join Our Community!</h4>
        <p class="text-muted mb-4">Create a free account to track your reading progress, save vocabulary, and leave comments on stories.</p>
        <div class="d-grid gap-3">
            <a href="register.php" class="btn btn-success btn-lg fw-bold rounded-pill shadow-sm">Create Free Account</a>
            <a href="login.php" class="text-decoration-none text-muted fw-medium">Already have an account? Log in</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Show the registration popup after 2 minutes (120,000 milliseconds)
    setTimeout(function() {
        var registerModal = new bootstrap.Modal(document.getElementById('registerPromptModal'));
        registerModal.show();
    }, 120000);
});
</script>
<?php endif; ?>

<!-- Reading Tools (Highlighter, Pen, Eraser) -->
<div class="reading-tools-bar shadow-lg">
    <button class="tool-btn" id="btnHighlight" title="Highlighter" onclick="toggleTool('highlight')"><i class="fas fa-highlighter"></i></button>
    <button class="tool-btn" id="btnPen" title="Red Pen (Draw)" onclick="toggleTool('pen')"><i class="fas fa-pen"></i></button>
    <button class="tool-btn" id="btnEraser" title="Eraser" onclick="toggleTool('eraser')"><i class="fas fa-eraser"></i></button>
</div>

<script>
// Canvas for Freehand Drawing (Pen Tool)
const canvas = document.createElement('canvas');
canvas.id = 'drawingCanvas';
canvas.style.position = 'absolute';
canvas.style.top = '0';
canvas.style.left = '0';
canvas.style.zIndex = '900'; // Below the toolbar but above the content
canvas.style.pointerEvents = 'none'; // Let mouse events pass through to the document
document.body.appendChild(canvas);

let ctx = canvas.getContext('2d');
let isDrawing = false;

function resizeCanvas() {
    canvas.width = document.documentElement.scrollWidth;
    canvas.height = document.documentElement.scrollHeight;
}
window.addEventListener('resize', resizeCanvas);
setTimeout(resizeCanvas, 500); // Initial resize

let currentTool = null;

function toggleTool(tool) {
    let btnHighlight = document.getElementById('btnHighlight');
    let btnPen = document.getElementById('btnPen');
    let btnEraser = document.getElementById('btnEraser');
    
    if (currentTool === tool) {
        // Deselect tool
        currentTool = null;
        btnHighlight.classList.remove('active-tool');
        btnPen.classList.remove('active-tool');
        btnEraser.classList.remove('active-tool');
        btnHighlight.style.color = '';
        btnPen.style.color = '';
        btnEraser.style.color = '';
        document.body.style.cursor = 'default';
        document.body.classList.remove('drawing-mode');
        return;
    }
    
    currentTool = tool;
    btnHighlight.classList.remove('active-tool');
    btnPen.classList.remove('active-tool');
    btnEraser.classList.remove('active-tool');
    btnHighlight.style.color = '';
    btnPen.style.color = '';
    btnEraser.style.color = '';
    
    if (tool === 'highlight') {
        btnHighlight.classList.add('active-tool');
        btnHighlight.style.color = '#f1c40f'; // yellow
        document.body.style.cursor = 'text';
        document.body.classList.remove('drawing-mode');
    } else if (tool === 'pen') {
        btnPen.classList.add('active-tool');
        btnPen.style.color = '#e74c3c'; // red pen
        document.body.style.cursor = 'crosshair';
        document.body.classList.add('drawing-mode'); // Prevent text selection while drawing
    } else if (tool === 'eraser') {
        btnEraser.classList.add('active-tool');
        btnEraser.style.color = '#7f8c8d'; // gray eraser
        document.body.style.cursor = 'crosshair';
        document.body.classList.remove('drawing-mode'); // Allow text selection for erasing highlights
    }
}

// Drawing Logic with Mobile Touch Support
function getCoordinates(e) {
    if (e.touches && e.touches.length > 0) {
        return { x: e.touches[0].pageX, y: e.touches[0].pageY };
    }
    return { x: e.pageX, y: e.pageY };
}

function startDrawing(e) {
    if (e.target.closest('.reading-tools-bar')) return;
    
    if (currentTool === 'pen' || currentTool === 'eraser') {
        isDrawing = true;
        const coords = getCoordinates(e);
        ctx.beginPath();
        ctx.moveTo(coords.x, coords.y);
        
        if (currentTool === 'pen') {
            ctx.globalCompositeOperation = 'source-over';
            ctx.strokeStyle = '#e74c3c'; // Red ink
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
        } else if (currentTool === 'eraser') {
            ctx.globalCompositeOperation = 'destination-out';
            ctx.lineWidth = 20; // Thick eraser
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
        }
    }
}

function draw(e) {
    if (isDrawing && (currentTool === 'pen' || currentTool === 'eraser')) {
        if(e.cancelable) e.preventDefault(); // Prevent mobile scrolling while actively drawing
        const coords = getCoordinates(e);
        ctx.lineTo(coords.x, coords.y);
        ctx.stroke();
    }
}

function stopDrawing(e) {
    if (isDrawing) {
        isDrawing = false;
    }
    
    // Highlighter Text Logic
    if (currentTool !== 'highlight' && currentTool !== 'eraser') return;
    
    let selection = window.getSelection();
    if (!selection || selection.rangeCount === 0 || selection.isCollapsed) return;
    
    let range = selection.getRangeAt(0);
    let container = range.commonAncestorContainer;
    if (container.nodeType === 3) container = container.parentNode;
    
    let storyContent = document.querySelector('.story-content');
    if (!storyContent && !document.querySelector('.story-text-gradient')) return;
    
    document.designMode = "on";
    if (currentTool === 'highlight') {
        document.execCommand("hiliteColor", false, "#fff176"); // Highlighter yellow
    } else if (currentTool === 'eraser') {
        // Eraser removes background color of text
        if (!document.execCommand("hiliteColor", false, "transparent")) {
            document.execCommand("backColor", false, "transparent");
        }
    }
    document.designMode = "off";
    selection.removeAllRanges();
}

// Attach Mouse Events
document.addEventListener('mousedown', startDrawing);
document.addEventListener('mousemove', draw, { passive: false });
document.addEventListener('mouseup', stopDrawing);

// Attach Touch Events for Mobile
document.addEventListener('touchstart', startDrawing, { passive: false });
document.addEventListener('touchmove', draw, { passive: false });
document.addEventListener('touchend', stopDrawing);
</script>

<?php include 'includes/footer.php'; ?>
