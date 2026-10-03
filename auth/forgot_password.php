<?php
// FORGOT PASSWORD  (auth/forgot_password.php)
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

// true  = (localhost) show the reset link on the page, because email usually doesn't work locally
// false = (live server) only send the link by email
$dev_mode = !defined('RUNNING_ON_VERCEL');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user  = $result->fetch_assoc();
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);

        // Token valid for 1 hour
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
        $stmt->bind_param("si", $hash, $user['id']);
        $stmt->execute();

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $link   = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/auth/reset_password.php?token=' . $token;

        $subject = "Password Reset - Online Exam System";
        $body    = "Hello " . $user['username'] . ",\n\nClick the link below to reset your password (valid for 1 hour):\n\n" . $link . "\n\nIf you did not ask for this, ignore this email.";
        send_email($email, $subject, $body);

        if ($dev_mode) {
            $dev_link = $link;
        }
    }
    // Same message whether or not the email exists (prevents guessing which emails are registered)
    $message = "If that email is registered, a reset link has been sent.";
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="auth-container">
    <div class="card auth-card">
        <h3 class="auth-header">Forgot Password</h3>
        <?php if (isset($message)): ?>
            <div class="alert alert-success text-center"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if (isset($dev_link)): ?>
            <div class="alert alert-warning small">
                <strong>Local testing mode:</strong> click this link to reset:<br>
                <a href="<?php echo htmlspecialchars($dev_link); ?>">Reset my password</a>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-4">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="Enter your registered email" required>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">Send Reset Link</button>
            </div>
        </form>
        <div class="text-center mt-3">
            <a href="/auth/login.php" class="text-decoration-none">Back to login</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
