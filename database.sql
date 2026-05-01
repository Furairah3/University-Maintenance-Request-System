-- =====================================================
-- Smart Hostel Management System — Database Schema
-- Ashesi University CS415 Group 11
-- =====================================================

CREATE DATABASE IF NOT EXISTS smart_hostel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_hostel;

-- USERS TABLE
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','admin','staff') NOT NULL DEFAULT 'student',
    room_number VARCHAR(20) DEFAULT NULL,
    avatar_path VARCHAR(255) DEFAULT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- EMAIL VERIFICATIONS TABLE — one pending OTP per user
CREATE TABLE IF NOT EXISTS email_verifications (
    user_id INT PRIMARY KEY,
    code CHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    attempts TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- CATEGORIES TABLE
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO categories (name) VALUES
('Electrical'), ('Plumbing'), ('Furniture'), ('HVAC'), ('Other');

-- REQUESTS TABLE
CREATE TABLE IF NOT EXISTS requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    status ENUM('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
    priority ENUM('High','Medium','Low') DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- STATUS HISTORY TABLE
CREATE TABLE IF NOT EXISTS status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    old_status VARCHAR(50) DEFAULT NULL,
    new_status VARCHAR(50) NOT NULL,
    changed_by INT DEFAULT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- NOTIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    request_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- REVIEWS TABLE — student rating + comment on completed work
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL UNIQUE,
    rating TINYINT NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- REMINDERS TABLE — student bumps on slow-moving requests
CREATE TABLE IF NOT EXISTS reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    student_id INT NOT NULL,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (request_id, created_at),
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- DEFAULT ADMIN ACCOUNT (password: Admin@1234) — pre-verified
INSERT INTO users (name, email, password, role, is_verified) VALUES
('Hostel Admin', 'admin@ashesi.edu.gh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

-- SAMPLE STUDENT (password: Student@1234) — pre-verified
INSERT INTO users (name, email, password, role, room_number, is_verified) VALUES
('Kwame Asante', 'k.asante@ashesi.edu.gh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'B-214', 1);

-- SAMPLE STAFF (password: Staff@1234) — pre-verified
INSERT INTO users (name, email, password, role, is_verified) VALUES
('Jonas Mensah', 'j.mensah@ashesi.edu.gh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 1);

-- SAMPLE REQUESTS
INSERT INTO requests (student_id, title, description, category, status, priority, assigned_to) VALUES
(2, 'Power socket not working — desk area', 'The right-hand socket near my study desk stopped working suddenly. Other sockets in the room work fine.', 'Electrical', 'Pending', 'High', NULL),
(2, 'Shower drain completely blocked', 'The shower drain in the bathroom is completely blocked. Water accumulates and does not drain at all.', 'Plumbing', 'In Progress', 'High', 3),
(2, 'Broken desk chair', 'The armrest on my desk chair has completely detached from the frame.', 'Furniture', 'Completed', 'Low', 3);

INSERT INTO status_history (request_id, old_status, new_status, changed_by) VALUES
(1, NULL, 'Pending', 2),
(2, NULL, 'Pending', 2),
(2, 'Pending', 'In Progress', 3),
(3, NULL, 'Pending', 2),
(3, 'Pending', 'In Progress', 3),
(3, 'In Progress', 'Completed', 3);
