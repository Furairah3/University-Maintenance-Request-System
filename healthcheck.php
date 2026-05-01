<?php
// Server health check — visit this URL to diagnose hosting issues.
// Safe to keep on the server; reveals no secrets.
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo '<!DOCTYPE html><html><head><title>Health Check</title>
<style>body{font-family:sans-serif;max-width:700px;margin:30px auto;padding:20px;}
.ok{color:#155724;background:#d4edda;padding:4px 10px;border-radius:4px;}
.fail{color:#721c24;background:#f8d7da;padding:4px 10px;border-radius:4px;}
table{width:100%;border-collapse:collapse;margin:16px 0;}
td,th{padding:8px;text-align:left;border-bottom:1px solid #eee;}
th{background:#f8f9fa;}
pre{background:#f8f9fa;padding:10px;border-radius:4px;overflow:auto;}</style>
</head><body>';

echo '<h1>🩺 Smart Hostel — Server Health Check</h1>';

$rows = [];

// 1. PHP version
$phpOk = PHP_VERSION_ID >= 80000;
$rows[] = ['PHP version', PHP_VERSION, $phpOk, $phpOk ? '' : 'Upgrade to 8.0+ in your host control panel'];

// 2. Required extensions
foreach (['pdo_mysql', 'openssl', 'mbstring', 'fileinfo', 'session'] as $ext) {
    $has = extension_loaded($ext);
    $rows[] = ["Extension: {$ext}", $has ? 'loaded' : 'MISSING', $has, ''];
}

// 3. Config file
$configOk = file_exists(__DIR__ . '/config/db.php');
$rows[] = ['config/db.php present', $configOk ? 'yes' : 'NO', $configOk, ''];

if ($configOk) {
    try {
        require_once __DIR__ . '/config/db.php';
        $rows[] = ['SITE_URL',       SITE_URL,     true, ''];
        $rows[] = ['DB_HOST',        DB_HOST,      true, ''];
        $rows[] = ['DB_NAME',        DB_NAME,      true, ''];
        $rows[] = ['DEV_MODE',       DEV_MODE ? 'on (localhost)' : 'off (production)', true, ''];
        $rows[] = ['APP_TIMEZONE',   APP_TIMEZONE, true, ''];

        // 4. DB connection
        try {
            $db = getDB();
            $v  = $db->query('SELECT VERSION() AS v')->fetchColumn();
            $rows[] = ['Database connection', "OK (MySQL {$v})", true, ''];

            // 5. Required tables
            foreach (['users', 'requests', 'notifications', 'categories',
                      'email_verifications', 'reviews', 'reminders', 'status_history'] as $t) {
                $exists = (bool)$db->query("SHOW TABLES LIKE " . $db->quote($t))->fetchColumn();
                $rows[] = ["Table: {$t}", $exists ? 'exists' : 'MISSING',
                           $exists,
                           $exists ? '' : 'Run migration.sql on this database'];
            }
        } catch (Throwable $e) {
            $rows[] = ['Database connection', 'FAILED: ' . $e->getMessage(), false, 'Check DB_HOST/USER/PASS/NAME in config/db.php'];
        }
    } catch (Throwable $e) {
        $rows[] = ['Config load', 'FAILED: ' . $e->getMessage(), false, ''];
    }
}

// 6. Upload dirs
$u1 = __DIR__ . '/uploads';
$u2 = __DIR__ . '/uploads/avatars';
$rows[] = ['uploads/ exists',             is_dir($u1) ? 'yes' : 'NO', is_dir($u1), ''];
$rows[] = ['uploads/ writable',           is_writable($u1) ? 'yes' : 'no', is_writable($u1), ''];
$rows[] = ['uploads/avatars/ exists',     is_dir($u2) ? 'yes' : 'NO', is_dir($u2), is_dir($u2) ? '' : 'Create this folder'];
$rows[] = ['uploads/avatars/ writable',   is_writable($u2) ? 'yes' : 'no', is_writable($u2), ''];

// 7. SMTP port reachable
$errno = 0; $errstr = '';
$fp = @stream_socket_client("tcp://smtp.gmail.com:587", $errno, $errstr, 5);
$smtpOk = (bool)$fp;
if ($fp) fclose($fp);
$rows[] = ['SMTP port 587 outbound',
           $smtpOk ? 'reachable' : "blocked ({$errstr})",
           $smtpOk,
           $smtpOk ? '' : 'Your host blocks outbound SMTP — switch provider or use API-based email'];

// Render
echo '<table><tr><th style="width:200px;">Check</th><th style="width:260px;">Result</th><th>Status</th><th>Action</th></tr>';
foreach ($rows as [$label, $value, $ok, $hint]) {
    $badge = $ok
        ? '<span class="ok">✓ OK</span>'
        : '<span class="fail">✗ FAIL</span>';
    echo '<tr>'
       . '<td><strong>' . htmlspecialchars($label) . '</strong></td>'
       . '<td><code>' . htmlspecialchars((string)$value) . '</code></td>'
       . '<td>' . $badge . '</td>'
       . '<td>' . htmlspecialchars($hint) . '</td>'
       . '</tr>';
}
echo '</table>';

echo '<p style="font-size:12px;color:#888;">Delete this file after debugging if you want — but it reveals nothing sensitive (no passwords shown).</p>';
echo '</body></html>';
