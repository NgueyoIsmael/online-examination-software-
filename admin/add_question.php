<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

$exam_id = (int)($_GET['exam_id'] ?? 0);
if (!$exam_id) {
    header("Location: /admin/exams.php");
    exit();
}

// Make sure the exam exists
$stmt = $conn->prepare("SELECT id, title FROM exams WHERE id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
if (!$exam) {
    header("Location: /admin/exams.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type'] ?? '';
    $text = trim($_POST['question_text'] ?? '');
    $points = (int)($_POST['points'] ?? 1);
    $option_texts = $_POST['option_text'] ?? [];
    $allowed = ['single_choice', 'multiple_choice', 'true_false', 'short_answer', 'fill_blank'];

    if (!in_array($type, $allowed)) {
        $error = "Invalid question type.";
    } elseif ($text === '') {
        $error = "Question text is required.";
    } elseif ($points < 1) {
        $error = "Points must be at least 1.";
    } elseif ($type == 'multiple_choice' && empty($_POST['correct_option_indices'])) {
        $error = "Please tick at least one correct answer.";
    } else {
        $stmt = $conn->prepare("INSERT INTO questions (exam_id, type, question_text, points) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $exam_id, $type, $text, $points);
        $stmt->execute();
        $question_id = $stmt->insert_id;

        if ($type == 'single_choice' || $type == 'true_false') {
            $correct_index = $_POST['correct_option_index'] ?? -1;
            foreach ($option_texts as $index => $opt_text) {
                if (trim($opt_text) === '') continue;
                $is_correct = ($index == $correct_index) ? 1 : 0;
                $stmt = $conn->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $question_id, $opt_text, $is_correct);
                $stmt->execute();
            }
        } elseif ($type == 'multiple_choice') {
            $correct_indices = $_POST['correct_option_indices'] ?? [];
            foreach ($option_texts as $index => $opt_text) {
                if (trim($opt_text) === '') continue;
                $is_correct = in_array($index, $correct_indices) ? 1 : 0;
                $stmt = $conn->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $question_id, $opt_text, $is_correct);
                $stmt->execute();
            }
        } else { // short_answer, fill_blank: the correct answer is stored as the one correct option
            $correct_answer = trim($_POST['correct_answer_text'] ?? '');
            $stmt = $conn->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, 1)");
            $stmt->bind_param("is", $question_id, $correct_answer);
            $stmt->execute();
        }

        update_total_marks($conn, $exam_id);

        // "Save and add another" stays on this page
        if (isset($_POST['add_another'])) {
            header("Location: /admin/add_question.php?exam_id=$exam_id&saved=1");
        } else {
            header("Location: /admin/exam_questions.php?id=$exam_id");
        }
        exit();
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<h2>Add Question</h2>
<p class="text-muted">Exam: <?php echo htmlspecialchars($exam['title']); ?></p>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">Question saved. Add another one below.</div>
<?php endif; ?>

<form method="POST" id="questionForm">
    <div class="mb-3">
        <label>Question Type</label>
        <select name="type" id="typeSelect" class="form-select" onchange="updateForm()">
            <option value="single_choice">Single Choice</option>
            <option value="multiple_choice">Select All That Apply</option>
            <option value="true_false">True / False</option>
            <option value="short_answer">Short Answer</option>
            <option value="fill_blank">Fill in the Blank</option>
        </select>
    </div>

    <div class="mb-3">
        <label>Question Text</label>
        <textarea name="question_text" class="form-control" required></textarea>
    </div>

    <div class="mb-3">
        <label>Points</label>
        <input type="number" name="points" class="form-control" value="1" min="1" required>
    </div>

    <div id="optionsContainer" class="mb-3"></div>

    <button type="submit" class="btn btn-primary">Save Question</button>
    <button type="submit" name="add_another" value="1" class="btn btn-success">Save and Add Another</button>
    <a href="/admin/exam_questions.php?id=<?php echo $exam_id; ?>" class="btn btn-secondary">Back</a>
</form>

<script>
function updateForm() {
    const type = document.getElementById('typeSelect').value;
    const container = document.getElementById('optionsContainer');
    container.innerHTML = '';

    if (type === 'single_choice') {
        let html = '<label>Options (Select the correct one)</label>';
        for (let i = 0; i < 4; i++) {
            html += `<div class="input-group mb-2">
                        <div class="input-group-text">
                            <input class="form-check-input mt-0" type="radio" name="correct_option_index" value="${i}" required>
                        </div>
                        <input type="text" name="option_text[]" class="form-control" placeholder="Option ${i+1}" required>
                     </div>`;
        }
        container.innerHTML = html;
    } else if (type === 'multiple_choice') {
        let html = '<label>Options (Tick all correct answers)</label>';
        for (let i = 0; i < 4; i++) {
            html += `<div class="input-group mb-2">
                        <div class="input-group-text">
                            <input class="form-check-input mt-0" type="checkbox" name="correct_option_indices[]" value="${i}">
                        </div>
                        <input type="text" name="option_text[]" class="form-control" placeholder="Option ${i+1}" required>
                     </div>`;
        }
        container.innerHTML = html;
    } else if (type === 'true_false') {
        container.innerHTML = `
            <label>Correct Answer</label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="correct_option_index" value="0" required>
                <label class="form-check-label">True</label>
                <input type="hidden" name="option_text[]" value="True">
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="correct_option_index" value="1" required>
                <label class="form-check-label">False</label>
                <input type="hidden" name="option_text[]" value="False">
            </div>`;
    } else {
        container.innerHTML = `
            <div class="mb-3">
                <label>Correct Answer (Exact Match)</label>
                <input type="text" name="correct_answer_text" class="form-control" required>
            </div>`;
    }
}
updateForm();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
