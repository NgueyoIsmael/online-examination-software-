<?php
// STUDENT LOGIN  (auth/login.php)
session_start();
require_once __DIR__ . '/../config/db.php';

// Already logged in? Send them to the right place
if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] == 'admin' ? "/admin/dashboard.php" : "/student/dashboard.php"));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Only STUDENT accounts can use this page
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? AND role = 'student'");
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
            header("Location: /student/dashboard.php");
            exit();
        }
    }
    $error = "Invalid student username or password.";
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<!-- Welcome Modal -->
<div class="modal fade" id="welcomeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <div class="modal-header border-0 justify-content-center">
        <h5 class="modal-title display-6">Welcome!</h5>
      </div>
      <div class="modal-body">
        <!-- CHANGE THE NAME BELOW TO YOURS -->
        <p class="lead">Welcome to examination system by NGUEYO ISMAEL</p>
      </div>
      <div class="modal-footer border-0 justify-content-center">
        <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Get Started</button>
      </div>
    </div>
  </div>
</div>

<div class="auth-container">
    <div class="card auth-card">
        <h3 class="auth-header">Student Sign In</h3>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success text-center">Registration successful! Please login.</div>
        <?php endif; ?>
        <?php if (isset($_GET['reset'])): ?>
            <div class="alert alert-success text-center">Password changed! Please login.</div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>
            <div class="text-end mb-3">
                <a href="/auth/forgot_password.php" class="text-decoration-none small">Forgot password?</a>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">Login</button>
            </div>
        </form>
        <div class="text-center mt-3">
            <a href="/auth/register.php" class="text-decoration-none">Create an account</a>
        </div>
        <div class="text-center mt-2">
            <a href="/auth/admin_login.php" class="text-decoration-none small text-muted">Admin login</a>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        var myModal = new bootstrap.Modal(document.getElementById('welcomeModal'));
        myModal.show();
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
