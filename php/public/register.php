<?php

/**
 * Register Page
 */

$pageTitle = 'Register';
require_once '../config/database.php';
require_once '../includes/functions.php';

// If already logged in, redirect
if (isAuthenticated()) {
    header('Location: ' . pageUrl('index.php'));
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeString($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $firstName = sanitizeString($_POST['first_name'] ?? '');
    $lastName = sanitizeString($_POST['last_name'] ?? '');
    $role = sanitizeString($_POST['role'] ?? 'student');

    // Validation
    if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
        $error = 'All fields are required';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } elseif (!validatePassword($password)) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (!validateRole($role) || !in_array($role, ['student', 'landlord'], true)) {
        $error = 'Invalid role selected';
    } else {
        require_once '../models/User.php';
        $user = new User($conn);
        $result = $user->register($email, $password, $firstName, $lastName, $role);

        if ($result['success']) {
            $success = 'Registration successful! Redirecting to login...';
            // Redirect after 2 seconds
            header('refresh:2;url=' . pageUrl('login.php'));
        } else {
            $error = $result['message'];
        }
    }
}

require_once '../templates/header.php';
?>

<div class="auth-container" style="max-width: 500px; margin: 2.5rem auto;">
    <style>
        .auth-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            border: 1px solid #e5edf2;
            box-shadow: 0 8px 24px rgba(44, 62, 80, 0.08);
        }

        .auth-container h1 {
            text-align: center;
            margin-bottom: 0.35rem;
        }

        .auth-subtitle {
            text-align: center;
            color: #657786;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .auth-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group label {
            color: #2c3e50;
        }

        .form-group input,
        .form-group select {
            padding: 0.85rem;
            border: 1px solid #d9e2e8;
            border-radius: 6px;
            background: #fbfcfd;
        }

        .form-group button {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 6px;
        }

        .social-auth {
            margin-top: 1.5rem;
        }

        .social-divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #7f8c8d;
            font-size: 0.9rem;
            margin: 0.25rem 0 1rem;
        }

        .social-divider::before,
        .social-divider::after {
            content: "";
            height: 1px;
            background: #e5edf2;
            flex: 1;
        }

        .social-auth-grid {
            display: grid;
            gap: 0.75rem;
        }

        .social-auth-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            min-height: 48px;
            border: 1px solid #d9e2e8;
            border-radius: 8px;
            color: #2c3e50;
            background: #ffffff;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
        }

        .social-auth-btn:hover {
            color: #2c3e50;
            border-color: #9fb3c1;
            box-shadow: 0 6px 16px rgba(44, 62, 80, 0.08);
            text-decoration: none;
            transform: translateY(-1px);
        }

        .social-auth-disabled,
        .social-auth-disabled:hover {
            cursor: not-allowed;
            opacity: 0.62;
            box-shadow: none;
            transform: none;
            background: #f8fafb;
            color: #6b7c86;
        }

        .social-auth-mark {
            width: 24px;
            height: 24px;
            display: grid;
            place-items: center;
        }

        .social-auth-mark svg {
            width: 22px;
            height: 22px;
            display: block;
        }

        @media (max-width: 560px) {
            .auth-form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <h1>Create Account</h1>
    <p class="auth-subtitle">Join CampusNest with your details below.</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="auth-form-row">
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="role">I am a</label>
            <select name="role" id="role" style="width: 100%;">
                <option value="student" <?php echo ($_POST['role'] ?? 'student') === 'student' ? 'selected' : ''; ?>>Student</option>
                <option value="landlord" <?php echo ($_POST['role'] ?? '') === 'landlord' ? 'selected' : ''; ?>>Landlord</option>
            </select>
        </div>

        <div class="auth-form-row">
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-success">Register</button>
        </div>
    </form>

    <?php $authMode = 'register';
    require '../templates/social-auth-buttons.php'; ?>

    <div class="auth-link">
        Already have an account? <a href="<?php echo pageUrl('login.php'); ?>">Login here</a>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
