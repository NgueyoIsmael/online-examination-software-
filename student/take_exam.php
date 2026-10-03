<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/exam_helpers.php';

$attempt_id = $_GET['id'] ?? null;
if (!$attempt_id) {
    die("Invalid attempt ID");
}

// Fetch Attempt and Exam details
$stmt = $conn->prepare("SELECT se.*, e.title, e.description, e.duration_minutes, e.scheduled_start, e.late_loses_time, e.shuffle_questions, e.shuffle_options, e.id as real_exam_id FROM student_exams se JOIN exams e ON se.exam_id = e.id WHERE se.id = ? AND se.student_id = ?");
$stmt->bind_param("ii", $attempt_id, $_SESSION['user_id']);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();

if (!$attempt) {
    die("Exam attempt not found");
}

if ($attempt['status'] !== 'in_progress') {
    header("Location: /student/result.php?id=$attempt_id");
    exit();
}

// Check timer
$start_time = strtotime($attempt['start_time']);
$duration_sec = $attempt['duration_minutes'] * 60;
$deadline = $start_time + $duration_sec;
// Scheduled exam where late students lose time: everybody finishes at the same fixed end time
if (!empty($attempt['scheduled_start']) && !empty($attempt['late_loses_time'])) {
    $deadline = min($deadline, strtotime($attempt['scheduled_start']) + $duration_sec);
}
$remaining = $deadline - time();

if ($remaining <= 0) {
    // Auto submit
    header("Location: /student/submit_exam.php?id=$attempt_id&auto=1");
    exit();
}

// Fetch Questions
$stmt = $conn->prepare("SELECT * FROM questions WHERE exam_id = ?");
$stmt->bind_param("i", $attempt['real_exam_id']);
$stmt->execute();
$questions = $stmt->get_result();
$questions_data = [];
while ($q = $questions->fetch_assoc()) {
    // Fetch options
    $opt_stmt = $conn->prepare("SELECT id, option_text FROM question_options WHERE question_id = ?");
    $opt_stmt->bind_param("i", $q['id']);
    $opt_stmt->execute();
    $q['options'] = $opt_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $questions_data[] = $q;
}

// Shuffle (the order stays the same for one student if the page is reloaded)
if (!empty($attempt['shuffle_questions'])) {
    usort($questions_data, function ($a, $b) use ($attempt_id) {
        return crc32($attempt_id . ':q:' . $a['id']) <=> crc32($attempt_id . ':q:' . $b['id']);
    });
}
if (!empty($attempt['shuffle_options'])) {
    foreach ($questions_data as &$qq) {
        if ($qq['type'] === 'single_choice' || $qq['type'] === 'multiple_choice') {
            usort($qq['options'], function ($a, $b) use ($attempt_id) {
                return crc32($attempt_id . ':o:' . $a['id']) <=> crc32($attempt_id . ':o:' . $b['id']);
            });
        }
    }
    unset($qq);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taking Exam: <?php echo htmlspecialchars($attempt['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="/public/css/style.css">
    <style>
        body { user-select: none; } /* Disable text selection */
        .question-card {
            border-left: 5px solid var(--primary-color);
        }
    </style>
</head>
<body oncontextmenu="return false;"> 

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center sticky-top bg-white py-3 border-bottom shadow-sm mb-4 px-3 rounded">
        <div>
            <h3 class="m-0 text-primary"><?php echo htmlspecialchars($attempt['title']); ?></h3>
            <small class="text-muted">Do not refresh or leave the page.</small>
        </div>
        <div class="exam-timer" id="timer">00:00:00</div>
    </div>

    <?php if (!empty($attempt['is_late'])): ?>
        <div class="alert alert-warning">
            You started <strong><?php echo (int)$attempt['minutes_late']; ?> minute(s) late</strong>.
            <?php if (!empty($attempt['late_loses_time'])): ?>Your time is shorter because the exam ends at the same time for everyone.<?php endif; ?>
        </div>
    <?php endif; ?>

    <form action="/student/submit_exam.php" method="POST" id="examForm">
        <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
        
        <?php foreach($questions_data as $index => $q): ?>
            <div class="card question-card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <span class="badge bg-secondary me-2"><?php echo $index + 1; ?></span>
                        <?php echo htmlspecialchars($q['question_text']); ?>
                    </h5>
                    <p class="text-muted text-end mb-2"><small>Points: <?php echo $q['points']; ?></small></p>
                    
                    <div class="ms-2">
                    <?php if($q['type'] == 'single_choice' || $q['type'] == 'true_false'): ?>
                        <?php foreach($q['options'] as $opt): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="answers[<?php echo $q['id']; ?>]" value="<?php echo $opt['id']; ?>" id="opt_<?php echo $opt['id']; ?>">
                                <label class="form-check-label" for="opt_<?php echo $opt['id']; ?>"><?php echo htmlspecialchars($opt['option_text']); ?></label>
                            </div>
                        <?php endforeach; ?>
                    
                    <?php elseif($q['type'] == 'multiple_choice'): ?>
                        <?php foreach($q['options'] as $opt): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="answers[<?php echo $q['id']; ?>][]" value="<?php echo $opt['id']; ?>" id="opt_<?php echo $opt['id']; ?>">
                                <label class="form-check-label" for="opt_<?php echo $opt['id']; ?>"><?php echo htmlspecialchars($opt['option_text']); ?></label>
                            </div>
                        <?php endforeach; ?>
                    
                    <?php elseif($q['type'] == 'short_answer' || $q['type'] == 'fill_blank'): ?>
                        <div class="mb-3">
                            <input type="text" class="form-control" name="answers[<?php echo $q['id']; ?>]" placeholder="Type your answer here">
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="d-grid gap-2 mb-5">
            <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Are you sure you want to submit?');">Submit Exam</button>
        </div>
    </form>
</div>

<script>
    let remainingTime = <?php echo $remaining; ?>;
    
    function updateTimer() {
        if (remainingTime <= 0) {
            document.getElementById('examForm').submit();
            return;
        }
        
        let hours = Math.floor(remainingTime / 3600);
        let minutes = Math.floor((remainingTime % 3600) / 60);
        let seconds = remainingTime % 60;
        
        document.getElementById('timer').innerText = 
            `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        remainingTime--;
    }
    
    setInterval(updateTimer, 1000);
    updateTimer();

    // Tab switch detection
    document.addEventListener("visibilitychange", function() {
        if (document.hidden) {
            alert("Warning: Tab switching is monitored. Please stay on the exam page.");
        }
    });

    // Prevent page refresh warning
    window.onbeforeunload = function() {
        return "Are you sure you want to leave? Your exam might be submitted.";
    };
    
    document.getElementById('examForm').onsubmit = function() {
        window.onbeforeunload = null;
    };
</script>

</body>
</html>
