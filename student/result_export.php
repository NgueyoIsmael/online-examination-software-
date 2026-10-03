<?php
// student/result_export.php  (NEW)  ?id=ATTEMPT_ID&format=pdf|doc
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/exam_helpers.php';

$attempt_id = (int)($_GET['id'] ?? 0);
$format = (($_GET['format'] ?? 'pdf') === 'doc') ? 'doc' : 'pdf';

$review = load_review($conn, $attempt_id, $_SESSION['user_id']);
if (!$review) {
    die("Result not available.");
}
send_review_export($review, $format);
