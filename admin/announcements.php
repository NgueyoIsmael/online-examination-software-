<?php
// admin/announcements.php (NEW) - post messages that students see on their dashboard
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($title === '' || $message === '' || strlen($title) > 150 || strlen($message) > 2000) {
            $_SESSION['flash'] = ['danger', 'Please enter a title (max 150 characters) and a message (max 2000 characters).'];
        } else {
            $uid = (int)$_SESSION['user_id'];
            $stmt = $conn->prepare("INSERT INTO announcements (title, message, created_by) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $title, $message, $uid);
            $stmt->execute();
            $_SESSION['flash'] = ['success', 'Announcement posted.'];
        }
    } elseif ($action === 'toggle' || $action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'toggle') {
            $stmt = $conn->prepare("UPDATE announcements SET is_active = 1 - is_active WHERE id = ?");
        } else {
            $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: /admin/announcements.php");
    exit();
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$list = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Announcements</h1>
    <a href="/admin/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo h($flash[0]); ?>"><?php echo h($flash[1]); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">New announcement</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="add">
            <div class="mb-3">
                <label>Title</label>
                <input type="text" name="title" class="form-control" maxlength="150" required>
            </div>
            <div class="mb-3">
                <label>Message</label>
                <textarea name="message" class="form-control" rows="3" maxlength="2000" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Post to students</button>
        </form>
    </div>
</div>

<h4>All announcements</h4>
<?php if ($list->num_rows == 0): ?>
    <p class="text-muted">Nothing posted yet.</p>
<?php endif; ?>
<?php while ($a = $list->fetch_assoc()): ?>
    <div class="card mb-2 <?php echo $a['is_active'] ? '' : 'bg-light'; ?>">
        <div class="card-body d-flex justify-content-between gap-3">
            <div>
                <h6 class="mb-1"><?php echo h($a['title']); ?>
                    <span class="badge bg-<?php echo $a['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $a['is_active'] ? 'Visible' : 'Hidden'; ?></span>
                </h6>
                <div><?php echo nl2br(h($a['message'])); ?></div>
                <small class="text-muted"><?php echo h($a['created_at']); ?></small>
            </div>
            <div class="text-nowrap">
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo $a['is_active'] ? 'Hide' : 'Show'; ?></button>
                </form>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this announcement?')">
                    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
<?php endwhile; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
