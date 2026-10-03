<?php
// admin/notify_exam.php (NEW) - emails every student about an open exam
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /admin/exams.php");
    exit();
}
csrf_check();
@set_time_limit(0);

$id = (int)($_POST['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

if (!$exam || $exam['status'] !== 'open') {
    $_SESSION['flash'] = ['danger', 'Only exams that are Open can be emailed to students.'];
    header("Location: /admin/exams.php");
    exit();
}

$when = empty($exam['scheduled_start'])
    ? 'Available now (you can start any time while the exam is open)'
    : format_exam_time(strtotime($exam['scheduled_start']));

$lines = [];
$lines[] = "Exam: " . $exam['title'];
$lines[] = "When: " . $when;
$lines[] = "Duration: " . (int)$exam['duration_minutes'] . " minutes";
if (!empty($exam['scheduled_start'])) {
    $lines[] = $exam['late_minutes'] > 0
        ? "Late entry: allowed for " . (int)$exam['late_minutes'] . " minutes after the start (you will be marked late)"
        : "Late entry: not allowed - please be on time";
}
if ($exam['max_attempts'] > 0) $lines[] = "Attempts allowed: " . (int)$exam['max_attempts'];
$lines[] = "Pass mark: " . (int)$exam['pass_mark'] . "%";
if (!empty($exam['enrollment_required'])) $lines[] = "Registration: you must register for this exam on your student dashboard before you can take it.";
if (!empty($exam['access_code'])) $lines[] = "Access code: you will be given the code before the exam.";
$details = implode("\n", $lines);
$link = site_base_url() . '/auth/login.php';

$res = $conn->query("SELECT username, email, full_name FROM users WHERE role = 'student'");
$count = ['sent' => 0, 'logged' => 0, 'failed' => 0];
while ($st = $res->fetch_assoc()) {
    $name = trim($st['full_name'] ?? '') !== '' ? $st['full_name'] : $st['username'];
    $body = "Hello $name,\n\nAn exam has been scheduled for you.\n\n$details\n\nLog in here: $link\n\nGood luck!\nOnline Exam System";
    $count[send_email($st['email'], 'Exam: ' . $exam['title'], $body)]++;
}

$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare("UPDATE exams SET notified_at = ? WHERE id = ?");
$stmt->bind_param("si", $now, $id);
$stmt->execute();

if ($count['sent'] > 0 || ($count['logged'] == 0 && $count['failed'] == 0)) {
    $msg = "Emails sent: {$count['sent']}.";
    if ($count['failed']) $msg .= " Failed: {$count['failed']}.";
    $_SESSION['flash'] = [$count['failed'] ? 'warning' : 'success', $msg];
} elseif ($count['logged'] > 0) {
    $_SESSION['flash'] = ['info', "Email is not set up yet, so {$count['logged']} message(s) were saved in logs/emails.log.php instead of being sent. Fill in config/mail.php to send real emails."];
} else {
    $_SESSION['flash'] = ['danger', "Could not send the emails ({$count['failed']} failed). Check config/mail.php and logs/emails.log.php."];
}
header("Location: /admin/exams.php");
exit();
