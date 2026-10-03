<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$stmt = $conn->prepare("SELECT * FROM exams ORDER BY created_at DESC");
$stmt->execute();
$exams = $stmt->get_result();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Manage Exams</h1>
    <a href="/admin/create_exam.php" class="btn btn-primary">Create New Exam</a>
</div>

<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Duration</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($exam = $exams->fetch_assoc()): ?>
            <tr>
                <td><?php echo $exam['id']; ?></td>
                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                <td><?php echo $exam['duration_minutes']; ?> min</td>
                <td>
                    <span class="badge bg-<?php echo $exam['status'] == 'open' ? 'success' : ($exam['status'] == 'closed' ? 'danger' : 'secondary'); ?>">
                        <?php echo ucfirst($exam['status']); ?>
                    </span>
                </td>
                <td>
                    <a href="/admin/edit_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a href="/admin/exam_questions.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-info">Questions</a>
                    <a href="/admin/delete_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include __DIR__ . '/../includes/footer.php'; ?>
