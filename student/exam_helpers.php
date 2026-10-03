<?php
// includes/exam_helpers.php  (NEW)
// Shared by the student pages and the admin download page.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// A student "passes" the whole exam at this percentage or higher
if (!defined('PASS_MARK_PERCENT')) define('PASS_MARK_PERCENT', 50);
// Set to false if you do NOT want students to see the correct answers after submitting
if (!defined('SHOW_CORRECT_ANSWERS')) define('SHOW_CORRECT_ANSWERS', true);

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('fmt_num')) {
    function fmt_num($v) {
        return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }
}
if (!function_exists('csrf_check')) {
    function csrf_check() {
        if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
            http_response_code(400);
            die("Invalid request. Go back and try again.");
        }
    }
}

// Turn the stored answer (option id, JSON list of ids, or text) into readable text
function review_answer_text($type, $raw, $optMap) {
    if ($raw === null || trim((string)$raw) === '') return '';
    if ($type === 'single_choice' || $type === 'true_false') {
        return $optMap[(int)$raw] ?? '';
    }
    if ($type === 'multiple_choice') {
        $ids = json_decode($raw, true);
        if (!is_array($ids)) return '';
        $texts = [];
        foreach ($ids as $id) {
            if (isset($optMap[(int)$id])) $texts[] = $optMap[(int)$id];
        }
        return implode(', ', $texts);
    }
    return (string)$raw;
}

// Loads one submitted attempt with every question marked passed / failed / partial / unanswered.
// $student_id = restrict to that student (use null for admin).
function load_review($conn, $attempt_id, $student_id = null) {
    $sql = "SELECT se.*, e.title, u.username
            FROM student_exams se
            JOIN exams e ON e.id = se.exam_id
            JOIN users u ON u.id = se.student_id
            WHERE se.id = ?";
    $types = "i";
    $params = [$attempt_id];
    if ($student_id !== null) {
        $sql .= " AND se.student_id = ?";
        $types .= "i";
        $params[] = $student_id;
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    if (!$attempt || $attempt['status'] !== 'submitted') {
        return null;
    }

    $stmt = $conn->prepare("SELECT q.id, q.type, q.question_text, q.points, sa.answer_text, sa.points_awarded
                            FROM questions q
                            LEFT JOIN student_answers sa ON sa.question_id = q.id AND sa.student_exam_id = ?
                            WHERE q.exam_id = ?
                            ORDER BY q.id");
    $stmt->bind_param("ii", $attempt_id, $attempt['exam_id']);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $opt_stmt = $conn->prepare("SELECT id, option_text, is_correct FROM question_options WHERE question_id = ? ORDER BY id");

    $items = [];
    $count = ['passed' => 0, 'failed' => 0, 'partial' => 0, 'unanswered' => 0];
    $max = 0.0;

    foreach ($rows as $row) {
        $qid = (int)$row['id'];
        $opt_stmt->bind_param("i", $qid);
        $opt_stmt->execute();
        $opts = $opt_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $map = [];
        $correct = [];
        foreach ($opts as $o) {
            $map[(int)$o['id']] = $o['option_text'];
            if ($o['is_correct']) $correct[] = $o['option_text'];
        }

        $given   = review_answer_text($row['type'], $row['answer_text'], $map);
        $awarded = (float)($row['points_awarded'] ?? 0);
        $points  = (float)$row['points'];
        $max    += $points;

        if ($given === '')           $status = 'unanswered';
        elseif ($awarded >= $points) $status = 'passed';
        elseif ($awarded > 0)        $status = 'partial';
        else                         $status = 'failed';
        $count[$status]++;

        $items[] = [
            'question' => $row['question_text'],
            'type'     => $row['type'],
            'given'    => $given,
            'correct'  => implode(', ', $correct),
            'awarded'  => $awarded,
            'points'   => $points,
            'status'   => $status,
        ];
    }

    $score = (float)$attempt['score'];
    $percent = $max > 0 ? round(($score / $max) * 100, 1) : 0;

    return [
        'attempt' => $attempt,
        'items'   => $items,
        'summary' => [
            'passed'     => $count['passed'],
            'failed'     => $count['failed'] + $count['partial'] + $count['unanswered'],
            'unanswered' => $count['unanswered'],
            'total'      => count($items),
            'score'      => $score,
            'max'        => $max,
            'percent'    => $percent,
            'overall_passed' => $percent >= PASS_MARK_PERCENT,
        ],
    ];
}

function review_filename($review) {
    $name = $review['attempt']['title'] . '_' . $review['attempt']['username'];
    $name = trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $name), '_');
    return $name !== '' ? $name : 'result';
}

// Builds the printable / Word document (plain HTML with inline CSS)
function render_review_document($review, $mode = 'pdf') {
    $a = $review['attempt'];
    $s = $review['summary'];
    $labels = ['passed' => 'PASSED', 'failed' => 'FAILED', 'partial' => 'PARTIAL', 'unanswered' => 'NOT ANSWERED'];
    $colors = ['passed' => '#198754', 'failed' => '#dc3545', 'partial' => '#b58100', 'unanswered' => '#6c757d'];
    $title = review_filename($review);
    ob_start(); ?>
<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<title><?php echo h($title); ?></title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #222; margin: 24px; }
    h1 { font-size: 20pt; margin: 0 0 6px 0; }
    h2 { font-size: 14pt; margin: 22px 0 8px 0; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #555; padding: 5px 7px; vertical-align: top; text-align: left; font-size: 10.5pt; }
    th { background: #e9ecef; }
    .meta td { border: none; padding: 2px 0; }
    .noprint { background: #fff3cd; border: 1px solid #ffe69c; padding: 10px; margin-bottom: 16px; font-size: 10.5pt; }
    @media print { .noprint { display: none; } body { margin: 0; } }
</style>
</head>
<body>
<?php if ($mode === 'pdf'): ?>
<div class="noprint">
    To save as PDF: in the print window, set <strong>Destination</strong> to <strong>Save as PDF</strong>, then click Save.
    <button onclick="window.print()">Print / Save as PDF</button>
</div>
<?php endif; ?>

<h1><?php echo h($a['title']); ?></h1>
<table class="meta">
    <tr><td><strong>Student:</strong> <?php echo h($a['username']); ?></td>
        <td><strong>Date:</strong> <?php echo h($a['end_time']); ?></td></tr>
</table>

<h2>Summary</h2>
<table>
    <tr>
        <th>Score</th><th>Percent</th><th>Result</th><th>Questions passed</th><th>Questions failed</th>
    </tr>
    <tr>
        <td><?php echo fmt_num($s['score']) . ' / ' . fmt_num($s['max']); ?></td>
        <td><?php echo $s['percent']; ?>%</td>
        <td><strong><?php echo $s['overall_passed'] ? 'PASSED' : 'FAILED'; ?></strong> (pass mark <?php echo PASS_MARK_PERCENT; ?>%)</td>
        <td><?php echo $s['passed']; ?></td>
        <td><?php echo $s['failed']; ?></td>
    </tr>
</table>

<h2>Question review</h2>
<table>
    <tr>
        <th style="width:4%">#</th>
        <th>Question</th>
        <th>Your answer</th>
        <?php if (SHOW_CORRECT_ANSWERS): ?><th>Correct answer</th><?php endif; ?>
        <th style="width:9%">Marks</th>
        <th style="width:12%">Result</th>
    </tr>
    <?php foreach ($review['items'] as $i => $it): ?>
    <tr>
        <td><?php echo $i + 1; ?></td>
        <td><?php echo h($it['question']); ?></td>
        <td><?php echo $it['given'] !== '' ? h($it['given']) : '<em>No answer</em>'; ?></td>
        <?php if (SHOW_CORRECT_ANSWERS): ?><td><?php echo h($it['correct']); ?></td><?php endif; ?>
        <td><?php echo fmt_num($it['awarded']) . ' / ' . fmt_num($it['points']); ?></td>
        <td style="color:<?php echo $colors[$it['status']]; ?>"><strong><?php echo $labels[$it['status']]; ?></strong></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php if ($mode === 'pdf'): ?>
<script>window.onload = function () { window.print(); };</script>
<?php endif; ?>
</body>
</html>
<?php
    return ob_get_clean();
}

// Sends the document to the browser: format 'doc' = Word download, 'pdf' = print / Save-as-PDF page
function send_review_export($review, $format) {
    if ($format === 'doc') {
        header('Content-Type: application/msword; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . review_filename($review) . '.doc"');
        echo render_review_document($review, 'doc');
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        echo render_review_document($review, 'pdf');
    }
    exit();
}
