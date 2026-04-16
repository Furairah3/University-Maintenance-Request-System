-- ============================================================
-- SMART HOSTEL MAINTENANCE REQUEST SYSTEM
-- Schema for InfinityFree (no CREATE DATABASE / USE)
-- Import this into your if0_41681787_hostel_maintenance database
-- ============================================================

-- 1. USERS TABLE
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
    UNIQUE KEY uk_users_email (email),
    INDEX idx_users_role (role),
    INDEX idx_users_active (is_active),
    INDEX idx_users_role_active (role, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CATEGORIES TABLE
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    icon VARCHAR(50) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_categories_name (name),
    INDEX idx_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. REQUESTS TABLE
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
    CONSTRAINT fk_requests_category FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_requests_creator FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_requests_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_requests_status (status),
    INDEX idx_requests_priority (priority),
    INDEX idx_requests_creator (created_by),
    INDEX idx_requests_assignee (assigned_to),
    INDEX idx_requests_category (category_id),
    INDEX idx_requests_archived (is_archived),
    INDEX idx_requests_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ASSIGNMENTS TABLE
CREATE TABLE IF NOT EXISTS assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NOT NULL,
    notes TEXT DEFAULT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_assignments_request FOREIGN KEY (request_id) REFERENCES requests(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_assignments_staff FOREIGN KEY (staff_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_assignments_admin FOREIGN KEY (assigned_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_assignments_request (request_id),
    INDEX idx_assignments_staff (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. STATUS HISTORY TABLE
CREATE TABLE IF NOT EXISTS status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    old_status ENUM('Pending', 'In Progress', 'Completed') DEFAULT NULL,
    new_status ENUM('Pending', 'In Progress', 'Completed') NOT NULL,
    changed_by INT UNSIGNED NOT NULL,
    change_reason VARCHAR(500) DEFAULT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_status_history_request FOREIGN KEY (request_id) REFERENCES requests(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_status_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_status_history_request (request_id),
    INDEX idx_status_history_date (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. NOTIFICATIONS TABLE
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
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_notifications_request FOREIGN KEY (request_id) REFERENCES requests(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_unread (user_id, is_read),
    INDEX idx_notifications_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. ACTIVITY LOG TABLE
CREATE TABLE IF NOT EXISTS activity_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    details JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_activity_user (user_id),
    INDEX idx_activity_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. SESSIONS TABLE
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    payload TEXT NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_sessions_user (user_id),
    INDEX idx_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEED CATEGORIES
INSERT INTO categories (name, description, icon, display_order) VALUES
('Electrical', 'Electrical sockets, wiring, lighting, power issues', 'zap', 1),
('Plumbing', 'Water leaks, pipes, toilets, drainage', 'droplets', 2),
('Furniture', 'Beds, desks, chairs, wardrobes, shelves', 'armchair', 3),
('HVAC', 'Air conditioning, heating, ventilation, fans', 'thermometer', 4),
('Other', 'Any maintenance issue not covered above', 'wrench', 5);
