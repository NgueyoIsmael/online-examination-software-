<?php
// admin/result_export.php  (NEW)  ?id=ATTEMPT_ID&format=pdf|doc
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/exam_helpers.php';

$attempt_id = (int)($_GET['id'] ?? 0);
$format = (($_GET['format'] ?? 'pdf') === 'doc') ? 'doc' : 'pdf';

$review = load_review($conn, $attempt_id, null); // admin can export any student's result
if (!$review) {
    die("Result not available (the student has not submitted this exam).");
}
send_review_export($review, $format);
