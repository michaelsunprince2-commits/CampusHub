<?php

/**
 * CampusNest - Main Entry Point
 * Routes to the PHP public application
 */

// Change to the public directory to ensure relative paths work correctly
chdir(__DIR__ . '/php/public');

// Now include the public index
require_once __DIR__ . '/php/public/index.php';
