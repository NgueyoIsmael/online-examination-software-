<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$exam_id = $_GET['id'] ?? null;
if (!$exam_id) {
    header("Location: /admin/exams.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

if (!$exam) {
    die("Exam not found");
}

$stmt = $conn->prepare("SELECT * FROM questions WHERE exam_id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$questions = $stmt->get_result();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<h2>Questions for: <?php echo htmlspecialchars($exam['title']); ?></h2>
<a href="/admin/add_question.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-success mb-3">Add Question</a>
<a href="/admin/exams.php" class="btn btn-secondary mb-3">Back to Exams</a>

<div class="list-group">
    <?php while($q = $questions->fetch_assoc()): ?>
        <div class="list-group-item">
            <div class="d-flex w-100 justify-content-between">
                <h5 class="mb-1"><?php echo htmlspecialchars($q['question_text']); ?></h5>
                <small>Type: <?php echo $q['type']; ?> | Points: <?php echo $q['points']; ?></small>
            </div>
            <p class="mb-1">
                <?php
                // Fetch options if applicable
                $q_id = $q['id'];
                $opt_stmt = $conn->prepare("SELECT * FROM question_options WHERE question_id = ?");
                $opt_stmt->bind_param("i", $q_id);
                $opt_stmt->execute();
                $options = $opt_stmt->get_result();
                while($opt = $options->fetch_assoc()) {
                    echo "<div>- " . htmlspecialchars($opt['option_text']) . ($opt['is_correct'] ? " (Correct)" : "") . "</div>";
                }
                ?>
            </p>
            <a href="/admin/delete_question.php?id=<?php echo $q['id']; ?>&exam_id=<?php echo $exam_id; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this question?');">Delete</a>
        </div>
    <?php endwhile; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
