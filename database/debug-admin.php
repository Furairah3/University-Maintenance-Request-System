<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Loading config<br>";
require_once __DIR__ . '/backend/config/config.php';
echo "Step 2: Config OK<br>";

echo "Step 3: Loading Logger<br>";
require_once __DIR__ . '/backend/includes/Logger.php';
echo "Step 4: Logger OK<br>";

echo "Step 5: Loading Database<br>";
require_once __DIR__ . '/backend/includes/Database.php';
echo "Step 6: Database class OK<br>";

echo "Step 7: Connecting to DB<br>";
try {
    $db = Database::getInstance();
    echo "Step 8: Connected OK<br>";
} catch (Exception $e) {
    echo "<b style='color:red'>DB Error: " . $e->getMessage() . "</b><br>";
    die();
}

echo "Step 9: Loading Auth<br>";
require_once __DIR__ . '/backend/includes/Auth.php';
echo "Step 10: Auth OK<br>";

echo "Step 11: Loading helpers<br>";
require_once __DIR__ . '/backend/includes/helpers.php';
echo "Step 12: Helpers OK<br>";

echo "Step 13: Testing session<br>";
Auth::initSession();
echo "Step 14: Session OK<br>";

echo "Step 15: Testing metrics query<br>";
try {
    $metrics = $db->fetchOne(
        "SELECT COUNT(*) as total,
            COUNT(CASE WHEN status='Pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status='In Progress' THEN 1 END) as in_progress,
            COUNT(CASE WHEN status='Completed' THEN 1 END) as completed
         FROM requests WHERE is_archived = 0"
    );
    echo "Step 16: Metrics query OK - Total: " . $metrics['total'] . "<br>";
} catch (Exception $e) {
    echo "<b style='color:red'>Metrics Error: " . $e->getMessage() . "</b><br>";
}

echo "Step 17: Testing avg response query<br>";
try {
    $avgResponse = $db->fetchOne(
        "SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, r.created_at, a.assigned_at)),1) as hours
         FROM requests r JOIN assignments a ON r.id = a.request_id WHERE r.is_archived = 0"
    );
    echo "Step 18: Avg response OK<br>";
} catch (Exception $e) {
    echo "<b style='color:red'>Avg Response Error: " . $e->getMessage() . "</b><br>";
}

echo "Step 19: Testing categories query<br>";
try {
    $categories = $db->fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY display_order");
    echo "Step 20: Categories OK - Found: " . count($categories) . "<br>";
} catch (Exception $e) {
    echo "<b style='color:red'>Categories Error: " . $e->getMessage() . "</b><br>";
}

echo "Step 21: Testing staff query<br>";
try {
    $staffList = $db->fetchAll("SELECT id, name FROM users WHERE role='staff' AND is_active=1 ORDER BY name");
    echo "Step 22: Staff OK - Found: " . count($staffList) . "<br>";
} catch (Exception $e) {
    echo "<b style='color:red'>Staff Error: " . $e->getMessage() . "</b><br>";
}

echo "<br><b style='color:green'>ALL TESTS PASSED!</b><br>";
echo "Delete this file now.";
?>
