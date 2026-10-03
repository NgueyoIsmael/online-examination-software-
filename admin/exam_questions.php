<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

$exam_id = (int)($_GET['id'] ?? 0);
if (!$exam_id) {
    header("Location: /admin/exams.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

if (!$exam) {
    header("Location: /admin/exams.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY id");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$questions = $stmt->get_result();

$total_points = 0;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<h2>Questions for: <?php echo htmlspecialchars($exam['title']); ?>
    <span class="badge bg-<?php echo $exam['status'] == 'open' ? 'success' : ($exam['status'] == 'closed' ? 'danger' : 'secondary'); ?> fs-6">
        <?php echo ucfirst($exam['status']); ?>
    </span>
</h2>
<a href="/admin/add_question.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-success mb-3">Add Question</a>
<a href="/admin/edit_exam.php?id=<?php echo $exam_id; ?>" class="btn btn-warning mb-3">Edit Exam</a>
<a href="/admin/exams.php" class="btn btn-secondary mb-3">Back to Exams</a>

<div class="list-group mb-3">
    <?php if ($questions->num_rows == 0): ?>
        <div class="list-group-item text-muted">No questions yet. Click "Add Question".</div>
    <?php endif; ?>
    <?php $n = 0; while($q = $questions->fetch_assoc()): $n++; $total_points += $q['points']; ?>
        <div class="list-group-item">
            <div class="d-flex w-100 justify-content-between">
                <h5 class="mb-1"><?php echo $n . '. ' . htmlspecialchars($q['question_text']); ?></h5>
                <small>Type: <?php echo htmlspecialchars($q['type']); ?> | Points: <?php echo $q['points']; ?></small>
            </div>
            <div class="mb-2">
                <?php
                $q_id = $q['id'];
                $opt_stmt = $conn->prepare("SELECT * FROM question_options WHERE question_id = ?");
                $opt_stmt->bind_param("i", $q_id);
                $opt_stmt->execute();
                $options = $opt_stmt->get_result();
                while($opt = $options->fetch_assoc()) {
                    echo "<div>- " . htmlspecialchars($opt['option_text']) . ($opt['is_correct'] ? " <strong class='text-success'>(Correct)</strong>" : "") . "</div>";
                }
                ?>
            </div>
            <form method="POST" action="/admin/delete_question.php" class="d-inline"
                  onsubmit="return confirm('Delete this question?');">
                <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
        </div>
    <?php endwhile; ?>
</div>

<p class="text-muted">Total marks for this exam: <strong><?php echo $total_points; ?></strong></p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
