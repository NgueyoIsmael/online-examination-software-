<?php
// student/register_exam.php (NEW) - a student registers for one particular exam
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/exam_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /student/dashboard.php");
    exit();
}
csrf_check();

$exam_id = (int)($_POST['exam_id'] ?? 0);
$student_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM exams WHERE id = ? AND status = 'open' AND is_custom = 0");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

if (!$exam) {
    header("Location: /student/dashboard.php?err=unavailable");
    exit();
}
if (empty($exam['enrollment_required'])) {
    header("Location: /student/dashboard.php");
    exit();
}

// Registration closes together with entry to the exam
$sch = exam_schedule_state($exam);
if ($sch['state'] == 'closed') {
    header("Location: /student/dashboard.php?err=closed");
    exit();
}

$stmt = $conn->prepare("INSERT IGNORE INTO exam_enrollments (exam_id, student_id) VALUES (?, ?)");
$stmt->bind_param("ii", $exam_id, $student_id);
$stmt->execute();

header("Location: /student/dashboard.php?registered=1");
exit();
