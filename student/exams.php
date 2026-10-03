<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

$exams = $conn->query("SELECT e.*,
        (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) AS q_count,
        (SELECT COALESCE(SUM(points), 0) FROM questions q WHERE q.exam_id = e.id) AS max_marks
    FROM exams e WHERE e.is_custom = 0 ORDER BY e.created_at DESC");
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Manage Exams</h1>
    <a href="/admin/create_exam.php" class="btn btn-primary">Create New Exam</a>
</div>

<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Duration</th>
            <th>Questions</th>
            <th>Marks</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($exams->num_rows == 0): ?>
            <tr><td colspan="7" class="text-muted">No exams yet. Click "Create New Exam".</td></tr>
        <?php endif; ?>
        <?php while ($exam = $exams->fetch_assoc()): ?>
            <tr>
                <td><?php echo $exam['id']; ?></td>
                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                <td><?php echo $exam['duration_minutes']; ?> min</td>
                <td><?php echo $exam['q_count']; ?></td>
                <td><?php echo $exam['max_marks']; ?></td>
                <td>
                    <span class="badge bg-<?php echo $exam['status'] == 'open' ? 'success' : ($exam['status'] == 'closed' ? 'danger' : 'secondary'); ?>">
                        <?php echo ucfirst($exam['status']); ?>
                    </span>
                </td>
                <td>
                    <a href="/admin/edit_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a href="/admin/exam_questions.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-info">Questions</a>
                    <a href="/admin/results.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-dark">Results</a>
                    <form method="POST" action="/admin/delete_exam.php" class="d-inline"
                          onsubmit="return confirm('Delete this exam, its questions and all student results for it?')">
                        <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="id" value="<?php echo $exam['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include __DIR__ . '/../includes/footer.php'; ?>
