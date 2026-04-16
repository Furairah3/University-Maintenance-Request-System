-- ============================================================
-- SMART HOSTEL MAINTENANCE REQUEST SYSTEM
-- Database Schema - MySQL 8.0+
-- CS415 Software Engineering | Group 11 | Ashesi University
-- ============================================================
-- Run this file: mysql -u root -p < schema.sql
-- ============================================================

-- Create database
CREATE DATABASE IF NOT EXISTS hostel_maintenance
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE hostel_maintenance;

-- ============================================================
-- 1. USERS TABLE
-- Stores all users: students, admins, maintenance staff
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin', 'staff') NOT NULL DEFAULT 'student',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    email_notifications TINYINT(1) NOT NULL DEFAULT 1,
    profile_image VARCHAR(255) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Constraints
    UNIQUE KEY uk_users_email (email),
    INDEX idx_users_role (role),
    INDEX idx_users_active (is_active),
    INDEX idx_users_role_active (role, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. CATEGORIES TABLE
-- Maintenance request categories (admin-managed)
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    icon VARCHAR(50) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Constraints
    UNIQUE KEY uk_categories_name (name),
    INDEX idx_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. REQUESTS TABLE
-- Core table: maintenance requests submitted by students
-- ============================================================
CREATE TABLE IF NOT EXISTS requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    status ENUM('Pending', 'In Progress', 'Completed') NOT NULL DEFAULT 'Pending',
    priority ENUM('High', 'Medium', 'Low') DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    assigned_to INT UNSIGNED DEFAULT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    location VARCHAR(200) DEFAULT NULL,
    room_number VARCHAR(20) DEFAULT NULL,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    completed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    CONSTRAINT fk_requests_category FOREIGN KEY (category_id)
        REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_requests_creator FOREIGN KEY (created_by)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_requests_assignee FOREIGN KEY (assigned_to)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,

    -- Indexes for performance
    INDEX idx_requests_status (status),
    INDEX idx_requests_priority (priority),
    INDEX idx_requests_creator (created_by),
    INDEX idx_requests_assignee (assigned_to),
    INDEX idx_requests_category (category_id),
    INDEX idx_requests_archived (is_archived),
    INDEX idx_requests_created (created_at),
    INDEX idx_requests_status_priority (status, priority),
    INDEX idx_requests_filter (status, category_id, priority, is_archived)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. ASSIGNMENTS TABLE
-- Tracks who assigned which request to which staff member
-- ============================================================
CREATE TABLE IF NOT EXISTS assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NOT NULL,
    notes TEXT DEFAULT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Foreign keys
    CONSTRAINT fk_assignments_request FOREIGN KEY (request_id)
        REFERENCES requests(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_assignments_staff FOREIGN KEY (staff_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_assignments_admin FOREIGN KEY (assigned_by)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,

    -- Indexes
    INDEX idx_assignments_request (request_id),
    INDEX idx_assignments_staff (staff_id),
    INDEX idx_assignments_date (assigned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. STATUS_HISTORY TABLE
-- Audit trail: every status change is logged
-- ============================================================
CREATE TABLE IF NOT EXISTS status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    old_status ENUM('Pending', 'In Progress', 'Completed') DEFAULT NULL,
    new_status ENUM('Pending', 'In Progress', 'Completed') NOT NULL,
    changed_by INT UNSIGNED NOT NULL,
    change_reason VARCHAR(500) DEFAULT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Foreign keys
    CONSTRAINT fk_status_history_request FOREIGN KEY (request_id)
        REFERENCES requests(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_status_history_user FOREIGN KEY (changed_by)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,

    -- Indexes
    INDEX idx_status_history_request (request_id),
    INDEX idx_status_history_date (changed_at),
    INDEX idx_status_history_request_date (request_id, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. NOTIFICATIONS TABLE
-- In-app and email notification records
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    request_id INT UNSIGNED DEFAULT NULL,
    type ENUM('status_change', 'assignment', 'reopen', 'system') NOT NULL DEFAULT 'status_change',
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    email_sent TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Foreign keys
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_notifications_request FOREIGN KEY (request_id)
        REFERENCES requests(id) ON UPDATE CASCADE ON DELETE SET NULL,

    -- Indexes
    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_unread (user_id, is_read),
    INDEX idx_notifications_date (created_at),
    INDEX idx_notifications_user_unread_date (user_id, is_read, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. ACTIVITY_LOG TABLE
-- General audit log for important system actions
-- ============================================================
CREATE TABLE IF NOT EXISTS activity_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    details JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Foreign keys
    CONSTRAINT fk_activity_log_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,

    -- Indexes
    INDEX idx_activity_user (user_id),
    INDEX idx_activity_entity (entity_type, entity_id),
    INDEX idx_activity_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. SESSIONS TABLE (for PHP session management)
-- ============================================================
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    payload TEXT NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Foreign keys
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,

    -- Indexes
    INDEX idx_sessions_user (user_id),
    INDEX idx_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRIGGERS
-- ============================================================

-- Auto-set completed_at when status changes to 'Completed'
DELIMITER //
CREATE TRIGGER trg_request_completed
    BEFORE UPDATE ON requests
    FOR EACH ROW
BEGIN
    IF NEW.status = 'Completed' AND OLD.status != 'Completed' THEN
        SET NEW.completed_at = NOW();
    END IF;
    IF NEW.status != 'Completed' THEN
        SET NEW.completed_at = NULL;
    END IF;
END//
DELIMITER ;

-- Auto-log initial status when request is created
DELIMITER //
CREATE TRIGGER trg_request_created_log
    AFTER INSERT ON requests
    FOR EACH ROW
BEGIN
    INSERT INTO status_history (request_id, old_status, new_status, changed_by)
    VALUES (NEW.id, NULL, 'Pending', NEW.created_by);
END//
DELIMITER ;

-- ============================================================
-- VIEWS (for common queries)
-- ============================================================

-- Dashboard view: requests with creator and assignee names
CREATE OR REPLACE VIEW vw_requests_full AS
SELECT
    r.id,
    r.title,
    r.description,
    r.status,
    r.priority,
    r.image_url,
    r.location,
    r.room_number,
    r.is_archived,
    r.created_at,
    r.updated_at,
    r.completed_at,
    c.id AS category_id,
    c.name AS category_name,
    c.icon AS category_icon,
    creator.id AS creator_id,
    creator.name AS creator_name,
    creator.email AS creator_email,
    assignee.id AS assignee_id,
    assignee.name AS assignee_name,
    assignee.email AS assignee_email
FROM requests r
    JOIN categories c ON r.category_id = c.id
    JOIN users creator ON r.created_by = creator.id
    LEFT JOIN users assignee ON r.assigned_to = assignee.id
WHERE r.is_archived = 0;

-- Staff workload view
CREATE OR REPLACE VIEW vw_staff_workload AS
SELECT
    u.id,
    u.name,
    u.email,
    u.is_active,
    COUNT(CASE WHEN r.status IN ('Pending', 'In Progress') THEN 1 END) AS active_tasks,
    COUNT(CASE WHEN r.status = 'Completed' THEN 1 END) AS completed_tasks,
    COUNT(r.id) AS total_tasks
FROM users u
    LEFT JOIN requests r ON u.id = r.assigned_to AND r.is_archived = 0
WHERE u.role = 'staff'
GROUP BY u.id, u.name, u.email, u.is_active;

-- Admin metrics view
CREATE OR REPLACE VIEW vw_admin_metrics AS
SELECT
    COUNT(*) AS total_requests,
    COUNT(CASE WHEN status = 'Pending' THEN 1 END) AS pending_count,
    COUNT(CASE WHEN status = 'In Progress' THEN 1 END) AS in_progress_count,
    COUNT(CASE WHEN status = 'Completed' THEN 1 END) AS completed_count,
    ROUND(AVG(CASE
        WHEN assigned_to IS NOT NULL
        THEN TIMESTAMPDIFF(HOUR, created_at, (
            SELECT MIN(a.assigned_at) FROM assignments a WHERE a.request_id = requests.id
        ))
    END), 1) AS avg_response_hours,
    ROUND(AVG(CASE
        WHEN completed_at IS NOT NULL
        THEN TIMESTAMPDIFF(HOUR, created_at, completed_at)
    END), 1) AS avg_completion_hours
FROM requests
WHERE is_archived = 0;

-- Requests by category view
CREATE OR REPLACE VIEW vw_requests_by_category AS
SELECT
    c.name AS category_name,
    c.icon AS category_icon,
    COUNT(r.id) AS request_count,
    COUNT(CASE WHEN r.status = 'Pending' THEN 1 END) AS pending,
    COUNT(CASE WHEN r.status = 'In Progress' THEN 1 END) AS in_progress,
    COUNT(CASE WHEN r.status = 'Completed' THEN 1 END) AS completed
FROM categories c
    LEFT JOIN requests r ON c.id = r.category_id AND r.is_archived = 0
WHERE c.is_active = 1
GROUP BY c.id, c.name, c.icon
ORDER BY request_count DESC;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default categories
INSERT INTO categories (name, description, icon, display_order) VALUES
('Electrical', 'Electrical sockets, wiring, lighting, power issues', 'zap', 1),
('Plumbing', 'Water leaks, pipes, toilets, drainage', 'droplets', 2),
('Furniture', 'Beds, desks, chairs, wardrobes, shelves', 'armchair', 3),
('HVAC', 'Air conditioning, heating, ventilation, fans', 'thermometer', 4),
('Other', 'Any maintenance issue not covered above', 'wrench', 5);

-- Default admin account (password: Admin@2026 — change immediately!)
INSERT INTO users (name, email, password_hash, role) VALUES
('System Administrator', 'admin@ashesi.edu.gh',
 '$2y$12$LJ3m4ys1rP5Rh8cVQK4.5Oq5Y7X3Z2vN6wM8kJ9pL0iH1gF2eD3a', 'admin');

-- Default maintenance staff accounts (password: Staff@2026 — change immediately!)
INSERT INTO users (name, email, password_hash, role) VALUES
('John Mensah', 'j.mensah@ashesi.edu.gh',
 '$2y$12$LJ3m4ys1rP5Rh8cVQK4.5Oq5Y7X3Z2vN6wM8kJ9pL0iH1gF2eD3a', 'staff'),
('Ama Darko', 'a.darko@ashesi.edu.gh',
 '$2y$12$LJ3m4ys1rP5Rh8cVQK4.5Oq5Y7X3Z2vN6wM8kJ9pL0iH1gF2eD3a', 'staff');

-- Sample student account (password: Student@2026 — for testing only!)
INSERT INTO users (name, email, password_hash, role) VALUES
('Test Student', 'student@ashesi.edu.gh',
 '$2y$12$LJ3m4ys1rP5Rh8cVQK4.5Oq5Y7X3Z2vN6wM8kJ9pL0iH1gF2eD3a', 'student');

-- ============================================================
-- SAMPLE DATA (for testing — remove before production)
-- ============================================================

-- Sample requests
INSERT INTO requests (title, description, category_id, status, priority, created_by, assigned_to, location, room_number) VALUES
('Broken light bulb in room 204', 'The ceiling light in room 204 has stopped working. Tried replacing the bulb but the socket seems faulty.', 1, 'Pending', 'Medium', 4, NULL, 'Block A', '204'),
('Water leak in bathroom', 'There is a constant drip from the shower head that worsens at night. The floor gets wet and slippery.', 2, 'In Progress', 'High', 4, 2, 'Block A', '204'),
('Broken desk drawer', 'The bottom drawer of my study desk is stuck and cannot be opened. The handle also seems loose.', 3, 'Completed', 'Low', 4, 3, 'Block A', '204');

-- Sample assignments
INSERT INTO assignments (request_id, staff_id, assigned_by) VALUES
(2, 2, 1),
(3, 3, 1);

-- Sample status history for the completed request
INSERT INTO status_history (request_id, old_status, new_status, changed_by) VALUES
(2, 'Pending', 'In Progress', 1),
(3, 'Pending', 'In Progress', 1),
(3, 'In Progress', 'Completed', 3);

-- Sample notifications
INSERT INTO notifications (user_id, request_id, type, title, message) VALUES
(4, 2, 'assignment', 'Request Assigned', 'Your request "Water leak in bathroom" has been assigned to John Mensah.'),
(4, 2, 'status_change', 'Status Updated', 'Your request "Water leak in bathroom" is now In Progress.'),
(4, 3, 'status_change', 'Request Completed', 'Your request "Broken desk drawer" has been marked as Completed.');

-- ============================================================
-- GRANT PERMISSIONS (adjust user/password as needed)
-- ============================================================
-- CREATE USER IF NOT EXISTS 'hostel_app'@'localhost' IDENTIFIED BY 'SecurePassword2026!';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON hostel_maintenance.* TO 'hostel_app'@'localhost';
-- FLUSH PRIVILEGES;

-- ============================================================
-- VERIFICATION QUERIES (run after setup to confirm)
-- ============================================================
-- SELECT 'Tables Created:' AS info, COUNT(*) AS count FROM information_schema.tables WHERE table_schema = 'hostel_maintenance';
-- SELECT * FROM vw_requests_full;
-- SELECT * FROM vw_staff_workload;
-- SELECT * FROM vw_admin_metrics;
-- SELECT * FROM vw_requests_by_category;
