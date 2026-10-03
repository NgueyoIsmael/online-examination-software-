<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $duration = $_POST['duration'];
    $status = $_POST['status'];
    $created_by = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO exams (title, description, duration_minutes, status, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisi", $title, $description, $duration, $status, $created_by);
    
    if ($stmt->execute()) {
        header("Location: /admin/exams.php");
        exit();
    } else {
        $error = "Error creating exam.";
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<h1>Create Exam</h1>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label>Title</label>
        <input type="text" name="title" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="3"></textarea>
    </div>
    <div class="mb-3">
        <label>Duration (minutes)</label>
        <input type="number" name="duration" class="form-control" required value="60">
    </div>
    <div class="mb-3">
        <label>Status</label>
        <select name="status" class="form-select">
            <option value="draft">Draft</option>
            <option value="open">Open</option>
            <option value="closed">Closed</option>
        </select>
    </div>
    <button type="submit" class="btn btn-success">Create</button>
    <a href="/admin/exams.php" class="btn btn-secondary">Cancel</a>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
