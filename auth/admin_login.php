<?php
// ADMIN LOGIN  (auth/admin_login.php)
session_start();
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] == 'admin' ? "/admin/dashboard.php" : "/student/dashboard.php"));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Only ADMIN accounts can use this page
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? AND role = 'admin'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: /admin/dashboard.php");
            exit();
        }
    }
    $error = "Invalid admin username or password.";
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="auth-container">
    <div class="card auth-card">
        <h3 class="auth-header">Admin Sign In</h3>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['reset'])): ?>
            <div class="alert alert-success text-center">Password changed! Please login.</div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Admin Username</label>
                <input type="text" name="username" class="form-control" placeholder="Enter admin username" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter admin password" required>
            </div>
            <div class="text-end mb-3">
                <a href="/auth/forgot_password.php" class="text-decoration-none small">Forgot password?</a>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-dark btn-lg">Login as Admin</button>
            </div>
        </form>
        <div class="text-center mt-3">
            <a href="/auth/login.php" class="text-decoration-none">Student login</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
