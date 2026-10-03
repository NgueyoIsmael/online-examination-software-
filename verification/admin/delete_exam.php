<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$id = $_GET['id'] ?? null;
if ($id) {
    $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}
header("Location: /admin/exams.php");
exit();
