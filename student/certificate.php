<?php
// student/certificate.php (NEW) - certificate for a PASSED exam (use Print -> Save as PDF)
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/exam_helpers.php';
require_once __DIR__ . '/../includes/site_info.php';

$attempt_id = (int)($_GET['id'] ?? 0);
$review = load_review($conn, $attempt_id, $_SESSION['user_id']);
if (!$review || !$review['summary']['overall_passed']) {
    header("Location: /student/dashboard.php");
    exit();
}
$a = $review['attempt'];
$s = $review['summary'];

$stmt = $conn->prepare("SELECT username, full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$has_full_name = trim($u['full_name'] ?? '') !== '';
$name = $has_full_name ? $u['full_name'] : $u['username'];

$code = certificate_code($attempt_id);
$verify_url = site_base_url() . '/verify.php?id=' . $attempt_id . '&code=' . $code;
$date = date('d F Y', strtotime($a['end_time']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Certificate - <?php echo h($a['title']); ?></title>
<link rel="icon" type="image/svg+xml" href="/public/img/logo.svg">
<style>
    @page { size: A4 landscape; margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e9ecef; font-family: Georgia, 'Times New Roman', serif; color: #222;
           -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bar { max-width: 277mm; margin: 12px auto; padding: 10px 14px; background: #fff3cd; border: 1px solid #ffe69c;
           font-family: Arial, sans-serif; font-size: 14px; }
    .bar button { padding: 6px 14px; margin-left: 10px; cursor: pointer; }
    .cert { width: 277mm; height: 190mm; margin: 10px auto 30px; background: #fff; border: 10px double #1f3c88;
            padding: 14mm 18mm; text-align: center; position: relative; }
    .org { letter-spacing: 4px; text-transform: uppercase; font-size: 13pt; color: #1f3c88; }
    h1 { font-size: 40pt; margin: 6mm 0 0; color: #1f3c88; letter-spacing: 6px; }
    .sub { font-size: 16pt; letter-spacing: 3px; text-transform: uppercase; margin-bottom: 8mm; }
    .small { font-size: 13pt; color: #555; }
    .name { font-size: 34pt; margin: 5mm auto; padding: 0 10mm 2mm; border-bottom: 2px solid #1f3c88; display: inline-block; min-width: 140mm; font-style: italic; }
    .exam { font-size: 24pt; font-weight: bold; margin: 4mm 0; }
    .foot { position: absolute; left: 18mm; right: 18mm; bottom: 12mm; display: flex; justify-content: space-between; align-items: flex-end;
            font-size: 11pt; color: #444; }
    .sign { border-top: 1px solid #444; padding-top: 2mm; width: 60mm; }
    .id { font-family: 'Courier New', monospace; font-size: 10pt; }
    @media print {
        body { background: #fff; }
        .bar { display: none; }
        .cert { margin: 0; width: 297mm; height: 209mm; }
    }
</style>
</head>
<body>
<div class="bar">
    To save as PDF: click the button, then set <strong>Destination</strong> to <strong>Save as PDF</strong>.
    <button onclick="window.print()">Print / Save as PDF</button>
    <?php if (!$has_full_name): ?>
        <br>Your certificate shows your username. <a href="/auth/profile.php">Add your full name in your profile</a>, then reload this page.
    <?php endif; ?>
</div>

<div class="cert">
    <div class="org"><img src="/public/img/logo.svg" width="46" height="46" alt="" style="vertical-align:middle;margin-right:10px;"><span style="vertical-align:middle;"><?php echo h(SITE_NAME); ?></span></div>
    <h1>CERTIFICATE</h1>
    <div class="sub">of Achievement</div>
    <div class="small">This is to certify that</div>
    <div class="name"><?php echo h($name); ?></div>
    <div class="small">has successfully passed the exam</div>
    <div class="exam"><?php echo h($a['title']); ?></div>
    <div class="small">with a score of <strong><?php echo fmt_num($s['score']) . ' / ' . fmt_num($s['max']); ?></strong> (<?php echo $s['percent']; ?>%)</div>

    <div class="foot">
        <div>
            <div><?php echo h($date); ?></div>
            <div class="sign">Date</div>
        </div>
        <div style="text-align:center;">
            <div class="id">Certificate ID: <?php echo h($code); ?></div>
            <div class="id">Verify: <?php echo h($verify_url); ?></div>
        </div>
        <div>
            <div style="height:6mm"></div>
            <div class="sign">Examination Board</div>
        </div>
    </div>
</div>
</body>
</html>
