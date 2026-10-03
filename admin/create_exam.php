<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

$f = ['title' => '', 'description' => '', 'duration' => 60, 'status' => 'draft',
      'scheduled_start' => '', 'late_minutes' => 0, 'late_loses_time' => 1,
      'max_attempts' => 1, 'pass_mark' => 50,
      'shuffle_questions' => 0, 'shuffle_options' => 0, 'access_code' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $f['title'] = trim($_POST['title'] ?? '');
    $f['description'] = trim($_POST['description'] ?? '');
    $f['duration'] = (int)($_POST['duration'] ?? 0);
    $f['status'] = $_POST['status'] ?? '';
    $f['scheduled_start'] = trim($_POST['scheduled_start'] ?? '');
    $f['late_minutes'] = (int)($_POST['late_minutes'] ?? 0);
    $f['late_loses_time'] = isset($_POST['late_loses_time']) ? 1 : 0;
    $f['max_attempts'] = (int)($_POST['max_attempts'] ?? 0);
    $f['pass_mark'] = (int)($_POST['pass_mark'] ?? 50);
    $f['shuffle_questions'] = isset($_POST['shuffle_questions']) ? 1 : 0;
    $f['shuffle_options'] = isset($_POST['shuffle_options']) ? 1 : 0;
    $f['access_code'] = strtoupper(trim($_POST['access_code'] ?? ''));
    $created_by = $_SESSION['user_id'];

    $sched = null;
    $date_ok = true;
    if ($f['scheduled_start'] !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $f['scheduled_start']);
        if ($dt && $dt->format('Y-m-d\TH:i') === $f['scheduled_start']) {
            $sched = $dt->format('Y-m-d H:i:s');
        } else {
            $date_ok = false;
        }
    }
    $code = $f['access_code'] === '' ? null : $f['access_code'];

    if ($f['title'] === '') {
        $error = "Title is required.";
    } elseif ($f['duration'] < 1 || $f['duration'] > 600) {
        $error = "Duration must be between 1 and 600 minutes.";
    } elseif (!in_array($f['status'], ['draft', 'open', 'closed'])) {
        $error = "Invalid status.";
    } elseif (!$date_ok) {
        $error = "The start date and time is not valid.";
    } elseif ($f['late_minutes'] < 0 || $f['late_minutes'] > $f['duration']) {
        $error = "Late entry minutes must be between 0 and the exam duration.";
    } elseif ($f['max_attempts'] < 0 || $f['max_attempts'] > 20) {
        $error = "Attempts must be between 0 and 20 (0 = unlimited).";
    } elseif ($f['pass_mark'] < 1 || $f['pass_mark'] > 100) {
        $error = "Pass mark must be between 1 and 100.";
    } elseif ($code !== null && !preg_match('/^[A-Z0-9]{3,20}$/', $code)) {
        $error = "Access code must be 3 to 20 letters or numbers (no spaces).";
    } else {
        $stmt = $conn->prepare("INSERT INTO exams (title, description, duration_minutes, status, created_by, scheduled_start, late_minutes, late_loses_time, max_attempts, pass_mark, shuffle_questions, shuffle_options, access_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisisiiiiiis", $f['title'], $f['description'], $f['duration'], $f['status'], $created_by, $sched, $f['late_minutes'], $f['late_loses_time'], $f['max_attempts'], $f['pass_mark'], $f['shuffle_questions'], $f['shuffle_options'], $code);
        if ($stmt->execute()) {
            header("Location: /admin/exam_questions.php?id=" . $stmt->insert_id);
            exit();
        } else {
            $error = "Error creating exam.";
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<h1>Create Exam</h1>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label>Title</label>
        <input type="text" name="title" class="form-control" required value="<?php echo h($f['title']); ?>">
    </div>
    <div class="mb-3">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="3"><?php echo h($f['description']); ?></textarea>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label>Duration (minutes)</label>
            <input type="number" name="duration" class="form-control" required min="1" max="600" value="<?php echo (int)$f['duration']; ?>">
        </div>
        <div class="col-md-4 mb-3">
            <label>Status</label>
            <select name="status" class="form-select">
                <?php foreach (['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Closed'] as $k => $v): ?>
                    <option value="<?php echo $k; ?>" <?php if ($f['status'] == $k) echo 'selected'; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Students only see exams that are Open.</div>
        </div>
        <div class="col-md-4 mb-3">
            <label>Pass mark (%)</label>
            <input type="number" name="pass_mark" class="form-control" min="1" max="100" required value="<?php echo (int)$f['pass_mark']; ?>">
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Schedule (optional)</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-5 mb-3">
                    <label>Exam day, date and time</label>
                    <input type="datetime-local" name="scheduled_start" class="form-control" value="<?php echo h($f['scheduled_start']); ?>">
                    <div class="form-text">Leave empty if students may start any time while the exam is Open.</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label>Late entry allowed (minutes)</label>
                    <input type="number" name="late_minutes" class="form-control" min="0" value="<?php echo (int)$f['late_minutes']; ?>">
                    <div class="form-text">0 = nobody can enter after the start.</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label>Attempts per student</label>
                    <input type="number" name="max_attempts" class="form-control" min="0" max="20" value="<?php echo (int)$f['max_attempts']; ?>">
                    <div class="form-text">0 = unlimited.</div>
                </div>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="late_loses_time" id="llt" <?php if ($f['late_loses_time']) echo 'checked'; ?>>
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
                        <input type="text" name="access_code" id="access_code" class="form-control" maxlength="20" placeholder="No code" value="<?php echo h($f['access_code']); ?>">
                        <button type="button" class="btn btn-outline-secondary" onclick="genCode()">Generate</button>
                    </div>
                    <div class="form-text">Students must type this code to start. Tell it to them in the exam room.</div>
                </div>
                <div class="col-md-7 mb-3 pt-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="shuffle_questions" id="shq" <?php if ($f['shuffle_questions']) echo 'checked'; ?>>
                        <label class="form-check-label" for="shq">Shuffle the order of questions for each student</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="shuffle_options" id="sho" <?php if ($f['shuffle_options']) echo 'checked'; ?>>
                        <label class="form-check-label" for="sho">Shuffle the answer options</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-success">Create and Add Questions</button>
    <a href="/admin/exams.php" class="btn btn-secondary">Cancel</a>
</form>

<script>
function genCode() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', code = '';
    for (var i = 0; i < 6; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
    document.getElementById('access_code').value = code;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
