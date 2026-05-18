<?php

/**
 * Header Template
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - CampusNest' : 'CampusNest - Student Housing Platform'; ?></title>
    <link rel="icon" type="image/png" href="<?php echo getBaseUrl(); ?>/php/public/assets/campusnest-logo.png">
    <link rel="stylesheet" href="/php/public/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        header {
            background-color: #2c3e50;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            flex: 0 0 auto;
            text-decoration: none;
        }

        .logo img {
            display: block;
            width: auto;
            height: 54px;
            max-width: 210px;
            object-fit: contain;
            background: white;
            border-radius: 6px;
            padding: 0.25rem 0.5rem;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 2rem;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        nav a {
            color: white;
            text-decoration: none;
            transition: color 0.3s;
        }

        nav a:hover {
            color: #3498db;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1.5rem;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.95rem;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #2980b9;
        }

        .btn-danger {
            background-color: #e74c3c;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-success {
            background-color: #27ae60;
        }

        .btn-success:hover {
            background-color: #229954;
        }

        main {
            padding: 2rem 0;
        }

        footer {
            background-color: #243342;
            color: #d8e2e8;
            margin-top: 3rem;
        }

        footer a {
            color: #d8e2e8;
            text-decoration: none;
        }

        footer a:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        .footer-content {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 1fr;
            gap: 2rem;
            padding: 2.5rem 0;
        }

        .footer-brand h2,
        .footer-column h3 {
            color: #ffffff;
            margin-bottom: 0.75rem;
        }

        .footer-brand h2 {
            font-size: 1.5rem;
        }

        .footer-brand p,
        .footer-column p {
            color: #b9c7d0;
            margin-bottom: 0.75rem;
        }

        .footer-column ul {
            display: grid;
            gap: 0.55rem;
            list-style: none;
        }

        .footer-column li {
            color: #b9c7d0;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            padding: 1rem 0;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            color: #b9c7d0;
            font-size: 0.92rem;
        }

        @media (max-width: 860px) {
            .header-content {
                justify-content: center;
                text-align: center;
            }

            nav {
                width: 100%;
            }

            nav ul {
                justify-content: center;
                gap: 0.65rem;
            }

            nav a {
                display: inline-flex;
                align-items: center;
                min-height: 40px;
                padding: 0.35rem 0.55rem;
            }

            .footer-content {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 560px) {
            header {
                padding: 0.75rem 0;
            }

            .container {
                padding: 0 14px;
            }

            .logo img {
                height: 44px;
                max-width: 170px;
            }

            nav ul {
                gap: 0.45rem;
            }

            nav a {
                font-size: 0.92rem;
                padding: 0.3rem 0.45rem;
            }

            nav a.btn {
                padding: 0.45rem 0.75rem;
                width: auto;
            }

            .footer-content {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .footer-bottom {
                flex-direction: column;
            }
        }

        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>
</head>

<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="<?php echo pageUrl('index.php'); ?>" class="logo" aria-label="CampusNest home">
                    <img src="<?php echo getBaseUrl(); ?>/php/public/assets/campusnest-logo.png" alt="CampusNest">
                </a>
                <nav>
                    <ul>
                        <li><a href="<?php echo pageUrl('index.php'); ?>">Home</a></li>
                        <li><a href="<?php echo pageUrl('properties.php'); ?>">Browse Properties</a></li>
                        <li><a href="<?php echo pageUrl('reviews.php'); ?>">Reviews</a></li>
                        <?php if (isAuthenticated()): ?>
                            <li><a href="<?php echo pageUrl('profile.php'); ?>">Profile</a></li>
                            <li><a href="<?php echo pageUrl('messages.php'); ?>">Messages</a></li>
                            <li><a href="<?php echo pageUrl('platform-review.php'); ?>">Share Review</a></li>
                            <?php if (getCurrentUserRole() === 'landlord'): ?>
                                <li><a href="<?php echo pageUrl('landlord-dashboard.php'); ?>">My Properties</a></li>
                            <?php elseif (getCurrentUserRole() === 'student'): ?>
                                <li><a href="<?php echo pageUrl('bookings.php'); ?>">My Bookings</a></li>
                            <?php endif; ?>
                            <?php if (in_array(getCurrentUserRole(), ['admin', 'committee'])): ?>
                                <li><a href="<?php echo pageUrl('admin-dashboard.php'); ?>"><?php echo getCurrentUserRole() === 'admin' ? 'Admin' : 'Committee'; ?></a></li>
                            <?php endif; ?>
                            <li><a href="<?php echo pageUrl('logout.php'); ?>" class="btn btn-danger">Logout</a></li>
                        <?php else: ?>
                            <li><a href="<?php echo pageUrl('login.php'); ?>" class="btn">Login</a></li>
                            <li><a href="<?php echo pageUrl('register.php'); ?>" class="btn btn-success">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
