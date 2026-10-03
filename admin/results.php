<?php
// admin/results.php - list of all exam attempts, filter by exam or student
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

$exam_id = (int)($_GET['exam_id'] ?? 0);
$student_id = (int)($_GET['student_id'] ?? 0);

$where = [];
$types = '';
$params = [];
if ($exam_id)    { $where[] = 'se.exam_id = ?';    $types .= 'i'; $params[] = $exam_id; }
if ($student_id) { $where[] = 'se.student_id = ?'; $types .= 'i'; $params[] = $student_id; }

$sql = "SELECT se.id, se.score, se.status, se.end_time, se.is_late, se.minutes_late,
               u.username, e.title, e.pass_mark,
               (SELECT COALESCE(SUM(points), 0) FROM questions q WHERE q.exam_id = e.id) AS max_marks
        FROM student_exams se
        JOIN users u ON u.id = se.student_id
        JOIN exams e ON e.id = se.exam_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY se.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$results = $stmt->get_result();

$exam_list = $conn->query("SELECT id, title FROM exams WHERE is_custom = 0 ORDER BY title");

$export_query = http_build_query(array_filter(['exam_id' => $exam_id, 'student_id' => $student_id]));
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1>Results</h1>
    <div class="d-flex gap-2">
        <a href="/admin/results_export.php<?php echo $export_query ? '?' . $export_query : ''; ?>" class="btn btn-success">Export to Excel (CSV)</a>
        <a href="/admin/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-5">
        <select name="exam_id" class="form-select" onchange="this.form.submit()">
            <option value="0">All exams</option>
            <?php while ($ex = $exam_list->fetch_assoc()): ?>
                <option value="<?php echo $ex['id']; ?>" <?php if ($exam_id == $ex['id']) echo 'selected'; ?>>
                    <?php echo h($ex['title']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <?php if ($student_id): ?>
        <div class="col-md-4">
            <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
            <a href="/admin/results.php<?php echo $exam_id ? '?exam_id=' . $exam_id : ''; ?>" class="btn btn-outline-secondary">Clear student filter</a>
        </div>
    <?php endif; ?>
</form>

<div class="table-responsive">
<table class="table table-striped align-middle">
    <thead>
        <tr>
            <th>Student</th>
            <th>Exam</th>
            <th>Score</th>
            <th>Percent</th>
            <th>Result</th>
            <th>Punctuality</th>
            <th>Status</th>
            <th>Finished</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if ($results->num_rows == 0): ?>
            <tr><td colspan="9" class="text-muted">No results found.</td></tr>
        <?php endif; ?>
        <?php while ($r = $results->fetch_assoc()):
            $pct_num = $r['max_marks'] > 0 ? round(($r['score'] / $r['max_marks']) * 100) : 0;
            $pm = (int)$r['pass_mark'] ?: 50;
        ?>
            <tr>
                <td><?php echo h($r['username']); ?></td>
                <td><?php echo h($r['title']); ?></td>
                <td><?php echo fmt_num($r['score']) . ' / ' . fmt_num($r['max_marks']); ?></td>
                <td><?php echo $r['max_marks'] > 0 ? $pct_num . '%' : '-'; ?></td>
                <td>
                    <?php if ($r['status'] == 'submitted'): ?>
                        <span class="badge bg-<?php echo $pct_num >= $pm ? 'success' : 'danger'; ?>"><?php echo $pct_num >= $pm ? 'Passed' : 'Failed'; ?></span>
                    <?php else: ?>-<?php endif; ?>
                </td>
                <td>
                    <?php if ($r['is_late']): ?>
                        <span class="badge bg-warning text-dark">Late <?php echo (int)$r['minutes_late']; ?> min</span>
                    <?php else: ?>
                        <span class="badge bg-light text-dark border">On time</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge bg-<?php echo $r['status'] == 'submitted' ? 'success' : ($r['status'] == 'in_progress' ? 'warning text-dark' : 'secondary'); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $r['status'])); ?>
                    </span>
                </td>
                <td><?php echo $r['end_time'] ? h($r['end_time']) : '-'; ?></td>
                <td><a href="/admin/result_detail.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-primary">View</a></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
