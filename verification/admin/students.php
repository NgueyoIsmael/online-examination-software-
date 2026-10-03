<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$stmt = $conn->prepare("SELECT id, username, email, role, created_at FROM users WHERE role = 'student' ORDER BY created_at DESC");
$stmt->execute();
$students = $stmt->get_result();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<h1>Manage Students</h1>

<table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Joined</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($student = $students->fetch_assoc()): ?>
            <tr>
                <td><?php echo $student['id']; ?></td>
                <td><?php echo htmlspecialchars($student['username']); ?></td>
                <td><?php echo htmlspecialchars($student['email']); ?></td>
                <td><?php echo $student['created_at']; ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include __DIR__ . '/../includes/footer.php'; ?>
