-- Student Companion System — Database Schema
-- Run once: CREATE DATABASE student_companion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Create DB (optional: IF NOT EXISTS add pannalam)
CREATE DATABASE IF NOT EXISTS student_companion 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE student_companion;
SET FOREIGN_KEY_CHECKS = 0;
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- 1. departments
CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. users
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','mentor','learner') NOT NULL,
    department_id INT UNSIGNED NULL,
    year TINYINT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    force_password_reset TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. subjects
CREATE TABLE IF NOT EXISTS subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    department_id INT UNSIGNED NOT NULL,
    year TINYINT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. mentor_subjects
CREATE TABLE IF NOT EXISTS mentor_subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mentor_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    department_id INT UNSIGNED NOT NULL,
    year TINYINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_mentor_subject (mentor_id, subject_id),
    FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. mentor_students
CREATE TABLE IF NOT EXISTS mentor_students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mentor_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_mentor_student (mentor_id, student_id),
    FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. attendance
CREATE TABLE IF NOT EXISTS attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    status ENUM('present','absent') NOT NULL,
    marked_by INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_attendance (student_id, subject_id, date),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. assignments
CREATE TABLE IF NOT EXISTS assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mentor_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    file_path VARCHAR(255) NULL,
    deadline DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. assignment_submissions
CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    marks TINYINT UNSIGNED NULL,
    feedback TEXT NULL,
    status ENUM('submitted','late','evaluated') NOT NULL DEFAULT 'submitted',
    UNIQUE KEY uq_submission (assignment_id, student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. study_materials
CREATE TABLE IF NOT EXISTS study_materials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mentor_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    type ENUM('pdf','ppt','image','video') NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_by INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    target_type ENUM('all','department','mentor_students','user') NOT NULL DEFAULT 'all',
    target_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. notification_reads
CREATE TABLE IF NOT EXISTS notification_reads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notif_read (notification_id, user_id),
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. leave_requests
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requested_by INT UNSIGNED NOT NULL,
    requested_to_role ENUM('mentor','admin') NOT NULL,
    reason TEXT NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    approved_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. password_resets
CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. audit_logs
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_table VARCHAR(100) NULL,
    target_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Seed data
INSERT INTO departments (name) VALUES ('Computer Science'), ('Information Technology');

-- Passwords for all seeded users below is: Password@123
-- Hash generated using password_hash('Password@123', PASSWORD_DEFAULT)
SET @pwd = '$2y$10$vG/2oG8xR.KOTYwS6K5/Z.r80h51H32W0a62Yl.7f9Hh0Q8P8JvGO';

-- HOD account (password: Admin@123)
INSERT INTO users (name, email, password_hash, role, department_id, status, force_password_reset)
VALUES ('HOD Admin', 'admin@school.edu', 
        '$2y$12$dG8ItagSLjmNSq84nklwn.qid78h0tBb6P0hoMQ0uAFo5ssArIFI.', 
        'admin', 1, 'active', 0);

-- Mentors
INSERT INTO users (name, email, password_hash, role, department_id, status, force_password_reset) VALUES 
('Dr. Alan Turing', 'alan@school.edu', @pwd, 'mentor', 1, 'active', 0),
('Dr. Ada Lovelace', 'ada@school.edu', @pwd, 'mentor', 1, 'active', 0),
('Prof. John von Neumann', 'john@school.edu', @pwd, 'mentor', 2, 'active', 0);

-- Learners (Students)
INSERT INTO users (name, email, password_hash, role, department_id, year, status, force_password_reset) VALUES 
('Alice Smith', 'alice@student.edu', @pwd, 'learner', 1, 2, 'active', 0),
('Bob Jones', 'bob@student.edu', @pwd, 'learner', 1, 2, 'active', 0),
('Charlie Brown', 'charlie@student.edu', @pwd, 'learner', 1, 3, 'active', 0),
('Diana Prince', 'diana@student.edu', @pwd, 'learner', 2, 2, 'active', 0),
('Evan Wright', 'evan@student.edu', @pwd, 'learner', 2, 3, 'active', 0);

-- Sample subjects
INSERT INTO subjects (name, department_id, year, semester) VALUES
('Data Structures', 1, 2, 1),
('Operating Systems', 1, 2, 2),
('Database Management', 1, 3, 1),
('Web Technologies', 1, 3, 2),
('Networking', 2, 2, 1),
('Software Engineering', 2, 3, 1);

-- Mentor Subjects (Who teaches what)
-- Alan teaches Data Structures and OS
INSERT INTO mentor_subjects (mentor_id, subject_id, department_id, year) VALUES 
(2, 1, 1, 2), (2, 2, 1, 2);
-- Ada teaches Database Management and Web Tech
INSERT INTO mentor_subjects (mentor_id, subject_id, department_id, year) VALUES 
(3, 3, 1, 3), (3, 4, 1, 3);
-- John teaches Networking and Software Eng
INSERT INTO mentor_subjects (mentor_id, subject_id, department_id, year) VALUES 
(4, 5, 2, 2), (4, 6, 2, 3);

-- Mentor Students (Class Advisor mapping)
-- Alan advises 2nd year CS students (Alice, Bob)
INSERT INTO mentor_students (mentor_id, student_id) VALUES (2, 5), (2, 6);
-- Ada advises 3rd year CS students (Charlie)
INSERT INTO mentor_students (mentor_id, student_id) VALUES (3, 7);
-- John advises IT students (Diana, Evan)
INSERT INTO mentor_students (mentor_id, student_id) VALUES (4, 8), (4, 9);

-- Dummy Attendance (for Alice in Data Structures)
INSERT INTO attendance (student_id, subject_id, date, status, marked_by) VALUES
(5, 1, CURDATE() - INTERVAL 1 DAY, 'present', 2),
(5, 1, CURDATE() - INTERVAL 2 DAY, 'present', 2),
(5, 1, CURDATE() - INTERVAL 3 DAY, 'absent', 2),
(5, 1, CURDATE() - INTERVAL 4 DAY, 'present', 2),
(6, 1, CURDATE() - INTERVAL 1 DAY, 'present', 2),
(6, 1, CURDATE() - INTERVAL 2 DAY, 'absent', 2);

-- Dummy Assignments
INSERT INTO assignments (mentor_id, subject_id, title, description, deadline) VALUES
(2, 1, 'Trees and Graphs Implementation', 'Implement a BST in C++', NOW() + INTERVAL 5 DAY),
(3, 3, 'SQL Normalization Assignment', 'Normalize the given schema to 3NF', NOW() + INTERVAL 2 DAY);

-- Dummy Notifications
INSERT INTO notifications (created_by, title, message, target_type) VALUES
(1, 'Welcome to the New Term', 'Please check your updated timetables.', 'all'),
(2, 'Data Structures Extra Class', 'There will be an extra lab session this Friday.', 'mentor_students');

-- Dummy Leave Requests
INSERT INTO leave_requests (requested_by, requested_to_role, reason, from_date, to_date, status) VALUES
(5, 'mentor', 'Family function', CURDATE() + INTERVAL 2 DAY, CURDATE() + INTERVAL 4 DAY, 'pending'),
(2, 'admin', 'Attending a conference', CURDATE() + INTERVAL 10 DAY, CURDATE() + INTERVAL 12 DAY, 'pending');
