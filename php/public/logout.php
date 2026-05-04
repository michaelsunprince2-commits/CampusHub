<?php

/**
 * Logout Page
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

// Destroy session
session_destroy();

// Redirect to home
header('Location: ' . pageUrl('index.php'));
exit();
