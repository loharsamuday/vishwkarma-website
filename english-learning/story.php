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

// Convert double newlines into paragraphs to give reading gaps
$content = '<p>' . preg_replace("/\n\s*\n/", "</p>\n<p>", $content) . '</p>';
// Convert remaining single newlines to line breaks
$content = nl2br($content);


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

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="stories.php">Stories</a></li>
                    <li class="breadcrumb-item"><a href="stories.php?category=<?= $story['category_id'] ?>"><?= escape($story['category_name']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= escape($story['title']) ?></li>
                </ol>
            </nav>

            <!-- Story Header -->
            <div class="text-center mb-5">
                <div class="mb-3">
                    <?php if($story['difficulty'] == 'Beginner'): ?>
                        <span class="badge badge-beginner fs-6 px-3 py-2">Difficulty: Beginner</span>
                    <?php elseif($story['difficulty'] == 'Intermediate'): ?>
                        <span class="badge badge-intermediate fs-6 px-3 py-2">Difficulty: Intermediate</span>
                    <?php else: ?>
                        <span class="badge badge-advanced fs-6 px-3 py-2">Difficulty: Advanced</span>
                    <?php endif; ?>
                    <span class="badge bg-light text-dark border fs-6 px-3 py-2 ms-2"><i class="far fa-clock me-1"></i> Reading Time: <?= $story['reading_time'] ?> min</span>
                </div>
                
                <h1 class="fw-bold display-5 text-primary-custom mb-3"><?= escape($story['title']) ?></h1>
                <?php if($story['short_description']): ?>
                    <p class="lead text-muted"><?= escape($story['short_description']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Story Content -->
            <div class="story-content mb-5">
                <div class="story-text-gradient">
                    <?= $content ?>
                </div>
            </div>

            <?php if (!empty($story['hindi_meaning'])): ?>
            <!-- Hindi Meaning -->
            <div class="mb-5 p-4 bg-light rounded-3 border-start border-4 border-success">
                <h4 class="fw-bold text-success mb-3"><i class="fas fa-language me-2"></i>Hindi Translation</h4>
                <div class="story-hindi-meaning">
                    <?= nl2br(escape($story['hindi_meaning'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($story['moral'])): ?>
            <!-- Moral -->
            <div class="mb-5 p-4 bg-warning bg-opacity-10 rounded-3 border border-warning">
                <h4 class="fw-bold text-warning-emphasis mb-3"><i class="fas fa-lightbulb me-2 text-warning"></i>Moral of the Story</h4>
                <p class="mb-0 fs-5 text-dark fw-medium fst-italic">"<?= escape($story['moral']) ?>"</p>
            </div>
            <?php endif; ?>

            <!-- Social Actions -->
            <div class="d-flex align-items-center justify-content-between mb-5 py-3 border-top border-bottom">
                <div class="d-flex gap-3">
                    <button class="btn btn-outline-primary rounded-pill px-4" onclick="alert('Liked!')"><i class="far fa-thumbs-up me-2"></i>Like</button>
                    <button class="btn btn-outline-secondary rounded-pill px-4" onclick="document.getElementById('comments-section').scrollIntoView({behavior: 'smooth'})"><i class="far fa-comment me-2"></i>Comment</button>
                </div>
                <div>
                    <button class="btn btn-outline-success rounded-pill px-4" onclick="navigator.clipboard.writeText(window.location.href); alert('Link copied to clipboard!');"><i class="fas fa-share me-2"></i>Share</button>
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
                <h3 class="fw-bold mb-4 text-primary-custom"><i class="fas fa-language me-2"></i>Vocabulary from this story</h3>
                
                <div class="row">
                    <?php foreach ($vocabularies as $vocab): ?>
                    <div class="col-12 mb-3">
                        <div class="card vocab-card border-0 p-3" id="vocab-<?= $vocab['id'] ?>">
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
            <div class="card bg-primary-custom text-white border-0 rounded-4 p-4 mt-5 text-center shadow">
                <div class="card-body">
                    <h3 class="fw-bold mb-3">Inspired by this story?</h3>
                    <p class="mb-4">Use the new vocabulary you've learned and write your own English story.</p>
                    <a href="write-story.php" class="btn btn-light btn-lg text-primary-custom fw-bold px-4">Write Your Own Story</a>
                </div>
            </div>

        </div>
    </div>
</div>

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

// Drawing Logic
document.addEventListener('mousedown', e => {
    // Check if clicking inside toolbar
    if (e.target.closest('.reading-tools-bar')) return;
    
    if (currentTool === 'pen' || currentTool === 'eraser') {
        isDrawing = true;
        ctx.beginPath();
        ctx.moveTo(e.pageX, e.pageY);
        
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
});

document.addEventListener('mousemove', e => {
    if (isDrawing && (currentTool === 'pen' || currentTool === 'eraser')) {
        ctx.lineTo(e.pageX, e.pageY);
        ctx.stroke();
    }
});

document.addEventListener('mouseup', function(e) {
    if (isDrawing) {
        isDrawing = false;
    }
    
    // Highlighter Text Logic
    if (currentTool !== 'highlight' && currentTool !== 'eraser') return;
    
    let selection = window.getSelection();
    if (!selection || selection.rangeCount === 0 || selection.isCollapsed) return;
    
    // Ensure selection is inside the story content
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
});
</script>

<?php include 'includes/footer.php'; ?>
