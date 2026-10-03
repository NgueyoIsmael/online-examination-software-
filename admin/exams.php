<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$exams = $conn->query("SELECT e.*,
        (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) AS q_count,
        (SELECT COALESCE(SUM(points), 0) FROM questions q WHERE q.exam_id = e.id) AS max_marks,
        (SELECT COUNT(*) FROM exam_enrollments en WHERE en.exam_id = e.id) AS enrolled_count
    FROM exams e WHERE e.is_custom = 0 ORDER BY (e.scheduled_start IS NULL), e.scheduled_start DESC, e.created_at DESC");
$now = time();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Manage Exams</h1>
    <a href="/admin/create_exam.php" class="btn btn-primary">Create New Exam</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo h($flash[0]); ?>"><?php echo h($flash[1]); ?></div>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-striped align-middle">
    <thead>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Schedule</th>
            <th>Duration</th>
            <th>Questions</th>
            <th>Marks</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($exams->num_rows == 0): ?>
            <tr><td colspan="8" class="text-muted">No exams yet. Click "Create New Exam".</td></tr>
        <?php endif; ?>
        <?php while ($exam = $exams->fetch_assoc()): ?>
            <tr>
                <td><?php echo $exam['id']; ?></td>
                <td>
                    <?php echo h($exam['title']); ?>
                    <?php if (!empty($exam['access_code'])): ?>
                        <br><small class="text-muted">Access code: <strong><?php echo h($exam['access_code']); ?></strong></small>
                    <?php endif; ?>
                    <?php if ($exam['shuffle_questions'] || $exam['shuffle_options']): ?>
                        <br><small class="text-muted">Shuffled</small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (empty($exam['scheduled_start'])): ?>
                        <span class="text-muted">Anytime</span>
                    <?php else:
                        $st = strtotime($exam['scheduled_start']);
                        $en = $st + $exam['duration_minutes'] * 60;
                        if ($now < $st)      { $lbl = 'Upcoming'; $cls = 'info'; }
                        elseif ($now <= $en) { $lbl = 'Live now';  $cls = 'success'; }
                        else                 { $lbl = 'Ended';     $cls = 'secondary'; }
                    ?>
                        <?php echo h(format_exam_time($st)); ?>
                        <span class="badge bg-<?php echo $cls; ?>"><?php echo $lbl; ?></span><br>
                        <small class="text-muted">
                            <?php echo $exam['late_minutes'] > 0 ? 'Late entry: ' . (int)$exam['late_minutes'] . ' min' : 'No late entry'; ?>
                        </small>
                    <?php endif; ?>
                </td>
                <td><?php echo $exam['duration_minutes']; ?> min</td>
                <td><?php echo $exam['q_count']; ?></td>
                <td><?php echo $exam['max_marks']; ?></td>
                <td>
                    <span class="badge bg-<?php echo $exam['status'] == 'open' ? 'success' : ($exam['status'] == 'closed' ? 'danger' : 'secondary'); ?>">
                        <?php echo ucfirst($exam['status']); ?>
                    </span>
                </td>
                <td class="text-nowrap">
                    <a href="/admin/edit_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a href="/admin/exam_questions.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-info">Questions</a>
                    <a href="/admin/results.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-dark">Results</a>
                    <a href="/admin/enroll.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-secondary"><?php echo $exam['enrollment_required'] ? 'Registered: ' . (int)$exam['enrolled_count'] : 'No registration'; ?></a>
                    <?php if ($exam['status'] == 'open'): ?>
                        <form method="POST" action="/admin/notify_exam.php" class="d-inline"
                              onsubmit="return confirm('Send an email about this exam to ALL students?')">
                            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="id" value="<?php echo $exam['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary"
                                    title="<?php echo $exam['notified_at'] ? 'Last emailed: ' . h($exam['notified_at']) : 'Not emailed yet'; ?>">
                                <?php echo $exam['notified_at'] ? 'Email again' : 'Email students'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
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
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
