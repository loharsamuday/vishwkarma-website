<?php
$page_title = "Add/Edit Question";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$q = [
    'question_type' => 'single_mcq',
    'question_text' => '',
    'option_a' => '',
    'option_b' => '',
    'option_c' => '',
    'option_d' => '',
    'option_e' => '',
    'correct_option' => 'A',
    'explanation' => '',
    'short_trick' => '',
    'subject_id' => '',
    'topic_id' => '',
    'difficulty_level' => 'Moderate',
    'marks' => '1.00',
    'negative_marks' => '0.25',
    'language' => 'English',
    'status' => 'active'
];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM mt_questions WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) $q = $row;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $question_type = $_POST['question_type'];
    $question_text = trim($_POST['question_text']);
    $option_a = trim($_POST['option_a']);
    $option_b = trim($_POST['option_b']);
    $option_c = trim($_POST['option_c']);
    $option_d = trim($_POST['option_d']);
    $option_e = trim($_POST['option_e']);
    
    // Handle multi_mcq correct options as JSON string or comma separated
    if ($question_type == 'multi_mcq' && isset($_POST['correct_option_multi'])) {
        $correct_option = implode(',', $_POST['correct_option_multi']);
    } else {
        $correct_option = $_POST['correct_option'];
    }
    
    $explanation = trim($_POST['explanation']);
    $short_trick = trim($_POST['short_trick']);
    $subject_id = $_POST['subject_id'];
    $topic_id = !empty($_POST['topic_id']) ? $_POST['topic_id'] : null;
    $difficulty_level = $_POST['difficulty_level'];
    $marks = $_POST['marks'];
    $negative_marks = $_POST['negative_marks'];
    $language = $_POST['language'];
    $status = $_POST['status'];

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE mt_questions SET question_type=?, question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, option_e=?, correct_option=?, explanation=?, short_trick=?, subject_id=?, topic_id=?, difficulty_level=?, marks=?, negative_marks=?, language=?, status=? WHERE id=?");
        $stmt->execute([$question_type, $question_text, $option_a, $option_b, $option_c, $option_d, $option_e, $correct_option, $explanation, $short_trick, $subject_id, $topic_id, $difficulty_level, $marks, $negative_marks, $language, $status, $id]);
        setFlashMessage('success', 'Question updated successfully.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO mt_questions (question_type, question_text, option_a, option_b, option_c, option_d, option_e, correct_option, explanation, short_trick, subject_id, topic_id, difficulty_level, marks, negative_marks, language, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$question_type, $question_text, $option_a, $option_b, $option_c, $option_d, $option_e, $correct_option, $explanation, $short_trick, $subject_id, $topic_id, $difficulty_level, $marks, $negative_marks, $language, $status]);
        setFlashMessage('success', 'Question added successfully.');
    }
    header("Location: mt_questions.php");
    exit;
}

$subjects = $pdo->query("SELECT * FROM mt_subjects ORDER BY name ASC")->fetchAll();
$topics_raw = $pdo->query("SELECT * FROM mt_topics ORDER BY name ASC")->fetchAll();
$topics_json = json_encode($topics_raw);
?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <h3 class="mb-0 text-dark">
            <i class="fa-solid <?= $id > 0 ? 'fa-edit' : 'fa-plus' ?> text-primary me-2"></i> 
            <?= $id > 0 ? 'Edit Question' : 'Add New Question' ?>
        </h3>
        <a href="mt_questions.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
    </div>
    
    <div class="card border-0 shadow-sm p-4 mb-5">
        <form method="POST" id="questionForm">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Subject *</label>
                    <select name="subject_id" id="subject_id" class="form-select" required onchange="updateTopics()">
                        <option value="">-- Select --</option>
                        <?php foreach($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $q['subject_id'] == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Topic</label>
                    <select name="topic_id" id="topic_id" class="form-select">
                        <option value="">-- Select Topic --</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Question Type</label>
                    <select name="question_type" id="question_type" class="form-select" onchange="toggleQuestionType()">
                        <option value="single_mcq" <?= $q['question_type'] == 'single_mcq' ? 'selected' : '' ?>>Single Correct MCQ</option>
                        <option value="multi_mcq" <?= $q['question_type'] == 'multi_mcq' ? 'selected' : '' ?>>Multiple Correct MCQ</option>
                        <option value="true_false" <?= $q['question_type'] == 'true_false' ? 'selected' : '' ?>>True / False</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Language</label>
                    <select name="language" class="form-select">
                        <option value="English" <?= $q['language'] == 'English' ? 'selected' : '' ?>>English</option>
                        <option value="Hindi" <?= $q['language'] == 'Hindi' ? 'selected' : '' ?>>Hindi</option>
                        <option value="Bilingual" <?= $q['language'] == 'Bilingual' ? 'selected' : '' ?>>Bilingual</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Question Text *</label>
                <textarea name="question_text" class="form-control" rows="4" required><?= htmlspecialchars($q['question_text']) ?></textarea>
                <small class="text-muted">You can use HTML tags (e.g. &lt;b&gt;, &lt;br&gt;) for formatting if needed.</small>
            </div>

            <div id="options_container">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Option A *</label>
                        <textarea name="option_a" class="form-control" rows="2" id="opt_a"><?= htmlspecialchars($q['option_a']) ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Option B *</label>
                        <textarea name="option_b" class="form-control" rows="2" id="opt_b"><?= htmlspecialchars($q['option_b']) ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Option C</label>
                        <textarea name="option_c" class="form-control" rows="2" id="opt_c"><?= htmlspecialchars($q['option_c']) ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Option D</label>
                        <textarea name="option_d" class="form-control" rows="2" id="opt_d"><?= htmlspecialchars($q['option_d']) ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Option E (Optional)</label>
                        <textarea name="option_e" class="form-control" rows="2" id="opt_e"><?= htmlspecialchars($q['option_e']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6" id="single_correct_container">
                    <label class="form-label fw-bold">Correct Option *</label>
                    <select name="correct_option" class="form-select" id="single_correct_dropdown">
                        <option value="A" <?= $q['correct_option'] == 'A' ? 'selected' : '' ?>>Option A</option>
                        <option value="B" <?= $q['correct_option'] == 'B' ? 'selected' : '' ?>>Option B</option>
                        <option value="C" <?= $q['correct_option'] == 'C' ? 'selected' : '' ?>>Option C</option>
                        <option value="D" <?= $q['correct_option'] == 'D' ? 'selected' : '' ?>>Option D</option>
                        <option value="E" <?= $q['correct_option'] == 'E' ? 'selected' : '' ?>>Option E</option>
                    </select>
                </div>
                <div class="col-md-6" id="multi_correct_container" style="display:none;">
                    <label class="form-label fw-bold">Correct Options (Select Multiple) *</label>
                    <div class="d-flex gap-3 mt-2">
                        <?php $selected_multi = explode(',', $q['correct_option']); ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="correct_option_multi[]" value="A" <?= in_array('A', $selected_multi) ? 'checked' : '' ?>> <label class="form-check-label">A</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="correct_option_multi[]" value="B" <?= in_array('B', $selected_multi) ? 'checked' : '' ?>> <label class="form-check-label">B</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="correct_option_multi[]" value="C" <?= in_array('C', $selected_multi) ? 'checked' : '' ?>> <label class="form-check-label">C</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="correct_option_multi[]" value="D" <?= in_array('D', $selected_multi) ? 'checked' : '' ?>> <label class="form-check-label">D</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="correct_option_multi[]" value="E" <?= in_array('E', $selected_multi) ? 'checked' : '' ?>> <label class="form-check-label">E</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Difficulty Level</label>
                    <select name="difficulty_level" class="form-select">
                        <option value="Easy" <?= $q['difficulty_level'] == 'Easy' ? 'selected' : '' ?>>Easy</option>
                        <option value="Moderate" <?= $q['difficulty_level'] == 'Moderate' ? 'selected' : '' ?>>Moderate</option>
                        <option value="Difficult" <?= $q['difficulty_level'] == 'Difficult' ? 'selected' : '' ?>>Difficult</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Marks</label>
                    <input type="number" step="0.25" name="marks" class="form-control" value="<?= htmlspecialchars($q['marks']) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Negative Marks</label>
                    <input type="number" step="0.25" name="negative_marks" class="form-control" value="<?= htmlspecialchars($q['negative_marks']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $q['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $q['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Detailed Explanation</label>
                <textarea name="explanation" class="form-control" rows="4"><?= htmlspecialchars($q['explanation']) ?></textarea>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold">Short Trick / Solution</label>
                <textarea name="short_trick" class="form-control" rows="3"><?= htmlspecialchars($q['short_trick']) ?></textarea>
            </div>

            <hr>
            <div class="text-end">
                <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="fa-solid fa-save"></i> Save Question</button>
            </div>
        </form>
    </div>
</div>

<script>
const topicsData = <?= $topics_json ?>;
const selectedTopicId = '<?= $q['topic_id'] ?>';

function updateTopics() {
    const subjectId = document.getElementById('subject_id').value;
    const topicDropdown = document.getElementById('topic_id');
    topicDropdown.innerHTML = '<option value="">-- Select Topic --</option>';
    
    topicsData.forEach(t => {
        if (t.subject_id == subjectId) {
            let option = document.createElement('option');
            option.value = t.id;
            option.text = t.name;
            if (t.id == selectedTopicId) option.selected = true;
            topicDropdown.appendChild(option);
        }
    });
}

function toggleQuestionType() {
    const qType = document.getElementById('question_type').value;
    const singleContainer = document.getElementById('single_correct_container');
    const multiContainer = document.getElementById('multi_correct_container');
    const optsContainer = document.getElementById('options_container');
    
    if (qType === 'true_false') {
        document.getElementById('opt_a').value = 'True';
        document.getElementById('opt_b').value = 'False';
        document.getElementById('opt_c').value = '';
        document.getElementById('opt_d').value = '';
        document.getElementById('opt_e').value = '';
        optsContainer.style.display = 'none';
        
        singleContainer.style.display = 'block';
        multiContainer.style.display = 'none';
        
        // Only allow A/B in dropdown
        let d = document.getElementById('single_correct_dropdown');
        for(let i=0; i<d.options.length; i++) {
            if(d.options[i].value == 'C' || d.options[i].value == 'D' || d.options[i].value == 'E') {
                d.options[i].disabled = true;
            }
        }
    } else {
        optsContainer.style.display = 'block';
        let d = document.getElementById('single_correct_dropdown');
        for(let i=0; i<d.options.length; i++) {
            d.options[i].disabled = false;
        }
        
        if (qType === 'multi_mcq') {
            singleContainer.style.display = 'none';
            multiContainer.style.display = 'block';
        } else {
            singleContainer.style.display = 'block';
            multiContainer.style.display = 'none';
        }
    }
}

// Initial calls
updateTopics();
toggleQuestionType();
</script>
<?php require_once 'includes/footer.php'; ?>
