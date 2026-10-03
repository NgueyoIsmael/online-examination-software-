<?php
// student/dashboard.php  (REPLACES the old file)
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}
if ($_SESSION['role'] !== 'student') {
    header("Location: /admin/dashboard.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/exam_helpers.php';

$student_id = (int)$_SESSION['user_id'];

// Announcements from the admin
$announcements = $conn->query("SELECT title, message, created_at FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5");

// Open exams: scheduled ones first (soonest first), then the rest
$stmt = $conn->prepare("SELECT e.* FROM exams e WHERE e.status = 'open' AND e.is_custom = 0
                        ORDER BY (e.scheduled_start IS NULL), e.scheduled_start ASC, e.created_at DESC");
$stmt->execute();
$available_exams = $stmt->get_result();

// Exams this student has registered for
$registered_for = [];
$stmt = $conn->prepare("SELECT exam_id FROM exam_enrollments WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $registered_for[(int)$row['exam_id']] = true;
}

// How many attempts this student already used per exam, and which are still running
$counts = [];
$stmt = $conn->prepare("SELECT exam_id, COUNT(*) AS c, SUM(status = 'in_progress') AS ip FROM student_exams WHERE student_id = ? GROUP BY exam_id");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $counts[$row['exam_id']] = $row;
}

// My attempts
$stmt = $conn->prepare("SELECT se.*, e.title FROM student_exams se JOIN exams e ON se.exam_id = e.id WHERE se.student_id = ? ORDER BY se.created_at DESC");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$my_exams = $stmt->get_result();

$err = $_GET['err'] ?? '';
$messages = [
    'unavailable' => ['danger',  'That exam is not available.'],
    'empty'       => ['warning', 'That exam has no questions yet.'],
    'upcoming'    => ['warning', 'That exam has not started yet.'],
    'closed'      => ['danger',  'Entry to that exam is closed. You can no longer start it.'],
    'attempts'    => ['warning', 'You have used all your attempts for that exam.'],
    'code'        => ['danger',  'Wrong access code. Please ask your teacher for the correct code.'],
    'locked'      => ['danger',  'Too many wrong codes. Please wait 5 minutes and try again.'],
    'notenrolled' => ['danger',  'You must register for that exam first. Click "Register for this exam" on the exam card.'],
    'registered'  => ['success', 'You are now registered for that exam.'],
];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="row mb-3">
    <div class="col-md-12 d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h1 class="display-5 text-primary">Student Dashboard</h1>
            <p class="lead mb-0">Welcome back, <?php echo h($_SESSION['username']); ?>!</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="/about.php" class="btn btn-outline-dark">About</a>
            <a href="/auth/profile.php" class="btn btn-outline-secondary">My Profile and Password</a>
        </div>
    </div>
</div>

<?php if (isset($_GET['registered'])) $err = 'registered'; ?>
<?php if (isset($messages[$err])): ?>
    <div class="alert alert-<?php echo $messages[$err][0]; ?>"><?php echo h($messages[$err][1]); ?></div>
<?php endif; ?>

<?php while ($an = $announcements->fetch_assoc()): ?>
    <div class="alert alert-info">
        <strong><?php echo h($an['title']); ?></strong>
        <small class="text-muted ms-2"><?php echo h(date('d M Y', strtotime($an['created_at']))); ?></small><br>
        <?php echo nl2br(h($an['message'])); ?>
    </div>
<?php endwhile; ?>

<div class="row mt-3">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">Available Exams</div>
            <div class="card-body">
                <div class="row">
                    <?php if($available_exams->num_rows > 0): ?>
                        <?php while($exam = $available_exams->fetch_assoc()):
                            $sch  = exam_schedule_state($exam);
                            $used = (int)($counts[$exam['id']]['c'] ?? 0);
                            $running = (int)($counts[$exam['id']]['ip'] ?? 0);
                            $limit_reached = $exam['max_attempts'] > 0 && $used >= $exam['max_attempts'];
                            $registered = isset($registered_for[(int)$exam['id']]);
                            $needs_reg = !empty($exam['enrollment_required']) && !$registered && $running == 0;
                            $code_input = !empty($exam['access_code'])
                                ? '<input type="text" name="access_code" class="form-control mb-2" placeholder="Access code" maxlength="20" required autocomplete="off">'
                                : '';
                        ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 border-light shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <h5 class="card-title text-primary"><?php echo h($exam['title']); ?></h5>
                                            <?php if ($sch['state'] == 'upcoming'): ?><span class="badge bg-info">Upcoming</span>
                                            <?php elseif ($sch['state'] == 'open'): ?><span class="badge bg-success">Live now</span>
                                            <?php elseif ($sch['state'] == 'late'): ?><span class="badge bg-warning text-dark">Late entry</span>
                                            <?php elseif ($sch['state'] == 'closed'): ?><span class="badge bg-danger">Entry closed</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="card-text text-muted"><?php echo h($exam['description']); ?></p>
                                        <p class="mb-1">Duration: <?php echo (int)$exam['duration_minutes']; ?> mins</p>
                                        <?php if (!empty($exam['enrollment_required'])): ?>
                                            <p class="mb-1 small <?php echo $registered ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $registered ? 'You are registered for this exam' : 'You are not registered for this exam'; ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($exam['max_attempts'] > 0): ?>
                                            <p class="mb-1 small text-muted">Attempts: <?php echo $used . ' / ' . (int)$exam['max_attempts']; ?></p>
                                        <?php endif; ?>
                                        <?php if ($sch['state'] != 'always'): ?>
                                            <p class="mb-1 small"><strong>Starts:</strong> <?php echo h(format_exam_time($sch['start'])); ?></p>
                                            <p class="mb-2 small text-muted">
                                                <?php echo $exam['late_minutes'] > 0
                                                    ? 'Late entry allowed for ' . (int)$exam['late_minutes'] . ' min after the start'
                                                    : 'No late entry'; ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if ($needs_reg): ?>
                                            <?php if ($sch['state'] == 'closed'): ?>
                                                <button class="btn btn-secondary w-100" disabled>Registration closed</button>
                                            <?php else: ?>
                                                <form action="/student/register_exam.php" method="POST">
                                                    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                                                    <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                                    <button type="submit" class="btn btn-success w-100">Register for this exam</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php elseif ($running > 0): ?>
                                            <form action="/student/create_attempt.php" method="POST">
                                                <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                                <button type="submit" class="btn btn-primary w-100">Continue Exam</button>
                                            </form>
                                        <?php elseif ($limit_reached): ?>
                                            <button class="btn btn-secondary w-100" disabled>Attempt limit reached</button>
                                        <?php elseif ($sch['state'] == 'upcoming'): ?>
                                            <button class="btn btn-outline-secondary w-100" disabled>
                                                Starts in <span class="countdown" data-seconds="<?php echo $sch['seconds_to_start']; ?>"></span>
                                            </button>
                                        <?php elseif ($sch['state'] == 'closed'): ?>
                                            <button class="btn btn-secondary w-100" disabled>Entry closed</button>
                                        <?php elseif ($sch['state'] == 'late'): ?>
                                            <div class="alert alert-warning py-2 small mb-2">
                                                You are <?php echo max(1, (int)floor($sch['late_seconds'] / 60)); ?> min late and will be marked LATE.
                                                <?php if (!empty($exam['late_loses_time'])): ?>
                                                    You will only have <?php echo max(1, (int)floor(($sch['end'] - time()) / 60)); ?> min left.
                                                <?php endif; ?>
                                            </div>
                                            <form action="/student/create_attempt.php" method="POST">
                                                <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                                <?php echo $code_input; ?>
                                                <button type="submit" class="btn btn-warning w-100">Start Late</button>
                                            </form>
                                        <?php else: ?>
                                            <form action="/student/create_attempt.php" method="POST">
                                                <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                                <?php echo $code_input; ?>
                                                <button type="submit" class="btn btn-outline-primary w-100">Start Exam</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted text-center p-3">No exams available at the moment.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-info text-white">My Attempts</div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if ($my_exams->num_rows == 0): ?>
                        <div class="list-group-item text-muted">No attempts yet.</div>
                    <?php endif; ?>
                    <?php while($attempt = $my_exams->fetch_assoc()): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo h($attempt['title']); ?></h6>
                                <small><?php echo date('M d', strtotime($attempt['created_at'])); ?></small>
                            </div>
                            <p class="mb-1">
                                Status:
                                <span class="badge bg-<?php echo $attempt['status'] == 'submitted' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $attempt['status'])); ?>
                                </span>
                                <?php if ($attempt['is_late']): ?>
                                    <span class="badge bg-warning text-dark">Late <?php echo (int)$attempt['minutes_late']; ?> min</span>
                                <?php endif; ?>
                            </p>
                            <?php if($attempt['status'] == 'submitted'): ?>
                                <small>Score: <strong><?php echo fmt_num($attempt['score']); ?></strong></small>
                            <?php endif; ?>
                            <div class="mt-2">
                                <?php if($attempt['status'] == 'in_progress'): ?>
                                    <a href="/student/take_exam.php?id=<?php echo $attempt['id']; ?>" class="btn btn-primary btn-sm w-100">Continue</a>
                                <?php elseif($attempt['status'] == 'submitted'): ?>
                                    <a href="/student/result.php?id=<?php echo $attempt['id']; ?>" class="btn btn-secondary btn-sm w-100">Review Answers</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Live countdown for upcoming exams (the time comes from the server, not the student's clock)
var timers = document.querySelectorAll('.countdown');
function tick() {
    timers.forEach(function (el) {
        var s = parseInt(el.getAttribute('data-seconds'), 10);
        if (s <= 0) { location.reload(); return; }
        var d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600),
            m = Math.floor((s % 3600) / 60), sec = s % 60;
        el.innerText = (d > 0 ? d + 'd ' : '') +
            String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
        el.setAttribute('data-seconds', s - 1);
    });
}
if (timers.length) { tick(); setInterval(tick, 1000); }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
