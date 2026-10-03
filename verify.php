<?php
// verify.php (NEW, goes in the project ROOT next to index.php)
// Anyone can open  /verify.php?id=7&code=ABCDE12345  to check a certificate is real.
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/exam_helpers.php';

$id = (int)($_GET['id'] ?? 0);
$code = strtoupper(trim($_GET['code'] ?? ''));
$valid = false;
$info = null;

if ($id && $code !== '' && hash_equals(certificate_code($id), $code)) {
    $review = load_review($conn, $id, null);
    if ($review && $review['summary']['overall_passed']) {
        $stmt = $conn->prepare("SELECT username, full_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $review['attempt']['student_id']);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $valid = true;
        $info = [
            'name'  => trim($u['full_name'] ?? '') !== '' ? $u['full_name'] : $u['username'],
            'exam'  => $review['attempt']['title'],
            'score' => fmt_num($review['summary']['score']) . ' / ' . fmt_num($review['summary']['max']) . ' (' . $review['summary']['percent'] . '%)',
            'date'  => date('d F Y', strtotime($review['attempt']['end_time'])),
        ];
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="auth-container">
    <div class="card auth-card">
        <h3 class="auth-header">Certificate Check</h3>
        <?php if ($valid): ?>
            <div class="alert alert-success text-center"><strong>This certificate is VALID.</strong></div>
            <table class="table">
                <tr><th>Name</th><td><?php echo h($info['name']); ?></td></tr>
                <tr><th>Exam</th><td><?php echo h($info['exam']); ?></td></tr>
                <tr><th>Score</th><td><?php echo h($info['score']); ?></td></tr>
                <tr><th>Date</th><td><?php echo h($info['date']); ?></td></tr>
            </table>
        <?php else: ?>
            <div class="alert alert-danger text-center">This certificate could not be verified. Check the ID and code.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
