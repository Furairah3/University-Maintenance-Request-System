<?php
/**
 * Database Configuration
 * Smart Hostel Maintenance System
 */

define('DB_HOST', 'sql111.infinityfree.com');
define('DB_NAME', 'if0_41681787_hostel_maintenance');
define('DB_USER', 'if0_41681787');
define('DB_PASS', 'PUT_YOUR_REAL_PASSWORD_HERE');  // <-- CHANGE THIS to your real password
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('APP_NAME', 'Smart Hostel Maintenance');
define('APP_URL', 'http://hostel-g11infinityfreeapp.ct.ws');
define('APP_VERSION', '1.0.0');

// Session settings
define('SESSION_LIFETIME', 900);
define('SESSION_NAME', 'hostel_session');

// Upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_TYPES', ['image/jpeg', 'image/png']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// Email settings
define('MAIL_ENABLED', false);
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', '');
define('MAIL_FROM', 'noreply@ashesi.edu.gh');
define('MAIL_FROM_NAME', 'Hostel Maintenance');

// Security
define('BCRYPT_COST', 12);
define('RATE_LIMIT_LOGIN', 5);
define('RATE_LIMIT_API', 100);
