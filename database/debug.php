<?php
/**
 * DEBUG FILE - Delete after testing!
 * Visit: http://hostel-g11infinityfreeapp.ct.ws/debug.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>1. PHP is working</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";

echo "<h2>2. Testing config file</h2>";
try {
    require_once __DIR__ . '/backend/config/config.php';
    echo "<p>DB_HOST: " . DB_HOST . "</p>";
    echo "<p>DB_NAME: " . DB_NAME . "</p>";
    echo "<p>DB_USER: " . DB_USER . "</p>";
    echo "<p>DB_PASS: " . (DB_PASS ? '***set***' : '***EMPTY***') . "</p>";
    echo "<p style='color:green'>Config loaded OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Config error: " . $e->getMessage() . "</p>";
    die();
}

echo "<h2>3. Testing database connection</h2>";
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<p style='color:green;font-weight:bold'>DATABASE CONNECTED SUCCESSFULLY!</p>";
    
    echo "<h2>4. Checking tables</h2>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        echo "<p style='color:red'>NO TABLES FOUND - You need to import schema-infinityfree.sql</p>";
    } else {
        echo "<p>Tables found: " . count($tables) . "</p>";
        echo "<ul>";
        foreach ($tables as $t) {
            echo "<li>$t</li>";
        }
        echo "</ul>";
        echo "<p style='color:green;font-weight:bold'>DATABASE IS READY!</p>";
    }
    
    echo "<h2>5. Checking users</h2>";
    $users = $pdo->query("SELECT id, name, email, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($users)) {
        echo "<p style='color:orange'>No user accounts yet - run create-accounts.php</p>";
    } else {
        echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";
        foreach ($users as $u) {
            echo "<tr><td>{$u['id']}</td><td>{$u['name']}</td><td>{$u['email']}</td><td>{$u['role']}</td></tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red;font-weight:bold'>CONNECTION FAILED!</p>";
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    echo "<h3>Common fixes:</h3>";
    echo "<ul>";
    echo "<li>Check DB_HOST - should be something like sql111.infinityfree.com</li>";
    echo "<li>Check DB_NAME - should be if0_XXXXXXXX_something</li>";
    echo "<li>Check DB_USER - should be if0_XXXXXXXX</li>";
    echo "<li>Check DB_PASS - the password you set in InfinityFree control panel</li>";
    echo "</ul>";
}

echo "<hr><p><strong>DELETE THIS FILE after debugging!</strong></p>";
?>
