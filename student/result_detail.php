<?php
// admin/result_detail.php  (NEW) - one student's answers, with manual marking
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

$attempt_id = (int)($_GET['id'] ?? 0);
if (!$attempt_id) {
    header("Location: /admin/results.php");
    exit();
}

$stmt = $conn->prepare("SELECT se.*, u.username, e.title
                        FROM student_exams se
                        JOIN users u ON u.id = se.student_id
                        JOIN exams e ON e.id = se.exam_id
                        WHERE se.id = ?");
$stmt->bind_param("i", $attempt_id);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();
if (!$attempt) {
    header("Location: /admin/results.php");
    exit();
}

// Load questions with this student's answers
$stmt = $conn->prepare("SELECT q.id, q.type, q.question_text, q.points,
                               sa.id AS answer_id, sa.answer_text, sa.points_awarded
                        FROM questions q
                        LEFT JOIN student_answers sa ON sa.question_id = q.id AND sa.student_exam_id = ?
                        WHERE q.exam_id = ?
                        ORDER BY q.id");
$stmt->bind_param("ii", $attempt_id, $attempt['exam_id']);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Save manual marks
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $max_by_answer = [];
    foreach ($rows as $row) {
        if ($row['answer_id']) $max_by_answer[$row['answer_id']] = (float)$row['points'];
    }
    foreach (($_POST['points'] ?? []) as $answer_id => $value) {
        $answer_id = (int)$answer_id;
        if (!isset($max_by_answer[$answer_id])) continue;
        $value = max(0, min((float)$value, $max_by_answer[$answer_id]));
        $up = $conn->prepare("UPDATE student_answers SET points_awarded = ? WHERE id = ? AND student_exam_id = ?");
        $up->bind_param("dii", $value, $answer_id, $attempt_id);
        $up->execute();
    }
    $up = $conn->prepare("UPDATE student_exams SET score = (SELECT COALESCE(SUM(points_awarded), 0) FROM student_answers WHERE student_exam_id = ?) WHERE id = ?");
    $up->bind_param("ii", $attempt_id, $attempt_id);
    $up->execute();
    header("Location: /admin/result_detail.php?id=$attempt_id&saved=1");
    exit();
}

// Helper: all options of a question (id => [text, is_correct])
function get_options($conn, $question_id) {
    $stmt = $conn->prepare("SELECT id, option_text, is_correct FROM question_options WHERE question_id = ? ORDER BY id");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Helper: turn the stored answer into readable text
function show_answer($raw, $options, $type) {
    if ($raw === null || $raw === '') {
        return '<em class="text-muted">No answer</em>';
    }
    $map = [];
    foreach ($options as $o) $map[(string)$o['id']] = $o['option_text'];

    $decoded = json_decode($raw, true);
    $items = is_array($decoded) ? $decoded : [$raw];
    $out = [];
    foreach ($items as $it) {
        $it = (string)$it;
        if (in_array($type, ['single_choice', 'multiple_choice', 'true_false']) && isset($map[$it])) {
            $out[] = $map[$it];
        } else {
            $out[] = $it;
        }
    }
    return htmlspecialchars(implode(', ', $out));
}

$max_total = 0;
foreach ($rows as $row) $max_total += $row['points'];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Result: <?php echo htmlspecialchars($attempt['username']); ?> - <?php echo htmlspecialchars($attempt['title']); ?></h2>
    <div class="d-flex gap-2">
        <?php if ($attempt['status'] == 'submitted'): ?>
            <a href="/admin/result_export.php?id=<?php echo $attempt_id; ?>&format=pdf" target="_blank" class="btn btn-outline-danger">Download PDF</a>
            <a href="/admin/result_export.php?id=<?php echo $attempt_id; ?>&format=doc" class="btn btn-outline-primary">Download Word</a>
        <?php endif; ?>
        <a href="/admin/results.php?exam_id=<?php echo $attempt['exam_id']; ?>" class="btn btn-secondary">Back to Results</a>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">Marks saved and score updated.</div>
<?php endif; ?>

<p>
    Status: <strong><?php echo ucfirst(str_replace('_', ' ', $attempt['status'])); ?></strong> |
    Score: <strong><?php echo $attempt['score'] . ' / ' . $max_total; ?></strong>
    <?php if ($attempt['end_time']): ?>| Finished: <?php echo htmlspecialchars($attempt['end_time']); ?><?php endif; ?>
</p>

<form method="POST">
    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">

    <?php $n = 0; foreach ($rows as $row): $n++;
        $options = get_options($conn, $row['id']);
        $correct = [];
        foreach ($options as $o) if ($o['is_correct']) $correct[] = $o['option_text'];
    ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <h5><?php echo $n . '. ' . htmlspecialchars($row['question_text']); ?></h5>
                    <small class="text-muted"><?php echo htmlspecialchars($row['type']); ?> | <?php echo $row['points']; ?> pts</small>
                </div>
                <p class="mb-1"><strong>Student answer:</strong> <?php echo show_answer($row['answer_text'], $options, $row['type']); ?></p>
                <p class="mb-2 text-success"><strong>Correct answer:</strong> <?php echo htmlspecialchars(implode(', ', $correct)); ?></p>
                <?php if ($row['answer_id']): ?>
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0">Marks given:</label>
                        <input type="number" step="0.5" min="0" max="<?php echo $row['points']; ?>"
                               name="points[<?php echo $row['answer_id']; ?>]"
                               value="<?php echo $row['points_awarded']; ?>"
                               class="form-control" style="width:100px">
                        <span>/ <?php echo $row['points']; ?></span>
                    </div>
                <?php else: ?>
                    <small class="text-muted">Not answered (0 marks).</small>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (count($rows) > 0): ?>
        <button type="submit" class="btn btn-primary">Save Marks</button>
    <?php endif; ?>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
