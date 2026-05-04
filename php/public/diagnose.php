<?php

/**
 * Diagnostic Script - Shows what's wrong
 */

// Force error display
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>CampusNest Diagnostic Report</h1>\n";
echo "<hr>\n";

// 1. Check PHP version
echo "<h3>✓ PHP Version</h3>\n";
echo "PHP " . phpversion() . " (Required: 7.4+)\n";
echo "<hr>\n";

// 2. Check database config exists
echo "<h3>Database Config</h3>\n";
if (file_exists('../config/database.php')) {
    echo "✓ database.php found<br>\n";

    // Try to include it and catch errors
    try {
        require_once '../config/database.php';

        if ($conn->connect_error) {
            echo "<strong style='color:red;'>✗ Database Connection Error:</strong><br>\n";
            echo "<pre>" . htmlspecialchars($conn->connect_error) . "</pre>\n";
            echo "<br><strong>Suggestions:</strong><br>\n";
            echo "1. Is MySQL running in XAMPP?<br>\n";
            echo "2. Check database.php config<br>\n";
            echo "3. Default: DB_HOST='localhost', DB_USER='root', DB_PASS='' (empty)<br>\n";
        } else {
            echo "✓ Database connection successful!<br>\n";

            // Check tables
            $result = $conn->query("SHOW TABLES");
            if ($result) {
                $tables = [];
                while ($row = $result->fetch_row()) {
                    $tables[] = $row[0];
                }
                echo "✓ Tables found: " . count($tables) . "<br>\n";
                echo "  " . implode(", ", $tables) . "<br>\n";
            }
        }
    } catch (Exception $e) {
        echo "<strong style='color:red;'>✗ Error including database.php:</strong><br>\n";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>\n";
    }
} else {
    echo "✗ database.php NOT found at ../config/database.php<br>\n";
}

echo "<hr>\n";

// 3. Check required files
echo "<h3>Required Files</h3>\n";
$files = [
    '../config/database.php' => 'Database Config',
    '../includes/functions.php' => 'Functions',
    '../templates/header.php' => 'Header Template',
];

foreach ($files as $path => $name) {
    if (file_exists($path)) {
        echo "✓ $name<br>\n";
    } else {
        echo "✗ $name (missing: $path)<br>\n";
    }
}

echo "<hr>\n";

// 4. Test includes
echo "<h3>Testing Includes</h3>\n";
try {
    if (file_exists('../includes/functions.php')) {
        require_once '../includes/functions.php';
        echo "✓ functions.php included successfully<br>\n";
    }
} catch (Exception $e) {
    echo "✗ Error in functions.php: " . htmlspecialchars($e->getMessage()) . "<br>\n";
}

echo "<hr>\n";

// 5. Check session
echo "<h3>Session Info</h3>\n";
session_start();
echo "✓ Session started<br>\n";
echo "Session ID: " . session_id() . "<br>\n";

echo "<hr>\n";

// 6. API Test
echo "<h3>Quick API Test</h3>\n";
echo "<code><a href='../api/auth.php?test=1'>/api/auth.php</a></code> (Should show 'Method not allowed' - that's OK)<br>\n";

echo "<hr>\n";
echo "<h3>Next Steps</h3>\n";
echo "1. Make sure MySQL is running in XAMPP<br>\n";
echo "2. Import database: <code>mysql -u root &lt; database.sql</code><br>\n";
echo "3. Check DB password in php/config/database.php<br>\n";
echo "4. Visit: <code>http://localhost/campusnest/php/public/diagnose.php</code> again<br>\n";

echo "<hr>\n";
echo "<small>Generated: " . date('Y-m-d H:i:s') . "</small>\n";
