<?php
// admin/results_export.php (NEW) - downloads the results list as a CSV file that opens in Excel
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/db.php';

$exam_id = (int)($_GET['exam_id'] ?? 0);
$student_id = (int)($_GET['student_id'] ?? 0);

$where = [];
$types = '';
$params = [];
if ($exam_id)    { $where[] = 'se.exam_id = ?';    $types .= 'i'; $params[] = $exam_id; }
if ($student_id) { $where[] = 'se.student_id = ?'; $types .= 'i'; $params[] = $student_id; }

$sql = "SELECT u.username, u.email, e.title, e.pass_mark, se.score, se.status, se.is_late, se.minutes_late,
               se.start_time, se.end_time,
               (SELECT COALESCE(SUM(points), 0) FROM questions q WHERE q.exam_id = e.id) AS max_marks
        FROM student_exams se
        JOIN users u ON u.id = se.student_id
        JOIN exams e ON e.id = se.exam_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY e.title, u.username";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="results_' . date('Y-m-d_H-i') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // so Excel shows accents correctly
fputcsv($out, ['Student', 'Email', 'Exam', 'Score', 'Max marks', 'Percent', 'Result', 'Late', 'Minutes late', 'Status', 'Started', 'Finished']);

while ($r = $res->fetch_assoc()) {
    $percent = $r['max_marks'] > 0 ? round(($r['score'] / $r['max_marks']) * 100, 1) : 0;
    $pm = (int)$r['pass_mark'] ?: 50;
    $result = $r['status'] == 'submitted' ? ($percent >= $pm ? 'Passed' : 'Failed') : '';
    fputcsv($out, [
        $r['username'], $r['email'], $r['title'],
        fmt_num($r['score']), fmt_num($r['max_marks']), $percent . '%', $result,
        $r['is_late'] ? 'Yes' : 'No', $r['is_late'] ? $r['minutes_late'] : 0,
        $r['status'], $r['start_time'], $r['end_time'],
    ]);
}
fclose($out);
exit();
