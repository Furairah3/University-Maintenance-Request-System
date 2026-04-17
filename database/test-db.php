<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: PHP works<br>";

echo "Step 2: Testing DB connection<br>";

$host = 'sql111.infinityfree.com';
$name = 'if0_41681787_hostel_maintenance';
$user = 'if0_41681787';
$pass = 'PUT_YOUR_REAL_PASSWORD_HERE';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<b style='color:green'>Step 3: CONNECTED!</b><br>";
    
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . count($tables) . "<br>";
    foreach ($tables as $t) echo "- $t<br>";
    
} catch (Exception $e) {
    echo "<b style='color:red'>FAILED: " . $e->getMessage() . "</b><br>";
}

echo "<br>Done. Delete this file!";
?>
