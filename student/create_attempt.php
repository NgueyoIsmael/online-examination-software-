<?php
// student/create_attempt.php  (REPLACES the old file)
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

$exam_id = (int)($_POST['exam_id'] ?? 0);
$student_id = (int)$_SESSION['user_id'];

// The exam must be open, and either an admin exam or this student's own exam
$stmt = $conn->prepare("SELECT e.*, (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) AS q_count
                        FROM exams e
                        WHERE e.id = ? AND e.status = 'open' AND (e.is_custom = 0 OR e.created_by = ?)");
$stmt->bind_param("ii", $exam_id, $student_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

if (!$exam) {
    header("Location: /student/dashboard.php?err=unavailable");
    exit();
}
// Only students registered for this exam by the admin may take it
if (!empty($exam['enrollment_required']) && empty($exam['is_custom'])) {
    $stmt = $conn->prepare("SELECT 1 FROM exam_enrollments WHERE exam_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $exam_id, $student_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) {
        header("Location: /student/dashboard.php?err=notenrolled");
        exit();
    }
}

if ($exam['q_count'] == 0) {
    header("Location: /student/dashboard.php?err=empty");
    exit();
}

// Already has an attempt in progress? Continue it (no code needed again)
$stmt = $conn->prepare("SELECT id FROM student_exams WHERE student_id = ? AND exam_id = ? AND status = 'in_progress'");
$stmt->bind_param("ii", $student_id, $exam_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
if ($existing) {
    header("Location: /student/take_exam.php?id=" . $existing['id']);
    exit();
}

// Attempt limit
if ($exam['max_attempts'] > 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM student_exams WHERE student_id = ? AND exam_id = ?");
    $stmt->bind_param("ii", $student_id, $exam_id);
    $stmt->execute();
    $used = $stmt->get_result()->fetch_row()[0];
    if ($used >= $exam['max_attempts']) {
        header("Location: /student/dashboard.php?err=attempts");
        exit();
    }
}

// Schedule check (decided on the server, so changing the computer clock does not help)
$sch = exam_schedule_state($exam);
$is_late = 0;
$minutes_late = 0;
if ($sch['state'] == 'upcoming') {
    header("Location: /student/dashboard.php?err=upcoming");
    exit();
}
if ($sch['state'] == 'closed') {
    header("Location: /student/dashboard.php?err=closed");
    exit();
}
if ($sch['state'] == 'late') {
    $is_late = 1;
    $minutes_late = max(1, (int)floor($sch['late_seconds'] / 60));
}

// Access code (5 wrong tries lock the student out for 5 minutes)
if (!empty($exam['access_code'])) {
    $fails = $_SESSION['code_fails'][$exam_id] ?? ['n' => 0, 't' => 0];
    if (time() - $fails['t'] >= 300) {
        $fails = ['n' => 0, 't' => 0];
    }
    if ($fails['n'] >= 5) {
        header("Location: /student/dashboard.php?err=locked");
        exit();
    }
    $typed = strtoupper(trim($_POST['access_code'] ?? ''));
    if (!hash_equals(strtoupper($exam['access_code']), $typed)) {
        $fails['n']++;
        $fails['t'] = time();
        $_SESSION['code_fails'][$exam_id] = $fails;
        header("Location: /student/dashboard.php?err=code");
        exit();
    }
    unset($_SESSION['code_fails'][$exam_id]);
}

// Create new attempt
$start_time = date('Y-m-d H:i:s');
$stmt = $conn->prepare("INSERT INTO student_exams (student_id, exam_id, start_time, status, is_late, minutes_late) VALUES (?, ?, ?, 'in_progress', ?, ?)");
$stmt->bind_param("iisii", $student_id, $exam_id, $start_time, $is_late, $minutes_late);
if ($stmt->execute()) {
    header("Location: /student/take_exam.php?id=" . $stmt->insert_id);
    exit();
}
die("Error starting exam");
