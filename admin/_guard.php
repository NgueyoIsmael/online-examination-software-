<?php
// admin/_guard.php  (REPLACES the old one)
// Every admin page includes this file. It checks the user is an admin,
// loads the shared helpers (time zone + security token), and has a total-marks updater.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/exam_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/admin_login.php");
    exit();
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: /student/dashboard.php");
    exit();
}

// Keeps exams.total_marks equal to the sum of its question points
function update_total_marks($conn, $exam_id) {
    $stmt = $conn->prepare("UPDATE exams SET total_marks = (SELECT COALESCE(SUM(points), 0) FROM questions WHERE exam_id = ?) WHERE id = ?");
    $stmt->bind_param("ii", $exam_id, $exam_id);
    $stmt->execute();
}
