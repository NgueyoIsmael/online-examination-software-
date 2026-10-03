-- Run this ONCE in phpMyAdmin (online_exam -> SQL tab) BEFORE using the new files

-- 1) Mark which exams were built by students
ALTER TABLE exams ADD COLUMN is_custom TINYINT(1) NOT NULL DEFAULT 0;

-- 2) Any custom exams students already made before today are marked too
UPDATE exams e
JOIN users u ON u.id = e.created_by
SET e.is_custom = 1
WHERE u.role = 'student';
