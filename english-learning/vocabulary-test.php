<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$page_title = 'English Vocabulary Test';
$seo_desc = 'Practice English vocabulary with instant-feedback multiple choice questions.';
require_once 'includes/header.php';

$stmt = $pdo->prepare("SELECT * FROM practice_questions WHERE status = 'Published' AND content_type = 'vocabulary' ORDER BY RAND() LIMIT 10");
$stmt->execute();
$questions = $stmt->fetchAll();
?>

<div class="bg-success text-white py-5 mb-5 shadow-sm">
    <div class="container text-center">
        <span class="badge bg-white text-success mb-2">English Practice</span>
        <h1 class="fw-bold mb-2">English Vocabulary Test</h1>
        <p class="lead mb-0">Answer each question and learn from the instant explanation.</p>
    </div>
</div>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if ($questions): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted fw-semibold" id="questionCounter">Question 1 of <?= count($questions) ?></span>
                    <span class="badge bg-warning text-dark fs-6" id="scoreDisplay">Score: 0</span>
                </div>
                <div class="progress mb-4" style="height: 9px;"><div class="progress-bar bg-success" id="quizProgress" style="width: <?= 100 / count($questions) ?>%"></div></div>
                <div class="card border-0 shadow-sm overflow-hidden">
                    <div class="card-body p-4 p-md-5">
                        <?php foreach ($questions as $index => $question): ?>
                            <section class="quiz-slide <?= $index ? 'd-none' : '' ?>" data-index="<?= $index ?>">
                                <h3 class="h4 fw-bold lh-base mb-4"><?= escape($question['question']) ?></h3>
                                <?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field): ?>
                                    <button type="button" class="btn btn-outline-secondary w-100 text-start p-3 mb-3 vocab-option" data-answer="<?= $letter ?>" data-correct="<?= escape($question['correct_answer']) ?>">
                                        <strong class="me-2"><?= $letter ?>.</strong><?= escape($question[$field]) ?>
                                    </button>
                                <?php endforeach; ?>
                                <?php if ($question['explanation'] || $question['hindi_explanation']): ?>
                                    <div class="explanation d-none mt-4 p-3 bg-light border-start border-4 border-success rounded-end">
                                        <?php if ($question['explanation']): ?><p class="mb-1"><strong>Explanation:</strong> <?= escape($question['explanation']) ?></p><?php endif; ?>
                                        <?php if ($question['hindi_explanation']): ?><p class="mb-0 text-muted"><strong>हिंदी:</strong> <?= escape($question['hindi_explanation']) ?></p><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>
                        <section id="resultSlide" class="d-none text-center py-4">
                            <i class="fas fa-trophy text-warning fa-4x mb-3"></i>
                            <h2 class="fw-bold">Test Completed!</h2>
                            <p class="fs-4 text-muted">Your score: <strong class="text-success" id="finalScore">0</strong> / <?= count($questions) ?></p>
                            <a href="vocabulary-test.php" class="btn btn-success"><i class="fas fa-redo me-1"></i> Take Another Test</a>
                        </section>
                    </div>
                    <div class="card-footer bg-white border-0 text-end px-4 pb-4"><button id="nextButton" class="btn btn-success d-none">Next Question <i class="fas fa-arrow-right ms-1"></i></button></div>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center py-5"><i class="fas fa-info-circle fa-2x mb-3"></i><h4>No vocabulary questions yet</h4><p class="mb-0">Please check back soon for the vocabulary test.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($questions): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const slides = Array.from(document.querySelectorAll('.quiz-slide'));
    const next = document.getElementById('nextButton');
    let current = 0, score = 0;
    document.querySelectorAll('.vocab-option').forEach(function (button) {
        button.addEventListener('click', function () {
            const slide = this.closest('.quiz-slide');
            if (slide.dataset.answered) return;
            slide.dataset.answered = '1';
            const correct = this.dataset.correct;
            slide.querySelectorAll('.vocab-option').forEach(function (option) {
                option.disabled = true;
                if (option.dataset.answer === correct) option.classList.replace('btn-outline-secondary', 'btn-success');
            });
            if (this.dataset.answer === correct) score++;
            else this.classList.replace('btn-outline-secondary', 'btn-danger');
            document.getElementById('scoreDisplay').textContent = 'Score: ' + score;
            const explanation = slide.querySelector('.explanation');
            if (explanation) explanation.classList.remove('d-none');
            next.classList.remove('d-none');
        });
    });
    next.addEventListener('click', function () {
        slides[current].classList.add('d-none');
        current++;
        if (current < slides.length) {
            slides[current].classList.remove('d-none');
            document.getElementById('questionCounter').textContent = 'Question ' + (current + 1) + ' of ' + slides.length;
            document.getElementById('quizProgress').style.width = ((current + 1) / slides.length * 100) + '%';
            next.classList.add('d-none');
        } else {
            document.getElementById('resultSlide').classList.remove('d-none');
            document.getElementById('finalScore').textContent = score;
            document.getElementById('questionCounter').textContent = 'Completed';
            document.getElementById('quizProgress').style.width = '100%';
            document.getElementById('scoreDisplay').classList.add('d-none');
            next.classList.add('d-none');
        }
    });
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
