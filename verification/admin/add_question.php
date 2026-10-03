<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$exam_id = $_GET['exam_id'] ?? null;
if (!$exam_id) {
    die("Exam ID missing");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type'];
    $text = $_POST['question_text'];
    $points = $_POST['points'];

    // Insert Question
    $stmt = $conn->prepare("INSERT INTO questions (exam_id, type, question_text, points) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issi", $exam_id, $type, $text, $points);
    $stmt->execute();
    $question_id = $stmt->insert_id;

    // Handle Options based on type
    if (in_array($type, ['single_choice', 'multiple_choice', 'true_false'])) {
        $option_texts = $_POST['option_text'] ?? [];
        $is_corrects = $_POST['is_correct'] ?? []; // For checkbox (multiple choice)
        // For radio (single choice/true false), usually one value is sent.
        
        // However, building a dynamic form for this is tricky in pure PHP/HTML without complex JS.
        // Let's assume the form sends arrays.
        
        // For Single Choice & True/False: 'correct_option' might be the index of the correct option.
        // For Multiple Choice: 'is_correct' array where keys match indices.

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
        }
    } 
    // For short_answer and fill_blank, we might store the correct answer as an option or just rely on manual grading?
    // Usually auto-grading needs the correct answer. I'll store the correct answer as a "correct" option.
    elseif ($type == 'short_answer' || $type == 'fill_blank') {
        $correct_answer = $_POST['correct_answer_text'] ?? '';
        $stmt = $conn->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, 1)");
        $stmt->bind_param("is", $question_id, $correct_answer);
        $stmt->execute();
    }

    header("Location: /admin/exam_questions.php?id=$exam_id");
    exit();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<h2>Add Question</h2>

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
        <input type="number" name="points" class="form-control" value="1" required>
    </div>

    <!-- Options Container -->
    <div id="optionsContainer" class="mb-3">
        <!-- Dynamic content -->
    </div>

    <button type="submit" class="btn btn-primary">Save Question</button>
</form>

<script>
function updateForm() {
    const type = document.getElementById('typeSelect').value;
    const container = document.getElementById('optionsContainer');
    container.innerHTML = '';

    if (type === 'single_choice') {
        let html = '<label>Options (Select the correct one)</label>';
        for(let i=0; i<4; i++) {
            html += `<div class="input-group mb-2">
                        <div class="input-group-text">
                            <input class="form-check-input mt-0" type="radio" name="correct_option_index" value="${i}" required>
                        </div>
                        <input type="text" name="option_text[]" class="form-control" placeholder="Option ${i+1}" required>
                     </div>`;
        }
        container.innerHTML = html;
    } else if (type === 'multiple_choice') {
        let html = '<label>Options (Check all correct answers)</label>';
        for(let i=0; i<4; i++) {
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
            </div>
        `;
    } else if (type === 'short_answer' || type === 'fill_blank') {
        container.innerHTML = `
            <div class="mb-3">
                <label>Correct Answer (Exact Match)</label>
                <input type="text" name="correct_answer_text" class="form-control" required>
            </div>
        `;
    }
}

// Init
updateForm();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
