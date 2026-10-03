<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

$students = $conn->query("SELECT u.id, u.username, u.email, u.created_at,
        (SELECT COUNT(*) FROM student_exams se WHERE se.student_id = u.id AND se.status = 'submitted') AS taken
    FROM users u WHERE u.role = 'student' ORDER BY u.created_at DESC");
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
            <th>Exams Taken</th>
            <th>Results</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($students->num_rows == 0): ?>
            <tr><td colspan="6" class="text-muted">No students have registered yet.</td></tr>
        <?php endif; ?>
        <?php while ($student = $students->fetch_assoc()): ?>
            <tr>
                <td><?php echo $student['id']; ?></td>
                <td><?php echo htmlspecialchars($student['username']); ?></td>
                <td><?php echo htmlspecialchars($student['email']); ?></td>
                <td><?php echo $student['created_at']; ?></td>
                <td><?php echo $student['taken']; ?></td>
                <td><a href="/admin/results.php?student_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-outline-dark">View</a></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include __DIR__ . '/../includes/footer.php'; ?>
