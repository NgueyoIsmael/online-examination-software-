<?php
// Deleting now needs a POST request with a security token (a plain link can no longer delete)
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

$exam_id = 0;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $exam_id = (int)($_POST['exam_id'] ?? 0);

    if ($id) {
        $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    if ($exam_id) {
        update_total_marks($conn, $exam_id);
    }
}

if ($exam_id) {
    header("Location: /admin/exam_questions.php?id=$exam_id");
} else {
    header("Location: /admin/exams.php");
}
exit();
