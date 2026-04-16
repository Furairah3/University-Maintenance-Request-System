-- ============================================================
-- Migration 001: Staff Specialties (many-to-many)
-- Run: mysql -u root -p hostel_maintenance < 001_staff_categories.sql
-- ============================================================

USE hostel_maintenance;

CREATE TABLE IF NOT EXISTS staff_categories (
    user_id     INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, category_id),

    CONSTRAINT fk_sc_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_sc_category FOREIGN KEY (category_id)
        REFERENCES categories(id) ON UPDATE CASCADE ON DELETE CASCADE,

    INDEX idx_sc_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed specialties for the default staff so the feature is visible after migration.
-- Only runs if the users and categories exist; safe to re-run.
INSERT IGNORE INTO staff_categories (user_id, category_id)
SELECT u.id, c.id
FROM users u
JOIN categories c ON c.name IN ('Electrical', 'HVAC')
WHERE u.email = 'j.mensah@ashesi.edu.gh';

INSERT IGNORE INTO staff_categories (user_id, category_id)
SELECT u.id, c.id
FROM users u
JOIN categories c ON c.name IN ('Plumbing', 'Furniture')
WHERE u.email = 'a.darko@ashesi.edu.gh';
