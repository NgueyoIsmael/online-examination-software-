<?php
// student/result.php  (REPLACES the old file)
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/exam_helpers.php';

$attempt_id = (int)($_GET['id'] ?? 0);
if (!$attempt_id) {
    header("Location: /student/dashboard.php");
    exit();
}

$review = load_review($conn, $attempt_id, $_SESSION['user_id']);
if (!$review) {
    header("Location: /student/dashboard.php");
    exit();
}
$a = $review['attempt'];
$s = $review['summary'];

$badge = ['passed' => 'success', 'failed' => 'danger', 'partial' => 'warning text-dark', 'unanswered' => 'secondary'];
$border = ['passed' => 'success', 'failed' => 'danger', 'partial' => 'warning', 'unanswered' => 'secondary'];
$label = ['passed' => 'Passed', 'failed' => 'Failed', 'partial' => 'Partial', 'unanswered' => 'Not answered'];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="mb-0">Result: <?php echo h($a['title']); ?></h2>
    <div class="btn-group">
        <a href="/student/result_export.php?id=<?php echo $attempt_id; ?>&format=pdf" target="_blank" class="btn btn-outline-danger">Download PDF</a>
        <a href="/student/result_export.php?id=<?php echo $attempt_id; ?>&format=doc" class="btn btn-outline-primary">Download Word</a>
        <?php if ($s['overall_passed']): ?>
            <a href="/student/certificate.php?id=<?php echo $attempt_id; ?>" target="_blank" class="btn btn-success">Certificate</a>
        <?php endif; ?>
    </div>
</div>
<p class="text-muted">Completed on: <?php echo h($a['end_time']); ?>
    <?php if (!empty($a['is_late'])): ?>
        <span class="badge bg-warning text-dark ms-2">Started <?php echo (int)$a['minutes_late']; ?> min late</span>
    <?php endif; ?>
    <span class="ms-2">Pass mark: <?php echo (int)$s['pass_mark']; ?>%</span>
</p>

<div class="row text-center mb-4">
    <div class="col-6 col-md-3 mb-2">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Score</div>
            <div class="fs-3 fw-bold"><?php echo fmt_num($s['score']) . ' / ' . fmt_num($s['max']); ?></div>
            <span class="badge bg-<?php echo $s['overall_passed'] ? 'success' : 'danger'; ?>">
                <?php echo $s['percent']; ?>% - <?php echo $s['overall_passed'] ? 'PASSED' : 'FAILED'; ?>
            </span>
        </div></div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card h-100 border-success"><div class="card-body">
            <div class="text-muted small">Questions passed</div>
            <div class="fs-3 fw-bold text-success"><?php echo $s['passed']; ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card h-100 border-danger"><div class="card-body">
            <div class="text-muted small">Questions failed</div>
            <div class="fs-3 fw-bold text-danger"><?php echo $s['failed']; ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Total questions</div>
            <div class="fs-3 fw-bold"><?php echo $s['total']; ?></div>
        </div></div>
    </div>
</div>

<div class="btn-group mb-3" id="filters">
    <button type="button" class="btn btn-outline-dark active" data-filter="all">All</button>
    <button type="button" class="btn btn-outline-success" data-filter="passed">Passed</button>
    <button type="button" class="btn btn-outline-danger" data-filter="failed">Failed</button>
</div>

<?php foreach ($review['items'] as $i => $it):
    $group = ($it['status'] === 'passed') ? 'passed' : 'failed'; ?>
    <div class="card mb-3 border-start border-4 border-<?php echo $border[$it['status']]; ?> review-item" data-group="<?php echo $group; ?>">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <h5 class="mb-2"><?php echo ($i + 1) . '. ' . h($it['question']); ?></h5>
                <span class="badge bg-<?php echo $badge[$it['status']]; ?>"><?php echo $label[$it['status']]; ?></span>
            </div>
            <p class="mb-1"><strong>Your answer:</strong>
                <?php echo $it['given'] !== '' ? h($it['given']) : '<em class="text-muted">No answer</em>'; ?>
            </p>
            <?php if (SHOW_CORRECT_ANSWERS && $it['status'] !== 'passed'): ?>
                <p class="mb-1 text-success"><strong>Correct answer:</strong> <?php echo h($it['correct']); ?></p>
            <?php endif; ?>
            <small class="text-muted">Marks: <?php echo fmt_num($it['awarded']) . ' / ' . fmt_num($it['points']); ?></small>
        </div>
    </div>
<?php endforeach; ?>

<a href="/student/dashboard.php" class="btn btn-primary mt-2">Back to Dashboard</a>

<script>
document.querySelectorAll('#filters button').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('#filters button').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var f = btn.getAttribute('data-filter');
        document.querySelectorAll('.review-item').forEach(function (card) {
            card.style.display = (f === 'all' || card.getAttribute('data-group') === f) ? '' : 'none';
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
