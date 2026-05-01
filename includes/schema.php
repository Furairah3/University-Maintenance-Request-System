<?php
// =====================================================
// Extended schema bootstrap — adds feedback/reminder tables
// Safe to call on every page load (idempotent).
// =====================================================
require_once __DIR__ . '/../config/db.php';

function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensureExtendedSchema(): void {
    static $done = false;
    if ($done) return;
    $done = true;  // mark done even if some steps fail — we only attempt once per request

    $db = getDB();

    // Run each migration step in its own try/catch so one restricted operation
    // (e.g. a host that disallows CREATE TABLE) doesn't cascade into a 500 on every page.
    $safe = function (string $sql) use ($db) {
        try { $db->exec($sql); } catch (Throwable) { /* swallow — migration is best-effort */ }
    };

    $safe("
        CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL UNIQUE,
            rating TINYINT NOT NULL,
            comment TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $safe("
        CREATE TABLE IF NOT EXISTS reminders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            student_id INT NOT NULL,
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (request_id, created_at)
        ) ENGINE=InnoDB
    ");

    // Patch older reminders variants that only had (id, request_id, reminder_type)
    try {
        if (!columnExists($db, 'reminders', 'student_id'))
            $safe("ALTER TABLE reminders ADD COLUMN student_id INT NOT NULL DEFAULT 0");
        if (!columnExists($db, 'reminders', 'message'))
            $safe("ALTER TABLE reminders ADD COLUMN message TEXT NULL");
        if (!columnExists($db, 'reminders', 'created_at'))
            $safe("ALTER TABLE reminders ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    } catch (Throwable) { /* information_schema might be restricted */ }

    // Reviews column patches (best-effort)
    try {
        if (!columnExists($db, 'reviews', 'rating'))
            $safe("ALTER TABLE reviews ADD COLUMN rating TINYINT NOT NULL DEFAULT 5");
        if (!columnExists($db, 'reviews', 'comment'))
            $safe("ALTER TABLE reviews ADD COLUMN comment TEXT NULL");
        if (!columnExists($db, 'reviews', 'created_at'))
            $safe("ALTER TABLE reviews ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        if (!columnExists($db, 'reviews', 'updated_at'))
            $safe("ALTER TABLE reviews ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    } catch (Throwable) { /* best-effort */ }

    // Email verification columns on users
    try {
        if (!columnExists($db, 'users', 'is_verified')) {
            $safe("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0");
            $safe("UPDATE users SET is_verified = 1");  // grandfather pre-existing accounts
        }
        if (!columnExists($db, 'users', 'avatar_path')) {
            $safe("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL");
        }
        // Staff profession / specialization
        if (!columnExists($db, 'users', 'profession')) {
            $safe("ALTER TABLE users ADD COLUMN profession VARCHAR(50) NULL");
        }
        // Parent link — set when a request is the result of reopening another one
        if (!columnExists($db, 'requests', 'parent_request_id')) {
            $safe("ALTER TABLE requests ADD COLUMN parent_request_id INT NULL");
            $safe("ALTER TABLE requests ADD INDEX idx_parent_request (parent_request_id)");
        }
    } catch (Throwable) { /* best-effort */ }

    $safe("
        CREATE TABLE IF NOT EXISTS email_verifications (
            user_id INT PRIMARY KEY,
            code CHAR(6) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            attempts TINYINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    // Password reset tokens — one active per user, 30-minute expiry
    $safe("
        CREATE TABLE IF NOT EXISTS password_resets (
            user_id INT PRIMARY KEY,
            token CHAR(64) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (token),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
}

// Called helpers for reminders + notifications to admins.
// Sends both in-app notification AND email so admins are reachable through any channel.
function notifyAdmins(int $requestId, string $message, string $emailSubject = ''): void {
    $db = getDB();
    $admins = $db->query("SELECT id, name, email FROM users WHERE role='admin' AND is_active=1")->fetchAll();
    if (!$admins) return;

    $ins = $db->prepare("INSERT INTO notifications (user_id, request_id, message) VALUES (?,?,?)");
    $url = SITE_URL . "/admin/request_details.php?id={$requestId}";

    if (!$emailSubject) $emailSubject = '[HostelIQ] ' . substr($message, 0, 80);

    foreach ($admins as $a) {
        $ins->execute([$a['id'], $requestId, $message]);

        // Lazy-load email functions only when sending; avoids circular includes.
        if (function_exists('sendEmail')) {
            $html = '<!DOCTYPE html><html><body style="font-family:sans-serif;background:#f4f4f4;padding:24px;">'
                  . '<div style="max-width:520px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;">'
                  . '<div style="background:#8B0000;padding:20px;text-align:center;color:#fff;"><h2 style="margin:0;">HostelIQ — Admin Alert</h2></div>'
                  . '<div style="padding:24px;">'
                  . '<p>Hi <strong>' . htmlspecialchars($a['name']) . '</strong>,</p>'
                  . '<p>' . htmlspecialchars($message) . '</p>'
                  . '<a href="' . $url . '" style="display:inline-block;background:#8B0000;color:#fff;padding:12px 22px;border-radius:6px;text-decoration:none;margin-top:8px;">View Request</a>'
                  . '<p style="color:#999;font-size:12px;margin-top:24px;">Ashesi University — Smart Hostel Management System</p>'
                  . '</div></div></body></html>';
            @sendEmail($a['email'], $a['name'], $emailSubject, $html);
        }
    }
}
