<?php
// student/submit_exam.php  (REPLACES the old file)
// Grades EVERY question of the exam (unanswered ones are saved with 0 marks),
// and only accepts options that really belong to the question.
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/exam_helpers.php';

$attempt_id = (int)($_POST['attempt_id'] ?? $_GET['id'] ?? 0);
if (!$attempt_id) {
    die("Invalid attempt ID");
}

$stmt = $conn->prepare("SELECT * FROM student_exams WHERE id = ? AND student_id = ?");
$stmt->bind_param("ii", $attempt_id, $_SESSION['user_id']);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();

if (!$attempt || $attempt['status'] !== 'in_progress') {
    if ($attempt && $attempt['status'] == 'submitted') {
        header("Location: /student/result.php?id=$attempt_id");
        exit();
    }
    die("Invalid attempt status");
}

$answers = $_POST['answers'] ?? [];
if (!is_array($answers)) $answers = [];

function normalize_text($s) {
    $s = trim((string)$s);
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

$q_stmt = $conn->prepare("SELECT id, type, points FROM questions WHERE exam_id = ? ORDER BY id");
$q_stmt->bind_param("i", $attempt['exam_id']);
$q_stmt->execute();
$questions = $q_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$opt_stmt = $conn->prepare("SELECT id, option_text, is_correct FROM question_options WHERE question_id = ?");
$total_score = 0;

try {
    $conn->begin_transaction();
    $ins_stmt = $conn->prepare("INSERT INTO student_answers (student_exam_id, question_id, answer_text, points_awarded) VALUES (?, ?, ?, ?)");

    foreach ($questions as $question) {
        $q_id = (int)$question['id'];
        $points = (float)$question['points'];
        $type = $question['type'];
        $ans = $answers[$q_id] ?? null;

        $opt_stmt->bind_param("i", $q_id);
        $opt_stmt->execute();
        $opts = $opt_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $valid = [];
        $correct_ids = [];
        $correct_texts = [];
        foreach ($opts as $o) {
            $valid[(int)$o['id']] = true;
            if ($o['is_correct']) {
                $correct_ids[] = (int)$o['id'];
                $correct_texts[] = $o['option_text'];
            }
        }

        $stored = '';
        $awarded = 0;

        if (($type == 'single_choice' || $type == 'true_false') && $ans !== null && !is_array($ans)) {
            $sel = (int)$ans;
            if (isset($valid[$sel])) {
                $stored = (string)$sel;
                if (in_array($sel, $correct_ids, true)) $awarded = $points;
            }
        } elseif ($type == 'multiple_choice' && is_array($ans)) {
            $sel = array_values(array_unique(array_map('intval', $ans)));
            $sel = array_values(array_filter($sel, function ($id) use ($valid) { return isset($valid[$id]); }));
            sort($sel);
            if ($sel) {
                $stored = json_encode($sel);
                $c = $correct_ids;
                sort($c);
                if ($sel === $c) $awarded = $points; // must match exactly
            }
        } elseif (($type == 'short_answer' || $type == 'fill_blank') && is_string($ans)) {
            $text = trim($ans);
            $stored = $text;
            if ($text !== '' && !empty($correct_texts) && normalize_text($text) === normalize_text($correct_texts[0])) {
                $awarded = $points;
            }
        }

        $total_score += $awarded;
        $ins_stmt->bind_param("iisd", $attempt_id, $q_id, $stored, $awarded);
        $ins_stmt->execute();
    }

    $end_time = date('Y-m-d H:i:s');
    $up_stmt = $conn->prepare("UPDATE student_exams SET status = 'submitted', end_time = ?, score = ? WHERE id = ? AND status = 'in_progress'");
    $up_stmt->bind_param("sdi", $end_time, $total_score, $attempt_id);
    $up_stmt->execute();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    die("Could not submit your exam. Please go back and try again.");
}

header("Location: /student/result.php?id=$attempt_id");
exit();
