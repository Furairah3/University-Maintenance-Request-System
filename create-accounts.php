<?php
/**
 * Run this ONCE to create admin and staff accounts
 * Access: http://localhost/hostel-system/create-accounts.php
 * DELETE THIS FILE after running!
 */

require_once __DIR__ . '/backend/config/config.php';

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accounts = [
    // Admins
    ['name' => 'System Administrator',  'email' => 'admin@ashesi.edu.gh',       'password' => 'Admin@2026',  'role' => 'admin'],
    ['name' => 'Furairah Admin',        'email' => 'furairah@ashesi.edu.gh',     'password' => 'Admin@2026',  'role' => 'admin'],

    // Maintenance Staff
    ['name' => 'John Mensah',           'email' => 'j.mensah@ashesi.edu.gh',     'password' => 'Staff@2026',  'role' => 'staff'],
    ['name' => 'Ama Darko',             'email' => 'a.darko@ashesi.edu.gh',      'password' => 'Staff@2026',  'role' => 'staff'],
    ['name' => 'Kwame Asante',          'email' => 'k.asante@ashesi.edu.gh',     'password' => 'Staff@2026',  'role' => 'staff'],

    // Test Student
    ['name' => 'Test Student',          'email' => 'student@ashesi.edu.gh',      'password' => 'Student@2026','role' => 'student'],
];

echo "<h2>Creating Accounts...</h2><pre>";

$stmt = $pdo->prepare(
    "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name)"
);

foreach ($accounts as $acc) {
    $hash = password_hash($acc['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt->execute([$acc['name'], $acc['email'], $hash, $acc['role']]);
    echo "✅ {$acc['role']}: {$acc['email']} / {$acc['password']}\n";
}

echo "\n<strong>All accounts created! DELETE THIS FILE NOW.</strong></pre>";
