<?php
// admin/enroll.php (NEW) - choose which students are registered for an exam
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

$exam_id = (int)($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);

$stmt = $conn->prepare("SELECT id, title, status, enrollment_required FROM exams WHERE id = ? AND is_custom = 0");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
if (!$exam) {
    header("Location: /admin/exams.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $required = isset($_POST['enrollment_required']) ? 1 : 0;
    $raw = $_POST['student_ids'] ?? [];
    if (!is_array($raw)) $raw = [];
    $ids = array_values(array_unique(array_filter(array_map('intval', $raw), function ($v) { return $v > 0; })));

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("UPDATE exams SET enrollment_required = ? WHERE id = ?");
        $stmt->bind_param("ii", $required, $exam_id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM exam_enrollments WHERE exam_id = ?");
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();

        // Only real student accounts can be registered
        $ins = $conn->prepare("INSERT IGNORE INTO exam_enrollments (exam_id, student_id) SELECT ?, u.id FROM users u WHERE u.id = ? AND u.role = 'student'");
        foreach ($ids as $sid) {
            $ins->bind_param("ii", $exam_id, $sid);
            $ins->execute();
        }
        $conn->commit();

        $_SESSION['flash'] = ['success', $required
            ? "Saved. " . count($ids) . " student(s) are registered for this exam."
            : "Saved. Registration is OFF, so every student can take this exam."];
    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['flash'] = ['danger', 'Could not save. Please try again.'];
    }
    header("Location: /admin/enroll.php?exam_id=" . $exam_id);
    exit();
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$enrolled = [];
$stmt = $conn->prepare("SELECT student_id, enrolled_at FROM exam_enrollments WHERE exam_id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $enrolled[(int)$r['student_id']] = $r['enrolled_at'];

$students = $conn->query("SELECT id, username, full_name, email FROM users WHERE role = 'student' ORDER BY username");
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1>Registered students: <?php echo h($exam['title']); ?></h1>
    <a href="/admin/exams.php" class="btn btn-secondary">Back to Exams</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo h($flash[0]); ?>"><?php echo h($flash[1]); ?></div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
    <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">

    <div class="card mb-3">
        <div class="card-body">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="enrollment_required" id="req"
                       <?php if ($exam['enrollment_required']) echo 'checked'; ?>>
                <label class="form-check-label" for="req">
                    <strong>Students must register for this exam before they can take it</strong>
                </label>
            </div>
            <div class="form-text">Students register themselves with the "Register for this exam" button on their dashboard.
                You can also tick students here to register them yourself, or untick a student to remove their registration.
                Switch this off if you want every student to be able to take the exam without registering.</div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <input type="text" id="search" class="form-control" style="max-width:300px" placeholder="Search name, username or email">
        <button type="button" class="btn btn-outline-primary" onclick="setAll(true)">Tick all shown</button>
        <button type="button" class="btn btn-outline-secondary" onclick="setAll(false)">Untick all shown</button>
        <span class="ms-auto"><span class="badge bg-primary" id="counter">0</span> registered</span>
    </div>

    <div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
            <tr><th style="width:50px"></th><th>Username</th><th>Full name</th><th>Email</th><th>Registered on</th></tr>
        </thead>
        <tbody>
            <?php if ($students->num_rows == 0): ?>
                <tr><td colspan="5" class="text-muted">No students have registered on the site yet.</td></tr>
            <?php endif; ?>
            <?php while ($st = $students->fetch_assoc()): ?>
                <tr class="srow" data-text="<?php echo h(strtolower($st['username'] . ' ' . $st['full_name'] . ' ' . $st['email'])); ?>">
                    <td><input class="form-check-input sbox" type="checkbox" name="student_ids[]" value="<?php echo $st['id']; ?>"
                               <?php if (isset($enrolled[(int)$st['id']])) echo 'checked'; ?>></td>
                    <td><?php echo h($st['username']); ?></td>
                    <td><?php echo h($st['full_name']); ?></td>
                    <td><?php echo h($st['email']); ?></td>
                    <td><?php echo isset($enrolled[(int)$st['id']]) ? h($enrolled[(int)$st['id']]) : '-'; ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>

    <button type="submit" class="btn btn-success">Save</button>
    <a href="/admin/exams.php" class="btn btn-secondary">Cancel</a>
</form>

<script>
function count() {
    document.getElementById('counter').innerText = document.querySelectorAll('.sbox:checked').length;
}
function setAll(state) {
    document.querySelectorAll('.srow').forEach(function (row) {
        if (row.style.display !== 'none') row.querySelector('.sbox').checked = state;
    });
    count();
}
document.querySelectorAll('.sbox').forEach(function (b) { b.addEventListener('change', count); });
document.getElementById('search').addEventListener('input', function () {
    var q = this.value.toLowerCase().trim();
    document.querySelectorAll('.srow').forEach(function (row) {
        row.style.display = row.getAttribute('data-text').indexOf(q) !== -1 ? '' : 'none';
    });
});
count();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
