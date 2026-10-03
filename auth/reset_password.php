<?php
// RESET PASSWORD  (auth/reset_password.php)
session_start();
require_once __DIR__ . '/../config/db.php';

$token = $_POST['token'] ?? ($_GET['token'] ?? '');
$user  = null;

if ($token !== '') {
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT id, role FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
    }
}

if ($user && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $new_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt->bind_param("si", $new_hash, $user['id']);
        $stmt->execute();

        $go = ($user['role'] == 'admin') ? "/auth/admin_login.php?reset=1" : "/auth/login.php?reset=1";
        header("Location: " . $go);
        exit();
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="auth-container">
    <div class="card auth-card">
        <h3 class="auth-header">Reset Password</h3>
        <?php if (!$user): ?>
            <div class="alert alert-danger text-center">This reset link is invalid or has expired.</div>
            <div class="text-center mt-3">
                <a href="/auth/forgot_password.php" class="text-decoration-none">Request a new link</a>
            </div>
        <?php else: ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" placeholder="At least 6 characters" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">Change Password</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
