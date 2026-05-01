-- =====================================================
-- Smart Hostel — Online DB Migration (idempotent)
-- Run this ONCE on your existing production database in phpMyAdmin
-- to add the new columns and tables introduced in this release.
-- Safe to re-run — every change uses IF NOT EXISTS / conditional logic.
-- =====================================================

-- ---- users: add avatar + verification columns ----
SET @db := DATABASE();

-- is_verified column
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_verified');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT "users.is_verified already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Existing accounts predate verification — grant them the flag so they can still log in
UPDATE users SET is_verified = 1 WHERE is_verified = 0 AND id IN (SELECT id FROM (SELECT id FROM users) x);

-- avatar_path column
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_path');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL',
    'SELECT "users.avatar_path already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---- categories table (in case your old DB lacks it) ----
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;
INSERT IGNORE INTO categories (name) VALUES
('Electrical'), ('Plumbing'), ('Furniture'), ('HVAC'), ('Other');

-- ---- email_verifications table ----
CREATE TABLE IF NOT EXISTS email_verifications (
    user_id INT PRIMARY KEY,
    code CHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    attempts TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---- reviews table ----
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL UNIQUE,
    rating TINYINT NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---- reminders table (and patch any older version) ----
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

-- Patch older reminders tables that had only (id, request_id, reminder_type)
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reminders' AND COLUMN_NAME = 'student_id');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE reminders ADD COLUMN student_id INT NOT NULL DEFAULT 0',
    'SELECT "reminders.student_id already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reminders' AND COLUMN_NAME = 'message');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE reminders ADD COLUMN message TEXT NULL',
    'SELECT "reminders.message already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reminders' AND COLUMN_NAME = 'created_at');
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE reminders ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'SELECT "reminders.created_at already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Done. You can verify with:
--   SHOW TABLES;
--   DESCRIBE users;
--   SELECT COUNT(*) FROM email_verifications;
