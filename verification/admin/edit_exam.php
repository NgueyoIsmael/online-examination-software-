<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: /admin/exams.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $duration = $_POST['duration'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE exams SET title = ?, description = ?, duration_minutes = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssisi", $title, $description, $duration, $status, $id);
    
    if ($stmt->execute()) {
        $success = "Exam updated successfully.";
    } else {
        $error = "Error updating exam.";
    }
}

$stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

if (!$exam) {
    echo "Exam not found.";
    exit();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<h1>Edit Exam</h1>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label>Title</label>
        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($exam['title']); ?>" required>
    </div>
    <div class="mb-3">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($exam['description']); ?></textarea>
    </div>
    <div class="mb-3">
        <label>Duration (minutes)</label>
        <input type="number" name="duration" class="form-control" value="<?php echo $exam['duration_minutes']; ?>" required>
    </div>
    <div class="mb-3">
        <label>Status</label>
        <select name="status" class="form-select">
            <option value="draft" <?php if($exam['status'] == 'draft') echo 'selected'; ?>>Draft</option>
            <option value="open" <?php if($exam['status'] == 'open') echo 'selected'; ?>>Open</option>
            <option value="closed" <?php if($exam['status'] == 'closed') echo 'selected'; ?>>Closed</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Update</button>
    <a href="/admin/exams.php" class="btn btn-secondary">Back</a>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
