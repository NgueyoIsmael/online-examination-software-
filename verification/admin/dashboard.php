<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    header("Location: /student/dashboard.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

// Stats
$stats = [];
$stats['exams'] = $conn->query("SELECT COUNT(*) FROM exams")->fetch_row()[0];
$stats['students'] = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetch_row()[0];
$stats['questions'] = $conn->query("SELECT COUNT(*) FROM questions")->fetch_row()[0];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="display-5 text-primary">Admin Dashboard</h1>
        <p class="lead">Welcome, Admin.</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-primary mb-3 h-100">
            <div class="card-body text-center">
                <h1 class="display-4"><?php echo $stats['exams']; ?></h1>
                <h5 class="card-title">Exams</h5>
                <a href="/admin/exams.php" class="btn btn-light mt-2">Manage Exams</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success mb-3 h-100">
            <div class="card-body text-center">
                 <h1 class="display-4"><?php echo $stats['questions']; ?></h1>
                <h5 class="card-title">Questions</h5>
                <p class="card-text">Total Questions in Bank</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-info mb-3 h-100">
             <div class="card-body text-center">
                 <h1 class="display-4"><?php echo $stats['students']; ?></h1>
                <h5 class="card-title">Students</h5>
                <a href="/admin/students.php" class="btn btn-light mt-2">View Students</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Quick Actions</div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="/admin/create_exam.php" class="btn btn-outline-primary">Create New Exam</a>
                    <a href="/admin/exams.php" class="btn btn-outline-secondary">View All Exams</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
