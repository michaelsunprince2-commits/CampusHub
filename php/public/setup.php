<?php

/**
 * CampusNest - Quick Setup Wizard
 * Interactive setup to get the site running
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

?>
<!DOCTYPE html>
<html>

<head>
    <title>CampusNest Setup Wizard</title>
    <link rel="icon" type="image/png" href="assets/campusnest-logo.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            padding: 40px;
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
        }

        .setup-logo {
            display: block;
            width: 220px;
            max-width: 100%;
            height: auto;
            margin: 0 auto 20px;
        }

        .check {
            margin: 15px 0;
            padding: 15px;
            border-radius: 5px;
        }

        .check.pass {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .check.fail {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .check.warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .check strong {
            display: block;
            margin-bottom: 5px;
        }

        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }

        .instructions {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-top: 30px;
            border-left: 4px solid #667eea;
        }

        .instructions h3 {
            color: #667eea;
            margin-bottom: 15px;
        }

        .instructions ol {
            margin-left: 20px;
        }

        .instructions li {
            margin: 10px 0;
        }

        .next-url {
            background: #667eea;
            color: white;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            margin-top: 20px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <img src="assets/campusnest-logo.png" alt="CampusNest" class="setup-logo">
        <h1>CampusNest Setup Wizard</h1>

        <?php
        $allPass = true;
        $issues = [];

        // Check 1: PHP Version
        echo '<div class="check ' . (phpversion() >= '7.4' ? 'pass' : 'fail') . '">';
        if (phpversion() >= '7.4') {
            echo '<strong>✓ PHP Version</strong>';
            echo 'PHP ' . phpversion() . ' (✓ OK)';
        } else {
            echo '<strong>✗ PHP Version</strong>';
            echo 'PHP ' . phpversion() . ' (Need 7.4+)';
            $allPass = false;
            $issues[] = 'PHP version too old';
        }
        echo '</div>';

        // Check 2: Database Config
        echo '<div class="check ' . (file_exists('../config/database.php') ? 'pass' : 'fail') . '">';
        if (file_exists('../config/database.php')) {
            echo '<strong>✓ Database Config Found</strong>';
            echo 'File exists: php/config/database.php';
        } else {
            echo '<strong>✗ Database Config Missing</strong>';
            echo 'File not found: php/config/database.php';
            $allPass = false;
            $issues[] = 'Database config missing';
        }
        echo '</div>';

        // Check 3: Database Connection
        echo '<div class="check';
        $dbConnected = false;
        if (file_exists('../config/database.php')) {
            require_once '../config/database.php';
            if (!$conn->connect_error) {
                $dbConnected = true;
                echo ' pass';
            } else {
                echo ' fail';
                $allPass = false;
                $issues[] = 'Database connection failed: ' . $conn->connect_error;
            }
        } else {
            echo ' fail';
            $allPass = false;
        }
        echo '">';

        if ($dbConnected) {
            echo '<strong>✓ Database Connected</strong>';
            echo 'Successfully connected to ' . DB_NAME;
        } else {
            echo '<strong>✗ Database Connection Failed</strong>';
            echo 'Error: ' . ($conn->connect_error ?? 'Unknown error');
        }
        echo '</div>';

        // Check 4: Database Tables
        if ($dbConnected) {
            $result = $conn->query("SHOW TABLES");
            $tableCount = 0;
            if ($result) {
                $tableCount = $result->num_rows;
            }

            echo '<div class="check ' . ($tableCount >= 7 ? 'pass' : 'warning') . '">';
            if ($tableCount >= 7) {
                echo '<strong>✓ Database Tables</strong>';
                echo 'Found ' . $tableCount . ' tables (✓ OK)';
            } else {
                echo '<strong>⚠ Database Tables</strong>';
                echo 'Found ' . $tableCount . ' tables (Expected 7+)<br>';
                echo 'You need to import database.sql';
                $allPass = false;
                $issues[] = 'Database not imported';
            }
            echo '</div>';
        }

        // Check 5: Required files
        $requiredFiles = [
            '../config/database.php' => 'Database Config',
            '../includes/functions.php' => 'Functions',
            '../templates/header.php' => 'Header',
            '../templates/footer.php' => 'Footer',
        ];

        $filesOk = true;
        foreach ($requiredFiles as $path => $name) {
            if (!file_exists($path)) {
                $filesOk = false;
                $issues[] = "Missing file: $name";
            }
        }

        echo '<div class="check ' . ($filesOk ? 'pass' : 'fail') . '">';
        echo '<strong>' . ($filesOk ? '✓' : '✗') . ' Required Files</strong>';
        foreach ($requiredFiles as $path => $name) {
            $status = file_exists($path) ? '✓' : '✗';
            echo $status . ' ' . $name . '<br>';
        }
        echo '</div>';

        // Summary
        echo '<div style="margin-top: 30px; padding: 15px; background: ' . ($allPass ? '#d4edda' : '#fff3cd') . '; border-radius: 5px;">';
        if ($allPass && $dbConnected) {
            echo '<h3 style="color: #155724; margin-bottom: 10px;">✓ Setup Complete!</h3>';
            echo 'Your CampusNest installation is ready!<br><br>';
            echo '<div class="next-url"><a href="index.php" style="color: white; text-decoration: none;">Visit CampusNest →</a></div>';
        } else if (count($issues) > 0) {
            echo '<h3 style="margin-bottom: 10px;">⚠ Setup Issues Found</h3>';
            echo 'Please fix these issues:<br><br>';
            echo '<ul style="margin-left: 20px;">';
            foreach ($issues as $issue) {
                echo '<li>' . htmlspecialchars($issue) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        // Instructions
        if (!$allPass || !$dbConnected) {
            echo '<div class="instructions">';
            echo '<h3>Setup Instructions</h3>';
            echo '<ol>';
            echo '<li><strong>Check MySQL is running</strong><br>Open XAMPP Control Panel and start Apache & MySQL</li>';
            echo '<li><strong>Import the database</strong><br>Open Command Prompt and run:<br><code>cd c:\\xampp\\htdocs\\campusnest<br>mysql -u root &lt; database.sql</code></li>';
            echo '<li><strong>Check database password</strong><br>If MySQL has a password, edit: <code>php/config/database.php</code><br>Update: <code>define(\'DB_PASS\', \'your_password\');</code></li>';
            echo '<li><strong>Refresh this page</strong><br>Once done, refresh this page to verify</li>';
            echo '</ol>';
            echo '</div>';
        }
        ?>

    </div>
</body>

</html>
