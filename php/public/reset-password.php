<?php

/**
 * Reset Password Page
 */

$pageTitle = 'Reset Password';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../models/User.php';

if (isAuthenticated()) {
    header('Location: ' . pageUrl('index.php'));
    exit();
}

$token = sanitizeString($_GET['token'] ?? $_POST['token'] ?? '', 128);
$error = '';
$success = '';
$reset = null;

$user = new User($conn);

if ($token === '') {
    $error = 'Reset token is missing.';
} else {
    $reset = $user->getValidPasswordReset($token);

    if (!$reset) {
        $error = 'This reset link is invalid or has expired.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!validatePassword($password)) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        $result = $user->resetPassword($token, $password);

        if ($result['success']) {
            $success = $result['message'];
            $reset = null;
        } else {
            $error = $result['message'];
        }
    }
}

require_once '../templates/header.php';
?>

<div class="auth-container" style="max-width: 440px; margin: 2.5rem auto;">
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

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group input {
            width: 100%;
            padding: 0.85rem;
            border: 1px solid #d9e2e8;
            border-radius: 6px;
            font-size: 1rem;
            background: #fbfcfd;
        }

        .password-field {
            position: relative;
        }

        .password-field input {
            padding-right: 4.75rem;
        }

        .password-toggle {
            position: absolute;
            right: 0.6rem;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #1f6f78;
            cursor: pointer;
            font: inherit;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.25rem;
        }

        .password-toggle:focus {
            outline: 2px solid rgba(31, 111, 120, 0.25);
            border-radius: 4px;
        }

        .form-group button {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 6px;
        }

        .auth-link {
            text-align: center;
            margin-top: 1rem;
        }

        .auth-link a {
            color: #3498db;
            text-decoration: none;
        }

        .auth-link a:hover {
            text-decoration: underline;
        }
    </style>

    <h1>Choose New Password</h1>
    <p class="auth-subtitle">Use at least 8 characters for your new password.</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($reset): ?>
        <form method="post">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="form-group">
                <label for="password">New Password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" required minlength="8">
                    <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password">Show</button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="password-field">
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    <button type="button" class="password-toggle" data-password-toggle="confirm_password" aria-label="Show confirm password">Show</button>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn">Reset Password</button>
            </div>
        </form>
    <?php endif; ?>

    <div class="auth-link">
        <a href="<?php echo pageUrl('login.php'); ?>">Back to login</a>
    </div>
</div>

<script>
    document.querySelectorAll('[data-password-toggle]').forEach(function(button) {
        button.addEventListener('click', function() {
            const input = document.getElementById(button.dataset.passwordToggle);
            const isHidden = input.type === 'password';

            input.type = isHidden ? 'text' : 'password';
            button.textContent = isHidden ? 'Hide' : 'Show';
            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });
</script>

<?php require_once '../templates/footer.php'; ?>
