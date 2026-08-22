<?php
// idioms-phrasal-verbs/practice.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$page_title = "Practice Idioms & Phrasal Verbs - MCQ Quiz";
$seo_desc = "Test your knowledge of English idioms and phrasal verbs with our interactive practice questions and mock tests.";
require_once '../includes/header.php';

// Build filter for practice questions
$type = isset($_GET['type']) ? $_GET['type'] : '';
$where = "status = 'Published'";
$params = [];

if ($type && in_array($type, ['idiom', 'phrasal_verb', 'general'])) {
    $where .= " AND content_type = ?";
    $params[] = $type;
}

// Just fetch 10 random questions for a practice session
$stmt = $pdo->prepare("SELECT * FROM practice_questions WHERE $where ORDER BY RAND() LIMIT 10");
$stmt->execute($params);
$questions = $stmt->fetchAll();
?>

<div class="bg-light py-4 border-bottom mb-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php">Vocabulary</a></li>
                <li class="breadcrumb-item active" aria-current="page">Practice</li>
            </ol>
        </nav>
        <h1 class="fw-bold">Practice Quiz</h1>
        <p class="text-muted mb-0">Test your understanding of idioms and phrasal verbs.</p>
    </div>
</div>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between mb-4">
                <div>
                    <a href="?type=" class="btn btn-sm <?= empty($type) ? 'btn-primary' : 'btn-outline-primary' ?>">Mix Test</a>
                    <a href="?type=idiom" class="btn btn-sm <?= $type == 'idiom' ? 'btn-primary' : 'btn-outline-primary' ?>">Idioms Only</a>
                    <a href="?type=phrasal_verb" class="btn btn-sm <?= $type == 'phrasal_verb' ? 'btn-primary' : 'btn-outline-primary' ?>">Phrasal Verbs Only</a>
                </div>
                <button id="restartBtn" class="btn btn-sm btn-outline-danger d-none" onclick="location.reload();"><i class="fas fa-redo"></i> Restart</button>
            </div>

            <?php if(count($questions) > 0): ?>
                <div class="card border-0 shadow-sm" id="quizContainer">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-muted" id="questionCounter">Question 1 of <?= count($questions) ?></h5>
                            <span class="badge bg-warning text-dark fs-6" id="scoreDisplay">Score: 0</span>
                        </div>
                        <div class="progress mt-3" style="height: 8px;">
                            <div class="progress-bar bg-primary" id="quizProgress" role="progressbar" style="width: <?= (1/count($questions))*100 ?>%" aria-valuenow="10" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php foreach($questions as $index => $q): ?>
                            <div class="question-slide <?= $index == 0 ? 'active' : 'd-none' ?>" id="slide-<?= $index ?>">
                                <h4 class="mb-4 fw-bold lh-base"><?= htmlspecialchars($q['question']) ?></h4>
                                
                                <div class="options-group">
                                    <button class="btn btn-outline-secondary w-100 text-start mb-3 p-3 option-btn" data-qindex="<?= $index ?>" data-value="A" data-correct="<?= $q['correct_answer'] ?>">
                                        <span class="fw-bold me-2">A.</span> <?= htmlspecialchars($q['option_a']) ?>
                                    </button>
                                    <button class="btn btn-outline-secondary w-100 text-start mb-3 p-3 option-btn" data-qindex="<?= $index ?>" data-value="B" data-correct="<?= $q['correct_answer'] ?>">
                                        <span class="fw-bold me-2">B.</span> <?= htmlspecialchars($q['option_b']) ?>
                                    </button>
                                    <button class="btn btn-outline-secondary w-100 text-start mb-3 p-3 option-btn" data-qindex="<?= $index ?>" data-value="C" data-correct="<?= $q['correct_answer'] ?>">
                                        <span class="fw-bold me-2">C.</span> <?= htmlspecialchars($q['option_c']) ?>
                                    </button>
                                    <button class="btn btn-outline-secondary w-100 text-start mb-3 p-3 option-btn" data-qindex="<?= $index ?>" data-value="D" data-correct="<?= $q['correct_answer'] ?>">
                                        <span class="fw-bold me-2">D.</span> <?= htmlspecialchars($q['option_d']) ?>
                                    </button>
                                </div>

                                <div class="explanation-box d-none mt-4 p-3 rounded bg-light border-start border-4 border-info">
                                    <h6 class="fw-bold text-info"><i class="fas fa-info-circle me-1"></i> Explanation</h6>
                                    <p class="mb-1"><?= htmlspecialchars($q['explanation']) ?></p>
                                    <?php if(!empty($q['hindi_explanation'])): ?>
                                        <p class="mb-0 text-muted small"><i class="fas fa-language me-1"></i> <?= htmlspecialchars($q['hindi_explanation']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Result Slide -->
                        <div class="question-slide d-none text-center py-5" id="slide-result">
                            <i class="fas fa-trophy fa-5x text-warning mb-4"></i>
                            <h2 class="fw-bold">Quiz Completed!</h2>
                            <h4 class="text-muted mb-4">Your Score: <span id="finalScore" class="text-primary">0</span> / <?= count($questions) ?></h4>
                            <div class="d-flex justify-content-center gap-3">
                                <button class="btn btn-primary" onclick="location.reload();">Take Another Quiz</button>
                                <a href="index.php" class="btn btn-outline-secondary">Back to Home</a>
                            </div>
                        </div>

                    </div>
                    <div class="card-footer bg-white text-end border-top-0 pb-4 pe-4">
                        <button id="nextBtn" class="btn btn-primary px-4 d-none">Next Question <i class="fas fa-arrow-right ms-1"></i></button>
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const totalQuestions = <?= count($questions) ?>;
                        let currentSlide = 0;
                        let score = 0;

                        const optionBtns = document.querySelectorAll('.option-btn');
                        const nextBtn = document.getElementById('nextBtn');
                        const scoreDisplay = document.getElementById('scoreDisplay');
                        const questionCounter = document.getElementById('questionCounter');
                        const quizProgress = document.getElementById('quizProgress');
                        const restartBtn = document.getElementById('restartBtn');

                        optionBtns.forEach(btn => {
                            btn.addEventListener('click', function() {
                                const qIndex = this.getAttribute('data-qindex');
                                const selectedValue = this.getAttribute('data-value');
                                const correctValue = this.getAttribute('data-correct');
                                const slide = document.getElementById('slide-' + qIndex);
                                
                                // Disable all buttons in this slide
                                const slideBtns = slide.querySelectorAll('.option-btn');
                                slideBtns.forEach(b => b.classList.add('disabled', 'opacity-75'));
                                
                                // Check answer
                                if (selectedValue === correctValue) {
                                    this.classList.remove('btn-outline-secondary');
                                    this.classList.add('btn-success', 'text-white');
                                    this.innerHTML += ' <i class="fas fa-check-circle float-end mt-1"></i>';
                                    score++;
                                    scoreDisplay.innerText = 'Score: ' + score;
                                } else {
                                    this.classList.remove('btn-outline-secondary');
                                    this.classList.add('btn-danger', 'text-white');
                                    this.innerHTML += ' <i class="fas fa-times-circle float-end mt-1"></i>';
                                    
                                    // Highlight correct answer
                                    const correctBtn = slide.querySelector(`[data-value="${correctValue}"]`);
                                    correctBtn.classList.remove('btn-outline-secondary');
                                    correctBtn.classList.add('btn-success', 'text-white');
                                }

                                // Show explanation
                                const explanation = slide.querySelector('.explanation-box');
                                if(explanation) explanation.classList.remove('d-none');
                                
                                // Show next button
                                nextBtn.classList.remove('d-none');
                                restartBtn.classList.remove('d-none');
                            });
                        });

                        nextBtn.addEventListener('click', function() {
                            document.getElementById('slide-' + currentSlide).classList.add('d-none');
                            document.getElementById('slide-' + currentSlide).classList.remove('active');
                            
                            currentSlide++;
                            
                            if (currentSlide < totalQuestions) {
                                document.getElementById('slide-' + currentSlide).classList.remove('d-none');
                                document.getElementById('slide-' + currentSlide).classList.add('active');
                                questionCounter.innerText = 'Question ' + (currentSlide + 1) + ' of ' + totalQuestions;
                                quizProgress.style.width = ((currentSlide + 1) / totalQuestions) * 100 + '%';
                                nextBtn.classList.add('d-none');
                            } else {
                                // Show result
                                document.getElementById('slide-result').classList.remove('d-none');
                                document.getElementById('finalScore').innerText = score;
                                questionCounter.innerText = 'Completed';
                                quizProgress.style.width = '100%';
                                nextBtn.classList.add('d-none');
                                scoreDisplay.classList.add('d-none');
                            }
                        });
                    });
                </script>

            <?php else: ?>
                <div class="alert alert-warning text-center py-5">
                    <h4>No questions available</h4>
                    <p>Check back later for new practice questions.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
