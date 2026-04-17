<?php
/**
 * Application Configuration
 * Smart Hostel Maintenance System
 *
 * Auto-detects local (XAMPP) vs. production (InfinityFree) based on host.
 */

// ---- Environment detection ----
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$isLocal = in_array($host, ['localhost', '127.0.0.1'], true)
        || str_starts_with($host, 'localhost:')
        || str_starts_with($host, '127.0.0.1:');

// ---- Database ----
if ($isLocal) {
    // XAMPP defaults
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'hostel_maintenance');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // InfinityFree
    define('DB_HOST', 'sql111.infinityfree.com');
    define('DB_NAME', 'if0_41681787_hostel_maintenance');
    define('DB_USER', 'if0_41681787');
    define('DB_PASS', '2kagiorPJqs');
}
define('DB_CHARSET', 'utf8mb4');

// ---- App identity ----
define('APP_NAME', 'Smart Hostel Maintenance');
define('APP_VERSION', '1.0.0');

// ---- APP_URL auto-detect ----
// On XAMPP the app lives at /hostel-system; on InfinityFree it lives at the domain root.
// We derive the base path from the location of this file relative to DOCUMENT_ROOT,
// so moving the folder or changing hosts doesn't require edits.
$scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$docRoot    = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
$appRoot    = str_replace('\\', '/', realpath(__DIR__ . '/../..'));
$basePath   = '';
if ($docRoot && $appRoot && str_starts_with($appRoot, $docRoot)) {
    $basePath = substr($appRoot, strlen($docRoot));
}
$hostForUrl = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('APP_URL', $scheme . '://' . $hostForUrl . $basePath);

// ---- Session ----
define('SESSION_LIFETIME', 900);
define('SESSION_NAME', 'hostel_session');

// ---- Uploads ----
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_TYPES', ['image/jpeg', 'image/png']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// ---- Email ----
define('MAIL_ENABLED', false);
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', '');
define('MAIL_FROM', 'noreply@ashesi.edu.gh');
define('MAIL_FROM_NAME', 'Hostel Maintenance');

// ---- Security ----
define('BCRYPT_COST', 12);
define('RATE_LIMIT_LOGIN', 5);
define('RATE_LIMIT_API', 100);
