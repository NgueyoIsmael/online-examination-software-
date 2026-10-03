<?php
// auth/profile.php (NEW) - profile and change password, for both admins and students
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/exam_helpers.php';

$uid = (int)$_SESSION['user_id'];
$back = ($_SESSION['role'] === 'admin') ? '/admin/dashboard.php' : '/student/dashboard.php';
$ok = null;
$bad = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $full = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (strlen($full) > 100) {
            $bad = "The name is too long.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $bad = "Please enter a valid email address.";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $stmt->bind_param("si", $email, $uid);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $bad = "That email is already used by another account.";
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $full, $email, $uid);
                $stmt->execute();
                $ok = "Profile saved.";
            }
        }
    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || !password_verify($current, $row['password'])) {
            $bad = "Your current password is not correct.";
        } elseif (strlen($new) < 6) {
            $bad = "The new password must be at least 6 characters.";
        } elseif ($new !== $confirm) {
            $bad = "The new passwords do not match.";
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $uid);
            $stmt->execute();
            session_regenerate_id(true);
            $ok = "Password changed.";
        }
    }
}

$stmt = $conn->prepare("SELECT username, email, full_name, role, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>My Profile</h1>
    <a href="<?php echo $back; ?>" class="btn btn-secondary">Back to Dashboard</a>
</div>

<?php if ($ok): ?><div class="alert alert-success"><?php echo h($ok); ?></div><?php endif; ?>
<?php if ($bad): ?><div class="alert alert-danger"><?php echo h($bad); ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">Details</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="action" value="profile">
                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" class="form-control" value="<?php echo h($user['username']); ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label>Full name (shown on certificates)</label>
                        <input type="text" name="full_name" class="form-control" maxlength="100" value="<?php echo h($user['full_name']); ?>">
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required value="<?php echo h($user['email']); ?>">
                    </div>
                    <p class="text-muted small">Role: <?php echo h(ucfirst($user['role'])); ?> | Joined: <?php echo h($user['created_at']); ?></p>
                    <button type="submit" class="btn btn-primary">Save profile</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">Change password</div>
            <div class="card-body">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="action" value="password">
                    <div class="mb-3">
                        <label>Current password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>New password</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label>Confirm new password</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                    </div>
                    <button type="submit" class="btn btn-warning">Change password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
