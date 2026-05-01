<?php
// =====================================================
// Smart Hostel Management — Ashesi CS415 Group 11
// CONFIG TEMPLATE — copy this file to `config/db.php` and fill in real credentials.
// `config/db.php` is gitignored so secrets never reach the repo.
// =====================================================

// Emergency debug: append ?debug=1 to any URL to see errors on a blank page.
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// PHP version guard — this app uses PHP 8.0+ features (match expression, union types)
if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    die('<div style="font-family:sans-serif;max-width:560px;margin:80px auto;padding:20px;border:1px solid #ddd;border-radius:8px;">
        <h2 style="color:#8B0000;">PHP version too old</h2>
        <p>This app requires <strong>PHP 8.0 or newer</strong>. Your server is running <strong>'
        . PHP_VERSION . '</strong>.</p>
        </div>');
}

$host = $_SERVER['HTTP_HOST'] ?? '';
$IS_LOCAL = (
    strpos($host, 'localhost') !== false ||
    strpos($host, '127.0.0.1') !== false ||
    strpos($host, '.test') !== false ||
    strpos($host, '.local') !== false
);

if ($IS_LOCAL) {
    // ---------- LOCAL (XAMPP) ----------
    define('DB_HOST',   'localhost');
    define('DB_USER',   'root');
    define('DB_PASS',   '');                // empty for XAMPP default
    define('DB_NAME',   'smart_hostel');
    define('SITE_URL',  'http://localhost/smart-hostel');
    define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/smart-hostel/uploads/');
    define('UPLOAD_URL', 'http://localhost/smart-hostel/uploads/');
    define('DEV_MODE',  true);
} else {
    // ---------- PRODUCTION ----------
    // Replace with your real hosting credentials before deploying.
    define('DB_HOST',   'YOUR_DB_HOST');
    define('DB_USER',   'YOUR_DB_USER');
    define('DB_PASS',   'YOUR_DB_PASSWORD');
    define('DB_NAME',   'YOUR_DB_NAME');

    define('SITE_URL',  'https://yourdomain.example.com/smart-hostel');
    define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/smart-hostel/uploads/');
    define('UPLOAD_URL', 'https://yourdomain.example.com/smart-hostel/uploads/');
    define('DEV_MODE',  false);
}

// Shared across environments
define('MAX_FILE_SIZE', 5 * 1024 * 1024);   // 5MB upload limit

// Email (SMTP) — replace with your own credentials.
// For Gmail: enable 2FA, then create an App Password at https://myaccount.google.com/apppasswords
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'YOUR_EMAIL@gmail.com');
define('SMTP_PASS', 'YOUR_16_CHAR_APP_PASSWORD');
define('FROM_NAME', 'HostelIQ');

// OTP tuning
define('OTP_TTL_MINUTES',            10);
define('OTP_RESEND_COOLDOWN_SECONDS', 60);

// Timezone — used by PHP and applied to every MySQL connection
define('APP_TIMEZONE', 'Africa/Accra');
date_default_timezone_set(APP_TIMEZONE);

// DB connection (singleton)
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $offset = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('P');
            $pdo->exec("SET time_zone = '{$offset}'");
        } catch (PDOException $e) {
            if (defined('DEV_MODE') && DEV_MODE) {
                die('Database connection failed: ' . $e->getMessage());
            }
            http_response_code(500);
            die('Database temporarily unavailable. Please try again in a moment.');
        }
    }
    return $pdo;
}
