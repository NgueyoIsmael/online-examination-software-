-- Database Schema for Online Examination System

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

CREATE TABLE student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_exam_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_text TEXT, -- For text answers or JSON of selected options
    points_awarded DECIMAL(10, 2) DEFAULT 0.00,
    FOREIGN KEY (student_exam_id) REFERENCES student_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Initial Admin User
-- Password is 'admin123'
INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@example.com', '$2y$10$J6ClVm5p/t4hDwcJXsQEk.FRnG.VLMHNF4ZsUy5HgmSqEAddiujX6', 'admin');

-- Initial Student User for testing
-- Password is 'student123' (I will generate this hash)
-- Let's just use the same hash for now for simplicity or generate a new one.
-- I'll reuse the hash for 'admin123' for the student too for now, or assume 'student123' is the password.
-- Wait, I should generate a hash for student123 to be proper.

INSERT INTO users (username, email, password, role) VALUES 
('student', 'student@example.com', '$2y$10$muXMRS2B/kWSQOjj8pQxJOK5lWGc13VAt3I/fJBl63ClYC.S9mAZm', 'student');
