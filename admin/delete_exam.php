<?php
// Deleting now needs a POST request with a security token (a plain link can no longer delete)
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
}
header("Location: /admin/exams.php");
exit();
