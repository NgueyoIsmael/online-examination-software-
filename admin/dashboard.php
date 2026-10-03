<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

$stats = [];
$stats['exams'] = $conn->query("SELECT COUNT(*) FROM exams WHERE is_custom = 0")->fetch_row()[0];
$stats['students'] = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetch_row()[0];
$stats['questions'] = $conn->query("SELECT COUNT(*) FROM questions q JOIN exams e ON e.id = q.exam_id WHERE e.is_custom = 0")->fetch_row()[0];
$stats['submissions'] = $conn->query("SELECT COUNT(*) FROM student_exams WHERE status = 'submitted'")->fetch_row()[0];

$recent = $conn->query("SELECT se.id, se.score, se.end_time, u.username, e.title
                        FROM student_exams se
                        JOIN users u ON u.id = se.student_id
                        JOIN exams e ON e.id = se.exam_id
                        WHERE se.status = 'submitted'
                        ORDER BY se.end_time DESC LIMIT 5");
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="display-5 text-primary">Admin Dashboard</h1>
        <p class="lead">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>.</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary mb-3 h-100">
            <div class="card-body text-center">
                <h1 class="display-4"><?php echo $stats['exams']; ?></h1>
                <h5 class="card-title">Exams</h5>
                <a href="/admin/exams.php" class="btn btn-light mt-2">Manage Exams</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success mb-3 h-100">
            <div class="card-body text-center">
                <h1 class="display-4"><?php echo $stats['questions']; ?></h1>
                <h5 class="card-title">Questions</h5>
                <p class="card-text">Total in all exams</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info mb-3 h-100">
            <div class="card-body text-center">
                <h1 class="display-4"><?php echo $stats['students']; ?></h1>
                <h5 class="card-title">Students</h5>
                <a href="/admin/students.php" class="btn btn-light mt-2">View Students</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-dark mb-3 h-100">
            <div class="card-body text-center">
                <h1 class="display-4"><?php echo $stats['submissions']; ?></h1>
                <h5 class="card-title">Submissions</h5>
                <a href="/admin/results.php" class="btn btn-light mt-2">View Results</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-5 mb-3">
        <div class="card">
            <div class="card-header">Quick Actions</div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="/admin/create_exam.php" class="btn btn-outline-primary">Create New Exam</a>
                    <a href="/admin/exams.php" class="btn btn-outline-secondary">View All Exams</a>
                    <a href="/admin/results.php" class="btn btn-outline-dark">View Results</a>
                    <a href="/admin/announcements.php" class="btn btn-outline-info">Announcements</a>
                    <a href="/auth/profile.php" class="btn btn-outline-secondary">My Profile and Password</a>
                    <a href="/about.php" class="btn btn-outline-dark">About</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-7 mb-3">
        <div class="card">
            <div class="card-header">Latest Submissions</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <tbody>
                    <?php if ($recent->num_rows == 0): ?>
                        <tr><td class="text-muted p-3">No submissions yet.</td></tr>
                    <?php endif; ?>
                    <?php while ($r = $recent->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['username']); ?></td>
                            <td><?php echo htmlspecialchars($r['title']); ?></td>
                            <td><?php echo $r['score']; ?></td>
                            <td><a href="/admin/result_detail.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
