<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Test 1: Checking database.php exists...\n";
echo file_exists('config/database.php') ? "YES\n" : "NO\n";

echo "\nTest 2: Requiring database.php...\n";
try {
    require_once 'config/database.php';
    echo "SUCCESS - Included\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\nTest 3: Check connection...\n";
if (isset($conn) && $conn && !$conn->connect_error) {
    echo "SUCCESS - Connected to database\n";
    $result = $conn->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = 'campusnest'");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "Tables found: " . $row['count'] . "\n";
    }
} else {
    echo "ERROR - No connection: " . ($conn->connect_error ?? 'Unknown') . "\n";
}
?>
