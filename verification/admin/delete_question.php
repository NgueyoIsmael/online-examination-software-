<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$id = $_GET['id'] ?? null;
$exam_id = $_GET['exam_id'] ?? null;

if ($id) {
    $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

if ($exam_id) {
    header("Location: /admin/exam_questions.php?id=$exam_id");
} else {
    header("Location: /admin/exams.php");
}
exit();
