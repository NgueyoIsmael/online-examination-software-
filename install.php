<?php
// install.php  (NEW, goes in the project ROOT next to index.php)
// Creates ALL the tables in your empty online database with one click.
// It does nothing if the tables already exist, so it is safe to open again.
require_once __DIR__ . '/config/db.php';

function inst_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$needed = ['users', 'exams', 'questions', 'question_options', 'student_exams', 'student_answers',
           'announcements', 'exam_enrollments', 'php_sessions'];
$missing = [];
foreach ($needed as $t) {
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    if (!$r || $r->num_rows == 0) $missing[] = $t;
}
$installed = count($missing) === 0;
$done = false;
$error = null;

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reset_token VARCHAR(64) NULL,
    reset_expires DATETIME NULL,
    full_name VARCHAR(100) NULL
);

CREATE TABLE IF NOT EXISTS exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INT NOT NULL DEFAULT 60,
    total_marks INT DEFAULT 0,
    status ENUM('draft', 'open', 'closed') DEFAULT 'draft',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_custom TINYINT(1) NOT NULL DEFAULT 0,
    scheduled_start DATETIME NULL,
    late_minutes INT NOT NULL DEFAULT 0,
    late_loses_time TINYINT(1) NOT NULL DEFAULT 1,
    max_attempts INT NOT NULL DEFAULT 0,
    pass_mark INT NOT NULL DEFAULT 50,
    shuffle_questions TINYINT(1) NOT NULL DEFAULT 0,
    shuffle_options TINYINT(1) NOT NULL DEFAULT 0,
    access_code VARCHAR(20) NULL,
    notified_at DATETIME NULL,
    enrollment_required TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    type ENUM('single_choice', 'multiple_choice', 'true_false', 'short_answer', 'fill_blank') NOT NULL,
    question_text TEXT NOT NULL,
    points INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS student_exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    start_time DATETIME,
    end_time DATETIME,
    score DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('pending', 'in_progress', 'submitted') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_late TINYINT(1) NOT NULL DEFAULT 0,
    minutes_late INT NOT NULL DEFAULT 0,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_exam_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_text TEXT,
    points_awarded DECIMAL(10, 2) DEFAULT 0.00,
    FOREIGN KEY (student_exam_id) REFERENCES student_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS exam_enrollments (
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (exam_id, student_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS php_sessions (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    data MEDIUMBLOB NOT NULL,
    last_activity INT NOT NULL,
    INDEX idx_last_activity (last_activity)
);

INSERT IGNORE INTO users (username, email, password, role) VALUES
('admin', 'admin@example.com', '$2y$10$J6ClVm5p/t4hDwcJXsQEk.FRnG.VLMHNF4ZsUy5HgmSqEAddiujX6', 'admin');
SQL;
    try {
        $conn->multi_query($sql);
        do {
            if ($res = $conn->store_result()) $res->free();
        } while ($conn->more_results() && $conn->next_result());
        $done = true;
        $installed = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set up the database</title>
<link rel="icon" type="image/svg+xml" href="/public/img/logo.svg">
<style>
    body { margin:0; font-family: 'Segoe UI', Arial, sans-serif; background:#eef2ff; color:#0f172a; display:flex; min-height:100vh; align-items:center; justify-content:center; }
    .box { background:#fff; max-width:520px; width:92%; padding:34px; border-radius:18px; box-shadow:0 18px 50px rgba(30,58,138,.18); text-align:center; }
    h1 { margin:12px 0 6px; font-size:1.5rem; }
    p { color:#475569; line-height:1.5; }
    .btn { display:inline-block; padding:12px 26px; border:0; border-radius:12px; background:#2563eb; color:#fff; font-size:1rem; font-weight:600; cursor:pointer; text-decoration:none; }
    .btn.alt { background:#e0e7ff; color:#1e3a8a; margin:4px; }
    .ok { color:#15803d; font-weight:600; }
    .bad { color:#b91c1c; word-break:break-word; }
    code { background:#f1f5f9; padding:2px 6px; border-radius:6px; }
</style>
</head>
<body>
<div class="box">
    <img src="/public/img/logo.svg" width="72" height="72" alt="">
    <?php if ($done): ?>
        <h1 class="ok">All set!</h1>
        <p>The database is ready. A first admin account was created:<br>
           username <code>admin</code> and password <code>admin123</code>.<br>
           <strong>Log in and change that password straight away.</strong></p>
        <a class="btn" href="/auth/admin_login.php">Go to admin login</a>
    <?php elseif ($installed): ?>
        <h1 class="ok">Already installed</h1>
        <p>All the tables are already in the database. Nothing was changed.</p>
        <a class="btn alt" href="/auth/login.php">Student login</a>
        <a class="btn alt" href="/auth/admin_login.php">Admin login</a>
    <?php else: ?>
        <h1>Set up the database</h1>
        <p>The connection works. The database is still empty, so the tables must be created once.</p>
        <?php if ($error): ?><p class="bad">Something went wrong: <?php echo inst_h($error); ?></p><?php endif; ?>
        <form method="POST"><button class="btn" type="submit">Create the tables now</button></form>
    <?php endif; ?>
</div>
</body>
</html>
