-- COMPLETE database for a brand-new online MySQL database (everything we added is included).
-- Import this ONCE into the empty database you create at your database provider.
-- (If you keep your own data, export your local database from phpMyAdmin instead and
--  then only run vercel_sessions.sql.)

CREATE TABLE users (
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

CREATE TABLE exams (
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

CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    type ENUM('single_choice', 'multiple_choice', 'true_false', 'short_answer', 'fill_blank') NOT NULL,
    question_text TEXT NOT NULL,
    points INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

CREATE TABLE question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

CREATE TABLE student_exams (
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

CREATE TABLE student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_exam_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_text TEXT,
    points_awarded DECIMAL(10, 2) DEFAULT 0.00,
    FOREIGN KEY (student_exam_id) REFERENCES student_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE exam_enrollments (
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (exam_id, student_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE php_sessions (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    data MEDIUMBLOB NOT NULL,
    last_activity INT NOT NULL,
    INDEX idx_last_activity (last_activity)
);

-- First admin account:  username  admin   password  admin123
-- Log in and CHANGE THE PASSWORD straight away (My Profile and Password).
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@example.com', '$2y$10$J6ClVm5p/t4hDwcJXsQEk.FRnG.VLMHNF4ZsUy5HgmSqEAddiujX6', 'admin');
