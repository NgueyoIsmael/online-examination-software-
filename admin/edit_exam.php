<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: /admin/exams.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = (int)($_POST['duration'] ?? 0);
    $status = $_POST['status'] ?? '';
    $raw_start = trim($_POST['scheduled_start'] ?? '');
    $late_minutes = (int)($_POST['late_minutes'] ?? 0);
    $late_loses_time = isset($_POST['late_loses_time']) ? 1 : 0;
    $max_attempts = (int)($_POST['max_attempts'] ?? 0);
    $pass_mark = (int)($_POST['pass_mark'] ?? 50);
    $shuffle_q = isset($_POST['shuffle_questions']) ? 1 : 0;
    $shuffle_o = isset($_POST['shuffle_options']) ? 1 : 0;
    $code_raw = strtoupper(trim($_POST['access_code'] ?? ''));
    $code = $code_raw === '' ? null : $code_raw;

    $sched = null;
    $date_ok = true;
    if ($raw_start !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $raw_start);
        if ($dt && $dt->format('Y-m-d\TH:i') === $raw_start) {
            $sched = $dt->format('Y-m-d H:i:s');
        } else {
            $date_ok = false;
        }
    }

    if ($title === '') {
        $error = "Title is required.";
    } elseif ($duration < 1 || $duration > 600) {
        $error = "Duration must be between 1 and 600 minutes.";
    } elseif (!in_array($status, ['draft', 'open', 'closed'])) {
        $error = "Invalid status.";
    } elseif (!$date_ok) {
        $error = "The start date and time is not valid.";
    } elseif ($late_minutes < 0 || $late_minutes > $duration) {
        $error = "Late entry minutes must be between 0 and the exam duration.";
    } elseif ($max_attempts < 0 || $max_attempts > 20) {
        $error = "Attempts must be between 0 and 20 (0 = unlimited).";
    } elseif ($pass_mark < 1 || $pass_mark > 100) {
        $error = "Pass mark must be between 1 and 100.";
    } elseif ($code !== null && !preg_match('/^[A-Z0-9]{3,20}$/', $code)) {
        $error = "Access code must be 3 to 20 letters or numbers (no spaces).";
    } else {
        $stmt = $conn->prepare("UPDATE exams SET title = ?, description = ?, duration_minutes = ?, status = ?, scheduled_start = ?, late_minutes = ?, late_loses_time = ?, max_attempts = ?, pass_mark = ?, shuffle_questions = ?, shuffle_options = ?, access_code = ? WHERE id = ?");
        $stmt->bind_param("ssissiiiiiisi", $title, $description, $duration, $status, $sched, $late_minutes, $late_loses_time, $max_attempts, $pass_mark, $shuffle_q, $shuffle_o, $code, $id);
        if ($stmt->execute()) {
            $success = "Exam updated successfully.";
        } else {
            $error = "Error updating exam.";
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

if (!$exam) {
    header("Location: /admin/exams.php");
    exit();
}
$start_value = $exam['scheduled_start'] ? date('Y-m-d\TH:i', strtotime($exam['scheduled_start'])) : '';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<h1>Edit Exam</h1>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo h($success); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label>Title</label>
        <input type="text" name="title" class="form-control" value="<?php echo h($exam['title']); ?>" required>
    </div>
    <div class="mb-3">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="3"><?php echo h($exam['description']); ?></textarea>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label>Duration (minutes)</label>
            <input type="number" name="duration" class="form-control" value="<?php echo (int)$exam['duration_minutes']; ?>" required min="1" max="600">
        </div>
        <div class="col-md-4 mb-3">
            <label>Status</label>
            <select name="status" class="form-select">
                <option value="draft" <?php if($exam['status'] == 'draft') echo 'selected'; ?>>Draft</option>
                <option value="open" <?php if($exam['status'] == 'open') echo 'selected'; ?>>Open</option>
                <option value="closed" <?php if($exam['status'] == 'closed') echo 'selected'; ?>>Closed</option>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label>Pass mark (%)</label>
            <input type="number" name="pass_mark" class="form-control" min="1" max="100" required value="<?php echo (int)$exam['pass_mark']; ?>">
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Schedule (optional)</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-5 mb-3">
                    <label>Exam day, date and time</label>
                    <input type="datetime-local" name="scheduled_start" class="form-control" value="<?php echo h($start_value); ?>">
                    <div class="form-text">Clear it to remove the schedule.</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label>Late entry allowed (minutes)</label>
                    <input type="number" name="late_minutes" class="form-control" min="0" value="<?php echo (int)$exam['late_minutes']; ?>">
                    <div class="form-text">0 = nobody can enter after the start.</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label>Attempts per student</label>
                    <input type="number" name="max_attempts" class="form-control" min="0" max="20" value="<?php echo (int)$exam['max_attempts']; ?>">
                    <div class="form-text">0 = unlimited.</div>
                </div>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="late_loses_time" id="llt" <?php if ($exam['late_loses_time']) echo 'checked'; ?>>
                <label class="form-check-label" for="llt">Late students lose the time they missed (everyone finishes at the same time)</label>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Security and fairness (optional)</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-5 mb-3">
                    <label>Access code</label>
                    <div class="input-group">
                        <input type="text" name="access_code" id="access_code" class="form-control" maxlength="20" placeholder="No code" value="<?php echo h($exam['access_code']); ?>">
                        <button type="button" class="btn btn-outline-secondary" onclick="genCode()">Generate</button>
                    </div>
                    <div class="form-text">Students must type this code to start. Clear it to remove the code.</div>
                </div>
                <div class="col-md-7 mb-3 pt-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="shuffle_questions" id="shq" <?php if ($exam['shuffle_questions']) echo 'checked'; ?>>
                        <label class="form-check-label" for="shq">Shuffle the order of questions for each student</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="shuffle_options" id="sho" <?php if ($exam['shuffle_options']) echo 'checked'; ?>>
                        <label class="form-check-label" for="sho">Shuffle the answer options</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Update</button>
    <a href="/admin/exam_questions.php?id=<?php echo $id; ?>" class="btn btn-info">Questions</a>
    <a href="/admin/exams.php" class="btn btn-secondary">Back</a>
</form>

<script>
function genCode() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', code = '';
    for (var i = 0; i < 6; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
    document.getElementById('access_code').value = code;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
