<?php

/**
 * CampusNest - Quick Start Setup Script
 * Run this script to verify installation and create test data
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "=== CampusNest Setup Verification ===\n\n";

// Check database connection
if (!$conn) {
    echo "❌ Database connection failed\n";
    exit(1);
}
echo "✓ Database connection successful\n";

// Check required tables
$requiredTables = [
    'users',
    'properties',
    'bookings',
    'payments',
    'messages',
    'reviews',
    'sessions'
];

$tables = $conn->query("SHOW TABLES")->fetch_all();
$existingTables = array_column($tables, 0);

echo "\nChecking tables:\n";
foreach ($requiredTables as $table) {
    if (in_array($table, $existingTables)) {
        echo "✓ Table '$table' exists\n";
    } else {
        echo "❌ Table '$table' missing\n";
    }
}

// Check file permissions
echo "\nChecking directories:\n";
$dirs = [
    'config' => dirname(__FILE__) . '/config',
    'public' => dirname(__FILE__) . '/public',
    'api' => dirname(__FILE__) . '/api',
];

foreach ($dirs as $name => $path) {
    if (is_writable($path)) {
        echo "✓ Directory '$name' is writable\n";
    } else {
        echo "⚠ Directory '$name' may have permission issues\n";
    }
}

// Create test admin account
echo "\n--- Creating Test Accounts ---\n";

require_once 'models/User.php';
$user = new User($conn);

$testAccounts = [
    [
        'email' => 'admin@campusnest.test',
        'password' => 'admin123456',
        'firstName' => 'Admin',
        'lastName' => 'User',
        'role' => 'admin'
    ],
    [
        'email' => 'landlord@campusnest.test',
        'password' => 'landlord123456',
        'firstName' => 'John',
        'lastName' => 'Landlord',
        'role' => 'landlord'
    ],
    [
        'email' => 'student@campusnest.test',
        'password' => 'student123456',
        'firstName' => 'Jane',
        'lastName' => 'Student',
        'role' => 'student'
    ]
];

foreach ($testAccounts as $account) {
    // Check if account exists
    $existing = getUserByEmail($conn, $account['email']);

    if (!$existing) {
        $result = $user->register(
            $account['email'],
            $account['password'],
            $account['firstName'],
            $account['lastName'],
            $account['role']
        );

        if ($result['success']) {
            echo "✓ Created {$account['role']} account: {$account['email']}\n";
        } else {
            echo "❌ Failed to create {$account['role']} account\n";
        }
    } else {
        echo "ℹ {$account['role']} account already exists: {$account['email']}\n";
    }
}

echo "\n=== Setup Complete ===\n";
echo "\nTest Accounts:\n";
echo "Admin:    admin@campusnest.test / admin123456\n";
echo "Landlord: landlord@campusnest.test / landlord123456\n";
echo "Student:  student@campusnest.test / student123456\n";

echo "\nAccess the application at: http://localhost:8000/public/index.php\n";
echo "\nFor development server, run:\n";
echo "php -S localhost:8000\n";
